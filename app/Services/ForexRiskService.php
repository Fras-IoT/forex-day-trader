<?php

namespace App\Services;

use App\Models\PaperOrder;
use App\Models\TradePlan;
use App\Models\TraderSetting;
use Carbon\Carbon;

class ForexRiskService
{
    public function __construct(private readonly ForexMarketService $market)
    {
    }

    /**
     * Size a Forex position in lots (0.01 step) and run Pre-Trade Risk Checks (PASS / BLOCK).
     */
    public function sizeAndCheck(TradePlan $plan, ?float $customMaxLoss = null): array
    {
        $settings = TraderSetting::current();
        $balance = (float) $settings->account_balance;
        $maxLossUsd = $customMaxLoss ?? (float) $settings->max_loss_per_trade_usd;
        $maxDailyLoss = (float) $settings->max_daily_loss_usd;
        $maxTradesPerDay = (int) $settings->max_trades_per_day;
        $minRR = (float) $settings->min_reward_risk;
        $leverage = max(1, (int) $settings->leverage);
        $maxPosPct = (float) $settings->max_position_pct;

        $entry = (float) $plan->entry_price;
        $stop = (float) $plan->stop_loss;
        $target = (float) $plan->take_profit;
        $riskPips = (float) $plan->risk_pips;
        $rr = (float) $plan->reward_risk;

        $pipValStd = $this->market->pipValuePerStandardLot($plan->pair, $entry);
        $rawLots = ($riskPips > 0 && $pipValStd > 0) ? ($maxLossUsd / ($riskPips * $pipValStd)) : 0.0;

        // Margin / position cap
        $maxNotional = ($balance * ($maxPosPct / 100.0)) * $leverage;
        $maxLotsByCap = $maxNotional / 100000.0;
        $lots = floor(min($rawLots, $maxLotsByCap) * 100) / 100;
        $lots = max(0.0, $lots);

        $units = (int) round($lots * 100000);
        $actualMaxLoss = round($lots * $riskPips * $pipValStd, 2);

        // Run Risk Checks
        $checks = [];
        $blocked = false;

        $addCheck = function (string $name, bool $pass, string $detail) use (&$checks, &$blocked) {
            $checks[] = ['rule' => $name, 'status' => $pass ? 'PASS' : 'BLOCK', 'detail' => $detail];
            if (!$pass) {
                $blocked = true;
            }
        };

        $stopValid = $plan->side === 'buy' ? ($stop < $entry && $target > $entry) : ($stop > $entry && $target < $entry);
        $addCheck('Stop & Target Side', $stopValid, $stopValid ? 'SL/TP berada di sisi yang benar.' : 'SL/TP berada di sisi yang salah terhadap entry.');

        $addCheck('Min Risk Distance', $riskPips >= 3.0, "Risk {$riskPips} pips (minimal 3.0 pips untuk menghindari spread noise).");
        $addCheck('Lot Size Valid', $lots >= 0.01, "Ukuran posisi: {$lots} lot ({$units} units).");
        $addCheck('Min Reward:Risk', $rr >= $minRR, "R:R {$rr}x vs batas minimal {$minRR}x.");
        $addCheck('Max Loss / Trade', $actualMaxLoss <= $maxLossUsd + 0.01, "Max loss \${$actualMaxLoss} vs batas \${$maxLossUsd}.");

        $todayStart = Carbon::today('UTC');
        $todayOrders = PaperOrder::where('created_at', '>=', $todayStart)
            ->whereIn('status', ['open', 'closed'])
            ->get();

        $tradesToday = $todayOrders->count();
        $addCheck('Daily Trade Limit', $tradesToday < $maxTradesPerDay, "{$tradesToday} / {$maxTradesPerDay} trade hari ini.");

        $committedLoss = $todayOrders->sum(fn ($o) => (float) ($o->max_loss_usd ?? 0));
        $addCheck('Daily Loss Limit', ($committedLoss + $actualMaxLoss) <= $maxDailyLoss, 'Total risiko harian $' . round($committedLoss + $actualMaxLoss, 2) . " vs batas \${$maxDailyLoss}.");

        $blockedPairs = array_map('strtoupper', (array) ($settings->blocked_pairs ?? []));
        $isPairBlocked = in_array(strtoupper($plan->pair), $blockedPairs, true);
        $addCheck('Pair Allowed', !$isPairBlocked, $isPairBlocked ? "{$plan->pair} ada di daftar blokir." : "{$plan->pair} diizinkan.");

        $dup = $todayOrders->where('pair', $plan->pair)->where('side', $plan->side)->where('status', 'open')->count() > 0;
        $addCheck('No Duplicate Open', !$dup, $dup ? "Sudah ada posisi {$plan->side} terbuka di {$plan->pair}." : 'Tidak ada duplikasi posisi terbuka.');

        $payloadForHash = json_encode([
            'plan_id' => (string) $plan->_id,
            'pair' => $plan->pair,
            'side' => $plan->side,
            'lots' => $lots,
            'entry' => $entry,
            'stop' => $stop,
            'target' => $target,
            'date' => gmdate('Y-m-d'),
        ]);

        return [
            'lots' => $lots,
            'units' => $units,
            'pip_value_usd' => round($lots * $pipValStd, 2),
            'max_loss_usd' => $actualMaxLoss,
            'reward_risk' => $rr,
            'risk_status' => $blocked ? 'BLOCK' : 'PASS',
            'risk_checks' => $checks,
            'fingerprint' => hash('sha256', (string) $payloadForHash),
            'paper' => (bool) $settings->paper_mode,
        ];
    }
}

