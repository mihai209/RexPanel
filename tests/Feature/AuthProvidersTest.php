<?php

namespace Tests\Feature;

use App\Models\LinkedAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProvidersTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(bool $isAdmin = false): User
    {
        return User::query()->create([
            'username' => $isAdmin ? 'admin' : 'user',
            'email' => $isAdmin ? 'admin@example.com' : 'user@example.com',
            'password' => 'password123',
            'is_admin' => $isAdmin,
        ]);
    }

    public function test_public_provider_list_uses_saved_settings(): void
    {
        SystemSetting::query()->create(['key' => 'auth_standard_enabled', 'value' => 'false']);
        SystemSetting::query()->create(['key' => 'auth_google_enabled', 'value' => 'true']);
        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        $response = $this->getJson('/api/v1/auth/providers');

        $response->assertOk();
        $response->assertJsonPath('standard_enabled', false);
        $response->assertJsonPath('providers.1.key', 'google');
        $response->assertJsonPath('providers.1.icon_key', 'google');
        $response->assertJsonPath('providers.1.brand_variant', 'google');
        $response->assertJsonPath('providers.1.enabled', true);
        $response->assertJsonPath('providers.1.configured', true);
    }

    public function test_admin_can_update_provider_settings(): void
    {
        Sanctum::actingAs($this->createUser(true));

        $response = $this->putJson('/api/v1/admin/auth-providers', [
            'standard_enabled' => true,
            'providers' => [
                'google' => [
                    'enabled' => true,
                    'register_enabled' => false,
                    'client_id' => 'google-id',
                    'client_secret' => 'google-secret',
                ],
                'github' => [
                    'enabled' => true,
                    'register_enabled' => true,
                    'client_id' => 'github-id',
                    'client_secret' => 'github-secret',
                ],
                'discord' => [
                    'enabled' => false,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'reddit' => [
                    'enabled' => false,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('system_settings', ['key' => 'auth_google_enabled', 'value' => 'true']);
        $this->assertDatabaseHas('system_settings', ['key' => 'auth_google_register_enabled', 'value' => 'false']);
        $this->assertDatabaseHas('system_settings', ['key' => 'auth_github_client_id', 'value' => 'github-id']);
    }

    public function test_admin_provider_list_does_not_expose_stored_credentials(): void
    {
        $admin = $this->createUser(true);
        Sanctum::actingAs($admin);

        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        $response = $this->getJson('/api/v1/admin/auth-providers');

        $response->assertOk();
        $response->assertJsonPath('providers.google.client_id_configured', true);
        $response->assertJsonPath('providers.google.client_secret_configured', true);
        $response->assertJsonMissing(['client_id' => 'google-client']);
        $response->assertJsonMissing(['client_secret' => 'google-secret']);
    }

    public function test_updating_provider_toggles_without_new_secret_keeps_existing_credentials(): void
    {
        $admin = $this->createUser(true);
        Sanctum::actingAs($admin);

        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        $response = $this->putJson('/api/v1/admin/auth-providers', [
            'standard_enabled' => true,
            'providers' => [
                'google' => [
                    'enabled' => true,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'github' => [
                    'enabled' => false,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'discord' => [
                    'enabled' => false,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'reddit' => [
                    'enabled' => false,
                    'register_enabled' => true,
                    'client_id' => '',
                    'client_secret' => '',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('system_settings', ['key' => 'auth_google_client_id', 'value' => 'google-client']);
        $this->assertDatabaseHas('system_settings', ['key' => 'auth_google_client_secret', 'value' => 'google-secret']);
    }

    public function test_login_accepts_username_like_cpanel_api(): void
    {
        User::query()->create([
            'username' => 'mihai',
            'email' => 'mihai@example.com',
            'password' => 'password123',
            'is_admin' => true,
            'first_name' => 'Mihai',
            'last_name' => 'Admin',
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'mihai',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.username', 'mihai');
        $response->assertJsonPath('user.isAdmin', true);
        $response->assertJsonPath('user.firstName', 'Mihai');
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::query()->create([
            'username' => 'mihai',
            'email' => 'mihai@example.com',
            'password' => 'password123',
            'is_suspended' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'mihai',
            'password' => 'password123',
        ]);

        $response->assertStatus(423);
        $response->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
    }

    public function test_authenticated_suspended_user_is_blocked_from_active_routes(): void
    {
        $user = $this->createUser(false);
        $user->forceFill(['is_suspended' => true])->save();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(423);
        $response->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
    }

    public function test_non_admin_cannot_update_provider_settings(): void
    {
        Sanctum::actingAs($this->createUser(false));

        $response = $this->putJson('/api/v1/admin/auth-providers', [
            'standard_enabled' => true,
            'providers' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_authenticated_user_can_request_link_redirect_url(): void
    {
        Sanctum::actingAs($this->createUser(false));

        SystemSetting::query()->create(['key' => 'auth_google_enabled', 'value' => 'true']);
        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        $response = $this->postJson('/api/v1/account/linked-accounts/google/redirect');

        $response->assertOk();
        $response->assertJsonStructure(['redirect_url']);
    }

    public function test_user_cannot_unlink_last_linked_account(): void
    {
        $user = $this->createUser(false);
        Sanctum::actingAs($user);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '12345',
            'provider_email' => 'user@example.com',
        ]);

        $response = $this->deleteJson('/api/v1/account/linked-accounts/google');

        $response->assertStatus(422);
    }

    public function test_linked_accounts_include_linked_disabled_provider(): void
    {
        $user = $this->createUser(false);
        Sanctum::actingAs($user);

        SystemSetting::query()->create(['key' => 'auth_google_enabled', 'value' => 'false']);
        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '12345',
            'provider_email' => 'user@example.com',
        ]);

        $response = $this->getJson('/api/v1/account/linked-accounts');

        $response->assertOk();
        $response->assertJsonFragment([
            'key' => 'google',
            'linked' => true,
            'enabled' => false,
        ]);
    }
}
