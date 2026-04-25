<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Node;
use App\Models\Server;
use App\Services\NodeHealthService;
use App\Services\CommerceProvisioningService;
use App\Services\ServerAllocationService;
use App\Services\ServerBuildService;
use App\Services\ServerDatabaseProvisioningService;
use App\Services\ServerProvisioningService;
use App\Services\ServerRuntimeStateService;
use App\Services\ServerStartupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{
    public function __construct(
        private ServerProvisioningService $provisioningService,
        private ServerBuildService $buildService,
        private ServerAllocationService $allocationService,
        private ServerStartupService $startupService,
        private ServerDatabaseProvisioningService $databaseService,
        private NodeHealthService $nodeHealth,
        private ServerRuntimeStateService $runtimeState,
        private CommerceProvisioningService $commerceProvisioning,
    ) {
    }

    public function index(): JsonResponse
    {
        $servers = Server::query()
            ->with([
                'node',
                'user',
                'image.package',
                'image.imageVariables',
                'primaryAllocation',
                'allocations',
            ])
            ->get()
            ->map(fn (Server $server) => $this->transformServer($server));

        return response()->json($servers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $normalized = $this->buildService->normalizePayload($validated);
        $this->nodeHealth->assertNodeIsActive(Node::query()->findOrFail($normalized['node_id']), 'Server creation');
        $image = Image::query()->with('imageVariables', 'package')->findOrFail($normalized['image_id']);
        $owner = \App\Models\User::query()->findOrFail($normalized['user_id']);
        $normalized = $this->commerceProvisioning->applyFallbackLimits($owner, $normalized);
        $normalized = $this->buildService->fillRuntimeDefaults($normalized, $image);

        $server = DB::transaction(function () use ($normalized, $image) {
            $server = $this->buildService->persist($normalized, $image);
            $this->allocationService->sync(
                $server,
                $normalized['node_id'],
                $normalized['allocation']['default'],
                $normalized['allocation']['additional'],
            );

            return $server;
        });

        $server = $this->loadServer($server);

        $this->provisioningService->dispatchInstall(
            $server,
            false,
            $normalized['start_on_completion'],
        );

        return response()->json([
            'message' => 'Server created successfully.',
            'server' => $this->transformServer($server),
        ], 201);
    }

    public function show(Server $server): JsonResponse
    {
        return response()->json($this->transformServer($this->loadServer($server)));
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $normalized = $this->buildService->normalizePayload($validated, $server);
        $imageChanged = (string) $server->image_id !== (string) $normalized['image_id'];
        $nodeChanged = (int) $server->node_id !== (int) $normalized['node_id'];
        $primaryChanged = (int) $server->allocation_id !== (int) $normalized['allocation']['default'];
        $additionalChanged = collect($server->allocations()->whereKeyNot($server->allocation_id)->pluck('id')->all())
            ->sort()
            ->values()
            ->all() !== collect($normalized['allocation']['additional'])->sort()->values()->all();

        $image = Image::query()->with('imageVariables', 'package')->findOrFail($normalized['image_id']);
        $owner = \App\Models\User::query()->findOrFail($normalized['user_id']);
        $normalized = $this->commerceProvisioning->applyFallbackLimits($owner, $normalized);
        $normalized = $this->buildService->fillRuntimeDefaults($normalized, $image, $server);

        DB::transaction(function () use ($server, $normalized, $image) {
            $this->buildService->persist($normalized, $image, $server);
            $this->allocationService->sync(
                $server,
                $normalized['node_id'],
                $normalized['allocation']['default'],
                $normalized['allocation']['additional'],
            );
        });

        $server = $this->loadServer($server->fresh());
        $runtimeChanged = $imageChanged
            || $nodeChanged
            || $primaryChanged
            || $additionalChanged
            || array_key_exists('startup', $validated)
            || array_key_exists('docker_image', $validated)
            || array_key_exists('variables', $validated);

        if ($runtimeChanged && $normalized['reinstall']) {
            $this->nodeHealth->assertServerIsActive($server, 'Server reinstall');
            $server->update(['status' => 'installing']);
            $this->runtimeState->recordInstallState($server, 'reinstalling', 'Reinstall has been queued.');
            $this->provisioningService->dispatchInstall(
                $this->loadServer($server->fresh()),
                true,
                $normalized['start_on_completion'],
            );
        } else {
            $this->nodeHealth->assertServerIsActive($server, 'Resource sync');
            $this->provisioningService->dispatchResourceLimits($server);
        }

        return response()->json([
            'message' => 'Server updated successfully.',
            'server' => $this->transformServer($this->loadServer($server->fresh())),
        ]);
    }

    public function updateResources(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'cpu' => 'required|integer|min:10|max:1600',
            'memory' => 'required|integer|min:128|max:65536',
            'disk' => 'required|integer|min:512',
            'swap' => 'nullable|integer|min:0',
            'io' => 'nullable|integer|min:10|max:1000',
            'threads' => 'nullable|string|max:64',
            'oom_disabled' => 'nullable|boolean',
        ]);

        $server->update([
            ...$data,
            'swap' => $data['swap'] ?? 0,
            'io' => $data['io'] ?? 500,
            'threads' => $data['threads'] ?? $server->threads,
            'oom_disabled' => (bool) ($data['oom_disabled'] ?? $server->oom_disabled),
        ]);

        $this->nodeHealth->assertServerIsActive($server, 'Resource limit update');
        $this->provisioningService->dispatchResourceLimits($server->fresh());

        return response()->json([
            'message' => 'Resource limits update sent to node.',
            'server' => $this->transformServer($this->loadServer($server->fresh())),
        ]);
    }

    public function destroy(Server $server): JsonResponse
    {
        $this->nodeHealth->assertServerIsActive($server, 'Server deletion');
        $this->provisioningService->dispatchDelete($server);
        $this->allocationService->releaseAll($server);
        $server->delete();

        return response()->json(['message' => 'Server deletion request sent.']);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'external_id' => 'nullable|string|max:255',
            'node_id' => 'required|exists:nodes,id',
            'allocation_id' => 'nullable|exists:node_allocations,id',
            'allocation.default' => 'nullable|exists:node_allocations,id',
            'allocation.additional' => 'nullable|array',
            'allocation.additional.*' => 'integer|exists:node_allocations,id',
            'user_id' => 'required|exists:users,id',
            'image_id' => 'required|exists:images,id',
            'cpu' => 'nullable|integer|min:10|max:1600',
            'memory' => 'nullable|integer|min:128|max:65536',
            'disk' => 'nullable|integer|min:512',
            'swap' => 'nullable|integer|min:0',
            'io' => 'nullable|integer|min:10|max:1000',
            'limits' => 'nullable|array',
            'limits.cpu' => 'nullable|integer|min:10|max:1600',
            'limits.memory' => 'nullable|integer|min:128|max:65536',
            'limits.disk' => 'nullable|integer|min:512',
            'limits.swap' => 'nullable|integer|min:0',
            'limits.io' => 'nullable|integer|min:10|max:1000',
            'limits.threads' => 'nullable|string|max:64',
            'threads' => 'nullable|string|max:64',
            'oom_disabled' => 'nullable|boolean',
            'database_limit' => 'nullable|integer|min:0',
            'allocation_limit' => 'nullable|integer|min:0',
            'backup_limit' => 'nullable|integer|min:0',
            'feature_limits' => 'nullable|array',
            'feature_limits.databases' => 'nullable|integer|min:0',
            'feature_limits.allocations' => 'nullable|integer|min:0',
            'feature_limits.backups' => 'nullable|integer|min:0',
            'docker_image' => 'nullable|string|max:255',
            'startup' => 'nullable|string',
            'variables' => 'nullable|array',
            'start_on_completion' => 'nullable|boolean',
            'reinstall' => 'nullable|boolean',
            'status' => 'nullable|string|max:50',
        ];
    }

    private function loadServer(Server $server): Server
    {
        return $server->load([
            'node',
            'user',
            'image.package',
            'image.imageVariables',
            'primaryAllocation',
            'allocations',
            'runtimeState',
        ]);
    }

    private function transformServer(Server $server): array
    {
        $primaryAllocation = $server->primaryAllocation;
        $startupPreview = null;
        $nodeHealth = $server->node ? $this->nodeHealth->summarize($server->node) : null;
        $runtime = $this->runtimeState->snapshot($server);

        if ($primaryAllocation && $server->image) {
            $startupPreview = $this->startupService->buildPreview($server, $server->image, $primaryAllocation);
        }

        $allocations = $server->allocations
            ->sortBy(fn ($allocation) => (int) $allocation->id === (int) $server->allocation_id ? 0 : 1)
            ->values()
            ->map(fn ($allocation) => [
                'id' => $allocation->id,
                'node_id' => $allocation->node_id,
                'ip' => $allocation->ip,
                'ip_alias' => $allocation->ip_alias,
                'port' => $allocation->port,
                'server_id' => $allocation->server_id,
                'notes' => $allocation->notes,
                'is_primary' => (int) $allocation->id === (int) $server->allocation_id,
            ])
            ->all();

        return [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'route_id' => $server->route_id,
            'container_id' => $server->route_id,
            'name' => $server->name,
            'description' => $server->description,
            'external_id' => $server->external_id,
            'node_id' => $server->node_id,
            'user_id' => $server->user_id,
            'image_id' => $server->image_id,
            'allocation_id' => $server->allocation_id,
            'cpu' => $server->cpu,
            'memory' => $server->memory,
            'disk' => $server->disk,
            'swap' => $server->swap,
            'io' => $server->io,
            'threads' => $server->threads,
            'oom_disabled' => (bool) $server->oom_disabled,
            'docker_image' => $server->docker_image,
            'startup' => $server->startup,
            'variables' => $server->variables ?? [],
            'status' => $runtime['power_state'] ?: $server->status,
            'database_limit' => $server->database_limit,
            'allocation_limit' => $server->allocation_limit,
            'backup_limit' => $server->backup_limit,
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
            'database_usage' => $this->databaseService->usage($server),
            'primary_allocation' => $primaryAllocation ? [
                'id' => $primaryAllocation->id,
                'node_id' => $primaryAllocation->node_id,
                'ip' => $primaryAllocation->ip,
                'ip_alias' => $primaryAllocation->ip_alias,
                'port' => $primaryAllocation->port,
                'server_id' => $primaryAllocation->server_id,
                'notes' => $primaryAllocation->notes,
                'is_primary' => true,
            ] : null,
            'allocation' => $primaryAllocation ? [
                'id' => $primaryAllocation->id,
                'ip' => $primaryAllocation->ip,
                'ip_alias' => $primaryAllocation->ip_alias,
                'port' => $primaryAllocation->port,
                'notes' => $primaryAllocation->notes,
            ] : null,
            'allocations' => $allocations,
            'additional_allocations' => array_values(array_filter($allocations, fn ($allocation) => ! $allocation['is_primary'])),
            'startup_preview' => $startupPreview,
            'user' => $server->user,
            'node' => $server->node,
            'node_health' => $nodeHealth,
            'image' => $server->image,
            'runtime' => $runtime,
            'feature_availability' => $nodeHealth ? $this->runtimeState->featureAvailability($server, $nodeHealth) : [],
            'action_permissions' => [
                'connector_actions' => (bool) ($nodeHealth['is_active'] ?? false),
                'reasons' => $nodeHealth['reasons'] ?? [],
                'reason_text' => $nodeHealth['reason_text'] ?? null,
            ],
            'created_at' => $server->created_at,
            'updated_at' => $server->updated_at,
        ];
    }
}
