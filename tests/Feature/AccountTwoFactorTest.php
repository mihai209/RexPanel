<?php

namespace Tests\Feature;

use App\Models\LinkedAccount;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AccountTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_setup_enable_and_disable_two_factor(): void
    {
        $user = User::query()->create([
            'username' => 'member',
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        Sanctum::actingAs($user);

        $setupResponse = $this->getJson('/api/v1/account/2fa/setup');
        $setupResponse->assertOk();
        $secret = $setupResponse->json('secret');

        $enableResponse = $this->postJson('/api/v1/account/2fa/enable', [
            'code' => app(TwoFactorService::class)->currentCode($secret),
        ]);

        $enableResponse->assertOk();
        $enableResponse->assertJsonPath('user.two_factor_enabled', true);
        $this->assertTrue($user->fresh()->two_factor_enabled);
        $this->assertNotNull($user->fresh()->two_factor_secret);

        $disableResponse = $this->postJson('/api/v1/account/2fa/disable', [
            'password' => 'password123',
        ]);

        $disableResponse->assertOk();
        $disableResponse->assertJsonPath('user.two_factor_enabled', false);
        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_login_requires_two_factor_completion_when_enabled(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();

        $user = User::query()->create([
            'username' => 'secure-user',
            'email' => 'secure@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'username' => 'secure-user',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $loginResponse->assertJsonPath('two_factor_required', true);
        $this->assertNull($loginResponse->json('token'));

        $verifyResponse = $this->postJson('/api/v1/auth/login/2fa', [
            'two_factor_token' => $loginResponse->json('two_factor_token'),
            'code' => app(TwoFactorService::class)->currentCode($secret),
        ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJsonPath('user.username', $user->username);
        $verifyResponse->assertJsonPath('user.two_factor_enabled', true);
        $this->assertNotNull($verifyResponse->json('token'));
    }

    public function test_oauth_callback_redirects_to_two_factor_challenge_when_enabled(): void
    {
        SystemSetting::query()->create(['key' => 'auth_google_enabled', 'value' => 'true']);
        SystemSetting::query()->create(['key' => 'auth_google_client_id', 'value' => 'google-client']);
        SystemSetting::query()->create(['key' => 'auth_google_client_secret', 'value' => 'google-secret']);

        $user = User::query()->create([
            'username' => 'oauth-user',
            'email' => 'oauth@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
        ]);

        LinkedAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
            'provider_email' => 'oauth@example.com',
            'provider_username' => 'oauth-user',
        ]);

        $oauthUser = Mockery::mock();
        $oauthUser->shouldReceive('getId')->andReturn('google-123');
        $oauthUser->shouldReceive('getEmail')->andReturn('oauth@example.com');
        $oauthUser->shouldReceive('getNickname')->andReturn('oauth-user');
        $oauthUser->shouldReceive('getName')->andReturn('OAuth User');
        $oauthUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $driver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($oauthUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('oauth_status=2fa_required', $location);
        $this->assertStringContainsString('two_factor_token=', $location);
    }
}
