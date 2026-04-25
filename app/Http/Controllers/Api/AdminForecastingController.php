<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ForecastingService;
use Illuminate\Http\JsonResponse;

class AdminForecastingController extends Controller
{
    public function __construct(private ForecastingService $forecasting)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->forecasting->adminReport());
    }
}
