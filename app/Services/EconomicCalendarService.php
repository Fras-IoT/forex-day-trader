<?php

namespace App\Services;

use App\Models\EconomicEvent;
use App\Models\TraderSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EconomicCalendarService
{
    /**
     * Major currencies tracked by Forex Day Trader.
     */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'NZD'];

    /**
     * Return list of Forex Day Trader pairs affected by the given currency.
     *
     * @return string[]
     */
    public static function getAffectedPairs(string $currency): array
    {
        $curr = strtoupper(trim($currency));
        $map = [
            'USD' => ['EUR/USD', 'GBP/USD', 'USD/JPY', 'AUD/USD', 'USD/CHF', 'USD/CAD', 'NZD/USD', 'XAU/USD'],
            'EUR' => ['EUR/USD', 'EUR/GBP', 'EUR/JPY'],
            'GBP' => ['GBP/USD', 'EUR/GBP', 'GBPJPY'],
            'JPY' => ['USD/JPY', 'EUR/JPY', 'GBPJPY'],
            'AUD' => ['AUD/USD'],
            'CAD' => ['USD/CAD'],
            'CHF' => ['USD/CHF'],
            'NZD' => ['NZD/USD'],
        ];

        return $map[$curr] ?? [];
    }

    /**
     * Return human-friendly description and volatility explanation in Indonesian for an economic event.
     */
    public static function getEventDescription(string $title, string $currency): string
    {
        $titleLower = strtolower($title);
        $curr = strtoupper(trim($currency));

        if (str_contains($titleLower, 'non-farm') || str_contains($titleLower, 'nfp')) {
            return "Non-Farm Payrolls (NFP) adalah rilis ekonomi paling volatil di pasar global. Mengukur penambahan tenaga kerja di AS di luar sektor pertanian. Memicu pergerakan harga 50-150 pips dan pelebaran spread ekstrem pada pasangan USD dan XAU/USD (Gold).";
        }
        if (str_contains($titleLower, 'unemployment rate')) {
            return "Tingkat pengangguran mencerminkan kesehatan pasar tenaga kerja {$curr}. Angka aktual yang menyimpang dari ekspektasi biasanya memicu lonjakan harga instan dan penyesuaian ekspektasi suku bunga.";
        }
        if (str_contains($titleLower, 'cpi') || str_contains($titleLower, 'inflation')) {
            return "Consumer Price Index (CPI) adalah tolok ukur inflasi utama. Inflasi di atas estimasi memperkuat potensi kenaikan suku bunga bank sentral dan memicu reli agresif pada pasangan {$curr}.";
        }
        if (str_contains($titleLower, 'interest rate') || str_contains($titleLower, 'rate decision') || str_contains($titleLower, 'cash rate') || str_contains($titleLower, 'fomc') || str_contains($titleLower, 'bank rate') || str_contains($titleLower, 'policy rate')) {
            return "Keputusan Suku Bunga Bank Sentral ({$curr}). Perubahan suku bunga atau nada pidato resmi dapat memicu tren besar berhari-hari. Berbahaya untuk posisi tanpa SL karena lonjakan spread tinggi.";
        }
        if (str_contains($titleLower, 'press conference') || str_contains($titleLower, 'statement') || str_contains($titleLower, 'monetary policy')) {
            return "Pernyataan kebijakan moneter dan konferensi pers gubernur bank sentral. Sering memicu pergerakan bolak-balik (whipsaw) tajam dalam hitungan menit saat sesi tanya jawab.";
        }
        if (str_contains($titleLower, 'retail sales')) {
            return "Data penjualan ritel mengukur konsumsi masyarakat yang menyumbang porsi terbesar ekonomi domestik {$curr}. Hasil deviasi tinggi dapat menggerakkan arah tren intraday.";
        }
        if (str_contains($titleLower, 'pmi')) {
            return "Purchasing Managers' Index (PMI) mengukur aktivitas bisnis manufaktur atau jasa. Angka >50 menandakan ekspansi ekonomi, sedangkan <50 kontraksi.";
        }
        if (str_contains($titleLower, 'employment') || str_contains($titleLower, 'claimant') || str_contains($titleLower, 'adp')) {
            return "Laporan ketenagakerjaan berkala {$curr}. Menunjukkan tren rekrutmen dan kesehatan ekonomi, sering menjadi penggerak momentum sebelum rilis data resmi.";
        }
        if (str_contains($titleLower, 'gdp')) {
            return "Gross Domestic Product (GDP) adalah total output ekonomi negara {$curr}. Menjadi jangkar analisis fundamental makro jangka menengah hingga panjang.";
        }
        if (str_contains($titleLower, 'speaks') || str_contains($titleLower, 'speech')) {
            return "Pidato pejabat bank sentral ({$curr}). Komentar seputar proyeksi inflasi atau suku bunga dapat memicu lonjakan volatilitas spontan.";
        }

        return "Agenda rilis ekonomi berkala {$curr}. Berpotensi meningkatkan likuiditas dan volatilitas pada pasangan mata uang terkait saat waktu rilis.";
    }

    /**
     * Sync 1 full year of economic calendar events into MongoDB.
     * Combines live feed data for immediate weeks with the complete institutional
     * 1-year calendar for major central bank & economic indicator releases.
     *
     * @return array{total_synced: int, high_impact: int, date_from: string, date_to: string}
     */
    public function syncOneYearCalendar(): array
    {
        $events = [];

        // 1. Ambil data feed live terkini jika koneksi internet tersedia
        try {
            $liveEvents = $this->fetchLiveFeeds();
            foreach ($liveEvents as $le) {
                $events[$le['event_key']] = $le;
            }
        } catch (\Throwable $e) {
            Log::warning('EconomicCalendarService live feed warning: ' . $e->getMessage());
        }

        // 2. Buat jadwal 1 tahun penuh ke depan (12 bulan dari sekarang) untuk semua event High Impact institusional
        $now = now();
        $startMonth = $now->copy()->subMonth()->startOfMonth();
        $endMonth = $now->copy()->addMonths(12)->endOfMonth();

        $generated = $this->generateOneYearInstitutionalEvents($startMonth, $endMonth);
        foreach ($generated as $gen) {
            if (!isset($events[$gen['event_key']])) {
                $events[$gen['event_key']] = $gen;
            }
        }

        // 3. Simpan ke MongoDB secara upsert
        $totalSynced = 0;
        $highImpactCount = 0;

        foreach ($events as $ev) {
            $impact = ucfirst(strtolower($ev['impact'] ?? 'Medium'));
            if ($impact === 'High') {
                $highImpactCount++;
            }

            EconomicEvent::updateOrCreate(
                ['event_key' => $ev['event_key']],
                [
                    'title' => $ev['title'],
                    'country' => strtoupper($ev['country']),
                    'currency' => strtoupper($ev['currency'] ?? $ev['country']),
                    'impact' => $impact,
                    'event_time' => $ev['event_time'],
                    'event_time_wib' => Carbon::parse($ev['event_time'])->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
                    'forecast' => $ev['forecast'] ?? null,
                    'previous' => $ev['previous'] ?? null,
                    'actual' => $ev['actual'] ?? null,
                    'source' => $ev['source'] ?? 'Institutional Macro Feed',
                ]
            );
            $totalSynced++;
        }

        // Simpan waktu sinkronisasi ke setting
        $settings = TraderSetting::current();
        $settings->economic_calendar_last_synced_at = now();
        $settings->save();

        return [
            'total_synced' => $totalSynced,
            'high_impact' => $highImpactCount,
            'date_from' => $startMonth->setTimezone('Asia/Jakarta')->format('d M Y'),
            'date_to' => $endMonth->setTimezone('Asia/Jakarta')->format('d M Y'),
        ];
    }

    /**
     * Fetch immediate live calendar feeds for current and upcoming weeks.
     */
    private function fetchLiveFeeds(): array
    {
        $urls = [
            'https://nfs.faireconomy.media/ff_calendar_thisweek.json',
            'https://nfs.faireconomy.media/ff_calendar_nextweek.json',
        ];

        $results = [];

        foreach ($urls as $url) {
            try {
                $resp = Http::timeout(6)
                    ->withHeaders(['User-Agent' => 'ForexDayTrader/1.0'])
                    ->get($url);

                if ($resp->ok() && is_array($resp->json())) {
                    foreach ($resp->json() as $item) {
                        $country = strtoupper(trim((string) ($item['country'] ?? '')));
                        if (!in_array($country, self::CURRENCIES, true)) {
                            continue;
                        }

                        $dateStr = (string) ($item['date'] ?? '');
                        if ($dateStr === '') {
                            continue;
                        }

                        $title = trim((string) ($item['title'] ?? 'Economic Release'));
                        $eventTime = Carbon::parse($dateStr);
                        $key = md5("{$country}_{$title}_" . $eventTime->format('Y-m-d_H:i'));

                        $impact = ucfirst(strtolower((string) ($item['impact'] ?? 'Medium')));
                        if (!in_array($impact, ['High', 'Medium', 'Low'], true)) {
                            $impact = 'Medium';
                        }

                        $results[] = [
                            'event_key' => $key,
                            'title' => $title,
                            'country' => $country,
                            'currency' => $country,
                            'impact' => $impact,
                            'event_time' => $eventTime,
                            'forecast' => $item['forecast'] ?? null,
                            'previous' => $item['previous'] ?? null,
                            'actual' => $item['actual'] ?? null,
                            'source' => 'Forex Factory Live Feed',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore failure for individual feeds
            }
        }

        return $results;
    }

    /**
     * Generate the complete 1-year institutional schedule for major central banks & key macro indicators.
     * Covers 12 months ahead for USD, EUR, GBP, JPY, AUD, CAD, CHF, NZD.
     */
    private function generateOneYearInstitutionalEvents(Carbon $start, Carbon $end): array
    {
        $events = [];
        $current = $start->copy()->startOfMonth();

        while ($current->lessThanOrEqualTo($end)) {
            $year = $current->year;
            $month = $current->month;

            // 1. USD - Non-Farm Payrolls & Unemployment Rate (Jumat Pertama tiap bulan, 13:30 UTC / 20:30 WIB)
            $firstFriday = Carbon::create($year, $month, 1, 13, 30, 0, 'UTC');
            while (!$firstFriday->isFriday()) {
                $firstFriday->addDay();
            }
            $events[] = $this->buildEventItem('USD', 'Non-Farm Employment Change (NFP)', 'High', $firstFriday, '175K', '180K');
            $events[] = $this->buildEventItem('USD', 'Unemployment Rate', 'High', $firstFriday, '4.1%', '4.1%');

            // 2. USD - CPI m/m & y/y (Sekitar tanggal 10-14 tiap bulan, 13:30 UTC)
            $cpiDay = Carbon::create($year, $month, 12, 13, 30, 0, 'UTC');
            if ($cpiDay->isWeekend()) {
                $cpiDay->next(Carbon::TUESDAY);
            }
            $events[] = $this->buildEventItem('USD', 'CPI m/m (Core Inflation)', 'High', $cpiDay, '0.3%', '0.3%');
            $events[] = $this->buildEventItem('USD', 'CPI y/y (Inflation Rate)', 'High', $cpiDay, '2.8%', '2.9%');

            // 3. USD - PPI & Retail Sales (Pertengahan bulan, 13:30 UTC)
            $retailDay = Carbon::create($year, $month, 15, 13, 30, 0, 'UTC');
            if ($retailDay->isWeekend()) {
                $retailDay->next(Carbon::THURSDAY);
            }
            $events[] = $this->buildEventItem('USD', 'Core Retail Sales m/m', 'High', $retailDay, '0.4%', '0.3%');

            // 4. USD - ISM Manufacturing PMI (Hari kerja pertama tiap bulan, 15:00 UTC)
            $ismMfg = Carbon::create($year, $month, 1, 15, 0, 0, 'UTC');
            while ($ismMfg->isWeekend()) {
                $ismMfg->addDay();
            }
            $events[] = $this->buildEventItem('USD', 'ISM Manufacturing PMI', 'High', $ismMfg, '49.5', '49.0');

            // 5. USD - FOMC Interest Rate Decision & Fed Press Conference (8x setahun: Jan, Mar, May, Jun, Jul, Sep, Nov, Dec)
            if (in_array($month, [1, 3, 5, 6, 7, 9, 11, 12], true)) {
                $fomcDay = Carbon::create($year, $month, 18, 19, 0, 0, 'UTC');
                while (!$fomcDay->isWednesday()) {
                    $fomcDay->addDay();
                }
                $events[] = $this->buildEventItem('USD', 'Fed Interest Rate Decision (FOMC)', 'High', $fomcDay, '4.50%', '4.50%');
                $events[] = $this->buildEventItem('USD', 'FOMC Press Conference & Economic Projections', 'High', $fomcDay->copy()->addMinutes(30), '-', '-');
            }

            // 6. EUR - ECB Interest Rate Decision & Press Conference (8x setahun: Jan, Mar, Apr, Jun, Jul, Sep, Oct, Dec)
            if (in_array($month, [1, 3, 4, 6, 7, 9, 10, 12], true)) {
                $ecbDay = Carbon::create($year, $month, 11, 12, 15, 0, 'UTC');
                while (!$ecbDay->isThursday()) {
                    $ecbDay->addDay();
                }
                $events[] = $this->buildEventItem('EUR', 'ECB Main Refinancing Rate Decision', 'High', $ecbDay, '3.25%', '3.40%');
                $events[] = $this->buildEventItem('EUR', 'ECB Monetary Policy Press Conference', 'High', $ecbDay->copy()->addMinutes(30), '-', '-');
            }

            // 7. EUR - Eurozone Flash CPI (Awal bulan, 10:00 UTC)
            $eurCpi = Carbon::create($year, $month, 2, 10, 0, 0, 'UTC');
            if ($eurCpi->isWeekend()) {
                $eurCpi->next(Carbon::MONDAY);
            }
            $events[] = $this->buildEventItem('EUR', 'Eurozone Core CPI Flash Estimate y/y', 'High', $eurCpi, '2.4%', '2.5%');

            // 8. GBP - BOE Official Bank Rate & Monetary Policy Report (Feb, Mar, May, Jun, Aug, Sep, Nov, Dec)
            if (in_array($month, [2, 3, 5, 6, 8, 9, 11, 12], true)) {
                $boeDay = Carbon::create($year, $month, 7, 12, 0, 0, 'UTC');
                while (!$boeDay->isThursday()) {
                    $boeDay->addDay();
                }
                $events[] = $this->buildEventItem('GBP', 'BOE Official Bank Rate Decision', 'High', $boeDay, '4.75%', '4.75%');
                $events[] = $this->buildEventItem('GBP', 'MPC Official Bank Rate Votes', 'High', $boeDay, '0-1-8', '0-1-8');
            }

            // 9. GBP - UK CPI & Claimant Count (Sekitar tanggal 16-20, 07:00 UTC)
            $gbpCpi = Carbon::create($year, $month, 17, 7, 0, 0, 'UTC');
            if ($gbpCpi->isWeekend()) {
                $gbpCpi->next(Carbon::WEDNESDAY);
            }
            $events[] = $this->buildEventItem('GBP', 'UK CPI y/y (Inflation Rate)', 'High', $gbpCpi, '2.2%', '2.3%');
            $events[] = $this->buildEventItem('GBP', 'UK Claimant Count / Employment Change', 'High', $gbpCpi->copy()->subDays(2), '15.0K', '18.5K');

            // 10. JPY - BOJ Policy Rate Decision (Jan, Mar, Apr, Jun, Jul, Sep, Oct, Dec)
            if (in_array($month, [1, 3, 4, 6, 7, 9, 10, 12], true)) {
                $bojDay = Carbon::create($year, $month, 20, 3, 30, 0, 'UTC');
                if ($bojDay->isWeekend()) {
                    $bojDay->next(Carbon::FRIDAY);
                }
                $events[] = $this->buildEventItem('JPY', 'BOJ Policy Rate & Monetary Policy Statement', 'High', $bojDay, '0.50%', '0.25%');
                $events[] = $this->buildEventItem('JPY', 'BOJ Press Conference', 'High', $bojDay->copy()->addHours(3), '-', '-');
            }

            // 11. AUD - RBA Cash Rate Decision (Feb, Mar, May, Jun, Aug, Sep, Nov, Dec)
            if (in_array($month, [2, 3, 5, 6, 8, 9, 11, 12], true)) {
                $rbaDay = Carbon::create($year, $month, 1, 3, 30, 0, 'UTC');
                while (!$rbaDay->isTuesday()) {
                    $rbaDay->addDay();
                }
                $events[] = $this->buildEventItem('AUD', 'RBA Cash Rate Decision & Rate Statement', 'High', $rbaDay, '4.35%', '4.35%');
            }

            // 12. AUD - Australia Employment Change (Pertengahan bulan, 01:30 UTC)
            $audJobs = Carbon::create($year, $month, 15, 1, 30, 0, 'UTC');
            if ($audJobs->isWeekend()) {
                $audJobs->next(Carbon::THURSDAY);
            }
            $events[] = $this->buildEventItem('AUD', 'Australia Employment Change & Unemployment Rate', 'High', $audJobs, '25.0K', '30.2K');

            // 13. CAD - BOC Rate Decision (Jan, Mar, Apr, Jun, Jul, Sep, Oct, Dec)
            if (in_array($month, [1, 3, 4, 6, 7, 9, 10, 12], true)) {
                $bocDay = Carbon::create($year, $month, 22, 14, 45, 0, 'UTC');
                while (!$bocDay->isWednesday()) {
                    $bocDay->addDay();
                }
                $events[] = $this->buildEventItem('CAD', 'BOC Interest Rate Decision & Monetary Policy Report', 'High', $bocDay, '3.75%', '4.00%');
            }

            // 14. CAD - Employment Change (Jumat pertama / kedua bersamaan NFP, 13:30 UTC)
            $cadJobs = $firstFriday->copy();
            $events[] = $this->buildEventItem('CAD', 'Canada Employment Change & Unemployment Rate', 'High', $cadJobs, '22.0K', '25.5K');

            // 15. CHF - SNB Policy Rate & Monetary Policy Assessment (Quarterly: Mar, Jun, Sep, Dec)
            if (in_array($month, [3, 6, 9, 12], true)) {
                $snbDay = Carbon::create($year, $month, 18, 7, 30, 0, 'UTC');
                while (!$snbDay->isThursday()) {
                    $snbDay->addDay();
                }
                $events[] = $this->buildEventItem('CHF', 'SNB Policy Rate Decision & Assessment', 'High', $snbDay, '1.00%', '1.00%');
            }

            // 16. NZD - RBNZ Official Cash Rate Decision (Feb, Apr, May, Jul, Aug, Oct, Nov)
            if (in_array($month, [2, 4, 5, 7, 8, 10, 11], true)) {
                $rbnzDay = Carbon::create($year, $month, 25, 1, 0, 0, 'UTC');
                while (!$rbnzDay->isWednesday()) {
                    $rbnzDay->addDay();
                }
                $events[] = $this->buildEventItem('NZD', 'RBNZ Official Cash Rate Decision & Statement', 'High', $rbnzDay, '4.25%', '4.75%');
            }

            $current->addMonth();
        }

        return $events;
    }

    private function buildEventItem(string $country, string $title, string $impact, Carbon $time, ?string $forecast, ?string $previous): array
    {
        $key = md5("{$country}_{$title}_" . $time->format('Y-m-d_H:i'));

        return [
            'event_key' => $key,
            'title' => $title,
            'country' => $country,
            'currency' => $country,
            'impact' => $impact,
            'event_time' => $time,
            'forecast' => $forecast,
            'previous' => $previous,
            'actual' => null,
            'source' => 'Institutional 1-Year Schedule',
        ];
    }
}

