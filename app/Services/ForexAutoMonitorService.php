<?php

namespace App\Services;

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
            $risk = $atrPrice * 1.5;

            $stop = $side === 'buy'
                ? round($entry - $risk, $decimals)
                : round($entry + $risk, $decimals);
            $target = $side === 'buy'
                ? round($entry + ($risk * $rr), $decimals)
                : round($entry - ($risk * $rr), $decimals);

            $summary['scan_hits_evaluated']++;
            $mc = $this->timeMachine->quickEvaluate($pair, $side, $entry, $stop, $target, 2500, 19, true);

            if ($mc['target_first_pct'] <= $minProb) {
                continue;
            }

            if ($this->telegram->isConfigured()) {
                $msg = "🚨 <b>PELUANG ENTRY &gt; {$minProb}% — {$pair} (" . strtoupper($side) . ")</b>\n"
                    . "Setup: <b>{$hit['setup']}</b>\n"
                    . "Scanner Score: <b>{$hit['score']}/99</b> | Trend: {$hit['trend_bias']}\n"
                    . "Monte Carlo Target First: <b>{$mc['target_first_pct']}%</b> (Expected: {$mc['expected_r']}R)\n"
                    . "Entry: <code>{$entry}</code> | SL: <code>{$stop}</code> | TP: <code>{$target}</code>\n"
                    . "<i>Sesi: {$scan->session} | Feed: {$priceSource}</i>";

                $sent = $this->telegram->sendMessage($msg);
                if ($sent['ok']) {
                    Cache::put($cooldownKey, true, now()->addMinutes(60));
                    $summary['alerts_sent']++;
                } elseif ($sent['error']) {
                    $summary['errors'][] = $sent['error'];
                }
            }
        }
    }
}

