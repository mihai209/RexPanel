<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ConnectorPresenceService
{
    private const KEY_PREFIX = 'connector_presence:node:';
    private const TTL_SECONDS = 180;

    public function markConnected(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): void
    {
        $this->put($nodeId, [
            'connected' => true,
            'panel_type' => $panelType,
            'connector_id' => $connectorId ?? $nodeId,
            'connected_at' => now()->toIso8601String(),
            'last_seen_at' => now()->toIso8601String(),
        ], $panelType, $connectorId);
    }

    public function touch(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): void
    {
        $current = $this->get($nodeId, $panelType, $connectorId);

        $this->put($nodeId, [
            'connected' => true,
            'panel_type' => $panelType,
            'connector_id' => $connectorId ?? $nodeId,
            'connected_at' => $current['connected_at'] ?? now()->toIso8601String(),
            'last_seen_at' => now()->toIso8601String(),
        ], $panelType, $connectorId);
    }

    public function markDisconnected(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): void
    {
        Cache::forget($this->key($nodeId, $panelType, $connectorId));
    }

    public function isConnected(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): bool
    {
        $state = $this->get($nodeId, $panelType, $connectorId);

        return (bool) ($state['connected'] ?? false);
    }

    public function state(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): ?array
    {
        return $this->get($nodeId, $panelType, $connectorId);
    }

    public function keyFor(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): string
    {
        return $this->key($nodeId, $panelType, $connectorId);
    }

    private function put(int $nodeId, array $payload, string $panelType = 'rex', ?int $connectorId = null): void
    {
        Cache::put($this->key($nodeId, $panelType, $connectorId), $payload, now()->addSeconds(self::TTL_SECONDS));
    }

    private function get(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): ?array
    {
        $value = Cache::get($this->key($nodeId, $panelType, $connectorId));

        return is_array($value) ? $value : null;
    }

    private function key(int $nodeId, string $panelType = 'rex', ?int $connectorId = null): string
    {
        $normalizedType = trim(strtolower($panelType)) ?: 'rex';
        $normalizedConnectorId = $connectorId ?? $nodeId;

        return self::KEY_PREFIX . $nodeId . ':' . $normalizedType . ':' . $normalizedConnectorId;
    }
}
