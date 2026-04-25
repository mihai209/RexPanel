<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RedisAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRedisController extends Controller
{
    public function __construct(private RedisAdminService $redis)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->redis->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate($this->redis->validationRules());

        return response()->json($this->redis->update($payload));
    }

    public function test(Request $request): JsonResponse
    {
        $payload = $request->validate($this->redis->validationRules(true));

        return response()->json($this->redis->testConnection($payload));
    }
}
