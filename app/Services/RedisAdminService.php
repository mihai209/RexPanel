<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RedisAdminService
{
    private const STATUS_CACHE_KEY = 'admin.redis.payload.v1';
    private const LAST_TEST_CACHE_KEY = 'admin.redis.last_test.v1';

    public function __construct(
        private PanelRedisProfileStore $profiles,
        private PanelRedisClientFactory $clients,
        private AiQuotaRedisService $quota,
        private ConnectorQueueService $connectorQueue,
        private UiWebsocketRedisService $uiWebsocket,
    ) {
    }

    public function validationRules(bool $partial = false): array
    {
        $prefix = $partial ? ['sometimes'] : [];

        return [
            'redisEnabled' => [...$prefix, 'boolean'],
            'redisRequired' => [...$prefix, 'boolean'],
            'redisUrl' => [...$prefix, 'nullable', 'string', 'max:2048'],
            'redisHost' => [...$prefix, 'nullable', 'string', 'max:255'],
            'redisPort' => [...$prefix, 'nullable', 'integer', 'min:1', 'max:65535'],
            'redisDb' => [...$prefix, 'nullable', 'integer', 'min:0', 'max:255'],
            'redisUsername' => [...$prefix, 'nullable', 'string', 'max:255'],
            'redisPassword' => [...$prefix, 'nullable', 'string', 'max:255'],
            'redisTls' => [...$prefix, 'boolean'],
            'redisSessionPrefix' => [...$prefix, 'nullable', 'string', 'max:120'],
        ];
    }

    public function payload(): array
    {
        return Cache::remember(self::STATUS_CACHE_KEY, now()->addSeconds(15), fn (): array => $this->buildPayload());
    }

    public function update(array $payload): array
    {
        $profile = $this->profiles->update($payload);
        $this->forgetCachedPayload();

        return [
            'message' => 'Redis admin profile updated.',
            'data' => $this->buildPayload($profile),
        ];
    }

    public function testConnection(array $payload): array
    {
        $current = $this->profiles->storedProfile();
        $normalized = $this->profiles->normalize(array_merge($current, $payload));

        if (! array_key_exists('redisPassword', $payload) || trim((string) $payload['redisPassword']) === '') {
            $normalized['redisPassword'] = $current['redisPassword'];
        }

        $result = $this->runConnectionTest($normalized);

        Cache::forever(self::LAST_TEST_CACHE_KEY, $result);
        $this->forgetCachedPayload();

        return $result;
    }

    private function buildPayload(?array $storedProfile = null): array
    {
        $profile = $storedProfile ?? $this->profiles->storedProfile();
        $masked = $this->profiles->maskedProfile($profile);
        $framework = $this->frameworkSummary();
        $panelRuntime = $this->panelRuntimeSummary($profile);
        $runtime = $this->runtimeSummary($profile, $framework, $panelRuntime);
        $usage = $this->usageSummary($framework, $panelRuntime);
        $warnings = $this->warnings($profile, $framework, $runtime, $usage);
        $lastTest = Cache::get(self::LAST_TEST_CACHE_KEY);

        return [
            'config' => [
                ...$masked,
                'endpointSummary' => $this->profiles->endpointSummary($profile),
                'effectiveModeSummary' => $runtime['effectiveMode'],
                'lastTest' => $lastTest,
            ],
            'runtime' => $runtime,
            'usage' => $usage,
            'framework' => $framework,
            'warnings' => $warnings,
        ];
    }

    private function runtimeSummary(array $profile, array $framework, array $panelRuntime): array
    {
        $frameworkEnabled = $framework['usesRedis'];
        $panelEnabled = (bool) $profile['redisEnabled'];
        $frameworkReady = ! $frameworkEnabled || $framework['ready'];
        $panelReady = ! $panelEnabled || $panelRuntime['ready'];
        $mode = match (true) {
            $frameworkEnabled && $panelEnabled => 'hybrid',
            $frameworkEnabled => 'framework_env',
            $panelEnabled => 'panel_managed',
            default => 'disabled',
        };

        $source = $frameworkEnabled ? 'env' : ($panelEnabled ? 'panel_settings' : null);
        $endpoints = [];
        if ($frameworkEnabled) {
            $endpoints[] = 'env: ' . $framework['endpointSummary'];
        }
        if ($panelEnabled) {
            $endpoints[] = 'panel: ' . $panelRuntime['endpointSummary'];
        }

        return [
            'enabled' => $frameworkEnabled || $panelEnabled,
            'ready' => $frameworkReady && $panelReady,
            'source' => $source,
            'lastError' => $frameworkEnabled && ! $framework['ready']
                ? $framework['lastError']
                : ($panelEnabled && ! $panelRuntime['ready'] ? $panelRuntime['lastError'] : null),
            'effectiveMode' => $mode,
            'endpointSummary' => $endpoints !== [] ? implode(' | ', $endpoints) : 'Redis is not active in RA-panel.',
        ];
    }

    private function frameworkSummary(): array
    {
        $cacheStore = (string) config('cache.default');
        $cacheConfig = config("cache.stores.{$cacheStore}", []);
        $sessionDriver = (string) config('session.driver');
        $queueConnection = (string) config('queue.default');
        $queueConfig = config("queue.connections.{$queueConnection}", []);
        $broadcastDriver = (string) config('broadcasting.default');
        $broadcastConfig = config("broadcasting.connections.{$broadcastDriver}", []);
        $reverbScalingEnabled = (bool) config('reverb.servers.reverb.scaling.enabled', false);
        $usesRedis = ($cacheConfig['driver'] ?? null) === 'redis'
            || $sessionDriver === 'redis'
            || ($queueConfig['driver'] ?? null) === 'redis'
            || $broadcastDriver === 'redis'
            || $reverbScalingEnabled;

        $ready = ! $usesRedis;
        $lastError = null;
        $endpointSummary = 'Laravel is not configured to use Redis-backed framework drivers.';

        if ($usesRedis) {
            try {
                $client = $this->clients->connectLaravelDefault();
                if (! $client) {
                    throw new \RuntimeException('Laravel Redis default connection is not configured.');
                }

                $pong = $client->ping();
                $ready = $this->pingSucceeded($pong);
                $endpointSummary = $this->laravelEndpointSummary();
                $client->close();
            } catch (\Throwable $exception) {
                $ready = false;
                $lastError = $exception->getMessage();
                $endpointSummary = $this->laravelEndpointSummary();
            }
        }

        return [
            'usesRedis' => $usesRedis,
            'ready' => $ready,
            'lastError' => $lastError,
            'endpointSummary' => $endpointSummary,
            'session' => [
                'driver' => $sessionDriver,
                'connection' => config('session.connection'),
                'store' => config('session.store'),
            ],
            'cache' => [
                'store' => $cacheStore,
                'driver' => $cacheConfig['driver'] ?? null,
                'connection' => $cacheConfig['connection'] ?? null,
            ],
            'queue' => [
                'connection' => $queueConnection,
                'driver' => $queueConfig['driver'] ?? null,
                'redisConnection' => $queueConfig['connection'] ?? null,
                'queue' => $queueConfig['queue'] ?? null,
            ],
            'broadcast' => [
                'driver' => $broadcastDriver,
                'connection' => $broadcastConfig['driver'] ?? null,
                'reverbScalingEnabled' => $reverbScalingEnabled,
            ],
        ];
    }

    private function panelRuntimeSummary(array $profile): array
    {
        if (! $profile['redisEnabled']) {
            return [
                'ready' => false,
                'lastError' => null,
                'endpointSummary' => $this->profiles->endpointSummary($profile),
            ];
        }

        $result = $this->runConnectionTest($profile);

        return [
            'ready' => (bool) $result['ok'],
            'lastError' => $result['error'] ?? null,
            'endpointSummary' => $result['endpointSummary'],
        ];
    }

    private function usageSummary(array $framework, array $panelRuntime): array
    {
        return [
            $this->quota->adminUsageSummary($panelRuntime['ready'], $panelRuntime['lastError']),
            $this->connectorQueue->adminUsageSummary($panelRuntime['ready'], $panelRuntime['lastError']),
            $this->uiWebsocket->adminUsageSummary($panelRuntime['ready'], $panelRuntime['lastError']),
            [
                'key' => 'framework_cache',
                'label' => 'Framework cache',
                'source' => 'env',
                'active' => ($framework['cache']['driver'] ?? null) === 'redis',
                'ready' => ($framework['cache']['driver'] ?? null) === 'redis' ? $framework['ready'] : false,
                'required' => false,
                'degraded' => ($framework['cache']['driver'] ?? null) === 'redis' && ! $framework['ready'],
                'lastError' => ($framework['cache']['driver'] ?? null) === 'redis' ? $framework['lastError'] : null,
                'details' => $framework['cache'],
            ],
            [
                'key' => 'framework_session',
                'label' => 'Framework session',
                'source' => 'env',
                'active' => ($framework['session']['driver'] ?? null) === 'redis',
                'ready' => ($framework['session']['driver'] ?? null) === 'redis' ? $framework['ready'] : false,
                'required' => false,
                'degraded' => ($framework['session']['driver'] ?? null) === 'redis' && ! $framework['ready'],
                'lastError' => ($framework['session']['driver'] ?? null) === 'redis' ? $framework['lastError'] : null,
                'details' => $framework['session'],
            ],
            [
                'key' => 'framework_queue',
                'label' => 'Framework queue',
                'source' => 'env',
                'active' => ($framework['queue']['driver'] ?? null) === 'redis',
                'ready' => ($framework['queue']['driver'] ?? null) === 'redis' ? $framework['ready'] : false,
                'required' => false,
                'degraded' => ($framework['queue']['driver'] ?? null) === 'redis' && ! $framework['ready'],
                'lastError' => ($framework['queue']['driver'] ?? null) === 'redis' ? $framework['lastError'] : null,
                'details' => $framework['queue'],
            ],
            [
                'key' => 'framework_broadcast',
                'label' => 'Framework broadcast / Reverb scaling',
                'source' => 'env',
                'active' => ($framework['broadcast']['driver'] ?? null) === 'redis' || ($framework['broadcast']['reverbScalingEnabled'] ?? false),
                'ready' => (($framework['broadcast']['driver'] ?? null) === 'redis' || ($framework['broadcast']['reverbScalingEnabled'] ?? false)) ? $framework['ready'] : false,
                'required' => false,
                'degraded' => (($framework['broadcast']['driver'] ?? null) === 'redis' || ($framework['broadcast']['reverbScalingEnabled'] ?? false)) && ! $framework['ready'],
                'lastError' => (($framework['broadcast']['driver'] ?? null) === 'redis' || ($framework['broadcast']['reverbScalingEnabled'] ?? false)) ? $framework['lastError'] : null,
                'details' => $framework['broadcast'],
            ],
        ];
    }

    private function warnings(array $profile, array $framework, array $runtime, array $usage): array
    {
        $warnings = [[
            'level' => 'info',
            'text' => 'Stored Redis settings do not hot-rebind Laravel cache, session, queue, broadcast, or Reverb runtime. Those still boot from env and framework config.',
        ]];

        if ($profile['redisEnabled'] && ! $runtime['ready']) {
            $warnings[] = [
                'level' => $profile['redisRequired'] ? 'error' : 'warning',
                'text' => $profile['redisRequired']
                    ? 'Panel-managed Redis is required but not ready. Optional fast-path services are degraded and need operator attention.'
                    : 'Panel-managed Redis is enabled but not ready. RA-panel will bypass optional Redis fast paths where possible.',
            ];
        }

        if ($framework['usesRedis'] && ! $framework['ready']) {
            $warnings[] = [
                'level' => 'warning',
                'text' => 'Laravel is configured to use env-backed Redis for at least one framework subsystem, but the current runtime check failed.',
            ];
        }

        if (collect($usage)->contains(fn (array $item): bool => (bool) ($item['degraded'] ?? false))) {
            $warnings[] = [
                'level' => 'warning',
                'text' => 'One or more Redis consumers are in a degraded state. Review the usage table before treating Redis as active in production.',
            ];
        }

        return $warnings;
    }

    private function runConnectionTest(array $profile): array
    {
        try {
            $client = $this->clients->connect($profile);
            $pong = $client->ping();
            $client->close();

            return [
                'ok' => $this->pingSucceeded($pong),
                'error' => null,
                'testedAt' => now()->toISOString(),
                'source' => 'panel_settings',
                'endpointSummary' => $this->profiles->endpointSummary($profile),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => $exception->getMessage(),
                'testedAt' => now()->toISOString(),
                'source' => 'panel_settings',
                'endpointSummary' => $this->profiles->endpointSummary($profile),
            ];
        }
    }

    private function pingSucceeded(mixed $pong): bool
    {
        return $pong === true || $pong === '+PONG' || strtoupper((string) $pong) === 'PONG';
    }

    private function laravelEndpointSummary(): string
    {
        $default = config('database.redis.default', []);

        if (! is_array($default)) {
            return 'Laravel default Redis connection is not configured.';
        }

        if (filled($default['url'] ?? null)) {
            return $this->profiles->endpointSummary([
                'redisUrl' => $default['url'],
                'redisTls' => str_starts_with((string) $default['url'], 'rediss://'),
                'redisHost' => $default['host'] ?? '127.0.0.1',
                'redisPort' => $default['port'] ?? 6379,
                'redisDb' => $default['database'] ?? 0,
            ]);
        }

        return sprintf(
            '%s://%s:%s/db%s',
            str_starts_with((string) ($default['url'] ?? ''), 'rediss://') ? 'rediss' : 'redis',
            $default['host'] ?? '127.0.0.1',
            $default['port'] ?? 6379,
            $default['database'] ?? 0,
        );
    }

    private function forgetCachedPayload(): void
    {
        Cache::forget(self::STATUS_CACHE_KEY);
    }
}
