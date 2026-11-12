<?php

namespace App\Services;

use App\Models\TraderSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ForexMarketService
{
    public const PAIRS = [
        'EUR/USD' => ['yahoo' => 'EURUSD=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'EUR', 'quote' => 'USD', 'base_price' => 1.0850],
        'GBP/USD' => ['yahoo' => 'GBPUSD=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'GBP', 'quote' => 'USD', 'base_price' => 1.2680],
        'USD/JPY' => ['yahoo' => 'USDJPY=X', 'pip' => 0.01,   'decimals' => 3, 'base' => 'USD', 'quote' => 'JPY', 'base_price' => 153.40],
        'AUD/USD' => ['yahoo' => 'AUDUSD=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'AUD', 'quote' => 'USD', 'base_price' => 0.6540],
        'USD/CHF' => ['yahoo' => 'USDCHF=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'USD', 'quote' => 'CHF', 'base_price' => 0.8810],
        'USD/CAD' => ['yahoo' => 'USDCAD=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'USD', 'quote' => 'CAD', 'base_price' => 1.3920],
        'NZD/USD' => ['yahoo' => 'NZDUSD=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'NZD', 'quote' => 'USD', 'base_price' => 0.5980],
        'EUR/GBP' => ['yahoo' => 'EURGBP=X', 'pip' => 0.0001, 'decimals' => 5, 'base' => 'EUR', 'quote' => 'GBP', 'base_price' => 0.8555],
        'EUR/JPY' => ['yahoo' => 'EURJPY=X', 'pip' => 0.01,   'decimals' => 3, 'base' => 'EUR', 'quote' => 'JPY', 'base_price' => 166.45],
        'GBP/JPY' => ['yahoo' => 'GBPJPY=X', 'pip' => 0.01,   'decimals' => 3, 'base' => 'GBP', 'quote' => 'JPY', 'base_price' => 194.50],
        'XAU/USD' => ['yahoo' => 'GC=F',     'pip' => 0.10,   'decimals' => 2, 'base' => 'XAU', 'quote' => 'USD', 'base_price' => 2685.00],
    ];

    public function getPairMeta(string $pair): array
    {
        $norm = strtoupper(trim($pair));
        if (isset(self::PAIRS[$norm])) {
            return array_merge(['pair' => $norm], self::PAIRS[$norm]);
        }

        return array_merge(['pair' => 'EUR/USD'], self::PAIRS['EUR/USD']);
    }

    public function getActiveSessions(?Carbon $utcNow = null): array
    {
        $now = $utcNow ? $utcNow->copy()->utc() : Carbon::now('UTC');
        $hour = (int) $now->format('G');
        $isWeekend = $now->isSaturday() || ($now->isSunday() && $hour < 22) || ($now->isFriday() && $hour >= 22);

        $sessions = [
            'sydney'   => ['name' => 'Sydney',   'open_utc' => 22, 'close_utc' => 7,  'active' => !$isWeekend && ($hour >= 22 || $hour < 7)],
            'tokyo'    => ['name' => 'Tokyo',    'open_utc' => 0,  'close_utc' => 9,  'active' => !$isWeekend && ($hour >= 0 && $hour < 9)],
            'london'   => ['name' => 'London',   'open_utc' => 8,  'close_utc' => 17, 'active' => !$isWeekend && ($hour >= 8 && $hour < 17)],
            'new_york' => ['name' => 'New York', 'open_utc' => 13, 'close_utc' => 22, 'active' => !$isWeekend && ($hour >= 13 && $hour < 22)],
        ];

        $overlap = !$isWeekend && ($hour >= 13 && $hour < 17);

        return [
            'utc_time' => $now->format('Y-m-d H:i') . ' UTC',
            'wib_time' => $now->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') . ' WIB',
            'market_open' => !$isWeekend,
            'london_ny_overlap' => $overlap,
            'sessions' => $sessions,
        ];
    }

    /**
     * Fetch OHLC bars from Yahoo Finance v8 chart API with fallback to realistic synthetic bars.
     *
     * @return array{source: string, bars: array<int, array{t: int, o: float, h: float, l: float, c: float}>}
     */
    public function fetchBars(string $pair, string $interval = '5m', string $range = '30d', bool $preferSpot = false): array
    {
        $meta = $this->getPairMeta($pair);
        $useSpot = $preferSpot || $meta['pair'] === 'XAU/USD';
        $tdKey = $useSpot ? $this->resolvedTwelveDataKey() : '';
        $useTd = $tdKey !== '';
        $modeTag = $useTd ? 'td' : 'yh';
        $cacheKey = "fx_bars_{$modeTag}_{$meta['yahoo']}_{$interval}_{$range}";
        $ttl = $interval === '1d' ? 21600 : 900;

        return Cache::remember($cacheKey, $ttl, function () use ($meta, $interval, $range, $tdKey, $useTd) {
            if ($useTd && !Cache::get('twelvedata_cooldown')) {
                $tdBars = $this->fetchTwelveDataBars($meta, $interval, $range, $tdKey);
                if ($tdBars !== null) {
                    return ['source' => 'twelvedata_spot', 'bars' => $tdBars];
                }
            }

            $hosts = ['https://query2.finance.yahoo.com', 'https://query1.finance.yahoo.com'];

            foreach ($hosts as $host) {
                try {
                    $resp = Http::timeout(6)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (compatible; ForexDayTraderAI/1.0)',
                        ])
                        ->get("{$host}/v8/finance/chart/{$meta['yahoo']}", [
                            'range' => $range,
                            'interval' => $interval,
                        ]);

                    if ($resp->ok()) {
                        $json = $resp->json();
                        $result = $json['chart']['result'][0] ?? null;
                        if ($result && !empty($result['timestamp'])) {
                            $bars = $this->parseYahooBars($result);
                            if (count($bars) >= 30) {
                                return ['source' => 'yahoo_live', 'bars' => $bars];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fall through to next host or synthetic fallback
                }
            }

            return [
                'source' => 'synthetic_offline',
                'bars' => $this->generateSyntheticBars($meta, $interval),
            ];
        });
    }

    public function getLatestPrice(string $pair, bool $preferSpot = false): array
    {
        $meta = $this->getPairMeta($pair);

        // Priority 1: Direct live quote from connected Finex MT5 terminal
        $mt5Heartbeat = Cache::get('mt5_bridge_heartbeat');
        if ($mt5Heartbeat && (time() - $mt5Heartbeat < 25)) {
            $mt5Quotes = (array) Cache::get('mt5_live_quotes', []);
            if (isset($mt5Quotes[$meta['pair']])) {
                $q = $mt5Quotes[$meta['pair']];
                return [
                    'pair' => $meta['pair'],
                    'price' => round((float) $q['price'], $meta['decimals']),
                    'prev_close' => round((float) $q['price'], $meta['decimals']),
                    'source' => 'mt5_finex_live',
                    'timestamp' => $q['timestamp'] ?? time(),
                ];
            }
        }

        $useSpot = $preferSpot || $meta['pair'] === 'XAU/USD';
        if ($useSpot) {
            $tdKey = $this->resolvedTwelveDataKey();
            if ($tdKey !== '' && !Cache::get('twelvedata_cooldown')) {
                $tdQuotes = $this->fetchTwelveDataBatchQuotes([$meta['pair']], $tdKey);
                if (isset($tdQuotes[$meta['pair']])) {
                    return [
                        'pair' => $meta['pair'],
                        'price' => $tdQuotes[$meta['pair']]['price'],
                        'prev_close' => $tdQuotes[$meta['pair']]['price'],
                        'source' => 'twelvedata_spot',
                        'timestamp' => time(),
                    ];
                }
            }
        }

        $data = $this->fetchBars($meta['pair'], '5m', '5d', $useSpot);
        $bars = $data['bars'];
        $last = end($bars);
        $prev = count($bars) > 1 ? $bars[count($bars) - 2] : $last;

        return [
            'pair' => $meta['pair'],
            'price' => round((float) $last['c'], $meta['decimals']),
            'prev_close' => round((float) $prev['c'], $meta['decimals']),
            'source' => $data['source'],
            'timestamp' => $last['t'],
        ];
    }

    /**
     * Fetch near-real-time quotes for all or selected Forex pairs (5s cache, Yahoo Finance to save Twelve Data quota).
     */
    public function getLiveQuotes(?array $pairs = null): array
    {
        $targets = $pairs ?: array_keys(self::PAIRS);
        $quotes = [];

        // Priority 1: Direct live quotes streamed from connected Finex MT5 terminal
        $mt5Heartbeat = Cache::get('mt5_bridge_heartbeat');
        $mt5IsLive = $mt5Heartbeat && (time() - $mt5Heartbeat < 25);
        if ($mt5IsLive) {
            $mt5Quotes = (array) Cache::get('mt5_live_quotes', []);
            foreach ($targets as $pair) {
                $meta = $this->getPairMeta($pair);
                if (isset($mt5Quotes[$meta['pair']])) {
                    $q = $mt5Quotes[$meta['pair']];
                    $quotes[$meta['pair']] = [
                        'pair' => $meta['pair'],
                        'price' => round((float) $q['price'], $meta['decimals']),
                        'decimals' => $meta['decimals'],
                        'source' => 'mt5_finex_live',
                        'timestamp' => $q['timestamp'] ?? time(),
                    ];
                }
            }
        }

        // Always fetch XAU/USD Spot from Twelve Data (1 credit, cached 90s) so Gold matches Finex Spot
        $hasGold = in_array('XAU/USD', array_map(fn ($p) => $this->getPairMeta($p)['pair'], $targets), true);
        if ($hasGold && !isset($quotes['XAU/USD'])) {
            $tdKey = $this->resolvedTwelveDataKey();
            if ($tdKey !== '' && !Cache::get('twelvedata_cooldown')) {
                $tdQuotes = $this->fetchTwelveDataBatchQuotes(['XAU/USD'], $tdKey);
                if (isset($tdQuotes['XAU/USD'])) {
                    $quotes['XAU/USD'] = $tdQuotes['XAU/USD'];
                }
            }
        }

        foreach ($targets as $pair) {
            $meta = $this->getPairMeta($pair);
            if (isset($quotes[$meta['pair']])) {
                continue;
            }
            $cacheKey = "fx_live_quote_{$meta['yahoo']}";

            $quotes[$meta['pair']] = Cache::remember($cacheKey, 5, function () use ($meta) {
                foreach (['https://query2.finance.yahoo.com', 'https://query1.finance.yahoo.com'] as $host) {
                    try {
                        $resp = Http::timeout(3)
                            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ForexDayTraderAI/1.0)'])
                            ->get("{$host}/v8/finance/chart/{$meta['yahoo']}", [
                                'range' => '1d',
                                'interval' => '1m',
                            ]);

                        if ($resp->ok()) {
                            $result = $resp->json()['chart']['result'][0] ?? null;
                            $mktPrice = $result['meta']['regularMarketPrice'] ?? null;
                            if ($mktPrice !== null && is_numeric($mktPrice)) {
                                return [
                                    'price' => round((float) $mktPrice, $meta['decimals']),
                                    'decimals' => $meta['decimals'],
                                    'source' => 'yahoo_live',
                                    'updated_at' => gmdate('H:i:s') . ' UTC',
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fall through
                    }
                }

                $fallback = $this->getLatestPrice($meta['pair']);

                return [
                    'price' => $fallback['price'],
                    'decimals' => $meta['decimals'],
                    'source' => $fallback['source'],
                    'updated_at' => gmdate('H:i:s') . ' UTC',
                ];
            });
        }

        return $quotes;
    }

    public function pipValuePerStandardLot(string $pair, float $price): float
    {
        $meta = $this->getPairMeta($pair);
        if ($meta['quote'] === 'USD') {
            return $meta['pair'] === 'XAU/USD' ? 10.0 : 10.0;
        }
        if ($meta['base'] === 'USD' && $price > 0) {
            return round((100000 * $meta['pip']) / $price, 4);
        }
        if ($meta['quote'] === 'JPY') {
            return round((100000 * 0.01) / 153.40, 4);
        }

        return 10.0;
    }

    private function resolvedTwelveDataKey(): string
    {
        try {
            return TraderSetting::current()->resolvedTwelveDataKey();
        } catch (\Throwable $e) {
            return trim((string) config('services.twelvedata.api_key', ''));
        }
    }

    private function fetchTwelveDataBars(array $meta, string $interval, string $range, string $apiKey): ?array
    {
        $tdInterval = $interval === '1d' ? '1day' : '5min';
        $outputSize = $interval === '1d' ? 200 : ($range === '5d' ? 288 : 800);

        try {
            $resp = Http::timeout(8)->get('https://api.twelvedata.com/time_series', [
                'symbol' => $meta['pair'],
                'interval' => $tdInterval,
                'outputsize' => $outputSize,
                'apikey' => $apiKey,
            ]);

            if ($resp->status() === 429) {
                Cache::put('twelvedata_cooldown', true, 120);

                return null;
            }

            if (!$resp->ok()) {
                return null;
            }

            $json = $resp->json();
            if (($json['status'] ?? '') === 'error') {
                if ((int) ($json['code'] ?? 0) === 429) {
                    Cache::put('twelvedata_cooldown', true, 120);
                }

                return null;
            }

            $values = $json['values'] ?? [];
            if (!is_array($values) || count($values) < 30) {
                return null;
            }

            $bars = [];
            foreach (array_reverse($values) as $row) {
                if (!isset($row['datetime'], $row['open'], $row['high'], $row['low'], $row['close'])) {
                    continue;
                }
                $bars[] = [
                    't' => strtotime($row['datetime'] . ' UTC') ?: time(),
                    'o' => (float) $row['open'],
                    'h' => (float) $row['high'],
                    'l' => (float) $row['low'],
                    'c' => (float) $row['close'],
                ];
            }

            return count($bars) >= 30 ? $bars : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchTwelveDataBatchQuotes(array $targets, string $apiKey): array
    {
        $normalized = array_values(array_unique(array_map(fn ($p) => $this->getPairMeta($p)['pair'], $targets)));
        if (empty($normalized)) {
            return [];
        }
        sort($normalized);
        $cacheKey = 'fx_td_batch_' . md5(implode(',', $normalized));

        return Cache::remember($cacheKey, 90, function () use ($normalized, $apiKey) {
            try {
                $resp = Http::timeout(6)->get('https://api.twelvedata.com/price', [
                    'symbol' => implode(',', $normalized),
                    'apikey' => $apiKey,
                ]);

                if ($resp->status() === 429 || !$resp->ok()) {
                    if ($resp->status() === 429) {
                        Cache::put('twelvedata_cooldown', true, 120);
                    }

                    return [];
                }

                $json = $resp->json();
                if (!is_array($json) || ($json['status'] ?? '') === 'error') {
                    if ((int) ($json['code'] ?? 0) === 429) {
                        Cache::put('twelvedata_cooldown', true, 120);
                    }

                    return [];
                }

                $out = [];
                // Single symbol returns ['price' => '...']; multiple returns ['EUR/USD' => ['price' => '...'], ...]
                if (count($normalized) === 1 && isset($json['price']) && is_numeric($json['price'])) {
                    $meta = $this->getPairMeta($normalized[0]);
                    $out[$meta['pair']] = [
                        'price' => round((float) $json['price'], $meta['decimals']),
                        'decimals' => $meta['decimals'],
                        'source' => 'twelvedata_spot',
                        'updated_at' => gmdate('H:i:s') . ' UTC',
                    ];

                    return $out;
                }

                foreach ($normalized as $pair) {
                    $price = $json[$pair]['price'] ?? null;
                    if ($price !== null && is_numeric($price)) {
                        $meta = $this->getPairMeta($pair);
                        $out[$meta['pair']] = [
                            'price' => round((float) $price, $meta['decimals']),
                            'decimals' => $meta['decimals'],
                            'source' => 'twelvedata_spot',
                            'updated_at' => gmdate('H:i:s') . ' UTC',
                        ];
                    }
                }

                return $out;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public function toPips(string $pair, float $priceDiff): float
    {
        $meta = $this->getPairMeta($pair);

        return round(abs($priceDiff) / $meta['pip'], 1);
    }

    private function parseYahooBars(array $result): array
    {
        $timestamps = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $opens = $quote['open'] ?? [];
        $highs = $quote['high'] ?? [];
        $lows = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];

        $bars = [];
        foreach ($timestamps as $i => $ts) {
            if (!isset($opens[$i], $highs[$i], $lows[$i], $closes[$i])) {
                continue;
            }
            if ($opens[$i] === null || $closes[$i] === null) {
                continue;
            }
            $bars[] = [
                't' => (int) $ts,
                'o' => (float) $opens[$i],
                'h' => (float) $highs[$i],
                'l' => (float) $lows[$i],
                'c' => (float) $closes[$i],
            ];
        }

        return $bars;
    }

    private function generateSyntheticBars(array $meta, string $interval): array
    {
        $count = $interval === '1d' ? 90 : 1800;
        $stepSec = $interval === '1d' ? 86400 : 300;
        $pip = $meta['pip'];
        $volatility = $interval === '1d' ? $pip * 55 : $pip * 4.5;

        // Deterministic per pair so scans & simulations are reproducible offline
        mt_srand(crc32($meta['pair'] . $interval));
        $now = time();
        $now = $now - ($now % $stepSec);
        $price = $meta['base_price'];
        $bars = [];

        for ($i = $count; $i >= 1; $i--) {
            $t = $now - ($i * $stepSec);
            $drift = (mt_rand(-1000, 1005) / 1000.0) * $volatility;
            $open = $price;
            $close = max($pip * 10, $open + $drift);
            $wickUp = abs(mt_rand(100, 900) / 1000.0) * $volatility;
            $wickDn = abs(mt_rand(100, 900) / 1000.0) * $volatility;
            $high = max($open, $close) + $wickUp;
            $low = min($open, $close) - $wickDn;

            $bars[] = [
                't' => $t,
                'o' => round($open, $meta['decimals']),
                'h' => round($high, $meta['decimals']),
                'l' => round($low, $meta['decimals']),
                'c' => round($close, $meta['decimals']),
            ];
            $price = $close;
        }

        return $bars;
    }
}

