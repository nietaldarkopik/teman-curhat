@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted" style="margin:0">
      @if ($lastSync) DM terakhir disinkron {{ \Illuminate\Support\Carbon::parse($lastSync)->diffForHumans() }}.
      @else DM belum pernah disinkron. @endif
    </p>
  </div>
  <div class="row">
    @if ($fbConnected)
      <form method="post" action="{{ route('sync') }}" class="inline">@csrf<button class="btn lamp">Sinkron DM sekarang</button></form>
    @endif
    <a class="btn primary" href="{{ route('projects.index') }}">Buat project video</a>
  </div>
</div>

<div class="grid g4" style="margin-bottom:16px">
  @foreach (['baru' => 'Belum dibaca', 'dibaca' => 'Sudah dibaca', 'dipilih' => 'Masuk project', 'dibacakan' => 'Sudah dibacakan'] as $k => $label)
    <a class="card stat" href="{{ route('inbox.index', ['status' => $k]) }}"><b>{{ $counts[$k] ?? 0 }}</b><span class="muted">{{ $label }}</span></a>
  @endforeach
</div>

<div class="grid side">
  <div>
    <div class="card">
      <div class="head" style="margin-bottom:8px"><h2 style="margin:0">Upload terdekat</h2><a class="btn sm" href="{{ route('uploads.index') }}">Semua jadwal</a></div>
      @if ($upcoming->isEmpty())
        <p class="muted">Belum ada video yang dijadwalkan. Selesaikan project video, lalu jadwalkan dari halaman Upload.</p>
      @else
        <div class="scroll-x"><table class="t">
          <tr><th>Tayang</th><th>Platform</th><th>Project</th><th>Status</th></tr>
          @foreach ($upcoming as $u)
            <tr>
              <td>{{ $u->publish_at?->translatedFormat('d M, H:i') }}</td>
              <td>{{ $u->platformLabel() }}</td>
              <td><a href="{{ route('projects.publish', $u->project_id) }}">{{ $u->project->title }}</a></td>
              <td><span class="pill {{ $u->status }}">{{ $u->statusLabel() }}</span></td>
            </tr>
          @endforeach
        </table></div>
      @endif
    </div>
    <div class="card">
      <h2>Project terbaru</h2>
      @forelse ($projects as $p)
        <div class="row" style="justify-content:space-between;padding:6px 0;border-top:1px solid var(--line)">
          <a href="{{ route('projects.show', $p) }}"><b>{{ $p->title }}</b></a>
          <span class="row"><span class="muted small">{{ $p->items_count }} curhat</span><span class="pill">{{ $p->statusLabel() }}</span></span>
        </div>
      @empty
        <p class="muted">Belum ada project.</p>
      @endforelse
    </div>
  </div>
  <div class="card">
    <h2>Kesiapan sistem</h2>
    <ul class="checklist">
      @foreach ($checks as [$label, $ok])
        <li><span class="{{ $ok ? 'y' : 'x' }}">{{ $ok ? '✓' : '✕' }}</span> {{ $label }}</li>
      @endforeach
    </ul>
    <p class="muted small">Yang belum siap bisa diatur di <a href="{{ route('settings') }}">Pengaturan</a>. Scheduler dipasang otomatis oleh install.bat lewat Task Scheduler Windows.</p>
  </div>
</div>
@endsection
