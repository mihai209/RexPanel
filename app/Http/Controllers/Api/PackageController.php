<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function __construct(private PackageService $packageService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            Package::query()
                ->withCount('images')
                ->withCount('servers')
                ->orderBy('name')
                ->get()
        );
    }

    public function show(Package $package): JsonResponse
    {
        $package->load([
            'images' => fn ($query) => $query->withCount('servers')->orderBy('name'),
        ])->loadCount(['images', 'servers']);

        return response()->json($package);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packages,slug',
            'description' => 'nullable|string|max:150',
            'image_url' => 'nullable|string|max:2048',
        ]);

        $package = $this->packageService->create($data);

        return response()->json([
            'message' => 'Package created successfully.',
            'package' => $package->loadCount(['images', 'servers']),
        ]);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('packages', 'slug')->ignore($package->id)],
            'description' => 'nullable|string|max:150',
            'image_url' => 'nullable|string|max:2048',
        ]);

        $package = $this->packageService->update($package, $data);

        return response()->json([
            'message' => 'Package updated successfully.',
            'package' => $package->loadCount(['images', 'servers']),
        ]);
    }

    public function destroy(Package $package): JsonResponse
    {
        try {
            $this->packageService->delete($package);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json([
            'message' => 'Package deleted successfully.',
        ]);
    }
}
