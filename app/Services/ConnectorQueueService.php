<?php

namespace App\Services;

class ConnectorQueueService
{
    private const QUEUE_KEY = 'connector:queue';

    public function __construct(
        private PanelRedisClientFactory $redis,
        private PanelRedisProfileStore $profiles,
    ) {
    }

    public function publish(array $message): bool
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return false;
        }

        try {
            $client->rpush(self::QUEUE_KEY, json_encode($message, JSON_UNESCAPED_SLASHES));

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            $client->close();
        }
    }

    public function consume(): ?array
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return null;
        }

        try {
            $message = $client->lpop(self::QUEUE_KEY);

            if (! is_string($message) || $message === '') {
                return null;
            }

            $decoded = json_decode($message, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        } finally {
            $client->close();
        }
    }

    public function adminUsageSummary(bool $ready, ?string $lastError = null): array
    {
        $profile = $this->profiles->storedProfile();
        $required = (bool) $profile['redisRequired'];
        $enabled = (bool) $profile['redisEnabled'];

        return [
            'key' => 'connector_queue',
            'label' => 'Connector queue',
            'source' => 'panel_settings',
            'active' => $enabled,
            'ready' => $enabled ? $ready : false,
            'required' => $required,
            'degraded' => $enabled && $required && ! $ready,
            'lastError' => $enabled ? $lastError : null,
            'details' => [
                'queueKey' => self::QUEUE_KEY,
                'fallback' => 'Provisioning dispatch is skipped when panel-managed Redis is unavailable.',
            ],
        ];
    }
}
