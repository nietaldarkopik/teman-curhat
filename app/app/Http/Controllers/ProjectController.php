<?php

namespace App\Http\Controllers;

use App\Models\Curhat;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Publication;
use App\Models\Setting;
use App\Services\AiWriter;
use App\Services\FacebookService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index()
    {
        return view('projects.index', [
            'projects' => Project::withCount('items')->latest()->paginate(20),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:191',
            'planned_date' => 'nullable|date',
        ]);
        $p = Project::create($data + ['status' => 'draf']);

        return redirect()->route('projects.show', $p)->with('ok', 'Project dibuat. Sekarang pilih curhat yang akan dibacakan.');
    }

    public function show(Request $r, Project $project)
    {
        $project->load('items.curhat');
        $used = $project->items->pluck('curhat_id');

        $avail = Curhat::whereIn('status', ['baru', 'dibaca'])->whereNotIn('id', $used);
        if ($t = $r->get('topic')) {
            $avail->where('topic', $t);
        }
        if ($r->boolean('starred')) {
            $avail->where('is_starred', true);
        }
        match ($r->get('sort', 'bintang')) {
            'terlama' => $avail->orderBy('received_at'),
            'terpanjang' => $avail->orderByDesc('char_count'),
            'terbaru' => $avail->orderByDesc('received_at'),
            default => $avail->orderByDesc('is_starred')->orderByDesc('received_at'),
        };

        return view('projects.show', [
            'p' => $project,
            'available' => $avail->limit(60)->get(),
            'topics' => Curhat::whereNotNull('topic')->where('topic', '!=', '')->distinct()->orderBy('topic')->pluck('topic'),
        ]);
    }

    public function update(Request $r, Project $project)
    {
        $data = $r->validate([
            'title' => 'sometimes|required|string|max:191',
            'planned_date' => 'nullable|date',
            'status' => 'sometimes|in:'.implode(',', array_keys(Project::STATUSES)),
            'opening' => 'nullable|string',
            'closing' => 'nullable|string',
        ]);
        $project->update($data);

        return back()->with('ok', 'Project disimpan.');
    }

    public function destroy(Project $project)
    {
        DB::transaction(function () use ($project) {
            $ids = $project->items()->pluck('curhat_id');
            $project->delete();
            Curhat::whereIn('id', $ids)->where('status', 'dipilih')
                ->whereDoesntHave('projectItems')
                ->update(['status' => 'dibaca']);
        });

        return redirect()->route('projects.index')->with('ok', 'Project dihapus. Curhat di dalamnya kembali ke inbox.');
    }

    /** Dipakai juga oleh aksi massal di inbox. */
    public static function attachCurhats(Project $project, array $ids): int
    {
        $n = 0;
        DB::transaction(function () use ($project, $ids, &$n) {
            $pos = (int) $project->items()->max('position');
            foreach (Curhat::whereIn('id', $ids)->orderBy('received_at')->get() as $c) {
                if ($project->items()->where('curhat_id', $c->id)->exists()) {
                    continue;
                }
                $project->items()->create([
                    'curhat_id' => $c->id,
                    'position' => ++$pos,
                    'read_text' => $c->readableText(),
                    'reply_text' => $c->reply_text,
                    'audience_question' => $c->audience_question,
                ]);
                $c->update(['status' => 'dipilih']);
                $n++;
            }
        });

        return $n;
    }

    public function addItems(Request $r, Project $project)
    {
        $data = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $n = self::attachCurhats($project, $data['ids']);

        return back()->with('ok', "{$n} curhat ditambahkan.");
    }

    private function own(Project $project, ProjectItem $item): void
    {
        abort_unless($item->project_id === $project->id, 404);
    }

    public function updateItem(Request $r, Project $project, ProjectItem $item)
    {
        $this->own($project, $item);
        $item->update($r->validate([
            'read_text' => 'nullable|string',
            'intro_text' => 'nullable|string',
            'reply_text' => 'nullable|string',
            'audience_question' => 'nullable|string',
        ]));

        return back()->with('ok', 'Bagian curhat disimpan.');
    }

    public function moveItem(Project $project, ProjectItem $item, string $dir)
    {
        $this->own($project, $item);
        $other = $project->items()
            ->where('position', $dir === 'up' ? '<' : '>', $item->position)
            ->orderBy('position', $dir === 'up' ? 'desc' : 'asc')
            ->first();
        if ($other) {
            DB::transaction(function () use ($item, $other) {
                [$a, $b] = [$item->position, $other->position];
                $item->update(['position' => $b]);
                $other->update(['position' => $a]);
            });
        }

        return back();
    }

    public function removeItem(Project $project, ProjectItem $item)
    {
        $this->own($project, $item);
        $curhat = $item->curhat;
        $item->delete();
        if ($curhat && $curhat->status === 'dipilih' && ! $curhat->projectItems()->exists()) {
            $curhat->update(['status' => 'dibaca']);
        }

        return back()->with('ok', 'Curhat dikeluarkan dari project.');
    }

    public function generateItem(Project $project, ProjectItem $item, AiWriter $ai)
    {
        $this->own($project, $item);
        @set_time_limit(300);
        try {
            $c = $item->curhat;
            if (! $c->anon_text) {
                $original = $item->read_text;
                $ai->analyze($c);
                if (! $original || $original === $c->original_text) {
                    $item->read_text = $c->readableText();
                }
            }
            $ids = $project->items()->pluck('id')->all();
            $out = $ai->reply($item->read_text ?: $c->readableText(), $c->notes, array_search($item->id, $ids) + 1, count($ids));
            $item->fill([
                'intro_text' => $out['intro'],
                'reply_text' => $out['reply'],
                'audience_question' => $out['question'],
            ])->save();

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function generateFrame(Project $project, AiWriter $ai)
    {
        @set_time_limit(300);
        $project->load('items.curhat');
        if ($project->items->isEmpty()) {
            return response()->json(['error' => 'Belum ada curhat di project ini.'], 422);
        }
        try {
            $out = $ai->frame($project);
            $project->update(['opening' => $out['opening'], 'closing' => $out['closing']]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function script(Project $project)
    {
        return view('projects.script', ['p' => $project->load('items.curhat')]);
    }

    public function screen(Project $project)
    {
        return view('projects.screen', ['p' => $project->load('items.curhat'), 'channel' => Setting::val('channel_name')]);
    }

    private function videoFiles(): array
    {
        $dir = rtrim((string) Setting::val('video_folder'), '\\/');
        if (! $dir || ! is_dir($dir)) {
            return [];
        }
        $exts = config('curhat.video_extensions');
        $files = [];
        foreach (scandir($dir) ?: [] as $f) {
            $full = $dir.DIRECTORY_SEPARATOR.$f;
            if (is_file($full) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $exts, true)) {
                $files[] = ['path' => $full, 'name' => $f, 'size' => filesize($full), 'time' => filemtime($full)];
            }
        }
        usort($files, fn ($a, $b) => $b['time'] <=> $a['time']);

        return array_slice($files, 0, 40);
    }

    public function publish(Project $project, YouTubeService $yt, FacebookService $fb)
    {
        $time = Setting::val('default_publish_time');
        $default = Carbon::tomorrow()->setTimeFromTimeString($time);

        return view('projects.publish', [
            'p' => $project->load('items.curhat', 'publications'),
            'files' => $this->videoFiles(),
            'videoFolder' => Setting::val('video_folder'),
            'ytConnected' => $yt->connected(),
            'fbConnected' => $fb->connected(),
            'defaultAt' => $default->format('Y-m-d\TH:i'),
            'defaultPrivacy' => Setting::val('default_privacy'),
        ]);
    }

    public function saveVideo(Request $r, Project $project)
    {
        $data = $r->validate([
            'video_path' => 'nullable|string|max:500',
            'transcript' => 'nullable|string',
            'chapters' => 'nullable|string',
        ]);
        if (! empty($data['video_path']) && ! is_file($data['video_path'])) {
            return back()->withInput()->with('err', 'File video tidak ditemukan di: '.$data['video_path']);
        }
        $project->update($data + ($project->status === 'siap_rekam' || $project->status === 'draf' ? ['status' => 'sudah_rekam'] : []));

        return back()->with('ok', 'Video dan catatan disimpan.');
    }

    public function generateMeta(Project $project, AiWriter $ai)
    {
        @set_time_limit(300);
        $project->load('items.curhat');
        try {
            $m = $ai->meta($project);
            $project->update([
                'title_options' => $m['titles'],
                'yt_title' => $m['titles'][0] ?? $project->yt_title,
                'yt_description' => $m['description'],
                'yt_tags' => $m['tags'],
                'fb_title' => $m['fb_title'],
                'fb_description' => $m['fb_description'],
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function saveMeta(Request $r, Project $project)
    {
        $project->update($r->validate([
            'yt_title' => 'nullable|string|max:100',
            'yt_description' => 'nullable|string|max:5000',
            'yt_tags' => 'nullable|string|max:1000',
            'fb_title' => 'nullable|string|max:191',
            'fb_description' => 'nullable|string',
        ]));

        return back()->with('ok', 'Judul dan deskripsi disimpan.');
    }

    public function schedule(Request $r, Project $project)
    {
        $data = $r->validate([
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:youtube,facebook',
            'yt_at' => 'nullable|date',
            'fb_at' => 'nullable|date',
            'yt_privacy' => 'nullable|in:public,unlisted,private',
        ]);
        if (! $project->video_path || ! is_file($project->video_path)) {
            return back()->with('err', 'Pilih file video dulu di langkah 1.');
        }
        if (! $project->yt_title) {
            return back()->with('err', 'Isi judul video dulu di langkah 2.');
        }

        foreach ($data['platforms'] as $platform) {
            $at = $platform === 'youtube' ? ($data['yt_at'] ?? null) : ($data['fb_at'] ?? null);
            Publication::create([
                'project_id' => $project->id,
                'platform' => $platform,
                'status' => 'menunggu',
                'privacy' => $platform === 'youtube' ? ($data['yt_privacy'] ?? 'public') : 'public',
                'publish_at' => $at ? Carbon::parse($at) : now(),
            ]);
        }

        DB::transaction(function () use ($project) {
            $project->update(['status' => 'dijadwalkan']);
            Curhat::whereIn('id', $project->items()->pluck('curhat_id'))->update(['status' => 'dibacakan', 'used_at' => now()]);
        });

        return back()->with('ok', 'Upload dijadwalkan. Video akan diunggah oleh scheduler dalam 1 menit, lalu tayang sesuai jam yang dipilih.');
    }
}
