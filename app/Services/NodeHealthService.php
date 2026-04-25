<?php

namespace App\Services;

use App\Console\Commands\ConnectorServer;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Http\Exceptions\HttpResponseException;

class NodeHealthService
{
    private const PANEL_TYPE = 'rex';

    public function __construct(private ConnectorPresenceService $presence)
    {
    }

    public function summarize(Node $node): array
    {
        $connectorId = (int) ($node->id ?? 0);
        $heartbeatAgeSeconds = $node->last_heartbeat
            ? now()->diffInSeconds($node->last_heartbeat)
            : null;
        $heartbeatStaleSeconds = 90;
        $testingKey = ConnectorServer::connectionKey($node->id, self::PANEL_TYPE, $connectorId);
        $isConnected = app()->environment('testing') && array_key_exists($testingKey, ConnectorServer::$testingConnectionStates)
            ? ConnectorServer::isNodeConnected($node->id, self::PANEL_TYPE, $connectorId)
            : $this->presence->isConnected($node->id, self::PANEL_TYPE, $connectorId);
        $presenceState = $this->presence->state($node->id, self::PANEL_TYPE, $connectorId);
        $heartbeatMissing = $node->last_heartbeat === null;
        $heartbeatStale = $heartbeatMissing || $heartbeatAgeSeconds > $heartbeatStaleSeconds;

        $reasons = [];

        if ((bool) $node->maintenance_mode) {
            $reasons[] = 'maintenance_mode';
        }

        if ($heartbeatMissing) {
            $reasons[] = 'heartbeat_missing';
        } elseif ($heartbeatStale) {
            $reasons[] = 'heartbeat_stale';
        }

        if (! $isConnected) {
            $reasons[] = 'connector_disconnected';
        }

        $isActive = ! $node->maintenance_mode && ! $heartbeatStale && $isConnected;
        $status = $isActive
            ? 'healthy'
            : (($isConnected || ! $heartbeatMissing) ? 'degraded' : 'offline');

        return [
            'status' => $status,
            'is_active' => $isActive,
            'is_connected' => $isConnected,
            'panel_type' => self::PANEL_TYPE,
            'connector_identity' => [
                'id' => $connectorId,
                'panel_type' => self::PANEL_TYPE,
                'presence_key' => $this->presence->keyFor($node->id, self::PANEL_TYPE, $connectorId),
            ],
            'maintenance_mode' => (bool) $node->maintenance_mode,
            'last_heartbeat' => $node->last_heartbeat?->toIso8601String(),
            'connector_last_seen_at' => $presenceState['last_seen_at'] ?? null,
            'connector_connected_at' => $presenceState['connected_at'] ?? null,
            'heartbeat_age_seconds' => $heartbeatAgeSeconds,
            'heartbeat_stale' => $heartbeatStale,
            'last_usage_snapshot' => $node->runtimeState(),
            'reasons' => $reasons,
            'reason' => $reasons[0] ?? null,
            'reason_text' => $this->reasonText($reasons),
        ];
    }

    public function assertServerIsActive(Server $server, string $action): array
    {
        $server->loadMissing('node');

        return $this->assertNodeIsActive($server->node, $action);
    }

    public function assertNodeIsActive(Node $node, string $action): array
    {
        $health = $this->summarize($node);

        if ($health['is_active']) {
            return $health;
        }

        throw new HttpResponseException(response()->json([
            'message' => $action . ' is blocked because the node is inactive.',
            'error' => 'Node inactive',
            'code' => 'node_inactive',
            'reason' => $health['reason'],
            'reasons' => $health['reasons'],
            'node_health' => $health,
        ], 409));
    }

    public function reasonText(array $reasons): string
    {
        if ($reasons === []) {
            return 'Connector healthy.';
        }

        return collect($reasons)
            ->map(fn (string $reason) => match ($reason) {
                'maintenance_mode' => 'Maintenance mode is enabled.',
                'heartbeat_missing' => 'No connector heartbeat has been received.',
                'heartbeat_stale' => 'The last connector heartbeat is stale.',
                'connector_disconnected' => 'The connector is disconnected.',
                default => str_replace('_', ' ', $reason),
            })
            ->implode(' ');
    }
}
