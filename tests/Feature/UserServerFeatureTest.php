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
use App\Models\ServerRuntimeState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserServerFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $name = 'user', bool $isAdmin = false): User
    {
        return User::query()->create([
            'username' => $name,
            'email' => "{$name}@example.com",
            'password' => 'password123',
            'is_admin' => $isAdmin,
        ]);
    }

    private function createNode(bool $active = true, bool $maintenance = false): Node
    {
        $location = Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ]);

        $node = Node::query()->create([
            'name' => 'node-1',
            'location_id' => $location->id,
            'fqdn' => 'node.local',
            'daemon_port' => 8080,
            'daemon_sftp_port' => 2022,
            'daemon_token' => 'token-node-1',
            'daemon_base' => '/var/lib/ra-panel',
            'memory_limit' => 16384,
            'memory_overallocate' => 0,
            'disk_limit' => 102400,
            'disk_overallocate' => 0,
            'is_public' => true,
            'maintenance_mode' => $maintenance,
            'last_heartbeat' => $active ? now() : null,
        ]);

        ConnectorServer::setTestingNodeConnectionState($node->id, $active);

        return $node;
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
            'startup' => 'java -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}} --port={{SERVER_PORT}}',
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

        return $image;
    }

    private function createServer(User $owner, Node $node, Image $image, array $overrides = []): Server
    {
        $allocation = NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ]);

        $server = Server::query()->create(array_merge([
            'name' => 'srv-1',
            'uuid' => (string) Str::uuid(),
            'route_id' => Str::lower(Str::random(8)),
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
            'variables' => ['SERVER_JARFILE' => 'paper.jar'],
        ], $overrides));

        $allocation->forceFill(['server_id' => $server->id])->save();

        ServerRuntimeState::query()->create([
            'server_id' => $server->id,
            'power_state' => $server->status,
            'resource_snapshot' => [
                'cpu_percent' => 12.5,
                'memory_bytes' => 134217728,
                'disk_bytes' => 268435456,
                'network_rx_bytes' => 1024,
                'network_tx_bytes' => 2048,
                'uptime_seconds' => 90,
            ],
            'console_output' => '[INFO] Ready',
            'install_output' => '',
        ]);

        return $server->fresh(['primaryAllocation', 'runtimeState', 'node']);
    }

    public function test_owner_can_fetch_server_bootstrap_and_other_user_cannot(): void
    {
        $owner = $this->createUser('owner');
        $other = $this->createUser('other');
        $server = $this->createServer($owner, $this->createNode(), $this->createImage());

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/servers/{$server->route_id}")
            ->assertOk()
            ->assertJsonPath('server.route_id', $server->route_id)
            ->assertJsonPath('server.permissions.can_power', true);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/servers/{$server->route_id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_server_endpoints_are_denied(): void
    {
        $owner = $this->createUser('owner');
        $server = $this->createServer($owner, $this->createNode(), $this->createImage());

        $this->getJson("/api/v1/servers/{$server->route_id}")->assertUnauthorized();
        $this->postJson("/api/v1/servers/{$server->route_id}/power", ['signal' => 'start'])->assertUnauthorized();
    }

    public function test_owner_can_read_resources_and_websocket_bootstrap(): void
    {
        $owner = $this->createUser('owner');
        $server = $this->createServer($owner, $this->createNode(), $this->createImage());

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/servers/{$server->route_id}/resources")
            ->assertOk()
            ->assertJsonPath('resources.cpu_percent', 12.5);

        $this->getJson("/api/v1/servers/{$server->route_id}/console/ws-token")
            ->assertOk()
            ->assertJsonPath('server.route_id', $server->route_id);
    }

    public function test_power_action_is_blocked_when_node_is_inactive(): void
    {
        $owner = $this->createUser('owner');
        $server = $this->createServer($owner, $this->createNode(active: false), $this->createImage());

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/servers/{$server->route_id}/power", ['signal' => 'start'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'node_inactive');
    }

    public function test_installing_server_returns_install_screen_payload(): void
    {
        $owner = $this->createUser('owner');
        $server = $this->createServer($owner, $this->createNode(), $this->createImage(), [
            'status' => 'installing',
        ]);

        $server->runtimeState->update([
            'install_state' => 'installing',
            'install_output' => 'Downloading image...',
            'install_message' => 'Install queued.',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/servers/{$server->route_id}")
            ->assertOk()
            ->assertJsonPath('server.install_state.is_installing', true)
            ->assertJsonPath('server.feature_availability.install_screen', true)
            ->assertJsonPath('server.runtime.install_output', 'Downloading image...');
    }

    public function test_inactive_node_blocks_admin_server_creation(): void
    {
        $admin = $this->createUser('admin', true);
        $owner = $this->createUser('owner');
        $node = $this->createNode(active: false, maintenance: true);
        $image = $this->createImage();
        $allocation = NodeAllocation::query()->create([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/servers', [
            'name' => 'blocked-create',
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'allocation' => [
                'default' => $allocation->id,
            ],
            'limits' => [
                'cpu' => 100,
                'memory' => 1024,
                'disk' => 4096,
                'swap' => 0,
                'io' => 500,
            ],
        ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'maintenance_mode');
    }
}
