<?php

namespace App\Http\Controllers;

use App\Models\Curhat;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Setting;
use App\Services\ClaudeService;
use App\Services\FacebookService;
use App\Services\YouTubeService;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(ClaudeService $ai, FacebookService $fb, YouTubeService $yt)
    {
        $counts = Curhat::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $beat = Setting::val('scheduler_heartbeat');

        return view('dashboard', [
            'counts' => $counts,
            'checks' => [
                ['API key Claude', $ai->configured()],
                ['Page Facebook terhubung', $fb->connected()],
                ['Channel YouTube terhubung', $yt->connected()],
                ['Scheduler Windows berjalan', $beat && Carbon::parse($beat)->gt(now()->subMinutes(5))],
            ],
            'fbConnected' => $fb->connected(),
            'lastSync' => Setting::val('fb_last_sync'),
            'upcoming' => Publication::with('project')->whereIn('status', ['menunggu', 'mengunggah', 'terjadwal', 'gagal'])->orderBy('publish_at')->limit(10)->get(),
            'projects' => Project::withCount('items')->latest()->limit(6)->get(),
        ]);
    }

    public function sync(FacebookService $fb)
    {
        @set_time_limit(600);
        try {
            $r = $fb->sync();

            return back()->with('ok', "Sinkron selesai. {$r['messages']} pesan baru dari {$r['conversations']} percakapan.");
        } catch (\Throwable $e) {
            return back()->with('err', $e->getMessage());
        }
    }
}
