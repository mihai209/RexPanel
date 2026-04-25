<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;

class CommerceCatalogStore
{
    public const REVENUE_PLANS_KEY = 'revenuePlanCatalog';
    public const STORE_DEALS_KEY = 'storeDealsCatalog';
    public const REDEEM_CODES_KEY = 'storeRedeemCodesCatalog';

    public function __construct(private CommerceCompatibilityService $compatibility)
    {
    }

    public function getCatalog(string $key): array
    {
        $value = SystemSetting::query()->where('key', $key)->value('value');

        return $this->compatibility->normalizeCatalog($key, $value);
    }

    public function putCatalog(string $key, array $catalog): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode(array_values($catalog), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]
        );
    }

    public function getUserRevenueProfile(User|int $user): array
    {
        return $this->compatibility->normalizeRevenueProfile(
            $this->getJsonSetting($this->userRevenueProfileKey($user), $this->defaultRevenueProfile())
        );
    }

    public function putUserRevenueProfile(User|int $user, array $profile): void
    {
        $this->putJsonSetting($this->userRevenueProfileKey($user), array_replace_recursive($this->defaultRevenueProfile(), $profile));
    }

    public function getUserInventoryState(User|int $user): array
    {
        return $this->compatibility->normalizeInventoryState(
            $this->getJsonSetting($this->userInventoryStateKey($user), $this->defaultInventoryState())
        );
    }

    public function putUserInventoryState(User|int $user, array $state): void
    {
        $this->putJsonSetting($this->userInventoryStateKey($user), array_replace_recursive($this->defaultInventoryState(), $state));
    }

    public function getUserRedeemUsage(User|int $user): array
    {
        return $this->getJsonSetting($this->userRedeemUsageKey($user), []);
    }

    public function putUserRedeemUsage(User|int $user, array $state): void
    {
        $this->putJsonSetting($this->userRedeemUsageKey($user), $state);
    }

    public function defaultRevenueProfile(): array
    {
        return [
            'status' => 'inactive',
            'active' => false,
            'planId' => null,
            'planNameSnapshot' => null,
            'planName' => null,
            'periodDays' => 30,
            'priceCoins' => 0,
            'trial' => false,
            'createdAtMs' => null,
            'updatedAtMs' => null,
            'lastRenewAtMs' => null,
            'nextRenewAtMs' => null,
            'graceEndsAtMs' => null,
        ];
    }

    public function defaultInventoryState(): array
    {
        return [
            'resources' => $this->defaultResourceState(),
            'purchases' => [],
            'updatedAtMs' => null,
            'updatedAt' => null,
        ];
    }

    public function defaultResourceState(): array
    {
        return $this->compatibility->defaultResources();
    }

    private function userRevenueProfileKey(User|int $user): string
    {
        return 'user_revenue_profile_' . $this->resolveUserId($user);
    }

    private function userInventoryStateKey(User|int $user): string
    {
        return 'user_inventory_state_' . $this->resolveUserId($user);
    }

    private function userRedeemUsageKey(User|int $user): string
    {
        return 'user_redeem_code_usage_' . $this->resolveUserId($user);
    }

    private function getJsonSetting(string $key, array $fallback): array
    {
        $value = SystemSetting::query()->where('key', $key)->value('value');

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_replace_recursive($fallback, $decoded) : $fallback;
    }

    private function putJsonSetting(string $key, array $payload): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]
        );
    }

    private function resolveUserId(User|int $user): int
    {
        return $user instanceof User ? (int) $user->id : (int) $user;
    }
}
