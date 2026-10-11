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

  <div class="card">
    <div class="head" style="margin-bottom:6px">
      <h2 style="margin:0">3. Thumbnail</h2>
      <button type="button" class="btn lamp" data-ai='@json(route('projects.thumb.generate', $p))' data-out="#thumbOut">Saran teks dari AI</button>
    </div>
    <p class="progress" id="thumbOut"></p>
    @if ($p->thumb_options)
      <label class="f">Pilihan teks dari AI <span class="hint">(klik untuk dipakai)</span></label>
      <div class="titles">
        @foreach ($p->thumb_options as $o)
          <label><input type="radio" name="_tpick" class="tpick" data-main="{{ $o['main'] }}" data-sub="{{ $o['sub'] }}"> <span><b>{{ $o['main'] }}</b>@if ($o['sub']) · {{ $o['sub'] }}@endif</span></label>
        @endforeach
      </div>
    @endif
    <canvas id="tc" width="1280" height="720" style="width:100%;border-radius:10px;border:1px solid var(--line);margin-top:10px;background:#222"></canvas>
    <div class="grid g2">
      <div><label class="f">Teks utama</label><input type="text" id="tMain" value="{{ $p->yt_title ? \Illuminate\Support\Str::words($p->yt_title, 5, '') : $p->title }}"></div>
      <div><label class="f">Teks kecil</label><input type="text" id="tSub" value="{{ \App\Models\Setting::val('channel_name') }}"></div>
      <div><label class="f">Latar</label>
        <select id="tBg">
          <option value="0">Senja (ungu - oranye)</option>
          <option value="1">Malam (biru tua)</option>
          <option value="2">Hangat (merah muda)</option>
          <option value="3">Hijau tenang</option>
          <option value="4">Gelap polos</option>
        </select></div>
      <div><label class="f">Gambar latar <span class="hint">(opsional)</span></label><input type="file" id="tImg" accept="image/*"></div>
      <div><label class="f">Warna teks</label><input type="color" id="tColor" value="#ffffff" style="height:36px;padding:2px"></div>
      <div><label class="f">Warna sorot</label><input type="color" id="tAccent" value="#ffd23f" style="height:36px;padding:2px"></div>
      <div><label class="f">Rata</label>
        <select id="tAlign"><option value="left">Kiri</option><option value="center">Tengah</option></select></div>
      <div><label class="f">Ukuran teks <span class="hint" id="tSizeV"></span></label><input type="range" id="tSize" min="60" max="190" value="130"></div>
    </div>
    <div class="row" style="margin-top:12px">
      <button type="button" class="btn primary" id="tSave">Simpan thumbnail</button>
      <a class="btn" id="tDl" download="thumbnail-{{ $p->id }}.jpg" href="#">Unduh</a>
      @if ($p->thumbnail_path)<span class="pill tayang">Tersimpan</span>@endif
    </div>
    <p class="small muted" id="tMsg" style="margin-top:6px">Ukuran 1280×720. Thumbnail yang tersimpan ikut terunggah ke YouTube dan Facebook. Di YouTube, thumbnail kustom butuh channel yang sudah terverifikasi.</p>
    @if ($p->thumbnail_path)
      <form method="post" action="{{ route('projects.thumb.delete', $p) }}" onsubmit="return confirm('Hapus thumbnail tersimpan?')" style="margin-top:6px">@csrf<button class="btn sm">Hapus thumbnail tersimpan</button></form>
    @endif
  </div>
</div>

<div>
  <form method="post" action="{{ route('projects.schedule', $p) }}" class="card">
    @csrf
    <h2>4. Jadwalkan upload</h2>
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
@push('scripts')
<script>
(function () {
  const $ = id => document.getElementById(id), c = $('tc'), x = c.getContext('2d');
  const W = 1280, H = 720, bgs = [['#3b1d6b', '#ff7a45'], ['#0b1b3a', '#2a5db0'], ['#7a1f4d', '#ff8fa3'], ['#0f3d2e', '#5fbf8f'], ['#15151a', '#15151a']];
  let img = null;

  function wrap(text, font, max) {
    x.font = font; const out = []; let line = '';
    text.split(/\s+/).filter(Boolean).forEach(w => {
      const t = line ? line + ' ' + w : w;
      if (x.measureText(t).width > max && line) { out.push(line); line = w; } else line = t;
    });
    if (line) out.push(line); return out;
  }

  function draw() {
    const [a, b] = bgs[+$('tBg').value];
    const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, a); g.addColorStop(1, b);
    x.fillStyle = g; x.fillRect(0, 0, W, H);
    if (img) {
      const r = Math.max(W / img.width, H / img.height), w = img.width * r, h = img.height * r;
      x.drawImage(img, (W - w) / 2, (H - h) / 2, w, h);
    }
    const sh = x.createLinearGradient(0, 0, 0, H); sh.addColorStop(0, 'rgba(0,0,0,.15)'); sh.addColorStop(1, 'rgba(0,0,0,.65)');
    x.fillStyle = sh; x.fillRect(0, 0, W, H);

    const align = $('tAlign').value, pad = 70, max = W - pad * 2, color = $('tColor').value, accent = $('tAccent').value;
    let size = +$('tSize').value, lines, lh;
    $('tSizeV').textContent = size;
    const fam = '"Plus Jakarta Sans", Arial, sans-serif';
    const text = $('tMain').value.trim().toUpperCase();
    do { lines = wrap(text, `700 ${size}px ${fam}`, max); lh = size * 1.08; size -= 4; } while ((lines.length > 4 || lines.length * lh > H - 260) && size > 40);
    size += 4;
    x.font = `700 ${size}px ${fam}`; x.textBaseline = 'top'; x.textAlign = align;
    const tx = align === 'center' ? W / 2 : pad, total = lines.length * lh, top = (H - total) / 2 - 20;
    x.lineJoin = 'round'; x.lineWidth = size * 0.14;
    lines.forEach((l, i) => {
      const y = top + i * lh;
      x.strokeStyle = 'rgba(0,0,0,.85)'; x.strokeText(l, tx, y);
      x.fillStyle = i === lines.length - 1 && lines.length > 1 ? accent : color; x.fillText(l, tx, y);
    });
    const sub = $('tSub').value.trim();
    if (sub) {
      x.font = `700 44px ${fam}`; x.textBaseline = 'alphabetic';
      const w = x.measureText(sub).width + 48, bx = align === 'center' ? (W - w) / 2 : pad;
      x.fillStyle = accent; x.beginPath(); x.roundRect(bx, H - 120, w, 70, 14); x.fill();
      x.fillStyle = '#111'; x.textAlign = 'left'; x.fillText(sub, bx + 24, H - 70);
    }
  }

  ['tMain', 'tSub', 'tBg', 'tColor', 'tAccent', 'tAlign', 'tSize'].forEach(i => $(i).addEventListener('input', draw));
  $('tImg').addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) { img = null; draw(); return; }
    const im = new Image(); im.onload = () => { img = im; draw(); }; im.src = URL.createObjectURL(f);
  });
  document.querySelectorAll('.tpick').forEach(r => r.addEventListener('click', () => { $('tMain').value = r.dataset.main; $('tSub').value = r.dataset.sub || $('tSub').value; draw(); }));

  function dataUrl() {
    for (const q of [.92, .85, .75, .6]) { const d = c.toDataURL('image/jpeg', q); if (d.length * 0.75 < 1.9 * 1024 * 1024) return d; }
    return c.toDataURL('image/jpeg', .5);
  }
  $('tDl').addEventListener('click', () => { $('tDl').href = dataUrl(); });
  $('tSave').addEventListener('click', async () => {
    const m = $('tMsg'); m.textContent = 'Menyimpan...';
    try {
      const res = await fetch(@json(route('projects.thumb.save', $p)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ image: dataUrl() }),
      });
      const j = await res.json();
      if (!res.ok) throw new Error(j.error || j.message || 'Gagal menyimpan.');
      m.textContent = 'Thumbnail tersimpan. Akan ikut terunggah saat upload diproses.';
    } catch (e) { m.textContent = 'Gagal: ' + e.message; }
  });

  (document.fonts ? document.fonts.load('700 60px "Plus Jakarta Sans"').catch(() => {}) : Promise.resolve()).then(draw);
  draw();
})();
</script>
@endpush

