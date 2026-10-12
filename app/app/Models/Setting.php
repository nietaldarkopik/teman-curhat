<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Kunci yang disimpan terenkripsi di database. */
    public const SECRET = [
        'anthropic_key', 'fb_app_secret', 'fb_page_token',
        'google_client_secret', 'yt_refresh_token', 'yt_access_token',
        'nine_router_api_key',
    ];

    public const DEFAULTS = [
        'ai_provider' => 'anthropic',
        'anthropic_model' => 'claude-sonnet-5',
        'nine_router_base_url' => 'http://localhost:20128/v1',
        'nine_router_model' => 'Kombo',
        'channel_name' => 'Teman Curhat',
        'host_style' => "Hangat dan santai seperti kakak yang mendengarkan. Memakai 'kamu' ke pengirim curhat dan 'teman-teman' ke penonton. Tidak menggurui, jujur, sesekali humor ringan bila suasananya pas.",
        'fb_graph_version' => 'v23.0',
        'fb_login_config_id' => '',
        'fb_scopes' => 'pages_show_list,pages_messaging,pages_read_engagement,pages_manage_posts',
        'fb_sync_days' => '30',
        'fb_group_hours' => '6',
        'default_privacy' => 'public',
        'default_publish_time' => '19:00',
        'video_folder' => '',
        'short_enabled' => '1',
        'short_make_time' => '09:00',
        'short_window_end' => '21:00',
        'short_daily_count' => '5',
        'short_publish_delay_minutes' => '20',
        'short_max_chars' => '900',
        'short_watermark' => '@temancurhat',
        'short_cta' => 'Mau curhat? Kirim DM ke Page Teman Curhat',
        'ffmpeg_path' => 'ffmpeg',
        'edge_tts_path' => 'edge-tts',
        'edge_tts_voice' => 'id-ID-ArdiNeural',
        'tts_model' => 'gemini/gemini-2.5-flash-preview-tts',
        'tts_voice' => 'Algenib',
        'tts_voice_character' => "A warm, gentle Indonesian male voice, sounding like a man in his late 20s (around 25–30 years old). Speak in a calm, natural, and empathetic tone, like a caring friend who genuinely listens without judging. The voice should feel comforting, sincere, and emotionally present, creating a safe space for people to share their feelings and personal stories.\n\nUse a soft, moderately low pitch with a smooth, slightly deep, and soothing vocal quality. Speak at a relaxed, natural pace with gentle pauses between sentences. The delivery should feel personal and conversational, never robotic, overly dramatic, or excessively cheerful.\n\nSpeak in natural Bahasa Indonesia with clear pronunciation and a subtle, friendly warmth. Convey patience, understanding, and reassurance, as if sitting beside someone who needs to be heard. Avoid sounding like a news presenter, radio announcer, therapist, or formal customer service representative.\n\nThe overall feeling should be like a trustworthy male friend saying, \"Aku di sini, ya. Cerita aja, aku dengerin.\"",
        'edge_tts_voice_female' => 'id-ID-GadisNeural',
        'tts_voice_female' => 'Despina',
        'tts_voice_character_female' => "A warm, gentle Indonesian female voice, sounding like a woman in her mid-to-late 20s. Speak in a calm, natural, and empathetic tone, like a caring friend who genuinely listens without judging. The voice should feel comforting, sincere, and emotionally present, creating a safe space for people to share their feelings and personal stories.\n\nUse a soft, warm pitch with a smooth, clear, and soothing vocal quality — not high-pitched, cutesy, or overly bright. Speak at a relaxed, natural pace with gentle pauses between sentences. The delivery should feel personal and conversational, never robotic, overly dramatic, or excessively cheerful.\n\nSpeak in natural Bahasa Indonesia with clear pronunciation and a subtle, friendly warmth. Convey patience, understanding, and reassurance, as if sitting beside someone who needs to be heard. Avoid sounding like a news presenter, radio announcer, therapist, or formal customer service representative.\n\nThe overall feeling should be like a trustworthy female friend saying, \"Aku di sini, ya. Cerita aja, aku dengerin.\"",
    ];

    protected static array $memo = [];

    public static function val(string $key, $default = null)
    {
        if (! array_key_exists($key, static::$memo)) {
            $row = static::query()->where('key', $key)->first();
            $value = $row?->value;
            if ($value !== null && $value !== '' && in_array($key, self::SECRET, true)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    $value = null;
                }
            }
            static::$memo[$key] = $value;
        }

        $v = static::$memo[$key];
        if ($v === null || $v === '') {
            return $default ?? (self::DEFAULTS[$key] ?? $v);
        }

        return $v;
    }

    public static function put(string $key, $value): void
    {
        $store = $value;
        if ($value !== null && $value !== '' && in_array($key, self::SECRET, true)) {
            $store = Crypt::encryptString((string) $value);
        }
        static::query()->updateOrCreate(['key' => $key], ['value' => $store]);
        static::$memo[$key] = $value;
    }

    public static function forgetMemo(): void
    {
        static::$memo = [];
    }
}
