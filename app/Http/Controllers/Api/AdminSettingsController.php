<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(private SystemSettingsService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->settings->groupedSettingsPayload());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate($this->settings->validationRules());

        return response()->json([
            'message' => 'Panel settings updated.',
            'data' => $this->settings->update($payload),
        ]);
    }
}
