<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Server;

class ServerBuildService
{
    public function normalizePayload(array $data, ?Server $server = null): array
    {
        $limits = is_array($data['limits'] ?? null) ? $data['limits'] : [];
        $featureLimits = is_array($data['feature_limits'] ?? null) ? $data['feature_limits'] : [];
        $allocation = is_array($data['allocation'] ?? null) ? $data['allocation'] : [];

        $primaryAllocationId = $allocation['default'] ?? $data['allocation_id'] ?? $server?->allocation_id;
        $additionalAllocations = $allocation['additional'] ?? [];

        return [
            'name' => $data['name'] ?? $server?->name,
            'description' => $data['description'] ?? $server?->description,
            'external_id' => $data['external_id'] ?? $server?->external_id,
            'node_id' => (int) ($data['node_id'] ?? $server?->node_id),
            'user_id' => (int) ($data['user_id'] ?? $server?->user_id),
            'image_id' => $data['image_id'] ?? $server?->image_id,
            'allocation' => [
                'default' => $primaryAllocationId ? (int) $primaryAllocationId : null,
                'additional' => collect(is_array($additionalAllocations) ? $additionalAllocations : [])
                    ->map(fn ($value) => (int) $value)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'cpu' => (int) ($limits['cpu'] ?? $data['cpu'] ?? $server?->cpu ?? 100),
            'memory' => (int) ($limits['memory'] ?? $data['memory'] ?? $server?->memory ?? 1024),
            'disk' => (int) ($limits['disk'] ?? $data['disk'] ?? $server?->disk ?? 5120),
            'swap' => (int) ($limits['swap'] ?? $data['swap'] ?? $server?->swap ?? 0),
            'io' => (int) ($limits['io'] ?? $data['io'] ?? $server?->io ?? 500),
            'threads' => array_key_exists('threads', $limits) ? $limits['threads'] : ($data['threads'] ?? $server?->threads),
            'oom_disabled' => (bool) ($data['oom_disabled'] ?? $server?->oom_disabled ?? false),
            'database_limit' => $this->normalizeNullableInteger($featureLimits['databases'] ?? $data['database_limit'] ?? $server?->database_limit),
            'allocation_limit' => $this->normalizeNullableInteger($featureLimits['allocations'] ?? $data['allocation_limit'] ?? $server?->allocation_limit),
            'backup_limit' => $this->normalizeNullableInteger($featureLimits['backups'] ?? $data['backup_limit'] ?? $server?->backup_limit),
            'docker_image' => $data['docker_image'] ?? $server?->docker_image,
            'startup' => $data['startup'] ?? $server?->startup,
            'variables' => is_array($data['variables'] ?? null) ? $data['variables'] : ($server?->variables ?? []),
            'status' => $data['status'] ?? $server?->status ?? 'offline',
            'start_on_completion' => (bool) ($data['start_on_completion'] ?? false),
            'reinstall' => (bool) ($data['reinstall'] ?? false),
        ];
    }

    public function fillRuntimeDefaults(array $normalized, Image $image, ?Server $server = null): array
    {
        $defaultVariables = $image->imageVariables
            ->mapWithKeys(fn ($variable) => [$variable->env_variable => $variable->default_value ?? ''])
            ->all();

        return [
            ...$normalized,
            'docker_image' => $normalized['docker_image'] ?: ($server?->docker_image ?: $image->docker_image),
            'startup' => $normalized['startup'] ?: ($server?->startup ?: $image->startup),
            'variables' => array_merge($defaultVariables, $server?->variables ?? [], $normalized['variables'] ?? []),
        ];
    }

    public function persist(array $normalized, Image $image, ?Server $server = null): Server
    {
        $payload = [
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'external_id' => $normalized['external_id'],
            'node_id' => $normalized['node_id'],
            'user_id' => $normalized['user_id'],
            'image_id' => $normalized['image_id'],
            'cpu' => $normalized['cpu'],
            'memory' => $normalized['memory'],
            'disk' => $normalized['disk'],
            'swap' => $normalized['swap'],
            'io' => $normalized['io'],
            'threads' => $normalized['threads'],
            'oom_disabled' => $normalized['oom_disabled'],
            'database_limit' => $normalized['database_limit'],
            'allocation_limit' => $normalized['allocation_limit'],
            'backup_limit' => $normalized['backup_limit'],
            'docker_image' => $normalized['docker_image'] ?: $image->docker_image,
            'startup' => $normalized['startup'] ?: $image->startup,
            'variables' => $normalized['variables'],
            'status' => $normalized['status'] ?: ($server?->status ?: 'offline'),
        ];

        if ($server) {
            $server->update($payload);

            return $server;
        }

        $payload['status'] = 'installing';

        return Server::query()->create($payload);
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
