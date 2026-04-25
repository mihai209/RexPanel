<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreDealsService
{
    public function __construct(
        private CommerceCatalogStore $store,
        private SystemSettingsService $settings,
        private InventoryStateService $inventory,
        private BillingAuditService $billing,
        private CommerceCompatibilityService $compatibility,
    ) {
    }

    public function list(): array
    {
        return collect($this->store->getCatalog(CommerceCatalogStore::STORE_DEALS_KEY))
            ->map(fn (array $deal): array => $this->compatibility->statusDecoratedDeal($deal))
            ->sortByDesc('featured')
            ->sortByDesc('createdAtMs')
            ->values()
            ->all();
    }

    public function create(array $payload): array
    {
        $catalog = $this->list();
        $entry = $this->normalizeEntry($payload);
        $this->assertUniqueName($entry['name'], $catalog);
        $catalog[] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::STORE_DEALS_KEY, $catalog);

        return $entry;
    }

    public function update(string $dealId, array $payload): array
    {
        $catalog = $this->list();
        $index = collect($catalog)->search(fn (array $deal): bool => $deal['id'] === $dealId);

        if ($index === false) {
            abort(404, 'Store deal not found.');
        }

        $entry = $this->normalizeEntry([...$catalog[$index], ...$payload, 'id' => $dealId]);
        $this->assertUniqueName($entry['name'], $catalog, $dealId);
        $catalog[$index] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::STORE_DEALS_KEY, $catalog);

        return $entry;
    }

    public function delete(string $dealId): void
    {
        $catalog = array_values(array_filter($this->list(), fn (array $deal): bool => $deal['id'] !== $dealId));
        $this->store->putCatalog(CommerceCatalogStore::STORE_DEALS_KEY, $catalog);
    }

    public function purchase(User $user, string $dealId, ?string $ip = null): array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled'] || ! $runtime['features']['storeDealsEnabled']) {
            abort(response()->json(['message' => 'Store deals are disabled.'], 403));
        }

        $catalog = $this->list();
        $index = collect($catalog)->search(fn (array $deal): bool => $deal['id'] === $dealId);

        if ($index === false) {
            abort(response()->json(['message' => 'Store deal not found.'], 404));
        }

        $deal = $catalog[$index];

        if (! ($deal['enabled'] ?? false)) {
            abort(response()->json(['message' => 'This deal is disabled.'], 422));
        }

        if (($deal['remainingStock'] ?? 0) <= 0) {
            abort(response()->json(['message' => 'This deal is sold out.'], 422));
        }

        $price = (int) ($deal['priceCoins'] ?? 0);
        $wallet = (int) $user->fresh()->coins;

        if ($wallet < $price) {
            abort(response()->json(['message' => 'Not enough coins in wallet.'], 422));
        }

        return DB::transaction(function () use ($user, $catalog, $index, $deal, $price, $runtime, $ip) {
            $freshUser = $user->fresh();
            $freshUser->forceFill([
                'coins' => max(0, (int) $freshUser->coins - $price),
            ])->save();

            $catalog[$index]['stockSold'] = min(
                (int) $catalog[$index]['stockTotal'],
                max(0, (int) ($catalog[$index]['stockSold'] ?? 0) + 1)
            );
            $catalog[$index]['updatedAtMs'] = now()->valueOf();
            $catalog[$index] = $this->compatibility->statusDecoratedDeal($catalog[$index]);
            $this->store->putCatalog(CommerceCatalogStore::STORE_DEALS_KEY, $catalog);

            $inventory = $this->inventory->grant($freshUser, $deal['resources'] ?? [], [
                'dealId' => $deal['id'],
                'dealName' => $deal['name'],
                'priceCoins' => $price,
            ]);

            $this->billing->record(
                $freshUser,
                'deal_purchase',
                sprintf('Purchased store deal %s', $deal['name']),
                -$price,
                $deal['resources'] ?? [],
                'store_deal',
                $deal['id'],
                [
                    'dealName' => $deal['name'],
                ],
                $ip,
            );

            return [
                'wallet' => [
                    'coins' => (int) $freshUser->fresh()->coins,
                    'economyUnit' => $runtime['economyUnit'],
                ],
                'deal' => $catalog[$index],
                'inventory' => $inventory,
            ];
        });
    }

    private function normalizeEntry(array $payload): array
    {
        $entry = [
            'id' => (string) ($payload['id'] ?? Str::lower(Str::random(12))),
            'name' => trim((string) ($payload['name'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'imageUrl' => trim((string) ($payload['imageUrl'] ?? '')),
            'enabled' => (bool) ($payload['enabled'] ?? true),
            'featured' => (bool) ($payload['featured'] ?? false),
            'priceCoins' => max(0, (int) ($payload['priceCoins'] ?? 0)),
            'stockTotal' => max(1, (int) ($payload['stockTotal'] ?? $payload['stock'] ?? 1)),
            'stockSold' => max(0, (int) ($payload['stockSold'] ?? 0)),
            'createdAtMs' => max(1, (int) ($payload['createdAtMs'] ?? now()->valueOf())),
            'updatedAtMs' => now()->valueOf(),
            'resources' => $this->inventory->sanitizeResources($payload['resources'] ?? []),
        ];

        if ($entry['name'] === '') {
            abort(response()->json(['message' => 'Store deal name is required.'], 422));
        }

        if (array_sum($entry['resources']) <= 0) {
            abort(response()->json(['message' => 'Store deals must grant at least one resource.'], 422));
        }

        if ($entry['stockSold'] > $entry['stockTotal']) {
            abort(response()->json(['message' => 'Sold stock cannot exceed total stock.'], 422));
        }

        return $this->compatibility->statusDecoratedDeal($entry);
    }

    private function assertUniqueName(string $name, array $catalog, ?string $ignoreId = null): void
    {
        $duplicate = collect($catalog)->contains(function (array $deal) use ($name, $ignoreId): bool {
            return strtolower((string) $deal['name']) === strtolower($name)
                && $deal['id'] !== $ignoreId;
        });

        if ($duplicate) {
            abort(response()->json(['message' => 'Deal names must be unique.'], 422));
        }
    }
}
