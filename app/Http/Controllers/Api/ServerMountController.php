<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mount;
use App\Models\Server;
use App\Services\ServerMountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerMountController extends Controller
{
    public function __construct(private ServerMountService $mounts)
    {
    }

    public function index(Server $server): JsonResponse
    {
        return response()->json($this->mounts->serverPayload($server));
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'mount_id' => ['required', 'exists:mounts,id'],
            'read_only' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Mount attached. Apply changes on next reinstall.',
            ...$this->mounts->attach($server, (int) $data['mount_id'], array_key_exists('read_only', $data) ? (bool) $data['read_only'] : null),
        ]);
    }

    public function destroy(Server $server, Mount $mount): JsonResponse
    {
        return response()->json([
            'message' => 'Mount detached. Apply changes on next reinstall.',
            ...$this->mounts->detach($server, $mount->id),
        ]);
    }
}
