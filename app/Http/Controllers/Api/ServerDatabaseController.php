<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Server;
use App\Services\ServerDatabaseProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerDatabaseController extends Controller
{
    public function __construct(private ServerDatabaseProvisioningService $databases)
    {
    }

    public function index(Server $server): JsonResponse
    {
        $server->loadMissing('node');
        $eligibleHosts = \App\Models\DatabaseHost::query()
            ->with('location')
            ->withCount('databases')
            ->where('location_id', $server->node?->location_id)
            ->orderBy('name')
            ->get()
            ->map(function ($host) {
                return [
                    'id' => $host->id,
                    'name' => $host->name,
                    'host' => $host->host,
                    'port' => $host->port,
                    'type' => $host->type,
                    'location_id' => $host->location_id,
                    'locationId' => $host->location_id,
                    'database_count' => (int) ($host->databases_count ?? 0),
                    'max_databases' => (int) $host->max_databases,
                    'available' => (int) $host->max_databases === 0 || (int) ($host->databases_count ?? 0) < (int) $host->max_databases,
                    'location' => $host->location?->toArray(),
                ];
            })
            ->values();

        return response()->json([
            'databases' => $this->databases->list($server),
            'database_usage' => $this->databases->usage($server),
            'eligible_hosts' => $eligibleHosts,
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
        ]);
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'database' => 'required|string|max:48|regex:/^[a-zA-Z0-9_]+$/',
            'remote' => 'nullable|string|max:255',
            'database_host_id' => 'nullable|exists:database_hosts,id',
            'databaseHostId' => 'nullable|exists:database_hosts,id',
        ]);

        $database = $this->databases->create($server, [
            ...$data,
            'database_host_id' => $data['database_host_id'] ?? $data['databaseHostId'] ?? null,
        ]);

        return response()->json([
            'message' => 'Database created successfully.',
            'database' => $database,
            'database_usage' => $this->databases->usage($server),
        ], 201);
    }

    public function resetPassword(Server $server, Database $database): JsonResponse
    {
        $password = $this->databases->resetPassword($server, $database);

        return response()->json([
            'message' => 'Password reset successfully.',
            'password' => $password,
            'database_usage' => $this->databases->usage($server),
        ]);
    }

    public function destroy(Server $server, Database $database): JsonResponse
    {
        $this->databases->delete($server, $database);

        return response()->json([
            'message' => 'Database deleted successfully.',
            'database_usage' => $this->databases->usage($server),
        ]);
    }
}
