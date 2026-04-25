<?php

namespace Tests\Feature;

use App\Models\PanelIncident;
use App\Models\PanelMaintenanceWindow;
use App\Models\PanelSecurityAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtensionsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'username' => 'user-' . fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'is_admin' => false,
        ], $attributes));
    }

    public function test_non_admin_users_cannot_access_admin_extensions_endpoints(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/extensions')->assertForbidden();
        $this->putJson('/api/v1/admin/extensions/announcer', [
            'enabled' => true,
            'severity' => 'warning',
            'message' => 'Denied',
        ])->assertForbidden();
        $this->postJson('/api/v1/admin/extensions/incidents', [
            'title' => 'Denied',
            'message' => 'Denied',
            'severity' => 'warning',
        ])->assertForbidden();
    }

    public function test_admin_can_save_announcer_and_runtime_status_only_returns_enabled_modules(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/extensions/announcer', [
            'enabled' => true,
            'severity' => 'critical',
            'message' => 'Major maintenance tonight.',
        ])->assertOk()
            ->assertJsonPath('settings.announcer.enabled', true)
            ->assertJsonPath('settings.announcer.severity', 'critical');

        PanelIncident::query()->create([
            'title' => 'Node outage',
            'message' => 'A node is down.',
            'severity' => 'warning',
            'status' => 'open',
        ]);

        PanelMaintenanceWindow::query()->create([
            'title' => 'Planned restart',
            'message' => 'Short reboot.',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        PanelSecurityAlert::query()->create([
            'title' => 'Suspicious login activity',
            'message' => 'Review auth logs.',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $this->getJson('/api/v1/account/extensions/status')
            ->assertOk()
            ->assertJsonPath('announcer.message', 'Major maintenance tonight.')
            ->assertJsonCount(0, 'incidents')
            ->assertJsonCount(0, 'maintenance')
            ->assertJsonCount(0, 'security');

        $this->putJson('/api/v1/admin/extensions/incidents/settings', ['enabled' => true])->assertOk();
        $this->putJson('/api/v1/admin/extensions/maintenance/settings', ['enabled' => true])->assertOk();
        $this->putJson('/api/v1/admin/extensions/security/settings', ['enabled' => true])->assertOk();

        $this->getJson('/api/v1/extensions/status')
            ->assertOk()
            ->assertJsonCount(1, 'incidents')
            ->assertJsonCount(1, 'maintenance')
            ->assertJsonCount(1, 'security');
    }

    public function test_admin_can_create_toggle_and_delete_extension_records(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $incidentResponse = $this->postJson('/api/v1/admin/extensions/incidents', [
            'title' => 'API incident',
            'message' => 'Something failed.',
            'severity' => 'warning',
        ])->assertOk();

        $maintenanceResponse = $this->postJson('/api/v1/admin/extensions/maintenance', [
            'title' => 'Rolling restart',
            'message' => 'Short restart.',
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
        ])->assertOk();

        $securityResponse = $this->postJson('/api/v1/admin/extensions/security', [
            'title' => 'Credential stuffing',
            'message' => 'Block offenders.',
            'severity' => 'critical',
        ])->assertOk();

        $incidentId = $incidentResponse->json('incident.id');
        $maintenanceId = $maintenanceResponse->json('maintenance.id');
        $securityId = $securityResponse->json('alert.id');

        $this->postJson("/api/v1/admin/extensions/incidents/{$incidentId}/toggle")
            ->assertOk()
            ->assertJsonPath('incident.status', 'resolved');

        $this->postJson("/api/v1/admin/extensions/maintenance/{$maintenanceId}/toggle-complete")
            ->assertOk()
            ->assertJsonPath('maintenance.isCompleted', true);

        $this->postJson("/api/v1/admin/extensions/security/{$securityId}/toggle")
            ->assertOk()
            ->assertJsonPath('alert.status', 'resolved');

        $this->deleteJson("/api/v1/admin/extensions/incidents/{$incidentId}")->assertOk();
        $this->deleteJson("/api/v1/admin/extensions/maintenance/{$maintenanceId}")->assertOk();
        $this->deleteJson("/api/v1/admin/extensions/security/{$securityId}")->assertOk();

        $this->assertDatabaseCount('panel_incidents', 0);
        $this->assertDatabaseCount('panel_maintenance_windows', 0);
        $this->assertDatabaseCount('panel_security_alerts', 0);
    }

    public function test_maintenance_validation_rejects_end_before_start(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/extensions/maintenance', [
            'title' => 'Invalid window',
            'message' => 'Nope.',
            'starts_at' => now()->addHours(2)->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_admin_can_save_webhooks_validate_inputs_and_run_test_logs(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/extensions/webhooks', [
            'moduleEnabled' => true,
            'enabled' => true,
            'discordWebhook' => 'not-a-url',
            'telegramBotToken' => 'token-only',
            'events' => ['incidentCreated' => true],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/extensions/webhooks', [
            'moduleEnabled' => true,
            'enabled' => true,
            'discordWebhook' => 'https://discord.com/api/webhooks/123/token',
            'telegramBotToken' => '123456:token',
            'telegramChatId' => '555',
            'events' => [
                'incidentCreated' => true,
                'incidentResolved' => true,
                'maintenanceScheduled' => true,
                'maintenanceCompleted' => true,
                'securityAlertCreated' => true,
                'securityAlertResolved' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('settings.features.webhooksEnabled', true)
            ->assertJsonPath('settings.channels.discordConfigured', true)
            ->assertJsonPath('settings.channels.telegramConfigured', true);

        Http::fake([
            'https://discord.com/*' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson('/api/v1/admin/extensions/webhooks/test', [
            'title' => 'Probe',
            'message' => 'Testing extensions delivery.',
        ])->assertOk()
            ->assertJsonCount(2, 'logs');

        $this->assertDatabaseHas('notification_delivery_logs', [
            'template_key' => 'extension_webhook_test',
            'channel' => 'discord',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'template_key' => 'extension_webhook_test',
            'channel' => 'telegram',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('system_settings', ['key' => 'featureExtensionWebhooksEnabled', 'value' => 'true']);
        $this->assertDatabaseHas('system_settings', ['key' => 'extensionWebhooksConfig']);
    }
}
