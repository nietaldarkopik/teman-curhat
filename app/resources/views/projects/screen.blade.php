<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layar · {{ $p->title }}</title>
<link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Plus+Jakarta+Sans:wght@500;700&display=swap" rel="stylesheet">
<style>
  html,body{margin:0;height:100%;overflow:hidden;background:#1E2A44;color:#F3F1EA;font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .slide{position:absolute;inset:0;display:none;flex-direction:column;padding:5vh 7vw}
  .slide.on{display:flex}
  .top{display:flex;justify-content:space-between;align-items:center;font-weight:700;color:#B8C2D6;font-size:clamp(14px,1.6vw,24px)}
  .top b{color:#F2C14E}
  .chat{flex:1;overflow:hidden;display:flex;flex-direction:column;gap:1.4vh;justify-content:flex-start;margin-top:4vh}
  .bubble{align-self:flex-start;max-width:78%;background:#F3F1EA;color:#1E2A44;border-radius:22px 22px 22px 6px;padding:1.4vh 1.6vw;font-family:"Literata",Georgia,serif;font-size:clamp(16px,2vw,34px);line-height:1.45;opacity:0;transform:translateY(8px);transition:opacity .35s,transform .35s}
  .bubble.show{opacity:1;transform:none}
  .anon{display:flex;align-items:center;gap:12px;font-size:clamp(14px,1.5vw,22px);color:#B8C2D6;margin-top:3vh}
  .avatar{width:2.4em;height:2.4em;border-radius:50%;background:#2C3852;display:grid;place-items:center;font-weight:700;color:#F2C14E}
  .title{margin:auto 0;font-size:clamp(28px,5vw,84px);line-height:1.1;font-weight:700;max-width:18ch}
  .title small{display:block;font-size:.35em;color:#F2C14E;margin-bottom:.6em}
  .ask{margin:auto 0;font-family:"Literata",Georgia,serif;font-size:clamp(22px,3.4vw,58px);line-height:1.3;max-width:24ch}
  .ask small{display:block;font-family:"Plus Jakarta Sans",sans-serif;font-size:.4em;color:#F2C14E;margin-bottom:.8em;font-weight:700}
  .help{position:fixed;bottom:8px;right:12px;font-size:12px;color:#5B6B86}
  @media (prefers-reduced-motion:reduce){.bubble{transition:none}}
</style>
</head>
<body>
@php
  $split = function ($text) {
      $parts = preg_split("/\n+/", trim((string) $text)) ?: [];
      $out = [];
      foreach ($parts as $para) {
          // Pecah paragraf panjang menjadi beberapa gelembung chat.
          $sentences = preg_split('/(?<=[.!?])\s+/', $para) ?: [$para];
          $buf = '';
          foreach ($sentences as $s) {
              if (mb_strlen($buf.' '.$s) > 220 && $buf !== '') { $out[] = trim($buf); $buf = ''; }
              $buf .= ' '.$s;
          }
          if (trim($buf) !== '') $out[] = trim($buf);
      }
      return $out;
  };
@endphp
<section class="slide on">
  <div class="top"><span>{{ $channel }}</span><span>{{ $p->items->count() }} curhat hari ini</span></div>
  <div class="title"><small>Sesi baca curhat</small>{{ $p->title }}</div>
</section>
@foreach ($p->items as $i => $it)
  <section class="slide" data-bubbles>
    <div class="top"><span>Curhat <b>{{ $i + 1 }}</b> dari {{ $p->items->count() }}</span><span>{{ $it->curhat->topic }}</span></div>
    <div class="anon"><span class="avatar">?</span><span>Pengirim anonim</span></div>
    <div class="chat">
      @foreach ($split($it->read_text) as $b)<div class="bubble">{{ $b }}</div>@endforeach
    </div>
  </section>
  @if ($it->audience_question)
  <section class="slide">
    <div class="top"><span>Curhat <b>{{ $i + 1 }}</b> dari {{ $p->items->count() }}</span><span>Tulis jawabanmu di komentar</span></div>
    <div class="ask"><small>Menurut kalian?</small>{{ $it->audience_question }}</div>
  </section>
  @endif
@endforeach
<section class="slide">
  <div class="top"><span>{{ $channel }}</span><span></span></div>
  <div class="title"><small>Mau curhat juga?</small>Kirim DM ke page Facebook {{ $channel }}</div>
</section>
<div class="help">→ / spasi: lanjut · ←: kembali · F: layar penuh</div>
<script>
  const slides = [...document.querySelectorAll('.slide')];
  let s = 0, b = 0;
  function bubbles() { return [...slides[s].querySelectorAll('.bubble')]; }
  function fit() {
    // Gulung gelembung lama ke atas bila chat sudah penuh.
    const chat = slides[s].querySelector('.chat');
    if (!chat) return;
    const shown = bubbles().filter(x => x.classList.contains('show'));
    shown.forEach(x => x.style.display = '');
    let i = 0;
    while (chat.scrollHeight > chat.clientHeight && i < shown.length - 1) { shown[i].style.display = 'none'; i++; }
  }
  function show(n) { slides.forEach((el, i) => el.classList.toggle('on', i === n)); s = n; b = 0; bubbles().forEach(x => { x.classList.remove('show'); x.style.display = ''; }); }
  function next() {
    const bs = bubbles();
    if (b < bs.length) { bs[b].classList.add('show'); b++; fit(); return; }
    if (s < slides.length - 1) show(s + 1);
  }
  function prev() {
    const bs = bubbles();
    if (b > 0) { b--; bs[b].classList.remove('show'); fit(); return; }
    if (s > 0) { show(s - 1); }
  }
  document.addEventListener('keydown', e => {
    if (['ArrowRight', ' ', 'PageDown', 'Enter'].includes(e.key)) { e.preventDefault(); next(); }
    if (['ArrowLeft', 'PageUp', 'Backspace'].includes(e.key)) { e.preventDefault(); prev(); }
    if (e.key.toLowerCase() === 'f') document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
  });
  document.addEventListener('click', next);
</script>
</body>
</html>
