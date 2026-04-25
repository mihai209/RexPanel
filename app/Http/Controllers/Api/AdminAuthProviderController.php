<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OAuthProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthProviderController extends Controller
{
    public function __construct(private OAuthProviderService $providers)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->providers->listAdminProviders());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'standard_enabled' => ['required', 'boolean'],
            'providers' => ['required', 'array'],
            'providers.*.enabled' => ['required', 'boolean'],
            'providers.*.register_enabled' => ['required', 'boolean'],
            'providers.*.client_id' => ['nullable', 'string', 'max:2048'],
            'providers.*.client_secret' => ['nullable', 'string', 'max:4096'],
        ]);

        $providers = $request->input('providers', []);
        foreach (array_keys($providers) as $provider) {
            $this->providers->ensureProviderIsSupported($provider);
        }

        return response()->json([
            'message' => 'Authentication provider settings updated.',
            'settings' => $this->providers->updateAdminProviders($request->all()),
        ]);
    }
}
