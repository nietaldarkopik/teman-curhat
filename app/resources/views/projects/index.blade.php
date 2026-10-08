@extends('layouts.app')
@section('title', 'Project video')
@section('content')
<div class="head"><div><h1>Project video</h1><p class="muted" style="margin:0">Satu project = satu video berisi 5-10 curhat.</p></div></div>
<div class="grid side">
  <div class="list">
    @forelse ($projects as $p)
      <div class="item" style="grid-template-columns:minmax(0,1fr) auto">
        <div>
          <a href="{{ route('projects.show', $p) }}"><b>{{ $p->title }}</b></a>
          <div class="meta small muted">{{ $p->items_count }} curhat @if ($p->planned_date) · rekam {{ $p->planned_date->translatedFormat('d M Y') }} @endif</div>
        </div>
        <span class="pill">{{ $p->statusLabel() }}</span>
      </div>
    @empty
      <div class="item" style="display:block"><p class="muted" style="margin:0">Belum ada project. Buat yang pertama di samping.</p></div>
    @endforelse
  </div>
  <form method="post" action="{{ route('projects.store') }}" class="card">
    @csrf
    <h2>Project baru</h2>
    <label class="f">Nama project</label>
    <input type="text" name="title" required placeholder="Contoh: Sesi curhat #12">
    <label class="f">Rencana tanggal rekam</label>
    <input type="date" name="planned_date">
    <div class="row" style="margin-top:14px"><button class="btn primary">Buat project</button></div>
  </form>
</div>
{{ $projects->links() }}
@endsection
