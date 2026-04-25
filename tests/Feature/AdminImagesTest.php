<?php

namespace Tests\Feature;

use App\Console\Commands\ConnectorServer;
use App\Models\Image;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\Package;
use App\Models\Server;
use App\Models\User;
use App\Services\ConnectorQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminImagesTest extends TestCase
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

    private function createPackage(array $attributes = []): Package
    {
        return Package::query()->create(array_merge([
            'slug' => 'minecraft',
            'name' => 'Minecraft',
        ], $attributes));
    }

    private function createImage(Package $package, array $attributes = []): Image
    {
        return Image::query()->create(array_merge([
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
        ], $attributes));
    }

    private function createNode(): Node
    {
        $locationId = \App\Models\Location::query()->create([
            'name' => 'Bucharest',
            'description' => 'Primary',
        ])->id;

        $node = Node::query()->create([
            'name' => 'node-1',
            'location_id' => $locationId,
            'fqdn' => 'node.local',
            'daemon_port' => 8080,
            'daemon_token' => 'token',
            'is_public' => true,
            'maintenance_mode' => false,
            'last_heartbeat' => now(),
        ]);

        ConnectorServer::setTestingNodeConnectionState($node->id, true);

        return $node;
    }

    private function createAllocation(Node $node, array $attributes = []): NodeAllocation
    {
        return NodeAllocation::query()->create(array_merge([
            'node_id' => $node->id,
            'ip' => '127.0.0.1',
            'port' => 25565,
        ], $attributes));
    }

    public function test_non_admin_cannot_access_admin_image_routes(): void
    {
        $user = User::query()->create([
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/admin/images/import', []);

        $response->assertStatus(403);
    }

    public function test_package_detail_includes_image_and_server_counts(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $node = $this->createNode();
        $owner = User::query()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        Server::query()->create([
            'name' => 'srv-1',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);

        $response = $this->getJson("/api/v1/admin/packages/{$package->id}");

        $response->assertOk();
        $response->assertJsonPath('images_count', 1);
        $response->assertJsonPath('servers_count', 1);
        $response->assertJsonPath('images.0.servers_count', 1);
    }

    public function test_import_endpoint_accepts_single_object_and_array_payloads_and_creates_variables(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();

        $singlePayload = json_encode([
            'meta' => ['version' => 'PTDL_v2'],
            'name' => 'Paper',
            'docker_images' => ['Java 21' => 'ghcr.io/cpanel/yolks:java_21'],
            'startup' => 'java -jar server.jar',
            'config' => ['files' => [], 'startup' => [], 'logs' => [], 'stop' => 'stop'],
            'scripts' => ['installation' => ['script' => 'echo install', 'entrypoint' => 'bash', 'container' => 'alpine']],
            'variables' => [[
                'name' => 'Jar File',
                'description' => 'Jar file name',
                'env_variable' => 'SERVER_JARFILE',
                'default_value' => 'server.jar',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string',
                'field_type' => 'text',
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postJson('/api/v1/admin/images/import', [
            'package_id' => $package->id,
            'json_payload' => $singlePayload,
            'is_public' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('summary.created', 1);
        $image = Image::query()->where('package_id', $package->id)->where('name', 'Paper')->firstOrFail();
        $this->assertDatabaseHas('image_variables', ['image_id' => $image->id, 'env_variable' => 'SERVER_JARFILE']);

        $arrayPayload = json_encode([[
            'meta' => ['version' => 'PTDL_v2'],
            'name' => 'Paper',
            'docker_images' => ['Java 21' => 'ghcr.io/cpanel/yolks:java_21'],
            'startup' => 'java -jar updated.jar',
            'config' => ['files' => [], 'startup' => [], 'logs' => [], 'stop' => 'stop'],
            'scripts' => ['installation' => ['script' => 'echo update', 'entrypoint' => 'bash', 'container' => 'alpine']],
            'variables' => [[
                'name' => 'Jar File',
                'description' => 'Jar file name',
                'env_variable' => 'SERVER_JARFILE',
                'default_value' => 'updated.jar',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string',
                'field_type' => 'text',
            ]],
        ]], JSON_THROW_ON_ERROR);

        $response = $this->postJson('/api/v1/admin/images/import', [
            'package_id' => $package->id,
            'json_payload' => $arrayPayload,
            'is_public' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('summary.updated', 1);
        $this->assertDatabaseHas('images', ['package_id' => $package->id, 'name' => 'Paper', 'is_public' => 0]);
        $this->assertDatabaseHas('image_variables', ['image_id' => $image->id, 'env_variable' => 'SERVER_JARFILE', 'default_value' => 'updated.jar']);
        $this->assertDatabaseCount('image_variables', 1);
    }

    public function test_import_endpoint_rejects_missing_docker_images(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage(['slug' => 'database', 'name' => 'Database']);

        $response = $this->postJson('/api/v1/admin/images/import', [
            'package_id' => $package->id,
            'json_payload' => json_encode([
                'meta' => ['version' => 'PTDL_v2'],
                'name' => 'Broken Egg',
                'startup' => 'echo broken',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response->assertOk();
        $response->assertJsonPath('summary.failed', 1);
    }

    public function test_image_export_returns_ptdl_compatible_json(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $image->imageVariables()->create([
            'name' => 'Jar File',
            'env_variable' => 'SERVER_JARFILE',
            'default_value' => 'server.jar',
            'user_viewable' => true,
            'user_editable' => true,
            'field_type' => 'text',
            'sort_order' => 0,
        ]);

        $response = $this->get("/api/v1/admin/images/{$image->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('PTDL_v2', $decoded['meta']['version']);
        $this->assertSame('Paper', $decoded['name']);
        $this->assertSame('SERVER_JARFILE', $decoded['variables'][0]['env_variable']);
    }

    public function test_replace_from_import_upserts_variables_without_duplicates(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $image->imageVariables()->create([
            'name' => 'Old Variable',
            'env_variable' => 'SERVER_JARFILE',
            'default_value' => 'server.jar',
            'user_viewable' => true,
            'user_editable' => true,
            'field_type' => 'text',
            'sort_order' => 0,
        ]);

        $payload = json_encode([
            'meta' => ['version' => 'PTDL_v2'],
            'name' => 'Paper',
            'description' => 'Updated description',
            'docker_images' => ['Java 21' => 'ghcr.io/cpanel/yolks:java_21'],
            'startup' => 'java -jar updated.jar',
            'config' => ['files' => [], 'startup' => [], 'logs' => [], 'stop' => 'stop'],
            'scripts' => ['installation' => ['script' => 'echo update', 'entrypoint' => 'bash', 'container' => 'alpine']],
            'variables' => [[
                'name' => 'Jar File',
                'description' => 'Updated',
                'env_variable' => 'SERVER_JARFILE',
                'default_value' => 'updated.jar',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string',
                'field_type' => 'text',
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = $this->putJson("/api/v1/admin/images/{$image->id}/import", [
            'json_payload' => $payload,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('images', ['id' => $image->id, 'description' => 'Updated description']);
        $this->assertDatabaseHas('image_variables', ['image_id' => $image->id, 'env_variable' => 'SERVER_JARFILE', 'default_value' => 'updated.jar']);
        $this->assertDatabaseCount('image_variables', 1);
    }

    public function test_image_delete_fails_when_servers_reference_it(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $node = $this->createNode();
        $owner = User::query()->create([
            'username' => 'owner2',
            'email' => 'owner2@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        Server::query()->create([
            'name' => 'srv-1',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);

        $response = $this->deleteJson("/api/v1/admin/images/{$image->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('images', ['id' => $image->id]);
    }

    public function test_package_delete_fails_when_images_exist(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $this->createImage($package);

        $response = $this->deleteJson("/api/v1/admin/packages/{$package->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseCount('images', 0);
    }

    public function test_package_delete_fails_when_any_image_has_allocated_servers(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $node = $this->createNode();
        $owner = User::query()->create([
            'username' => 'owner-delete-guard',
            'email' => 'owner-delete-guard@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        Server::query()->create([
            'name' => 'srv-delete-guard',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'node_id' => $node->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [],
        ]);

        $response = $this->deleteJson("/api/v1/admin/packages/{$package->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('packages', ['id' => $package->id]);
        $this->assertDatabaseHas('images', ['id' => $image->id]);
    }

    public function test_server_creation_requires_valid_image_id(): void
    {
        $this->actingAsAdmin();
        $node = $this->createNode();
        $allocation = $this->createAllocation($node);
        $owner = User::query()->create([
            'username' => 'owner3',
            'email' => 'owner3@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/admin/servers', [
            'name' => 'srv-2',
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'status' => 'offline',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image_id']);
    }

    public function test_server_creation_reserves_allocation_and_dispatches_install_to_connector(): void
    {
        $this->actingAsAdmin();
        $package = $this->createPackage();
        $image = $this->createImage($package);
        $node = $this->createNode();
        $allocation = $this->createAllocation($node);
        $owner = User::query()->create([
            'username' => 'owner4',
            'email' => 'owner4@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $queue = Mockery::mock(ConnectorQueueService::class);
        $queue->shouldReceive('publish')
            ->once()
            ->withArgs(fn (array $payload): bool => ($payload['panel_type'] ?? null) === 'rex'
                && ($payload['connector_id'] ?? null) === ($payload['node_id'] ?? null)
                && ($payload['payload']['type'] ?? null) === 'install_server')
            ->andReturn(true);
        $this->app->instance(ConnectorQueueService::class, $queue);

        $response = $this->postJson('/api/v1/admin/servers', [
            'name' => 'srv-install',
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'user_id' => $owner->id,
            'image_id' => $image->id,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'swap' => 0,
            'io' => 500,
            'docker_image' => $image->docker_image,
            'startup' => $image->startup,
            'variables' => [
                'SERVER_JARFILE' => 'custom.jar',
            ],
            'start_on_completion' => true,
        ]);

        $response->assertCreated();
        $serverId = $response->json('server.id');
        $this->assertDatabaseHas('servers', ['id' => $serverId, 'allocation_id' => $allocation->id, 'status' => 'installing']);
        $this->assertDatabaseHas('node_allocations', ['id' => $allocation->id, 'server_id' => $serverId]);
    }

}
