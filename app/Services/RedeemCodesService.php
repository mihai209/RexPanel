<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemCodesService
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
        return collect($this->store->getCatalog(CommerceCatalogStore::REDEEM_CODES_KEY))
            ->map(fn (array $entry): array => $this->compatibility->statusDecoratedRedeemCode($entry))
            ->sortByDesc('enabled')
            ->values()
            ->all();
    }

    public function create(array $payload): array
    {
        $catalog = $this->list();
        $entry = $this->normalizeEntry($payload);
        $this->assertUniqueCode($entry['code'], $catalog);
        $catalog[] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::REDEEM_CODES_KEY, $catalog);

        return $entry;
    }

    public function update(string $codeId, array $payload): array
    {
        $catalog = $this->list();
        $index = collect($catalog)->search(fn (array $code): bool => $code['id'] === $codeId);

        if ($index === false) {
            abort(404, 'Redeem code not found.');
        }

        $entry = $this->normalizeEntry([...$catalog[$index], ...$payload, 'id' => $codeId]);
        $this->assertUniqueCode($entry['code'], $catalog, $codeId);
        $catalog[$index] = $entry;
        $this->store->putCatalog(CommerceCatalogStore::REDEEM_CODES_KEY, $catalog);

        return $entry;
    }

    public function delete(string $codeId): void
    {
        $catalog = array_values(array_filter($this->list(), fn (array $code): bool => $code['id'] !== $codeId));
        $this->store->putCatalog(CommerceCatalogStore::REDEEM_CODES_KEY, $catalog);
    }

    public function redeem(User $user, string $codeValue, ?string $ip = null): array
    {
        $runtime = $this->settings->commerceRuntimeValues();

        if (! $runtime['features']['userStoreEnabled'] || ! $runtime['features']['redeemCodesEnabled']) {
            abort(response()->json(['message' => 'Redeem codes are disabled.'], 403));
        }

        $catalog = $this->list();
        $index = collect($catalog)->search(fn (array $code): bool => strtoupper($code['code']) === strtoupper(trim($codeValue)));

        if ($index === false) {
            abort(response()->json(['message' => 'Redeem code not found.'], 404));
        }

        $code = $catalog[$index];

        if (! ($code['enabled'] ?? false)) {
            abort(response()->json(['message' => 'This redeem code is disabled.'], 422));
        }

        if (($code['status'] ?? 'active') === 'expired') {
            abort(response()->json(['message' => 'This redeem code has expired.'], 422));
        }

        $maxUses = (int) ($code['maxUses'] ?? 0);

        if (($code['status'] ?? 'active') === 'exhausted' || ($maxUses > 0 && (int) ($code['usesCount'] ?? 0) >= $maxUses)) {
            abort(response()->json(['message' => 'This redeem code is exhausted.'], 422));
        }

        $usage = $this->store->getUserRedeemUsage($user);
        $perUserUses = (int) (($code['usageByUser'] ?? [])[(string) $user->id] ?? ($usage[$code['id']]['count'] ?? 0));
        $perUserLimit = $code['perUserLimit'] ?? null;

        if ($perUserLimit !== null && $perUserUses >= (int) $perUserLimit) {
            abort(response()->json(['message' => 'Redeem limit reached for this account.'], 422));
        }

        return DB::transaction(function () use ($user, $catalog, $index, $code, $usage, $runtime, $ip) {
            $freshUser = $user->fresh();
            $rewardCoins = max(0, (int) (($code['rewards']['coins'] ?? 0)));
            if ($rewardCoins > 0) {
                $freshUser->forceFill([
                    'coins' => max(0, (int) $freshUser->coins + $rewardCoins),
                ])->save();
            }

            $inventory = $this->inventory->grant($freshUser, $code['rewards'] ?? [], [
                'redeemCodeId' => $code['id'],
                'redeemCode' => $code['code'],
                'rewardCoins' => $rewardCoins,
            ]);

            $catalog[$index]['usesCount'] = max(0, (int) ($catalog[$index]['usesCount'] ?? 0) + 1);
            $catalog[$index]['usageByUser'] = is_array($catalog[$index]['usageByUser'] ?? null) ? $catalog[$index]['usageByUser'] : [];
            $catalog[$index]['usageByUser'][(string) $freshUser->id] = max(0, (int) (($catalog[$index]['usageByUser'][(string) $freshUser->id] ?? 0) + 1));
            $catalog[$index]['recentUses'] = array_values(array_slice([
                [
                    'userId' => (int) $freshUser->id,
                    'username' => (string) $freshUser->username,
                    'usedAtMs' => now()->valueOf(),
                ],
                ...(is_array($catalog[$index]['recentUses'] ?? null) ? $catalog[$index]['recentUses'] : []),
            ], 0, 200));
            $catalog[$index]['updatedAtMs'] = now()->valueOf();
            $catalog[$index] = $this->compatibility->statusDecoratedRedeemCode($catalog[$index]);
            $this->store->putCatalog(CommerceCatalogStore::REDEEM_CODES_KEY, $catalog);

            $usage[$code['id']] = [
                'count' => max(0, (int) ($usage[$code['id']]['count'] ?? 0) + 1),
                'lastRedeemedAt' => now()->toIso8601String(),
            ];
            $this->store->putUserRedeemUsage($freshUser, $usage);

            $this->billing->record(
                $freshUser,
                'redeem_code',
                sprintf('Redeemed code %s', $code['code']),
                $rewardCoins,
                $code['rewards'] ?? [],
                'redeem_code',
                $code['id'],
                [
                    'code' => $code['code'],
                ],
                $ip,
            );

            return [
                'wallet' => [
                    'coins' => (int) $freshUser->fresh()->coins,
                    'economyUnit' => $runtime['economyUnit'],
                ],
                'inventory' => $inventory,
                'code' => $catalog[$index],
            ];
        });
    }

    private function normalizeEntry(array $payload): array
    {
        $entry = [
            'id' => (string) ($payload['id'] ?? Str::lower(Str::random(12))),
            'code' => $this->compatibility->normalizeRedeemCodeValue($payload['code'] ?? ''),
            'name' => trim((string) ($payload['name'] ?? $payload['code'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'enabled' => (bool) ($payload['enabled'] ?? true),
            'rewards' => $this->compatibility->normalizeRewards($payload['rewards'] ?? [
                'coins' => $payload['rewardCoins'] ?? 0,
                ...($payload['resources'] ?? []),
            ]),
            'maxUses' => array_key_exists('maxUses', $payload) && $payload['maxUses'] !== null && $payload['maxUses'] !== ''
                ? max(0, (int) $payload['maxUses'])
                : 0,
            'perUserLimit' => array_key_exists('perUserLimit', $payload) && $payload['perUserLimit'] !== null && $payload['perUserLimit'] !== ''
                ? max(0, (int) $payload['perUserLimit'])
                : max(0, (int) ($payload['maxUsesPerUser'] ?? 0)),
            'expiresAtMs' => filled($payload['expiresAtMs'] ?? $payload['expiresAt'] ?? null)
                ? $this->compatibility->timestampMs($payload['expiresAtMs'] ?? $payload['expiresAt'], 0)
                : 0,
            'usesCount' => max(0, (int) ($payload['usesCount'] ?? $payload['used'] ?? 0)),
            'usageByUser' => is_array($payload['usageByUser'] ?? null) ? $payload['usageByUser'] : [],
            'recentUses' => is_array($payload['recentUses'] ?? null) ? $payload['recentUses'] : [],
            'createdAtMs' => max(1, (int) ($payload['createdAtMs'] ?? now()->valueOf())),
            'updatedAtMs' => now()->valueOf(),
        ];

        if ($entry['code'] === '') {
            abort(response()->json(['message' => 'Redeem code is required.'], 422));
        }

        if (($entry['rewards']['coins'] ?? 0) <= 0 && array_sum(Arr::except($entry['rewards'], ['coins'])) <= 0) {
            abort(response()->json(['message' => 'Redeem code must grant coins or resources.'], 422));
        }

        return $this->compatibility->statusDecoratedRedeemCode($entry);
    }

    private function assertUniqueCode(string $code, array $catalog, ?string $ignoreId = null): void
    {
        $duplicate = collect($catalog)->contains(function (array $entry) use ($code, $ignoreId): bool {
            return strtoupper((string) $entry['code']) === $code
                && $entry['id'] !== $ignoreId;
        });

        if ($duplicate) {
            abort(response()->json(['message' => 'Redeem codes must be unique.'], 422));
        }
    }
}
