<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Net;
use RuntimeException;

/**
 * Text-to-speech lewat instance 9Router lokal (endpoint OpenAI-compatible
 * /audio/speech, gateway yang sama yang dipakai clipper-tool). Karakter suara
 * (Setting::tts_voice_character) ditempel sebagai instruksi gaya bicara di
 * depan teks yang dibacakan — ini cara Gemini TTS menerima arahan gaya
 * (bukan lewat field terpisah), jadi pendekatan ini paling luwes dipakai
 * lintas model yang tersedia di 9Router.
 */
class TtsService
{
    public function configured(): bool
    {
        return (bool) Setting::val('nine_router_api_key') && (bool) Setting::val('tts_model');
    }

    /** Sintesis teks jadi audio (WAV), simpan ke storage/app/public/tts, kembalikan path relatif disk "public". */
    public function synthesize(string $text, string $filename): string
    {
        $key = Setting::val('nine_router_api_key');
        $baseUrl = Setting::val('nine_router_base_url');
        $model = Setting::val('tts_model');
        $voice = Setting::val('tts_voice');
        if (! $key || ! $model) {
            throw new RuntimeException('TTS belum lengkap diisi (API key/model 9Router). Buka menu Pengaturan.');
        }

        $character = trim((string) Setting::val('tts_voice_character'));
        $input = $character ? "{$character}\n\n---\n\nUcapkan naskah berikut sesuai karakter suara di atas:\n\n{$text}" : $text;

        $modelId = $voice ? "{$model}/{$voice}" : $model;

        $res = Net::http()
            ->timeout(120)
            ->withToken($key)
            ->post(rtrim($baseUrl, '/').'/audio/speech', [
                'model' => $modelId,
                'input' => $input,
            ]);

        if ($res->failed()) {
            throw new RuntimeException('9Router TTS: '.$res->body());
        }

        $dir = 'tts';
        $path = "{$dir}/{$filename}.wav";
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $res->body());

        return $path;
    }
}
