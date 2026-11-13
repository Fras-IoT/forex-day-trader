<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TraderSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'settings';

    protected $fillable = [
        'key',
        'account_balance',
        'max_loss_per_trade_usd',
        'max_daily_loss_usd',
        'max_trades_per_day',
        'min_reward_risk',
        'max_position_pct',
        'leverage',
        'paper_mode',
        'blocked_pairs',
        'allowed_sessions',
        'flat_by_utc',
        'flat_by_wib',
        'gemini_api_key',
        'gemini_model',
        'telegram_bot_token',
        'telegram_chat_id',
        'auto_scan_enabled',
        'auto_scan_min_score',
        'auto_scan_min_prob',
        'auto_close_on_hit',
        'twelve_data_api_key',
        'mt5_bridge_token',
        'last_telegram_update_id',
        'trading_mode',
        'news_guard_enabled',
        'news_guard_buffer_minutes',
        'news_guard_min_impact',
        'economic_calendar_last_synced_at',
    ];

    public function isAutoTrading(): bool
    {
        return ($this->trading_mode ?? 'manual') === 'auto';
    }

    public function isManualTrading(): bool
    {
        return !$this->isAutoTrading();
    }

    public function isNewsGuardEnabled(): bool
    {
        return (bool) ($this->news_guard_enabled ?? true);
    }

    public function newsGuardBufferMinutes(): int
    {
        return max(5, min(120, (int) ($this->news_guard_buffer_minutes ?? 30)));
    }

    public function resolvedFlatByWib(): string
    {
        if (!empty($this->flat_by_wib)) {
            return $this->flat_by_wib;
        }
        if (!empty($this->flat_by_utc)) {
            try {
                return \Carbon\Carbon::createFromFormat('H:i', $this->flat_by_utc, 'UTC')->setTimezone('Asia/Jakarta')->format('H:i');
            } catch (\Throwable $e) {}
        }
        return '04:55';
    }

    public static function current(): self
    {
        $doc = static::where('key', 'default')->orderBy('updated_at', 'desc')->first();
        if ($doc) {
            // Bersihkan dokumen duplikat yang mungkin timbul dari migrasi atau multi-proses
            static::where('key', 'default')->where('_id', '!=', $doc->_id)->delete();
            return $doc;
        }

        return static::create([
            'key' => 'default',
            'account_balance' => 10000.00,
            'max_loss_per_trade_usd' => 100.00,
            'max_daily_loss_usd' => 300.00,
            'max_trades_per_day' => 5,
            'min_reward_risk' => 1.5,
            'max_position_pct' => 50,
            'leverage' => 30,
            'paper_mode' => true,
            'blocked_pairs' => [],
            'allowed_sessions' => ['tokyo', 'london', 'new_york'],
            'flat_by_utc' => '21:55',
            'flat_by_wib' => '04:55',
            'gemini_api_key' => '',
            'gemini_model' => config('services.gemini.model', 'gemini-3.8-flash'),
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'auto_scan_enabled' => true,
            'auto_scan_min_score' => 70,
            'auto_scan_min_prob' => 55,
            'auto_close_on_hit' => true,
            'twelve_data_api_key' => '',
            'mt5_bridge_token' => '',
            'trading_mode' => 'manual',
            'news_guard_enabled' => true,
            'news_guard_buffer_minutes' => 30,
        ]);
    }

    public function resolvedGeminiKey(): string
    {
        $dbKey = trim((string) ($this->gemini_api_key ?? ''));
        if ($dbKey !== '') {
            return $dbKey;
        }

        return trim((string) config('services.gemini.api_key', ''));
    }

    public function resolvedTelegramToken(): string
    {
        $dbToken = trim((string) ($this->telegram_bot_token ?? ''));
        if ($dbToken !== '') {
            return $dbToken;
        }

        return trim((string) config('services.telegram.bot_token', ''));
    }

    public function resolvedTelegramChatId(): string
    {
        $dbChat = trim((string) ($this->telegram_chat_id ?? ''));
        if ($dbChat !== '') {
            return $dbChat;
        }

        return trim((string) config('services.telegram.chat_id', ''));
    }

    public function resolvedTwelveDataKey(): string
    {
        $dbKey = trim((string) ($this->twelve_data_api_key ?? ''));
        if ($dbKey !== '') {
            return $dbKey;
        }

        return trim((string) config('services.twelvedata.api_key', ''));
    }

    public function resolvedMt5BridgeToken(): string
    {
        $dbToken = trim((string) ($this->mt5_bridge_token ?? ''));
        if ($dbToken !== '') {
            return $dbToken;
        }

        return trim((string) config('services.mt5.bridge_token', ''));
    }
}

