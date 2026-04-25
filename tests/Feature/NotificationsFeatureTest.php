<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsFeatureTest extends TestCase
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

    public function test_authenticated_user_can_list_mark_read_mark_all_read_and_get_auth_unread_count(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $first = UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'First',
            'message' => 'Unread message',
            'severity' => 'info',
            'category' => 'general',
        ]);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Second',
            'message' => 'Unread message two',
            'severity' => 'warning',
            'category' => 'security',
        ]);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('notificationUnreadCount', 2);

        $this->getJson('/api/v1/account/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 2)
            ->assertJsonCount(2, 'notifications');

        $this->postJson("/api/v1/account/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('unreadCount', 1);

        $first->refresh();
        $this->assertTrue($first->is_read);
        $this->assertNotNull($first->read_at);

        $this->postJson('/api/v1/account/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unreadCount', 0);

        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->where('is_read', false)->count());
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        Sanctum::actingAs($owner);

        $notification = UserNotification::query()->create([
            'user_id' => $other->id,
            'title' => 'Private',
            'message' => 'Not yours',
        ]);

        $this->postJson("/api/v1/account/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_admin_can_send_notifications_and_create_delivery_logs(): void
    {
        Http::fake([
            'https://api.resend.com/*' => Http::response(['id' => 'email-1'], 200),
        ]);

        SystemSetting::query()->create([
            'key' => 'notificationDeliveryConfig',
            'value' => json_encode([
                'browserEnabled' => true,
                'resendEnabled' => true,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        SystemSetting::query()->create([
            'key' => 'resendConfig',
            'value' => json_encode([
                'apiKey' => 're_test_123456',
                'fromEmail' => 'notify@example.com',
                'fromName' => 'RA-panel',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $admin = $this->createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => true]);
        $recipient = $this->createUser(['username' => 'target', 'email' => 'target@example.com']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/notifications', [
            'target_mode' => 'single',
            'user_id' => $recipient->id,
            'title' => 'Maintenance',
            'message' => 'Planned reboot window.',
            'severity' => 'warning',
            'category' => 'system',
            'send_browser' => true,
            'send_email' => true,
        ])->assertOk()
            ->assertJsonPath('recipientsCount', 1);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'title' => 'Maintenance',
            'browser_eligible' => 1,
            'email_eligible' => 1,
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'panel',
            'status' => 'sent',
            'target' => 'user:' . $recipient->id,
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'browser',
            'target' => 'user:' . $recipient->id,
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'email',
            'status' => 'sent',
            'target' => 'target@example.com',
        ]);
    }

    public function test_non_admin_users_are_rejected_from_admin_notification_endpoints(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/notifications')->assertForbidden();
        $this->putJson('/api/v1/admin/notifications/settings', [
            'resend_enabled' => true,
        ])->assertForbidden();
        $this->postJson('/api/v1/admin/notifications', [
            'target_mode' => 'all',
            'title' => 'Denied',
            'message' => 'No access',
        ])->assertForbidden();
        $this->getJson('/api/v1/admin/notifications-test/config')->assertForbidden();
    }

    public function test_admin_can_save_resend_settings_for_user_notifications_page(): void
    {
        $admin = $this->createUser(['username' => 'admin3', 'email' => 'admin3@example.com', 'is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/notifications/settings', [
            'browser_enabled' => true,
            'resend_enabled' => true,
            'sender_name' => 'RA Notifications',
            'reply_to' => 'reply@example.com',
            'resend_api_key' => 're_live_12345678',
            'resend_from_email' => 'notify@example.com',
            'resend_from_name' => 'RA-panel',
        ])->assertOk()
            ->assertJsonPath('settings.delivery.resendEnabled', true)
            ->assertJsonPath('settings.resend.fromEmail', 'notify@example.com')
            ->assertJsonPath('settings.channels.resendConfigured', true);

        $this->assertDatabaseHas('system_settings', ['key' => 'notificationDeliveryConfig']);
        $this->assertDatabaseHas('system_settings', ['key' => 'resendConfig']);
    }

    public function test_admin_notifications_bootstrap_payload_includes_logs_and_test_center_data(): void
    {
        $admin = $this->createUser(['username' => 'admin4', 'email' => 'admin4@example.com', 'is_admin' => true]);
        Sanctum::actingAs($admin);

        NotificationDeliveryLog::query()->create([
            'channel' => 'discord',
            'status' => 'failed',
            'target' => 'https://discord.com/api/webhooks/1/token',
        ]);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonStructure([
                'users',
                'recent_notifications',
                'recent_logs',
                'logs_payload' => ['logs', 'last_failed_log'],
                'settings',
                'test_center' => ['settings', 'masked_targets', 'last_failed_log'],
            ]);
    }

    public function test_notifications_test_channels_fail_cleanly_when_missing_config_and_retry_webhook_creates_follow_up_log(): void
    {
        $admin = $this->createUser(['username' => 'admin2', 'email' => 'admin2@example.com', 'is_admin' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/notifications-test/send', [
            'channel' => 'discord',
            'title' => 'Probe',
            'message' => 'No config',
        ])->assertOk()
            ->assertJsonPath('log.status', 'failed');

        $failedWebhook = NotificationDeliveryLog::query()->create([
            'channel' => 'webhook',
            'status' => 'failed',
            'target' => 'https://hooks.example.test/ra',
            'request_payload' => [
                'channel' => 'webhook',
                'title' => 'Webhook retry',
                'message' => 'Retry me',
                'webhook_url' => 'https://hooks.example.test/ra',
            ],
        ]);

        Http::fake([
            'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->postJson("/api/v1/admin/notifications/logs/{$failedWebhook->id}/retry")
            ->assertOk()
            ->assertJsonPath('log.status', 'sent')
            ->assertJsonPath('log.retriedFromId', $failedWebhook->id);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'channel' => 'webhook',
            'status' => 'sent',
            'retried_from_id' => $failedWebhook->id,
        ]);
    }
}
