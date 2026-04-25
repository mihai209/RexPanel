<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_suspended) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account has been suspended by an administrator.',
                'code' => 'ACCOUNT_SUSPENDED',
            ], 423);
        }

        return $next($request);
    }
}
