<?php

namespace App\Http\Controllers;

use App\Services\EmoncDashboardService;

class EmoncAnalyticsDashboardController extends Controller
{
    public function index()
    {
        $data = app(EmoncDashboardService::class)->build(auth()->user());

        return view('analytics.emonc-dashboard', [
            'kpis'             => $data['kpis'],
            'completionMatrix' => $data['completionMatrix'],
            'chartData'        => $data['chartData'],
            'pendingActions'   => $data['pendingActions'],
        ]);
    }
}
