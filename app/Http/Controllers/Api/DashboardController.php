<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, DashboardStatsService $stats)
    {
        return $this->ok($stats->forUser($request->user()), 'Dashboard data');
    }
}
