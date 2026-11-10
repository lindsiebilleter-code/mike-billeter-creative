// CRT transition between the modern site and the 1999 edition, plus the typed-word trigger.
window.MBWFx = (function () {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function go(url) {
    if (reduced) { location.href = url; return; }
    const o = document.createElement('div'); o.className = 'mbw-fx-out'; o.setAttribute('aria-hidden', 'true'); document.body.appendChild(o);
    try { sessionStorage.setItem('mbw-fx', '1'); } catch (e) {}
    setTimeout(() => { location.href = url; }, 680);
  }
  function arrive() {
    let f = null; try { f = sessionStorage.getItem('mbw-fx'); sessionStorage.removeItem('mbw-fx'); } catch (e) {}
    if (!f || reduced) return;
    const o = document.createElement('div'); o.className = 'mbw-fx-in'; o.setAttribute('aria-hidden', 'true'); document.body.appendChild(o);
    setTimeout(() => o.remove(), 560);
  }
  // Rolling buffer: resets after 1.5s of silence; ignored inside text fields.
  function typed(word, cb) {
    let buf = '', last = 0;
    window.addEventListener('keydown', e => {
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const t = e.target; if (t && t.closest && t.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])')) return;
      if (typeof e.key !== 'string' || e.key.length !== 1) return;
      const now = Date.now(); if (now - last > 1500) buf = ''; last = now;
      buf = (buf + e.key.toLowerCase()).slice(-word.length);
      if (buf === word) { buf = ''; cb(); }
    });
  }
  return { go, arrive, typed, reduced };
})();
