<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teleprompter · {{ $p->title }}</title>
<link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,400;7..72,600&family=Plus+Jakarta+Sans:wght@500;700&display=swap" rel="stylesheet">
<style>
  html,body{margin:0;height:100%;background:#0E1320;color:#F3F1EA;font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .bar{position:fixed;top:0;left:0;right:0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 16px;background:#141B2D;border-bottom:1px solid #2A3350;z-index:2;font-size:14px}
  .bar button{background:#1F2942;color:#F3F1EA;border:1px solid #2C3852;border-radius:8px;padding:7px 12px;font:inherit;font-weight:700;cursor:pointer}
  .bar label{display:flex;gap:6px;align-items:center;color:#B8C0D4}
  .bar input[type=range]{accent-color:#E9B949;width:120px}
  .bar .t{font-weight:700;margin-right:auto}
  #scroll{position:absolute;inset:54px 0 0;overflow-y:auto;padding:30vh 7vw 70vh}
  #text{max-width:30ch;margin:0 auto;font-family:"Literata",Georgia,serif;line-height:1.55;font-size:40px;white-space:pre-wrap}
  .mirror #text{transform:scaleX(-1)}
  .h{display:block;font-family:"Plus Jakarta Sans",sans-serif;font-size:.45em;font-weight:700;color:#E9B949;margin:1.8em 0 .4em}
  .cue{color:#9FB3E0;font-style:italic}
  .bb{display:block;border-left:4px solid #2A3350;padding:.1em 0 .1em .7em;margin:.6em 0;opacity:.45;transition:opacity .2s,border-color .2s}
  .bb.cur{opacity:1;border-color:#E9B949}
  .bb.done{opacity:.3}
  .tag{display:block;font-family:"Plus Jakarta Sans",sans-serif;font-size:.4em;font-weight:700;color:#E9B949;margin-bottom:.3em;letter-spacing:.04em}
  .guide{position:fixed;left:0;right:0;top:38vh;height:2px;background:rgba(233,185,73,.35);pointer-events:none}
</style>
</head>
<body>
<div class="bar">
  <span class="t">{{ $p->title }}</span>
  <button id="play">Mulai gulir (spasi)</button>
  <label>Kecepatan <input type="range" id="speed" min="10" max="150" value="45"></label>
  <label>Ukuran <input type="range" id="size" min="24" max="80" value="40"></label>
  <label><input type="checkbox" id="mirror"> Cermin</label>
  <span id="link" style="color:#B8C0D4">Sinkron: → / ← / Enter menggerakkan layar</span>
</div>
<div id="scroll"><div id="text"><span class="h" id="open">PEMBUKA</span>{{ $p->opening ?: '(Pembuka belum dibuat)' }}
@foreach ($p->items as $i => $it)
<span class="h" id="item-{{ $i }}">CURHAT {{ $i + 1 }} DARI {{ $p->items->count() }}</span>@if ($it->intro_text)<span class="cue">{{ $it->intro_text }}</span>

@endif
@foreach ($it->bubbles() as $bi => $bt)<span class="bb" id="b-{{ $i }}-{{ $bi }}"><span class="tag">GELEMBUNG {{ $bi + 1 }} · tekan →</span>{{ $bt }}</span>
@endforeach
<span class="h">TANGGAPAN</span>{{ $it->reply_text ?: '(Balasan belum dibuat)' }}
<span class="h" id="ask-{{ $i }}">LEMPAR KE PENONTON</span>{{ $it->audience_question }}
@endforeach
<span class="h" id="close">PENUTUP</span>{{ $p->closing ?: '(Penutup belum dibuat)' }}</div></div>
<div class="guide"></div>
<script>
  const sc = document.getElementById('scroll'), txt = document.getElementById('text');
  let on = false, last = 0, acc = 0;
  function tick(t) {
    if (!on) return;
    if (last) { acc += (t - last) / 1000 * +speed.value; const w = Math.floor(acc); if (w) { sc.scrollTop += w; acc -= w; } }
    last = t; requestAnimationFrame(tick);
  }
  function toggle() { on = !on; last = 0; play.textContent = on ? 'Jeda (spasi)' : 'Mulai gulir (spasi)'; if (on) requestAnimationFrame(tick); }
  play.onclick = toggle;
  size.oninput = () => txt.style.fontSize = size.value + 'px';
  mirror.onchange = () => document.body.classList.toggle('mirror', mirror.checked);
  // Sinkron dengan jendela layar (/screen) di browser yang sama.
  const sync = 'BroadcastChannel' in window ? new BroadcastChannel('curhat-{{ $p->id }}') : null;
  if (sync) {
    sync.onmessage = e => {
      if (e.data.type !== 'state') return;
      document.querySelectorAll('.bb').forEach(x => x.classList.remove('cur', 'done'));
      const m = /^item-(\d+)$/.exec(e.data.key);
      let el = document.getElementById(e.data.key);
      if (m) {
        const n = e.data.bubble || 0;
        document.querySelectorAll('[id^="b-' + m[1] + '-"]').forEach((x, i) => x.classList.add(i < n - 1 ? 'done' : 'cur') || (i >= n && x.classList.remove('cur', 'done')));
        el = document.getElementById('b-' + m[1] + '-' + Math.max(0, n - 1)) || el;
      }
      if (el) sc.scrollTo({ top: el.offsetTop - sc.clientHeight * 0.35, behavior: 'smooth' });
    };
    sync.postMessage({ type: 'hello' });
  } else link.textContent = 'Sinkron tidak didukung browser ini';
  document.addEventListener('keydown', e => {
    if (sync && ['ArrowRight', 'PageDown', 'Enter'].includes(e.key)) { e.preventDefault(); sync.postMessage({ type: 'next' }); }
    if (sync && ['ArrowLeft', 'PageUp', 'Backspace'].includes(e.key)) { e.preventDefault(); sync.postMessage({ type: 'prev' }); }
    if (e.key === ' ') { e.preventDefault(); toggle(); }
    if (e.key === 'ArrowUp') speed.value = +speed.value + 5;
    if (e.key === 'ArrowDown') speed.value = +speed.value - 5;
  });
</script>
</body>
</html>
