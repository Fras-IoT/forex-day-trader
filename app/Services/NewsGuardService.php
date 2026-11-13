<?php

namespace App\Services;

use App\Models\EconomicEvent;
use App\Models\TraderSetting;
use Carbon\Carbon;

class NewsGuardService
{
    /**
     * Map a pair string to its underlying currencies.
     */
    public function getCurrenciesForPair(string $pair): array
    {
        $clean = strtoupper(trim(str_replace(' ', '', $pair)));
        if (str_contains($clean, '/')) {
            $parts = explode('/', $clean);
            $base = $parts[0] ?? '';
            $quote = $parts[1] ?? '';
            if ($base === 'XAU') {
                return ['USD'];
            }
            return array_filter([$base, $quote]);
        }

        if (strlen($clean) === 6) {
            $base = substr($clean, 0, 3);
            $quote = substr($clean, 3, 3);
            if ($base === 'XAU') {
                return ['USD'];
            }
            return [$base, $quote];
        }

        if (str_starts_with($clean, 'XAU') || str_starts_with($clean, 'GOLD')) {
            return ['USD'];
        }

        return ['USD'];
    }

    /**
     * Check if a pair has High-Impact news within the safety buffer window (e.g. +/- 30 minutes).
     *
     * @return array{has_news: bool, is_blocked: bool, buffer_minutes: int, event: ?EconomicEvent, message: string, time_status: string}
     */
    public function checkNewsRisk(string $pair, ?int $bufferMinutes = null): array
    {
        $settings = TraderSetting::current();
        $buffer = $bufferMinutes ?? $settings->newsGuardBufferMinutes();
        $enabled = $settings->isNewsGuardEnabled();

        $currencies = $this->getCurrenciesForPair($pair);
        $now = now();
        $windowStart = $now->copy()->subMinutes($buffer);
        $windowEnd = $now->copy()->addMinutes($buffer);

        $events = EconomicEvent::whereIn('currency', $currencies)
            ->where('impact', 'High')
            ->where('event_time', '>=', $windowStart)
            ->where('event_time', '<=', $windowEnd)
            ->orderBy('event_time', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return [
                'has_news' => false,
                'is_blocked' => false,
                'buffer_minutes' => $buffer,
                'event' => null,
                'message' => 'Aman dari jadwal berita High Impact.',
                'time_status' => 'Clear',
            ];
        }

        /** @var EconomicEvent $nearest */
        $nearest = $events->first();
        $eventTime = Carbon::parse($nearest->event_time);
        $diffMinutes = abs((int) $now->diffInMinutes($eventTime));

        if ($eventTime->isFuture()) {
            $timeStatus = $diffMinutes === 0 ? 'Sedang rilis saat ini' : "Rilis dalam {$diffMinutes} menit lagi";
        } else {
            $timeStatus = $diffMinutes === 0 ? 'Baru saja rilis' : "Baru saja rilis {$diffMinutes} menit lalu (Volatilitas masih tinggi)";
        }

        $msg = "Dideteksi berita HIGH IMPACT '{$nearest->title}' ({$nearest->currency}) {$timeStatus}. Jendela proteksi News Guard {$buffer} menit aktif.";

        return [
            'has_news' => true,
            'is_blocked' => $enabled,
            'buffer_minutes' => $buffer,
            'event' => $nearest,
            'message' => $msg,
            'time_status' => $timeStatus,
        ];
    }
}

