<?php

namespace App\Services;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevenueModeService
{
    public function __construct(
        private CommerceCatalogStore $store,
        private SystemSettingsService $settings,
        private BillingAuditService $billing,
        private CommerceCompatibilityService $compatibility,
    ) {
    }

    public function list(): array
    {
        return collect($this->store->getCatalog(CommerceCatalogStore::REVENUE_PLANS_KEY))
            ->sortBy('sortOrder')
            ->values()
            ->all();
    }

    public function create(array $payload): array
    {
        $catalog = $this->list();
        $entry = $this->normalizeEntry($payload);

        $this->assertUniqueName($entry['name'], $catalog);
        $catalog[] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::REVENUE_PLANS_KEY, $catalog);

        return $entry;
    }

    public function update(string $planId, array $payload): array
    {
        $catalog = $this->list();
        $index = collect($catalog)->search(fn (array $plan): bool => $plan['id'] === $planId);

        if ($index === false) {
            abort(404, 'Revenue plan not found.');
        }

        $entry = $this->normalizeEntry([...$catalog[$index], ...$payload, 'id' => $planId]);
        $this->assertUniqueName($entry['name'], $catalog, $planId);
        $catalog[$index] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::REVENUE_PLANS_KEY, $catalog);

        return $entry;
    }

    public function delete(string $planId): void
    {
        $catalog = array_values(array_filter(
            $this->list(),
            fn (array $plan): bool => $plan['id'] !== $planId
        ));

        $this->store->putCatalog(CommerceCatalogStore::REVENUE_PLANS_KEY, $catalog);
    }

    public function subscribe(User $user, string $planId, ?string $ip = null): array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled'] || ! $runtime['features']['revenueModeEnabled']) {
            abort(response()->json(['message' => 'Revenue plans are disabled.'], 403));
        }

        $plan = collect($this->list())->firstWhere('id', $planId);

        if (! $plan) {
            abort(response()->json(['message' => 'Revenue plan not found.'], 404));
        }

        if (! ($plan['enabled'] ?? false)) {
            abort(response()->json(['message' => 'This revenue plan is disabled.'], 422));
        }

        $cost = (int) ($plan['priceCoins'] ?? 0);
        $wallet = (int) $user->fresh()->coins;

        if ($wallet < $cost) {
            abort(response()->json(['message' => 'Not enough coins in wallet.'], 422));
        }

        return DB::transaction(function () use ($user, $plan, $cost, $runtime, $ip) {
            $freshUser = $user->fresh();
            $freshUser->forceFill([
                'coins' => max(0, (int) $freshUser->coins - $cost),
            ])->save();

            $durationDays = max(1, (int) ($plan['periodDays'] ?? $runtime['renewDays']));
            $graceDays = (int) ($plan['graceDays'] ?? $runtime['revenueGraceDays']);
            $now = now();
            $renewsAt = $now->copy()->addDays($durationDays);
            $profile = [
                'status' => 'active',
                'active' => true,
                'planId' => $plan['id'],
                'planNameSnapshot' => $plan['name'],
                'planName' => $plan['name'],
                'periodDays' => $durationDays,
                'priceCoins' => $cost,
                'trial' => false,
                'createdAtMs' => $now->valueOf(),
                'updatedAtMs' => $now->valueOf(),
                'lastRenewAtMs' => $now->valueOf(),
                'nextRenewAtMs' => $renewsAt->valueOf(),
                'graceEndsAtMs' => $renewsAt->copy()->addDays($graceDays)->valueOf(),
            ];

            $this->store->putUserRevenueProfile($freshUser, $profile);
            $this->billing->record(
                $freshUser,
                'revenue_subscribe',
                sprintf('Subscribed to revenue plan %s', $plan['name']),
                -$cost,
                [
                    'maxServers' => (int) ($plan['maxServers'] ?? 0),
                    'maxMemoryMb' => (int) ($plan['maxMemoryMb'] ?? 0),
                    'maxCpuPercent' => (int) ($plan['maxCpuPercent'] ?? 0),
                    'maxDiskMb' => (int) ($plan['maxDiskMb'] ?? 0),
                ],
                'revenue_plan',
                $plan['id'],
                [
                    'planName' => $plan['name'],
                    'durationDays' => $durationDays,
                ],
                $ip,
            );

            return [
                'wallet' => [
                    'coins' => (int) $freshUser->fresh()->coins,
                    'economyUnit' => $runtime['economyUnit'],
                ],
                'profile' => $this->profilePayload($freshUser),
                'plan' => $plan,
            ];
        });
    }

    public function profilePayload(User $user): array
    {
        $profile = $this->store->getUserRevenueProfile($user);

        $nextRenewAtMs = (int) ($profile['nextRenewAtMs'] ?? 0);
        $graceEndsAtMs = (int) ($profile['graceEndsAtMs'] ?? 0);
        $nowMs = now()->valueOf();

        if ($nextRenewAtMs > 0 && $nextRenewAtMs <= $nowMs) {
            $profile['status'] = $graceEndsAtMs > 0 && $graceEndsAtMs > $nowMs ? 'past_due' : 'expired';
            $profile['active'] = $profile['status'] !== 'expired';
            $profile['updatedAtMs'] = $nowMs;
            $this->store->putUserRevenueProfile($user, $profile);
        }

        return array_replace_recursive($this->store->defaultRevenueProfile(), $profile);
    }

    public function activePlan(User $user): ?array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['revenueModeEnabled']) {
            return null;
        }

        $profile = $this->profilePayload($user);

        if (! ($profile['active'] ?? false) || ! in_array($profile['status'] ?? 'inactive', ['active', 'trial', 'past_due'], true)) {
            return null;
        }

        return collect($this->list())->firstWhere('id', $profile['planId']);
    }

    public function effectiveProvisioningLimits(User $user): ?array
    {
        $plan = $this->activePlan($user);

        if (! $plan) {
            return null;
        }

        return [
            'cpuPercent' => (int) ($plan['maxCpuPercent'] ?? 0),
            'ramMb' => (int) ($plan['maxMemoryMb'] ?? 0),
            'diskMb' => (int) ($plan['maxDiskMb'] ?? 0),
            'databases' => 0,
            'allocations' => 0,
            'packages' => 0,
            'images' => 0,
            'swapMb' => 0,
        ];
    }

    public function aggregateUsage(User $user): array
    {
        return [
            'serverCount' => Server::query()->where('user_id', $user->id)->count(),
            'memoryMb' => (int) Server::query()->where('user_id', $user->id)->sum('memory'),
            'cpuPercent' => (int) Server::query()->where('user_id', $user->id)->sum('cpu'),
            'diskMb' => (int) Server::query()->where('user_id', $user->id)->sum('disk'),
        ];
    }

    private function normalizeEntry(array $payload): array
    {
        $entry = [
            'id' => (string) ($payload['id'] ?? Str::lower(Str::random(12))),
            'name' => trim((string) ($payload['name'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'enabled' => (bool) ($payload['enabled'] ?? true),
            'featured' => (bool) ($payload['featured'] ?? false),
            'priceCoins' => max(0, (int) ($payload['priceCoins'] ?? 0)),
            'periodDays' => max(1, (int) ($payload['periodDays'] ?? $payload['durationDays'] ?? 30)),
            'maxServers' => max(0, (int) ($payload['maxServers'] ?? 0)),
            'maxMemoryMb' => max(0, (int) ($payload['maxMemoryMb'] ?? 0)),
            'maxCpuPercent' => max(0, (int) ($payload['maxCpuPercent'] ?? 0)),
            'maxDiskMb' => max(0, (int) ($payload['maxDiskMb'] ?? 0)),
            'createdAtMs' => max(1, (int) ($payload['createdAtMs'] ?? now()->valueOf())),
            'updatedAtMs' => now()->valueOf(),
        ];

        if ($entry['name'] === '') {
            abort(response()->json(['message' => 'Revenue plan name is required.'], 422));
        }

        return $this->compatibility->normalizeRevenuePlan($entry);
    }

    private function assertUniqueName(string $name, array $catalog, ?string $ignoreId = null): void
    {
        $duplicate = collect($catalog)->contains(function (array $plan) use ($name, $ignoreId): bool {
            return strtolower((string) $plan['name']) === strtolower($name)
                && $plan['id'] !== $ignoreId;
        });

        if ($duplicate) {
            abort(response()->json(['message' => 'Revenue plan names must be unique.'], 422));
        }
    }
}
