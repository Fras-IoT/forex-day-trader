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
     * Polled every ~5s by MT5 Expert Advisor. Also checks Telegram Approve/Reject button clicks.
     */
    public function pending(Request $request, ForexAutoMonitorService $monitor)
    {
        if ($err = $this->authorizeBridge($request)) {
            return $err;
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
}

