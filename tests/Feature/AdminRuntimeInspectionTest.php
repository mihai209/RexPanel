<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\Server;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRuntimeInspectionTest extends TestCase
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

    private function setSetting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function createNode(): Node
    {
        $location = Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ]);

        return Node::query()->create([
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
        ]);
    }

    private function createServer(Node $node, User $owner): Server
    {
        $package = Package::query()->create([
            'slug' => 'minecraft',
            'name' => 'Minecraft',
        ]);

        $image = Image::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
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
        ]);

        $allocation = NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ]);

        return Server::query()->create([
            'name' => 'srv-1',
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'status' => 'running',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);
    }

    public function test_runtime_inspection_endpoints_are_blocked_when_features_are_disabled(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/policy-events')->assertForbidden();
        $this->getJson('/api/v1/admin/remediation-actions')->assertForbidden();
        $this->getJson('/api/v1/admin/abuse-scores')->assertForbidden();
        $this->getJson('/api/v1/admin/service-health')->assertForbidden();
        $this->getJson('/api/v1/admin/anti-miner')->assertForbidden();
    }

    public function test_admin_can_process_telemetry_and_inspect_policy_abuse_health_and_remediation_runtime(): void
    {
        $this->actingAsAdmin();
        $owner = User::query()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);
        $node = $this->createNode();
        $server = $this->createServer($node, $owner);

        foreach ([
            'featurePolicyEngineEnabled' => 'true',
            'featureAutoRemediationEnabled' => 'true',
            'featurePlaybooksAutomationEnabled' => 'true',
            'featureStrictAuditEnabled' => 'true',
            'featureAntiMinerEnabled' => 'true',
            'featureAbuseScoreEnabled' => 'true',
            'featureServiceHealthChecksEnabled' => 'true',
            'antiMinerHighCpuPercent' => '90',
            'antiMinerHighCpuSamples' => '2',
            'antiMinerSuspendScore' => '1',
            'abuseScoreAlertThreshold' => '1',
            'abuseScoreWindowHours' => '24',
            'autoRemediationCooldownSeconds' => '60',
        ] as $key => $value) {
            $this->setSetting($key, $value);
        }

        $this->postJson('/api/v1/admin/runtime/telemetry', [
            'node_id' => $node->id,
            'status' => 'degraded',
            'response_time_ms' => 120,
            'server_samples' => [
                ['server_id' => $server->id, 'cpu_percent' => 95],
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v1/admin/runtime/telemetry', [
            'node_id' => $node->id,
            'status' => 'unhealthy',
            'response_time_ms' => 220,
            'server_samples' => [
                ['server_id' => $server->id, 'cpu_percent' => 96],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('anti_miner_samples', [
            'server_id' => $server->id,
            'cpu_percent' => 96,
            'resulting_score_delta' => 1,
        ]);
        $this->assertDatabaseHas('policy_events', [
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'policy_key' => 'anti_miner.high_cpu_detected',
        ]);
        $this->assertDatabaseHas('policy_events', [
            'subject_type' => 'user',
            'subject_id' => $owner->id,
            'policy_key' => 'abuse.threshold_hit',
        ]);
        $this->assertDatabaseHas('remediation_actions', [
            'action_type' => 'disable_reward_accrual',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('service_health_checks', [
            'node_id' => $node->id,
            'status' => 'unhealthy',
        ]);
        $this->assertDatabaseHas('abuse_score_windows', [
            'subject_type' => 'user',
            'subject_id' => $owner->id,
            'score' => 2,
        ]);

        $this->assertDatabaseHas('account_reward_states', [
            'user_id' => $owner->id,
            'reward_accrual_disabled' => true,
        ]);

        $policyEvents = $this->getJson('/api/v1/admin/policy-events');
        $policyEvents->assertOk();
        $policyEvents->assertJsonPath('data.0.policy_key', $policyEvents->json('data.0.policy_key'));

        $this->getJson('/api/v1/admin/remediation-actions')->assertOk();
        $this->getJson('/api/v1/admin/abuse-scores')->assertOk();
        $this->getJson('/api/v1/admin/service-health')->assertOk();
        $this->getJson('/api/v1/admin/anti-miner')->assertOk();

        $eventId = \App\Models\PolicyEvent::query()->where('policy_key', 'anti_miner.high_cpu_detected')->value('id');

        $this->postJson("/api/v1/admin/policy-events/{$eventId}/playbooks", [
            'playbook' => 'miner-response',
        ])->assertOk();

        $this->assertTrue($owner->fresh()->is_suspended);
        $this->assertTrue($node->fresh()->maintenance_mode);
    }
}
