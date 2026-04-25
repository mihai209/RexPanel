<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\NodeHealthService;
use App\Services\ServerAccessService;
use App\Services\ServerProvisioningService;
use App\Services\ServerRuntimeStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserServerController extends Controller
{
    public function __construct(
        private ServerAccessService $access,
        private NodeHealthService $nodeHealth,
        private ServerRuntimeStateService $runtimeState,
        private ServerProvisioningService $provisioning,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $servers = Server::query()
            ->with(['primaryAllocation', 'runtimeState'])
            ->when(! $request->user()->is_admin, fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderBy('name')
            ->get()
            ->map(function (Server $server) {
                $runtime = $this->runtimeState->snapshot($server);

                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'description' => $server->description,
                    'route_id' => $server->route_id,
                    'container_id' => $server->route_id,
                    'status' => $runtime['power_state'] ?: $server->status,
                    'cpu' => $server->cpu,
                    'memory' => $server->memory,
                    'disk' => $server->disk,
                    'allocation' => $server->primaryAllocation
                        ? sprintf('%s:%s', $server->primaryAllocation->ip, $server->primaryAllocation->port)
                        : null,
                    'is_installing' => $runtime['is_installing'],
                    'has_install_error' => $runtime['has_install_error'],
                ];
            })
            ->values();

        return response()->json($servers);
    }

    public function show(Request $request, string $containerId): JsonResponse
    {
        $server = $this->access->resolveForUser($containerId, $request->user());
        $nodeHealth = $this->nodeHealth->summarize($server->node);
        $runtime = $this->runtimeState->snapshot($server);
        $features = $this->runtimeState->featureAvailability($server, $nodeHealth);

        return response()->json([
            'server' => [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'route_id' => $server->route_id,
                'container_id' => $server->route_id,
                'name' => $server->name,
                'description' => $server->description,
                'status' => $runtime['power_state'] ?: $server->status,
                'node_health' => $nodeHealth,
                'limits' => [
                    'cpu' => $server->cpu,
                    'memory' => $server->memory,
                    'disk' => $server->disk,
                    'swap' => $server->swap,
                    'io' => $server->io,
                    'threads' => $server->threads,
                ],
                'primary_allocation' => $server->primaryAllocation ? [
                    'id' => $server->primaryAllocation->id,
                    'ip' => $server->primaryAllocation->ip,
                    'port' => $server->primaryAllocation->port,
                    'ip_alias' => $server->primaryAllocation->ip_alias,
                    'notes' => $server->primaryAllocation->notes,
                ] : null,
                'feature_availability' => $features,
                'install_state' => [
                    'state' => $runtime['install_state'],
                    'message' => $runtime['install_message'],
                    'is_installing' => $runtime['is_installing'],
                    'has_install_error' => $runtime['has_install_error'],
                ],
                'runtime' => [
                    'resources' => $runtime['resource_snapshot'],
                    'console_output' => $runtime['console_output'],
                    'install_output' => $runtime['install_output'],
                    'power_state' => $runtime['power_state'],
                    'last_resource_at' => $runtime['last_resource_at'],
                    'last_console_at' => $runtime['last_console_at'],
                    'last_install_output_at' => $runtime['last_install_output_at'],
                ],
                'permissions' => [
                    'can_view' => true,
                    'can_power' => $features['power'],
                    'can_console' => $features['console'],
                    'can_send_command' => $features['command'],
                ],
            ],
        ]);
    }

    public function resources(Request $request, string $containerId): JsonResponse
    {
        $server = $this->access->resolveForUser($containerId, $request->user());
        $runtime = $this->runtimeState->snapshot($server);

        return response()->json([
            'status' => $runtime['power_state'] ?: $server->status,
            'resources' => $runtime['resource_snapshot'],
            'last_resource_at' => $runtime['last_resource_at'],
        ]);
    }

    public function power(Request $request, string $containerId): JsonResponse
    {
        $data = $request->validate([
            'signal' => ['required', 'in:start,stop,restart,kill'],
        ]);

        $server = $this->access->resolveForUser($containerId, $request->user());
        $this->nodeHealth->assertServerIsActive($server, 'Power action');

        $this->provisioning->dispatchPowerSignal($server, $data['signal']);
        $optimisticState = match ($data['signal']) {
            'start' => 'starting',
            'restart' => 'restarting',
            default => 'stopping',
        };

        $this->runtimeState->recordPowerState($server, $optimisticState);

        return response()->json([
            'message' => 'Power action dispatched.',
            'signal' => $data['signal'],
            'status' => $optimisticState,
        ]);
    }

    public function sendConsoleCommand(Request $request, string $containerId): JsonResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', 'max:2048'],
        ]);

        $server = $this->access->resolveForUser($containerId, $request->user());
        $this->nodeHealth->assertServerIsActive($server, 'Console command');
        $runtime = $this->runtimeState->snapshot($server);

        if ($runtime['is_installing']) {
            return response()->json([
                'message' => 'Console commands are unavailable while the server is installing.',
            ], 409);
        }

        $this->provisioning->dispatchConsoleCommand($server, $data['command']);

        return response()->json([
            'message' => 'Console command dispatched.',
        ]);
    }

    public function websocketBootstrap(Request $request, string $containerId): JsonResponse
    {
        $server = $this->access->resolveForUser($containerId, $request->user());

        return response()->json([
            'token' => (string) $request->bearerToken(),
            'server' => [
                'id' => $server->id,
                'route_id' => $server->route_id,
                'container_id' => $server->route_id,
            ],
            'websocket' => [
                'host' => env('UI_WS_HOST', $request->getHost()),
                'port' => (int) env('UI_WS_PORT', 8082),
                'scheme' => env('UI_WS_SCHEME', $request->isSecure() ? 'wss' : 'ws'),
            ],
            'event_types' => [
                'server:resource-update',
                'server:power-state',
                'server:console',
                'server:install-output',
                'server:install-state',
            ],
        ]);
    }
}
