<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCaptchaTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(bool $admin = false): User
    {
        return User::query()->create([
            'username' => $admin ? 'admin' : 'user',
            'email' => $admin ? 'admin@example.com' : 'user@example.com',
            'password' => 'password123',
            'is_admin' => $admin,
        ]);
    }

    public function test_admin_can_get_and_update_captcha_settings(): void
    {
        Sanctum::actingAs($this->createUser(true));

        $this->getJson('/api/v1/admin/captcha')
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->putJson('/api/v1/admin/captcha', [
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('settings.enabled', true);

        $this->assertDatabaseHas('system_settings', ['key' => 'captchastatus', 'value' => 'on']);
    }

    public function test_non_admin_cannot_manage_captcha_settings(): void
    {
        Sanctum::actingAs($this->createUser(false));

        $this->getJson('/api/v1/admin/captcha')->assertForbidden();
        $this->putJson('/api/v1/admin/captcha', ['enabled' => true])->assertForbidden();
    }
}
