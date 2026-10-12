<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Net;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Text-to-speech lewat instance 9Router lokal (endpoint OpenAI-compatible
 * /audio/speech, gateway yang sama yang dipakai clipper-tool). Karakter suara
 * ditempel sebagai instruksi gaya bicara di depan teks yang dibacakan — ini cara
 * Gemini TTS menerima arahan gaya (bukan lewat field terpisah), jadi pendekatan
 * ini paling luwes dipakai lintas model yang tersedia di 9Router.
 *
 * Ada dua profil suara: "male" (default, dipakai bila $gender bukan "female")
 * dan "female" — dipilih per pemanggilan, bukan satu suara global, supaya
 * MakeShort bisa menyesuaikan suara pembaca/pembalas dengan gender pengirim.
 */
class TtsService
{
    public function configured(): bool
    {
        return (bool) Setting::val('nine_router_api_key') && (bool) Setting::val('tts_model');
    }

    /** Sintesis teks jadi audio (WAV), simpan ke storage/app/public/tts, kembalikan path relatif disk "public". */
    public function synthesize(string $text, string $filename, string $gender = 'male'): string
    {
        $key = Setting::val('nine_router_api_key');
        $baseUrl = Setting::val('nine_router_base_url');
        $model = Setting::val('tts_model');
        $voice = $this->voiceSetting($gender);
        if (! $key || ! $model) {
            throw new RuntimeException('TTS belum lengkap diisi (API key/model 9Router). Buka menu Pengaturan.');
        }

        $character = trim((string) Setting::val($this->characterSetting($gender)));
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
        Storage::disk('public')->put($path, $res->body());

        return $path;
    }

    /**
     * Seperti synthesize(), tapi bila 9Router gagal atau kuotanya habis, otomatis pindah
     * ke suara cadangan (edge-tts, gratis, perlu `pip install edge-tts`). Mengembalikan
     * path absolut file audio dan nama mesin yang dipakai.
     *
     * @return array{0:string,1:string} [path absolut, 'nine_router'|'edge_tts']
     */
    public function speak(string $text, string $filename, string $gender = 'male'): array
    {
        $primaryError = null;
        if ($this->configured()) {
            try {
                $rel = $this->synthesize($text, $filename, $gender);

                return [Storage::disk('public')->path($rel), 'nine_router'];
            } catch (\Throwable $e) {
                $primaryError = $e->getMessage();
            }
        } else {
            $primaryError = 'TTS 9Router belum dikonfigurasi.';
        }

        try {
            return [$this->edgeTts($text, $filename, $gender), 'edge_tts'];
        } catch (\Throwable $e) {
            throw new RuntimeException('Semua suara gagal. 9Router: '.$primaryError.' | Cadangan: '.$e->getMessage());
        }
    }

    private function voiceSetting(string $gender): string
    {
        return (string) Setting::val($gender === 'female' ? 'tts_voice_female' : 'tts_voice');
    }

    private function characterSetting(string $gender): string
    {
        return $gender === 'female' ? 'tts_voice_character_female' : 'tts_voice_character';
    }

    private function edgeTts(string $text, string $filename, string $gender = 'male'): string
    {
        $bin = trim((string) Setting::val('edge_tts_path')) ?: 'edge-tts';
        $voiceKey = $gender === 'female' ? 'edge_tts_voice_female' : 'edge_tts_voice';
        $voice = trim((string) Setting::val($voiceKey)) ?: ($gender === 'female' ? 'id-ID-GadisNeural' : 'id-ID-ArdiNeural');

        Storage::disk('public')->makeDirectory('tts');
        $out = Storage::disk('public')->path("tts/{$filename}-edge.mp3");
        $in = Storage::disk('public')->path("tts/{$filename}-edge.txt");
        file_put_contents($in, $text);

        $p = @proc_open([$bin, '--voice', $voice, '--file', $in, '--write-media', $out], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($p)) {
            @unlink($in);
            throw new RuntimeException("edge-tts tidak ditemukan ({$bin}). Pasang dengan `pip install edge-tts` atau isi path-nya di Pengaturan.");
        }
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        @unlink($in);

        if ($code !== 0 || ! is_file($out) || filesize($out) < 1000) {
            throw new RuntimeException('edge-tts gagal: '.mb_substr(trim((string) $err), -300));
        }

        return $out;
    }
}
