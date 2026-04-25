<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\ServiceHealthCheck;
use App\Services\NodeHealthService;
use App\Services\RuntimeMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceHealthCheckController extends Controller
{
    public function __construct(
        private RuntimeMonitoringService $monitoring,
        private NodeHealthService $health,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ServiceHealthCheck::query()
            ->with('node:id,name')
            ->whereNull('server_id')
            ->orderByDesc('checked_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('node_id')) {
            $query->where('node_id', (int) $request->integer('node_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('checked_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('checked_at', '<=', $request->string('date_to'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 25)));
    }

    public function latest(): JsonResponse
    {
        $records = ServiceHealthCheck::query()
            ->with('node:id,name')
            ->whereNull('server_id')
            ->orderByDesc('checked_at')
            ->get()
            ->unique('node_id')
            ->values()
            ->map(fn (ServiceHealthCheck $check) => $this->serializeCheck($check));

        return response()->json([
            'data' => $records,
        ]);
    }

    public function run(): JsonResponse
    {
        $results = Node::query()
            ->with('location')
            ->orderBy('name')
            ->get()
            ->map(function (Node $node) {
                $summary = $this->health->summarize($node);

                $check = $this->monitoring->recordHealthCheck(
                    nodeId: $node->id,
                    serverId: null,
                    status: $summary['status'],
                    responseTimeMs: null,
                    metadata: [
                        'source' => 'manual_admin_run',
                        'reasons' => $summary['reasons'],
                        'node_health' => $summary,
                    ],
                    checkedAt: now(),
                );

                return $this->serializeCheck($check->load('node:id,name'));
            })
            ->values();

        return response()->json([
            'message' => 'Manual service health checks completed.',
            'checks' => $results,
        ]);
    }

    private function serializeCheck(ServiceHealthCheck $check): array
    {
        return [
            'id' => $check->id,
            'node_id' => $check->node_id,
            'status' => $check->status,
            'response_time_ms' => $check->response_time_ms,
            'checked_at' => optional($check->checked_at)?->toIso8601String(),
            'metadata' => $check->metadata,
            'node' => $check->node ? [
                'id' => $check->node->id,
                'name' => $check->node->name,
            ] : null,
        ];
    }
}
