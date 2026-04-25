<?php

namespace Tests\Unit;

use App\Console\Commands\ConnectorServer;
use App\Models\Location;
use App\Models\Node;
use App\Services\ConnectorPresenceService;
use App\Services\NodeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ConnectorServer::$testingConnectionStates = [];
    }

    private function makeNode(mixed $heartbeat = null, bool $maintenance = false): Node
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
            'daemon_token' => 'token-node-1',
            'daemon_base' => '/var/lib/ra-panel',
            'memory_limit' => 16384,
            'memory_overallocate' => 0,
            'disk_limit' => 102400,
            'disk_overallocate' => 0,
            'is_public' => true,
            'maintenance_mode' => $maintenance,
            'last_heartbeat' => $heartbeat,
        ])->fresh();
    }

    public function test_health_reports_maintenance_mode(): void
    {
        $node = $this->makeNode(now()->toDateTimeString(), true);
        ConnectorServer::setTestingNodeConnectionState($node->id, true);

        $health = app(NodeHealthService::class)->summarize($node);
        $this->assertFalse($health['is_active']);
        $this->assertContains('maintenance_mode', $health['reasons']);
    }

    public function test_health_reports_missing_heartbeat(): void
    {
        $node = $this->makeNode(null, false);
        ConnectorServer::setTestingNodeConnectionState($node->id, true);

        $health = app(NodeHealthService::class)->summarize($node);

        $this->assertFalse($health['is_active']);
        $this->assertContains('heartbeat_missing', $health['reasons']);
    }

    public function test_health_reports_disconnected_connector_with_last_heartbeat_metadata(): void
    {
        $node = new Node([
            'id' => 999,
            'maintenance_mode' => false,
            'last_heartbeat' => now()->subDay(),
        ]);
        $node->id = 999;
        ConnectorServer::setTestingNodeConnectionState($node->id, false);

        $health = app(NodeHealthService::class)->summarize($node);

        $this->assertFalse($health['is_active']);
        $this->assertContains('connector_disconnected', $health['reasons']);
        $this->assertNotNull($health['last_heartbeat']);
    }

    public function test_health_uses_shared_connector_presence_state(): void
    {
        $node = $this->makeNode(now()->toDateTimeString(), false);
        app(ConnectorPresenceService::class)->markConnected($node->id, 'rex', $node->id);

        $health = app(NodeHealthService::class)->summarize($node);

        $this->assertTrue($health['is_connected']);
        $this->assertTrue($health['is_active']);
        $this->assertSame('healthy', $health['status']);
        $this->assertSame('rex', $health['panel_type']);
        $this->assertSame($node->id, $health['connector_identity']['id']);
    }
}
