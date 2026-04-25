<?php

namespace App\Services;

use App\Models\SystemSetting;

class PanelRedisProfileStore
{
    public const SETTING_KEYS = [
        'redisEnabled',
        'redisRequired',
        'redisUrl',
        'redisHost',
        'redisPort',
        'redisDb',
        'redisUsername',
        'redisPassword',
        'redisTls',
        'redisSessionPrefix',
    ];

    public function storedProfile(): array
    {
        $stored = SystemSetting::query()
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key')
            ->all();

        return $this->normalize([
            'redisEnabled' => $stored['redisEnabled'] ?? false,
            'redisRequired' => $stored['redisRequired'] ?? false,
            'redisUrl' => $stored['redisUrl'] ?? null,
            'redisHost' => $stored['redisHost'] ?? '127.0.0.1',
            'redisPort' => $stored['redisPort'] ?? 6379,
            'redisDb' => $stored['redisDb'] ?? 0,
            'redisUsername' => $stored['redisUsername'] ?? null,
            'redisPassword' => $stored['redisPassword'] ?? null,
            'redisTls' => $stored['redisTls'] ?? false,
            'redisSessionPrefix' => $stored['redisSessionPrefix'] ?? '',
        ]);
    }

    public function update(array $payload): array
    {
        $current = $this->storedProfile();
        $normalized = $this->normalize(array_merge($current, $payload));

        if (! array_key_exists('redisPassword', $payload) || trim((string) $payload['redisPassword']) === '') {
            $normalized['redisPassword'] = $current['redisPassword'];
        }

        foreach (self::SETTING_KEYS as $key) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $this->serializeValue($normalized[$key] ?? null)]
            );
        }

        return $normalized;
    }

    public function normalize(array $payload): array
    {
        $url = $this->nullableString($payload['redisUrl'] ?? null);
        $host = $this->nullableString($payload['redisHost'] ?? null) ?? '127.0.0.1';
        $username = $this->nullableString($payload['redisUsername'] ?? null);
        $password = $this->nullableString($payload['redisPassword'] ?? null);
        $sessionPrefix = trim((string) ($payload['redisSessionPrefix'] ?? ''));

        return [
            'redisEnabled' => $this->toBool($payload['redisEnabled'] ?? false),
            'redisRequired' => $this->toBool($payload['redisRequired'] ?? false),
            'redisUrl' => $url,
            'redisHost' => $host,
            'redisPort' => min(max((int) ($payload['redisPort'] ?? 6379), 1), 65535),
            'redisDb' => min(max((int) ($payload['redisDb'] ?? 0), 0), 255),
            'redisUsername' => $username,
            'redisPassword' => $password,
            'redisTls' => $this->toBool($payload['redisTls'] ?? false),
            'redisSessionPrefix' => mb_substr($sessionPrefix, 0, 120),
        ];
    }

    public function maskedProfile(?array $profile = null): array
    {
        $resolved = $profile ?? $this->storedProfile();

        return [
            ...$resolved,
            'redisUrl' => $this->maskUrl($resolved['redisUrl']),
            'redisPassword' => null,
            'hasPassword' => filled($resolved['redisPassword']),
            'mode' => filled($resolved['redisUrl']) ? 'url' : 'host',
        ];
    }

    public function endpointSummary(array $profile, bool $masked = true): string
    {
        if (filled($profile['redisUrl'])) {
            return $masked ? ($this->maskUrl($profile['redisUrl']) ?? 'URL profile') : (string) $profile['redisUrl'];
        }

        return sprintf(
            '%s://%s:%d/db%d',
            $profile['redisTls'] ? 'rediss' : 'redis',
            $profile['redisHost'],
            (int) $profile['redisPort'],
            (int) $profile['redisDb'],
        );
    }

    private function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function maskUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return 'Configured URL';
        }

        $scheme = $parts['scheme'] ?? 'redis';
        $user = $parts['user'] ?? null;
        $pass = array_key_exists('pass', $parts) ? '***' : null;
        $auth = '';

        if ($user !== null) {
            $auth = $user;
            if ($pass !== null) {
                $auth .= ':' . $pass;
            }
            $auth .= '@';
        }

        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return sprintf('%s://%s%s%s', $scheme, $auth, $host, $port . $path);
    }
}
