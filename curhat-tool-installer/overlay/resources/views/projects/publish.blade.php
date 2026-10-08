@extends('layouts.app')
@section('title', 'Upload · '.$p->title)
@section('content')
<div class="head"><div><a href="{{ route('projects.index') }}" class="muted small">← Semua project</a><h1>{{ $p->title }}</h1></div></div>
@include('projects._steps')

<div class="grid side">
<div>
  <form method="post" action="{{ route('projects.video', $p) }}" class="card">
    @csrf
    <h2>1. File video hasil rekaman</h2>
    @if ($files)
      <label class="f">Pilih dari folder video <span class="hint">{{ $videoFolder }}</span></label>
      <select onchange="document.getElementById('vp').value=this.value">
        <option value="">Pilih file...</option>
        @foreach ($files as $f)
          <option value="{{ $f['path'] }}" @selected($p->video_path === $f['path'])>{{ $f['name'] }} · {{ number_format($f['size'] / 1048576, 0) }} MB · {{ date('d M H:i', $f['time']) }}</option>
        @endforeach
      </select>
    @else
      <p class="info small">Atur "Folder video" di Pengaturan supaya file hasil rekaman OBS bisa dipilih dari daftar. Atau tulis lokasi lengkap filenya di bawah.</p>
    @endif
    <label class="f">Lokasi file</label>
    <input type="text" id="vp" name="video_path" value="{{ old('video_path', $p->video_path) }}" placeholder="C:\Users\Opik\Videos\sesi-12.mp4">
    <label class="f">Daftar bab dan waktu <span class="hint">(opsional, akan disalin ke deskripsi)</span></label>
    <textarea name="chapters" placeholder="00:00 Pembuka&#10;01:30 Curhat 1: suami jarang pulang&#10;06:10 Curhat 2: ...">{{ old('chapters', $p->chapters) }}</textarea>
    <label class="f">Transkrip video <span class="hint">(opsional, membuat judul dan deskripsi lebih sesuai dengan yang benar-benar Anda ucapkan)</span></label>
    <textarea name="transcript" style="min-height:100px">{{ old('transcript', $p->transcript) }}</textarea>
    <div class="row" style="margin-top:12px"><button class="btn primary">Simpan</button></div>
  </form>

  <div class="card">
    <div class="head" style="margin-bottom:6px">
      <h2 style="margin:0">2. Judul, deskripsi, dan tag</h2>
      <button type="button" class="btn lamp" data-ai='@json(route('projects.meta.generate', $p))' data-out="#metaOut">Generate dengan AI</button>
    </div>
    <p class="progress" id="metaOut"></p>
    <form method="post" action="{{ route('projects.meta', $p) }}">
      @csrf
      @if ($p->title_options)
        <label class="f">Pilihan judul dari AI</label>
        <div class="titles">
          @foreach ($p->title_options as $t)
            <label><input type="radio" name="_pick" onclick="document.getElementById('ytt').value=this.value" value="{{ $t }}" @checked($p->yt_title === $t)> <span>{{ $t }}</span></label>
          @endforeach
        </div>
      @endif
      <label class="f">Judul YouTube <span class="hint">(maks. 100 karakter)</span></label>
      <input type="text" id="ytt" name="yt_title" maxlength="100" value="{{ $p->yt_title }}">
      <label class="f">Deskripsi YouTube</label>
      <textarea name="yt_description" style="min-height:220px">{{ $p->yt_description }}</textarea>
      <label class="f">Tag <span class="hint">(pisahkan dengan koma)</span></label>
      <input type="text" name="yt_tags" value="{{ $p->yt_tags }}">
      <label class="f">Judul Facebook</label>
      <input type="text" name="fb_title" value="{{ $p->fb_title }}">
      <label class="f">Caption Facebook</label>
      <textarea name="fb_description">{{ $p->fb_description }}</textarea>
      <div class="row" style="margin-top:12px"><button class="btn primary">Simpan</button></div>
    </form>
  </div>
</div>

<div>
  <form method="post" action="{{ route('projects.schedule', $p) }}" class="card">
    @csrf
    <h2>3. Jadwalkan upload</h2>
    <p class="muted small">Video diunggah secepatnya oleh scheduler, lalu platform yang menayangkannya pada jam yang dipilih. Komputer tidak perlu menyala saat jam tayang.</p>
    <label class="check" style="margin-top:10px"><input type="checkbox" name="platforms[]" value="youtube" @checked($ytConnected) @disabled(! $ytConnected)> <b>YouTube</b> @unless ($ytConnected)<span class="hint">belum terhubung</span>@endunless</label>
    <div class="grid g2" style="margin-top:6px">
      <div><label class="f">Tayang</label><input type="datetime-local" name="yt_at" value="{{ $defaultAt }}"></div>
      <div><label class="f">Visibilitas</label>
        <select name="yt_privacy">
          <option value="public" @selected($defaultPrivacy === 'public')>Publik (terjadwal)</option>
          <option value="unlisted" @selected($defaultPrivacy === 'unlisted')>Tidak publik</option>
          <option value="private" @selected($defaultPrivacy === 'private')>Pribadi</option>
        </select></div>
    </div>
    <label class="check" style="margin-top:14px"><input type="checkbox" name="platforms[]" value="facebook" @checked($fbConnected) @disabled(! $fbConnected)> <b>Facebook Page</b> @unless ($fbConnected)<span class="hint">belum terhubung</span>@endunless</label>
    <label class="f">Tayang</label><input type="datetime-local" name="fb_at" value="{{ $defaultAt }}">
    <p class="muted small">Jadwal tayang Facebook minimal sekitar 10 menit dari sekarang. Kalau lebih cepat, video langsung tayang setelah terunggah.</p>
    <div class="row" style="margin-top:12px"><button class="btn primary" @disabled(! $ytConnected && ! $fbConnected)>Jadwalkan upload</button></div>
  </form>

  <div class="card">
    <div class="head" style="margin-bottom:6px"><h2 style="margin:0">Status upload</h2>
      <form method="post" action="{{ route('uploads.run') }}">@csrf<button class="btn sm">Proses antrean sekarang</button></form></div>
    @forelse ($p->publications as $u)
      <div style="border-top:1px solid var(--line);padding:8px 0">
        <div class="row" style="justify-content:space-between"><b>{{ $u->platformLabel() }}</b><span class="pill {{ $u->status }}">{{ $u->statusLabel() }}</span></div>
        <div class="small muted">Tayang {{ $u->publish_at?->translatedFormat('d M Y, H:i') }}</div>
        @if ($u->remote_url)<a class="small" href="{{ $u->remote_url }}" target="_blank" rel="noopener">{{ $u->remote_url }}</a>@endif
        @if ($u->error)<div class="small" style="color:var(--rose)">{{ $u->error }}</div>@endif
        @if ($u->status === 'gagal')
          <form method="post" action="{{ route('uploads.retry', $u) }}" style="margin-top:6px">@csrf<button class="btn sm">Coba lagi</button></form>
        @endif
      </div>
    @empty
      <p class="muted small">Belum ada jadwal untuk project ini.</p>
    @endforelse
  </div>
</div>
</div>
@endsection
