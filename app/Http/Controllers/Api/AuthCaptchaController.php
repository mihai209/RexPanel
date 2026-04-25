<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaptchaChallengeService;
use App\Services\CaptchaSettingsService;
use Illuminate\Http\JsonResponse;

class AuthCaptchaController extends Controller
{
    public function __construct(
        private CaptchaSettingsService $settings,
        private CaptchaChallengeService $captcha,
    ) {
    }

    public function show(): JsonResponse
    {
        if (! $this->settings->isEnabled()) {
            return response()->json([
                'enabled' => false,
            ]);
        }

        return response()->json([
            'enabled' => true,
            ...$this->captcha->issue(),
        ]);
    }
}
