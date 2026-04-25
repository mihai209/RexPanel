<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Services\NodeHealthService;
use Illuminate\Http\JsonResponse;

class AdminConnectorLabController extends Controller
{
    private const CHECKS = [
        'docker_access' => 'Docker access',
        'dns' => 'DNS',
        'udp_bind' => 'UDP bind',
        'sftp_auth' => 'SFTP auth',
        'websocket_payload_size' => 'Websocket payload size',
        'disk_permissions' => 'Disk permissions',
        'image_pull' => 'Image pull',
        'archive_tools' => 'Archive tools',
        'java_runtime' => 'Java runtime',
        'node_runtime' => 'Node runtime',
    ];

    public function __construct(private NodeHealthService $health)
    {
    }

    public function index(): JsonResponse
    {
        $nodes = Node::query()
            ->with('location')
            ->withCount(['servers', 'allocations'])
            ->orderBy('name')
            ->get();

        $cards = $nodes->map(fn (Node $node) => $this->serializeNodeCard($node))->values();

        return response()->json([
            'connectors' => $cards,
            'triage' => [
                'healthy' => $cards->where('health.status', 'healthy')->count(),
                'degraded' => $cards->where('health.status', 'degraded')->count(),
                'offline' => $cards->where('health.status', 'offline')->count(),
                'with_diagnostics' => $cards->filter(fn ($card) => $card['diagnostics_updated_at'] !== null)->count(),
            ],
        ]);
    }

    public function show(Node $node): JsonResponse
    {
        $node->load('location')->loadCount(['servers', 'allocations']);

        return response()->json([
            'connector' => $this->serializeNodeCard($node, true),
        ]);
    }

    private function serializeNodeCard(Node $node, bool $detail = false): array
    {
        $health = $this->health->summarize($node);
        $checks = $this->compatibilityChecks($node->connector_diagnostics ?? []);

        $data = [
            'id' => $node->id,
            'name' => $node->name,
            'fqdn' => $node->fqdn,
            'location' => $node->location ? [
                'id' => $node->location->id,
                'name' => $node->location->name,
                'short_name' => $node->location->short_name,
                'shortName' => $node->location->short_name,
                'image_url' => $node->location->image_url,
                'imageUrl' => $node->location->image_url,
            ] : null,
            'health' => $health,
            'server_count' => (int) ($node->servers_count ?? 0),
            'allocation_count' => (int) ($node->allocations_count ?? 0),
            'diagnostics_updated_at' => optional($node->diagnostics_updated_at)?->toIso8601String(),
            'triage' => [
                'failed_checks' => collect($checks)->where('status', 'failed')->count(),
                'warning_checks' => collect($checks)->where('status', 'warning')->count(),
                'passing_checks' => collect($checks)->where('status', 'passed')->count(),
            ],
            'compatibility_checks' => $checks,
        ];

        if ($detail) {
            $data['raw_diagnostics'] = $node->connector_diagnostics;
        }

        return $data;
    }

    private function compatibilityChecks(array $diagnostics): array
    {
        $normalizedChecks = [];

        foreach ((array) ($diagnostics['checks'] ?? $diagnostics['compatibility_checks'] ?? []) as $key => $value) {
            $checkKey = is_string($key) ? $key : (string) ($value['key'] ?? $value['name'] ?? '');
            if ($checkKey === '') {
                continue;
            }

            $normalizedChecks[$checkKey] = is_array($value) ? $value : ['status' => $value];
        }

        return collect(self::CHECKS)->map(function (string $label, string $key) use ($normalizedChecks) {
            $raw = $normalizedChecks[$key] ?? [];
            $status = strtolower((string) ($raw['status'] ?? $raw['result'] ?? 'unknown'));

            $status = match ($status) {
                'ok', 'pass', 'passed', 'success', 'healthy' => 'passed',
                'warn', 'warning', 'degraded' => 'warning',
                'fail', 'failed', 'error', 'offline' => 'failed',
                default => $status === '' ? 'unknown' : $status,
            };

            return [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'message' => $raw['message'] ?? $raw['detail'] ?? null,
                'metadata' => $raw,
            ];
        })->values()->all();
    }
}
