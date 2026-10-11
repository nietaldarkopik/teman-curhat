<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Layar · {{ $p->title }}</title>
<link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Plus+Jakarta+Sans:wght@500;700&display=swap" rel="stylesheet">
<style>
  html{background:#1E2A44}
  html,body{margin:0;height:100%;overflow:hidden;color:#F3F1EA;font-family:"Plus Jakarta Sans",system-ui,sans-serif}
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
  /* Latar bergerak: gradien hangat ungu-biru-merah yang bergeser perlahan. */
  .bg{position:fixed;inset:0;z-index:-1;overflow:hidden;background:linear-gradient(135deg,#1B1A3A,#2A1E48 45%,#3A1F3F);background-size:200% 200%;animation:drift 40s ease-in-out infinite}
  .bg i{position:absolute;width:70vmax;height:70vmax;border-radius:50%;opacity:.55;will-change:transform}
  .bg i:nth-child(1){left:-15vmax;top:-20vmax;background:radial-gradient(circle,#6D4BD6 0,transparent 62%);animation:f1 34s ease-in-out infinite}
  .bg i:nth-child(2){right:-20vmax;top:5vh;background:radial-gradient(circle,#2F6BD9 0,transparent 62%);animation:f2 41s ease-in-out infinite}
  .bg i:nth-child(3){left:10vw;bottom:-30vmax;background:radial-gradient(circle,#D8456A 0,transparent 62%);animation:f3 37s ease-in-out infinite}
  .bg i:nth-child(4){right:5vw;bottom:-35vmax;width:55vmax;height:55vmax;opacity:.35;background:radial-gradient(circle,#F2A24E 0,transparent 62%);animation:f1 47s ease-in-out infinite reverse}
  .bg::after{content:"";position:absolute;inset:0;background:radial-gradient(ellipse at center,transparent 40%,rgba(10,10,25,.45))}
  @keyframes drift{0%,100%{background-position:0 50%}50%{background-position:100% 50%}}
  @keyframes f1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(18vw,12vh) scale(1.2)}}
  @keyframes f2{0%,100%{transform:translate(0,0) scale(1.1)}50%{transform:translate(-20vw,18vh) scale(.9)}}
  @keyframes f3{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(15vw,-14vh) scale(1.25)}}
  @media (prefers-reduced-motion:reduce){.bg,.bg i{animation:none}}
  .help{position:fixed;bottom:8px;right:12px;font-size:12px;color:#5B6B86}
  @media (prefers-reduced-motion:reduce){.bubble{transition:none}}
</style>
</head>
<body>
<div class="bg"><i></i><i></i><i></i><i></i></div>
<section class="slide on" data-key="open">
  <div class="top"><span>{{ $channel }}</span><span>{{ $p->items->count() }} curhat hari ini</span></div>
  <div class="title"><small>Sesi baca curhat</small>{{ $p->title }}</div>
</section>
@foreach ($p->items as $i => $it)
  <section class="slide" data-bubbles data-key="item-{{ $i }}">
    <div class="top"><span>Curhat <b>{{ $i + 1 }}</b> dari {{ $p->items->count() }}</span><span>{{ $it->curhat->topic }}</span></div>
    <div class="anon"><span class="avatar">?</span><span>Pengirim anonim</span></div>
    <div class="chat">
      @foreach ($it->bubbles() as $b)<div class="bubble">{{ $b }}</div>@endforeach
    </div>
  </section>
  @if ($it->audience_question)
  <section class="slide" data-key="ask-{{ $i }}">
    <div class="top"><span>Curhat <b>{{ $i + 1 }}</b> dari {{ $p->items->count() }}</span><span>Tulis jawabanmu di komentar</span></div>
    <div class="ask"><small>Menurut kalian?</small>{{ $it->audience_question }}</div>
  </section>
  @endif
@endforeach
<section class="slide" data-key="close">
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
  const sync = 'BroadcastChannel' in window ? new BroadcastChannel('curhat-{{ $p->id }}') : null;
  function announce() { sync && sync.postMessage({ type: 'state', key: slides[s].dataset.key, bubble: b }); }
  function show(n) { slides.forEach((el, i) => el.classList.toggle('on', i === n)); s = n; b = 0; bubbles().forEach(x => { x.classList.remove('show'); x.style.display = ''; }); announce(); }
  function next() {
    const bs = bubbles();
    if (b < bs.length) { bs[b].classList.add('show'); b++; fit(); announce(); return; }
    if (s < slides.length - 1) show(s + 1);
  }
  function prev() {
    const bs = bubbles();
    if (b > 0) { b--; bs[b].classList.remove('show'); fit(); announce(); return; }
    if (s > 0) { show(s - 1); }
  }
  document.addEventListener('keydown', e => {
    if (['ArrowRight', ' ', 'PageDown', 'Enter'].includes(e.key)) { e.preventDefault(); next(); }
    if (['ArrowLeft', 'PageUp', 'Backspace'].includes(e.key)) { e.preventDefault(); prev(); }
    if (e.key.toLowerCase() === 'f') document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
  });
  document.addEventListener('click', next);
  // Sinkron dengan jendela teleprompter (/script) di browser yang sama.
  if (sync) sync.onmessage = e => {
    if (e.data.type === 'next') next();
    if (e.data.type === 'prev') prev();
    if (e.data.type === 'hello') announce();
  };
  announce();
</script>
</body>
</html>
