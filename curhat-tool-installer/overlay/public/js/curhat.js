(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;

  async function post(url) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
    });
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok) throw new Error(data.error || data.message || ('HTTP ' + res.status));
    return data;
  }

  // Jalankan beberapa permintaan AI satu per satu, dengan progres.
  async function runSequence(urls, out, btn) {
    if (!urls.length) { out.textContent = 'Tidak ada yang perlu diproses.'; return; }
    btn && (btn.disabled = true);
    let ok = 0, fail = 0, lastErr = '';
    for (let i = 0; i < urls.length; i++) {
      out.textContent = `Memproses ${i + 1} dari ${urls.length}... (bisa 10-40 detik per item)`;
      try { await post(urls[i]); ok++; } catch (e) { fail++; lastErr = e.message; }
    }
    out.textContent = `Selesai: ${ok} berhasil` + (fail ? `, ${fail} gagal. ${lastErr}` : '.') + ' Memuat ulang...';
    setTimeout(() => location.reload(), fail ? 3500 : 800);
  }
  window.CT = { post, runSequence };

  // Tombol AI tunggal: data-ai="url" data-out="#id"
  document.querySelectorAll('[data-ai]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.dataset.aiConfirm && !confirm(btn.dataset.aiConfirm)) return;
      const out = document.querySelector(btn.dataset.out) || btn.nextElementSibling;
      const urls = JSON.parse(btn.dataset.ai);
      runSequence(Array.isArray(urls) ? urls : [urls], out, btn);
    });
  });

  // Pilih semua
  document.querySelectorAll('[data-check-all]').forEach(box => {
    box.addEventListener('change', () => {
      document.querySelectorAll(box.dataset.checkAll).forEach(c => { c.checked = box.checked; });
      document.dispatchEvent(new Event('selection-changed'));
    });
  });

  // Aksi AI massal di inbox: data-bulk-ai="template-url-with-__ID__"
  document.querySelectorAll('[data-bulk-ai]').forEach(btn => {
    btn.addEventListener('click', () => {
      const ids = [...document.querySelectorAll('input.pick:checked')].map(c => c.value);
      const out = document.querySelector(btn.dataset.out);
      if (!ids.length) { out.textContent = 'Centang curhat dulu.'; return; }
      runSequence(ids.map(id => btn.dataset.bulkAi.replace('__ID__', id)), out, btn);
    });
  });

  // Hitung yang dicentang
  const counter = document.querySelector('[data-selected-count]');
  function recount() {
    if (counter) counter.textContent = document.querySelectorAll('input.pick:checked').length;
  }
  document.addEventListener('change', e => { if (e.target.classList?.contains('pick')) recount(); });
  document.addEventListener('selection-changed', recount);

  // Konfirmasi
  document.querySelectorAll('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });
})();
