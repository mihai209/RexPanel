<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AiQuotaRedisService
{
    public function __construct(
        private PanelRedisClientFactory $redis,
        private PanelRedisProfileStore $profiles,
    ) {
    }

    public function usageForUser(int $userId): int
    {
        return $this->usageMap(collect([$userId]))[$userId] ?? 0;
    }

    public function usageMap(Collection $userIds): array
    {
        $keys = $userIds
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => $this->usageKey((int) $id))
            ->values();

        if ($keys->isEmpty()) {
            return [];
        }

        $client = $this->redis->connectStored();

        if (! $client) {
            return [];
        }

        try {
            $values = $client->mget($keys->all());
            $map = [];

            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? null;

                if (is_numeric($value)) {
                    $map[(int) explode(':', $key)[2]] = (int) $value;
                }
            }

            return $map;
        } catch (\Throwable) {
            return [];
        } finally {
            $client->close();
        }
    }

    public function resetUsage(int $userId): void
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return;
        }

        try {
            $client->del($this->usageKey($userId));
        } catch (\Throwable) {
            // AI quota counters must degrade cleanly when panel Redis is unavailable.
        } finally {
            $client->close();
        }
    }

    public function usageKey(int $userId): string
    {
        return sprintf('ai:quota:%d:%s', $userId, now()->toDateString());
    }

    public function adminUsageSummary(bool $ready, ?string $lastError = null): array
    {
        $profile = $this->profiles->storedProfile();
        $required = (bool) $profile['redisRequired'];
        $enabled = (bool) $profile['redisEnabled'];

        return [
            'key' => 'ai_quota',
            'label' => 'AI quota counters',
            'source' => 'panel_settings',
            'active' => $enabled,
            'ready' => $enabled ? $ready : false,
            'required' => $required,
            'degraded' => $enabled && $required && ! $ready,
            'lastError' => $enabled ? $lastError : null,
            'details' => [
                'keyPattern' => 'ai:quota:{userId}:{YYYY-MM-DD}',
                'fallback' => 'Quota reads and resets fall back to zero / no-op when Redis is unavailable.',
            ],
        ];
    }
}
