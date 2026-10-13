<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\FacebookService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    private const PLAIN = [
        'channel_name', 'host_style', 'ai_provider', 'anthropic_model', 'nine_router_base_url', 'nine_router_model',
        'tts_model', 'tts_voice', 'tts_voice_character', 'tts_voice_female', 'tts_voice_character_female',
        'short_enabled', 'short_make_time', 'short_window_end', 'short_daily_count', 'short_publish_delay_minutes', 'short_max_chars', 'short_watermark', 'short_cta', 'short_logo', 'short_bgm', 'short_illustration',
        'ffmpeg_path', 'edge_tts_path', 'edge_tts_voice', 'edge_tts_voice_female',
        'video_folder', 'default_privacy', 'default_publish_time',
        'fb_app_id', 'fb_login_config_id', 'fb_graph_version', 'fb_scopes', 'fb_sync_days', 'fb_group_hours', 'google_client_id',
    ];

    /** Rahasia: dikosongkan di form berarti "jangan diubah". */
    private const SECRETS = ['anthropic_key', 'nine_router_api_key', 'fb_app_secret', 'google_client_secret'];

    public function index(FacebookService $fb, YouTubeService $yt)
    {
        $pages = session('fb_pages') ? decrypt(session('fb_pages')) : [];

        return view('settings.index', [
            's' => fn ($k) => Setting::val($k),
            'has' => fn ($k) => (bool) Setting::val($k),
            'fb' => $fb,
            'yt' => $yt,
            'pages' => $pages,
        ]);
    }

    public function update(Request $r)
    {
        $r->validate([
            'video_folder' => 'nullable|string|max:500',
            'default_publish_time' => 'nullable|date_format:H:i',
            'default_privacy' => 'nullable|in:public,unlisted,private',
            'short_make_time' => 'nullable|date_format:H:i',
            'short_window_end' => 'nullable|date_format:H:i',
            'short_daily_count' => 'nullable|integer|min:1|max:20',
            'short_publish_delay_minutes' => 'nullable|integer|min:5|max:720',
            'short_max_chars' => 'nullable|integer|min:100|max:2000',
            'short_enabled' => 'nullable|in:0,1',
            'fb_sync_days' => 'nullable|integer|min:1|max:365',
            'fb_group_hours' => 'nullable|integer|min:1|max:72',
        ]);
        foreach (self::PLAIN as $k) {
            if ($r->has($k)) {
                Setting::put($k, trim((string) $r->input($k)));
            }
        }
        foreach (self::SECRETS as $k) {
            if ($r->filled($k)) {
                Setting::put($k, trim((string) $r->input($k)));
            }
        }
        $folder = trim((string) $r->input('video_folder'));
        if ($folder && ! is_dir($folder)) {
            return back()->with('err', 'Pengaturan disimpan, tapi folder video tidak ditemukan: '.$folder);
        }

        return back()->with('ok', 'Pengaturan disimpan.');
    }

    public function facebookRedirect(FacebookService $fb)
    {
        if (! $fb->appConfigured()) {
            return redirect()->route('settings')->with('err', 'Isi App ID dan App Secret Facebook dulu.');
        }
        $state = Str::random(32);
        session(['oauth_state' => $state]);

        return redirect()->away($fb->authUrl($state));
    }

    public function facebookCallback(Request $r, FacebookService $fb)
    {
        if (! $r->filled('code') || $r->get('state') !== session('oauth_state')) {
            return redirect()->route('settings')->with('err', 'Login Facebook dibatalkan atau tidak valid. '.$r->get('error_description'));
        }
        try {
            $pages = $fb->pagesFromCode($r->get('code'));
        } catch (\Throwable $e) {
            return redirect()->route('settings')->with('err', $e->getMessage());
        }
        if (! $pages) {
            return redirect()->route('settings')->with('err', 'Tidak ada Page yang bisa diakses. Pastikan Anda admin Page dan izin Page dicentang saat login.');
        }
        session(['fb_pages' => encrypt($pages)]);

        return redirect()->route('settings')->with('ok', 'Login berhasil. Pilih Page yang dipakai.');
    }

    public function facebookPage(Request $r)
    {
        $pages = session('fb_pages') ? decrypt(session('fb_pages')) : [];
        $page = collect($pages)->firstWhere('id', $r->input('page_id'));
        if (! $page) {
            return back()->with('err', 'Page tidak ditemukan. Login ulang ke Facebook.');
        }
        Setting::put('fb_page_id', $page['id']);
        Setting::put('fb_page_name', $page['name']);
        Setting::put('fb_page_token', $page['access_token']);
        session()->forget('fb_pages');

        return back()->with('ok', 'Page "'.$page['name'].'" terhubung. Klik "Sinkron DM sekarang" di Dashboard untuk menarik pesan.');
    }

    public function facebookDisconnect()
    {
        foreach (['fb_page_id', 'fb_page_name', 'fb_page_token', 'fb_last_sync'] as $k) {
            Setting::put($k, null);
        }

        return back()->with('ok', 'Facebook diputuskan.');
    }

    public function googleRedirect(YouTubeService $yt)
    {
        if (! $yt->appConfigured()) {
            return redirect()->route('settings')->with('err', 'Isi Client ID dan Client Secret Google dulu.');
        }
        $state = Str::random(32);
        session(['oauth_state' => $state]);

        return redirect()->away($yt->authUrl($state));
    }

    public function googleCallback(Request $r, YouTubeService $yt)
    {
        if (! $r->filled('code') || $r->get('state') !== session('oauth_state')) {
            return redirect()->route('settings')->with('err', 'Login Google dibatalkan atau tidak valid.');
        }
        try {
            $yt->connectWithCode($r->get('code'));
        } catch (\Throwable $e) {
            return redirect()->route('settings')->with('err', $e->getMessage());
        }

        return redirect()->route('settings')->with('ok', 'YouTube terhubung ke channel "'.Setting::val('yt_channel_title').'".');
    }

    public function youtubeDisconnect()
    {
        foreach (['yt_refresh_token', 'yt_access_token', 'yt_token_expires', 'yt_channel_title'] as $k) {
            Setting::put($k, null);
        }

        return back()->with('ok', 'YouTube diputuskan.');
    }
}
