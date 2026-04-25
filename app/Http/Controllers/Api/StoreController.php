<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ForecastingService;
use App\Services\InventoryStateService;
use App\Services\RedeemCodesService;
use App\Services\RevenueModeService;
use App\Services\StoreDealsService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private SystemSettingsService $settings,
        private RevenueModeService $revenue,
        private StoreDealsService $deals,
        private RedeemCodesService $codes,
        private InventoryStateService $inventory,
        private ForecastingService $forecasting,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled']) {
            return response()->json(['message' => 'The store is disabled.'], 403);
        }

        $user = $request->user()->fresh();

        return response()->json([
            'wallet' => [
                'coins' => (int) $user->coins,
                'economyUnit' => $runtime['economyUnit'],
            ],
            'features' => $runtime['features'],
            'pricing' => $runtime['pricing'],
            'revenuePlans' => $this->revenue->list(),
            'featuredDeals' => array_values(array_filter($this->deals->list(), fn (array $deal): bool => (bool) ($deal['featured'] ?? false))),
            'inventory' => $this->inventory->getState($user),
            'revenueProfile' => $this->revenue->profilePayload($user),
            'activePlan' => $this->revenue->activePlan($user),
            'forecast' => $runtime['features']['quotaForecastingEnabled'] ? $this->forecasting->userReport($user) : null,
        ]);
    }

    public function deals(Request $request): JsonResponse
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled'] || ! $runtime['features']['storeDealsEnabled']) {
            return response()->json(['message' => 'Store deals are disabled.'], 403);
        }

        return response()->json([
            'wallet' => [
                'coins' => (int) $request->user()->fresh()->coins,
                'economyUnit' => $runtime['economyUnit'],
            ],
            'deals' => $this->deals->list(),
            'inventory' => $this->inventory->getState($request->user()),
        ]);
    }

    public function redeemStatus(Request $request): JsonResponse
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled'] || ! $runtime['features']['redeemCodesEnabled']) {
            return response()->json(['message' => 'Redeem codes are disabled.'], 403);
        }

        return response()->json([
            'wallet' => [
                'coins' => (int) $request->user()->fresh()->coins,
                'economyUnit' => $runtime['economyUnit'],
            ],
            'inventory' => $this->inventory->getState($request->user()),
            'usage' => app(\App\Services\CommerceCatalogStore::class)->getUserRedeemUsage($request->user()),
            'codes' => $this->codes->list(),
        ]);
    }

    public function subscribeRevenuePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'planId' => ['required', 'string'],
        ]);

        return response()->json([
            'message' => 'Revenue plan subscribed.',
            ...$this->revenue->subscribe($request->user(), $data['planId'], $request->ip()),
        ]);
    }

    public function purchaseDeal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dealId' => ['required', 'string'],
        ]);

        return response()->json([
            'message' => 'Store deal purchased.',
            ...$this->deals->purchase($request->user(), $data['dealId'], $request->ip()),
        ]);
    }

    public function redeemCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:128'],
        ]);

        return response()->json([
            'message' => 'Redeem code claimed.',
            ...$this->codes->redeem($request->user(), $data['code'], $request->ip()),
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        return response()->json($this->forecasting->userReport($request->user()));
    }
}
