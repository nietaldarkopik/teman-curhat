<?php

namespace App\Console\Commands;

use App\Models\Curhat;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Publication;
use App\Models\Setting;
use App\Services\AiWriter;
use App\Services\ClaudeService;
use App\Services\ShortRenderer;
use App\Services\TtsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MakeShort extends Command
{
    protected $signature = 'curhat:make-short
        {--curhat= : ID curhat tertentu (abaikan pemilihan otomatis)}
        {--force : abaikan jam, batas satu video per hari, dan jeda percobaan ulang}
        {--no-upload : buat video saja, jangan masuk antrean upload}';

    protected $description = 'Buat satu video short dari DM curhat (curhatan lalu balasan) dan masukkan ke antrean upload YouTube + Facebook';

    private const MAX_ATTEMPTS_PER_DAY = 3;
    private const RETRY_AFTER_MINUTES = 30;

    public function handle(AiWriter $ai, TtsService $tts, ClaudeService $claude): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! $this->due()) {
            return self::SUCCESS;
        }
        if (! $claude->configured()) {
            $this->error('AI (Claude/9Router) belum dikonfigurasi di Pengaturan.');

            return self::FAILURE;
        }

        $this->noteAttempt();

        try {
            $curhat = $this->pick($ai);
            if (! $curhat) {
                $this->line('Tidak ada curhat yang memenuhi syarat hari ini (belum ada DM baru, sensitif, atau tidak diizinkan).');
                $this->resetAttempts();

                return self::SUCCESS;
            }
            $this->info("Curhat #{$curhat->id} dipilih.");

            $project = $this->build($curhat, $ai, $tts);
        } catch (\Throwable $e) {
            Log::error('curhat.make-short.failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->resetAttempts();
        $this->info("Short #{$project->id} siap: {$project->video_path}");

        return self::SUCCESS;
    }

    // ------------------------------------------------------------ jadwal

    private function due(): bool
    {
        if (Setting::val('short_enabled') === '0') {
            return false;
        }
        $time = (string) Setting::val('short_make_time');
        if (preg_match('/^\d{1,2}:\d{2}$/', $time) && now()->lt(now()->setTimeFromTimeString($time))) {
            return false;
        }
        if (Project::where('kind', 'short')->whereDate('created_at', today())->exists()) {
            return false;
        }
        if ((string) Setting::val('short_attempt_date') === today()->toDateString()) {
            if ((int) Setting::val('short_attempt_count') >= self::MAX_ATTEMPTS_PER_DAY) {
                return false;
            }
            $last = Setting::val('short_attempt_at');
            if ($last && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($last), true) < self::RETRY_AFTER_MINUTES) {
                return false;
            }
        }

        return true;
    }

    private function noteAttempt(): void
    {
        $same = (string) Setting::val('short_attempt_date') === today()->toDateString();
        Setting::put('short_attempt_count', (string) ($same ? ((int) Setting::val('short_attempt_count')) + 1 : 1));
        Setting::put('short_attempt_date', today()->toDateString());
        Setting::put('short_attempt_at', now()->toDateTimeString());
    }

    private function resetAttempts(): void
    {
        Setting::put('short_attempt_count', '0');
    }

    // ------------------------------------------------------------ pilih curhat

    private function pick(AiWriter $ai): ?Curhat
    {
        $max = (int) Setting::val('short_max_chars');

        if ($id = $this->option('curhat')) {
            $c = Curhat::findOrFail($id);

            return $this->eligible($c, $ai, $max) ? $c : null;
        }

        $candidates = Curhat::query()
            ->whereIn('status', ['baru', 'dibaca'])
            ->where('is_sensitive', false)
            ->where('consent_status', '!=', 'denied')
            ->where('char_count', '>=', 60)
            ->where('char_count', '<=', $max + 200)
            ->orderByRaw("CASE consent_status WHEN 'granted' THEN 0 ELSE 1 END")
            ->orderByDesc('is_starred')
            ->orderBy('received_at')
            ->limit(8)
            ->get();

        foreach ($candidates as $c) {
            if ($this->eligible($c, $ai, $max)) {
                return $c;
            }
        }

        return null;
    }

    /** Anonimkan dulu bila belum; teks asli (dengan nama) tidak pernah dipakai di video. */
    private function eligible(Curhat $c, AiWriter $ai, int $max): bool
    {
        if ($c->consent_status === 'denied') {
            return false;
        }
        if (! $c->anon_text) {
            $this->line("Menganonimkan curhat #{$c->id}...");
            $ai->analyze($c);
            $c->refresh();
        }
        if ($c->is_sensitive || ! $c->anon_text) {
            $this->line("Curhat #{$c->id} dilewati: ditandai sensitif.");

            return false;
        }
        $len = mb_strlen($c->anon_text);

        return $len >= 60 && $len <= $max;
    }

    // ------------------------------------------------------------ bangun video

    private function build(Curhat $c, AiWriter $ai, TtsService $tts): Project
    {
        $readText = trim(preg_replace('/\s+/u', ' ', (string) $c->anon_text));

        $this->line('Menulis balasan dan metadata...');
        $pkg = $ai->shortPackage($readText, $c->reply_text, $c->topic);
        $replySpoken = trim($pkg['reply'].' '.$pkg['question']);

        $this->line('Membuat suara...');
        $stamp = $c->id.'-'.time();
        [$readAudio, $engine1] = $tts->speak($readText, "short-{$stamp}-read");
        [$replyAudio, $engine2] = $tts->speak($replySpoken, "short-{$stamp}-reply");
        $this->line("Suara: {$engine1} / {$engine2}");

        $this->line('Merender video...');
        $out = storage_path('app/shorts/curhat-'.$c->id.'-'.now()->format('Ymd-His').'.mp4');
        $channel = (string) Setting::val('channel_name');
        $renderer = new ShortRenderer(
            ffmpeg: (string) Setting::val('ffmpeg_path'),
            logo: Setting::val('short_logo') ?: null,
            bgm: Setting::val('short_bgm') ?: null,
            watermark: (string) Setting::val('short_watermark'),
            channel: $channel,
            cta: (string) Setting::val('short_cta'),
        );
        $info = $renderer->render([
            'title' => $pkg['hook'],
            'read_text' => $readText,
            'reply_text' => $replySpoken,
            'read_audio' => $readAudio,
            'reply_audio' => $replyAudio,
        ], $out);
        $this->line('Durasi: '.round($info['duration']).' detik');

        $tags = array_slice(array_values(array_unique(array_merge($pkg['tags'], ['shorts', 'curhat', 'temancurhat']))), 0, 15);
        $hashtags = '#Shorts #'.Str::slug($channel, '').' #curhat';
        $yt = trim($pkg['description'])."\n\n".$hashtags;

        return DB::transaction(function () use ($c, $pkg, $out, $tags, $yt, $replySpoken, $readText) {
            $project = Project::create([
                'title' => 'Short: '.$pkg['hook'],
                'kind' => 'short',
                'planned_date' => today(),
                'status' => $this->option('no-upload') ? 'sudah_rekam' : 'dijadwalkan',
                'video_path' => $out,
                'yt_title' => $pkg['yt_title'],
                'yt_description' => $yt,
                'yt_tags' => implode(', ', $tags),
                'fb_title' => $pkg['yt_title'],
                'fb_description' => $pkg['fb_caption'] ?: $pkg['description'],
            ]);
            ProjectItem::create([
                'project_id' => $project->id,
                'curhat_id' => $c->id,
                'position' => 1,
                'read_text' => $readText,
                'reply_text' => $replySpoken,
                'audience_question' => $pkg['question'],
            ]);

            $c->update([
                'status' => 'dibacakan',
                'used_at' => now(),
                'reply_text' => $c->reply_text ?: $pkg['reply'],
                'audience_question' => $c->audience_question ?: $pkg['question'],
            ]);

            if (! $this->option('no-upload')) {
                $at = $this->publishAt();
                foreach (array_keys(Publication::PLATFORMS) as $platform) {
                    Publication::create([
                        'project_id' => $project->id,
                        'platform' => $platform,
                        'status' => 'menunggu',
                        'privacy' => $platform === 'youtube' ? (string) Setting::val('default_privacy') : 'public',
                        'publish_at' => $at,
                    ]);
                }
            }

            return $project;
        });
    }

    /** Jam tayang default hari ini; bila sudah lewat (atau kurang dari 15 menit lagi), tayang segera. */
    private function publishAt(): ?\Illuminate\Support\Carbon
    {
        $time = (string) Setting::val('default_publish_time');
        if (! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return null;
        }
        $at = now()->setTimeFromTimeString($time);

        return $at->gt(now()->addMinutes(15)) ? $at : null;
    }
}
