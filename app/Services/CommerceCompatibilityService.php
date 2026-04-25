<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CommerceCompatibilityService
{
    public function defaultResources(): array
    {
        return [
            'ramMb' => 0,
            'cpuPercent' => 0,
            'diskMb' => 0,
            'swapMb' => 0,
            'allocations' => 0,
            'images' => 0,
            'databases' => 0,
            'packages' => 0,
        ];
    }

    public function normalizeCatalog(string $key, mixed $value): array
    {
        $parsed = $this->decodeArray($value);

        if (! is_array($parsed)) {
            return [];
        }

        $entries = collect($parsed)
            ->filter(fn ($entry): bool => is_array($entry))
            ->map(function (array $entry) use ($key): array {
                return match ($key) {
                    CommerceCatalogStore::REVENUE_PLANS_KEY => $this->normalizeRevenuePlan($entry),
                    CommerceCatalogStore::STORE_DEALS_KEY => $this->normalizeStoreDeal($entry),
                    CommerceCatalogStore::REDEEM_CODES_KEY => $this->normalizeRedeemCode($entry),
                    default => $entry,
                };
            })
            ->values();

        return match ($key) {
            CommerceCatalogStore::REVENUE_PLANS_KEY => $entries->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            CommerceCatalogStore::STORE_DEALS_KEY => $entries->sortByDesc('featured')->sortByDesc('createdAtMs')->values()->all(),
            CommerceCatalogStore::REDEEM_CODES_KEY => $entries->sortByDesc('createdAtMs')->values()->all(),
            default => $entries->all(),
        };
    }

    public function normalizeRevenuePlan(array $entry, ?int $nowMs = null): array
    {
        $nowMs ??= $this->nowMs();
        $limits = $entry['limits'] ?? [];

        $createdAtMs = $this->timestampMs($entry['createdAtMs'] ?? $entry['createdAt'] ?? null, $nowMs);
        $updatedAtMs = $this->timestampMs($entry['updatedAtMs'] ?? $entry['updatedAt'] ?? null, $createdAtMs);

        return [
            'id' => $this->nonEmptyString($entry['id'] ?? null, Str::lower(Str::random(12))),
            'name' => Str::limit(trim((string) ($entry['name'] ?? '')), 120, ''),
            'description' => Str::limit(trim((string) ($entry['description'] ?? '')), 500, ''),
            'enabled' => $this->boolValue($entry['enabled'] ?? true, true),
            'featured' => $this->boolValue($entry['featured'] ?? false, false),
            'priceCoins' => $this->intValue($entry['priceCoins'] ?? 0),
            'periodDays' => max(1, $this->intValue($entry['periodDays'] ?? $entry['durationDays'] ?? 30, 30)),
            'maxServers' => $this->intValue($entry['maxServers'] ?? Arr::get($limits, 'servers', 0)),
            'maxMemoryMb' => $this->intValue($entry['maxMemoryMb'] ?? Arr::get($limits, 'ramMb', Arr::get($limits, 'memory', 0))),
            'maxCpuPercent' => $this->intValue($entry['maxCpuPercent'] ?? Arr::get($limits, 'cpuPercent', Arr::get($limits, 'cpu', 0))),
            'maxDiskMb' => $this->intValue($entry['maxDiskMb'] ?? Arr::get($limits, 'diskMb', Arr::get($limits, 'disk', 0))),
            'createdAtMs' => $createdAtMs,
            'updatedAtMs' => max($createdAtMs, $updatedAtMs),
        ];
    }

    public function normalizeStoreDeal(array $entry, ?int $nowMs = null): array
    {
        $nowMs ??= $this->nowMs();
        $createdAtMs = $this->timestampMs($entry['createdAtMs'] ?? $entry['createdAt'] ?? null, $nowMs);
        $updatedAtMs = $this->timestampMs($entry['updatedAtMs'] ?? $entry['updatedAt'] ?? null, $createdAtMs);
        $stockTotal = max(0, $this->intValue($entry['stockTotal'] ?? $entry['stock'] ?? 0));
        $stockSold = max(0, $this->intValue($entry['stockSold'] ?? 0));
        $stockTotal = max($stockTotal, $stockSold);
        $resources = $this->normalizeResources($entry['resources'] ?? []);
        $normalized = [
            'id' => $this->nonEmptyString($entry['id'] ?? null, Str::lower(Str::random(12))),
            'name' => Str::limit(trim((string) ($entry['name'] ?? '')), 120, ''),
            'description' => Str::limit(trim((string) ($entry['description'] ?? '')), 1200, ''),
            'imageUrl' => $this->sanitizeUrl($entry['imageUrl'] ?? ''),
            'enabled' => $this->boolValue($entry['enabled'] ?? true, true),
            'featured' => $this->boolValue($entry['featured'] ?? false, false),
            'priceCoins' => $this->intValue($entry['priceCoins'] ?? 0),
            'stockTotal' => $stockTotal,
            'stockSold' => min($stockTotal, $stockSold),
            'createdAtMs' => $createdAtMs,
            'updatedAtMs' => max($createdAtMs, $updatedAtMs),
            'resources' => $resources,
        ];

        $normalized['remainingStock'] = max(0, (int) $normalized['stockTotal'] - (int) $normalized['stockSold']);
        $normalized['status'] = $this->dealStatus($normalized);

        return $normalized;
    }

    public function normalizeRedeemCode(array $entry, ?int $nowMs = null): array
    {
        $nowMs ??= $this->nowMs();
        $createdAtMs = $this->timestampMs($entry['createdAtMs'] ?? $entry['createdAt'] ?? null, $nowMs);
        $updatedAtMs = $this->timestampMs($entry['updatedAtMs'] ?? $entry['updatedAt'] ?? null, $createdAtMs);
        $rewards = $this->normalizeRewards($entry['rewards'] ?? [
            'coins' => $entry['rewardCoins'] ?? 0,
            ...($entry['resources'] ?? []),
        ]);
        $usageByUser = collect($this->decodeArray($entry['usageByUser'] ?? []))
            ->filter(fn ($count, $userId): bool => ctype_digit((string) $userId) && $this->intValue($count) > 0)
            ->mapWithKeys(fn ($count, $userId): array => [(string) $userId => $this->intValue($count)])
            ->all();
        $recentUses = collect(is_array($entry['recentUses'] ?? null) ? $entry['recentUses'] : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'userId' => $this->intValue($row['userId'] ?? 0),
                'username' => Str::limit(trim((string) ($row['username'] ?? '')), 120, ''),
                'usedAtMs' => $this->timestampMs($row['usedAtMs'] ?? null, $nowMs),
            ])
            ->filter(fn (array $row): bool => $row['userId'] > 0)
            ->sortByDesc('usedAtMs')
            ->take(200)
            ->values()
            ->all();
        $usesCount = max(0, $this->intValue($entry['usesCount'] ?? $entry['used'] ?? 0));
        $maxUses = max(0, $this->intValue($entry['maxUses'] ?? 0));

        $normalized = [
            'id' => $this->nonEmptyString($entry['id'] ?? null, Str::lower(Str::random(12))),
            'code' => $this->normalizeRedeemCodeValue($entry['code'] ?? ''),
            'name' => Str::limit(trim((string) ($entry['name'] ?? $entry['code'] ?? '')), 120, ''),
            'description' => Str::limit(trim((string) ($entry['description'] ?? '')), 1200, ''),
            'enabled' => $this->boolValue($entry['enabled'] ?? true, true),
            'expiresAtMs' => max(0, $this->timestampMs($entry['expiresAtMs'] ?? $entry['expiresAt'] ?? null, 0)),
            'maxUses' => $maxUses,
            'usesCount' => $usesCount,
            'perUserLimit' => max(0, $this->intValue($entry['perUserLimit'] ?? $entry['maxUsesPerUser'] ?? 0)),
            'usageByUser' => $usageByUser,
            'recentUses' => $recentUses,
            'createdAtMs' => $createdAtMs,
            'updatedAtMs' => max($createdAtMs, $updatedAtMs),
            'rewards' => $rewards,
        ];

        $normalized['status'] = $this->redeemStatus($normalized, $nowMs);
        $normalized['remainingUses'] = $maxUses > 0 ? max(0, $maxUses - $usesCount) : null;

        return $normalized;
    }

    public function normalizeRevenueProfile(mixed $value): array
    {
        $source = $this->decodeArray($value);
        $nowMs = $this->nowMs();
        $status = strtolower(trim((string) ($source['status'] ?? 'inactive')));
        $status = in_array($status, ['inactive', 'trial', 'active', 'past_due', 'expired'], true) ? $status : 'inactive';
        $nextRenewAtMs = $this->timestampMs($source['nextRenewAtMs'] ?? $source['renewsAt'] ?? $source['expiresAt'] ?? null, 0);
        $graceEndsAtMs = $this->timestampMs($source['graceEndsAtMs'] ?? $source['graceEndsAt'] ?? null, 0);
        $createdAtMs = $this->timestampMs($source['createdAtMs'] ?? $source['subscribedAt'] ?? null, $nowMs);
        $updatedAtMs = $this->timestampMs($source['updatedAtMs'] ?? null, $createdAtMs);
        $active = in_array($status, ['trial', 'active', 'past_due'], true);

        return [
            'status' => $status,
            'active' => $active,
            'planId' => trim((string) ($source['planId'] ?? '')),
            'planNameSnapshot' => Str::limit(trim((string) ($source['planNameSnapshot'] ?? $source['planName'] ?? '')), 120, ''),
            'planName' => Str::limit(trim((string) ($source['planNameSnapshot'] ?? $source['planName'] ?? '')), 120, ''),
            'periodDays' => max(1, $this->intValue($source['periodDays'] ?? $source['durationDays'] ?? 30, 30)),
            'priceCoins' => $this->intValue($source['priceCoins'] ?? 0),
            'trial' => $this->boolValue($source['trial'] ?? false, false),
            'createdAtMs' => $createdAtMs,
            'updatedAtMs' => max($createdAtMs, $updatedAtMs),
            'lastRenewAtMs' => $this->timestampMs($source['lastRenewAtMs'] ?? null, 0),
            'nextRenewAtMs' => $nextRenewAtMs,
            'graceEndsAtMs' => $graceEndsAtMs,
        ];
    }

    public function normalizeInventoryState(mixed $value): array
    {
        $source = $this->decodeArray($value);
        $purchases = is_array($source['purchases'] ?? null) ? array_values($source['purchases']) : [];

        return [
            'resources' => $this->normalizeResources($source['resources'] ?? $source),
            'purchases' => $purchases,
            'updatedAtMs' => $this->timestampMs($source['updatedAtMs'] ?? $source['updatedAt'] ?? null, 0),
            'updatedAt' => $this->timestampIso($source['updatedAtMs'] ?? $source['updatedAt'] ?? null),
        ];
    }

    public function normalizeResources(mixed $raw): array
    {
        $source = is_array($raw) ? $raw : [];
        $packagesFallback = $this->intValue($source['backups'] ?? 0);

        return [
            'ramMb' => $this->intValue($source['ramMb'] ?? $source['memory'] ?? 0),
            'cpuPercent' => $this->intValue($source['cpuPercent'] ?? $source['cpu'] ?? 0),
            'diskMb' => $this->intValue($source['diskMb'] ?? $source['disk'] ?? 0),
            'swapMb' => $this->intValue($source['swapMb'] ?? $source['swap'] ?? 0),
            'allocations' => $this->intValue($source['allocations'] ?? 0),
            'images' => $this->intValue($source['images'] ?? 0),
            'databases' => $this->intValue($source['databases'] ?? 0),
            'packages' => $this->intValue($source['packages'] ?? $packagesFallback),
        ];
    }

    public function normalizeRewards(mixed $raw): array
    {
        $source = is_array($raw) ? $raw : [];

        return [
            'coins' => $this->intValue($source['coins'] ?? $source['rewardCoins'] ?? 0),
            ...$this->normalizeResources($source),
        ];
    }

    public function normalizeRedeemCodeValue(mixed $value): string
    {
        return Str::upper((string) preg_replace('/[^A-Z0-9_-]/', '', preg_replace('/\s+/', '', trim((string) $value)) ?? ''));
    }

    public function statusDecoratedDeal(array $deal): array
    {
        return $this->normalizeStoreDeal($deal);
    }

    public function statusDecoratedRedeemCode(array $entry): array
    {
        return $this->normalizeRedeemCode($entry);
    }

    public function dealStatus(array $deal): string
    {
        if (! $this->boolValue($deal['enabled'] ?? true, true)) {
            return 'disabled';
        }

        if (max(0, $this->intValue($deal['stockTotal'] ?? 0)) <= max(0, $this->intValue($deal['stockSold'] ?? 0))) {
            return 'sold_out';
        }

        return 'active';
    }

    public function redeemStatus(array $entry, ?int $nowMs = null): string
    {
        $nowMs ??= $this->nowMs();

        if (! $this->boolValue($entry['enabled'] ?? true, true)) {
            return 'disabled';
        }

        if (max(0, $this->timestampMs($entry['expiresAtMs'] ?? null, 0)) > 0 && $this->timestampMs($entry['expiresAtMs'] ?? null, 0) <= $nowMs) {
            return 'expired';
        }

        $maxUses = max(0, $this->intValue($entry['maxUses'] ?? 0));
        $usesCount = max(0, $this->intValue($entry['usesCount'] ?? 0));

        if ($maxUses > 0 && $usesCount >= $maxUses) {
            return 'exhausted';
        }

        return 'active';
    }

    public function resourceBundleCoinValue(array $resources, array $unitCosts): int
    {
        return max(0, (int) ceil(
            (($this->intValue($resources['ramMb'] ?? 0) / 1024) * (float) ($unitCosts['ramGb'] ?? 0))
            + (($this->intValue($resources['cpuPercent'] ?? 0) / 100) * (float) ($unitCosts['cpuCore'] ?? 0))
            + (($this->intValue($resources['diskMb'] ?? 0) / 1024) * (float) ($unitCosts['diskGb'] ?? 0))
            + (($this->intValue($resources['swapMb'] ?? 0) / 1024) * (float) ($unitCosts['swapGb'] ?? 0))
            + ($this->intValue($resources['allocations'] ?? 0) * (float) ($unitCosts['allocation'] ?? 0))
            + ($this->intValue($resources['images'] ?? 0) * (float) ($unitCosts['image'] ?? 0))
            + ($this->intValue($resources['databases'] ?? 0) * (float) ($unitCosts['database'] ?? 0))
            + ($this->intValue($resources['packages'] ?? 0) * (float) ($unitCosts['package'] ?? 0))
        ));
    }

    public function timestampMs(mixed $value, int $fallback = 0): int
    {
        if ($value instanceof CarbonInterface) {
            return (int) $value->valueOf();
        }

        if (is_int($value) || is_float($value)) {
            return $value > 0 ? (int) floor($value) : $fallback;
        }

        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return $fallback;
        }

        if (ctype_digit($raw)) {
            $parsed = (int) $raw;
            return $parsed > 0 ? $parsed : $fallback;
        }

        $parsed = strtotime($raw);

        return $parsed !== false && $parsed > 0 ? $parsed * 1000 : $fallback;
    }

    public function timestampIso(mixed $value): ?string
    {
        $timestamp = $this->timestampMs($value, 0);

        return $timestamp > 0 ? now()->createFromTimestampMs($timestamp)->toIso8601String() : null;
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function intValue(mixed $value, int $fallback = 0): int
    {
        if (is_numeric($value)) {
            return max(0, (int) floor((float) $value));
        }

        $raw = trim((string) ($value ?? ''));

        return is_numeric($raw) ? max(0, (int) floor((float) $raw)) : $fallback;
    }

    private function boolValue(mixed $value, bool $fallback): bool
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    private function nonEmptyString(mixed $value, string $fallback): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function sanitizeUrl(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return '';
        }

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $normalized : '';
    }

    private function nowMs(): int
    {
        return (int) now()->valueOf();
    }
}
