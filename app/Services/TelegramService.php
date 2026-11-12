<?php

namespace App\Services;

use App\Models\TraderSetting;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    public function isConfigured(): bool
    {
        $settings = TraderSetting::current();

        return $settings->resolvedTelegramToken() !== ''
            && $settings->resolvedTelegramChatId() !== '';
    }

    /**
     * Send an HTML-formatted message to the configured Telegram chat.
     *
     * @return array{ok: bool, message_id: ?int, error: ?string}
     */
    public function sendMessage(string $message, ?array $inlineKeyboard = null): array
    {
        $settings = TraderSetting::current();
        $token = $settings->resolvedTelegramToken();
        $chatId = $settings->resolvedTelegramChatId();

        if ($token === '' || $chatId === '') {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => 'Telegram Bot Token atau Chat ID belum diatur di Settings / .env.',
            ];
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        try {
            $resp = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            if (!$resp->successful()) {
                $desc = $resp->json('description') ?? ('HTTP ' . $resp->status());

                return ['ok' => false, 'message_id' => null, 'error' => "Telegram API error: {$desc}"];
            }

            return [
                'ok' => true,
                'message_id' => $resp->json('result.message_id'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message_id' => null, 'error' => 'Koneksi Telegram gagal: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch incoming updates (used to read Approve/Reject button clicks without needing HTTPS webhook).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0): array
    {
        $token = TraderSetting::current()->resolvedTelegramToken();
        if ($token === '') {
            return [];
        }

        try {
            $resp = Http::timeout(8)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $offset,
                'limit' => 25,
                'allowed_updates' => json_encode(['callback_query']),
            ]);

            if (!$resp->successful() || !$resp->json('ok')) {
                return [];
            }

            return $resp->json('result') ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text): void
    {
        $token = TraderSetting::current()->resolvedTelegramToken();
        if ($token === '') {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => false,
            ]);
        } catch (\Throwable $e) {
            // Best-effort acknowledgment
        }
    }
}
