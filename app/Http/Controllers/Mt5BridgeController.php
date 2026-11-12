<?php

namespace App\Http\Controllers;

use App\Models\Mt5Order;
use App\Models\TraderSetting;
use App\Services\ForexAutoMonitorService;
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
    public function pending(Request $request, ForexAutoMonitorService $monitor)
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

        if (Cache::add('tg_poll_lock', true, 3)) {
            $monitor->pollTelegramCallbacks();
        }

        // Expire approved orders whose 15m window has passed
        Mt5Order::where('status', 'approved')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $orders = Mt5Order::where('status', 'approved')
            ->orderBy('approved_at', 'asc')
            ->take(5)
            ->get()
            ->map(fn ($o) => [
                'id' => (string) $o->_id,
                'pair' => $o->pair,
                'symbol' => $o->mt5_symbol,
                'side' => $o->side,
                'lots' => (float) $o->lots,
                'entry_price' => (float) $o->entry_price,
                'sl' => (float) $o->stop_loss,
                'tp' => (float) $o->take_profit,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'orders' => $orders,
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

