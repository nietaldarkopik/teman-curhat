<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\Setting;
use App\Services\FacebookService;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class ProcessUploads extends Command
{
    protected $signature = 'curhat:process-uploads {--limit=3}';

    protected $description = 'Upload video yang sudah dijadwalkan ke YouTube dan Facebook';

    public function handle(YouTubeService $yt, FacebookService $fb): int
    {
        Setting::put('scheduler_heartbeat', now()->toDateTimeString());

        Publication::where('status', 'mengunggah')
            ->where('updated_at', '<', now()->subHours(3))
            ->update(['status' => 'gagal', 'error' => 'Upload terputus di tengah jalan. Klik "Coba lagi".']);

        Publication::where('status', 'terjadwal')
            ->where('publish_at', '<=', now())
            ->update(['status' => 'tayang']);

        $due = Publication::with('project')
            ->where('status', 'menunggu')
            ->orderBy('publish_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($due as $pub) {
            $pub->update(['status' => 'mengunggah', 'attempts' => $pub->attempts + 1, 'error' => null]);
            $this->line("Mengunggah #{$pub->id} ke {$pub->platform}...");
            try {
                [$id, $url, $scheduled] = $pub->platform === 'youtube' ? $yt->upload($pub) : $fb->upload($pub);
                $pub->update([
                    'remote_id' => $id,
                    'remote_url' => $url,
                    'uploaded_at' => now(),
                    'status' => $scheduled ? 'terjadwal' : 'tayang',
                ]);
                $this->info("Berhasil: {$url}");
            } catch (\Throwable $e) {
                $pub->update(['status' => 'gagal', 'error' => $e->getMessage()]);
                $this->error($e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
