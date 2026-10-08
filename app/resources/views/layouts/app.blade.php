<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · Studio Teman Curhat</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/curhat.css') }}">
</head>
<body>
<nav class="nav">
  <div class="nav-in">
    <a class="logo" href="{{ route('dashboard') }}">Teman Curhat <span>Studio</span></a>
    <a class="link {{ request()->routeIs('dashboard') ? 'on' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
    <a class="link {{ request()->routeIs('inbox.*') ? 'on' : '' }}" href="{{ route('inbox.index') }}">Inbox curhat</a>
    <a class="link {{ request()->routeIs('projects.*') ? 'on' : '' }}" href="{{ route('projects.index') }}">Project video</a>
    <a class="link {{ request()->routeIs('uploads.*') ? 'on' : '' }}" href="{{ route('uploads.index') }}">Jadwal upload</a>
    <a class="link {{ request()->routeIs('settings') ? 'on' : '' }}" href="{{ route('settings') }}">Pengaturan</a>
  </div>
</nav>
<main class="main">
  @if (session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
  @if (session('err'))<div class="flash err">{{ session('err') }}</div>@endif
  @if ($errors->any())
    <div class="flash err"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif
  @yield('content')
</main>
<script src="{{ asset('js/curhat.js') }}"></script>
@stack('scripts')
</body>
</html>
