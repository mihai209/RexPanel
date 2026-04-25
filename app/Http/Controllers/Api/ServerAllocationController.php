<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NodeAllocation;
use App\Models\Server;
use App\Services\ServerAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerAllocationController extends Controller
{
    public function __construct(private ServerAllocationService $allocations)
    {
    }

    public function index(Server $server): JsonResponse
    {
        $server->load(['primaryAllocation', 'allocations']);
        $available = $this->allocations->availableForServer($server);

        return response()->json([
            'allocation_id' => $server->allocation_id,
            'primary_allocation' => $server->primaryAllocation,
            'allocations' => $server->allocations()
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$server->allocation_id])
                ->get()
                ->map(fn ($allocation) => [
                    'id' => $allocation->id,
                    'node_id' => $allocation->node_id,
                    'ip' => $allocation->ip,
                    'ip_alias' => $allocation->ip_alias,
                    'port' => $allocation->port,
                    'notes' => $allocation->notes,
                    'server_id' => $allocation->server_id,
                    'is_primary' => (int) $allocation->id === (int) $server->allocation_id,
                ]),
            'available_allocations' => $available->map(fn ($allocation) => [
                'id' => $allocation->id,
                'node_id' => $allocation->node_id,
                'ip' => $allocation->ip,
                'ip_alias' => $allocation->ip_alias,
                'port' => $allocation->port,
                'notes' => $allocation->notes,
                'server_id' => $allocation->server_id,
            ])->values(),
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
        ]);
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'allocation_id' => 'nullable|integer|exists:node_allocations,id',
            'allocation_ids' => 'nullable|array',
            'allocation_ids.*' => 'integer|exists:node_allocations,id',
            'notes' => 'nullable|string',
        ]);

        $targetIds = collect($data['allocation_ids'] ?? [$data['allocation_id'] ?? null])
            ->filter()
            ->values();

        foreach ($targetIds as $allocationId) {
            $this->allocations->assign($server, (int) $allocationId, $data['notes'] ?? null);
        }

        return response()->json([
            'message' => 'Allocation assigned successfully.',
            ...$this->index($server->fresh())->getData(true),
        ], 201);
    }

    public function setPrimary(Server $server, NodeAllocation $allocation): JsonResponse
    {
        $this->allocations->setPrimary($server, $allocation);

        return response()->json([
            'message' => 'Primary allocation updated successfully.',
            ...$this->index($server->fresh())->getData(true),
        ]);
    }

    public function update(Request $request, Server $server, NodeAllocation $allocation): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $this->allocations->updateAllocation($server, $allocation, $data);

        return response()->json([
            'message' => 'Allocation updated successfully.',
            ...$this->index($server->fresh())->getData(true),
        ]);
    }

    public function destroy(Server $server, NodeAllocation $allocation): JsonResponse
    {
        $this->allocations->remove($server, $allocation);

        return response()->json([
            'message' => 'Allocation unassigned successfully.',
            ...$this->index($server->fresh())->getData(true),
        ]);
    }
}
