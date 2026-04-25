<?php

namespace App\Services;

class UiWebsocketRedisService
{
    private const QUEUE_KEY = 'ui_ws:queue';

    public function __construct(
        private PanelRedisClientFactory $redis,
        private PanelRedisProfileStore $profiles,
    ) {
    }

    public function publishUserEvent(int $userId, array $payload): bool
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return false;
        }

        try {
            $client->rpush(self::QUEUE_KEY, json_encode([
                'user_id' => $userId,
                'payload' => $payload,
            ], JSON_UNESCAPED_SLASHES));

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            $client->close();
        }
    }

    public function consumeQueuedEvent(): ?array
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

    public function activeSocketCount(int $userId): int
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return 0;
        }

        try {
            return max(0, (int) $client->get($this->connectionsKey($userId)));
        } catch (\Throwable) {
            return 0;
        } finally {
            $client->close();
        }
    }

    public function syncSocketCount(int $userId, int $count): void
    {
        $client = $this->redis->connectStored();

        if (! $client) {
            return;
        }

        try {
            $client->set($this->connectionsKey($userId), max(0, $count));
        } catch (\Throwable) {
            // Ignore Redis sync issues. Websocket auth still works for connected clients.
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
            'key' => 'ui_websocket',
            'label' => 'UI websocket queue',
            'source' => 'panel_settings',
            'active' => $enabled,
            'ready' => $enabled ? $ready : false,
            'required' => $required,
            'degraded' => $enabled && $required && ! $ready,
            'lastError' => $enabled ? $lastError : null,
            'details' => [
                'queueKey' => self::QUEUE_KEY,
                'connectionsKeyPattern' => 'ui_ws:user:{userId}:connections',
                'fallback' => 'Live browser pushes are best-effort; HTTP notification payloads remain authoritative.',
            ],
        ];
    }

    private function connectionsKey(int $userId): string
    {
        return "ui_ws:user:{$userId}:connections";
    }
}
