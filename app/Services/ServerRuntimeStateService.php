<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerRuntimeState;

class ServerRuntimeStateService
{
    private const MAX_BUFFER_LENGTH = 60000;

    public function __construct(private UiWebsocketRedisService $uiWebsocket)
    {
    }

    public function snapshot(Server $server): array
    {
        $server->loadMissing('runtimeState');

        $state = $server->runtimeState;
        $installState = $this->normalizeInstallState($server, $state);

        return [
            'power_state' => $state?->power_state ?: $server->status,
            'install_state' => $installState,
            'install_message' => $state?->install_message,
            'resource_snapshot' => $state?->resource_snapshot,
            'console_output' => $state?->console_output ?? '',
            'install_output' => $state?->install_output ?? '',
            'last_resource_at' => $state?->last_resource_at?->toIso8601String(),
            'last_console_at' => $state?->last_console_at?->toIso8601String(),
            'last_install_output_at' => $state?->last_install_output_at?->toIso8601String(),
            'is_installing' => in_array($installState, ['installing', 'reinstalling'], true),
            'has_install_error' => $installState === 'failed',
        ];
    }

    public function featureAvailability(Server $server, array $nodeHealth): array
    {
        $runtime = $this->snapshot($server);
        $installing = $runtime['is_installing'];
        $installFailed = $runtime['has_install_error'];
        $connectorAvailable = (bool) ($nodeHealth['is_active'] ?? false);

        return [
            'console' => $connectorAvailable && ! $installing,
            'power' => $connectorAvailable && ! $installing,
            'command' => $connectorAvailable && ! $installing && ! $installFailed,
            'live_stats' => $connectorAvailable,
            'install_screen' => $installing || $installFailed,
        ];
    }

    public function recordResourceSnapshot(Server $server, array $snapshot, ?string $powerState = null): array
    {
        $state = $this->stateModel($server);
        $state->forceFill([
            'resource_snapshot' => $this->normalizeResourceSnapshot($snapshot),
            'last_resource_at' => now(),
            'power_state' => $powerState ?: $state->power_state ?: $server->status,
        ])->save();

        $payload = [
            'type' => 'server:resource-update',
            'server' => $this->serverIdentityPayload($server),
            'resource' => $state->resource_snapshot,
            'powerState' => $state->power_state,
            'at' => $state->last_resource_at?->toIso8601String(),
        ];

        $this->publishToOwner($server, $payload);

        return $payload;
    }

    public function recordPowerState(Server $server, string $powerState): array
    {
        $state = $this->stateModel($server);
        $state->forceFill([
            'power_state' => $powerState,
        ])->save();

        $payload = [
            'type' => 'server:power-state',
            'server' => $this->serverIdentityPayload($server),
            'powerState' => $powerState,
        ];

        $this->publishToOwner($server, $payload);

        return $payload;
    }

    public function recordInstallState(Server $server, string $installState, ?string $message = null, ?string $output = null): array
    {
        $state = $this->stateModel($server);
        $attributes = [
            'install_state' => $installState,
            'install_message' => $message,
        ];

        if ($output !== null && $output !== '') {
            $attributes['install_output'] = $this->appendBuffer($state->install_output, $output);
            $attributes['last_install_output_at'] = now();
        }

        $state->forceFill($attributes)->save();

        $payload = [
            'type' => 'server:install-state',
            'server' => $this->serverIdentityPayload($server),
            'installState' => $installState,
            'message' => $message,
            'installOutput' => $state->install_output ?? '',
            'at' => $state->last_install_output_at?->toIso8601String(),
        ];

        $this->publishToOwner($server, $payload);

        return $payload;
    }

    public function appendConsoleOutput(Server $server, string $output, bool $install = false): array
    {
        $state = $this->stateModel($server);
        $timestampColumn = $install ? 'last_install_output_at' : 'last_console_at';
        $bufferColumn = $install ? 'install_output' : 'console_output';

        $state->forceFill([
            $bufferColumn => $this->appendBuffer($state->{$bufferColumn}, $output),
            $timestampColumn => now(),
        ])->save();

        $payload = [
            'type' => $install ? 'server:install-output' : 'server:console',
            'server' => $this->serverIdentityPayload($server),
            'output' => $output,
            'fullOutput' => $state->{$bufferColumn},
            'at' => $state->{$timestampColumn}?->toIso8601String(),
        ];

        $this->publishToOwner($server, $payload);

        return $payload;
    }

    private function stateModel(Server $server): ServerRuntimeState
    {
        $server->loadMissing('runtimeState');

        if ($server->runtimeState) {
            return $server->runtimeState;
        }

        $state = ServerRuntimeState::query()->create([
            'server_id' => $server->id,
        ]);

        $server->setRelation('runtimeState', $state);

        return $state;
    }

    private function normalizeInstallState(Server $server, ?ServerRuntimeState $state): ?string
    {
        if ($state?->install_state) {
            return $state->install_state;
        }

        return match (strtolower((string) $server->status)) {
            'installing' => 'installing',
            'reinstalling' => 'reinstalling',
            'error', 'install_failed', 'failed_install' => 'failed',
            default => null,
        };
    }

    private function normalizeResourceSnapshot(array $snapshot): array
    {
        $memory = is_array($snapshot['memory'] ?? null) ? $snapshot['memory'] : [];
        $disk = is_array($snapshot['disk'] ?? null) ? $snapshot['disk'] : [];
        $network = is_array($snapshot['network'] ?? null) ? $snapshot['network'] : [];

        return [
            'cpu_percent' => (float) ($snapshot['cpu_percent'] ?? $snapshot['cpuPercent'] ?? $snapshot['cpu'] ?? 0),
            'memory_bytes' => (int) ($snapshot['memory_bytes'] ?? $snapshot['memoryBytes'] ?? $memory['used'] ?? 0),
            'memory_limit_bytes' => (int) ($snapshot['memory_limit_bytes'] ?? $snapshot['memoryLimitBytes'] ?? $memory['limit'] ?? $memory['total'] ?? 0),
            'disk_bytes' => (int) ($snapshot['disk_bytes'] ?? $snapshot['diskBytes'] ?? $disk['used'] ?? 0),
            'disk_limit_bytes' => (int) ($snapshot['disk_limit_bytes'] ?? $snapshot['diskLimitBytes'] ?? $disk['limit'] ?? $disk['total'] ?? 0),
            'network_rx_bytes' => (int) ($snapshot['network_rx_bytes'] ?? $snapshot['networkRxBytes'] ?? $network['rx'] ?? 0),
            'network_tx_bytes' => (int) ($snapshot['network_tx_bytes'] ?? $snapshot['networkTxBytes'] ?? $network['tx'] ?? 0),
            'uptime_seconds' => (int) ($snapshot['uptime_seconds'] ?? $snapshot['uptimeSeconds'] ?? $snapshot['uptime'] ?? 0),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function appendBuffer(?string $existing, string $chunk): string
    {
        $buffer = (string) $existing . $chunk;

        if (strlen($buffer) <= self::MAX_BUFFER_LENGTH) {
            return $buffer;
        }

        return substr($buffer, -1 * self::MAX_BUFFER_LENGTH);
    }

    private function serverIdentityPayload(Server $server): array
    {
        return [
            'id' => $server->id,
            'routeId' => $server->route_id,
            'containerId' => $server->route_id,
            'uuid' => $server->uuid,
            'name' => $server->name,
        ];
    }

    private function publishToOwner(Server $server, array $payload): void
    {
        $this->uiWebsocket->publishUserEvent($server->user_id, $payload);
    }
}
