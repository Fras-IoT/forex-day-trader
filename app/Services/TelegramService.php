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
     * @return array{ok: bool, error: ?string}
     */
    public function sendMessage(string $message): array
    {
        $settings = TraderSetting::current();
        $token = $settings->resolvedTelegramToken();
        $chatId = $settings->resolvedTelegramChatId();

        if ($token === '' || $chatId === '') {
            return [
                'ok' => false,
                'error' => 'Telegram Bot Token atau Chat ID belum diatur di Settings / .env.',
            ];
        }

        try {
            $resp = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$resp->successful()) {
                $desc = $resp->json('description') ?? ('HTTP ' . $resp->status());

                return ['ok' => false, 'error' => "Telegram API error: {$desc}"];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Koneksi Telegram gagal: ' . $e->getMessage()];
        }
    }
}

