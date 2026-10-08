@extends('layouts.app')
@section('title', 'Inbox curhat')
@section('content')
<div class="head">
  <div><h1>Inbox curhat</h1><p class="muted" style="margin:0">{{ $curhats->total() }} curhat sesuai filter.</p></div>
  <details>
    <summary class="btn">Tambah curhat manual</summary>
    <form method="post" action="{{ route('inbox.store') }}" class="card" style="margin-top:8px;width:min(520px,90vw)">
      @csrf
      <label class="f">Tempel isi curhat</label>
      <textarea name="text" class="read" required></textarea>
      <div class="row" style="margin-top:10px"><button class="btn primary">Simpan ke inbox</button></div>
    </form>
  </details>
</div>

<form method="get" class="card filters">
  <div><label class="f">Cari</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Kata dalam curhat atau ringkasan"></div>
  <div><label class="f">Status</label>
    <select name="status">
      <option value="aktif" @selected(request('status', 'aktif') === 'aktif')>Belum dipakai</option>
      @foreach (\App\Models\Curhat::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
      <option value="semua" @selected(request('status') === 'semua')>Semua</option>
    </select></div>
  <div><label class="f">Topik</label>
    <select name="topic"><option value="">Semua topik</option>
      @foreach ($topics as $t)<option @selected(request('topic') === $t)>{{ $t }}</option>@endforeach
    </select></div>
  <div><label class="f">AI</label>
    <select name="ai"><option value="">Semua</option>
      <option value="belum" @selected(request('ai') === 'belum')>Belum dianalisis</option>
      <option value="sensitif" @selected(request('ai') === 'sensitif')>Ditandai sensitif</option>
    </select></div>
  <div><label class="f">Urutkan</label>
    <select name="sort">@foreach ($sorts as $k => $v)<option value="{{ $k }}" @selected(request('sort', 'terbaru') === $k)>{{ $v }}</option>@endforeach</select></div>
  <div class="row"><label class="check"><input type="checkbox" name="starred" value="1" @checked(request('starred'))> Bintang</label><button class="btn primary">Terapkan</button></div>
</form>

<form method="post" action="{{ route('inbox.bulk') }}" id="bulk">
  @csrf
  <div class="bulkbar">
    <label class="check"><input type="checkbox" data-check-all="input.pick"> <span><b data-selected-count>0</b> dipilih</span></label>
    <select name="action" id="bulkAction">
      <option value="dibaca">Tandai sudah dibaca</option>
      <option value="baru">Tandai belum dibaca</option>
      <option value="bintang">Beri bintang</option>
      <option value="hapus_bintang">Hapus bintang</option>
      <option value="arsip">Arsipkan</option>
      <option value="aktifkan">Keluarkan dari arsip</option>
      <option value="project">Tambahkan ke project...</option>
    </select>
    <select name="project_id" id="bulkProject" class="hidden">
      <option value="">Pilih project</option>
      @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach
    </select>
    <button class="btn">Jalankan</button>
    <span style="flex:1"></span>
    <button type="button" class="btn" data-bulk-ai="{{ route('inbox.analyze', '__ID__') }}" data-out="#bulkOut">Anonimkan dan beri topik (AI)</button>
    <button type="button" class="btn" data-bulk-ai="{{ route('inbox.reply', '__ID__') }}" data-out="#bulkOut">Buat balasan (AI)</button>
    <span class="progress" id="bulkOut"></span>
  </div>

  <div class="list">
    @forelse ($curhats as $c)
      <div class="item {{ $c->status === 'baru' ? 'is-new' : '' }}">
        <input type="checkbox" class="pick" name="ids[]" value="{{ $c->id }}" aria-label="Pilih curhat {{ $c->id }}">
        <span class="star {{ $c->is_starred ? 'on' : '' }}" title="Bintang">★</span>
        <div>
          <div class="meta">
            <span class="pill {{ $c->status }}">{{ $c->statusLabel() }}</span>
            @if ($c->topic)<span class="topic">{{ $c->topic }}</span>@endif
            @if ($c->is_sensitive)<span class="pill sens">Sensitif</span>@endif
            @if ($c->reply_text)<span class="small muted">✓ ada balasan</span>@endif
            @if (! $c->anon_text)<span class="small muted">belum dianonimkan</span>@endif
          </div>
          <a class="excerpt" href="{{ route('inbox.show', $c) }}">{{ \Illuminate\Support\Str::limit($c->summary ?: $c->readableText(), 220) }}</a>
        </div>
        <div class="right">
          {{ $c->received_at?->translatedFormat('d M Y, H:i') }}<br>
          {{ number_format($c->char_count) }} karakter<br>
          @if ($c->sender_name)<span title="Hanya terlihat di sini, tidak pernah dikirim ke AI">{{ \Illuminate\Support\Str::limit($c->sender_name, 18) }}</span>@endif
        </div>
      </div>
    @empty
      <div class="item" style="display:block"><p class="muted" style="margin:0">Tidak ada curhat di filter ini. Sinkronkan DM dari Dashboard, atau tambah manual.</p></div>
    @endforelse
  </div>
</form>
{{ $curhats->links() }}
@endsection
@push('scripts')
<script>
  const a = document.getElementById('bulkAction'), p = document.getElementById('bulkProject');
  a.addEventListener('change', () => p.classList.toggle('hidden', a.value !== 'project'));
</script>
@endpush
