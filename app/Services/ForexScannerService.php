<?php

namespace App\Services;

use App\Models\ScanResult;

class ForexScannerService
{
    public function __construct(private readonly ForexMarketService $market)
    {
    }

    public function scanAll(string $mode = 'intraday'): ScanResult
    {
        $sessions = $this->market->getActiveSessions();
        $activeSessionName = $sessions['london_ny_overlap']
            ? 'London/NY Overlap'
            : ($sessions['sessions']['london']['active'] ? 'London' : ($sessions['sessions']['new_york']['active'] ? 'New York' : 'Tokyo/Sydney'));

        $hits = [];
        $sources = [];

        foreach (array_keys(ForexMarketService::PAIRS) as $pair) {
            $meta = $this->market->getPairMeta($pair);
            $daily = $this->market->fetchBars($pair, '1d', '6mo');
            $intraday = $this->market->fetchBars($pair, '5m', '5d');

            $sources[$daily['source']] = true;
            $sources[$intraday['source']] = true;

            $dBars = $daily['bars'];
            $mBars = $intraday['bars'];
            if (count($dBars) < 25 || count($mBars) < 40) {
                continue;
            }

            $lastM = end($mBars);
            $price = round((float) $lastM['c'], $meta['decimals']);
            $prevDayClose = (float) $dBars[count($dBars) - 2]['c'];
            $changePct = $prevDayClose > 0 ? round((($price - $prevDayClose) / $prevDayClose) * 100, 3) : 0.0;

            $rsi = $this->computeWilderRsi($dBars, 14);
            $sma20 = $this->computeSma($dBars, 20);
            $ema50 = $this->computeEma($dBars, 50);
            $ema200 = $this->computeEma($dBars, 200);
            $atr = $this->computeAtr($mBars, 14);
            $atrPips = max(1.0, $this->market->toPips($pair, $atr));

            // Macro Trend Bias from EMA 50 / EMA 200
            $trendBias = 'Neutral';
            if ($ema50 !== null && $ema200 !== null) {
                if ($price > $ema50 && $ema50 > $ema200) {
                    $trendBias = 'Bullish';
                } elseif ($price < $ema50 && $ema50 < $ema200) {
                    $trendBias = 'Bearish';
                }
            } elseif ($ema50 !== null) {
                $trendBias = $price >= $ema50 ? 'Bullish' : 'Bearish';
            }

            // True Asian Session Range (00:00 - 07:00 UTC) from latest session day
            [$rangeHigh, $rangeLow, $rangeType] = $this->computeAsianSessionRange($mBars);

            $setups = [];
            $side = 'buy';

            if ($price > $rangeHigh) {
                $setups[] = "{$rangeType} Breakout (Long)";
                $side = 'buy';
            } elseif ($price < $rangeLow) {
                $setups[] = "{$rangeType} Breakdown (Short)";
                $side = 'sell';
            }

            // 20-day high/low breakout
            $last20 = array_slice($dBars, -21, 20);
            $hi20 = max(array_column($last20, 'h'));
            $lo20 = min(array_column($last20, 'l'));
            if ($price > $hi20) {
                $setups[] = '20D Breakout';
                $side = 'buy';
            } elseif ($price < $lo20) {
                $setups[] = '20D Breakdown';
                $side = 'sell';
            }

            // RSI bounce
            if ($rsi <= 32.0) {
                $setups[] = 'RSI Oversold Bounce';
                $side = 'buy';
            } elseif ($rsi >= 68.0) {
                $setups[] = 'RSI Overbought Reversal';
                $side = 'sell';
            }

            // MA20 Reclaim
            if ($sma20 !== null) {
                $prevClose = (float) $dBars[count($dBars) - 2]['c'];
                if ($prevClose < $sma20 && $price > $sma20) {
                    $setups[] = 'MA20 Bullish Reclaim';
                    $side = 'buy';
                } elseif ($prevClose > $sma20 && $price < $sma20) {
                    $setups[] = 'MA20 Bearish Reject';
                    $side = 'sell';
                }
            }

            if (empty($setups) && $mode === 'all') {
                $setups[] = $trendBias === 'Bearish' ? 'Trend Watch (Bearish)' : 'Trend Watch (Bullish)';
                $side = $trendBias === 'Bearish' ? 'sell' : 'buy';
            }

            if (!empty($setups)) {
                $alignedWithTrend = ($side === 'buy' && $trendBias === 'Bullish')
                    || ($side === 'sell' && $trendBias === 'Bearish');
                $counterTrend = ($side === 'buy' && $trendBias === 'Bearish')
                    || ($side === 'sell' && $trendBias === 'Bullish');

                $score = 52 + (count($setups) * 12) + ($atrPips > 4 ? 6 : 0);
                if ($alignedWithTrend) {
                    $score += 12;
                } elseif ($counterTrend) {
                    $score -= 10;
                }

                $hits[] = [
                    'pair' => $pair,
                    'yahoo_symbol' => $meta['yahoo'],
                    'side' => $side,
                    'setup' => $setups[0],
                    'all_setups' => $setups,
                    'price' => $price,
                    'decimals' => $meta['decimals'],
                    'change_pct' => $changePct,
                    'rsi14' => round($rsi, 1),
                    'sma20' => $sma20 !== null ? round($sma20, $meta['decimals']) : null,
                    'trend_bias' => $trendBias,
                    'range_high' => round($rangeHigh, $meta['decimals']),
                    'range_low' => round($rangeLow, $meta['decimals']),
                    'atr_pips' => $atrPips,
                    'signal_time' => gmdate('H:i', $lastM['t']) . ' UTC',
                    'score' => max(10, min(99, $score)),
                ];
            }
        }

        usort($hits, fn ($a, $b) => $b['score'] <=> $a['score']);

        return ScanResult::create([
            'mode' => $mode,
            'session' => $activeSessionName,
            'pairs_scanned' => count(ForexMarketService::PAIRS),
            'hits' => $hits,
            'data_source' => implode(', ', array_keys($sources)),
            'ai_summary' => null,
        ]);
    }

    /**
     * Compute Asian Session Range (00:00 - 07:00 UTC) from the latest day's 5m bars.
     * Falls back to rolling 12-bar window if Asian bars are not present.
     *
     * @return array{0: float, 1: float, 2: string}
     */
    private function computeAsianSessionRange(array $mBars): array
    {
        $lastTs = end($mBars)['t'];
        $targetDate = gmdate('Y-m-d', $lastTs);
        $asianBars = [];

        foreach ($mBars as $b) {
            if (gmdate('Y-m-d', $b['t']) === $targetDate) {
                $hour = (int) gmdate('G', $b['t']);
                if ($hour >= 0 && $hour < 7) {
                    $asianBars[] = $b;
                }
            }
        }

        if (count($asianBars) >= 6) {
            return [
                max(array_column($asianBars, 'h')),
                min(array_column($asianBars, 'l')),
                'Asian Range',
            ];
        }

        $recentWindow = array_slice($mBars, -48, 12);

        return [
            max(array_column($recentWindow, 'h')),
            min(array_column($recentWindow, 'l')),
            'Session Range',
        ];
    }

    private function computeWilderRsi(array $bars, int $period = 14): float
    {
        $closes = array_column($bars, 'c');
        $n = count($closes);
        if ($n <= $period) {
            return 50.0;
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) {
                $gains += $diff;
            } else {
                $losses += abs($diff);
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $g = $diff > 0 ? $diff : 0.0;
            $l = $diff < 0 ? abs($diff) : 0.0;
            $avgGain = (($avgGain * ($period - 1)) + $g) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $l) / $period;
        }

        if ($avgLoss == 0.0) {
            return 100.0;
        }
        $rs = $avgGain / $avgLoss;

        return 100.0 - (100.0 / (1.0 + $rs));
    }

    private function computeSma(array $bars, int $period = 20): ?float
    {
        $n = count($bars);
        if ($n < $period) {
            return null;
        }
        $slice = array_slice($bars, -$period);

        return array_sum(array_column($slice, 'c')) / $period;
    }

    private function computeEma(array $bars, int $period): ?float
    {
        $closes = array_column($bars, 'c');
        $n = count($closes);
        if ($n < $period) {
            return null;
        }

        $k = 2.0 / ($period + 1);
        $ema = array_sum(array_slice($closes, 0, $period)) / $period;

        for ($i = $period; $i < $n; $i++) {
            $ema = ($closes[$i] * $k) + ($ema * (1.0 - $k));
        }

        return $ema;
    }

    private function computeAtr(array $bars, int $period = 14): float
    {
        $n = count($bars);
        if ($n < 2) {
            return 0.0;
        }
        $trs = [];
        $start = max(1, $n - $period);
        for ($i = $start; $i < $n; $i++) {
            $h = $bars[$i]['h'];
            $l = $bars[$i]['l'];
            $pc = $bars[$i - 1]['c'];
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        return count($trs) > 0 ? array_sum($trs) / count($trs) : 0.0;
    }
}
