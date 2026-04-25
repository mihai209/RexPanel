<?php

namespace Tests\Feature;

use App\Console\Commands\ConnectorServer;
use App\Models\DatabaseHost;
use App\Models\Image;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\PanelIncident;
use App\Models\PolicyEvent;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminParityFeatureTest extends TestCase
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

    private function createNode(Location $location, bool $active = true): Node
    {
        $node = Node::query()->create([
            'name' => 'node-' . $location->id,
            'location_id' => $location->id,
            'fqdn' => 'node-' . $location->id . '.local',
            'daemon_port' => 8080,
            'daemon_sftp_port' => 2022,
            'daemon_token' => 'token',
            'daemon_base' => '/var/lib/ra-panel',
            'memory_limit' => 8192,
            'memory_overallocate' => 0,
            'disk_limit' => 20480,
            'disk_overallocate' => 0,
            'is_public' => true,
            'maintenance_mode' => false,
            'last_heartbeat' => $active ? now() : null,
        ]);

        ConnectorServer::setTestingNodeConnectionState($node->id, $active);

        return $node;
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
            'disk' => 4096,
            'swap' => 0,
            'io' => 500,
            'database_limit' => 2,
            'status' => 'running',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);
    }

    public function test_locations_support_cpanel_fields_counts_and_delete_guards(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/v1/admin/locations', [
            'short_name' => 'RO-Bucharest',
            'description' => 'Primary',
            'image_url' => 'https://example.com/ro.png',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('location.short_name', 'RO-Bucharest');
        $create->assertJsonPath('location.shortName', 'RO-Bucharest');
        $create->assertJsonPath('location.image_url', 'https://example.com/ro.png');

        $location = Location::query()->firstOrFail();
        $this->createNode($location);

        $host = DatabaseHost::query()->create([
            'name' => 'Main DB',
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
            'database' => 'mysql',
            'location_id' => $location->id,
            'max_databases' => 0,
            'type' => 'mysql',
        ]);

        $index = $this->getJson('/api/v1/admin/locations');
        $index->assertOk();
        $index->assertJsonPath('0.database_hosts_count', 1);
        $index->assertJsonPath('0.connectors_count', 1);

        $show = $this->getJson("/api/v1/admin/locations/{$location->id}");
        $show->assertOk();
        $show->assertJsonPath('assets.database_hosts.0.id', $host->id);

        $delete = $this->deleteJson("/api/v1/admin/locations/{$location->id}");
        $delete->assertStatus(400);
    }

    public function test_server_database_creation_is_location_aware(): void
    {
        $this->actingAsAdmin();

        $owner = User::query()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $locationA = Location::query()->create(['name' => 'RO-Bucharest']);
        $locationB = Location::query()->create(['name' => 'DE-Frankfurt']);
        $node = $this->createNode($locationA);
        $server = $this->createServer($node, $owner);

        $wrongLocationHost = DatabaseHost::query()->create([
            'name' => 'Wrong Host',
            'host' => '127.0.0.2',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
            'database' => 'mysql',
            'location_id' => $locationB->id,
            'max_databases' => 0,
            'type' => 'mysql',
        ]);

        $wrongLocation = $this->postJson("/api/v1/admin/servers/{$server->id}/databases", [
            'database' => 'main',
            'database_host_id' => $wrongLocationHost->id,
        ]);
        $wrongLocation->assertStatus(422);
        $wrongLocation->assertJsonValidationErrors('database_host_id');

        $noHost = $this->postJson("/api/v1/admin/servers/{$server->id}/databases", [
            'database' => 'main',
        ]);
        $noHost->assertStatus(422);
        $noHost->assertJsonValidationErrors('database_host_id');

        $locationHost = DatabaseHost::query()->create([
            'name' => 'Primary Host',
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
            'database' => 'mysql',
            'location_id' => $locationA->id,
            'max_databases' => 1,
            'type' => 'mysql',
        ]);

        \App\Models\Database::query()->create([
            'server_id' => $server->id,
            'database_host_id' => $locationHost->id,
            'database' => 'occupied',
            'username' => 'occupied',
            'password' => 'secret',
            'remote_id' => '%',
        ]);

        $exhausted = $this->postJson("/api/v1/admin/servers/{$server->id}/databases", [
            'database' => 'main',
            'database_host_id' => $locationHost->id,
        ]);
        $exhausted->assertStatus(422);
        $exhausted->assertJsonValidationErrors('database_host_id');
    }

    public function test_incident_center_connector_lab_and_service_health_endpoints_work(): void
    {
        $this->actingAsAdmin();

        $location = Location::query()->create(['name' => 'RO-Bucharest']);
        $offlineNode = $this->createNode($location, false);
        $onlineNode = $this->createNode(Location::query()->create(['name' => 'RO-Iasi']), true);
        $onlineNode->update([
            'connector_diagnostics' => [
                'checks' => [
                    'docker_access' => ['status' => 'passed'],
                    'dns' => ['status' => 'failed', 'message' => 'resolver timeout'],
                ],
            ],
            'diagnostics_updated_at' => now(),
        ]);

        $policyEvent = PolicyEvent::query()->create([
            'subject_type' => 'node',
            'subject_id' => $offlineNode->id,
            'policy_key' => 'health.failed',
            'severity' => 'warning',
            'score_delta' => 0,
            'reason' => 'Node unhealthy',
            'title' => 'Node unhealthy',
            'status' => 'open',
            'created_at' => now(),
        ]);

        PanelIncident::query()->create([
            'title' => 'Extension incident',
            'message' => 'Read only mirror',
            'severity' => 'warning',
            'status' => 'open',
        ]);

        $this->getJson('/api/v1/admin/incidents')
            ->assertOk()
            ->assertJsonPath('runtime.data.0.id', $policyEvent->id)
            ->assertJsonPath('extension_incidents.0.read_only', true);

        $this->postJson("/api/v1/admin/incidents/{$policyEvent->id}/resolve")
            ->assertOk()
            ->assertJsonPath('incident.status', 'resolved');

        $this->getJson('/api/v1/admin/connector-lab')
            ->assertOk()
            ->assertJsonPath('connectors.1.compatibility_checks.0.key', 'docker_access');

        $this->postJson("/api/v1/admin/nodes/{$offlineNode->id}/diagnostics/run")
            ->assertStatus(409);

        $this->postJson('/api/v1/admin/service-health-checks/run')
            ->assertOk()
            ->assertJsonCount(2, 'checks');

        $this->getJson('/api/v1/admin/service-health-checks/latest')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
