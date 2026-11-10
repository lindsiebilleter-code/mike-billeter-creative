// Modern edition: page behavior (form, hero edit line, sand field, scroll motion, 1999 trigger).
(function () {
  const reduced = MBWFx.reduced;
  MBWFx.arrive();
  const page = document.body.dataset.page || 'index';

  // 1999 edition: type "y2k" anywhere (outside text fields) or use the footer link.
  const toY2k = () => MBWFx.go('y2k/' + page + '.html' + location.hash);
  MBWFx.typed('y2k', toY2k);
  const tog = document.getElementById('y2k-toggle'); if (tog) tog.addEventListener('click', toY2k);

  const form = document.getElementById('contact-form');
  if (form && window.MBWContact) MBWContact.wire(form);

  // Hero edit line: strike the generic sentence, then write the real one.
  const edit = document.getElementById('edit-line');
  if (edit) {
    const apply = s => {
      edit.querySelectorAll('[data-cut]').forEach(el => el.classList.toggle('is-cut', +el.dataset.cut <= s));
      edit.querySelectorAll('[data-show]').forEach(el => el.classList.toggle('is-on', +el.dataset.show <= s));
      const c = document.getElementById('edit-caret'); if (c) c.style.opacity = s === 5 ? '1' : '0';
    };
    if (reduced) apply(6);
    else { const steps = [1400, 650, 650, 800, 700, 1000]; let t = 0; steps.forEach((d, i) => { t += d; setTimeout(() => apply(i + 1), t); }); }
  }

  // Interactive sand field behind the hero.
  const canvas = document.getElementById('sand');
  if (canvas && window.mountSand) { canvas.parentElement.style.touchAction = 'pan-y'; window.mountSand(canvas, canvas.parentElement, { background: '#fafaf7' }); }

  // Scroll motion. Everything animated here is fully visible without JavaScript.
  const gsap = window.gsap, ST = window.ScrollTrigger;
  if (!gsap || !ST || reduced) return;
  gsap.registerPlugin(ST);
  gsap.fromTo('[data-hero-line]', { yPercent: 110 }, { yPercent: 0, duration: 1.2, stagger: 0.09, ease: 'power4.out', delay: 0.15 });
  document.querySelectorAll('[data-reveal]').forEach(el => {
    gsap.fromTo(el, { y: 36, opacity: 0 }, { y: 0, opacity: 1, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: el, start: 'top 88%', once: true } });
  });
  document.querySelectorAll('[data-reveal-group]').forEach(g => {
    gsap.fromTo(g.children, { y: 28, opacity: 0 }, { y: 0, opacity: 1, duration: 0.9, stagger: 0.08, ease: 'power3.out', scrollTrigger: { trigger: g, start: 'top 86%', once: true } });
  });
  document.querySelectorAll('[data-parallax]').forEach(img => {
    gsap.fromTo(img, { yPercent: -16 }, { yPercent: 16, ease: 'none', scrollTrigger: { trigger: img.parentElement, start: 'top bottom', end: 'bottom top', scrub: true } });
  });
  document.querySelectorAll('[data-scale]').forEach(el => {
    gsap.fromTo(el, { scale: 0.86, opacity: 0.25 }, { scale: 1, opacity: 1, ease: 'none', scrollTrigger: { trigger: el, start: 'top 95%', end: 'top 45%', scrub: true } });
  });
  const line = document.querySelector('[data-progress-line]');
  if (line) gsap.fromTo(line, { scaleY: 0 }, { scaleY: 1, ease: 'none', scrollTrigger: { trigger: line.parentElement, start: 'top 55%', end: 'bottom 70%', scrub: true } });
  document.querySelectorAll('[data-step-num]').forEach(n => {
    gsap.fromTo(n, { xPercent: -12, opacity: 0.15 }, { xPercent: 0, opacity: 1, ease: 'none', scrollTrigger: { trigger: n.parentElement, start: 'top 90%', end: 'top 45%', scrub: true } });
  });
  window.addEventListener('load', () => ST.refresh());
})();
