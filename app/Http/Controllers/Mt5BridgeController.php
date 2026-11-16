<?php

namespace App\Http\Controllers;

use App\Models\Mt5Order;
use App\Models\PaperOrder;
use App\Models\TraderSetting;
use App\Services\ForexAutoMonitorService;
use App\Services\ForexMarketService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Mt5BridgeController extends Controller
{
    private function authorizeBridge(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $expected = TraderSetting::current()->resolvedMt5BridgeToken();
        $provided = trim((string) ($request->header('X-MT5-Token') ?? $request->query('token', '')));

        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized MT5 Bridge token.'], 401);
        }

        return null;
    }

    /**
     * Polled every ~5s by MT5 Expert Advisor. Also receives live quotes and checks Telegram Approve/Reject clicks.
     */
    public function pending(Request $request)
    {
        if ($err = $this->authorizeBridge($request)) {
            return $err;
        }

        // Record heartbeat & trade allowed state from MT5 terminal
        $nowTs = time();
        Cache::put('mt5_bridge_heartbeat', $nowTs, 30);
        if ($request->has('trade_allowed')) {
            Cache::put('mt5_trade_allowed', (bool) $request->input('trade_allowed'), 30);
        }

        // Store live quotes pushed directly from Finex MT5
        $quotes = $request->input('quotes');
        if (is_array($quotes) && !empty($quotes)) {
            $formatted = Cache::get('mt5_live_quotes', []);
            if (!is_array($formatted)) {
                $formatted = [];
            }
            foreach ($quotes as $rawSym => $data) {
                $sym = strtoupper(trim((string) $rawSym));
                $pair = strlen($sym) === 6 ? substr($sym, 0, 3) . '/' . substr($sym, 3, 3) : (str_starts_with($sym, 'XAU') ? 'XAU/USD' : $sym);
                $price = is_array($data) ? (float) ($data['price'] ?? $data['bid'] ?? 0) : (float) $data;
                $bid = is_array($data) ? (float) ($data['bid'] ?? $price) : $price;
                $ask = is_array($data) ? (float) ($data['ask'] ?? $price) : $price;
                if ($price > 0) {
                    $formatted[$pair] = [
                        'pair' => $pair,
                        'price' => $price,
                        'bid' => $bid,
                        'ask' => $ask,
                        'source' => 'mt5_finex_live',
                        'timestamp' => $nowTs,
                    ];
                }
            }
            if (!empty($formatted)) {
                Cache::put('mt5_live_quotes', $formatted, 30);
            }
        }

        // Expire approved orders whose 15m window has passed (including legacy orders with null expires_at)
        Mt5Order::where('status', 'approved')
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('expires_at')
                         ->where('created_at', '<', now()->subMinutes(15));
                  });
            })
            ->update(['status' => 'expired']);

        $rawOrders = Mt5Order::where('status', 'approved')
            ->orderBy('approved_at', 'asc')
            ->take(5)
            ->get();

        $tradeAllowed = (bool) Cache::get('mt5_trade_allowed', true);

        // Jika tombol Algo Trading di MT5 mati, jangan dispatch order dulu dan ingatkan user
        if (!$tradeAllowed && $rawOrders->isNotEmpty()) {
            if (!Cache::has('mt5_algo_disabled_warned')) {
                Cache::put('mt5_algo_disabled_warned', true, 60);
                try {
                    $tg = app(TelegramService::class);
                    if ($tg->isConfigured()) {
                        $tg->sendMessage("⚠️ <b>PERINGATAN MT5:</b> Ada order disetujui, tapi tombol <b>Algo Trading</b> di terminal MT5 Anda sedang <b>MATI / NONAKTIF</b>. Tolong aktifkan tombol Algo Trading di MT5 agar order dapat dieksekusi!");
                    }
                } catch (\Throwable $e) {}
            }

            return response()->json([
                'ok' => true,
                'orders' => [],
                'warning' => 'MT5 trade_allowed is false; orders held pending Algo Trading activation.',
            ]);
        }

        $orders = $rawOrders->map(fn ($o) => [
            'id' => (string) $o->_id,
            'pair' => $o->pair,
            'symbol' => $o->mt5_symbol,
            'side' => $o->side,
            'lots' => (float) $o->lots,
            'entry_price' => (float) $o->entry_price,
            'sl' => (float) $o->stop_loss,
            'tp' => (float) $o->take_profit,
        ])->values();

        // KUNCI PENTING: Segera ubah status menjadi 'dispatched'
        // agar polling 5 detik berikutnya TIDAK PERNAH mengirim ulang order yang sama!
        foreach ($rawOrders as $raw) {
            $raw->status = 'dispatched';
            $raw->dispatched_at = now();
            $raw->save();
        }

        // Sinkronisasi posisi aktif yang dilaporkan langsung dari terminal MT5
        $reportedPositions = $request->input('positions');
        if (is_array($reportedPositions)) {
            $activeTickets = [];
            foreach ($reportedPositions as $p) {
                $t = trim((string) ($p['ticket'] ?? ''));
                if ($t !== '') {
                    $activeTickets[] = $t;
                }
            }
            Cache::put('mt5_active_positions', $reportedPositions, 30);

            // Periksa posisi PaperOrder ber-tiket MT5 yang statusnya masih 'open'
            // Jika tiketnya sudah tidak ada di terminal MT5, tandai bahwa order sudah ditutup
            $openMt5Orders = PaperOrder::where('status', 'open')
                ->whereNotNull('mt5_ticket')
                ->where('mt5_ticket', '!=', '')
                ->get();

            foreach ($openMt5Orders as $pOrder) {
                if (!in_array((string) $pOrder->mt5_ticket, $activeTickets, true)) {
                    $market = app(ForexMarketService::class);
                    $latestPx = $market->getLatestPrice($pOrder->pair, true);
                    $closePx = (float) ($latestPx['price'] ?? $pOrder->entry_price);

                    $meta = $market->getPairMeta($pOrder->pair);
                    $entry = (float) $pOrder->entry_price;
                    $diff = $pOrder->side === 'buy' ? ($closePx - $entry) : ($entry - $closePx);
                    $pips = round($diff / $meta['pip'], 1);
                    $pnlUsd = round($pips * (float) $pOrder->pip_value_usd, 2);

                    $pOrder->status = 'closed';
                    $pOrder->close_price = $closePx;
                    $pOrder->pnl_pips = $pips;
                    $pOrder->pnl_usd = $pnlUsd;
                    $pOrder->closed_at = now();
                    $pOrder->save();

                    try {
                        $tg = app(TelegramService::class);
                        if ($tg->isConfigured()) {
                            $sign = $pnlUsd >= 0 ? '+' : '';
                            $tg->sendMessage(
                                "🔒 <b>POSISI MT5 TELAH DITUTUP</b>\n"
                                . "Ticket: <code>#{$pOrder->mt5_ticket}</code>\n"
                                . "Pair: <b>{$pOrder->pair} (" . strtoupper($pOrder->side) . ")</b> | {$pOrder->lots} lot\n"
                                . "Entry: <code>{$entry}</code> → Close: <code>{$closePx}</code>\n"
                                . "PnL: <b>{$sign}{$pips} pips ({$sign}\${$pnlUsd})</b>"
                            );
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        $closeTickets = (array) Cache::pull('mt5_tickets_to_close', []);

        return response()->json([
            'ok' => true,
            'orders' => $orders,
            'close_tickets' => array_values($closeTickets),
        ]);
    }

    /**
     * Called by MT5 Expert Advisor after executing (or failing) an approved order.
     */
    public function report(Request $request, TelegramService $telegram)
    {
        if ($err = $this->authorizeBridge($request)) {
            return $err;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'status' => 'required|in:executed,failed',
            'ticket' => 'nullable|string|max:64',
            'executed_price' => 'nullable|numeric',
            'error_message' => 'nullable|string|max:500',
        ]);

        $order = Mt5Order::find($data['id']);
        if (!$order) {
            return response()->json(['ok' => false, 'error' => 'Order not found.'], 404);
        }

        $order->status = $data['status'];
        $order->mt5_ticket = $data['ticket'] ?? null;
        $order->executed_price = isset($data['executed_price']) ? (float) $data['executed_price'] : null;
        $order->error_message = $data['error_message'] ?? null;
        $order->executed_at = now();
        $order->save();

        // Sinkronisasi otomatis ke PaperOrder agar order muncul di dashboard web app
        $paperOrder = !empty($order->paper_order_id)
            ? PaperOrder::find($order->paper_order_id)
            : PaperOrder::where('mt5_order_id', (string) $order->_id)->first();

        $px = $order->executed_price ?? $order->entry_price;

        if ($data['status'] === 'executed') {
            if ($paperOrder) {
                $paperOrder->status = 'open';
                $paperOrder->entry_price = $px;
                $paperOrder->mt5_ticket = (string) $order->mt5_ticket;
                $paperOrder->executed_at = now();
                $paperOrder->save();
            } else {
                $market = app(ForexMarketService::class);
                $pipVal = max(0.1, $market->pipValuePerStandardLot($order->pair, $px));
                $paperOrder = PaperOrder::create([
                    'pair' => $order->pair,
                    'side' => $order->side,
                    'order_type' => 'market',
                    'lots' => (float) $order->lots,
                    'units' => (int) round($order->lots * 100000),
                    'pip_value_usd' => $pipVal,
                    'entry_price' => $px,
                    'stop_loss' => (float) $order->stop_loss,
                    'take_profit' => (float) $order->take_profit,
                    'max_loss_usd' => 80.0,
                    'reward_risk' => (float) ($order->expected_r ?? 1.5),
                    'risk_status' => 'PASS',
                    'paper' => false,
                    'status' => 'open',
                    'mt5_order_id' => (string) $order->_id,
                    'mt5_ticket' => (string) $order->mt5_ticket,
                    'executed_at' => now(),
                ]);
                $order->paper_order_id = (string) $paperOrder->_id;
                $order->save();
            }
        } elseif ($data['status'] === 'failed') {
            if ($paperOrder) {
                $paperOrder->status = 'rejected';
                $paperOrder->save();
            }
        }

        if ($telegram->isConfigured()) {
            if ($data['status'] === 'executed') {
                $px = $order->executed_price ?? $order->entry_price;
                $telegram->sendMessage(
                    "✅ <b>MT5 FINEX EXECUTED</b>\n"
                    . "Ticket: <code>#{$order->mt5_ticket}</code>\n"
                    . "Pair: <b>{$order->pair} (" . strtoupper($order->side) . ")</b> | {$order->lots} lot\n"
                    . "Harga Eksekusi: <code>{$px}</code>\n"
                    . "SL: <code>{$order->stop_loss}</code> | TP: <code>{$order->take_profit}</code>"
                );
            } else {
                $telegram->sendMessage(
                    "⚠️ <b>MT5 EKSEKUSI GAGAL — {$order->pair}</b>\n"
                    . "Alasan: <code>{$order->error_message}</code>"
                );
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Public / web status indicator endpoint to check if MT5 terminal is open and sending quotes.
     */
    public function status()
    {
        $heartbeat = Cache::get('mt5_bridge_heartbeat');
        $isConnected = $heartbeat && (time() - $heartbeat < 20);
        $tradeAllowed = (bool) Cache::get('mt5_trade_allowed', true);

        return response()->json([
            'ok' => true,
            'connected' => (bool) $isConnected,
            'trade_allowed' => $tradeAllowed,
            'last_seen_seconds_ago' => $heartbeat ? (time() - $heartbeat) : null,
            'quotes_count' => count((array) Cache::get('mt5_live_quotes', [])),
        ]);
    }
}

