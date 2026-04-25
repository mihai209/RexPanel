<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApplyAdminApiRatePlan
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureEnabled()) {
            return $next($request);
        }

        $plan = $this->resolvePlan();
        $userId = (int) ($request->user()?->id ?? 0);
        $cacheKey = sprintf(
            'admin_api_rate_plan:%s:%s:%s',
            $userId,
            $plan['name'],
            floor(time() / max(1, $plan['windowSeconds']))
        );

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, 0, now()->addSeconds($plan['windowSeconds']));
        }

        $count = (int) Cache::increment($cacheKey);

        if ($count > $plan['maxRequests']) {
            return response()->json([
                'message' => 'Admin API rate plan limit reached.',
                'ratePlan' => $plan,
            ], 429);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Admin-Rate-Plan', $plan['name']);
        $response->headers->set('X-Admin-RateLimit-Limit', (string) $plan['maxRequests']);
        $response->headers->set('X-Admin-RateLimit-Remaining', (string) max(0, $plan['maxRequests'] - $count));

        return $response;
    }

    private function featureEnabled(): bool
    {
        $value = SystemSetting::query()->where('key', 'featureAdminApiRatePlansEnabled')->value('value');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function resolvePlan(): array
    {
        $default = [
            'name' => 'standard',
            'maxRequests' => 180,
            'windowSeconds' => 60,
        ];

        $raw = SystemSetting::query()->where('key', 'adminApiRatePlanCatalog')->value('value');
        $catalog = is_string($raw) ? json_decode($raw, true) : [];
        $selected = is_array($catalog) ? ($catalog['standard'] ?? null) : null;

        if (! is_array($selected)) {
            return $default;
        }

        return [
            'name' => 'standard',
            'maxRequests' => max(1, (int) ($selected['maxRequests'] ?? $default['maxRequests'])),
            'windowSeconds' => max(1, (int) ($selected['windowSeconds'] ?? $default['windowSeconds'])),
        ];
    }
}
