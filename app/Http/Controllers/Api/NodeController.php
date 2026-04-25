<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\ServiceHealthCheck;
use App\Services\ConnectorQueueService;
use App\Services\NodeHealthService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    private const CONNECTOR_PANEL_TYPE = 'rex';

    public function __construct(
        private SystemSettingsService $settings,
        private NodeHealthService $nodeHealth,
        private ConnectorQueueService $queue,
    )
    {
    }

    /**
     * Display a listing of allocations for a node.
     */
    public function listAllocations(Node $node): JsonResponse
    {
        return response()->json($node->allocations()->with('server')->get());
    }

    /**
     * Create new allocations for a node.
     */
    public function createAllocations(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'ip' => 'required|string',
            'alias' => 'nullable|string',
            'ports' => 'required|string', // comma or space separated
        ]);

        $ports = preg_split('/[\s,]+/', $data['ports'], -1, PREG_SPLIT_NO_EMPTY);
        $created = 0;

        foreach ($ports as $portGroup) {
            if (str_contains($portGroup, '-')) {
                $range = explode('-', $portGroup);
                if (count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1])) {
                    $start = (int) $range[0];
                    $end = (int) $range[1];
                    for ($p = min($start, $end); $p <= max($start, $end); $p++) {
                        if ($p < 1 || $p > 65535) continue;
                        if ($this->createSingleAlloc($node->id, $data['ip'], $data['alias'], $p)) $created++;
                    }
                }
            } else {
                if (is_numeric($portGroup)) {
                    $p = (int) $portGroup;
                    if ($p >= 1 && $p <= 65535) {
                        if ($this->createSingleAlloc($node->id, $data['ip'], $data['alias'], $p)) $created++;
                    }
                }
            }
        }

        return response()->json([
            'message' => "Successfully created $created allocations.",
        ]);
    }

    private function createSingleAlloc($nodeId, $ip, $alias, $port)
    {
        try {
            NodeAllocation::create([
                'node_id' => $nodeId,
                'ip' => $ip,
                'ip_alias' => $alias,
                'port' => $port,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an allocation.
     */
    public function deleteAllocation(Node $node, NodeAllocation $allocation): JsonResponse
    {
        if ($allocation->node_id !== $node->id) {
            return response()->json(['message' => 'Invalid node for this allocation.'], 403);
        }

        if ($allocation->server_id) {
            return response()->json(['message' => 'Cannot delete an assigned allocation.'], 400);
        }

        $allocation->delete();

        return response()->json(['message' => 'Allocation deleted successfully.']);
    }

    /**
     * Display a listing of nodes.
     */
    public function index(): JsonResponse
    {
        $nodes = Node::with('location')->get();

        // For each node, attach allocated resources (sum of servers)
        $allocations = \App\Models\Server::selectRaw('node_id, SUM(memory) as memory, SUM(disk) as disk, COUNT(*) as server_count')
            ->groupBy('node_id')
            ->get()
            ->keyBy('node_id');

        $result = $nodes->map(function ($node) use ($allocations) {
            $alloc = $allocations->get($node->id);
            $node->allocated_memory = (int) ($alloc->memory ?? 0);
            $node->allocated_disk = (int) ($alloc->disk ?? 0);
            $node->server_count = (int) ($alloc->server_count ?? 0);
            $node->health = $this->nodeHealth->summarize($node);
            return $node;
        });

        return response()->json($result);
    }

    /**
     * Store a newly created node.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'fqdn' => 'required|string',
            'daemon_port' => 'required|integer',
            'daemon_sftp_port' => 'required|integer',
            'is_public' => 'boolean',
            'memory_limit' => 'required|integer|min:1',
            'memory_overallocate' => 'required|integer|min:0',
            'disk_limit' => 'required|integer|min:1',
            'disk_overallocate' => 'required|integer|min:0',
            'daemon_base' => 'required|string',
        ]);

        $data['daemon_token'] = \Illuminate\Support\Str::random(32);

        $node = Node::create($data);

        return response()->json([
            'message' => 'Node created successfully.',
            'node' => $node->load('location'),
        ]);
    }

    /**
     * Display the specified node.
     */
    public function show(Node $node): JsonResponse
    {
        $node->load('location');
        
        // Calculate allocated resources
        $allocated = \App\Models\Server::where('node_id', $node->id)
            ->selectRaw('SUM(memory) as memory, SUM(disk) as disk, COUNT(*) as count')
            ->first();

        // Get hosted servers
        $servers = \App\Models\Server::where('node_id', $node->id)->with('owner')->get();

        return response()->json([
            'node' => $node,
            'allocated' => [
                'memory' => (int) ($allocated->memory ?? 0),
                'disk' => (int) ($allocated->disk ?? 0),
                'server_count' => (int) ($allocated->count ?? 0),
            ],
            'health' => $this->nodeHealth->summarize($node),
            'servers' => $servers,
            'system' => [
                'type' => $node->os ?? 'Linux',
                'arch' => $node->arch ?? 'x86_64',
                'release' => php_uname('r'), 
                'cpus' => $node->cpu_total ?? 1,
                'memory' => [
                    'total' => round(($node->memory_total ?? 0) / 1024, 1),
                    'used' => round(($node->memory_used ?? 0) / 1024, 1),
                ],
                'disk' => [
                    'total' => round(($node->disk_total ?? 0) / 1024, 1),
                    'used' => round(($node->disk_used ?? 0) / 1024, 1),
                ],
            ]
        ]);
    }

    /**
     * Update the specified node.
     */
    public function update(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'fqdn' => 'required|string',
            'daemon_port' => 'required|integer',
            'daemon_sftp_port' => 'required|integer',
            'daemon_token' => 'required|string',
            'is_public' => 'boolean',
            'maintenance_mode' => 'boolean',
            'memory_limit' => 'required|integer|min:1',
            'memory_overallocate' => 'required|integer|min:0',
            'disk_limit' => 'required|integer|min:1',
            'disk_overallocate' => 'required|integer|min:0',
            'daemon_base' => 'required|string',
        ]);

        $node->update($data);

        return response()->json([
            'message' => 'Node updated successfully.',
            'node' => $node->load('location'),
        ]);
    }

    /**
     * Get the connector configuration JSON.
     */
    public function configuration(Node $node): JsonResponse
    {
        $runtime = $this->settings->connectorConfigValues();
        $sftpEnabled = (bool) ($runtime['sftp']['enabled'] ?? true);
        $panelUrl = rtrim((string) config('app.url'), '/');
        $wsUrl = $this->connectorWebsocketUrl($panelUrl);
        $panelEntry = [
            'type' => self::CONNECTOR_PANEL_TYPE,
            'namespace' => self::CONNECTOR_PANEL_TYPE,
            'panel' => [
                'url' => $panelUrl,
                'ws_url' => $wsUrl,
                'allowedUrls' => [$panelUrl],
                'allowedOrigins' => [$panelUrl],
            ],
            'connector' => [
                'id' => $node->id,
                'token' => $node->daemon_token,
                'name' => $node->name,
            ],
        ];
        $panels = [$panelEntry];
        $sharedRockyEntry = $this->sharedRockyPanelEntryFromEnv();
        if ($sharedRockyEntry !== null) {
            $panels[] = $sharedRockyEntry;
        }

        $config = [
            'panel' => [
                'url' => $panelUrl,
                'ws_url' => $wsUrl,
            ],
            'connector' => [
                'id' => $node->id,
                'token' => $node->daemon_token,
                'name' => $node->name,
                'host' => $runtime['api']['host'] ?? '0.0.0.0',
                'port' => $node->daemon_port,
            ],
            'panels' => $panels,
            'docker' => [
                'socket' => '/var/run/docker.sock',
                'base' => $node->daemon_base,
                'rootless' => $runtime['docker']['rootless'] ?? [
                    'enabled' => false,
                    'container_uid' => 0,
                    'container_gid' => 0,
                ],
            ],
            'sftp' => [
                'enabled' => $sftpEnabled,
                'port' => $sftpEnabled ? $node->daemon_sftp_port : null,
                'directory' => $node->daemon_base . '/volumes',
                'readOnly' => (bool) ($runtime['sftp']['readOnly'] ?? false),
            ],
            'api' => [
                'host' => $runtime['api']['host'] ?? '0.0.0.0',
                'port' => $node->daemon_port,
                'trustedProxies' => $runtime['api']['trustedProxies'] ?? [],
                'ssl' => $runtime['api']['ssl'] ?? [
                    'enabled' => false,
                    'cert' => '',
                    'key' => '',
                ],
            ],
            'system' => $runtime['system'] ?? [
                'diskCheckTtlSeconds' => 10,
            ],
            'monitoring' => $runtime['monitoring'] ?? [],
            'transfers' => $runtime['transfers'] ?? [
                'downloadLimit' => 0,
            ],
            'throttles' => $runtime['throttles'] ?? [
                'enabled' => true,
                'lines' => 2000,
                'lineResetInterval' => 100,
            ],
            'crashPolicy' => $runtime['crashPolicy'] ?? [
                'enabled' => true,
                'detectCleanExitAsCrash' => true,
                'cooldownSeconds' => 60,
            ],
            'features' => $runtime['features'] ?? [
                'sftpEnabled' => true,
                'webUploadEnabled' => true,
                'webUploadMaxMb' => 50,
                'remoteDownloadEnabled' => true,
            ],
        ];

        return response()->json($config);
    }

    /**
     * Regenerate the daemon token — disconnects live connector immediately.
     */
    public function regenerateToken(Node $node): JsonResponse
    {
        $newToken = bin2hex(random_bytes(20)); // 40-char hex token

        $node->update([
            'daemon_token' => $newToken,
            'last_heartbeat' => null, // mark offline immediately
        ]);

        \App\Console\Commands\ConnectorServer::disconnectNodeConnections(
            $node->id,
            self::CONNECTOR_PANEL_TYPE,
            null,
            'Token was regenerated. Update config.json and restart the connector.',
        );

        return response()->json([
            'message' => 'Token regenerated. Connector has been disconnected.',
            'token'   => $newToken,
        ]);
    }

    public function runDiagnostics(Node $node): JsonResponse
    {
        $health = $this->nodeHealth->summarize($node);

        if (! $health['is_active']) {
            return response()->json([
                'message' => 'Diagnostics run is blocked because the connector is offline.',
                'code' => 'node_offline',
                'node_health' => $health,
            ], 409);
        }

        $published = $this->queue->publish([
            'node_id' => $node->id,
            'panel_type' => self::CONNECTOR_PANEL_TYPE,
            'connector_id' => $node->id,
            'payload' => [
                'type' => 'run_diagnostics',
                'nodeId' => $node->id,
                'requestId' => 'panel-' . uniqid(),
            ],
        ]);

        if (! $published) {
            return response()->json([
                'message' => 'Diagnostics request could not be queued.',
            ], 503);
        }

        return response()->json([
            'message' => 'Diagnostics request queued.',
        ]);
    }

    /**
     * Remove the specified node.
     */
    public function destroy(Node $node): JsonResponse
    {
        $node->delete();
        return response()->json(['message' => 'Node deleted successfully.']);
    }

    private function latestNodeHealth(int $nodeId): ?array
    {
        $health = ServiceHealthCheck::query()
            ->where('node_id', $nodeId)
            ->whereNull('server_id')
            ->latest('checked_at')
            ->first();

        if (! $health) {
            return null;
        }

        return [
            'status' => $health->status,
            'responseTimeMs' => $health->response_time_ms,
            'checkedAt' => optional($health->checked_at)?->toIso8601String(),
            'metadata' => $health->metadata,
        ];
    }

    private function connectorWebsocketUrl(string $panelUrl): string
    {
        $parsed = parse_url($panelUrl);
        $scheme = (($parsed['scheme'] ?? 'http') === 'https') ? 'wss' : 'ws';
        $host = $parsed['host'] ?? 'localhost';

        return sprintf('%s://%s:%d', $scheme, $host, 8081);
    }

    private function sharedRockyPanelEntryFromEnv(): ?array
    {
        $url = trim((string) env('CONNECTOR_SHARED_ROCKY_URL', ''));
        $connectorId = (int) env('CONNECTOR_SHARED_ROCKY_ID', 0);
        $token = trim((string) env('CONNECTOR_SHARED_ROCKY_TOKEN', ''));

        if ($url === '' || $connectorId < 1 || $token === '') {
            return null;
        }

        $wsUrl = trim((string) env('CONNECTOR_SHARED_ROCKY_WS_URL', ''));
        $name = trim((string) env('CONNECTOR_SHARED_ROCKY_NAME', 'CPanel Rocky'));
        $allowedUrls = collect(preg_split('/[\r\n,]+/', (string) env('CONNECTOR_SHARED_ROCKY_ALLOWED_URLS', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values()
            ->all();

        if ($allowedUrls === []) {
            $allowedUrls = [$url];
        }

        return [
            'type' => 'rocky',
            'namespace' => trim((string) env('CONNECTOR_SHARED_ROCKY_NAMESPACE', 'rocky')) ?: 'rocky',
            'panel' => array_filter([
                'url' => $url,
                'ws_url' => $wsUrl !== '' ? $wsUrl : null,
                'allowedUrls' => $allowedUrls,
                'allowedOrigins' => $allowedUrls,
            ], fn ($value) => $value !== null),
            'connector' => [
                'id' => $connectorId,
                'token' => $token,
                'name' => $name !== '' ? $name : 'CPanel Rocky',
            ],
        ];
    }
}
