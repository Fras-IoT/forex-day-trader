<?php

namespace App\Http\Controllers;

use App\Models\TraderSetting;
use App\Services\ForexMarketService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(GeminiService $gemini)
    {
        $settings = TraderSetting::current();
        $rawKey = $settings->resolvedGeminiKey();
        $maskedKey = $rawKey !== '' ? substr($rawKey, 0, 6) . str_repeat('*', max(4, strlen($rawKey) - 10)) . substr($rawKey, -4) : '';

        return view('settings', [
            'settings' => $settings,
            'maskedKey' => $maskedKey,
            'geminiConfigured' => $gemini->isConfigured(),
            'pairs' => array_keys(ForexMarketService::PAIRS),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'account_balance' => 'required|numeric|min:100',
            'max_loss_per_trade_usd' => 'required|numeric|min:1',
            'max_daily_loss_usd' => 'required|numeric|min:1',
            'max_trades_per_day' => 'required|integer|min:1|max:50',
            'min_reward_risk' => 'required|numeric|min:0.5|max:10',
            'max_position_pct' => 'required|numeric|min:5|max:100',
            'leverage' => 'required|integer|min:1|max:500',
            'flat_by_utc' => 'required|string|max:10',
            'gemini_model' => 'required|string|max:80',
            'gemini_api_key' => 'nullable|string|max:255',
            'blocked_pairs' => 'nullable|array',
        ]);

        $settings = TraderSetting::current();
        $settings->account_balance = (float) $data['account_balance'];
        $settings->max_loss_per_trade_usd = (float) $data['max_loss_per_trade_usd'];
        $settings->max_daily_loss_usd = (float) $data['max_daily_loss_usd'];
        $settings->max_trades_per_day = (int) $data['max_trades_per_day'];
        $settings->min_reward_risk = (float) $data['min_reward_risk'];
        $settings->max_position_pct = (float) $data['max_position_pct'];
        $settings->leverage = (int) $data['leverage'];
        $settings->flat_by_utc = $data['flat_by_utc'];
        $settings->gemini_model = $data['gemini_model'];
        $settings->paper_mode = $request->boolean('paper_mode');
        $settings->blocked_pairs = $data['blocked_pairs'] ?? [];

        if (!empty($data['gemini_api_key'])) {
            $settings->gemini_api_key = trim($data['gemini_api_key']);
        }

        $settings->save();

        return redirect()->route('settings.index')
            ->with('status', 'Pengaturan & konfigurasi MongoDB berhasil disimpan.');
    }

    public function testGemini(GeminiService $gemini)
    {
        $res = $gemini->interact('Reply with exactly: "Gemini Forex AI connected and ready."');

        if ($res['ok']) {
            return redirect()->route('settings.index')
                ->with('status', 'Koneksi Gemini berhasil! Respons: ' . $res['text']);
        }

        return redirect()->route('settings.index')->with('error', $res['error']);
    }
}

