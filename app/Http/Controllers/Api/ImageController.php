<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Package;
use App\Services\EggImportService;
use App\Services\ImageExportService;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends Controller
{
    public function __construct(
        private EggImportService $eggImportService,
        private ImageService $imageService,
        private ImageExportService $imageExportService,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Image::query()
            ->with('package')
            ->withCount('servers')
            ->orderBy('name');

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->integer('package_id'));
        }

        return response()->json($query->get());
    }

    public function show(Image $image): JsonResponse
    {
        return response()->json($image->load(['package', 'imageVariables'])->loadCount('servers'));
    }

    public function destroy(Image $image): JsonResponse
    {
        try {
            $this->imageService->delete($image);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'json_payload' => 'required|string',
            'is_public' => 'nullable|boolean',
        ]);

        try {
            $decoded = json_decode($data['json_payload'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['message' => 'Invalid JSON payload.'], 422);
        }

        $items = array_is_list($decoded) ? $decoded : [$decoded];
        $result = $this->eggImportService->importFromPayload(
            $items,
            (int) $data['package_id'],
            (bool) ($data['is_public'] ?? true)
        );

        return response()->json([
            'message' => 'Image import finished.',
            'summary' => $result,
        ]);
    }

    public function store(Request $request, Package $package): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'author' => 'nullable|string|max:255',
            'update_url' => 'nullable|string|max:2048',
            'docker_image' => 'required|string|max:255',
            'docker_images' => 'nullable|array',
            'features' => 'nullable|array',
            'file_denylist' => 'nullable|array',
            'startup' => 'nullable|string',
            'config_files' => 'nullable|string',
            'config_startup' => 'nullable|string',
            'config_logs' => 'nullable|string',
            'config_stop' => 'nullable|string|max:255',
            'is_public' => 'nullable|boolean',
        ]);

        $image = $this->imageService->create($package, $data);

        return response()->json([
            'message' => 'Image created successfully.',
            'image' => $image,
        ], 201);
    }

    public function update(Request $request, Image $image): JsonResponse
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'docker_image' => 'required|string|max:255',
            'docker_images' => 'required|array',
            'features' => 'nullable|array',
            'file_denylist' => 'nullable|array',
            'startup' => 'nullable|string',
            'config_files' => 'nullable|string',
            'config_startup' => 'nullable|string',
            'config_logs' => 'nullable|string',
            'config_stop' => 'nullable|string|max:255',
            'is_public' => 'nullable|boolean',
        ]);

        $image = $this->imageService->updateConfiguration($image, $data);

        return response()->json([
            'message' => 'Image configuration updated successfully.',
            'image' => $image,
        ]);
    }

    public function updateScripts(Request $request, Image $image): JsonResponse
    {
        $data = $request->validate([
            'script_install' => 'nullable|string',
            'script_entry' => 'nullable|string|max:255',
            'script_container' => 'nullable|string|max:255',
        ]);

        $image = $this->imageService->updateScripts($image, $data);

        return response()->json([
            'message' => 'Install script updated successfully.',
            'image' => $image,
        ]);
    }

    public function replaceImport(Request $request, Image $image): JsonResponse
    {
        $data = $request->validate([
            'json_payload' => 'required|string',
        ]);

        try {
            $decoded = json_decode($data['json_payload'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['message' => 'Invalid JSON payload.'], 422);
        }

        $normalized = $this->eggImportService->parseEggJson($decoded);
        $normalized['source_hash'] = hash('sha256', $data['json_payload']);
        $image = $this->imageService->replaceFromImport($image, $normalized);

        return response()->json([
            'message' => 'Image configuration replaced from import successfully.',
            'image' => $image,
        ]);
    }

    public function export(Image $image): Response
    {
        return response($this->imageExportService->handle($image), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => sprintf('attachment; filename="%s.json"', Strtolower(str_replace(' ', '-', $image->name))),
        ]);
    }

}
