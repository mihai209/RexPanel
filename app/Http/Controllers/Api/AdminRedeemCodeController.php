<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RedeemCodesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRedeemCodeController extends Controller
{
    public function __construct(private RedeemCodesService $codes)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'codes' => $this->codes->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Redeem code created.',
            'code' => $this->codes->create($request->all()),
        ], 201);
    }

    public function update(string $codeId, Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Redeem code updated.',
            'code' => $this->codes->update($codeId, $request->all()),
        ]);
    }

    public function destroy(string $codeId): JsonResponse
    {
        $this->codes->delete($codeId);

        return response()->json([
            'message' => 'Redeem code deleted.',
        ]);
    }
}
