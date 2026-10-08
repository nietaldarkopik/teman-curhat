@extends('layouts.app')
@section('title', $p->title)
@section('content')
@php
  $count = $p->items->count();
  $missing = $p->items->filter(fn ($i) => ! $i->reply_text);
  $genUrls = $missing->map(fn ($i) => route('projects.items.generate', [$p, $i]))->values()->all();
  $allUrls = $p->items->map(fn ($i) => route('projects.items.generate', [$p, $i]))->values()->all();
@endphp
<div class="head">
  <div><a href="{{ route('projects.index') }}" class="muted small">← Semua project</a><h1>{{ $p->title }}</h1></div>
  <form method="post" action="{{ route('projects.destroy', $p) }}" data-confirm="Hapus project ini? Curhat di dalamnya kembali ke inbox.">@csrf<button class="btn sm danger">Hapus project</button></form>
</div>
@include('projects._steps')

<form method="post" action="{{ route('projects.update', $p) }}" class="card">
  @csrf
  <div class="grid g3">
    <div><label class="f">Nama project</label><input type="text" name="title" value="{{ $p->title }}" required></div>
    <div><label class="f">Rencana rekam</label><input type="date" name="planned_date" value="{{ $p->planned_date?->format('Y-m-d') }}"></div>
    <div><label class="f">Status</label><select name="status">@foreach (\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected($p->status === $k)>{{ $v }}</option>@endforeach</select></div>
  </div>
  <div class="row" style="margin-top:12px"><button class="btn">Simpan</button></div>
</form>

<div class="card">
  <div class="head" style="margin-bottom:6px">
    <div>
      <h2 style="margin:0">Naskah sesi</h2>
      <p class="muted small" style="margin:0">{{ $count }} curhat @if ($count < 5) · disarankan 5-10 curhat per video @elseif ($count > 10) · lebih dari 10, video bisa jadi terlalu panjang @endif · {{ $count - $missing->count() }} sudah ada balasan</p>
    </div>
    <div class="row">
      <button type="button" class="btn lamp" @disabled(! $count)
        data-ai='@json(array_merge($genUrls, [route('projects.frame', $p)]))' data-out="#genOut">Generate naskah yang belum ada</button>
      <button type="button" class="btn" @disabled(! $count)
        data-ai-confirm="Tulis ulang SEMUA balasan, pembuka, dan penutup? Editan Anda akan tertimpa."
        data-ai='@json(array_merge($allUrls, [route('projects.frame', $p)]))' data-out="#genOut">Generate ulang semua</button>
    </div>
  </div>
  <p class="progress" id="genOut"></p>

  <details class="pi" @if (! $p->opening) open @endif>
    <summary><span class="n">▶</span><b>Pembuka</b><span class="small muted">{{ $p->opening ? str_word_count($p->opening).' kata' : 'kosong' }}</span></summary>
    <form method="post" action="{{ route('projects.update', $p) }}" class="body">
      @csrf
      <textarea name="opening" class="read" style="min-height:160px">{{ $p->opening }}</textarea>
      <div class="row" style="margin-top:8px"><button class="btn sm primary">Simpan pembuka</button></div>
    </form>
  </details>

  @foreach ($p->items as $i => $it)
    <details class="pi">
      <summary>
        <span class="n">{{ $i + 1 }}</span>
        <span><span class="done-dot {{ $it->reply_text ? 'ok' : '' }}" title="{{ $it->reply_text ? 'Sudah ada balasan' : 'Belum ada balasan' }}"></span>
          @if ($it->curhat->topic)<span class="topic">{{ $it->curhat->topic }}</span>@endif
          {{ \Illuminate\Support\Str::limit($it->curhat->summary ?: $it->read_text, 120) }}
          @if ($it->curhat->is_sensitive)<span class="pill sens">Sensitif</span>@endif</span>
        <span class="row">
          <button class="btn sm" form="up{{ $it->id }}" @disabled($loop->first) aria-label="Naikkan">↑</button>
          <button class="btn sm" form="down{{ $it->id }}" @disabled($loop->last) aria-label="Turunkan">↓</button>
          <button class="btn sm danger" form="rm{{ $it->id }}">Keluarkan</button>
        </span>
      </summary>
      <form method="post" action="{{ route('projects.items.update', [$p, $it]) }}" class="body">
        @csrf
        <label class="f">Pengantar</label>
        <textarea name="intro_text" style="min-height:60px">{{ $it->intro_text }}</textarea>
        <label class="f">Curhat yang dibacakan <a class="hint" href="{{ route('inbox.show', $it->curhat) }}" target="_blank">buka di inbox</a></label>
        <textarea name="read_text" class="read">{{ $it->read_text }}</textarea>
        <label class="f">Balasan dan solusi</label>
        <textarea name="reply_text" style="min-height:180px">{{ $it->reply_text }}</textarea>
        <label class="f">Lemparan ke penonton</label>
        <textarea name="audience_question">{{ $it->audience_question }}</textarea>
        <div class="row" style="margin-top:10px">
          <button class="btn primary sm">Simpan bagian ini</button>
          <button type="button" class="btn sm" data-ai='@json(route('projects.items.generate', [$p, $it]))' data-out="#o{{ $it->id }}">Generate ulang bagian ini</button>
          <span class="progress" id="o{{ $it->id }}"></span>
        </div>
      </form>
    </details>
    <form id="up{{ $it->id }}" method="post" action="{{ route('projects.items.move', [$p, $it, 'up']) }}" class="hidden">@csrf</form>
    <form id="down{{ $it->id }}" method="post" action="{{ route('projects.items.move', [$p, $it, 'down']) }}" class="hidden">@csrf</form>
    <form id="rm{{ $it->id }}" method="post" action="{{ route('projects.items.remove', [$p, $it]) }}" class="hidden" data-confirm="Keluarkan curhat ini dari project?">@csrf</form>
  @endforeach

  <details class="pi" @if (! $p->closing && $count) open @endif>
    <summary><span class="n">■</span><b>Penutup</b><span class="small muted">{{ $p->closing ? str_word_count($p->closing).' kata' : 'kosong' }}</span></summary>
    <form method="post" action="{{ route('projects.update', $p) }}" class="body">
      @csrf
      <textarea name="closing" class="read" style="min-height:140px">{{ $p->closing }}</textarea>
      <div class="row" style="margin-top:8px"><button class="btn sm primary">Simpan penutup</button></div>
    </form>
  </details>
</div>

<div class="card" id="pilih">
  <div class="head" style="margin-bottom:8px">
    <h2 style="margin:0">Pilih curhat untuk video ini</h2>
    <form method="get" action="{{ route('projects.show', $p) }}#pilih" class="row">
      <select name="topic"><option value="">Semua topik</option>@foreach ($topics as $t)<option @selected(request('topic') === $t)>{{ $t }}</option>@endforeach</select>
      <select name="sort">
        <option value="bintang" @selected(request('sort', 'bintang') === 'bintang')>Berbintang dulu</option>
        <option value="terbaru" @selected(request('sort') === 'terbaru')>Terbaru</option>
        <option value="terlama" @selected(request('sort') === 'terlama')>Terlama</option>
        <option value="terpanjang" @selected(request('sort') === 'terpanjang')>Terpanjang</option>
      </select>
      <label class="check"><input type="checkbox" name="starred" value="1" @checked(request('starred'))> Bintang saja</label>
      <button class="btn sm">Filter</button>
    </form>
  </div>
  <form method="post" action="{{ route('projects.items.add', $p) }}">
    @csrf
    <div class="list" style="max-height:520px;overflow-y:auto">
      @forelse ($available as $c)
        <label class="item" style="grid-template-columns:28px minmax(0,1fr) auto;cursor:pointer">
          <input type="checkbox" class="pick" name="ids[]" value="{{ $c->id }}">
          <div>
            <div class="meta">
              @if ($c->is_starred)<span class="star on">★</span>@endif
              <span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span>
              @if ($c->topic)<span class="topic">{{ $c->topic }}</span>@endif
              @if ($c->is_sensitive)<span class="pill sens">Sensitif</span>@endif
            </div>
            <span class="excerpt">{{ \Illuminate\Support\Str::limit($c->summary ?: $c->readableText(), 200) }}</span>
          </div>
          <span class="right">{{ $c->received_at?->translatedFormat('d M') }}<br>{{ number_format($c->char_count) }} kar.</span>
        </label>
      @empty
        <div class="item" style="display:block"><p class="muted" style="margin:0">Tidak ada curhat yang tersedia di filter ini.</p></div>
      @endforelse
    </div>
    <div class="row" style="margin-top:10px"><button class="btn primary">Tambahkan yang dicentang (<span data-selected-count>0</span>)</button></div>
  </form>
</div>
@endsection
