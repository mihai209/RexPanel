<?php

namespace App\Services;

class PanelRedisClientFactory
{
    public function __construct(private PanelRedisProfileStore $profiles)
    {
    }

    public function connectStored(): ?PanelRedisClient
    {
        $profile = $this->profiles->storedProfile();

        if (! $profile['redisEnabled']) {
            return null;
        }

        return $this->connect($profile);
    }

    public function connect(array $profile): PanelRedisClient
    {
        if (! class_exists(\Redis::class)) {
            throw new \RuntimeException('The phpredis extension is not installed.');
        }

        $resolved = $this->resolveConnectionProfile($profile);
        $client = new \Redis();
        $timeout = 1.5;
        $readTimeout = 1.5;
        $context = $resolved['tls']
            ? ['stream' => ['verify_peer' => true, 'verify_peer_name' => true]]
            : null;

        $client->connect(
            $resolved['connectHost'],
            $resolved['port'],
            $timeout,
            null,
            0,
            $readTimeout,
            $context
        );

        if ($resolved['username'] !== null || $resolved['password'] !== null) {
            $authPayload = $resolved['username'] !== null
                ? [$resolved['username'], (string) ($resolved['password'] ?? '')]
                : (string) $resolved['password'];

            if ($client->auth($authPayload) !== true) {
                throw new \RuntimeException('Redis authentication failed.');
            }
        }

        if ($client->select($resolved['db']) !== true) {
            throw new \RuntimeException('Redis database selection failed.');
        }

        return new PanelRedisClient($client, $resolved);
    }

    public function connectLaravelDefault(): ?PanelRedisClient
    {
        $default = config('database.redis.default');

        if (! is_array($default)) {
            return null;
        }

        return $this->connect([
            'redisEnabled' => true,
            'redisUrl' => $default['url'] ?? null,
            'redisHost' => $default['host'] ?? null,
            'redisPort' => $default['port'] ?? 6379,
            'redisDb' => $default['database'] ?? 0,
            'redisUsername' => $default['username'] ?? null,
            'redisPassword' => $default['password'] ?? null,
            'redisTls' => str_starts_with((string) ($default['url'] ?? ''), 'rediss://'),
            'redisSessionPrefix' => config('database.redis.options.prefix', ''),
        ]);
    }

    private function resolveConnectionProfile(array $profile): array
    {
        $url = trim((string) ($profile['redisUrl'] ?? ''));

        if ($url !== '') {
            $parts = parse_url($url);

            if ($parts === false || empty($parts['host'])) {
                throw new \RuntimeException('Invalid Redis URL.');
            }

            $db = 0;
            if (isset($parts['path']) && $parts['path'] !== '') {
                $db = max(0, (int) ltrim($parts['path'], '/'));
            }

            return [
                'redisUrl' => $url,
                'connectHost' => (($parts['scheme'] ?? 'redis') === 'rediss' ? 'tls://' : '') . $parts['host'],
                'host' => $parts['host'],
                'port' => (int) ($parts['port'] ?? 6379),
                'db' => $db,
                'username' => isset($parts['user']) ? urldecode((string) $parts['user']) : null,
                'password' => isset($parts['pass']) ? urldecode((string) $parts['pass']) : null,
                'tls' => ($parts['scheme'] ?? 'redis') === 'rediss',
                'sessionPrefix' => trim((string) ($profile['redisSessionPrefix'] ?? '')),
            ];
        }

        $host = trim((string) ($profile['redisHost'] ?? '127.0.0.1'));

        return [
            'redisUrl' => null,
            'connectHost' => ($profile['redisTls'] ?? false) ? 'tls://' . $host : $host,
            'host' => $host,
            'port' => min(max((int) ($profile['redisPort'] ?? 6379), 1), 65535),
            'db' => min(max((int) ($profile['redisDb'] ?? 0), 0), 255),
            'username' => $this->normalizeNullableString($profile['redisUsername'] ?? null),
            'password' => $this->normalizeNullableString($profile['redisPassword'] ?? null),
            'tls' => filter_var($profile['redisTls'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sessionPrefix' => trim((string) ($profile['redisSessionPrefix'] ?? '')),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
