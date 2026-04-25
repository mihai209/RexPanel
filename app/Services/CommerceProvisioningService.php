<?php

namespace App\Services;

use App\Models\Image;
use App\Models\User;

class CommerceProvisioningService
{
    public function __construct(
        private SystemSettingsService $settings,
        private RevenueModeService $revenue,
        private InventoryStateService $inventory,
    ) {
    }

    public function effectiveLimits(User $user): array
    {
        $runtime = $this->settings->commerceRuntimeValues();
        $revenue = $runtime['features']['revenueModeEnabled']
            ? $this->revenue->effectiveProvisioningLimits($user)
            : null;

        if (is_array($revenue)) {
            return [
                'source' => 'revenue_plan',
                'limits' => $revenue,
            ];
        }

        if (! $runtime['features']['inventoryModeEnabled']) {
            return [
                'source' => 'none',
                'limits' => $this->inventory->sanitizeResources([]),
            ];
        }

        return [
            'source' => 'inventory',
            'limits' => $this->inventory->effectiveLimits($user),
        ];
    }

    public function applyFallbackLimits(User $user, array $normalized): array
    {
        $effective = $this->effectiveLimits($user);
        $limits = $effective['limits'];

        if ($effective['source'] === 'revenue_plan') {
            $this->assertRevenuePlanCapacity($user, $normalized);
        }

        foreach ([
            'cpu' => 'cpuPercent',
            'memory' => 'ramMb',
            'disk' => 'diskMb',
            'swap' => 'swapMb',
        ] as $field => $resource) {
            if (($normalized[$field] ?? null) === null && ($limits[$resource] ?? 0) > 0) {
                $normalized[$field] = (int) $limits[$resource];
            }
        }

        if (($normalized['database_limit'] ?? null) === null && ($limits['databases'] ?? 0) > 0) {
            $normalized['database_limit'] = (int) $limits['databases'];
        }

        if (($normalized['allocation_limit'] ?? null) === null && ($limits['allocations'] ?? 0) > 0) {
            $normalized['allocation_limit'] = (int) $limits['allocations'];
        }

        if (($normalized['backup_limit'] ?? null) === null && ($limits['backups'] ?? 0) > 0) {
            $normalized['backup_limit'] = (int) $limits['backups'];
        }

        $normalized['commerce_provisioning'] = $effective;

        return $normalized;
    }

    public function previewCreateCosts(array $normalized, ?Image $image = null): array
    {
        $runtime = $this->settings->commerceRuntimeValues();
        $pricing = $runtime['pricing']['unitCosts'];
        $swapGb = max(0, (int) ($normalized['swap'] ?? 0)) / 1024;
        $databaseLimit = max(0, (int) ($normalized['database_limit'] ?? 0));
        $hasImage = $image !== null;
        $hasPackage = $image?->package_id !== null;

        $resourcesCost = (
            ((max(0, (int) ($normalized['memory'] ?? 0)) / 1024) * (float) ($pricing['ramGb'] ?? 0))
            + ((max(0, (int) ($normalized['cpu'] ?? 0)) / 100) * (float) ($pricing['cpuCore'] ?? 0))
            + ((max(0, (int) ($normalized['disk'] ?? 0)) / 1024) * (float) ($pricing['diskGb'] ?? 0))
            + ($swapGb * (float) ($pricing['swapGb'] ?? 0))
        );

        return [
            'total' => (int) ceil($resourcesCost
                + ((int) ($normalized['allocation_limit'] ?? 0) > 0 ? (float) ($pricing['allocation'] ?? 0) : 0)
                + ($databaseLimit * (float) ($pricing['database'] ?? 0))
                + ($hasImage ? (float) ($pricing['image'] ?? 0) : 0)
                + ($hasPackage ? (float) ($pricing['package'] ?? 0) : 0)),
            'breakdown' => [
                'resourcesCost' => (int) ceil($resourcesCost),
                'imageCost' => $hasImage ? (int) ceil((float) ($pricing['image'] ?? 0)) : 0,
                'packageCost' => $hasPackage ? (int) ceil((float) ($pricing['package'] ?? 0)) : 0,
                'databaseCost' => (int) ceil($databaseLimit * (float) ($pricing['database'] ?? 0)),
                'allocationCost' => (int) (($normalized['allocation_limit'] ?? 0) > 0 ? ceil((float) ($pricing['allocation'] ?? 0)) : 0),
            ],
        ];
    }

    private function assertRevenuePlanCapacity(User $user, array $normalized): void
    {
        $plan = $this->revenue->activePlan($user);

        if (! $plan) {
            return;
        }

        $usage = $this->revenue->aggregateUsage($user);
        $projected = [
            'serverCount' => $usage['serverCount'] + 1,
            'memoryMb' => $usage['memoryMb'] + max(0, (int) ($normalized['memory'] ?? 0)),
            'cpuPercent' => $usage['cpuPercent'] + max(0, (int) ($normalized['cpu'] ?? 0)),
            'diskMb' => $usage['diskMb'] + max(0, (int) ($normalized['disk'] ?? 0)),
        ];

        $exceeded = [];
        if ((int) ($plan['maxServers'] ?? 0) > 0 && $projected['serverCount'] > (int) $plan['maxServers']) {
            $exceeded[] = 'servers';
        }
        if ((int) ($plan['maxMemoryMb'] ?? 0) > 0 && $projected['memoryMb'] > (int) $plan['maxMemoryMb']) {
            $exceeded[] = 'memory';
        }
        if ((int) ($plan['maxCpuPercent'] ?? 0) > 0 && $projected['cpuPercent'] > (int) $plan['maxCpuPercent']) {
            $exceeded[] = 'cpu';
        }
        if ((int) ($plan['maxDiskMb'] ?? 0) > 0 && $projected['diskMb'] > (int) $plan['maxDiskMb']) {
            $exceeded[] = 'disk';
        }

        if ($exceeded !== []) {
            abort(response()->json([
                'message' => 'Requested server exceeds the active revenue plan limits.',
                'exceeded' => $exceeded,
                'usage' => $usage,
                'plan' => $plan,
            ], 422));
        }
    }
}
