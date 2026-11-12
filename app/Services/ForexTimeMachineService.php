<?php

namespace App\Services;

use App\Models\SimulationRun;
use App\Models\TradePlan;

class ForexTimeMachineService
{
    public function __construct(private readonly ForexMarketService $market)
    {
    }

    /**
     * Port of day-trader-skill/trader-timemachine/timemachine.py for 24/5 Forex:
     * 10,000 Monte Carlo session replays using the pair's own historical 5m bar ratios.
     */
    public function runSimulation(
        TradePlan $plan,
        int $replays = 10000,
        int $seed = 19,
        int $horizonBars = 72 // 6 hours of 5m bars
    ): SimulationRun {
        $computed = $this->computeSimulation(
            $plan->pair,
            $plan->side,
            (float) $plan->entry_price,
            (float) $plan->stop_loss,
            (float) $plan->take_profit,
            $replays,
            $seed,
            $horizonBars,
            true
        );

        $histStats = $this->runHistoricalRuleCheck($computed['sessions'], $plan, $computed['risk']);

        return SimulationRun::create(array_merge($computed['record'], [
            'trade_plan_id' => (string) $plan->_id,
            'history_stats' => $histStats,
            'ai_verdict' => null,
        ]));
    }

    /**
     * Fast in-memory Monte Carlo evaluation for automated scanner alerts (no DB write).
     *
     * @return array{target_first_pct: float, stop_first_pct: float, expected_r: float, verdict: string}
     */
    public function quickEvaluate(
        string $pair,
        string $side,
        float $entry,
        float $stop,
        float $target,
        int $replays = 2500,
        int $seed = 19,
        bool $preferSpot = true
    ): array {
        $computed = $this->computeSimulation($pair, $side, $entry, $stop, $target, $replays, $seed, 72, $preferSpot);
        $rec = $computed['record'];

        return [
            'target_first_pct' => $rec['target_first_pct'],
            'stop_first_pct' => $rec['stop_first_pct'],
            'expected_r' => $rec['expected_r'],
            'verdict' => $rec['verdict'],
        ];
    }

    private function computeSimulation(
        string $pair,
        string $side,
        float $entry,
        float $stop,
        float $target,
        int $replays,
        int $seed,
        int $horizonBars,
        bool $preferSpot = false
    ): array {
        $meta = $this->market->getPairMeta($pair);
        $data = $this->market->fetchBars($pair, '5m', '30d', $preferSpot);
        $bars = $data['bars'];

        $isBuy = $side === 'buy';
        $risk = abs($entry - $stop);
        $rewardR = $risk > 0 ? abs($target - $entry) / $risk : 1.0;

        // Build pool of bar ratios relative to previous close
        $pool = [];
        $sessions = array_chunk($bars, $horizonBars);
        $slotPool = array_fill(0, $horizonBars, []);

        foreach ($sessions as $sess) {
            if (count($sess) < 10) {
                continue;
            }
            for ($i = 1; $i < count($sess); $i++) {
                $prevC = (float) $sess[$i - 1]['c'];
                if ($prevC <= 0) {
                    continue;
                }
                $ratio = [
                    'o' => $sess[$i]['o'] / $prevC,
                    'h' => $sess[$i]['h'] / $prevC,
                    'l' => $sess[$i]['l'] / $prevC,
                    'c' => $sess[$i]['c'] / $prevC,
                ];
                $slotPool[$i][] = $ratio;
                $pool[] = $ratio;
            }
        }

        if (empty($pool)) {
            $pool[] = ['o' => 1.0, 'h' => 1.0004, 'l' => 0.9996, 'c' => 1.0];
        }

        mt_srand($seed);
        $targetFirst = 0;
        $stopFirst = 0;
        $neither = 0;
        $green = 0;
        $rValues = [];
        $endPrices = [];
        $fanSampleCount = min(1500, $replays);
        $fanMatrix = array_fill(0, $horizonBars, []);
        $maxSamplePaths = 45;
        $sampleStride = max(1, (int) floor($replays / $maxSamplePaths));
        $samplePaths = [];

        $window = 3;
        for ($r = 0; $r < $replays; $r++) {
            $cur = $entry;
            $outcome = 'neither';
            $rOut = 0.0;
            $recordFan = $r < $fanSampleCount;
            $recordPath = ($r % $sampleStride === 0) && count($samplePaths) < $maxSamplePaths;
            $pathPts = $recordPath ? [round($entry, $meta['decimals'])] : [];

            for ($step = 1; $step < $horizonBars; $step++) {
                if ($outcome !== 'neither') {
                    if ($recordFan) {
                        $fanMatrix[$step][] = $cur;
                    }
                    if ($recordPath && $step % 3 === 0) {
                        $pathPts[] = round($cur, $meta['decimals']);
                    }
                    continue;
                }

                $j = max(1, min($horizonBars - 1, $step + mt_rand(-$window, $window)));
                $candidates = !empty($slotPool[$j]) ? $slotPool[$j] : $pool;
                $pick = $candidates[mt_rand(0, count($candidates) - 1)];

                $barH = $cur * $pick['h'];
                $barL = $cur * $pick['l'];
                $barC = $cur * $pick['c'];

                // Conservative rule: check stop BEFORE target in every bar
                if ($isBuy) {
                    if ($barL <= $stop) {
                        $outcome = 'stop';
                        $rOut = -1.0;
                        $cur = $stop;
                    } elseif ($barH >= $target) {
                        $outcome = 'target';
                        $rOut = $rewardR;
                        $cur = $target;
                    } else {
                        $cur = $barC;
                    }
                } else {
                    if ($barH >= $stop) {
                        $outcome = 'stop';
                        $rOut = -1.0;
                        $cur = $stop;
                    } elseif ($barL <= $target) {
                        $outcome = 'target';
                        $rOut = $rewardR;
                        $cur = $target;
                    } else {
                        $cur = $barC;
                    }
                }

                if ($recordFan) {
                    $fanMatrix[$step][] = $cur;
                }
                if ($recordPath && $step % 3 === 0) {
                    $pathPts[] = round($cur, $meta['decimals']);
                }
            }

            if ($recordPath) {
                $samplePaths[] = [
                    'outcome' => $outcome,
                    'points' => $pathPts,
                ];
            }

            if ($outcome === 'target') {
                $targetFirst++;
                $green++;
            } elseif ($outcome === 'stop') {
                $stopFirst++;
            } else {
                $neither++;
                $rOut = $risk > 0 ? (($isBuy ? ($cur - $entry) : ($entry - $cur)) / $risk) : 0.0;
                if (($isBuy && $cur > $entry) || (!$isBuy && $cur < $entry)) {
                    $green++;
                }
            }

            $rValues[] = $rOut;
            $endPrices[] = $cur;
        }

        sort($endPrices);
        $pct = function (array $sorted, float $q) use ($meta) {
            $idx = min(count($sorted) - 1, max(0, (int) floor($q * count($sorted))));

            return round($sorted[$idx], $meta['decimals']);
        };

        $targetPct = round(($targetFirst / $replays) * 100, 1);
        $stopPct = round(($stopFirst / $replays) * 100, 1);
        $neitherPct = round(($neither / $replays) * 100, 1);
        $greenPct = round(($green / $replays) * 100, 1);
        $expectedR = round(array_sum($rValues) / $replays, 3);

        $decided = $targetFirst + $stopFirst;
        $winShare = $decided > 0 ? ($targetFirst / $decided) : 0.5;
        $breakevenShare = 1.0 / (1.0 + max(0.1, $rewardR));
        $breakevenPct = round($breakevenShare * 100, 1);
        $gap = $winShare - $breakevenShare;

        if (abs($gap) < 0.05) {
            $verdict = abs($rewardR - 1.0) < 0.05 ? 'Close to a coin flip' : 'Close to break-even';
        } elseif ($gap >= 0.15) {
            $verdict = 'Strongly toward the target';
        } elseif ($gap >= 0.05) {
            $verdict = 'Leans toward the target';
        } elseif ($gap <= -0.15) {
            $verdict = 'Strongly toward the stop';
        } else {
            $verdict = 'Leans toward the stop';
        }

        // Build fan series (every 3 bars for clean SVG plotting)
        $fanSeries = [];
        $fanMatrix[0] = [$entry];
        for ($s = 0; $s < $horizonBars; $s += 3) {
            $col = $fanMatrix[$s] ?? [$entry];
            if (empty($col)) {
                $col = [$entry];
            }
            sort($col);
            $fanSeries[] = [
                'bar' => $s,
                'p05' => $pct($col, 0.05),
                'p25' => $pct($col, 0.25),
                'p50' => $pct($col, 0.50),
                'p75' => $pct($col, 0.75),
                'p95' => $pct($col, 0.95),
            ];
        }

        return [
            'sessions' => $sessions,
            'risk' => $risk,
            'record' => [
                'pair' => $pair,
                'side' => $side,
                'entry_price' => $entry,
                'stop_loss' => $stop,
                'take_profit' => $target,
                'replays' => $replays,
                'seed' => $seed,
                'sessions_used' => count($sessions),
                'target_first_pct' => $targetPct,
                'stop_first_pct' => $stopPct,
                'neither_pct' => $neitherPct,
                'green_pct' => $greenPct,
                'expected_r' => $expectedR,
                'breakeven_win_pct' => $breakevenPct,
                'verdict' => $verdict,
                'percentiles' => [
                    'p05' => $pct($endPrices, 0.05),
                    'p25' => $pct($endPrices, 0.25),
                    'p50' => $pct($endPrices, 0.50),
                    'p75' => $pct($endPrices, 0.75),
                    'p95' => $pct($endPrices, 0.95),
                ],
                'fan_series' => $fanSeries,
                'sample_paths' => $samplePaths,
                'histogram' => $this->buildHistogram($endPrices, 16, $meta['decimals']),
            ],
        ];
    }

    private function runHistoricalRuleCheck(array $sessions, TradePlan $plan, float $risk): array
    {
        $isBuy = $plan->side === 'buy';
        $rewardR = (float) $plan->reward_risk;
        $fires = 0;
        $wins = 0;
        $losses = 0;
        $neither = 0;

        foreach ($sessions as $sess) {
            if (count($sess) < 16) {
                continue;
            }
            // Use first 6 bars as session range, look for breakout
            $rangeBars = array_slice($sess, 0, 6);
            $rHigh = max(array_column($rangeBars, 'h'));
            $rLow = min(array_column($rangeBars, 'l'));
            $entryBarIdx = null;
            $entryPx = null;

            for ($i = 6; $i < count($sess) - 4; $i++) {
                if ($isBuy && $sess[$i]['c'] > $rHigh) {
                    $entryBarIdx = $i;
                    $entryPx = (float) $sess[$i]['c'];
                    break;
                }
                if (!$isBuy && $sess[$i]['c'] < $rLow) {
                    $entryBarIdx = $i;
                    $entryPx = (float) $sess[$i]['c'];
                    break;
                }
            }

            if ($entryBarIdx === null || $risk <= 0) {
                continue;
            }
            $fires++;
            $sl = $isBuy ? ($entryPx - $risk) : ($entryPx + $risk);
            $tp = $isBuy ? ($entryPx + $risk * $rewardR) : ($entryPx - $risk * $rewardR);
            $hit = 'neither';

            for ($j = $entryBarIdx + 1; $j < count($sess); $j++) {
                if ($isBuy) {
                    if ($sess[$j]['l'] <= $sl) {
                        $hit = 'stop';
                        break;
                    }
                    if ($sess[$j]['h'] >= $tp) {
                        $hit = 'target';
                        break;
                    }
                } else {
                    if ($sess[$j]['h'] >= $sl) {
                        $hit = 'stop';
                        break;
                    }
                    if ($sess[$j]['l'] <= $tp) {
                        $hit = 'target';
                        break;
                    }
                }
            }

            if ($hit === 'target') {
                $wins++;
            } elseif ($hit === 'stop') {
                $losses++;
            } else {
                $neither++;
            }
        }

        return [
            'fires' => $fires,
            'wins' => $wins,
            'losses' => $losses,
            'neither' => $neither,
        ];
    }

    private function buildHistogram(array $sorted, int $bins, int $decimals): array
    {
        if (empty($sorted)) {
            return [];
        }
        $min = $sorted[0];
        $max = end($sorted);
        if ($max <= $min) {
            return [['label' => (string) round($min, $decimals), 'count' => count($sorted)]];
        }
        $step = ($max - $min) / $bins;
        $counts = array_fill(0, $bins, 0);
        foreach ($sorted as $v) {
            $idx = min($bins - 1, max(0, (int) floor(($v - $min) / $step)));
            $counts[$idx]++;
        }
        $out = [];
        for ($i = 0; $i < $bins; $i++) {
            $out[] = [
                'label' => (string) round($min + ($i * $step), $decimals),
                'count' => $counts[$i],
            ];
        }

        return $out;
    }
}

