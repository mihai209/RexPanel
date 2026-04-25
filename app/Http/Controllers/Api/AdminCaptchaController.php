<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaptchaSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCaptchaController extends Controller
{
    public function __construct(private CaptchaSettingsService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->settings->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Captcha settings updated.',
            'settings' => $this->settings->update((bool) $data['enabled']),
        ]);
    }
}
