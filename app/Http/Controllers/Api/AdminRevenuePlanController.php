<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevenueModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRevenuePlanController extends Controller
{
    public function __construct(private RevenueModeService $revenue)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => $this->revenue->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Revenue plan created.',
            'plan' => $this->revenue->create($request->all()),
        ], 201);
    }

    public function update(string $planId, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Revenue plan updated.',
            'plan' => $this->revenue->update($planId, $request->all()),
        ]);
    }

    public function destroy(string $planId): JsonResponse
    {
        $this->revenue->delete($planId);

        return response()->json([
            'message' => 'Revenue plan deleted.',
        ]);
    }
}
