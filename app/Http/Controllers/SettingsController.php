<?php

namespace App\Http\Controllers;

use App\Models\TraderSetting;
use App\Services\ForexAutoMonitorService;
use App\Services\ForexMarketService;
use App\Services\GeminiService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(GeminiService $gemini, TelegramService $telegram)
    {
        $settings = TraderSetting::current();
        $rawKey = $settings->resolvedGeminiKey();
        $maskedKey = $rawKey !== '' ? substr($rawKey, 0, 6) . str_repeat('*', max(4, strlen($rawKey) - 10)) . substr($rawKey, -4) : '';

        return view('settings', [
            'settings' => $settings,
            'maskedKey' => $maskedKey,
            'geminiConfigured' => $gemini->isConfigured(),
            'telegramConfigured' => $telegram->isConfigured(),
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
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id' => 'nullable|string|max:80',
            'auto_scan_min_score' => 'nullable|integer|min:10|max:99',
            'auto_scan_min_prob' => 'nullable|numeric|min:10|max:95',
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
        $settings->auto_scan_enabled = $request->boolean('auto_scan_enabled');
        $settings->auto_close_on_hit = $request->boolean('auto_close_on_hit');
        $settings->auto_scan_min_score = (int) ($data['auto_scan_min_score'] ?? 70);
        $settings->auto_scan_min_prob = (float) ($data['auto_scan_min_prob'] ?? 55);
        $settings->blocked_pairs = $data['blocked_pairs'] ?? [];

        if (!empty($data['gemini_api_key'])) {
            $settings->gemini_api_key = trim($data['gemini_api_key']);
        }
        if (!empty($data['telegram_bot_token'])) {
            $settings->telegram_bot_token = trim($data['telegram_bot_token']);
        }
        if (array_key_exists('telegram_chat_id', $data) && $data['telegram_chat_id'] !== null) {
            $settings->telegram_chat_id = trim((string) $data['telegram_chat_id']);
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

    public function testTelegram(TelegramService $telegram)
    {
        $res = $telegram->sendMessage("✅ <b>Forex Day Trader AI</b>\nKoneksi Telegram Bot aktif! Alarm peluang &gt;55% dan SL/TP siap dikirim.");

        if ($res['ok']) {
            return redirect()->route('settings.index')->with('status', 'Pesan tes berhasil dikirim ke Telegram!');
        }

        return redirect()->route('settings.index')->with('error', $res['error']);
    }

    public function runMonitor(ForexAutoMonitorService $monitor)
    {
        $res = $monitor->run(true);
        $msg = "Auto-Monitor selesai: {$res['positions_checked']} posisi dicek ({$res['positions_hit']} hit SL/TP), "
            . "{$res['scan_hits_evaluated']} setup dievaluasi Monte Carlo, {$res['alerts_sent']} alert Telegram terkirim.";

        if (!empty($res['errors'])) {
            return redirect()->route('settings.index')->with('error', $msg . ' Error: ' . implode('; ', $res['errors']));
        }

        return redirect()->route('settings.index')->with('status', $msg);
    }
}

