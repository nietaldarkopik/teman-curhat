<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Net;
use RuntimeException;

class ClaudeService
{
    public function configured(): bool
    {
        return Setting::val('ai_provider') === 'nine_router'
            ? (bool) Setting::val('nine_router_api_key')
            : (bool) Setting::val('anthropic_key');
    }

    public function text(string $prompt, int $maxTokens = 4000): string
    {
        return Setting::val('ai_provider') === 'nine_router'
            ? $this->textViaNineRouter($prompt, $maxTokens)
            : $this->textViaAnthropic($prompt, $maxTokens);
    }

    private function textViaAnthropic(string $prompt, int $maxTokens): string
    {
        $key = Setting::val('anthropic_key');
        if (! $key) {
            throw new RuntimeException('API key Claude belum diisi. Buka menu Pengaturan.');
        }

        $res = Net::http()
            ->timeout(180)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => Setting::val('anthropic_model'),
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        if ($res->failed()) {
            $msg = $res->json('error.message') ?: ('HTTP '.$res->status());
            throw new RuntimeException('Claude API: '.$msg);
        }

        return collect($res->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    /**
     * Lewat instance 9Router lokal (gateway OpenAI-compatible yang dipakai juga
     * oleh clipper-tool, jalan di mesin ini sendiri — lihat start-all.bat: "9router").
     * Perlu proses 9router itu sedang berjalan di NINE_ROUTER_BASE_URL.
     */
    private function textViaNineRouter(string $prompt, int $maxTokens): string
    {
        $key = Setting::val('nine_router_api_key');
        $baseUrl = Setting::val('nine_router_base_url');
        $model = Setting::val('nine_router_model');
        if (! $key || ! $model) {
            throw new RuntimeException('9Router belum lengkap diisi (API key/model). Buka menu Pengaturan.');
        }

        $req = Net::http()->timeout(180)->withToken($key);

        $res = $req->post(rtrim($baseUrl, '/').'/chat/completions', [
            'model' => $model,
            'stream' => false,
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if ($res->failed()) {
            throw new RuntimeException('9Router: '.$res->body());
        }

        return (string) $res->json('choices.0.message.content');
    }

    public function json(string $prompt, int $maxTokens = 4000): array
    {
        $text = $this->text($prompt."\n\nBalas hanya dengan satu objek JSON yang valid, tanpa teks lain dan tanpa blok kode.", $maxTokens);
        $a = strpos($text, '{');
        $b = strrpos($text, '}');
        if ($a === false || $b === false || $b < $a) {
            throw new RuntimeException('Jawaban AI tidak berisi JSON. Coba lagi.');
        }
        $data = json_decode(substr($text, $a, $b - $a + 1), true);
        if (! is_array($data)) {
            throw new RuntimeException('Jawaban AI tidak bisa dibaca sebagai JSON. Coba lagi.');
        }

        return $data;
    }
}
