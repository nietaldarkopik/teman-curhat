@extends('layouts.app')
@section('title', 'Izin Pengirim')
@section('content')
<div class="head"><div>
  <h1>Izin Pengirim</h1>
  <p class="muted" style="margin:0">Hanya curhat berstatus <b>Boleh dibacakan</b> yang bisa dimasukkan ke project video.</p>
</div></div>

<div class="card">
  <h2>Pesan untuk minta izin</h2>
  <p class="muted small">Salin, kirim lewat Messenger, lalu ubah statusnya di bawah setelah pengirim menjawab. Teksnya boleh diubah dulu sebelum disalin.</p>
  <textarea id="consentTpl" style="min-height:130px">{{ $template }}</textarea>
  <button type="button" class="btn sm" onclick="navigator.clipboard.writeText(document.getElementById('consentTpl').value).then(() => { this.textContent = 'Tersalin'; })">Salin pesan</button>
</div>

<div class="card">
  <p>
    <a href="{{ route('consent.index') }}" class="btn sm {{ $status ? 'ghost' : 'primary' }}">Semua</a>
    @foreach (\App\Models\Curhat::CONSENTS as $k => $label)
      <a href="{{ route('consent.index', ['status' => $k]) }}" class="btn sm {{ $status === $k ? 'primary' : 'ghost' }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
  </p>

  <div style="overflow-x:auto">
  <table>
    <tr><th>Masuk</th><th>Curhat</th><th style="min-width:320px">Izin</th></tr>
    @forelse ($curhats as $c)
      <tr>
        <td class="small muted" style="white-space:nowrap">{{ optional($c->received_at)->format('d M Y') }}</td>
        <td>
          @if ($c->topic)<b>{{ $c->topic }}</b><br>@endif
          {{ $c->summary ?: \Illuminate\Support\Str::limit($c->readableText(), 160) }}
        </td>
        <td>
          <form method="post" action="{{ route('consent.update', $c) }}">
            @csrf
            <select name="consent_status">
              @foreach (\App\Models\Curhat::CONSENTS as $k => $label)
                <option value="{{ $k }}" @selected($c->consent_status === $k)>{{ $label }}</option>
              @endforeach
            </select>
            <input type="text" name="consent_note" value="{{ $c->consent_note }}" placeholder="Catatan, misalnya: setuju lewat DM 5 Okt">
            <button class="btn sm">Simpan</button>
            @if ($c->consent_at)<div class="muted small">Diubah {{ $c->consent_at->format('d M Y H:i') }}</div>@endif
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="3" class="muted">Tidak ada curhat di daftar ini.</td></tr>
    @endforelse
  </table>
  </div>
</div>
{{ $curhats->links() }}
@endsection
