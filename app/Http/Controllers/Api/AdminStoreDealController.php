<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StoreDealsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreDealController extends Controller
{
    public function __construct(private StoreDealsService $deals)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'deals' => $this->deals->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Store deal created.',
            'deal' => $this->deals->create($request->all()),
        ], 201);
    }

    public function update(string $dealId, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Store deal updated.',
            'deal' => $this->deals->update($dealId, $request->all()),
        ]);
    }

    public function destroy(string $dealId): JsonResponse
    {
        $this->deals->delete($dealId);

        return response()->json([
            'message' => 'Store deal deleted.',
        ]);
    }
}
