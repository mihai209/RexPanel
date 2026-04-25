<?php

namespace Tests\Feature;

use App\Console\Commands\ConnectorServer;
use App\Models\Image;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ConnectorQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;
use Workerman\Connection\TcpConnection;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

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

    private function createNode(array $attributes = []): Node
    {
        $location = Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ]);

        $node = Node::query()->create(array_merge([
            'name' => 'node-1',
            'location_id' => $location->id,
            'fqdn' => 'node.local',
            'daemon_port' => 8080,
            'daemon_sftp_port' => 2022,
            'daemon_token' => 'token',
            'daemon_base' => '/var/lib/ra-panel',
            'memory_limit' => 16384,
            'memory_overallocate' => 0,
            'disk_limit' => 102400,
            'disk_overallocate' => 0,
            'is_public' => true,
            'maintenance_mode' => false,
            'last_heartbeat' => now(),
        ], $attributes));

        ConnectorServer::setTestingNodeConnectionState($node->id, true);

        return $node;
    }

    private function createPackage(array $attributes = []): Package
    {
        return Package::query()->create(array_merge([
            'slug' => 'minecraft',
            'name' => 'Minecraft',
        ], $attributes));
    }

    private function createImage(Package $package, array $attributes = []): Image
    {
        return Image::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'package_id' => $package->id,
            'name' => 'Paper',
            'description' => 'Paper image',
            'author' => 'author@example.com',
            'docker_image' => 'ghcr.io/cpanel/yolks:java_21',
            'docker_images' => ['Java 21' => 'ghcr.io/cpanel/yolks:java_21'],
            'features' => [],
            'file_denylist' => [],
            'startup' => 'java -jar server.jar',
            'variables' => [],
        ], $attributes));
    }

    private function createAllocation(Node $node, array $attributes = []): NodeAllocation
    {
        return NodeAllocation::query()->create(array_merge([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ], $attributes));
    }

    public function test_admin_can_fetch_grouped_settings_payload_with_defaults(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/settings');

        $response->assertOk();
        $response->assertJsonPath('settings.brandName', 'RA-panel');
        $response->assertJsonPath('settings.faviconUrl', '/favicon.ico');
        $response->assertJsonPath('settings.aiDailyQuota', 100);
        $response->assertJsonPath('settings.abuseScoreWindowHours', 72);
        $response->assertJsonPath('settings.serviceHealthCheckIntervalSeconds', 300);
        $response->assertJsonPath('settings.claimDailyStreakMax', 30);
        $response->assertJsonPath('settings.afkRewardActivePeriod', 'minute');
        $response->assertJsonPath('sections.branding.state', 'active');
        $response->assertJsonPath('sections.feature_toggles.fields.featureSftpEnabled.state', 'active');
        $response->assertJsonPath('sections.feature_toggles.fields.featureClaimRewardsEnabled.state', 'active');
        $response->assertJsonPath('sections.feature_toggles.fields.featurePolicyEngineEnabled.state', 'active');
        $response->assertJsonPath('sections.feature_toggles.fields.featureAntiMinerEnabled.state', 'active');
        $response->assertJsonPath('sections.rewards_economy.state', 'active');
        $response->assertJsonPath('sections.rewards_economy.fields.economyUnit.state', 'active');
        $response->assertJsonPath('sections.rewards_economy.fields.autoRemediationCooldownSeconds.state', 'active');
        $response->assertJsonPath('sections.commerce.state', 'active');
        $response->assertJsonPath('sections.commerce.fields.featureUserStoreEnabled.state', 'active');
        $response->assertJsonPath('sections.commerce.fields.commerceRevenueGraceDays.state', 'active');
        $response->assertJsonPath('sections.auth_providers.state', 'external');
        $response->assertJsonPath('tabs.7.key', 'commerce');
    }

    public function test_admin_can_update_branding_and_expanded_settings_catalog(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'SecurePanel',
            'faviconUrl' => 'https://example.com/favicon.png',
            'aiDailyQuota' => 42,
            'featureWebUploadEnabled' => true,
            'featureWebUploadMaxMb' => 128,
            'connectorConsoleThrottleLines' => 3000,
            'connectorRootlessEnabled' => true,
            'connectorRootlessContainerUid' => 1001,
            'economyUnit' => 'Tokens',
            'abuseScoreWindowHours' => 48,
            'claimDailyStreakMax' => 60,
            'afkRewardActivePeriod' => 'week',
            'featureUserStoreEnabled' => true,
            'featureRevenueModeEnabled' => true,
            'commerceCpuPricePerUnit' => 12,
            'commerceRevenueGraceDays' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.brandName', 'SecurePanel');
        $response->assertJsonPath('data.settings.aiDailyQuota', 42);
        $response->assertJsonPath('data.settings.featureWebUploadEnabled', true);
        $response->assertJsonPath('data.settings.featureWebUploadMaxMb', 128);
        $response->assertJsonPath('data.settings.connectorConsoleThrottleLines', 3000);
        $response->assertJsonPath('data.settings.connectorRootlessEnabled', true);
        $response->assertJsonPath('data.settings.connectorRootlessContainerUid', 1001);
        $response->assertJsonPath('data.settings.economyUnit', 'Tokens');
        $response->assertJsonPath('data.settings.abuseScoreWindowHours', 48);
        $response->assertJsonPath('data.settings.claimDailyStreakMax', 60);
        $response->assertJsonPath('data.settings.afkRewardActivePeriod', 'week');
        $response->assertJsonPath('data.settings.featureUserStoreEnabled', true);
        $response->assertJsonPath('data.settings.featureRevenueModeEnabled', true);
        $response->assertJsonPath('data.settings.commerceCpuPricePerUnit', 12);
        $response->assertJsonPath('data.settings.commerceRevenueGraceDays', 5);

        $this->assertDatabaseHas('system_settings', ['key' => 'brandName', 'value' => 'SecurePanel']);
        $this->assertDatabaseHas('system_settings', ['key' => 'faviconUrl', 'value' => 'https://example.com/favicon.png']);
        $this->assertDatabaseHas('system_settings', ['key' => 'aiDailyQuota', 'value' => '42']);
        $this->assertDatabaseHas('system_settings', ['key' => 'featureWebUploadEnabled', 'value' => 'true']);
        $this->assertDatabaseHas('system_settings', ['key' => 'featureWebUploadMaxMb', 'value' => '128']);
        $this->assertDatabaseHas('system_settings', ['key' => 'connectorConsoleThrottleLines', 'value' => '3000']);
        $this->assertDatabaseHas('system_settings', ['key' => 'connectorRootlessContainerUid', 'value' => '1001']);
        $this->assertDatabaseHas('system_settings', ['key' => 'economyUnit', 'value' => 'Tokens']);
        $this->assertDatabaseHas('system_settings', ['key' => 'claimDailyStreakMax', 'value' => '60']);
        $this->assertDatabaseHas('system_settings', ['key' => 'commerceCpuPricePerUnit', 'value' => '12']);
        $this->assertDatabaseHas('system_settings', ['key' => 'commerceRevenueGraceDays', 'value' => '5']);
    }

    public function test_non_admin_cannot_access_settings_endpoints(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'Denied',
        ])->assertForbidden();
    }

    public function test_regenerating_daemon_token_disconnects_all_live_connector_sessions_immediately(): void
    {
        $this->actingAsAdmin();

        $node = $this->createNode([
            'daemon_token' => 'old-token',
            'last_heartbeat' => now(),
        ]);

        $primaryConnection = Mockery::mock(TcpConnection::class);
        $primaryConnection->shouldReceive('send')->once()->withArgs(function ($payload) {
            $decoded = json_decode($payload, true);

            return ($decoded['type'] ?? null) === 'auth_fail'
                && str_contains((string) ($decoded['error'] ?? ''), 'Token was regenerated');
        });
        $primaryConnection->shouldReceive('close')->once();

        $secondaryConnection = Mockery::mock(TcpConnection::class);
        $secondaryConnection->shouldReceive('send')->once()->withArgs(function ($payload) {
            $decoded = json_decode($payload, true);

            return ($decoded['type'] ?? null) === 'auth_fail';
        });
        $secondaryConnection->shouldReceive('close')->once();

        ConnectorServer::$nodeConnections = [
            ConnectorServer::connectionKey($node->id, 'rex', $node->id) => $primaryConnection,
            ConnectorServer::connectionKey($node->id, 'rex', 999) => $secondaryConnection,
        ];

        ConnectorServer::setTestingNodeConnectionState($node->id, true, 'rex', $node->id);
        ConnectorServer::setTestingNodeConnectionState($node->id, true, 'rex', 999);

        $response = $this->postJson("/api/v1/admin/nodes/{$node->id}/regenerate-token");

        $response->assertOk();
        $response->assertJsonPath('message', 'Token regenerated. Connector has been disconnected.');
        $this->assertNotSame('old-token', $response->json('token'));

        $node->refresh();
        $this->assertNull($node->last_heartbeat);
        $this->assertNotSame('old-token', $node->daemon_token);
        $this->assertFalse(ConnectorServer::isNodeConnected($node->id, 'rex', $node->id));
        $this->assertFalse(ConnectorServer::isNodeConnected($node->id, 'rex', 999));
        $this->assertSame([], ConnectorServer::$nodeConnections);
    }

    public function test_missing_boolean_fields_are_stored_deterministically_as_false_on_save(): void
    {
        $this->actingAsAdmin();

        SystemSetting::query()->create(['key' => 'featureSftpEnabled', 'value' => 'true']);

        $response = $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'RA-panel',
            'faviconUrl' => '/favicon.ico',
            'aiDailyQuota' => 100,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.featureSftpEnabled', false);
        $this->assertDatabaseHas('system_settings', ['key' => 'featureSftpEnabled', 'value' => 'false']);
    }

    public function test_integer_settings_are_clamped_to_cpanel_bounds(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'SecurePanel',
            'faviconUrl' => '/favicon.ico',
            'aiDailyQuota' => 999999,
            'featureWebUploadMaxMb' => 999999,
            'abuseScoreWindowHours' => 999999,
            'serviceHealthCheckIntervalSeconds' => 1,
            'connectorRootlessContainerUid' => 999999,
            'claimDailyStreakMax' => 0,
            'afkRewardActivePeriod' => 'invalid-period',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.aiDailyQuota', 10000);
        $response->assertJsonPath('data.settings.featureWebUploadMaxMb', 2048);
        $response->assertJsonPath('data.settings.abuseScoreWindowHours', 720);
        $response->assertJsonPath('data.settings.serviceHealthCheckIntervalSeconds', 30);
        $response->assertJsonPath('data.settings.connectorRootlessContainerUid', 65535);
        $response->assertJsonPath('data.settings.claimDailyStreakMax', 1);
        $response->assertJsonPath('data.settings.afkRewardActivePeriod', 'minute');

        $this->assertDatabaseHas('system_settings', ['key' => 'serviceHealthCheckIntervalSeconds', 'value' => '30']);
        $this->assertDatabaseHas('system_settings', ['key' => 'connectorRootlessContainerUid', 'value' => '65535']);
        $this->assertDatabaseHas('system_settings', ['key' => 'claimDailyStreakMax', 'value' => '1']);
    }

    public function test_node_configuration_reflects_saved_connector_runtime_and_security_settings(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();

        $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'SecurePanel',
            'faviconUrl' => '/favicon.ico',
            'aiDailyQuota' => 100,
            'featureSftpEnabled' => false,
            'featureWebUploadEnabled' => false,
            'featureWebUploadMaxMb' => 64,
            'featureRemoteDownloadEnabled' => false,
            'connectorSftpReadOnly' => true,
            'connectorApiHost' => '127.0.0.1',
            'connectorApiSslEnabled' => true,
            'connectorApiSslCertPath' => '/etc/ssl/cert.pem',
            'connectorApiSslKeyPath' => '/etc/ssl/key.pem',
            'connectorApiTrustedProxies' => "10.0.0.0/8\n192.168.1.1",
            'connectorDiskCheckTtlSeconds' => 120,
            'connectorTransferDownloadLimit' => 250,
            'connectorConsoleThrottleEnabled' => true,
            'connectorConsoleThrottleLines' => 4096,
            'connectorConsoleThrottleIntervalMs' => 300,
            'connectorRootlessEnabled' => true,
            'connectorRootlessContainerUid' => 1000,
            'connectorRootlessContainerGid' => 1001,
            'crashDetectionEnabled' => true,
            'crashDetectCleanExitAsCrash' => false,
            'crashDetectionCooldownSeconds' => 90,
        ])->assertOk();

        $response = $this->getJson("/api/v1/admin/nodes/{$node->id}/configuration");

        $response->assertOk();
        $response->assertJsonPath('connector.host', '127.0.0.1');
        $response->assertJsonPath('panels.0.type', 'rex');
        $response->assertJsonPath('panels.0.namespace', 'rex');
        $response->assertJsonPath('panels.0.connector.id', $node->id);
        $response->assertJsonPath('panels.0.connector.token', 'token');
        $response->assertJsonPath('panels.0.panel.url', rtrim(config('app.url'), '/'));
        $response->assertJsonPath('sftp.enabled', false);
        $response->assertJsonPath('sftp.port', null);
        $response->assertJsonPath('sftp.readOnly', true);
        $response->assertJsonPath('api.ssl.enabled', true);
        $response->assertJsonPath('api.ssl.cert', '/etc/ssl/cert.pem');
        $response->assertJsonPath('api.trustedProxies.0', '10.0.0.0/8');
        $response->assertJsonPath('api.trustedProxies.1', '192.168.1.1');
        $response->assertJsonPath('system.diskCheckTtlSeconds', 120);
        $response->assertJsonPath('system.healthChecks.enabled', false);
        $response->assertJsonPath('system.healthChecks.intervalSeconds', 300);
        $response->assertJsonPath('monitoring.features.policyEngineEnabled', false);
        $response->assertJsonPath('monitoring.antiMiner.highCpuPercent', 95);
        $response->assertJsonPath('transfers.downloadLimit', 250);
        $response->assertJsonPath('throttles.lines', 4096);
        $response->assertJsonPath('docker.rootless.enabled', true);
        $response->assertJsonPath('docker.rootless.container_uid', 1000);
        $response->assertJsonPath('crashPolicy.detectCleanExitAsCrash', false);
        $response->assertJsonPath('features.webUploadEnabled', false);
        $response->assertJsonPath('features.webUploadMaxMb', 64);
        $response->assertJsonPath('features.remoteDownloadEnabled', false);
    }

    public function test_node_configuration_can_append_shared_rocky_panel_entry_from_env(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();

        putenv('CONNECTOR_SHARED_ROCKY_URL=https://rocky.example.com');
        putenv('CONNECTOR_SHARED_ROCKY_WS_URL=wss://rocky.example.com/ws/connector');
        putenv('CONNECTOR_SHARED_ROCKY_ID=77');
        putenv('CONNECTOR_SHARED_ROCKY_TOKEN=rocky-token');
        putenv('CONNECTOR_SHARED_ROCKY_NAME=Rocky Peer');
        putenv('CONNECTOR_SHARED_ROCKY_ALLOWED_URLS=https://rocky.example.com');

        try {
            $response = $this->getJson("/api/v1/admin/nodes/{$node->id}/configuration");

            $response->assertOk();
            $response->assertJsonPath('panels.0.type', 'rex');
            $response->assertJsonPath('panels.1.type', 'rocky');
            $response->assertJsonPath('panels.1.connector.id', 77);
            $response->assertJsonPath('panels.1.connector.token', 'rocky-token');
            $response->assertJsonPath('panels.1.panel.url', 'https://rocky.example.com');
            $response->assertJsonPath('panels.1.panel.ws_url', 'wss://rocky.example.com/ws/connector');
        } finally {
            putenv('CONNECTOR_SHARED_ROCKY_URL');
            putenv('CONNECTOR_SHARED_ROCKY_WS_URL');
            putenv('CONNECTOR_SHARED_ROCKY_ID');
            putenv('CONNECTOR_SHARED_ROCKY_TOKEN');
            putenv('CONNECTOR_SHARED_ROCKY_NAME');
            putenv('CONNECTOR_SHARED_ROCKY_ALLOWED_URLS');
        }
    }

    public function test_server_install_payload_includes_runtime_settings(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $node = $this->createNode();
        $allocation = $this->createAllocation($node);
        $owner = User::query()->create([
            'username' => 'owner4',
            'email' => 'owner4@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $this->putJson('/api/v1/admin/settings', [
            'brandName' => 'SecurePanel',
            'faviconUrl' => '/favicon.ico',
            'aiDailyQuota' => 100,
            'featureSftpEnabled' => false,
            'featureWebUploadEnabled' => true,
            'featureWebUploadMaxMb' => 96,
            'featureRemoteDownloadEnabled' => false,
            'connectorSftpReadOnly' => true,
            'connectorApiHost' => '10.10.10.10',
            'connectorApiTrustedProxies' => "10.0.0.0/8\n172.16.0.0/12",
            'connectorRootlessEnabled' => true,
            'connectorRootlessContainerUid' => 2000,
            'connectorRootlessContainerGid' => 3000,
            'connectorConsoleThrottleLines' => 2048,
            'connectorConsoleThrottleIntervalMs' => 250,
            'connectorDiskCheckTtlSeconds' => 33,
            'connectorTransferDownloadLimit' => 512,
            'featureServiceHealthChecksEnabled' => true,
            'serviceHealthCheckIntervalSeconds' => 75,
            'crashDetectionEnabled' => true,
            'crashDetectCleanExitAsCrash' => true,
            'crashDetectionCooldownSeconds' => 45,
        ])->assertOk();

        $queue = Mockery::mock(ConnectorQueueService::class);
        $queue->shouldReceive('publish')
            ->once()
            ->withArgs(function (array $payload) {
                return ($payload['panel_type'] ?? null) === 'rex'
                    && ($payload['connector_id'] ?? null) === $payload['node_id']
                    && ($payload['payload']['type'] ?? null) === 'install_server'
                    && ($payload['payload']['config']['featureFlags']['sftpEnabled'] ?? true) === false
                    && ($payload['payload']['config']['featureFlags']['webUploadMaxMb'] ?? null) === 96
                    && ($payload['payload']['config']['featureFlags']['remoteDownloadEnabled'] ?? true) === false
                    && ($payload['payload']['config']['crashPolicy']['cooldownSeconds'] ?? null) === 45
                    && ($payload['payload']['config']['connectorRuntime']['sftp']['readOnly'] ?? false) === true
                    && ($payload['payload']['config']['connectorRuntime']['api']['host'] ?? null) === '10.10.10.10'
                    && ($payload['payload']['config']['connectorRuntime']['docker']['rootless']['container_uid'] ?? null) === 2000
                    && ($payload['payload']['config']['connectorRuntime']['system']['healthChecks']['enabled'] ?? false) === true
                    && ($payload['payload']['config']['connectorRuntime']['system']['healthChecks']['intervalSeconds'] ?? null) === 75
                    && ($payload['payload']['config']['connectorRuntime']['monitoring']['healthChecks']['intervalSeconds'] ?? null) === 75
                    && ($payload['payload']['config']['connectorRuntime']['transfers']['downloadLimit'] ?? null) === 512
                    && ($payload['payload']['config']['connectorRuntime']['throttles']['lineResetInterval'] ?? null) === 250;
            })
            ->andReturn(true);
        $this->app->instance(ConnectorQueueService::class, $queue);

        $response = $this->postJson('/api/v1/admin/servers', [
            'name' => 'srv-install',
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [
                'SERVER_JARFILE' => 'custom.jar',
            ],
            'start_on_completion' => true,
        ]);

        $response->assertCreated();
    }

    public function test_app_shell_uses_saved_branding_and_still_loads_without_settings(): void
    {
        $defaultResponse = $this->get('/');

        $defaultResponse->assertOk();
        $defaultResponse->assertSee('<title>RA-panel</title>', false);
        $defaultResponse->assertSee('href="/favicon.ico"', false);

        SystemSetting::query()->create(['key' => 'brandName', 'value' => 'SecurePanel']);
        SystemSetting::query()->create(['key' => 'faviconUrl', 'value' => '/branding/secure.ico']);

        $customResponse = $this->get('/');

        $customResponse->assertOk();
        $customResponse->assertSee('<title>SecurePanel</title>', false);
        $customResponse->assertSee('href="/branding/secure.ico"', false);
        $customResponse->assertSee('window.RA_PANEL_BOOTSTRAP', false);
        $customResponse->assertSee('SecurePanel', false);
    }
}
