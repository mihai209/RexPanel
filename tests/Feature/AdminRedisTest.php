<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiQuotaRedisService;
use App\Services\ConnectorQueueService;
use App\Services\PanelRedisClient;
use App\Services\PanelRedisClientFactory;
use App\Services\PanelRedisProfileStore;
use App\Services\UiWebsocketRedisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminRedisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function actingAsAdmin(): User
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsUser(): User
    {
        $user = User::query()->create([
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_can_get_put_and_test_redis_endpoints(): void
    {
        $this->actingAsAdmin();

        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $panelClient = Mockery::mock(PanelRedisClient::class);
        $frameworkClient = Mockery::mock(PanelRedisClient::class);

        $factory->shouldReceive('connect')->times(2)->andReturn($panelClient);
        $factory->shouldReceive('connectLaravelDefault')->zeroOrMoreTimes()->andReturn($frameworkClient);
        $panelClient->shouldReceive('ping')->times(2)->andReturn('+PONG');
        $panelClient->shouldReceive('close')->times(2);
        $frameworkClient->shouldReceive('ping')->zeroOrMoreTimes()->andReturn('+PONG');
        $frameworkClient->shouldReceive('close')->zeroOrMoreTimes();

        $this->app->instance(PanelRedisClientFactory::class, $factory);

        $this->getJson('/api/v1/admin/redis')->assertOk();

        $this->putJson('/api/v1/admin/redis', [
            'redisEnabled' => true,
            'redisRequired' => true,
            'redisHost' => '10.0.0.15',
            'redisPort' => 6380,
            'redisDb' => 4,
            'redisUsername' => 'panel',
            'redisPassword' => 'super-secret',
            'redisTls' => true,
            'redisSessionPrefix' => 'ra-panel',
        ])->assertOk()
            ->assertJsonPath('data.config.hasPassword', true)
            ->assertJsonPath('data.runtime.source', 'panel_settings');

        $this->postJson('/api/v1/admin/redis/test', [
            'redisHost' => '10.0.0.15',
            'redisPort' => 6380,
            'redisDb' => 4,
            'redisTls' => true,
        ])->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_non_admin_and_guest_are_rejected(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/v1/admin/redis')->assertForbidden();
        $this->putJson('/api/v1/admin/redis', ['redisEnabled' => true])->assertForbidden();
        $this->postJson('/api/v1/admin/redis/test', ['redisHost' => '127.0.0.1'])->assertForbidden();

        auth()->forgetGuards();

        $this->getJson('/api/v1/admin/redis')->assertUnauthorized();
        $this->putJson('/api/v1/admin/redis', ['redisEnabled' => true])->assertUnauthorized();
        $this->postJson('/api/v1/admin/redis/test', ['redisHost' => '127.0.0.1'])->assertUnauthorized();
    }

    public function test_host_mode_persists_expected_keys_and_get_masks_password(): void
    {
        $this->actingAsAdmin();
        $this->bindRedisFactoryWithFailures();

        $this->putJson('/api/v1/admin/redis', [
            'redisEnabled' => true,
            'redisRequired' => false,
            'redisHost' => 'cache.internal',
            'redisPort' => 6381,
            'redisDb' => 7,
            'redisUsername' => 'quota-user',
            'redisPassword' => 'top-secret',
            'redisTls' => true,
            'redisSessionPrefix' => 'panel-fast-paths',
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'redisHost', 'value' => 'cache.internal']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisPort', 'value' => '6381']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisDb', 'value' => '7']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisUsername', 'value' => 'quota-user']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisPassword', 'value' => 'top-secret']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisTls', 'value' => 'true']);

        $this->getJson('/api/v1/admin/redis')
            ->assertOk()
            ->assertJsonPath('config.redisPassword', null)
            ->assertJsonPath('config.hasPassword', true)
            ->assertJsonMissing(['top-secret']);
    }

    public function test_url_mode_persists_expected_keys_and_blank_password_preserves_existing_secret(): void
    {
        $this->actingAsAdmin();
        $this->bindRedisFactoryWithFailures();

        SystemSetting::query()->create(['key' => 'redisPassword', 'value' => 'existing-secret']);

        $this->putJson('/api/v1/admin/redis', [
            'redisEnabled' => true,
            'redisRequired' => true,
            'redisUrl' => 'redis://panel-user:masked@cache.example.com:6390/2',
            'redisUsername' => 'panel-user',
            'redisPassword' => '',
            'redisTls' => false,
        ])->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'redisUrl', 'value' => 'redis://panel-user:masked@cache.example.com:6390/2']);
        $this->assertDatabaseHas('system_settings', ['key' => 'redisPassword', 'value' => 'existing-secret']);
    }

    public function test_connection_test_does_not_mutate_framework_runtime_config(): void
    {
        $this->actingAsAdmin();
        config([
            'database.redis.default.host' => 'env-redis',
            'database.redis.default.port' => 6379,
            'database.redis.default.database' => 0,
        ]);

        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $panelClient = Mockery::mock(PanelRedisClient::class);

        $factory->shouldReceive('connect')->once()->andReturn($panelClient);
        $factory->shouldReceive('connectLaravelDefault')->never();
        $panelClient->shouldReceive('ping')->once()->andReturn('+PONG');
        $panelClient->shouldReceive('close')->once();

        $this->app->instance(PanelRedisClientFactory::class, $factory);

        $this->postJson('/api/v1/admin/redis/test', [
            'redisEnabled' => true,
            'redisHost' => 'panel-redis',
            'redisPort' => 6391,
            'redisDb' => 5,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('env-redis', config('database.redis.default.host'));
        $this->assertSame(6379, config('database.redis.default.port'));
        $this->assertSame(0, config('database.redis.default.database'));
    }

    public function test_required_panel_redis_reports_degraded_usage_when_unavailable(): void
    {
        $this->actingAsAdmin();
        SystemSetting::query()->insert([
            ['key' => 'redisEnabled', 'value' => 'true'],
            ['key' => 'redisRequired', 'value' => 'true'],
            ['key' => 'redisHost', 'value' => 'missing-redis'],
            ['key' => 'redisPort', 'value' => '6379'],
            ['key' => 'redisDb', 'value' => '0'],
        ]);

        $this->bindRedisFactoryWithFailures();

        $response = $this->getJson('/api/v1/admin/redis');

        $response->assertOk();
        $response->assertJsonPath('runtime.ready', false);
        $response->assertJsonPath('usage.0.key', 'ai_quota');
        $response->assertJsonPath('usage.0.degraded', true);
        $response->assertJsonPath('warnings.1.level', 'error');
    }

    public function test_ai_quota_service_uses_same_key_format_and_degrades_cleanly(): void
    {
        $client = Mockery::mock(PanelRedisClient::class);
        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $factory->shouldReceive('connectStored')->twice()->andReturn($client);
        $client->shouldReceive('mget')->once()->with([
            'ai:quota:4:' . now()->toDateString(),
        ])->andReturn(['3']);
        $client->shouldReceive('del')->once()->with('ai:quota:4:' . now()->toDateString())->andThrow(new \RuntimeException('offline'));
        $client->shouldReceive('close')->twice();

        $service = new AiQuotaRedisService($factory, app(PanelRedisProfileStore::class));

        $this->assertSame(3, $service->usageForUser(4));
        $service->resetUsage(4);
        $this->assertSame('ai:quota:4:' . now()->toDateString(), $service->usageKey(4));
    }

    public function test_connector_queue_service_preserves_payload_shape(): void
    {
        $client = Mockery::mock(PanelRedisClient::class);
        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $factory->shouldReceive('connectStored')->once()->andReturn($client);
        $client->shouldReceive('rpush')->once()->withArgs(function (string $queue, string $payload): bool {
            $decoded = json_decode($payload, true);

            return $queue === 'connector:queue'
                && ($decoded['node_id'] ?? null) === 9
                && ($decoded['payload']['type'] ?? null) === 'install_server';
        })->andReturn(1);
        $client->shouldReceive('close')->once();

        $service = new ConnectorQueueService($factory, app(PanelRedisProfileStore::class));

        $this->assertTrue($service->publish([
            'node_id' => 9,
            'payload' => ['type' => 'install_server', 'serverId' => 12],
        ]));
    }

    public function test_ui_websocket_service_preserves_payload_shape_and_reads_connection_count(): void
    {
        $client = Mockery::mock(PanelRedisClient::class);
        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $factory->shouldReceive('connectStored')->twice()->andReturn($client);
        $client->shouldReceive('rpush')->once()->withArgs(function (string $queue, string $payload): bool {
            $decoded = json_decode($payload, true);

            return $queue === 'ui_ws:queue'
                && ($decoded['user_id'] ?? null) === 14
                && ($decoded['payload']['type'] ?? null) === 'notification:new';
        })->andReturn(1);
        $client->shouldReceive('get')->once()->with('ui_ws:user:14:connections')->andReturn('2');
        $client->shouldReceive('close')->twice();

        $service = new UiWebsocketRedisService($factory, app(PanelRedisProfileStore::class));

        $this->assertTrue($service->publishUserEvent(14, ['type' => 'notification:new']));
        $this->assertSame(2, $service->activeSocketCount(14));
    }

    private function bindRedisFactoryWithFailures(): void
    {
        $factory = Mockery::mock(PanelRedisClientFactory::class);
        $factory->shouldReceive('connect')->andThrow(new \RuntimeException('Connection refused'));
        $factory->shouldReceive('connectLaravelDefault')->andReturn(null);
        $this->app->instance(PanelRedisClientFactory::class, $factory);
    }
}
