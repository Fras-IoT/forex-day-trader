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
        'gemini_api_key',
        'gemini_model',
        'telegram_bot_token',
        'telegram_chat_id',
        'auto_scan_enabled',
        'auto_scan_min_score',
        'auto_scan_min_prob',
        'auto_close_on_hit',
        'twelve_data_api_key',
    ];

    public static function current(): self
    {
        $doc = static::where('key', 'default')->first();
        if ($doc) {
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
            'gemini_api_key' => '',
            'gemini_model' => config('services.gemini.model', 'gemini-3.8-flash'),
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'auto_scan_enabled' => true,
            'auto_scan_min_score' => 70,
            'auto_scan_min_prob' => 55,
            'auto_close_on_hit' => true,
            'twelve_data_api_key' => '',
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
}

