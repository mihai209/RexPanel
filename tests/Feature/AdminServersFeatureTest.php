<?php

namespace Tests\Feature;

use App\Console\Commands\ConnectorServer;
use App\Models\Image;
use App\Models\ImageVariable;
use App\Models\Location;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\Server;
use App\Models\User;
use App\Services\ServerProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminServersFeatureTest extends TestCase
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

    private function createOwner(string $suffix = 'owner'): User
    {
        return User::query()->create([
            'username' => $suffix,
            'email' => "{$suffix}@example.com",
            'password' => 'password123',
        ]);
    }

    private function createNode(string $name = 'node-1'): Node
    {
        $location = Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ]);

        $node = Node::query()->create([
            'name' => $name,
            'location_id' => $location->id,
            'fqdn' => "{$name}.local",
            'daemon_port' => 8080,
            'daemon_sftp_port' => 2022,
            'daemon_token' => 'token-' . $name,
            'daemon_base' => '/var/lib/ra-panel',
            'memory_limit' => 16384,
            'memory_overallocate' => 0,
            'disk_limit' => 102400,
            'disk_overallocate' => 0,
            'is_public' => true,
            'maintenance_mode' => false,
            'last_heartbeat' => now(),
        ]);

        ConnectorServer::setTestingNodeConnectionState($node->id, true);

        return $node;
    }

    private function createAllocation(Node $node, int $port): NodeAllocation
    {
        return NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => $port,
        ]);
    }

    private function createImage(): Image
    {
        $package = Package::query()->create([
            'slug' => 'minecraft',
            'name' => 'Minecraft',
        ]);

        $image = Image::query()->create([
            'id' => (string) Str::uuid(),
            'package_id' => $package->id,
            'name' => 'Paper',
            'description' => 'Paper image',
            'author' => 'author@example.com',
            'docker_image' => 'ghcr.io/cpanel/yolks:java_21',
            'docker_images' => ['Java 21' => 'ghcr.io/cpanel/yolks:java_21'],
            'features' => [],
            'file_denylist' => [],
            'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}} --port={{SERVER_PORT}}',
            'variables' => [],
        ]);

        ImageVariable::query()->create([
            'image_id' => $image->id,
            'name' => 'Jar File',
            'env_variable' => 'SERVER_JARFILE',
            'default_value' => 'server.jar',
            'user_viewable' => true,
            'user_editable' => true,
        ]);

        return $image->fresh('imageVariables', 'package');
    }

    private function createServer(Node $node, NodeAllocation $allocation, Image $image, User $owner, array $overrides = []): Server
    {
        $server = Server::query()->create(array_merge([
            'name' => 'srv-1',
            'uuid' => (string) Str::uuid(),
            'description' => null,
            'external_id' => null,
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 4096,
            'swap' => 0,
            'io' => 500,
            'threads' => null,
            'oom_disabled' => false,
            'database_limit' => null,
            'allocation_limit' => null,
            'backup_limit' => null,
            'status' => 'offline',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => ['SERVER_JARFILE' => 'paper.jar'],
        ], $overrides));

        $allocation->forceFill(['server_id' => $server->id])->save();

        return $server->fresh(['image.imageVariables', 'primaryAllocation', 'allocations']);
    }

    public function test_admin_server_network_and_database_routes_require_authentication(): void
    {
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $allocation = $this->createAllocation($node, 25565);
        $server = $this->createServer($node, $allocation, $image, $owner);

        $this->getJson("/api/v1/admin/servers/{$server->id}/network")->assertUnauthorized();
        $this->postJson("/api/v1/admin/servers/{$server->id}/allocations", ['allocation_id' => $allocation->id])->assertUnauthorized();
        $this->getJson("/api/v1/admin/servers/{$server->id}/databases")->assertUnauthorized();
    }

    public function test_admin_can_create_server_with_nested_limits_and_additional_allocations(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $primary = $this->createAllocation($node, 25565);
        $extra = $this->createAllocation($node, 25566);

        $response = $this->postJson('/api/v1/admin/servers', [
            'name' => 'nested-server',
            'description' => 'Nested description',
            'external_id' => 'ext-123',
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => ['SERVER_JARFILE' => 'custom.jar'],
            'limits' => [
                'cpu' => 150,
                'memory' => 2048,
                'disk' => 8192,
                'swap' => 512,
                'io' => 600,
                'threads' => '0-2',
            ],
            'feature_limits' => [
                'databases' => 2,
                'allocations' => 2,
                'backups' => 1,
            ],
            'allocation' => [
                'default' => $primary->id,
                'additional' => [$extra->id],
            ],
            'oom_disabled' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('server.feature_limits.databases', 2);
        $response->assertJsonPath('server.allocations.0.id', $primary->id);
        $response->assertJsonPath('server.allocations.1.id', $extra->id);
        $response->assertJsonPath('server.startup_preview.resolved', 'java -Xms128M -Xmx2048M -jar custom.jar --port=25565');
        $this->assertNotEmpty($response->json('server.route_id'));

        $serverId = $response->json('server.id');
        $this->assertDatabaseHas('servers', [
            'id' => $serverId,
            'description' => 'Nested description',
            'external_id' => 'ext-123',
            'threads' => '0-2',
            'oom_disabled' => 1,
            'database_limit' => 2,
            'allocation_limit' => 2,
            'backup_limit' => 1,
        ]);
        $this->assertDatabaseHas('node_allocations', ['id' => $primary->id, 'server_id' => $serverId]);
        $this->assertDatabaseHas('node_allocations', ['id' => $extra->id, 'server_id' => $serverId]);
    }

    public function test_legacy_flat_server_update_still_works(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $allocationA = $this->createAllocation($node, 25565);
        $allocationB = $this->createAllocation($node, 25566);
        $server = $this->createServer($node, $allocationA, $image, $owner);

        $response = $this->putJson("/api/v1/admin/servers/{$server->id}", [
            'name' => 'legacy-update',
            'node_id' => $node->id,
            'allocation_id' => $allocationB->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 200,
            'memory' => 4096,
            'disk' => 10240,
            'swap' => 256,
            'io' => 700,
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => ['SERVER_JARFILE' => 'legacy.jar'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('server.name', 'legacy-update');
        $response->assertJsonPath('server.allocation_id', $allocationB->id);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'name' => 'legacy-update',
            'allocation_id' => $allocationB->id,
            'cpu' => 200,
            'memory' => 4096,
        ]);
        $this->assertDatabaseHas('node_allocations', ['id' => $allocationA->id, 'server_id' => null]);
        $this->assertDatabaseHas('node_allocations', ['id' => $allocationB->id, 'server_id' => $server->id]);
    }

    public function test_allocation_limit_is_enforced_for_additional_allocations(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $primary = $this->createAllocation($node, 25565);
        $extra = $this->createAllocation($node, 25566);

        $response = $this->postJson('/api/v1/admin/servers', [
            'name' => 'limit-server',
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'allocation' => [
                'default' => $primary->id,
                'additional' => [$extra->id],
            ],
            'limits' => [
                'cpu' => 100,
                'memory' => 1024,
                'disk' => 4096,
                'swap' => 0,
                'io' => 500,
            ],
            'feature_limits' => [
                'allocations' => 0,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('allocation.additional');
        $this->assertDatabaseCount('servers', 0);
    }

    public function test_database_limit_blocks_create(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $primary = $this->createAllocation($node, 25565);
        $server = $this->createServer($node, $primary, $image, $owner, [
            'database_limit' => 0,
        ]);

        $host = \App\Models\DatabaseHost::query()->create([
            'name' => 'Main DB',
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
            'database' => 'mysql',
            'location_id' => $node->location_id,
            'max_databases' => 100,
            'type' => 'mysql',
        ]);

        $response = $this->postJson("/api/v1/admin/servers/{$server->id}/databases", [
            'database' => 'main',
            'database_host_id' => $host->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('database');
    }

    public function test_admin_can_manage_server_network_endpoints(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $primary = $this->createAllocation($node, 25565);
        $extraA = $this->createAllocation($node, 25566);
        $extraB = $this->createAllocation($node, 25567);
        $server = $this->createServer($node, $primary, $image, $owner, [
            'allocation_limit' => 3,
        ]);

        $assign = $this->postJson("/api/v1/admin/servers/{$server->id}/allocations", [
            'allocation_id' => $extraA->id,
            'notes' => 'voice',
        ]);
        $assign->assertCreated();
        $assign->assertJsonPath('allocations.1.notes', 'voice');

        $makePrimary = $this->postJson("/api/v1/admin/servers/{$server->id}/allocations/{$extraA->id}/primary");
        $makePrimary->assertOk();
        $makePrimary->assertJsonPath('allocation_id', $extraA->id);

        $assignSecond = $this->postJson("/api/v1/admin/servers/{$server->id}/allocations", [
            'allocation_id' => $extraB->id,
        ]);
        $assignSecond->assertCreated();

        $updateNotes = $this->patchJson("/api/v1/admin/servers/{$server->id}/allocations/{$primary->id}", [
            'notes' => 'fallback',
        ]);
        $updateNotes->assertOk();
        $updateNotes->assertJsonPath('allocations.1.notes', 'fallback');

        $delete = $this->deleteJson("/api/v1/admin/servers/{$server->id}/allocations/{$primary->id}");
        $delete->assertOk();
        $delete->assertJsonCount(2, 'allocations');

        $this->assertDatabaseHas('servers', ['id' => $server->id, 'allocation_id' => $extraA->id]);
        $this->assertDatabaseHas('node_allocations', ['id' => $primary->id, 'server_id' => null]);
        $this->assertDatabaseHas('node_allocations', ['id' => $extraB->id, 'server_id' => $server->id]);
    }

    public function test_provisioning_payload_includes_primary_and_additional_allocations(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $image = $this->createImage();
        $owner = $this->createOwner();
        $primary = $this->createAllocation($node, 25565);
        $extra = $this->createAllocation($node, 25566);
        $server = $this->createServer($node, $primary, $image, $owner, [
            'database_limit' => 1,
            'allocation_limit' => 2,
            'backup_limit' => 3,
            'variables' => ['SERVER_JARFILE' => 'multi.jar'],
        ]);
        $extra->forceFill(['server_id' => $server->id, 'notes' => 'query'])->save();

        $payload = app(ServerProvisioningService::class)->buildInstallPayload(
            $server->fresh(['image.imageVariables', 'primaryAllocation', 'allocations'])
        );

        $this->assertCount(2, $payload['config']['ports']);
        $this->assertSame(25565, $payload['config']['ports'][0]['host']);
        $this->assertSame(25566, $payload['config']['ports'][1]['host']);
        $this->assertSame('java -Xms128M -Xmx1024M -jar multi.jar --port=25565', $payload['config']['startup']);
        $this->assertSame([
            'databases' => 1,
            'allocations' => 2,
            'backups' => 3,
        ], $payload['config']['featureLimits']);
    }
}
