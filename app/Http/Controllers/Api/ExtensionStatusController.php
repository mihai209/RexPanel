<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExtensionService;
use Illuminate\Http\JsonResponse;

class ExtensionStatusController extends Controller
{
    public function __construct(private ExtensionService $extensions)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->extensions->runtimePayload());
    }
}
