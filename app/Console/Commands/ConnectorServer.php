<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\Server;
use App\Services\ConnectorPresenceService;
use App\Services\ConnectorQueueService;
use App\Services\RuntimeMonitoringService;
use App\Services\ServerRuntimeStateService;
use Illuminate\Console\Command;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class ConnectorServer extends Command
{
    private const DEFAULT_PANEL_TYPE = 'rex';

    protected $signature = 'app:connector-server {action=start} {--daemon}';
    protected $description = 'Start the RA-Connector WebSocket server using Workerman';

    protected $worker;
    public static array $nodeConnections = []; // nodeId:panelType:connectorId => connection
    public static array $testingConnectionStates = [];

    public function handle()
    {
        $this->info("Starting Connector WebSocket server on port 8081...");

        // Workerman needs global $argv to be set correctly
        global $argv;
        $argv[1] = $this->argument('action');
        if ($this->option('daemon')) {
            $argv[2] = '-d';
        }

        // Create a Websocket worker listening on 8081
        $this->worker = new Worker("websocket://0.0.0.0:8081");
        $this->worker->count = 1;

        // Periodic timer to check Redis for outgoing messages
        $this->worker->onWorkerStart = function() {
            // We use a timer to poll Redis list to avoid blocking
            \Workerman\Timer::add(0.1, function() {
                $msg = app(ConnectorQueueService::class)->consume();
                if ($msg) {
                    $nodeId = $msg['node_id'] ?? null;
                    $panelType = $msg['panel_type'] ?? self::DEFAULT_PANEL_TYPE;
                    $connectorId = $msg['connector_id'] ?? $nodeId;
                    $connectionKey = self::connectionKey((int) $nodeId, (string) $panelType, (int) $connectorId);
                    if ($nodeId && isset(self::$nodeConnections[$connectionKey])) {
                        self::$nodeConnections[$connectionKey]->send(json_encode($msg['payload']));
                        $this->info("Forwarded message to Node {$nodeId} via {$connectionKey}");
                    }
                }
            });
        };

        $this->worker->onConnect = function (TcpConnection $connection) {
            $this->info("New connection from " . $connection->getRemoteIp());
            $connection->authenticated = false;
            $connection->nodeId = null;
            $connection->connectorId = null;
            $connection->panelType = self::DEFAULT_PANEL_TYPE;
            $connection->presenceKey = null;
        };

        $this->worker->onMessage = function (TcpConnection $connection, $data) {
            $msg = json_decode($data, true);
            if (!$msg) return;

            if ($msg['type'] === 'auth') {
                $this->handleAuth($connection, $msg);
                return;
            }

            if (!$connection->authenticated) {
                $connection->send(json_encode(['type' => 'auth_fail', 'error' => 'Not authenticated']));
                $connection->close();
                return;
            }

            if ($msg['type'] === 'heartbeat') {
                $this->handleHeartbeat($connection->nodeId, $msg);
                return;
            }

            if (in_array($msg['type'], ['server_resource_update', 'resource_update', 'server_stats'], true)) {
                $this->handleServerResourceUpdate($msg);
                return;
            }

            if (in_array($msg['type'], ['diagnostics_result', 'connector_diagnostics'], true)) {
                $node = Node::query()->find($connection->nodeId);
                if ($node) {
                    app(RuntimeMonitoringService::class)->storeConnectorDiagnostics($node, [
                        'diagnostics' => $msg['diagnostics'] ?? $msg['payload'] ?? $msg,
                    ]);
                }
                return;
            }

            if (in_array($msg['type'], ['console_output', 'server_console_output'], true)) {
                $this->handleConsoleOutput($msg, false);
                return;
            }

            if (in_array($msg['type'], ['install_output', 'server_install_output', 'installation_output'], true)) {
                $this->handleConsoleOutput($msg, true);
                return;
            }

            if ($msg['type'] === 'resource_limits_result') {
                $this->info("Resource limits update result for Server {$msg['serverId']}: " . ($msg['success'] ? 'Success' : 'Fail'));
                return;
            }

            if ($msg['type'] === 'install_success') {
                $server = Server::query()->find($msg['serverId'] ?? null);
                if ($server) {
                    $server->update([
                        'status' => $msg['status'] ?? 'offline',
                    ]);
                    app(ServerRuntimeStateService::class)->recordInstallState($server, 'ready', $msg['message'] ?? 'Installation completed.');
                }
                $this->info("Install success for Server {$msg['serverId']}");
                return;
            }

            if ($msg['type'] === 'install_fail') {
                $server = Server::query()->find($msg['serverId'] ?? null);
                if ($server) {
                    $server->update([
                        'status' => 'error',
                    ]);
                    app(ServerRuntimeStateService::class)->recordInstallState($server, 'failed', $msg['error'] ?? 'Installation failed.');
                }
                $this->warn("Install failed for Server {$msg['serverId']}: " . ($msg['error'] ?? 'unknown error'));
                return;
            }

            if ($msg['type'] === 'server_status_update') {
                $server = Server::query()->find($msg['serverId'] ?? null);
                if ($server) {
                    $server->update([
                        'status' => $msg['status'] ?? 'offline',
                    ]);
                    app(ServerRuntimeStateService::class)->recordPowerState($server, $msg['status'] ?? 'offline');
                }
                return;
            }

            if ($msg['type'] === 'delete_success') {
                $this->info("Delete success for Server {$msg['serverId']}");
                return;
            }

            if ($msg['type'] === 'delete_fail') {
                $this->warn("Delete failed for Server {$msg['serverId']}: " . ($msg['error'] ?? 'unknown error'));
                return;
            }
        };

        $this->worker->onClose = function (TcpConnection $connection) {
            if (isset($connection->nodeId)) {
                // Mark node as offline immediately
                $node = Node::find($connection->nodeId);
                if ($node) {
                    $node->update(['last_heartbeat' => null]);
                }
                $panelType = (string) ($connection->panelType ?? self::DEFAULT_PANEL_TYPE);
                $connectorId = (int) ($connection->connectorId ?? $connection->nodeId);
                app(ConnectorPresenceService::class)->markDisconnected((int) $connection->nodeId, $panelType, $connectorId);
                unset(self::$nodeConnections[$connection->presenceKey ?? self::connectionKey((int) $connection->nodeId, $panelType, $connectorId)]);
            }
            $this->info("Connection closed for Node " . ($connection->nodeId ?? 'Unknown'));
        };

        // Run worker
        Worker::runAll();
    }

    protected function handleAuth(TcpConnection $connection, $data)
    {
        $nodeId = $data['id'] ?? null;
        $token = $data['token'] ?? null;
        $panelType = trim(strtolower((string) ($data['panelType'] ?? $data['type'] ?? self::DEFAULT_PANEL_TYPE))) ?: self::DEFAULT_PANEL_TYPE;
        $connectorId = (int) ($data['connectorId'] ?? $nodeId);

        $node = Node::find($nodeId);
        if ($node && $node->daemon_token === $token) {
            $connection->authenticated = true;
            $connection->nodeId = $nodeId;
            $connection->connectorId = $connectorId;
            $connection->panelType = $panelType;
            $connection->presenceKey = self::connectionKey((int) $nodeId, $panelType, $connectorId);
            self::$nodeConnections[$connection->presenceKey] = $connection;
            $node->update(['last_heartbeat' => now()]);
            app(ConnectorPresenceService::class)->markConnected((int) $nodeId, $panelType, $connectorId);
            
            $connection->send(json_encode(['type' => 'auth_success']));
            $this->info("Node {$nodeId} ({$node->name}) authenticated successfully for {$connection->presenceKey}.");
        } else {
            $connection->send(json_encode(['type' => 'auth_fail', 'error' => 'Invalid credentials']));
            $connection->close();
            $this->warn("Node " . ($nodeId ?? 'Unknown') . " auth failed.");
        }
    }

    protected function handleHeartbeat($nodeId, $data)
    {
        $usage = $data['usage'] ?? [];
        $node = Node::find($nodeId);
        if ($node) {
            $node->update([
                'cpu_usage' => $usage['cpu'] ?? 0,
                'cpu_total' => $usage['cpu_total'] ?? 1,
                'os' => $usage['os'] ?? 'Linux',
                'arch' => $usage['arch'] ?? 'x86_64',
                'memory_total' => $usage['memory']['total'] ?? 0,
                'memory_used' => $usage['memory']['used'] ?? 0,
                'disk_total' => $usage['disk']['total'] ?? 0,
                'disk_used' => $usage['disk']['used'] ?? 0,
                'last_heartbeat' => now(),
            ]);
            $panelType = trim(strtolower((string) ($data['panelType'] ?? $data['type'] ?? self::DEFAULT_PANEL_TYPE))) ?: self::DEFAULT_PANEL_TYPE;
            $connectorId = (int) ($data['connectorId'] ?? $nodeId);
            app(ConnectorPresenceService::class)->touch((int) $nodeId, $panelType, $connectorId);

            app(RuntimeMonitoringService::class)->ingestNodeTelemetry($node, [
                'status' => $data['status'] ?? 'healthy',
                'response_time_ms' => isset($data['response_time_ms']) ? (int) $data['response_time_ms'] : null,
                'usage' => $usage,
                'server_samples' => $data['server_samples'] ?? ($usage['servers'] ?? []),
                'diagnostics' => $data['diagnostics'] ?? $data['connector_diagnostics'] ?? null,
            ]);

            foreach (($data['server_samples'] ?? ($usage['servers'] ?? [])) as $sample) {
                $serverId = (int) ($sample['server_id'] ?? $sample['serverId'] ?? 0);
                if ($serverId < 1) {
                    continue;
                }

                $server = Server::query()->find($serverId);

                if ($server) {
                    app(ServerRuntimeStateService::class)->recordResourceSnapshot(
                        $server,
                        $sample,
                        $sample['state'] ?? $sample['status'] ?? null,
                    );
                }
            }
        }
    }

    public static function connectionKey(int $nodeId, string $panelType = self::DEFAULT_PANEL_TYPE, ?int $connectorId = null): string
    {
        $normalizedType = trim(strtolower($panelType)) ?: self::DEFAULT_PANEL_TYPE;
        $normalizedConnectorId = $connectorId ?? $nodeId;

        return $nodeId . ':' . $normalizedType . ':' . $normalizedConnectorId;
    }

    public static function isNodeConnected(int $nodeId, string $panelType = self::DEFAULT_PANEL_TYPE, ?int $connectorId = null): bool
    {
        $connectionKey = self::connectionKey($nodeId, $panelType, $connectorId);

        if (app()->environment('testing') && array_key_exists($connectionKey, self::$testingConnectionStates)) {
            return (bool) self::$testingConnectionStates[$connectionKey];
        }

        $connection = self::$nodeConnections[$connectionKey] ?? null;

        return $connection instanceof TcpConnection
            && $connection->readyState === TcpConnection::STATUS_ESTABLISHED;
    }

    public static function setTestingNodeConnectionState(int $nodeId, bool $connected, string $panelType = self::DEFAULT_PANEL_TYPE, ?int $connectorId = null): void
    {
        self::$testingConnectionStates[self::connectionKey($nodeId, $panelType, $connectorId)] = $connected;
    }

    public static function connectionForNode(int $nodeId, string $panelType = self::DEFAULT_PANEL_TYPE, ?int $connectorId = null): ?TcpConnection
    {
        return self::$nodeConnections[self::connectionKey($nodeId, $panelType, $connectorId)] ?? null;
    }

    public static function disconnectNodeConnections(
        int $nodeId,
        string $panelType = self::DEFAULT_PANEL_TYPE,
        ?int $connectorId = null,
        ?string $reason = null,
    ): int {
        $normalizedType = trim(strtolower($panelType)) ?: self::DEFAULT_PANEL_TYPE;
        $disconnected = 0;

        foreach (array_keys(self::$nodeConnections) as $connectionKey) {
            [$connectedNodeId, $connectedPanelType, $connectedConnectorId] = array_pad(explode(':', (string) $connectionKey, 3), 3, null);

            if ((int) $connectedNodeId !== $nodeId || (string) $connectedPanelType !== $normalizedType) {
                continue;
            }

            if ($connectorId !== null && (int) $connectedConnectorId !== $connectorId) {
                continue;
            }

            $connection = self::$nodeConnections[$connectionKey] ?? null;

            if ($connection && $reason !== null && method_exists($connection, 'send')) {
                try {
                    $connection->send(json_encode([
                        'type' => 'auth_fail',
                        'error' => $reason,
                    ]));
                } catch (\Throwable) {
                }
            }

            if ($connection && method_exists($connection, 'close')) {
                try {
                    $connection->close();
                } catch (\Throwable) {
                }
            }

            unset(self::$nodeConnections[$connectionKey]);
            self::setTestingNodeConnectionState($nodeId, false, $normalizedType, (int) $connectedConnectorId);
            app(ConnectorPresenceService::class)->markDisconnected($nodeId, $normalizedType, (int) $connectedConnectorId);
            $disconnected++;
        }

        return $disconnected;
    }

    private function handleServerResourceUpdate(array $message): void
    {
        $server = Server::query()->find($message['serverId'] ?? null);

        if (! $server) {
            return;
        }

        app(ServerRuntimeStateService::class)->recordResourceSnapshot(
            $server,
            $message['resource'] ?? $message,
            $message['status'] ?? $message['state'] ?? null,
        );
    }

    private function handleConsoleOutput(array $message, bool $install): void
    {
        $server = Server::query()->find($message['serverId'] ?? null);

        if (! $server) {
            return;
        }

        $output = $message['output'] ?? $message['line'] ?? $message['data'] ?? null;

        if (is_array($output)) {
            $output = implode('', $output);
        }

        if (! is_string($output) || $output === '') {
            return;
        }

        app(ServerRuntimeStateService::class)->appendConsoleOutput($server, $output, $install);

        if ($install) {
            app(ServerRuntimeStateService::class)->recordInstallState($server, 'installing', $message['message'] ?? null);
        }
    }
}
