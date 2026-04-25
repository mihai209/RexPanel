<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaptchaSettingsService;
use App\Services\OAuthProviderService;
use Illuminate\Http\JsonResponse;

class AuthProviderController extends Controller
{
    public function __construct(
        private OAuthProviderService $providers,
        private CaptchaSettingsService $captcha,
    )
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            ...$this->providers->listPublicProviders(),
            'captcha_enabled' => $this->captcha->isEnabled(),
        ]);
    }
}
