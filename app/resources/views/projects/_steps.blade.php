<div class="steps">
  <a href="{{ route('projects.show', $p) }}" class="{{ request()->routeIs('projects.show') ? 'on' : '' }}">1. Susun curhat dan naskah</a>
  <a href="{{ route('projects.script', $p) }}" target="_blank">2. Teleprompter</a>
  <a href="{{ route('projects.screen', $p) }}" target="_blank">3. Tampilan layar untuk rekam</a>
  <a href="{{ route('projects.publish', $p) }}" class="{{ request()->routeIs('projects.publish') ? 'on' : '' }}">4. Video, judul, dan jadwal upload</a>
</div>
