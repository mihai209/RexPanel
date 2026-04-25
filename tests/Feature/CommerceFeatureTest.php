<?php

namespace Tests\Feature;

use App\Models\BillingEvent;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::query()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_admin' => true,
            'coins' => 1000,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'username' => 'member',
            'email' => 'member@example.com',
            'password' => 'password123',
            'coins' => 1000,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function setSetting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function test_admin_can_manage_catalogs_and_forecasting_reads_billing_events(): void
    {
        $admin = $this->actingAsAdmin();

        $plan = $this->postJson('/api/v1/admin/revenue-plans', [
            'name' => 'Starter',
            'priceCoins' => 200,
            'periodDays' => 30,
            'maxServers' => 2,
            'maxCpuPercent' => 100,
            'maxMemoryMb' => 2048,
            'maxDiskMb' => 4096,
        ]);
        $plan->assertCreated()->assertJsonPath('plan.name', 'Starter');

        $deal = $this->postJson('/api/v1/admin/store/deals', [
            'name' => 'Disk Pack',
            'priceCoins' => 50,
            'stockTotal' => 5,
            'resources' => ['diskMb' => 2048],
        ]);
        $deal->assertCreated()->assertJsonPath('deal.name', 'Disk Pack');
        $deal->assertJsonPath('deal.remainingStock', 5);

        $code = $this->postJson('/api/v1/admin/store/redeem-codes', [
            'code' => 'WELCOME100',
            'name' => 'Welcome Pack',
            'rewards' => ['coins' => 100],
            'maxUses' => 2,
        ]);
        $code->assertCreated()->assertJsonPath('code.code', 'WELCOME100');
        $code->assertJsonPath('code.name', 'Welcome Pack');

        BillingEvent::query()->create([
            'user_id' => $admin->id,
            'event_type' => 'deal_purchase',
            'source_type' => 'store_deal',
            'source_id' => 'disk-pack',
            'coins_delta' => -50,
            'wallet_before' => 1000,
            'wallet_after' => 950,
            'resource_delta' => ['disk' => 2048],
            'details' => ['dealName' => 'Disk Pack'],
            'created_at' => now(),
        ]);

        $forecast = $this->getJson('/api/v1/admin/forecasting');
        $forecast->assertOk();
        $forecast->assertJsonPath('summary.totalRevenueCoins', 50);
        $forecast->assertJsonPath('summary.eventCount', 1);

        $settings = $this->getJson('/api/v1/admin/settings');
        $settings->assertOk();
        $settings->assertJsonPath('sections.commerce.fields.featureBillingInvoicesEnabled.state', 'active');
        $settings->assertJsonPath('sections.commerce.fields.featureAdminApiRatePlansEnabled.state', 'active');
    }

    public function test_user_can_subscribe_purchase_and_redeem_with_wallet_updates_and_audit_events(): void
    {
        $user = $this->actingAsUser(['coins' => 1000]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'revenuePlanCatalog'],
            ['value' => json_encode([[
                'id' => 'starter',
                'name' => 'Starter',
                'description' => 'Starter plan',
                'enabled' => true,
                'featured' => true,
                'priceCoins' => 300,
                'periodDays' => 30,
                'maxServers' => 3,
                'maxCpuPercent' => 150,
                'maxMemoryMb' => 2048,
                'maxDiskMb' => 4096,
            ]])]
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'storeDealsCatalog'],
            ['value' => json_encode([[
                'id' => 'disk-pack',
                'name' => 'Disk Pack',
                'description' => 'More disk',
                'enabled' => true,
                'featured' => true,
                'priceCoins' => 120,
                'stockTotal' => 3,
                'stockSold' => 0,
                'resources' => ['diskMb' => 2048],
            ]])]
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'storeRedeemCodesCatalog'],
            ['value' => json_encode([[
                'id' => 'welcome',
                'code' => 'WELCOME100',
                'name' => 'Welcome Pack',
                'description' => 'Welcome',
                'enabled' => true,
                'rewards' => ['coins' => 100, 'ramMb' => 512],
                'maxUses' => 3,
                'perUserLimit' => 1,
                'expiresAtMs' => now()->addDay()->valueOf(),
                'usesCount' => 0,
            ]])]
        );

        $overview = $this->getJson('/api/v1/store');
        $overview->assertOk();
        $overview->assertJsonPath('wallet.coins', 1000);

        $subscribe = $this->postJson('/api/v1/store/revenue/subscribe', [
            'planId' => 'starter',
        ]);
        $subscribe->assertOk();
        $subscribe->assertJsonPath('wallet.coins', 700);
        $subscribe->assertJsonPath('profile.planId', 'starter');

        $purchase = $this->postJson('/api/v1/store/deals/purchase', [
            'dealId' => 'disk-pack',
        ]);
        $purchase->assertOk();
        $purchase->assertJsonPath('wallet.coins', 580);
        $purchase->assertJsonPath('inventory.resources.diskMb', 2048);
        $purchase->assertJsonPath('deal.stockSold', 1);
        $purchase->assertJsonPath('deal.remainingStock', 2);

        $redeem = $this->postJson('/api/v1/store/redeem', [
            'code' => 'welcome100',
        ]);
        $redeem->assertOk();
        $redeem->assertJsonPath('wallet.coins', 680);
        $redeem->assertJsonPath('inventory.resources.ramMb', 512);
        $redeem->assertJsonPath('code.usesCount', 1);
        $redeem->assertJsonPath('code.usageByUser.' . $user->id, 1);

        $forecast = $this->getJson('/api/v1/store/forecast');
        $forecast->assertOk();
        $forecast->assertJsonPath('wallet.coins', 680);
        $forecast->assertJsonPath('effectiveLimits.cpuPercent', 150);
        $forecast->assertJsonPath('activePlan.maxServers', 3);

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('coins', 680);

        $this->assertSame(680, $user->fresh()->coins);
        $this->assertDatabaseCount('billing_events', 3);
        $this->assertDatabaseHas('billing_events', [
            'user_id' => $user->id,
            'event_type' => 'revenue_subscribe',
            'coins_delta' => -300,
        ]);
        $this->assertDatabaseHas('billing_events', [
            'user_id' => $user->id,
            'event_type' => 'deal_purchase',
            'coins_delta' => -120,
        ]);
        $this->assertDatabaseHas('billing_events', [
            'user_id' => $user->id,
            'event_type' => 'redeem_code',
            'coins_delta' => 100,
        ]);
    }

    public function test_disabled_feature_flags_block_user_commerce_actions(): void
    {
        $this->actingAsUser();
        $this->setSetting('featureUserStoreEnabled', 'false');

        $this->getJson('/api/v1/store')->assertForbidden();
        $this->postJson('/api/v1/store/revenue/subscribe', ['planId' => 'starter'])->assertForbidden();
    }
}
