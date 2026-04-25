<?php

namespace App\Services;

use App\Models\BillingEvent;
use App\Models\Server;
use App\Models\User;

class ForecastingService
{
    public function __construct(
        private SystemSettingsService $settings,
        private RevenueModeService $revenue,
        private InventoryStateService $inventory,
        private CommerceCompatibilityService $compatibility,
    ) {
    }

    public function adminReport(): array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['quotaForecastingEnabled']) {
            abort(response()->json(['message' => 'Quota forecasting is disabled.'], 403));
        }

        $events = BillingEvent::query()->latest('created_at')->limit(200)->get();

        return [
            'summary' => [
                'totalRevenueCoins' => (int) $events->where('coins_delta', '<', 0)->sum(fn (BillingEvent $event): int => abs((int) $event->coins_delta)),
                'totalCreditsCoins' => (int) $events->where('coins_delta', '>', 0)->sum('coins_delta'),
                'eventCount' => $events->count(),
                'activeRevenueProfiles' => collect($this->revenueProfiles())->where('active', true)->count(),
            ],
            'pricing' => $runtime['pricing'],
            'invoicePreview' => $runtime['features']['billingInvoicesEnabled'] ? $this->invoicePreviewRows() : null,
            'renewDays' => $runtime['renewDays'],
            'deleteGraceDays' => $runtime['deleteGraceDays'],
            'recentEvents' => $events->map(fn (BillingEvent $event): array => [
                'id' => $event->id,
                'user_id' => $event->user_id,
                'event_type' => $event->event_type,
                'source_type' => $event->source_type,
                'source_id' => $event->source_id,
                'coins_delta' => $event->coins_delta,
                'wallet_after' => $event->wallet_after,
                'resource_delta' => $event->resource_delta ?? [],
                'details' => $event->details ?? [],
                'created_at' => optional($event->created_at)?->toIso8601String(),
            ])->all(),
        ];
    }

    public function userReport(User $user): array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['quotaForecastingEnabled']) {
            abort(response()->json(['message' => 'Quota forecasting is disabled.'], 403));
        }

        $inventory = $this->inventory->getState($user);
        $profile = $this->revenue->profilePayload($user);
        $effectiveLimits = $this->effectiveLimits($user);
        $activePlan = $this->revenue->activePlan($user);
        $usage = $this->aggregateUsage($user);
        $recurring = $this->recurringBurn($usage, $runtime['pricing']['recurring']);

        return [
            'wallet' => [
                'coins' => (int) $user->fresh()->coins,
                'economyUnit' => $runtime['economyUnit'],
            ],
            'pricing' => $runtime['pricing'],
            'effectiveLimits' => $effectiveLimits,
            'estimatedCoinValue' => $this->compatibility->resourceBundleCoinValue($inventory['resources'] ?? [], $runtime['pricing']['unitCosts']),
            'activePlan' => $activePlan,
            'aggregateUsage' => $usage,
            'recurringBurn' => $recurring,
            'invoicePreview' => $runtime['features']['billingInvoicesEnabled'] ? [
                'visible' => true,
                'rows' => $this->invoicePreviewRows($user),
            ] : [
                'visible' => false,
                'rows' => [],
            ],
            'renewDays' => $runtime['renewDays'],
            'deleteGraceDays' => $runtime['deleteGraceDays'],
            'revenueProfile' => $profile,
            'inventory' => $inventory,
            'recentEvents' => BillingEvent::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BillingEvent $event): array => [
                    'id' => $event->id,
                    'eventType' => $event->event_type,
                    'coinsDelta' => $event->coins_delta,
                    'resourceDelta' => $event->resource_delta ?? [],
                    'details' => $event->details ?? [],
                    'createdAt' => optional($event->created_at)?->toIso8601String(),
                ])->all(),
        ];
    }

    public function effectiveLimits(User $user): array
    {
        return $this->revenue->effectiveProvisioningLimits($user)
            ?? $this->inventory->effectiveLimits($user);
    }

    private function aggregateUsage(User $user): array
    {
        return [
            'serverCount' => Server::query()->where('user_id', $user->id)->count(),
            'memoryMb' => (int) Server::query()->where('user_id', $user->id)->sum('memory'),
            'cpuPercent' => (int) Server::query()->where('user_id', $user->id)->sum('cpu'),
            'diskMb' => (int) Server::query()->where('user_id', $user->id)->sum('disk'),
        ];
    }

    private function revenueProfiles(): array
    {
        return \App\Models\SystemSetting::query()
            ->where('key', 'like', 'user_revenue_profile_%')
            ->get()
            ->map(function ($setting): array {
                $decoded = json_decode((string) $setting->value, true);

                return is_array($decoded) ? $decoded : [];
            })
            ->all();
    }

    private function recurringBurn(array $usage, array $pricing): array
    {
        $monthly = (
            ((int) ($usage['serverCount'] ?? 0) * (float) ($pricing['baseMonthly'] ?? 0))
            + (((int) ($usage['memoryMb'] ?? 0) / 1024) * (float) ($pricing['perGbRamMonthly'] ?? 0))
            + (((int) ($usage['cpuPercent'] ?? 0) / 100) * (float) ($pricing['perCpuCoreMonthly'] ?? 0))
            + (((int) ($usage['diskMb'] ?? 0) / 1024) * (float) ($pricing['perGbDiskMonthly'] ?? 0))
        );

        return [
            'monthlyCoins' => round($monthly, 2),
            'dailyCoins' => round($monthly / 30, 2),
        ];
    }

    private function invoicePreviewRows(?User $user = null): array
    {
        $query = BillingEvent::query()->latest('created_at');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->limit(10)->get()->map(fn (BillingEvent $event): array => [
            'id' => $event->id,
            'eventType' => $event->event_type,
            'coinsDelta' => (int) $event->coins_delta,
            'sourceType' => $event->source_type,
            'sourceId' => $event->source_id,
            'createdAt' => optional($event->created_at)?->toIso8601String(),
            'details' => $event->details ?? [],
        ])->all();
    }
}
