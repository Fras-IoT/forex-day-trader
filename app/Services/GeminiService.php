<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\TraderSetting;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1/interactions';

    public function isConfigured(): bool
    {
        return TraderSetting::current()->resolvedGeminiKey() !== '';
    }

    /**
     * Call Gemini Interactions API (v1) using gemini-3.8-flash.
     *
     * @return array{configured: bool, ok: bool, interaction_id: ?string, text: ?string, error: ?string}
     */
    public function interact(
        string $prompt,
        ?string $systemInstruction = null,
        ?string $previousInteractionId = null,
        ?string $pair = null,
        bool $saveHistory = false
    ): array {
        $settings = TraderSetting::current();
        $apiKey = $settings->resolvedGeminiKey();

        if ($apiKey === '') {
            return [
                'configured' => false,
                'ok' => false,
                'interaction_id' => null,
                'text' => null,
                'error' => 'Gemini API token belum diatur. Silakan tambahkan token di halaman Settings atau file .env (GEMINI_API_KEY).',
            ];
        }

        $model = $settings->gemini_model ?: config('services.gemini.model', 'gemini-3.8-flash');

        $payload = [
            'model' => $model,
            'input' => $prompt,
            'store' => true,
        ];

        if ($systemInstruction !== null && $systemInstruction !== '') {
            $payload['system_instruction'] = $systemInstruction;
        }

        if ($previousInteractionId !== null && $previousInteractionId !== '') {
            $payload['previous_interaction_id'] = $previousInteractionId;
        }

        try {
            $resp = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::ENDPOINT, $payload);

            if (!$resp->successful()) {
                $errBody = $resp->json();
                $errMsg = $errBody['error']['message'] ?? ('HTTP ' . $resp->status());

                return [
                    'configured' => true,
                    'ok' => false,
                    'interaction_id' => null,
                    'text' => null,
                    'error' => "Gemini API error: {$errMsg}",
                ];
            }

            $data = $resp->json();
            $text = $this->extractOutputText($data);
            $interactionId = $data['id'] ?? null;

            if ($saveHistory) {
                AiInteraction::create([
                    'interaction_id' => $interactionId,
                    'previous_interaction_id' => $previousInteractionId,
                    'pair' => $pair,
                    'role' => 'user',
                    'content' => $prompt,
                    'model' => $model,
                ]);
                AiInteraction::create([
                    'interaction_id' => $interactionId,
                    'previous_interaction_id' => $previousInteractionId,
                    'pair' => $pair,
                    'role' => 'assistant',
                    'content' => $text ?? '',
                    'model' => $model,
                ]);
            }

            return [
                'configured' => true,
                'ok' => true,
                'interaction_id' => $interactionId,
                'text' => $text,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'ok' => false,
                'interaction_id' => null,
                'text' => null,
                'error' => 'Gagal menghubungi Gemini API: ' . $e->getMessage(),
            ];
        }
    }

    private function extractOutputText(array $data): ?string
    {
        if (!empty($data['output_text']) && is_string($data['output_text'])) {
            return trim($data['output_text']);
        }

        $chunks = [];
        $outputs = $data['outputs'] ?? [];
        foreach ($outputs as $output) {
            if (($output['type'] ?? '') === 'text' && isset($output['text'])) {
                $chunks[] = $output['text'];
            }
            if (!empty($output['content']) && is_array($output['content'])) {
                foreach ($output['content'] as $part) {
                    if (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                        $chunks[] = $part['text'];
                    }
                }
            }
        }

        $joined = trim(implode("\n", $chunks));

        return $joined !== '' ? $joined : null;
    }
}

