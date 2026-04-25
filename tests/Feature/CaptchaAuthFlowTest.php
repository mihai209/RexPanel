<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CaptchaChallengeService;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CaptchaAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_captcha_endpoint_reflects_setting(): void
    {
        $this->getJson('/api/v1/auth/captcha')
            ->assertOk()
            ->assertJsonPath('enabled', false);

        SystemSetting::query()->create(['key' => 'captchastatus', 'value' => 'on']);

        $response = $this->getJson('/api/v1/auth/captcha');

        $response->assertOk();
        $response->assertJsonPath('enabled', true);
        $response->assertJsonStructure(['token', 'svg', 'expires_at']);
    }

    public function test_login_rejects_invalid_captcha_when_enabled(): void
    {
        SystemSetting::query()->create(['key' => 'captchastatus', 'value' => 'on']);
        User::query()->create([
            'username' => 'mihai',
            'email' => 'mihai@example.com',
            'password' => 'password123',
        ]);

        $captcha = Mockery::mock(CaptchaChallengeService::class);
        $captcha->shouldReceive('validate')->once()->andReturn(false);
        $this->app->instance(CaptchaChallengeService::class, $captcha);

        $this->postJson('/api/login', [
            'username' => 'mihai',
            'password' => 'password123',
            'captcha_token' => 'challenge-1',
            'captcha' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid captcha code.');
    }

    public function test_login_accepts_valid_captcha_when_enabled(): void
    {
        SystemSetting::query()->create(['key' => 'captchastatus', 'value' => 'on']);
        User::query()->create([
            'username' => 'mihai',
            'email' => 'mihai@example.com',
            'password' => 'password123',
        ]);

        $captcha = Mockery::mock(CaptchaChallengeService::class);
        $captcha->shouldReceive('validate')->once()->andReturn(true);
        $this->app->instance(CaptchaChallengeService::class, $captcha);

        $this->postJson('/api/login', [
            'username' => 'mihai',
            'password' => 'password123',
            'captcha_token' => 'challenge-1',
            'captcha' => 'valid',
        ])->assertOk()
            ->assertJsonPath('user.username', 'mihai');
    }

    public function test_two_factor_login_requires_valid_captcha_when_enabled(): void
    {
        SystemSetting::query()->create(['key' => 'captchastatus', 'value' => 'on']);

        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        User::query()->create([
            'username' => 'mihai',
            'email' => 'mihai@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $captcha = Mockery::mock(CaptchaChallengeService::class);
        $captcha->shouldReceive('validate')->twice()->andReturn(true, false);
        $this->app->instance(CaptchaChallengeService::class, $captcha);

        $loginResponse = $this->postJson('/api/login', [
            'username' => 'mihai',
            'password' => 'password123',
            'captcha_token' => 'login-captcha',
            'captcha' => 'valid',
        ]);

        $loginResponse->assertOk();
        $loginResponse->assertJsonPath('two_factor_required', true);
        $challengeToken = $loginResponse->json('two_factor_token');

        $this->postJson('/api/v1/auth/login/2fa', [
            'two_factor_token' => $challengeToken,
            'code' => $twoFactor->currentCode($secret),
            'captcha_token' => 'second-captcha',
            'captcha' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid captcha code.');
    }
}
