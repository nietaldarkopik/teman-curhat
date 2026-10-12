@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
<div class="head"><div><h1>Pengaturan</h1><p class="muted" style="margin:0">Kunci rahasia disimpan terenkripsi. Kosongkan kolom rahasia bila tidak ingin mengubahnya.</p></div></div>

<form method="post" action="{{ route('settings.update') }}">
@csrf
<div class="grid g2">
  <div class="card">
    <h2>Channel dan gaya host</h2>
    <label class="f">Nama channel / page</label>
    <input type="text" name="channel_name" value="{{ $s('channel_name') }}">
    <label class="f">Gaya bicara host <span class="hint">(dipakai AI saat menulis naskah)</span></label>
    <textarea name="host_style" style="min-height:120px">{{ $s('host_style') }}</textarea>
    <label class="f">Folder video hasil rekaman</label>
    <input type="text" name="video_folder" value="{{ $s('video_folder') }}" placeholder="C:\Users\Opik\Videos">
    <div class="grid g2">
      <div><label class="f">Jam tayang default</label><input type="time" name="default_publish_time" value="{{ $s('default_publish_time') }}"></div>
      <div><label class="f">Visibilitas YouTube default</label>
        <select name="default_privacy">
          @foreach (['public' => 'Publik', 'unlisted' => 'Tidak publik', 'private' => 'Pribadi'] as $k => $v)<option value="{{ $k }}" @selected($s('default_privacy') === $k)>{{ $v }}</option>@endforeach
        </select></div>
    </div>
  </div>

  <div class="card">
    <h2>AI Penulis Naskah</h2>
    <label class="f">Penyedia</label>
    <select name="ai_provider" onchange="document.getElementById('prov-anthropic').style.display=this.value==='anthropic'?'':'none';document.getElementById('prov-ninerouter').style.display=this.value==='nine_router'?'':'none';">
      <option value="anthropic" @selected($s('ai_provider') === 'anthropic')>Claude API (langsung ke Anthropic)</option>
      <option value="nine_router" @selected($s('ai_provider') === 'nine_router')>9Router (gateway lokal, dipakai bareng clipper-tool)</option>
    </select>

    <div id="prov-anthropic" style="{{ $s('ai_provider') === 'nine_router' ? 'display:none' : '' }}">
      <label class="f">API key <span class="hint">{{ $has('anthropic_key') ? '(sudah tersimpan)' : '(belum diisi)' }}</span></label>
      <input type="password" name="anthropic_key" autocomplete="off" placeholder="{{ $has('anthropic_key') ? '••••••••  biarkan kosong bila tidak diubah' : 'sk-ant-...' }}">
      <p class="muted small">Buat API key di console.anthropic.com. Pemakaian API dibayar terpisah dari langganan Claude.</p>
      <label class="f">Model</label>
      <input type="text" name="anthropic_model" value="{{ $s('anthropic_model') }}">
    </div>

    <div id="prov-ninerouter" style="{{ $s('ai_provider') === 'nine_router' ? '' : 'display:none' }}">
      <label class="f">URL 9Router</label>
      <input type="text" name="nine_router_base_url" value="{{ $s('nine_router_base_url') }}">
      <label class="f">API key <span class="hint">{{ $has('nine_router_api_key') ? '(sudah tersimpan)' : '(belum diisi)' }}</span></label>
      <input type="password" name="nine_router_api_key" autocomplete="off" placeholder="{{ $has('nine_router_api_key') ? '••••••••  biarkan kosong bila tidak diubah' : 'sk-...' }}">
      <label class="f">Model</label>
      <input type="text" name="nine_router_model" value="{{ $s('nine_router_model') }}">
      <p class="muted small">Proses <code>9router</code> harus sedang berjalan di komputer ini (lihat <code>start-all.bat</code> milik clipper-tool) supaya penyedia ini bisa dipakai.</p>
    </div>
  </div>

  <div class="card">
    <h2>Narasi Suara (TTS)</h2>
    <p class="muted small">Dipakai tombol "Buat narasi suara (TTS)" di Inbox dan Short Harian Otomatis, lewat 9Router yang sama seperti di atas. Dua profil suara — Short Harian otomatis memilih yang sesuai gender pengirim curhat (deteksi AI saat anonimisasi).</p>
    <label class="f">Model TTS</label>
    <input type="text" name="tts_model" value="{{ $s('tts_model') }}">

    <p class="muted small" style="margin-top:14px;margin-bottom:4px"><b>Suara pria (default)</b></p>
    <label class="f">Suara <span class="hint">(nama voice Gemini, mis. Achird, Sulafat, Umbriel — kosongkan bila model tidak butuh voice)</span></label>
    <input type="text" name="tts_voice" value="{{ $s('tts_voice') }}">
    <label class="f">Karakter suara <span class="hint">(instruksi gaya bicara, bebas bahasa Inggris/Indonesia)</span></label>
    <textarea name="tts_voice_character" style="min-height:140px">{{ $s('tts_voice_character') }}</textarea>

    <p class="muted small" style="margin-top:14px;margin-bottom:4px"><b>Suara wanita</b></p>
    <label class="f">Suara <span class="hint">(nama voice Gemini, mis. Kore, Despina, Aoede, Leda)</span></label>
    <input type="text" name="tts_voice_female" value="{{ $s('tts_voice_female') }}">
    <label class="f">Karakter suara</label>
    <textarea name="tts_voice_character_female" style="min-height:140px">{{ $s('tts_voice_character_female') }}</textarea>
  </div>

  <div class="card">
    <h2>Short Harian Otomatis</h2>
    <p class="muted small">Tiap hari beberapa curhat dari DM dianonimkan, dibacakan, dibalas, lalu dirender jadi video 9:16 dan diunggah ke YouTube + Facebook. Curhat yang ditandai sensitif atau berstatus "Tidak boleh" di menu Izin tidak pernah dipakai. Bila 9Router TTS gagal atau kuota habis, suara beralih ke edge-tts.</p>
    <div class="grid g2">
      <div><label class="f">Status</label>
        <select name="short_enabled">
          <option value="1" @selected($s('short_enabled') !== '0')>Aktif</option>
          <option value="0" @selected($s('short_enabled') === '0')>Nonaktif</option>
        </select></div>
      <div><label class="f">Video per hari</label><input type="number" min="1" max="20" name="short_daily_count" value="{{ $s('short_daily_count') }}"></div>
    </div>
    <div class="grid g2">
      <div><label class="f">Slot pertama jam</label><input type="time" name="short_make_time" value="{{ $s('short_make_time') }}"></div>
      <div><label class="f">Slot terakhir jam <span class="hint">(video disebar merata di antara dua jam ini)</span></label><input type="time" name="short_window_end" value="{{ $s('short_window_end') }}"></div>
    </div>
    <label class="f">Tayang berapa menit setelah dibuat <span class="hint">(bukan jam tetap — supaya beberapa video sehari tayang tersebar, bukan numpuk bareng di satu jam)</span></label>
    <input type="number" min="5" max="720" name="short_publish_delay_minutes" value="{{ $s('short_publish_delay_minutes') }}">
    <label class="f">Maksimal panjang curhat <span class="hint">(karakter; yang lebih panjang dilewati)</span></label>
    <input type="number" name="short_max_chars" value="{{ $s('short_max_chars') }}">
    <div class="grid g2">
      <div><label class="f">Watermark</label><input type="text" name="short_watermark" value="{{ $s('short_watermark') }}"></div>
      <div><label class="f">Ajakan di bawah video</label><input type="text" name="short_cta" value="{{ $s('short_cta') }}"></div>
    </div>
    <label class="f">Logo <span class="hint">(path file PNG, mis. C:\wamp64\www\curhat-tool\logo.png; kosong = logo bawaan)</span></label>
    <input type="text" name="short_logo" value="{{ $s('short_logo') }}">
    <label class="f">Musik latar <span class="hint">(opsional, path file mp3; dipelankan di bawah suara)</span></label>
    <input type="text" name="short_bgm" value="{{ $s('short_bgm') }}">
    <label class="f">Path FFmpeg <span class="hint">(kosongkan/"ffmpeg" bila sudah ada di PATH)</span></label>
    <input type="text" name="ffmpeg_path" value="{{ $s('ffmpeg_path') }}">
    <div class="grid g2">
      <div><label class="f">Path edge-tts <span class="hint">(suara cadangan)</span></label><input type="text" name="edge_tts_path" value="{{ $s('edge_tts_path') }}"></div>
      <div><label class="f">Suara edge-tts (pria)</label><input type="text" name="edge_tts_voice" value="{{ $s('edge_tts_voice') }}"></div>
    </div>
    <label class="f">Suara edge-tts (wanita)</label>
    <input type="text" name="edge_tts_voice_female" value="{{ $s('edge_tts_voice_female') }}">
  </div>

  <div class="card">
    <h2>Facebook Page</h2>
    @if ($fb->connected())
      <p class="info">Terhubung ke Page <b>{{ $s('fb_page_name') }}</b>.</p>
    @endif
    <label class="f">App ID</label>
    <input type="text" name="fb_app_id" value="{{ $s('fb_app_id') }}">
    <label class="f">App Secret <span class="hint">{{ $has('fb_app_secret') ? '(sudah tersimpan)' : '' }}</span></label>
    <input type="password" name="fb_app_secret" autocomplete="off">
    <label class="f">Valid OAuth Redirect URI <span class="hint">(daftarkan di Facebook Login > Settings)</span></label>
    <code class="copy">{{ $fb->redirectUri() }}</code>
    <label class="f">Login Configuration ID <span class="hint">(opsional, dari Facebook Login &gt; Configurations; bila diisi menggantikan Izin/scope di bawah)</span></label>
    <input type="text" name="fb_login_config_id" value="{{ $s('fb_login_config_id') }}">
    <details style="margin-top:10px"><summary class="muted small">Pengaturan lanjutan</summary>
      <div class="grid g2">
        <div><label class="f">Versi Graph API</label><input type="text" name="fb_graph_version" value="{{ $s('fb_graph_version') }}"></div>
        <div><label class="f">Sinkron awal (hari ke belakang)</label><input type="number" name="fb_sync_days" value="{{ $s('fb_sync_days') }}"></div>
      </div>
      <label class="f">Gabungkan pesan beruntun dari orang yang sama dalam (jam)</label>
      <input type="number" name="fb_group_hours" value="{{ $s('fb_group_hours') }}">
      <label class="f">Izin (scope)</label>
      <input type="text" name="fb_scopes" value="{{ $s('fb_scopes') }}">
    </details>
  </div>

  <div class="card">
    <h2>YouTube</h2>
    @if ($yt->connected())
      <p class="info">Terhubung ke channel <b>{{ $s('yt_channel_title') }}</b>.</p>
    @endif
    <label class="f">Google OAuth Client ID</label>
    <input type="text" name="google_client_id" value="{{ $s('google_client_id') }}">
    <label class="f">Client Secret <span class="hint">{{ $has('google_client_secret') ? '(sudah tersimpan)' : '' }}</span></label>
    <input type="password" name="google_client_secret" autocomplete="off">
    <label class="f">Authorized redirect URI <span class="hint">(daftarkan di Google Cloud Console)</span></label>
    <code class="copy">{{ $yt->redirectUri() }}</code>
  </div>
</div>
<div class="row" style="margin-bottom:16px"><button class="btn primary">Simpan pengaturan</button></div>
</form>

<div class="grid g2">
  <div class="card">
    <h3>Hubungkan Facebook</h3>
    @if ($pages)
      <form method="post" action="{{ route('settings.facebook.page') }}" class="row">
        @csrf
        <select name="page_id" style="flex:1">@foreach ($pages as $pg)<option value="{{ $pg['id'] }}">{{ $pg['name'] }}</option>@endforeach</select>
        <button class="btn primary">Pakai Page ini</button>
      </form>
    @else
      <div class="row">
        <a class="btn lamp" href="{{ route('oauth.facebook') }}">{{ $fb->connected() ? 'Hubungkan ulang' : 'Login dengan Facebook' }}</a>
        @if ($fb->connected())
          <form method="post" action="{{ route('settings.facebook.disconnect') }}" data-confirm="Putuskan Page Facebook?">@csrf<button class="btn danger">Putuskan</button></form>
        @endif
      </div>
    @endif
    <p class="muted small">Simpan App ID dan App Secret dulu sebelum login.</p>
  </div>
  <div class="card">
    <h3>Hubungkan YouTube</h3>
    <div class="row">
      <a class="btn lamp" href="{{ route('oauth.google') }}">{{ $yt->connected() ? 'Hubungkan ulang' : 'Login dengan Google' }}</a>
      @if ($yt->connected())
        <form method="post" action="{{ route('settings.youtube.disconnect') }}" data-confirm="Putuskan YouTube?">@csrf<button class="btn danger">Putuskan</button></form>
      @endif
    </div>
    <p class="muted small">Login dengan akun Google pemilik channel. Simpan Client ID dan Secret dulu.</p>
  </div>
</div>
@endsection
