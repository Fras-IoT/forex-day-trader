<?php

namespace App\Http\Controllers;

use App\Models\TradePlan;
use App\Models\TraderSetting;
use App\Services\ForexMarketService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class TradePlanController extends Controller
{
    public function index(ForexMarketService $market, GeminiService $gemini)
    {
        $plans = TradePlan::orderBy('created_at', 'desc')->take(15)->get();
        $pairs = array_keys(ForexMarketService::PAIRS);

        return view('plans', [
            'plans' => $plans,
            'pairs' => $pairs,
            'prefill' => [
                'pair' => request('pair', 'EUR/USD'),
                'side' => request('side', 'buy'),
                'setup' => request('setup', 'Session Range Breakout'),
                'entry_price' => request('entry_price'),
                'range_high' => request('range_high'),
                'range_low' => request('range_low'),
            ],
            'geminiConfigured' => $gemini->isConfigured(),
        ]);
    }

    public function store(Request $request, ForexMarketService $market)
    {
        $data = $request->validate([
            'pair' => 'required|string',
            'side' => 'required|in:buy,sell',
            'setup' => 'required|string|max:120',
            'entry_price' => 'nullable|numeric|gt:0',
            'stop_rule' => 'required|string',
            'stop_value' => 'nullable|numeric',
            'target_rule' => 'required|string',
            'target_value' => 'nullable|numeric',
        ]);

        $meta = $market->getPairMeta($data['pair']);
        $latest = $market->getLatestPrice($meta['pair']);
        $entry = $data['entry_price'] ? (float) $data['entry_price'] : (float) $latest['price'];
        $isBuy = $data['side'] === 'buy';
        $pip = $meta['pip'];

        // Calculate Stop Loss
        $stop = match ($data['stop_rule']) {
            'pips' => $isBuy
                ? $entry - (max(5, (float) ($data['stop_value'] ?? 20)) * $pip)
                : $entry + (max(5, (float) ($data['stop_value'] ?? 20)) * $pip),
            'price' => (float) ($data['stop_value'] ?? ($isBuy ? $entry - 20 * $pip : $entry + 20 * $pip)),
            default => $isBuy ? $entry - (20 * $pip) : $entry + (20 * $pip),
        };

        $riskDiff = abs($entry - $stop);
        if ($riskDiff <= 0) {
            return back()->with('error', 'Stop Loss tidak valid (jarak risiko harus > 0).');
        }

        // Calculate Take Profit
        $target = match ($data['target_rule']) {
            '1R' => $isBuy ? $entry + $riskDiff : $entry - $riskDiff,
            '1.5R' => $isBuy ? $entry + ($riskDiff * 1.5) : $entry - ($riskDiff * 1.5),
            '2R' => $isBuy ? $entry + ($riskDiff * 2.0) : $entry - ($riskDiff * 2.0),
            '3R' => $isBuy ? $entry + ($riskDiff * 3.0) : $entry - ($riskDiff * 3.0),
            'price' => (float) ($data['target_value'] ?? ($isBuy ? $entry + $riskDiff * 2 : $entry - $riskDiff * 2)),
            default => $isBuy ? $entry + ($riskDiff * 2.0) : $entry - ($riskDiff * 2.0),
        };

        $rewardDiff = abs($target - $entry);
        $riskPips = $market->toPips($meta['pair'], $riskDiff);
        $rewardPips = $market->toPips($meta['pair'], $rewardDiff);
        $rr = $riskDiff > 0 ? round($rewardDiff / $riskDiff, 2) : 0.0;

        $settings = TraderSetting::current();

        $plan = TradePlan::create([
            'pair' => $meta['pair'],
            'yahoo_symbol' => $meta['yahoo'],
            'side' => $data['side'],
            'setup' => $data['setup'],
            'entry_price' => round($entry, $meta['decimals']),
            'stop_loss' => round($stop, $meta['decimals']),
            'take_profit' => round($target, $meta['decimals']),
            'stop_rule' => $data['stop_rule'],
            'target_rule' => $data['target_rule'],
            'risk_pips' => $riskPips,
            'reward_pips' => $rewardPips,
            'reward_risk' => $rr,
            'flat_by' => $settings->flat_by_utc ?? '21:55',
            'status' => 'planned',
            'ai_analysis' => null,
        ]);

        return redirect()->route('timemachine.index', ['plan_id' => (string) $plan->_id])
            ->with('status', "Trade Plan {$plan->pair} ({$plan->side}) dibuat! Lanjutkan ke Time Machine untuk simulasi 10.000x.");
    }

    public function analyze(string $id, GeminiService $gemini)
    {
        $plan = TradePlan::findOrFail($id);

        $prompt = "Evaluate this Forex intraday trade plan:\n"
            . "Pair: {$plan->pair} | Side: " . strtoupper($plan->side) . " | Setup: {$plan->setup}\n"
            . "Entry: {$plan->entry_price} | Stop Loss: {$plan->stop_loss} ({$plan->risk_pips} pips) | Take Profit: {$plan->take_profit} ({$plan->reward_pips} pips)\n"
            . "Reward:Risk: {$plan->reward_risk}R | Flat-by: {$plan->flat_by} UTC\n\n"
            . "Berikan review kritis dalam Bahasa Indonesia (validitas level SL/TP, risiko spread/slippage, dan hal yang perlu diwaspadai sebelum entry).";

        $res = $gemini->interact(
            $prompt,
            'You are a strict Forex risk & execution coach. Be concise and objective.'
        );

        if (!$res['ok']) {
            return redirect()->route('plans.index')->with('error', $res['error']);
        }

        $plan->ai_analysis = $res['text'];
        $plan->save();

        return redirect()->route('plans.index')->with('status', "Analisis Gemini AI untuk plan {$plan->pair} selesai.");
    }
}

