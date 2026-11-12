<?php

namespace App\Http\Controllers;

use App\Models\Mt5Order;
use App\Models\PaperOrder;
use App\Models\PriceAlert;
use App\Models\TradePlan;
use App\Models\TraderSetting;
use App\Services\ForexMarketService;
use App\Services\ForexRiskService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class RiskBrokerController extends Controller
{
    public function index(ForexMarketService $market)
    {
        $plans = TradePlan::orderBy('created_at', 'desc')->take(10)->get();
        $selectedPlanId = request('plan_id');
        $activePlan = $selectedPlanId ? TradePlan::find($selectedPlanId) : $plans->first();
        $pendingOrders = PaperOrder::where('status', 'pending_approval')->orderBy('created_at', 'desc')->get();
        $rejectedOrders = PaperOrder::where('status', 'rejected')->orderBy('created_at', 'desc')->take(5)->get();
        $openOrders = PaperOrder::where('status', 'open')->orderBy('created_at', 'desc')->get();
        $closedOrders = PaperOrder::where('status', 'closed')->orderBy('closed_at', 'desc')->take(15)->get();
        $settings = TraderSetting::current();

        // Compute live unrealized PnL for open orders
        $livePrices = [];
        foreach ($openOrders as $o) {
            if (!isset($livePrices[$o->pair])) {
                $livePrices[$o->pair] = $market->getLatestPrice($o->pair)['price'];
            }
        }

        return view('broker', [
            'plans' => $plans,
            'activePlan' => $activePlan,
            'pendingOrders' => $pendingOrders,
            'rejectedOrders' => $rejectedOrders,
            'openOrders' => $openOrders,
            'closedOrders' => $closedOrders,
            'livePrices' => $livePrices,
            'settings' => $settings,
        ]);
    }

    public function sizeAndCheck(Request $request, ForexRiskService $risk)
    {
        $data = $request->validate([
            'trade_plan_id' => 'required|string',
            'max_loss_usd' => 'nullable|numeric|min:1',
            'order_type' => 'nullable|in:market,limit',
        ]);

        $plan = TradePlan::findOrFail($data['trade_plan_id']);
        $customMaxLoss = isset($data['max_loss_usd']) ? (float) $data['max_loss_usd'] : null;
        $result = $risk->sizeAndCheck($plan, $customMaxLoss);

        $order = PaperOrder::create([
            'trade_plan_id' => (string) $plan->_id,
            'pair' => $plan->pair,
            'side' => $plan->side,
            'order_type' => $data['order_type'] ?? 'market',
            'lots' => $result['lots'],
            'units' => $result['units'],
            'pip_value_usd' => $result['pip_value_usd'],
            'entry_price' => $plan->entry_price,
            'stop_loss' => $plan->stop_loss,
            'take_profit' => $plan->take_profit,
            'max_loss_usd' => $result['max_loss_usd'],
            'reward_risk' => $result['reward_risk'],
            'risk_status' => $result['risk_status'],
            'risk_checks' => $result['risk_checks'],
            'fingerprint' => $result['fingerprint'],
            'paper' => $result['paper'],
            'status' => $result['risk_status'] === 'PASS' ? 'pending_approval' : 'rejected',
        ]);

        $plan->status = 'sized';
        $plan->save();

        if ($order->risk_status === 'PASS') {
            $msg = "Risk Check PASS! Ticket {$order->pair} ({$order->lots} lot) siap untuk konfirmasi eksekusi.";
        } else {
            $failedDetails = collect($result['risk_checks'] ?? [])
                ->where('status', 'BLOCK')
                ->map(fn ($c) => "{$c['rule']}: {$c['detail']}")
                ->implode(' | ');
            $msg = "Risk Check BLOCK! Alasan penolakan: " . ($failedDetails ?: 'Melanggar parameter risiko.');
        }

        return redirect()->route('broker.index', ['plan_id' => (string) $plan->_id])
            ->with($order->risk_status === 'PASS' ? 'status' : 'error', $msg);
    }

    public function execute(string $id, Request $request, TelegramService $telegram)
    {
        $order = PaperOrder::findOrFail($id);

        if ($order->risk_status !== 'PASS' || $order->status !== 'pending_approval') {
            return redirect()->route('broker.index')->with('error', 'Order tidak memenuhi syarat PASS atau sudah diproses.');
        }

        if ($request->input('confirm_yes') !== 'YES') {
            return redirect()->route('broker.index')->with('error', 'Eksekusi dibatalkan: konfirmasi eksplisit diperlukan.');
        }

        $order->status = 'open';
        $order->executed_at = now();
        $order->save();

        // Auto-create SL & TP alerts just like trader-alerts --from-plan
        PriceAlert::create([
            'pair' => $order->pair,
            'condition' => $order->side === 'buy' ? 'below' : 'above',
            'target_price' => $order->stop_loss,
            'label' => "Stop Loss ({$order->pair} {$order->side})",
            'trade_plan_id' => $order->trade_plan_id,
            'status' => 'active',
        ]);
        PriceAlert::create([
            'pair' => $order->pair,
            'condition' => $order->side === 'buy' ? 'above' : 'below',
            'target_price' => $order->take_profit,
            'label' => "Take Profit ({$order->pair} {$order->side})",
            'trade_plan_id' => $order->trade_plan_id,
            'status' => 'active',
        ]);

        // Metode 1: Sinkronkan langsung ke MT5 EA dengan status 'approved'
        $mt5Symbol = str_replace(['/', ' '], '', (string) $order->pair);
        $mt5Order = Mt5Order::create([
            'pair' => $order->pair,
            'mt5_symbol' => $mt5Symbol,
            'side' => $order->side,
            'lots' => (float) $order->lots,
            'entry_price' => (float) $order->entry_price,
            'stop_loss' => (float) $order->stop_loss,
            'take_profit' => (float) $order->take_profit,
            'setup' => 'Web App Manual Approval',
            'score' => 100,
            'expected_r' => (float) ($order->reward_risk ?? 1.5),
            'target_first_pct' => 50.0,
            'status' => 'approved',
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        if ($telegram->isConfigured()) {
            $telegram->sendMessage(
                "🌐 <b>ORDER DISETUJUI DARI APLIKASI WEB</b>\n"
                . "Pair: <b>{$order->pair} (" . strtoupper($order->side) . ")</b>\n"
                . "Lots: <code>{$order->lots}</code> | Entry: <code>{$order->entry_price}</code>\n"
                . "SL: <code>{$order->stop_loss}</code> | TP: <code>{$order->take_profit}</code>\n"
                . "Status: <b>Diteruskan ke Robot MT5 (Akan open otomatis)</b>"
            );
        }

        return redirect()->route('broker.index')
            ->with('status', "Order DISETUJUI: " . strtoupper($order->side) . " {$order->lots} lot {$order->pair} @ {$order->entry_price}. Posisi diteruskan ke Robot MT5 & Alert SL/TP aktif.");
    }

    public function close(string $id, ForexMarketService $market)
    {
        $order = PaperOrder::findOrFail($id);
        if ($order->status !== 'open') {
            return redirect()->route('broker.index')->with('error', 'Posisi sudah ditutup.');
        }

        $latest = $market->getLatestPrice($order->pair);
        $closePrice = (float) $latest['price'];
        $entry = (float) $order->entry_price;
        $diff = $order->side === 'buy' ? ($closePrice - $entry) : ($entry - $closePrice);
        $meta = $market->getPairMeta($order->pair);
        $pips = round($diff / $meta['pip'], 1);
        $pnlUsd = round($pips * (float) $order->pip_value_usd, 2);

        $order->status = 'closed';
        $order->close_price = $closePrice;
        $order->pnl_pips = $pips;
        $order->pnl_usd = $pnlUsd;
        $order->closed_at = now();
        $order->save();

        return redirect()->route('broker.index')
            ->with('status', "Posisi {$order->pair} ditutup @ {$closePrice} (PnL: {$pips} pips / \${$pnlUsd}).");
    }
}

