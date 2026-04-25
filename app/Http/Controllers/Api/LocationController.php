<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = Location::query()
            ->withCount(['databaseHosts', 'nodes as connectors_count'])
            ->orderBy('short_name')
            ->get();

        return response()->json($locations->map(fn (Location $location) => $this->serializeLocation($location))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $location = Location::create($data);

        return response()->json([
            'message' => 'Location created successfully.',
            'location' => $this->serializeLocation($location->fresh()->loadCount(['databaseHosts', 'nodes as connectors_count'])),
        ], 201);
    }

    public function show(Location $location): JsonResponse
    {
        $location->load([
            'databaseHosts' => fn ($query) => $query->withCount('databases')->orderBy('name'),
            'nodes' => fn ($query) => $query->withCount(['servers', 'allocations'])->orderBy('name'),
        ])->loadCount(['databaseHosts', 'nodes as connectors_count']);

        return response()->json($this->serializeLocation($location, true));
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $data = $this->validatedPayload($request, $location);

        $location->update($data);

        return response()->json([
            'message' => 'Location updated successfully.',
            'location' => $this->serializeLocation($location->fresh()->loadCount(['databaseHosts', 'nodes as connectors_count'])),
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        if ($location->nodes()->exists()) {
            return response()->json([
                'message' => 'Cannot delete location because it still has assigned connectors.',
                'code' => 'location_has_connectors',
            ], 400);
        }

        if ($location->databaseHosts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete location because it still has assigned database hosts.',
                'code' => 'location_has_database_hosts',
            ], 400);
        }

        $location->delete();

        return response()->json(['message' => 'Location deleted successfully.']);
    }

    private function validatedPayload(Request $request, ?Location $location = null): array
    {
        $shortName = trim((string) $request->input('short_name', $request->input('shortName', $request->input('name', ''))));

        $data = $request->merge([
            'short_name' => $shortName,
            'name' => trim((string) $request->input('name', $shortName)),
            'image_url' => $request->input('image_url', $request->input('imageUrl')),
        ])->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'short_name')->ignore($location?->id),
            ],
            'description' => ['nullable', 'string', 'max:30'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $data['name'] = $data['short_name'];

        return $data;
    }

    private function serializeLocation(Location $location, bool $withAssets = false): array
    {
        $data = $location->toArray();

        if ($withAssets) {
            $data['assets'] = [
                'database_hosts' => $location->databaseHosts->map(function ($host) {
                    return [
                        'id' => $host->id,
                        'name' => $host->name,
                        'host' => $host->host,
                        'port' => $host->port,
                        'type' => $host->type,
                        'database_count' => (int) ($host->database_count ?? 0),
                    ];
                })->values(),
                'connectors' => $location->nodes->map(function ($node) {
                    return [
                        'id' => $node->id,
                        'name' => $node->name,
                        'fqdn' => $node->fqdn,
                        'server_count' => (int) ($node->servers_count ?? 0),
                        'allocation_count' => (int) ($node->allocations_count ?? 0),
                    ];
                })->values(),
            ];
        }

        return $data;
    }
}
