<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageVariable;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageVariableController extends Controller
{
    public function __construct(private ImageService $imageService)
    {
    }

    public function index(Image $image): JsonResponse
    {
        return response()->json(
            $image->imageVariables()->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request, Image $image): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'env_variable' => 'required|string|max:255',
            'default_value' => 'nullable|string',
            'user_viewable' => 'nullable|boolean',
            'user_editable' => 'nullable|boolean',
            'rules' => 'nullable|string|max:255',
            'field_type' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $variable = $this->imageService->createVariable($image, $data);

        return response()->json([
            'message' => 'Variable created successfully.',
            'variable' => $variable,
        ], 201);
    }

    public function update(Request $request, Image $image, ImageVariable $variable): JsonResponse
    {
        if ($variable->image_id !== $image->id) {
            return response()->json(['message' => 'Variable does not belong to this image.'], 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'env_variable' => 'required|string|max:255',
            'default_value' => 'nullable|string',
            'user_viewable' => 'nullable|boolean',
            'user_editable' => 'nullable|boolean',
            'rules' => 'nullable|string|max:255',
            'field_type' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $variable = $this->imageService->updateVariable($variable, $data);

        return response()->json([
            'message' => 'Variable updated successfully.',
            'variable' => $variable,
        ]);
    }

    public function destroy(Image $image, ImageVariable $variable): JsonResponse
    {
        if ($variable->image_id !== $image->id) {
            return response()->json(['message' => 'Variable does not belong to this image.'], 422);
        }

        $this->imageService->deleteVariable($variable);

        return response()->json([
            'message' => 'Variable deleted successfully.',
        ]);
    }
}
