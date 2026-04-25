<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DatabaseHost;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseHostController extends Controller
{
    /**
     * Display a listing of database hosts.
     */
    public function index(): JsonResponse
    {
        $hosts = DatabaseHost::query()
            ->with('location')
            ->withCount('databases')
            ->orderBy('name')
            ->get();

        return response()->json($hosts->map(fn (DatabaseHost $host) => $this->serializeHost($host))->values());
    }

    /**
     * Store a newly created database host.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $host = DatabaseHost::create($data);

        return response()->json([
            'message' => 'Database host created successfully.',
            'host' => $this->serializeHost($host->fresh()->load('location')->loadCount('databases')),
        ], 201);
    }

    /**
     * Display the specified database host.
     */
    public function show(DatabaseHost $databaseHost): JsonResponse
    {
        return response()->json($this->serializeHost($databaseHost->load('location')->loadCount('databases')));
    }

    /**
     * Update the specified database host.
     */
    public function update(Request $request, DatabaseHost $databaseHost): JsonResponse
    {
        $data = $this->validatedPayload($request, true);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $databaseHost->update($data);

        return response()->json([
            'message' => 'Database host updated successfully.',
            'host' => $this->serializeHost($databaseHost->fresh()->load('location')->loadCount('databases')),
        ]);
    }

    /**
     * Remove the specified database host from storage.
     */
    public function destroy(DatabaseHost $databaseHost): JsonResponse
    {
        if ($databaseHost->databases()->exists()) {
            return response()->json([
                'message' => 'Cannot delete database host because it has active databases.',
            ], 400);
        }

        $databaseHost->delete();

        return response()->json([
            'message' => 'Database host deleted successfully.',
        ]);
    }

    /**
     * Test the connection to the database host.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $data = $request->merge([
            'location_id' => $request->input('location_id', $request->input('locationId')),
        ])->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'database' => 'required|string',
            'type' => 'required|string|in:mysql,mariadb,postgres',
        ]);

        $connectionName = 'temp_test_connection_' . uniqid();
        
        Config::set("database.connections.{$connectionName}", [
            'driver' => $data['type'] === 'postgres' ? 'pgsql' : 'mysql',
            'host' => $data['host'],
            'port' => $data['port'],
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [
                \PDO::ATTR_TIMEOUT => 5,
            ],
        ]);

        try {
            DB::connection($connectionName)->getPdo();
            return response()->json(['message' => 'Connection successful!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Connection failed: ' . $e->getMessage()], 400);
        }
    }

    private function validatedPayload(Request $request, bool $updating = false): array
    {
        return $request->merge([
            'location_id' => $request->input('location_id', $request->input('locationId')),
        ])->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => $updating ? 'nullable|string' : 'required|string',
            'database' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'max_databases' => 'required|integer|min:0',
            'type' => 'required|string|in:mysql,mariadb,postgres',
        ]);
    }

    private function serializeHost(DatabaseHost $host): array
    {
        $data = $host->toArray();
        $location = $host->location;

        $data['database_count'] = (int) ($host->databases_count ?? 0);
        $data['location_id'] = (int) $host->location_id;
        $data['locationId'] = (int) $host->location_id;

        if ($location instanceof Location) {
            $data['location'] = [
                'id' => $location->id,
                'name' => $location->name,
                'short_name' => $location->short_name,
                'shortName' => $location->short_name,
                'image_url' => $location->image_url,
                'imageUrl' => $location->image_url,
            ];
        }

        return $data;
    }
}
