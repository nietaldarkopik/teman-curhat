@extends('layouts.app')
@section('title', 'Jadwal upload')
@section('content')
<div class="head">
  <div><h1>Jadwal upload</h1><p class="muted" style="margin:0">Semua video yang dijadwalkan ke YouTube dan Facebook.</p></div>
  <form method="post" action="{{ route('uploads.run') }}">@csrf<button class="btn">Proses antrean sekarang</button></form>
</div>
<div class="card scroll-x">
  <table class="t">
    <tr><th>Tayang</th><th>Platform</th><th>Project</th><th>Status</th><th>Link / pesan</th><th></th></tr>
    @forelse ($pubs as $u)
      <tr>
        <td style="white-space:nowrap">{{ $u->publish_at?->translatedFormat('d M Y, H:i') }}</td>
        <td>{{ $u->platformLabel() }}@if ($u->platform === 'youtube')<br><span class="small muted">{{ $u->privacy }}</span>@endif</td>
        <td><a href="{{ route('projects.publish', $u->project_id) }}">{{ $u->project->title }}</a></td>
        <td><span class="pill {{ $u->status }}">{{ $u->statusLabel() }}</span>@if ($u->attempts > 1)<br><span class="small muted">{{ $u->attempts }}x percobaan</span>@endif</td>
        <td class="small">
          @if ($u->remote_url)<a href="{{ $u->remote_url }}" target="_blank" rel="noopener">{{ $u->remote_url }}</a>@endif
          @if ($u->error)<span style="color:var(--rose)">{{ $u->error }}</span>@endif
        </td>
        <td class="row">
          @if ($u->status === 'gagal')<form method="post" action="{{ route('uploads.retry', $u) }}">@csrf<button class="btn sm">Coba lagi</button></form>@endif
          @if ($u->status !== 'mengunggah')<form method="post" action="{{ route('uploads.destroy', $u) }}" data-confirm="Hapus jadwal ini dari daftar?">@csrf<button class="btn sm danger">Hapus</button></form>@endif
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Belum ada jadwal upload.</td></tr>
    @endforelse
  </table>
</div>
{{ $pubs->links() }}
@endsection
