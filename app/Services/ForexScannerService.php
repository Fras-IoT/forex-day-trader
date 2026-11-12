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
            $daily = $this->market->fetchBars($pair, '1d', '3mo');
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
            $atr = $this->computeAtr($mBars, 14);
            $atrPips = $this->market->toPips($pair, $atr);

            // Session Opening Range (last 36-42 5m bars window for intraday breakout)
            $recentWindow = array_slice($mBars, -48, 12);
            $rangeHigh = max(array_column($recentWindow, 'h'));
            $rangeLow = min(array_column($recentWindow, 'l'));

            $setups = [];
            $side = 'buy';

            if ($price > $rangeHigh) {
                $setups[] = 'Session Range Breakout (Long)';
                $side = 'buy';
            } elseif ($price < $rangeLow) {
                $setups[] = 'Session Range Breakdown (Short)';
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
                $setups[] = $price >= ($sma20 ?? $price) ? 'Trend Watch (Bullish)' : 'Trend Watch (Bearish)';
                $side = $price >= ($sma20 ?? $price) ? 'buy' : 'sell';
            }

            if (!empty($setups)) {
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
                    'range_high' => round($rangeHigh, $meta['decimals']),
                    'range_low' => round($rangeLow, $meta['decimals']),
                    'atr_pips' => $atrPips,
                    'signal_time' => gmdate('H:i', $lastM['t']) . ' UTC',
                    'score' => min(99, 55 + (count($setups) * 14) + ($atrPips > 4 ? 8 : 0)),
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

