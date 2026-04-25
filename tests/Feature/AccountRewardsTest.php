<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountRewardsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'username' => 'member',
            'email' => 'member@example.com',
            'password' => 'password123',
            'coins' => 0,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function setSetting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function test_authenticated_user_can_fetch_rewards_runtime_and_claim_daily_reward(): void
    {
        $user = $this->actingAsUser(['coins' => 10]);

        $this->setSetting('featureClaimRewardsEnabled', 'true');
        $this->setSetting('featureAfkRewardsEnabled', 'true');
        $this->setSetting('economyUnit', 'Tokens');
        $this->setSetting('afkRewardActivePeriod', 'day');
        $this->setSetting('afkRewardDayCoins', '120');
        $this->setSetting('claimDailyStreakBonusCoins', '5');
        $this->setSetting('claimDailyStreakMax', '30');

        $status = $this->getJson('/api/v1/account/rewards');

        $status->assertOk();
        $status->assertJsonPath('wallet.coins', 10);
        $status->assertJsonPath('wallet.economyUnit', 'Tokens');
        $status->assertJsonPath('features.claimRewardsEnabled', true);
        $status->assertJsonPath('claim.selectedPeriod', 'day');

        $claim = $this->postJson('/api/v1/account/rewards/claim', [
            'period' => 'day',
        ]);

        $claim->assertOk();
        $claim->assertJsonPath('period', 'day');
        $claim->assertJsonPath('baseRewardCoins', 120);
        $claim->assertJsonPath('streakBonusCoins', 0);
        $claim->assertJsonPath('awardedCoins', 120);
        $claim->assertJsonPath('coins', 130);
        $claim->assertJsonPath('dailyStreak', 1);

        $this->assertDatabaseHas('account_reward_states', [
            'user_id' => $user->id,
            'selected_period' => 'day',
            'daily_streak' => 1,
            'reward_accrual_disabled' => false,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'rewards.claim',
            'action' => 'Claimed day reward',
        ]);

        $cooldown = $this->postJson('/api/v1/account/rewards/claim', [
            'period' => 'day',
        ]);

        $cooldown->assertStatus(429);
        $cooldown->assertJsonPath('dailyStreak', 1);

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('coins', 130);
        $this->assertSame(130, $user->fresh()->coins);
    }

    public function test_afk_ping_awards_wallet_when_cooldown_has_elapsed(): void
    {
        $user = $this->actingAsUser(['coins' => 25]);

        $this->setSetting('featureAfkRewardsEnabled', 'true');
        $this->setSetting('economyUnit', 'Coins');
        $this->setSetting('afkTimerCoins', '4');
        $this->setSetting('afkTimerCooldownSeconds', '60');

        $status = $this->getJson('/api/v1/account/afk');
        $status->assertOk();
        $status->assertJsonPath('afkTimer.rewardCoins', 4);

        $firstPing = $this->postJson('/api/v1/account/afk/ping');
        $firstPing->assertOk();
        $firstPing->assertJsonPath('awarded', false);
        $firstPing->assertJsonPath('coins', 25);

        DB::table('account_afk_states')->where('user_id', $user->id)->update([
            'last_timer_reward_at' => now()->subSeconds(61),
            'next_payout_at' => now()->subSecond(),
        ]);

        $secondPing = $this->postJson('/api/v1/account/afk/ping');
        $secondPing->assertOk();
        $secondPing->assertJsonPath('awarded', true);
        $secondPing->assertJsonPath('awardedCoins', 4);
        $secondPing->assertJsonPath('coins', 29);

        $this->assertSame(29, $user->fresh()->coins);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'rewards.afk_payout',
            'action' => 'AFK payout awarded',
        ]);
    }

    public function test_rewards_endpoints_respect_disabled_feature_flags(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/account/rewards/claim', [
            'period' => 'day',
        ])->assertForbidden();

        $this->getJson('/api/v1/account/afk')->assertForbidden();
        $this->postJson('/api/v1/account/afk/ping')->assertForbidden();
    }
}
