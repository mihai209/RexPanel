<?php

namespace App\Services;

use App\Models\NodeAllocation;
use App\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServerAllocationService
{
    public function sync(Server $server, int $nodeId, int $primaryAllocationId, array $additionalAllocationIds = []): void
    {
        $assignments = collect([$primaryAllocationId, ...$additionalAllocationIds])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'allocation.default' => 'A primary allocation is required.',
            ]);
        }

        $allocations = NodeAllocation::query()
            ->whereIn('id', $assignments->all())
            ->get()
            ->keyBy('id');

        foreach ($assignments as $allocationId) {
            $allocation = $allocations->get($allocationId);
            $this->assertAssignable($allocation, $server, $nodeId);
        }

        if ($server->allocation_limit !== null && max(0, $assignments->count() - 1) > $server->allocation_limit) {
            throw ValidationException::withMessages([
                'allocation.additional' => 'Additional allocations exceed the server allocation limit.',
            ]);
        }

        NodeAllocation::query()
            ->where('server_id', $server->id)
            ->whereNotIn('id', $assignments->all())
            ->update(['server_id' => null, 'notes' => null]);

        NodeAllocation::query()
            ->whereIn('id', $assignments->all())
            ->update(['server_id' => $server->id]);

        $server->forceFill(['allocation_id' => $primaryAllocationId])->save();
    }

    public function assign(Server $server, int $allocationId, ?string $notes = null): NodeAllocation
    {
        $allocation = NodeAllocation::query()->findOrFail($allocationId);
        $this->assertAssignable($allocation, $server, $server->node_id);

        $additionalCount = $server->allocations()->whereKeyNot($server->allocation_id)->count();
        if ($server->allocation_limit !== null && $allocation->id !== $server->allocation_id && $additionalCount >= $server->allocation_limit) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Additional allocations exceed the server allocation limit.',
            ]);
        }

        $allocation->forceFill([
            'server_id' => $server->id,
            'notes' => $notes,
        ])->save();

        return $allocation;
    }

    public function setPrimary(Server $server, NodeAllocation $allocation): void
    {
        $this->assertOwnedByServer($server, $allocation);
        $server->forceFill(['allocation_id' => $allocation->id])->save();
    }

    public function updateAllocation(Server $server, NodeAllocation $allocation, array $data): NodeAllocation
    {
        $this->assertOwnedByServer($server, $allocation);
        $allocation->fill([
            'notes' => $data['notes'] ?? $allocation->notes,
        ])->save();

        return $allocation;
    }

    public function remove(Server $server, NodeAllocation $allocation): void
    {
        $this->assertOwnedByServer($server, $allocation);

        if ((int) $server->allocation_id === (int) $allocation->id) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Primary allocation cannot be removed. Set another primary allocation first.',
            ]);
        }

        $allocation->forceFill(['server_id' => null, 'notes' => null])->save();
    }

    public function releaseAll(Server $server): void
    {
        NodeAllocation::query()
            ->where('server_id', $server->id)
            ->update(['server_id' => null, 'notes' => null]);
    }

    public function availableForServer(Server $server): Collection
    {
        return NodeAllocation::query()
            ->where('node_id', $server->node_id)
            ->where(function ($query) use ($server) {
                $query->whereNull('server_id')
                    ->orWhere('server_id', $server->id);
            })
            ->orderBy('ip')
            ->orderBy('port')
            ->get();
    }

    private function assertAssignable(?NodeAllocation $allocation, Server $server, int $nodeId): void
    {
        if (! $allocation) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Allocation was not found.',
            ]);
        }

        if ((int) $allocation->node_id !== $nodeId) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Allocation does not belong to the selected node.',
            ]);
        }

        if ($allocation->server_id !== null && (int) $allocation->server_id !== (int) $server->id) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Allocation is already assigned to another server.',
            ]);
        }
    }

    private function assertOwnedByServer(Server $server, NodeAllocation $allocation): void
    {
        if ((int) $allocation->server_id !== (int) $server->id) {
            throw ValidationException::withMessages([
                'allocation_id' => 'Allocation is not assigned to this server.',
            ]);
        }
    }
}
