<?php

namespace App\Services;

use App\Models\User;

class InventoryStateService
{
    public function __construct(
        private CommerceCatalogStore $store,
        private CommerceCompatibilityService $compatibility,
    )
    {
    }

    public function getState(User $user): array
    {
        return $this->store->getUserInventoryState($user);
    }

    public function grant(User $user, array $delta, array $purchaseMeta = []): array
    {
        $state = $this->getState($user);
        $state['resources'] = $this->mergeResources($state['resources'] ?? [], $delta);

        if ($purchaseMeta !== []) {
            $state['purchases'][] = [
                ...$purchaseMeta,
                'grantedResources' => $this->sanitizeResources($delta),
                'createdAtMs' => now()->valueOf(),
                'createdAt' => now()->toIso8601String(),
            ];
        }

        $state['updatedAtMs'] = now()->valueOf();
        $state['updatedAt'] = now()->toIso8601String();
        $this->store->putUserInventoryState($user, $state);

        return $state;
    }

    public function consume(User $user, array $delta): array
    {
        $state = $this->getState($user);
        $resources = $state['resources'] ?? [];

        foreach ($this->sanitizeResources($delta) as $key => $value) {
            $current = (int) ($resources[$key] ?? 0);

            if ($current < $value) {
                abort(response()->json([
                    'message' => sprintf('Inventory does not have enough %s.', $key),
                ], 422));
            }

            $resources[$key] = $current - $value;
        }

        $state['resources'] = array_replace($this->store->defaultResourceState(), $resources);
        $state['updatedAtMs'] = now()->valueOf();
        $state['updatedAt'] = now()->toIso8601String();
        $this->store->putUserInventoryState($user, $state);

        return $state;
    }

    public function effectiveLimits(User $user): array
    {
        return array_replace(
            $this->store->defaultResourceState(),
            $this->getState($user)['resources'] ?? []
        );
    }

    public function sanitizeResources(array $resources): array
    {
        return $this->compatibility->normalizeResources($resources);
    }

    private function mergeResources(array $base, array $delta): array
    {
        $current = array_replace($this->store->defaultResourceState(), $base);

        foreach ($this->sanitizeResources($delta) as $key => $value) {
            $current[$key] = max(0, (int) ($current[$key] ?? 0) + $value);
        }

        return $current;
    }
}
