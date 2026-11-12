<?php

namespace App\Http\Controllers;

use App\Models\ScanResult;
use App\Services\ForexMarketService;
use App\Services\ForexScannerService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    public function index(ForexMarketService $market, GeminiService $gemini)
    {
        $latestScan = ScanResult::orderBy('created_at', 'desc')->first();
        $history = ScanResult::orderBy('created_at', 'desc')->take(5)->get();

        return view('scanner', [
            'latestScan' => $latestScan,
            'history' => $history,
            'sessions' => $market->getActiveSessions(),
            'geminiConfigured' => $gemini->isConfigured(),
        ]);
    }

    public function run(Request $request, ForexScannerService $scanner)
    {
        $mode = $request->input('mode', 'intraday');
        $scan = $scanner->scanAll($mode);

        return redirect()->route('scanner.index')
            ->with('status', "Scan selesai ({$scan->pairs_scanned} pair, " . count($scan->hits ?? []) . " setup ditemukan).");
    }

    public function analyze(Request $request, GeminiService $gemini)
    {
        $scan = ScanResult::orderBy('created_at', 'desc')->first();
        if (!$scan || empty($scan->hits)) {
            return redirect()->route('scanner.index')->with('error', 'Jalankan scanner terlebih dahulu.');
        }

        $topHits = array_slice($scan->hits, 0, 5);
        $summaryLines = collect($topHits)->map(function ($h) {
            return "{$h['pair']} ({$h['side']}): {$h['setup']} @ {$h['price']}, RSI14={$h['rsi14']}, ATR={$h['atr_pips']} pips, Score={$h['score']}";
        })->implode("\n");

        $prompt = "Active Forex Session: {$scan->session}\nTop Scanner Setups:\n{$summaryLines}\n\n"
            . "Berikan analisis singkat dalam Bahasa Indonesia: mana 2 setup paling menarik, apa konfirmasi entry yang harus ditunggu, dan risiko sesi saat ini.";

        $res = $gemini->interact(
            $prompt,
            'You are a disciplined Forex intraday technical analyst. Never give financial advice; focus on price structure, liquidity, and risk.'
        );

        if (!$res['ok']) {
            return redirect()->route('scanner.index')->with('error', $res['error']);
        }

        $scan->ai_summary = $res['text'];
        $scan->save();

        return redirect()->route('scanner.index')->with('status', 'Analisis Gemini AI untuk hasil scan diperbarui.');
    }

    public function livePrices(Request $request, ForexMarketService $market)
    {
        $pairsParam = $request->query('pairs');
        $pairs = $pairsParam ? array_filter(array_map('trim', explode(',', (string) $pairsParam))) : null;

        $heartbeat = \Illuminate\Support\Facades\Cache::get('mt5_bridge_heartbeat');
        $isConnected = $heartbeat && (time() - $heartbeat < 25);
        $tradeAllowed = (bool) \Illuminate\Support\Facades\Cache::get('mt5_trade_allowed', true);

        return response()->json([
            'quotes' => $market->getLiveQuotes($pairs),
            'server_time' => now()->setTimezone('Asia/Jakarta')->format('H:i:s') . ' WIB',
            'mt5' => [
                'connected' => (bool) $isConnected,
                'trade_allowed' => $tradeAllowed,
                'last_seen_seconds_ago' => $heartbeat ? (time() - $heartbeat) : null,
                'source' => $isConnected ? 'mt5_finex_live' : 'external_fallback',
                'label' => $isConnected
                    ? ($tradeAllowed ? 'MT5 Finex Live' : 'MT5 Standby (Algo Mati)')
                    : 'MT5 Disconnected',
            ],
        ]);
    }
}

