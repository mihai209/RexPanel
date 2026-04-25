<?php

namespace App\Services;

class PanelRedisClient
{
    public function __construct(
        private \Redis $client,
        private array $profile,
    ) {
    }

    public function profile(): array
    {
        return $this->profile;
    }

    public function ping(): mixed
    {
        return $this->client->ping();
    }

    public function mget(array $keys): array
    {
        return $this->client->mGet($keys);
    }

    public function get(string $key): mixed
    {
        return $this->client->get($key);
    }

    public function set(string $key, mixed $value): bool
    {
        return (bool) $this->client->set($key, $value);
    }

    public function del(string $key): int
    {
        return (int) $this->client->del($key);
    }

    public function rpush(string $key, string $value): int
    {
        return (int) $this->client->rPush($key, $value);
    }

    public function lpop(string $key): mixed
    {
        return $this->client->lPop($key);
    }

    public function close(): void
    {
        try {
            $this->client->close();
        } catch (\Throwable) {
            // Ignore close failures on short-lived admin/runtime clients.
        }
    }
}
