<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Location;
use App\Models\Mount;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\Server;
use App\Models\ServerMount;
use App\Models\User;
use App\Services\ServerProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MountsFeatureTest extends TestCase
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

    private function createNode(string $name = 'node-1'): Node
    {
        $location = Location::query()->create([
            'name' => 'Bucharest-' . $name,
            'description' => 'Primary',
        ]);

        return Node::query()->create([
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
        ]);
    }

    private function createAllocation(Node $node, int $port = 25565): NodeAllocation
    {
        return NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => $port,
        ]);
    }

    private function createPackage(): Package
    {
        return Package::query()->create([
            'slug' => 'minecraft',
            'name' => 'Minecraft',
        ]);
    }

    private function createImage(Package $package): Image
    {
        return Image::query()->create([
            'id' => (string) Str::uuid(),
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
    }

    private function createServer(Node $node, NodeAllocation $allocation, Image $image, User $owner): Server
    {
        $server = Server::query()->create([
            'name' => 'srv-1',
            'uuid' => (string) Str::uuid(),
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 4096,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);

        $allocation->forceFill(['server_id' => $server->id])->save();

        return $server;
    }

    public function test_admin_can_create_list_and_delete_mounts(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();

        $createResponse = $this->postJson('/api/v1/admin/mounts', [
            'name' => 'shared-assets',
            'description' => 'Shared assets',
            'source_path' => '/srv/shared/assets',
            'target_path' => 'assets',
            'node_id' => $node->id,
            'read_only' => true,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('mounts.0.name', 'shared-assets');
        $createResponse->assertJsonPath('mounts.0.targetPath', '/assets');
        $this->assertDatabaseHas('mounts', ['name' => 'shared-assets', 'node_id' => $node->id]);

        $listResponse = $this->getJson('/api/v1/admin/mounts');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('mounts.0.nodeName', $node->name);

        $mountId = $createResponse->json('mount.id');
        $this->deleteJson("/api/v1/admin/mounts/{$mountId}")
            ->assertOk()
            ->assertJsonPath('mounts', []);
    }

    public function test_server_mount_attach_and_detach_flow_filters_by_node(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode('node-a');
        $otherNode = $this->createNode('node-b');
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $allocation = $this->createAllocation($node);
        $owner = User::query()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);
        $server = $this->createServer($node, $allocation, $image, $owner);

        $nodeMount = Mount::query()->create([
            'name' => 'node-specific',
            'source_path' => '/srv/node-a/shared',
            'target_path' => '/home/container/node-shared',
            'read_only' => false,
            'node_id' => $node->id,
        ]);

        Mount::query()->create([
            'name' => 'other-node',
            'source_path' => '/srv/node-b/shared',
            'target_path' => '/home/container/other-shared',
            'read_only' => false,
            'node_id' => $otherNode->id,
        ]);

        $availableResponse = $this->getJson("/api/v1/admin/servers/{$server->id}/mounts");
        $availableResponse->assertOk();
        $availableResponse->assertJsonCount(1, 'availableMounts');
        $availableResponse->assertJsonPath('availableMounts.0.name', 'node-specific');

        $attachResponse = $this->postJson("/api/v1/admin/servers/{$server->id}/mounts", [
            'mount_id' => $nodeMount->id,
            'read_only' => true,
        ]);
        $attachResponse->assertOk();
        $attachResponse->assertJsonPath('assignedMounts.0.name', 'node-specific');
        $attachResponse->assertJsonPath('assignedMounts.0.readOnly', true);
        $this->assertDatabaseHas('server_mounts', ['server_id' => $server->id, 'mount_id' => $nodeMount->id, 'read_only' => 1]);

        $this->deleteJson("/api/v1/admin/servers/{$server->id}/mounts/{$nodeMount->id}")
            ->assertOk()
            ->assertJsonPath('assignedMounts', []);
    }

    public function test_provisioning_payload_includes_attached_mounts(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $allocation = $this->createAllocation($node);
        $owner = User::query()->create([
            'username' => 'owner2',
            'email' => 'owner2@example.com',
            'password' => 'password123',
        ]);
        $server = $this->createServer($node, $allocation, $image, $owner);

        $mount = Mount::query()->create([
            'name' => 'shared-config',
            'source_path' => '/srv/shared/config',
            'target_path' => '/home/container/config',
            'read_only' => false,
            'node_id' => $node->id,
        ]);

        ServerMount::query()->create([
            'server_id' => $server->id,
            'mount_id' => $mount->id,
            'read_only' => true,
        ]);

        $payload = app(ServerProvisioningService::class)->buildInstallPayload($server->fresh(['image.imageVariables', 'allocation']));

        $this->assertSame([
            [
                'source' => '/srv/shared/config',
                'target' => '/home/container/config',
                'readOnly' => true,
            ],
        ], $payload['config']['mounts']);
    }
}
