<?php

namespace App\Http\Controllers;

use App\Models\NewsBrief;
use App\Models\PriceAlert;
use App\Services\ForexMarketService;
use App\Services\ForexNewsService;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class NewsAlertController extends Controller
{
    public function news(GeminiService $gemini)
    {
        $briefs = NewsBrief::orderBy('created_at', 'desc')->take(10)->get();
        $pairs = array_keys(ForexMarketService::PAIRS);

        return view('news', [
            'briefs' => $briefs,
            'pairs' => $pairs,
            'geminiConfigured' => $gemini->isConfigured(),
        ]);
    }

    public function fetchNews(Request $request, ForexNewsService $newsService)
    {
        $pair = $request->input('pair', 'EUR/USD');
        $brief = $newsService->fetchAndAnalyze($pair);

        return redirect()->route('news.index')
            ->with('status', "Berita & analisis makro untuk {$brief->pair} berhasil diperbarui.");
    }

    public function alerts()
    {
        $alerts = PriceAlert::orderBy('created_at', 'desc')->take(30)->get();
        $pairs = array_keys(ForexMarketService::PAIRS);

        return view('alerts', [
            'alerts' => $alerts,
            'pairs' => $pairs,
        ]);
    }

    public function storeAlert(Request $request)
    {
        $data = $request->validate([
            'pair' => 'required|string',
            'condition' => 'required|in:above,below',
            'target_price' => 'required|numeric|gt:0',
            'label' => 'required|string|max:120',
        ]);

        PriceAlert::create([
            'pair' => $data['pair'],
            'condition' => $data['condition'],
            'target_price' => (float) $data['target_price'],
            'label' => $data['label'],
            'status' => 'active',
        ]);

        return redirect()->route('alerts.index')->with('status', 'Price Alert baru ditambahkan.');
    }

    public function checkAlerts(ForexMarketService $market)
    {
        $active = PriceAlert::where('status', 'active')->get();
        $triggeredCount = 0;

        foreach ($active as $alert) {
            $latest = $market->getLatestPrice($alert->pair);
            $px = (float) $latest['price'];
            $hit = ($alert->condition === 'above' && $px >= (float) $alert->target_price)
                || ($alert->condition === 'below' && $px <= (float) $alert->target_price);

            if ($hit) {
                $alert->status = 'triggered';
                $alert->triggered_at = now();
                $alert->triggered_price = $px;
                $alert->save();
                $triggeredCount++;
            }
        }

        return redirect()->route('alerts.index')
            ->with('status', "Pengecekan selesai: {$triggeredCount} alert terpicu.");
    }

    public function dismissAlert(string $id)
    {
        $alert = PriceAlert::findOrFail($id);
        $alert->status = 'dismissed';
        $alert->save();

        return redirect()->route('alerts.index')->with('status', 'Alert ditutup.');
    }
}

