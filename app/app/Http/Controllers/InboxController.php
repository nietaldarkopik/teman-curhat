<?php

namespace App\Http\Controllers;

use App\Models\Curhat;
use App\Models\Project;
use App\Services\AiWriter;
use App\Services\TtsService;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public const SORTS = [
        'terbaru' => 'Terbaru',
        'terlama' => 'Terlama',
        'terpanjang' => 'Terpanjang',
        'terpendek' => 'Terpendek',
        'bintang' => 'Berbintang dulu',
        'topik' => 'Topik (A-Z)',
    ];

    public function index(Request $r)
    {
        $q = Curhat::query();
        $status = $r->get('status', 'aktif');
        if ($status === 'aktif') {
            $q->whereIn('status', ['baru', 'dibaca']);
        } elseif ($status !== 'semua') {
            $q->where('status', $status);
        }
        if ($topic = $r->get('topic')) {
            $q->where('topic', $topic);
        }
        if ($r->boolean('starred')) {
            $q->where('is_starred', true);
        }
        if ($r->get('ai') === 'belum') {
            $q->whereNull('anon_text');
        } elseif ($r->get('ai') === 'sensitif') {
            $q->where('is_sensitive', true);
        }
        if ($s = trim((string) $r->get('q'))) {
            $q->where(fn ($w) => $w->where('original_text', 'like', "%{$s}%")
                ->orWhere('anon_text', 'like', "%{$s}%")
                ->orWhere('summary', 'like', "%{$s}%"));
        }
        match ($r->get('sort', 'terbaru')) {
            'terlama' => $q->orderBy('received_at'),
            'terpanjang' => $q->orderByDesc('char_count'),
            'terpendek' => $q->orderBy('char_count'),
            'bintang' => $q->orderByDesc('is_starred')->orderByDesc('received_at'),
            'topik' => $q->orderByRaw('topic IS NULL')->orderBy('topic')->orderByDesc('received_at'),
            default => $q->orderByDesc('received_at'),
        };

        return view('inbox.index', [
            'curhats' => $q->paginate(30)->withQueryString(),
            'topics' => Curhat::whereNotNull('topic')->where('topic', '!=', '')->distinct()->orderBy('topic')->pluck('topic'),
            'projects' => Project::whereIn('status', ['draf', 'siap_rekam'])->latest()->get(),
            'sorts' => self::SORTS,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['text' => 'required|string|min:10']);
        $text = trim($data['text']);
        $c = Curhat::create([
            'source' => 'manual',
            'original_text' => $text,
            'char_count' => mb_strlen($text),
            'status' => 'baru',
            'received_at' => now(),
            'last_message_at' => now(),
        ]);

        return redirect()->route('inbox.show', $c)->with('ok', 'Curhat ditambahkan.');
    }

    public function show(Curhat $curhat)
    {
        if ($curhat->status === 'baru') {
            $curhat->update(['status' => 'dibaca', 'read_at' => now()]);
        }
        $curhat->load('projectItems.project');

        return view('inbox.show', [
            'c' => $curhat,
            'projects' => Project::whereIn('status', ['draf', 'siap_rekam'])->latest()->get(),
            'prevId' => Curhat::whereIn('status', ['baru', 'dibaca'])->where('received_at', '>', $curhat->received_at)->orderBy('received_at')->value('id'),
            'nextId' => Curhat::whereIn('status', ['baru', 'dibaca'])->where('received_at', '<', $curhat->received_at)->orderByDesc('received_at')->value('id'),
        ]);
    }

    public function update(Request $r, Curhat $curhat)
    {
        $data = $r->validate([
            'anon_text' => 'nullable|string',
            'topic' => 'nullable|string|max:90',
            'summary' => 'nullable|string',
            'reply_text' => 'nullable|string',
            'audience_question' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:'.implode(',', array_keys(Curhat::STATUSES)),
        ]);
        $curhat->fill($data);
        $curhat->is_starred = $r->boolean('is_starred');
        $curhat->save();

        return back()->with('ok', 'Tersimpan.');
    }

    public function analyze(Curhat $curhat, AiWriter $ai)
    {
        @set_time_limit(240);
        try {
            $ai->analyze($curhat);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reply(Curhat $curhat, AiWriter $ai)
    {
        @set_time_limit(240);
        try {
            if (! $curhat->anon_text) {
                $ai->analyze($curhat);
            }
            $out = $ai->reply($curhat->readableText(), $curhat->notes);
            $curhat->update(['reply_text' => $out['reply'], 'audience_question' => $out['question']]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function tts(Curhat $curhat, TtsService $tts)
    {
        @set_time_limit(240);
        if (! $curhat->reply_text) {
            return response()->json(['error' => 'Buat balasan (AI) dulu sebelum bikin narasi suara.'], 422);
        }
        try {
            $path = $tts->synthesize($curhat->reply_text, 'curhat-'.$curhat->id.'-'.time());
            $curhat->update(['tts_path' => $path, 'tts_generated_at' => now()]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function bulk(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:dibaca,baru,arsip,aktifkan,bintang,hapus_bintang,project',
            'project_id' => 'nullable|integer|exists:projects,id',
        ]);
        $q = Curhat::whereIn('id', $data['ids']);

        switch ($data['action']) {
            case 'dibaca':
                $q->where('status', 'baru')->update(['status' => 'dibaca', 'read_at' => now()]);
                break;
            case 'baru':
                $q->where('status', 'dibaca')->update(['status' => 'baru']);
                break;
            case 'arsip':
                $q->whereIn('status', ['baru', 'dibaca'])->update(['status' => 'arsip']);
                break;
            case 'aktifkan':
                $q->where('status', 'arsip')->update(['status' => 'dibaca']);
                break;
            case 'bintang':
                $q->update(['is_starred' => true]);
                break;
            case 'hapus_bintang':
                $q->update(['is_starred' => false]);
                break;
            case 'project':
                if (empty($data['project_id'])) {
                    return back()->with('err', 'Pilih project video dulu.');
                }
                $n = ProjectController::attachCurhats(Project::findOrFail($data['project_id']), $data['ids']);

                return back()->with('ok', "{$n} curhat ditambahkan ke project.");
        }

        return back()->with('ok', 'Selesai.');
    }
}
