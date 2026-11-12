<?php

namespace App\Http\Controllers;

use App\Models\AiInteraction;
use App\Models\ScanResult;
use App\Models\TradePlan;
use App\Services\ForexMarketService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class CopilotController extends Controller
{
    public function index(GeminiService $gemini)
    {
        $messages = AiInteraction::orderBy('created_at', 'asc')->take(40)->get();
        $lastAssistant = AiInteraction::where('role', 'assistant')->orderBy('created_at', 'desc')->first();

        return view('copilot', [
            'messages' => $messages,
            'lastInteractionId' => $lastAssistant?->interaction_id,
            'pairs' => array_keys(ForexMarketService::PAIRS),
            'geminiConfigured' => $gemini->isConfigured(),
        ]);
    }

    public function send(Request $request, GeminiService $gemini, ForexMarketService $market)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'pair' => 'nullable|string',
            'previous_interaction_id' => 'nullable|string',
        ]);

        $pair = $data['pair'] ?? 'EUR/USD';
        $latest = $market->getLatestPrice($pair);
        $sessions = $market->getActiveSessions();
        $latestPlan = TradePlan::orderBy('created_at', 'desc')->first();
        $latestScan = ScanResult::orderBy('created_at', 'desc')->first();

        $context = "Live Market Context:\n"
            . "- Time: {$sessions['utc_time']} ({$sessions['wib_time']})\n"
            . "- Pair Focus: {$pair} @ {$latest['price']} (source: {$latest['source']})\n";

        if ($latestPlan) {
            $context .= "- Latest Plan: {$latestPlan->pair} {$latestPlan->side} @ {$latestPlan->entry_price}, SL {$latestPlan->stop_loss}, TP {$latestPlan->take_profit} ({$latestPlan->reward_risk}R)\n";
        }
        if ($latestScan && !empty($latestScan->hits)) {
            $top = $latestScan->hits[0];
            $context .= "- Top Scan Hit: {$top['pair']} {$top['setup']} (Score {$top['score']})\n";
        }

        $sys = "You are Forex Day Trader AI Copilot powered by Gemini. Respond in clear, concise Indonesian (unless asked in English). "
            . "Use the provided live market context. Always emphasize risk management, stop loss discipline, and remind that this is not financial advice.\n\n{$context}";

        $res = $gemini->interact(
            $data['message'],
            $sys,
            $data['previous_interaction_id'] ?: null,
            $pair,
            true
        );

        if (!$res['ok']) {
            return redirect()->route('copilot.index')->with('error', $res['error']);
        }

        return redirect()->route('copilot.index');
    }

    public function clear()
    {
        AiInteraction::truncate();

        return redirect()->route('copilot.index')->with('status', 'Riwayat percakapan Copilot dibersihkan.');
    }
}

