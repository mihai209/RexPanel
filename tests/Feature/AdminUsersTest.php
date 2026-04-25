<?php

namespace Tests\Feature;

use App\Models\LinkedAccount;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiQuotaRedisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function bindQuotaServiceMock(array $usageMap = []): void
    {
        $quota = Mockery::mock(AiQuotaRedisService::class);
        $quota->shouldReceive('usageMap')->andReturnUsing(function ($userIds) use ($usageMap) {
            $resolved = [];

            foreach ($userIds as $userId) {
                $userId = (int) $userId;
                if (array_key_exists($userId, $usageMap)) {
                    $resolved[$userId] = $usageMap[$userId];
                }
            }

            return $resolved;
        });
        $quota->shouldReceive('usageForUser')->andReturnUsing(fn (int $userId): int => $usageMap[$userId] ?? 0);
        $quota->shouldReceive('resetUsage')->andReturnNull();

        $this->app->instance(AiQuotaRedisService::class, $quota);
    }

    private function actingAsAdmin(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function createServerFor(User $owner, string $name = 'srv-1'): void
    {
        $location = Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ]);

        $node = Node::query()->create([
            'name' => 'node-1',
            'location_id' => $location->id,
            'fqdn' => 'node.local',
            'daemon_port' => 8080,
            'daemon_token' => 'token',
            'is_public' => true,
            'maintenance_mode' => false,
        ]);

        $allocation = NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ]);

        Server::query()->create([
            'name' => $name,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => null,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 4096,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
            'docker_image' => 'ghcr.io/example/image:latest',
            'startup' => 'echo start',
            'variables' => [],
        ]);
    }

    public function test_list_users_returns_paginated_rich_payload(): void
    {
        $this->actingAsAdmin();
        SystemSetting::query()->create(['key' => 'aiDailyQuota', 'value' => '12']);

        $user = User::query()->create([
            'username' => 'alpha',
            'email' => 'alpha@example.com',
            'password' => 'password123',
            'is_suspended' => true,
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
            'ai_daily_quota_override' => 8,
            'first_name' => 'Alpha',
            'last_name' => 'Tester',
            'avatar_provider' => 'google',
        ]);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-1',
            'provider_email' => 'alpha@example.com',
            'provider_username' => 'alpha',
        ]);

        $this->createServerFor($user);
        $this->bindQuotaServiceMock([$user->id => 3]);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonPath('data.0.username', 'alpha');
        $response->assertJsonPath('data.0.servers_count', 1);
        $response->assertJsonPath('data.0.is_suspended', true);
        $response->assertJsonPath('data.0.two_factor_enabled', true);
        $response->assertJsonPath('data.0.ai_quota_override', 8);
        $response->assertJsonPath('data.0.ai_quota_remaining', 5);
        $response->assertJsonPath('data.0.linked_accounts.0.provider', 'google');
    }

    public function test_search_filters_by_username_email_name_or_id(): void
    {
        $this->actingAsAdmin();

        $alpha = User::query()->create([
            'username' => 'alpha',
            'email' => 'alpha@example.com',
            'password' => 'password123',
            'first_name' => 'Alpha',
            'last_name' => 'Tester',
        ]);

        User::query()->create([
            'username' => 'beta',
            'email' => 'beta@example.com',
            'password' => 'password123',
            'first_name' => 'Beta',
            'last_name' => 'Member',
        ]);
        $this->bindQuotaServiceMock();

        $response = $this->getJson('/api/v1/admin/users?search=beta@example.com');
        $responseByName = $this->getJson('/api/v1/admin/users?search=Alpha%20Tester');
        $responseById = $this->getJson("/api/v1/admin/users?search={$alpha->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.username', 'beta');
        $responseByName->assertOk();
        $responseByName->assertJsonCount(1, 'data');
        $responseByName->assertJsonPath('data.0.username', 'alpha');
        $responseById->assertOk();
        $responseById->assertJsonCount(1, 'data');
        $responseById->assertJsonPath('data.0.username', 'alpha');
    }

    public function test_create_user_supports_new_fields_and_defaults(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/users', [
            'username' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'first_name' => 'New',
            'last_name' => 'User',
            'avatar_provider' => 'url',
            'avatar_url' => 'https://example.com/avatar.png',
            'custom_avatar_url' => 'https://example.com/custom.png',
            'is_admin' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.first_name', 'New');
        $response->assertJsonPath('user.is_suspended', false);

        $this->assertDatabaseHas('users', [
            'username' => 'new-user',
            'email' => 'new-user@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'avatar_provider' => 'url',
            'is_admin' => 1,
            'is_suspended' => 0,
        ]);
    }

    public function test_edit_user_updates_new_fields_and_rotates_password(): void
    {
        $this->actingAsAdmin();
        $user = User::query()->create([
            'username' => 'member',
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", [
            'username' => 'member-updated',
            'email' => 'member-updated@example.com',
            'password' => 'newpassword123',
            'first_name' => 'Member',
            'last_name' => 'Updated',
            'avatar_provider' => 'gravatar',
            'is_admin' => true,
            'is_suspended' => true,
            'coins' => 42,
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.username', 'member-updated');
        $response->assertJsonPath('user.is_suspended', true);

        $user->refresh();
        $this->assertSame('member-updated', $user->username);
        $this->assertSame('member-updated@example.com', $user->email);
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_suspended);
        $this->assertSame(42, $user->coins);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_self_delete_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->deleteJson("/api/v1/admin/users/{$admin->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_self_suspend_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->putJson("/api/v1/admin/users/{$admin->id}", [
            'username' => $admin->username,
            'email' => $admin->email,
            'is_suspended' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'You cannot suspend your own account.');
        $this->assertFalse($admin->fresh()->is_suspended);
    }

    public function test_admin_unlink_removes_target_linked_account(): void
    {
        $this->actingAsAdmin();
        $user = User::query()->create([
            'username' => 'linked',
            'email' => 'linked@example.com',
            'password' => 'password123',
        ]);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-1',
        ]);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'github-1',
        ]);

        SystemSetting::query()->create([
            'key' => "oauth_google_tokens_user_{$user->id}",
            'value' => 'encrypted-token',
        ]);

        $response = $this->postJson("/api/v1/admin/users/{$user->id}/unlink/google");

        $response->assertOk();
        $this->assertDatabaseMissing('linked_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
        $this->assertDatabaseMissing('system_settings', [
            'key' => "oauth_google_tokens_user_{$user->id}",
        ]);
    }

    public function test_ai_quota_override_and_reset_work(): void
    {
        $this->actingAsAdmin();
        $user = User::query()->create([
            'username' => 'quota-user',
            'email' => 'quota@example.com',
            'password' => 'password123',
        ]);

        $setResponse = $this->postJson("/api/v1/admin/users/{$user->id}/ai-quota", [
            'daily_quota' => 77,
        ]);

        $setResponse->assertOk();
        $this->assertSame(77, $user->fresh()->ai_daily_quota_override);
        $this->bindQuotaServiceMock();

        $resetResponse = $this->postJson("/api/v1/admin/users/{$user->id}/ai-quota/reset");

        $resetResponse->assertOk();
        $resetResponse->assertJsonPath('message', 'AI quota usage reset for today.');
    }

    public function test_disable_2fa_clears_state(): void
    {
        $this->actingAsAdmin();
        $user = User::query()->create([
            'username' => 'twofactor',
            'email' => 'twofactor@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
        ]);

        $response = $this->postJson("/api/v1/admin/users/{$user->id}/disable-2fa");

        $response->assertOk();
        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_suspending_user_revokes_existing_tokens(): void
    {
        $this->actingAsAdmin();
        $user = User::query()->create([
            'username' => 'live-user',
            'email' => 'live-user@example.com',
            'password' => 'password123',
        ]);

        $token = $user->createToken('webapp');

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", [
            'username' => $user->username,
            'email' => $user->email,
            'is_suspended' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->is_suspended);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => $token->accessToken->name,
        ]);
    }
}
