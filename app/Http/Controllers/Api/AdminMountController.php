<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mount;
use App\Services\ServerMountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMountController extends Controller
{
    public function __construct(private ServerMountService $mounts)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'mounts' => $this->mounts->adminPayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'source_path' => ['required', 'string', 'max:512'],
            'target_path' => ['required', 'string', 'max:512'],
            'read_only' => ['nullable', 'boolean'],
            'node_id' => ['nullable', 'exists:nodes,id'],
        ]);

        $mount = $this->mounts->create($data);

        return response()->json([
            'message' => 'Mount created successfully.',
            'mount' => $mount,
            'mounts' => $this->mounts->adminPayload(),
        ], 201);
    }

    public function destroy(Mount $mount): JsonResponse
    {
        $this->mounts->delete($mount);

        return response()->json([
            'message' => 'Mount deleted.',
            'mounts' => $this->mounts->adminPayload(),
        ]);
    }
}
