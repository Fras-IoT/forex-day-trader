<?php

namespace App\Services;

use App\Models\Mt5Order;
use App\Models\PaperOrder;
use App\Models\PriceAlert;
use App\Models\TraderSetting;
use Illuminate\Support\Facades\Cache;

class ForexAutoMonitorService
{
    public function __construct(
        private readonly ForexMarketService $market,
        private readonly ForexScannerService $scanner,
        private readonly ForexTimeMachineService $timeMachine,
        private readonly TelegramService $telegram,
    ) {
    }

    /**
     * Run both open-position SL/TP monitoring and combined Scanner + Monte Carlo (>55%) alerts.
     *
     * @return array{positions_checked: int, positions_hit: int, scan_hits_evaluated: int, alerts_sent: int, errors: array<int, string>}
     */
    public function run(bool $forceScan = false): array
    {
        $settings = TraderSetting::current();
        $summary = [
            'positions_checked' => 0,
            'positions_hit' => 0,
            'scan_hits_evaluated' => 0,
            'alerts_sent' => 0,
            'errors' => [],
        ];

        $this->pollTelegramCallbacks();

        $sessions = $this->market->getActiveSessions();
        if (!$forceScan && !($sessions['market_open'] ?? true)) {
            return $summary;
        }

        $this->checkOpenPositions($settings, $summary);

        if ($forceScan || ($settings->auto_scan_enabled ?? true)) {
            $this->scanHighProbabilitySetups($settings, $summary);
        }

        return $summary;
    }

    private function checkOpenPositions(TraderSetting $settings, array &$summary): void
    {
        $openOrders = PaperOrder::where('status', 'open')->get();
        $summary['positions_checked'] = $openOrders->count();

        foreach ($openOrders as $order) {
            try {
                $latest = $this->market->getLatestPrice($order->pair, true);
                $price = (float) ($latest['price'] ?? 0);
                if ($price <= 0) {
                    continue;
                }

                $isBuy = $order->side === 'buy';
                $sl = (float) $order->stop_loss;
                $tp = (float) $order->take_profit;

                $hitSl = $isBuy ? ($price <= $sl) : ($price >= $sl);
                $hitTp = $isBuy ? ($price >= $tp) : ($price <= $tp);

                if (!$hitSl && !$hitTp) {
                    // Auto-Breakeven (+1.0R): move SL to entry once profit reaches 1R
                    $entry = (float) $order->entry_price;
                    $initialRisk = abs($entry - $sl);
                    if (!($order->breakeven_moved ?? false) && $initialRisk > 0) {
                        $reached1R = $isBuy
                            ? ($price >= $entry + $initialRisk)
                            : ($price <= $entry - $initialRisk);

                        if ($reached1R) {
                            $order->stop_loss = $entry;
                            $order->breakeven_moved = true;
                            $order->save();

                            if ($this->telegram->isConfigured()) {
                                $msg = "🛡️ <b>BREAKEVEN (+1.0R) — {$order->pair} (" . strtoupper($order->side) . ")</b>\n"
                                    . "Live Price: <code>{$price}</code>\n"
                                    . "Stop Loss otomatis digeser ke Entry: <code>{$entry}</code> (Risk-Free)";
                                $sent = $this->telegram->sendMessage($msg);
                                if ($sent['ok']) {
                                    $summary['alerts_sent']++;
                                }
                            }
                        }
                    }
                    continue;
                }

                $summary['positions_hit']++;
                $hitType = $hitTp ? 'TP' : 'SL';
                $closePrice = $hitTp ? $tp : $sl;

                $meta = $this->market->getPairMeta($order->pair);
                $entry = (float) $order->entry_price;
                $diff = $isBuy ? ($closePrice - $entry) : ($entry - $closePrice);
                $pips = round($diff / $meta['pip'], 1);
                $pnlUsd = round($pips * (float) $order->pip_value_usd, 2);

                $autoClosed = false;
                if ($settings->auto_close_on_hit ?? true) {
                    $order->status = 'closed';
                    $order->close_price = $closePrice;
                    $order->pnl_pips = $pips;
                    $order->pnl_usd = $pnlUsd;
                    $order->closed_at = now();
                    $order->save();
                    $autoClosed = true;

                    PriceAlert::where('trade_plan_id', $order->trade_plan_id)
                        ->where('status', 'active')
                        ->update(['status' => 'triggered', 'triggered_at' => now()]);
                }

                $dedupeKey = "tg_order_hit_{$order->_id}_{$hitType}";
                if (!$autoClosed && !Cache::add($dedupeKey, true, now()->addHours(6))) {
                    continue;
                }

                if ($this->telegram->isConfigured()) {
                    $icon = $hitTp ? '🎯' : '🛑';
                    $statusTxt = $autoClosed ? 'Auto-Closed' : 'Alert Only (Masih Open)';
                    $sign = $pnlUsd >= 0 ? '+' : '';
                    $msg = "{$icon} <b>{$hitType} HIT — {$order->pair} (" . strtoupper($order->side) . ")</b>\n"
                        . "Lot: <code>{$order->lots}</code> | Status: <b>{$statusTxt}</b>\n"
                        . "Entry: <code>{$entry}</code> → Close: <code>{$closePrice}</code>\n"
                        . "Live Price: <code>{$price}</code>\n"
                        . "PnL: <b>{$sign}{$pips} pips ({$sign}\${$pnlUsd})</b>";

                    $sent = $this->telegram->sendMessage($msg);
                    if ($sent['ok']) {
                        $summary['alerts_sent']++;
                    } elseif ($sent['error']) {
                        $summary['errors'][] = $sent['error'];
                    }
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "Order {$order->pair}: " . $e->getMessage();
            }
        }
    }

    private function scanHighProbabilitySetups(TraderSetting $settings, array &$summary): void
    {
        $minScore = (int) ($settings->auto_scan_min_score ?? 70);
        $minProb = (float) ($settings->auto_scan_min_prob ?? 55);
        $rr = max(1.0, (float) ($settings->min_reward_risk ?? 1.5));

        $scan = $this->scanner->scanAll('intraday');

        foreach (($scan->hits ?? []) as $hit) {
            if (($hit['score'] ?? 0) <= $minScore) {
                continue;
            }

            $pair = $hit['pair'];
            $side = $hit['side'];
            $cooldownKey = "tg_scan_alert_{$pair}_{$side}";
            if (Cache::has($cooldownKey)) {
                continue;
            }

            $meta = $this->market->getPairMeta($pair);
            $decimals = (int) ($hit['decimals'] ?? $meta['decimals']);

            // Fetch Twelve Data Spot price only for pairs with score > 70 (and not in cooldown)
            $spot = $this->market->getLatestPrice($pair, true);
            $entry = (float) ($spot['price'] ?? $hit['price']);
            $priceSource = $spot['source'] ?? 'yahoo_live';

            $atrPrice = max($meta['pip'] * 5, ((float) ($hit['atr_pips'] ?? 10)) * $meta['pip']);
            $minAtrRisk = $atrPrice * 2.5;
            $maxAtrRisk = $atrPrice * 6.0;
            $rangeEdge = $side === 'buy'
                ? (float) ($hit['range_low'] ?? ($entry - $minAtrRisk))
                : (float) ($hit['range_high'] ?? ($entry + $minAtrRisk));
            $structuralDist = abs($entry - $rangeEdge) + ($atrPrice * 0.5);
            $risk = max($minAtrRisk, min($maxAtrRisk, $structuralDist));

            $stop = $side === 'buy'
                ? round($entry - $risk, $decimals)
                : round($entry + $risk, $decimals);
            $target = $side === 'buy'
                ? round($entry + ($risk * $rr), $decimals)
                : round($entry - ($risk * $rr), $decimals);

            $summary['scan_hits_evaluated']++;
            $mc = $this->timeMachine->quickEvaluate($pair, $side, $entry, $stop, $target, 2500, 19, true);

            $hasPositiveEdge = ($mc['expected_r'] >= 0.15)
                && ($mc['target_first_pct'] >= ($rr >= 2.0 ? 45.0 : 50.0));
            $meetsHighProb = $mc['target_first_pct'] > $minProb;

            if (!$hasPositiveEdge && !$meetsHighProb) {
                continue;
            }

            if ($this->telegram->isConfigured()) {
                $riskPips = max(1.0, $this->market->toPips($pair, $risk));
                $pipValueStd = max(0.1, $this->market->pipValuePerStandardLot($pair, $entry));
                $maxLossUsd = max(1.0, (float) ($settings->max_loss_per_trade_usd ?? 100));
                $lots = max(0.01, min(10.0, round($maxLossUsd / ($riskPips * $pipValueStd), 2)));
                $mt5Symbol = str_replace('/', '', $pair);

                $mt5Order = Mt5Order::create([
                    'pair' => $pair,
                    'mt5_symbol' => $mt5Symbol,
                    'side' => $side,
                    'lots' => $lots,
                    'entry_price' => $entry,
                    'stop_loss' => $stop,
                    'take_profit' => $target,
                    'setup' => $hit['setup'],
                    'score' => (int) $hit['score'],
                    'expected_r' => (float) $mc['expected_r'],
                    'target_first_pct' => (float) $mc['target_first_pct'],
                    'status' => 'awaiting_approval',
                    'expires_at' => now()->addMinutes(15),
                ]);

                $kzTag = !empty($hit['killzone']) ? ' | Killzone ✓' : '';
                $msg = "🚨 <b>PELUANG ENTRY BER-EDGE — {$pair} (" . strtoupper($side) . ")</b>\n"
                    . "Setup: <b>{$hit['setup']}</b>\n"
                    . "Scanner Score: <b>{$hit['score']}/99</b> | Trend: {$hit['trend_bias']}{$kzTag}\n"
                    . "Monte Carlo: <b>{$mc['target_first_pct']}%</b> (Expected: <b>{$mc['expected_r']}R</b>)\n"
                    . "Entry: <code>{$entry}</code> | SL: <code>{$stop}</code> | TP: <code>{$target}</code>\n"
                    . "Ukuran Lot: <b>{$lots} lot</b> (Max Risk \${$maxLossUsd})\n"
                    . "<i>Sesi: {$scan->session} | Feed: {$priceSource} | Berlaku 15 menit</i>";

                $buttons = [[
                    ['text' => "✅ SETUJUI ENTRY ({$lots} lot)", 'callback_data' => "mt5_approve:{$mt5Order->_id}"],
                    ['text' => '❌ TOLAK', 'callback_data' => "mt5_reject:{$mt5Order->_id}"],
                ]];

                $sent = $this->telegram->sendMessage($msg, $buttons);
                if ($sent['ok']) {
                    if (!empty($sent['message_id'])) {
                        $mt5Order->telegram_message_id = $sent['message_id'];
                        $mt5Order->save();
                    }
                    Cache::put($cooldownKey, true, now()->addMinutes(60));
                    $summary['alerts_sent']++;
                } elseif ($sent['error']) {
                    $summary['errors'][] = $sent['error'];
                }
            }
        }
    }

    /**
     * Poll Telegram callback_query updates so Approve/Reject buttons work over plain HTTP without HTTPS webhook.
     */
    public function pollTelegramCallbacks(): int
    {
        if (!$this->telegram->isConfigured()) {
            return 0;
        }

        $allowedChatId = TraderSetting::current()->resolvedTelegramChatId();
        $lastUpdateId = (int) Cache::get('tg_last_update_id', 0);
        $updates = $this->telegram->getUpdates($lastUpdateId > 0 ? $lastUpdateId + 1 : 0);
        $processed = 0;

        foreach ($updates as $upd) {
            $updateId = (int) ($upd['update_id'] ?? 0);
            if ($updateId > $lastUpdateId) {
                $lastUpdateId = $updateId;
                Cache::put('tg_last_update_id', $lastUpdateId, now()->addDays(7));
            }

            $cb = $upd['callback_query'] ?? null;
            if (!$cb) {
                continue;
            }

            $cbId = (string) ($cb['id'] ?? '');
            $fromChatId = (string) ($cb['message']['chat']['id'] ?? $cb['from']['id'] ?? '');
            $data = (string) ($cb['data'] ?? '');

            if ($fromChatId !== $allowedChatId) {
                $this->telegram->answerCallbackQuery($cbId, 'Akses ditolak.');
                continue;
            }

            if (!str_contains($data, ':')) {
                continue;
            }

            [$action, $orderId] = explode(':', $data, 2);
            $order = Mt5Order::find($orderId);
            if (!$order) {
                $this->telegram->answerCallbackQuery($cbId, 'Order tidak ditemukan.');
                continue;
            }

            if ($order->status !== 'awaiting_approval') {
                $this->telegram->answerCallbackQuery($cbId, "Order sudah berstatus: {$order->status}");
                continue;
            }

            if ($action === 'mt5_approve') {
                if ($order->expires_at && $order->expires_at->isPast()) {
                    $order->status = 'expired';
                    $order->save();
                    $this->telegram->answerCallbackQuery($cbId, 'Sinyal sudah kedaluwarsa (>15 menit).');
                    $this->telegram->sendMessage("⏰ <b>Kedaluwarsa:</b> Sinyal {$order->pair} (" . strtoupper($order->side) . ") melewati batas 15 menit dan dibatalkan.");
                    continue;
                }

                $order->status = 'approved';
                $order->approved_at = now();
                $order->save();
                $processed++;

                $this->telegram->answerCallbackQuery($cbId, 'Disetujui! Mengirim ke MT5...');
                $this->telegram->sendMessage("⏳ <b>DISETUJUI:</b> {$order->pair} " . strtoupper($order->side) . " ({$order->lots} lot)\nMenunggu EA MT5 Finex mengeksekusi...");
            } elseif ($action === 'mt5_reject') {
                $order->status = 'rejected';
                $order->save();
                $processed++;

                $this->telegram->answerCallbackQuery($cbId, 'Order ditolak.');
                $this->telegram->sendMessage("❌ <b>DITOLAK:</b> Setup {$order->pair} (" . strtoupper($order->side) . ") dibatalkan.");
            }
        }

        return $processed;
    }
}

