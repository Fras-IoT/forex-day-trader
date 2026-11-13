<?php

namespace App\Http\Controllers;

use App\Models\EconomicEvent;
use App\Models\NewsBrief;
use App\Models\PriceAlert;
use App\Models\TraderSetting;
use App\Services\EconomicCalendarService;
use App\Services\ForexMarketService;
use App\Services\ForexNewsService;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NewsAlertController extends Controller
{
    public function news(Request $request, GeminiService $gemini)
    {
        $briefs = NewsBrief::orderBy('created_at', 'desc')->take(10)->get();
        $pairs = array_keys(ForexMarketService::PAIRS);
        $settings = TraderSetting::current();

        $selectedCurrency = strtoupper(trim((string) $request->query('currency', 'ALL')));
        $selectedImpact = strtoupper(trim((string) $request->query('impact', 'ALL')));
        $selectedTimeframe = strtolower(trim((string) $request->query('timeframe', 'upcoming')));
        $viewMode = strtolower(trim((string) $request->query('view', 'calendar'))); // 'calendar' or 'table'

        // Month & Year parameter for interactive monthly grid
        $nowWib = Carbon::now('Asia/Jakarta');
        $year = (int) $request->query('year', $nowWib->year);
        $month = (int) $request->query('month', $nowWib->month);

        if ($month < 1 || $month > 12) {
            $month = (int) $nowWib->month;
        }
        if ($year < 2024 || $year > 2030) {
            $year = (int) $nowWib->year;
        }

        $currentMonthDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta');
        $prevMonthDate = $currentMonthDate->copy()->subMonth();
        $nextMonthDate = $currentMonthDate->copy()->addMonth();

        $monthNamesId = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $currentMonthName = $monthNamesId[$month] . ' ' . $year;

        // Generate 14 selectable months around 1-year window
        $availableMonths = [];
        $cursor = $nowWib->copy()->subMonth()->startOfMonth();
        for ($i = 0; $i < 14; $i++) {
            $y = $cursor->year;
            $m = $cursor->month;
            $availableMonths[] = [
                'year' => $y,
                'month' => $m,
                'label' => $monthNamesId[$m] . ' ' . $y,
                'is_current' => ($y === $year && $m === $month),
            ];
            $cursor->addMonth();
        }

        // Build calendar grid days
        $daysInMonth = $currentMonthDate->daysInMonth;
        // Monday = 1, Sunday = 7
        $firstDayOfWeekIso = $currentMonthDate->dayOfWeekIso; // 1 to 7
        $leadingBlanks = $firstDayOfWeekIso - 1;

        $prevMonthDaysCount = $prevMonthDate->daysInMonth;
        $calendarDays = [];

        // 1. Leading blank days from previous month
        for ($i = $leadingBlanks - 1; $i >= 0; $i--) {
            $dayNum = $prevMonthDaysCount - $i;
            $dateStr = sprintf('%04d-%02d-%02d', $prevMonthDate->year, $prevMonthDate->month, $dayNum);
            $calendarDays[] = [
                'day' => $dayNum,
                'date' => $dateStr,
                'is_current_month' => false,
                'is_today' => false,
                'is_weekend' => false,
            ];
        }

        // 2. Days in the current month
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = ($dateStr === $nowWib->format('Y-m-d'));
            $dayCarbon = Carbon::create($year, $month, $d, 0, 0, 0, 'Asia/Jakarta');
            $isWeekend = $dayCarbon->isSaturday() || $dayCarbon->isSunday();

            $calendarDays[] = [
                'day' => $d,
                'date' => $dateStr,
                'is_current_month' => true,
                'is_today' => $isToday,
                'is_weekend' => $isWeekend,
                'formatted' => $dayCarbon->isoFormat('dddd, D MMMM Y'),
            ];
        }

        // 3. Trailing blank days to fill the final week row (multiple of 7)
        $totalSlots = count($calendarDays);
        $remainder = $totalSlots % 7;
        if ($remainder !== 0) {
            $trailingCount = 7 - $remainder;
            for ($d = 1; $d <= $trailingCount; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $nextMonthDate->year, $nextMonthDate->month, $d);
                $calendarDays[] = [
                    'day' => $d,
                    'date' => $dateStr,
                    'is_current_month' => false,
                    'is_today' => false,
                    'is_weekend' => false,
                ];
            }
        }

        // Query events for this month (using Asia/Jakarta converted to UTC boundaries)
        $monthStartUtc = $currentMonthDate->copy()->startOfMonth()->setTimezone('UTC');
        $monthEndUtc = $currentMonthDate->copy()->endOfMonth()->setTimezone('UTC');

        $calendarEventsQuery = EconomicEvent::whereBetween('event_time', [$monthStartUtc, $monthEndUtc]);
        if ($selectedCurrency !== 'ALL' && $selectedCurrency !== '') {
            $calendarEventsQuery->where('currency', $selectedCurrency);
        }
        if ($selectedImpact !== 'ALL' && $selectedImpact !== '') {
            $calendarEventsQuery->where('impact', ucfirst(strtolower($selectedImpact)));
        }
        $monthEvents = $calendarEventsQuery->orderBy('event_time', 'asc')->get();

        $eventsByDate = [];
        $bufferMinutes = $settings->newsGuardBufferMinutes();

        foreach ($monthEvents as $ev) {
            $evWib = Carbon::parse($ev->event_time)->setTimezone('Asia/Jakarta');
            $dateKey = $evWib->format('Y-m-d');
            $diffMinutes = abs((int) $nowWib->diffInMinutes($evWib));
            $isInBuffer = ($diffMinutes <= $bufferMinutes) && $ev->isHighImpact();

            $eventsByDate[$dateKey][] = [
                'id' => (string) $ev->_id,
                'title' => $ev->title,
                'currency' => $ev->currency,
                'country' => $ev->country,
                'impact' => $ev->impact,
                'is_high' => $ev->isHighImpact(),
                'is_medium' => $ev->isMediumImpact(),
                'time_wib' => $evWib->format('H:i'),
                'time_full_wib' => $evWib->format('d M Y H:i') . ' WIB',
                'date_formatted' => $evWib->isoFormat('dddd, D MMMM Y'),
                'forecast' => $ev->forecast ?? '-',
                'previous' => $ev->previous ?? '-',
                'actual' => $ev->actual ?? '-',
                'description' => $ev->description,
                'affected_pairs' => $ev->affected_pairs,
                'is_in_buffer' => $isInBuffer,
                'is_past' => $evWib->isPast(),
            ];
        }

        // Table list query
        $tableQuery = EconomicEvent::query();
        if ($selectedCurrency !== 'ALL' && $selectedCurrency !== '') {
            $tableQuery->where('currency', $selectedCurrency);
        }
        if ($selectedImpact !== 'ALL' && $selectedImpact !== '') {
            $tableQuery->where('impact', ucfirst(strtolower($selectedImpact)));
        }
        if ($selectedTimeframe === 'upcoming') {
            $tableQuery->where('event_time', '>=', now()->subHours(2));
        } elseif ($selectedTimeframe === 'month') {
            $tableQuery->whereBetween('event_time', [$monthStartUtc, $monthEndUtc]);
        }

        $events = $tableQuery->orderBy('event_time', 'asc')->paginate(50)->withQueryString();
        $totalEventsCount = EconomicEvent::count();
        $highImpactCount = EconomicEvent::where('impact', 'High')->count();
        $monthEventsCount = $monthEvents->count();

        return view('news', [
            'briefs' => $briefs,
            'pairs' => $pairs,
            'geminiConfigured' => $gemini->isConfigured(),
            'events' => $events,
            'totalEventsCount' => $totalEventsCount,
            'highImpactCount' => $highImpactCount,
            'monthEventsCount' => $monthEventsCount,
            'selectedCurrency' => $selectedCurrency,
            'selectedImpact' => $selectedImpact,
            'selectedTimeframe' => $selectedTimeframe,
            'viewMode' => $viewMode,
            'currencies' => EconomicCalendarService::CURRENCIES,
            'settings' => $settings,
            'lastSyncedAt' => $settings->economic_calendar_last_synced_at,
            // Calendar Grid specific variables
            'year' => $year,
            'month' => $month,
            'currentMonthName' => $currentMonthName,
            'prevYear' => $prevMonthDate->year,
            'prevMonth' => $prevMonthDate->month,
            'nextYear' => $nextMonthDate->year,
            'nextMonth' => $nextMonthDate->month,
            'availableMonths' => $availableMonths,
            'calendarDays' => $calendarDays,
            'eventsByDate' => $eventsByDate,
            'eventsByDateJson' => json_encode($eventsByDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
        ]);
    }

    public function syncCalendar(EconomicCalendarService $calendarService)
    {
        $res = $calendarService->syncOneYearCalendar();

        return redirect()->route('news.index')
            ->with('status', "Berhasil menyinkronkan {$res['total_synced']} agenda berita ekonomi ({$res['high_impact']} High Impact) untuk 1 tahun ke depan ({$res['date_from']} s/d {$res['date_to']}).");
    }

    public function fetchNews(Request $request, ForexNewsService $newsService)
    {
        $pair = $request->input('pair', 'EUR/USD');
        $brief = $newsService->fetchAndAnalyze($pair);

        return redirect()->route('news.index')
            ->with('status', "Berita & analisis makro untuk {$brief->pair} berhasil diperbarui.");
    }

    public function alerts()
    {
        $alerts = PriceAlert::orderBy('created_at', 'desc')->take(30)->get();
        $pairs = array_keys(ForexMarketService::PAIRS);

        return view('alerts', [
            'alerts' => $alerts,
            'pairs' => $pairs,
        ]);
    }

    public function storeAlert(Request $request)
    {
        $data = $request->validate([
            'pair' => 'required|string',
            'condition' => 'required|in:above,below',
            'target_price' => 'required|numeric|gt:0',
            'label' => 'required|string|max:120',
        ]);

        PriceAlert::create([
            'pair' => $data['pair'],
            'condition' => $data['condition'],
            'target_price' => (float) $data['target_price'],
            'label' => $data['label'],
            'status' => 'active',
        ]);

        return redirect()->route('alerts.index')->with('status', 'Price Alert baru ditambahkan.');
    }

    public function checkAlerts(ForexMarketService $market)
    {
        $active = PriceAlert::where('status', 'active')->get();
        $triggeredCount = 0;

        foreach ($active as $alert) {
            $latest = $market->getLatestPrice($alert->pair);
            $px = (float) $latest['price'];
            $hit = ($alert->condition === 'above' && $px >= (float) $alert->target_price)
                || ($alert->condition === 'below' && $px <= (float) $alert->target_price);

            if ($hit) {
                $alert->status = 'triggered';
                $alert->triggered_at = now();
                $alert->triggered_price = $px;
                $alert->save();
                $triggeredCount++;
            }
        }

        return redirect()->route('alerts.index')
            ->with('status', "Pengecekan selesai: {$triggeredCount} alert terpicu.");
    }

    public function dismissAlert(string $id)
    {
        $alert = PriceAlert::findOrFail($id);
        $alert->status = 'dismissed';
        $alert->save();

        return redirect()->route('alerts.index')->with('status', 'Alert ditutup.');
    }
}

