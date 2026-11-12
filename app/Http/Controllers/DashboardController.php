<?php

namespace App\Http\Controllers;

use App\Models\PaperOrder;
use App\Models\PriceAlert;
use App\Models\ScanResult;
use App\Models\SimulationRun;
use App\Models\TradePlan;
use App\Models\TraderSetting;
use App\Services\ForexMarketService;
use App\Services\GeminiService;

class DashboardController extends Controller
{
    public function index(ForexMarketService $market, GeminiService $gemini)
    {
        $settings = TraderSetting::current();
        $sessions = $market->getActiveSessions();
        $latestScan = ScanResult::orderBy('created_at', 'desc')->first();
        $plans = TradePlan::orderBy('created_at', 'desc')->take(5)->get();
        $latestSim = SimulationRun::orderBy('created_at', 'desc')->first();
        $openOrders = PaperOrder::where('status', 'open')->orderBy('created_at', 'desc')->get();
        $closedOrders = PaperOrder::where('status', 'closed')->orderBy('closed_at', 'desc')->take(5)->get();
        $activeAlerts = PriceAlert::where('status', 'active')->orderBy('created_at', 'desc')->get();

        $totalRealizedPnl = (float) PaperOrder::where('status', 'closed')->sum('pnl_usd');
        $effectiveBalance = round((float) $settings->account_balance + $totalRealizedPnl, 2);

        return view('dashboard', [
            'settings' => $settings,
            'geminiConfigured' => $gemini->isConfigured(),
            'sessions' => $sessions,
            'latestScan' => $latestScan,
            'plans' => $plans,
            'latestSim' => $latestSim,
            'openOrders' => $openOrders,
            'closedOrders' => $closedOrders,
            'activeAlerts' => $activeAlerts,
            'totalRealizedPnl' => round($totalRealizedPnl, 2),
            'effectiveBalance' => $effectiveBalance,
        ]);
    }
}

