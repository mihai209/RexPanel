<?php

namespace App\Services;

use App\Models\Mount;
use App\Models\Server;
use App\Models\ServerMount;
use Illuminate\Validation\ValidationException;

class ServerMountService
{
    public function adminPayload(): array
    {
        return Mount::query()
            ->with('node:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Mount $mount): array => $this->serializeMount($mount))
            ->values()
            ->all();
    }

    public function create(array $payload): Mount
    {
        $targetPath = $this->normalizeTargetPath($payload['target_path'] ?? '');

        return Mount::query()->create([
            'name' => trim((string) $payload['name']),
            'description' => $this->nullableString($payload['description'] ?? null),
            'source_path' => trim((string) $payload['source_path']),
            'target_path' => $targetPath,
            'read_only' => filter_var($payload['read_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'node_id' => ! empty($payload['node_id']) ? (int) $payload['node_id'] : null,
        ])->load('node:id,name');
    }

    public function delete(Mount $mount): void
    {
        ServerMount::query()->where('mount_id', $mount->id)->delete();
        $mount->delete();
    }

    public function serverPayload(Server $server): array
    {
        $server->loadMissing(['allocation', 'node']);

        $assignedRows = ServerMount::query()
            ->where('server_id', $server->id)
            ->with('mount.node:id,name')
            ->get();

        $assignedMounts = $assignedRows
            ->filter(fn (ServerMount $row): bool => $row->mount !== null)
            ->map(function (ServerMount $row): array {
                $mount = $row->mount;

                return [
                    'id' => $mount->id,
                    'name' => $mount->name,
                    'description' => $mount->description,
                    'sourcePath' => $mount->source_path,
                    'targetPath' => $mount->target_path,
                    'nodeId' => $mount->node_id,
                    'nodeName' => $mount->node?->name,
                    'readOnly' => $row->read_only,
                    'serverMountId' => $row->id,
                ];
            })
            ->values();

        $assignedIds = $assignedMounts->pluck('id')->all();
        $availableMounts = Mount::query()
            ->with('node:id,name')
            ->when($assignedIds !== [], fn ($query) => $query->whereNotIn('id', $assignedIds))
            ->where(function ($query) use ($server) {
                $query->whereNull('node_id')
                    ->orWhere('node_id', $server->node_id);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Mount $mount): array => [
                'id' => $mount->id,
                'name' => $mount->name,
                'description' => $mount->description,
                'sourcePath' => $mount->source_path,
                'targetPath' => $mount->target_path,
                'nodeId' => $mount->node_id,
                'nodeName' => $mount->node?->name,
                'readOnly' => (bool) $mount->read_only,
            ])
            ->values()
            ->all();

        return [
            'assignedMounts' => $assignedMounts->all(),
            'availableMounts' => $availableMounts,
        ];
    }

    public function attach(Server $server, int $mountId, ?bool $readOnly = null): array
    {
        $mount = Mount::query()->findOrFail($mountId);
        $this->ensureMountAvailableForServer($server, $mount);

        $record = ServerMount::query()->firstOrCreate(
            ['server_id' => $server->id, 'mount_id' => $mount->id],
            ['read_only' => $readOnly ?? (bool) $mount->read_only]
        );

        $resolvedReadOnly = $readOnly ?? (bool) $mount->read_only;
        if ((bool) $record->read_only !== (bool) $resolvedReadOnly) {
            $record->forceFill(['read_only' => (bool) $resolvedReadOnly])->save();
        }

        return $this->serverPayload($server);
    }

    public function detach(Server $server, int $mountId): array
    {
        ServerMount::query()
            ->where('server_id', $server->id)
            ->where('mount_id', $mountId)
            ->delete();

        return $this->serverPayload($server);
    }

    public function installConfig(Server $server): array
    {
        return ServerMount::query()
            ->where('server_id', $server->id)
            ->with('mount')
            ->get()
            ->filter(fn (ServerMount $row): bool => $row->mount !== null)
            ->map(function (ServerMount $row): ?array {
                $source = trim((string) $row->mount->source_path);
                $target = trim((string) $row->mount->target_path);

                if ($source === '' || $target === '') {
                    return null;
                }

                return [
                    'source' => $source,
                    'target' => $target,
                    'readOnly' => (bool) $row->read_only,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function ensureMountAvailableForServer(Server $server, Mount $mount): void
    {
        if ($mount->node_id !== null && (int) $mount->node_id !== (int) $server->node_id) {
            throw ValidationException::withMessages([
                'mount_id' => 'Mount is not available on the selected node.',
            ]);
        }
    }

    private function serializeMount(Mount $mount): array
    {
        return [
            'id' => $mount->id,
            'name' => $mount->name,
            'description' => $mount->description,
            'sourcePath' => $mount->source_path,
            'targetPath' => $mount->target_path,
            'readOnly' => (bool) $mount->read_only,
            'nodeId' => $mount->node_id,
            'nodeName' => $mount->node?->name,
            'serversCount' => $mount->serverMounts()->count(),
        ];
    }

    private function normalizeTargetPath(mixed $value): string
    {
        $targetPath = trim((string) $value);

        if ($targetPath === '') {
            return '';
        }

        return str_starts_with($targetPath, '/') ? $targetPath : '/' . ltrim($targetPath, '/');
    }

    private function nullableString(mixed $value): ?string
    {
        $resolved = trim((string) ($value ?? ''));

        return $resolved === '' ? null : $resolved;
    }
}
