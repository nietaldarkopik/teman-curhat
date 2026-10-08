@extends('layouts.app')
@section('title', 'Curhat #'.$c->id)
@section('content')
<div class="head">
  <div>
    <a href="{{ route('inbox.index') }}" class="muted small">← Inbox</a>
    <h1>Curhat #{{ $c->id }}</h1>
    <div class="row">
      <span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span>
      @if ($c->topic)<span class="topic">{{ $c->topic }}</span>@endif
      <span class="muted small">{{ $c->received_at?->translatedFormat('l, d M Y H:i') }} · {{ $c->source === 'facebook' ? 'DM Facebook' : 'Manual' }}@if ($c->sender_name) · dari {{ $c->sender_name }}@endif</span>
    </div>
  </div>
  <div class="row">
    @if ($prevId)<a class="btn sm" href="{{ route('inbox.show', $prevId) }}">← Lebih baru</a>@endif
    @if ($nextId)<a class="btn sm" href="{{ route('inbox.show', $nextId) }}">Lebih lama →</a>@endif
  </div>
</div>

@if ($c->is_sensitive)
  <div class="warn"><b>Topik berat.</b> {{ $c->sensitive_note }} Pertimbangkan untuk membalas pengirim secara pribadi dan mengarahkannya ke bantuan profesional daripada menjadikannya konten.</div>
@endif

<form method="post" action="{{ route('inbox.update', $c) }}" id="cform">@csrf</form>
<div class="grid side">
  <div>
    <div class="card">
      <div class="row" style="justify-content:space-between"><h2 style="margin:0">Teks untuk dibacakan</h2>
        <span class="row"><button type="button" class="btn sm" data-ai='@json(route('inbox.analyze', $c))' data-out="#aiOut">Anonimkan ulang (AI)</button></span></div>
      <p class="muted small">Nama dan detail pribadi sudah disamarkan oleh AI. Tetap baca ulang sebelum dipakai.</p>
      <textarea form="cform" name="anon_text" class="read" style="min-height:260px" placeholder="Belum dianonimkan. Klik tombol AI di atas.">{{ $c->anon_text }}</textarea>
      <details style="margin-top:10px"><summary class="muted small">Lihat teks asli dari DM</summary>
        <div style="white-space:pre-wrap;font-family:var(--read);background:var(--bg);border-radius:9px;padding:12px;margin-top:8px">{{ $c->original_text }}</div>
      </details>
      <div class="grid g2" style="margin-top:6px">
        <div><label class="f">Topik</label><input form="cform" type="text" name="topic" value="{{ $c->topic }}"></div>
        <div><label class="f">Ringkasan</label><input form="cform" type="text" name="summary" value="{{ $c->summary }}"></div>
      </div>
    </div>
    <div class="card">
      <div class="row" style="justify-content:space-between"><h2 style="margin:0">Balasan dan solusi</h2>
        <button type="button" class="btn sm lamp" data-ai='@json(route('inbox.reply', $c))' data-out="#aiOut">Buat balasan (AI)</button></div>
      <label class="f">Pendapat Anda <span class="hint">(opsional, AI akan menyusun balasan berdasarkan ini)</span></label>
      <textarea form="cform" name="notes" placeholder="Contoh: menurut saya dia perlu jujur ke pasangannya dulu.">{{ $c->notes }}</textarea>
      <label class="f">Balasan</label>
      <textarea form="cform" name="reply_text" style="min-height:200px">{{ $c->reply_text }}</textarea>
      <label class="f">Lemparan ke penonton</label>
      <textarea form="cform" name="audience_question">{{ $c->audience_question }}</textarea>

      <div class="row" style="justify-content:space-between;margin-top:14px">
        <label class="f" style="margin:0">Narasi suara (TTS)</label>
        <button type="button" class="btn sm lamp" data-ai='@json(route('inbox.tts', $c))' data-out="#aiOut"
          @if (! $c->reply_text) disabled title="Buat balasan (AI) dulu" @endif>Buat narasi suara (TTS)</button>
      </div>
      @if ($c->tts_path)
        <audio controls src="{{ $c->ttsUrl() }}" style="width:100%;margin-top:6px"></audio>
        <p class="muted small" style="margin-top:4px">Dibuat {{ $c->tts_generated_at?->diffForHumans() }}.</p>
      @else
        <p class="muted small">Belum ada narasi suara.</p>
      @endif
    </div>
  </div>
  <div>
    <div class="card">
      <label class="f">Status</label>
      <select form="cform" name="status">@foreach (\App\Models\Curhat::STATUSES as $k => $v)<option value="{{ $k }}" @selected($c->status === $k)>{{ $v }}</option>@endforeach</select>
      <label class="check" style="margin-top:12px"><input form="cform" type="checkbox" name="is_starred" value="1" @checked($c->is_starred)> Beri bintang (kandidat bagus)</label>
      <div class="row" style="margin-top:14px"><button form="cform" class="btn primary">Simpan</button><span class="progress" id="aiOut"></span></div>
    </div>
    <div class="card">
      <h3>Masukkan ke project video</h3>
      @if ($c->projectItems->isNotEmpty())
        <p class="small">Sudah ada di: @foreach ($c->projectItems as $it)<a href="{{ route('projects.show', $it->project) }}">{{ $it->project->title }}</a>@if (! $loop->last), @endif @endforeach</p>
      @endif
      @if ($projects->isEmpty())
        <p class="muted small">Belum ada project berstatus draf. <a href="{{ route('projects.index') }}">Buat project</a>.</p>
      @else
        <form method="post" action="{{ route('inbox.bulk') }}" class="row">
          @csrf
          <input type="hidden" name="ids[]" value="{{ $c->id }}">
          <input type="hidden" name="action" value="project">
          <select name="project_id" style="flex:1">@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach</select>
          <button class="btn">Tambahkan</button>
        </form>
      @endif
    </div>
  </div>
</div>
@endsection
