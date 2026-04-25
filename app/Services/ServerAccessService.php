<?php

namespace App\Services;

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServerAccessService
{
    public function resolveForUser(string $routeId, User $user, bool $allowAdminBypass = true): Server
    {
        $server = Server::query()
            ->with([
                'node',
                'user',
                'image.package',
                'primaryAllocation',
                'allocations',
                'runtimeState',
            ])
            ->where('route_id', $routeId)
            ->first();

        if (! $server) {
            throw new HttpResponseException(response()->json([
                'message' => 'Server not found.',
            ], 404));
        }

        if ((int) $server->user_id === (int) $user->id) {
            return $server;
        }

        if ($allowAdminBypass && (bool) $user->is_admin) {
            return $server;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'You do not have permission to access this server.',
        ], 403));
    }
}
