// Interactive sand field — ported from uploads/interactive-sand-background/SandField.tsx
const TAU = Math.PI * 2;
const TONES = ['rgba(139, 137, 130, 0.22)', 'rgba(157, 154, 145, 0.26)', 'rgba(116, 116, 111, 0.17)', 'rgba(181, 177, 165, 0.30)'];

function seededRandom(seed) {
  let state = seed >>> 0;
  return () => { state = (state * 1664525 + 1013904223) >>> 0; return state / 4294967296; };
}

function mountSand(canvas, surface, opts = {}) {
  const context = canvas.getContext('2d');
  if (!context) return () => {};
  const ground = opts.background || '#fafaf7';
  const pointer = { x: -1000, y: -1000, easedX: -1000, easedY: -1000, vx: 0, vy: 0, previousX: -1000, previousY: -1000, active: false };
  const wake = [];
  let grains = [], width = 0, height = 0, frame = 0, lastWakeAt = 0, visible = true;
  const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  let reduceMotion = motionQuery.matches;

  function buildSand() {
    const bounds = surface.getBoundingClientRect();
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const random = seededRandom(1847);
    width = bounds.width; height = bounds.height;
    canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio);
    canvas.style.width = width + 'px'; canvas.style.height = height + 'px';
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    const count = Math.min(34000, Math.max(10000, Math.floor((width * height) / 31)));
    grains = Array.from({ length: count }, () => {
      const x = random() * width, baseY = random() * height;
      const dune = Math.sin(x * 0.0056 + Math.sin(x * 0.0017) * 1.7) * 7 + Math.sin(x * 0.016 + 1.8) * 2.2;
      return { x, y: (baseY + dune + height) % height, size: 0.34 + Math.pow(random(), 2.2) * 1.12, phase: random() * TAU, tone: Math.floor(random() * TONES.length) };
    }).sort((a, b) => a.tone - b.tone);
  }

  function addWake(x, y, time) {
    const distance = Math.hypot(x - pointer.previousX, y - pointer.previousY);
    if (time - lastWakeAt < 34 && distance < 14) return;
    wake.unshift({ x, y, vx: pointer.vx, vy: pointer.vy, born: performance.now() });
    wake.splice(7); lastWakeAt = time;
  }

  function draw(time = 0) {
    if (!visible || document.hidden) { frame = window.requestAnimationFrame(draw); return; }
    context.clearRect(0, 0, width, height);
    context.fillStyle = ground; context.fillRect(0, 0, width, height);
    if (pointer.active) { pointer.easedX += (pointer.x - pointer.easedX) * 0.16; pointer.easedY += (pointer.y - pointer.easedY) * 0.16; }
    pointer.vx *= 0.9; pointer.vy *= 0.9;
    const now = performance.now();
    for (let i = wake.length - 1; i >= 0; i -= 1) if (now - wake[i].born > 780) wake.splice(i, 1);
    let currentTone = -1;
    for (const grain of grains) {
      if (grain.tone !== currentTone) { currentTone = grain.tone; context.fillStyle = TONES[currentTone]; }
      const ambient = reduceMotion ? 0 : Math.sin(time * 0.0002 + grain.phase);
      let x = grain.x + ambient * 0.7, y = grain.y + Math.cos(time * 0.00016 + grain.phase) * 0.34, compression = 0;
      if (pointer.active) {
        const dx = x - pointer.easedX, dy = y - pointer.easedY, distance = Math.hypot(dx, dy), radius = 172;
        if (distance < radius && distance > 0.2) {
          const influence = Math.pow(1 - distance / radius, 2);
          const speed = Math.min(18, Math.hypot(pointer.vx, pointer.vy));
          const push = influence * (19 + speed * 0.85);
          x += (dx / distance) * push + pointer.vx * influence * 0.2;
          y += (dy / distance) * push + pointer.vy * influence * 0.2;
          compression += influence;
        }
      }
      for (let w = 0; w < wake.length; w += 1) {
        const mark = wake[w], life = Math.max(0, 1 - (now - mark.born) / 780), radius = 124 - w * 7;
        const dx = x - mark.x, dy = y - mark.y, distance = Math.hypot(dx, dy);
        if (distance < radius && distance > 0.2) {
          const influence = Math.pow(1 - distance / radius, 2) * life, push = influence * 13;
          x += (dx / distance) * push + mark.vx * influence * 0.1;
          y += (dy / distance) * push + mark.vy * influence * 0.1;
          compression += influence * 0.42;
        }
      }
      const size = grain.size + Math.min(0.75, compression * 0.36);
      context.fillRect(x, y, size, size);
    }
    if (pointer.active) {
      const g = context.createRadialGradient(pointer.easedX, pointer.easedY, 4, pointer.easedX, pointer.easedY, 118);
      g.addColorStop(0, 'rgba(250, 250, 247, 0.44)'); g.addColorStop(0.42, 'rgba(250, 250, 247, 0.17)'); g.addColorStop(1, 'rgba(250, 250, 247, 0)');
      context.fillStyle = g; context.beginPath(); context.arc(pointer.easedX, pointer.easedY, 118, 0, TAU); context.fill();
    }
    if (!reduceMotion || pointer.active || wake.length > 0) frame = window.requestAnimationFrame(draw);
  }

  function setPointer(event) {
    const bounds = canvas.getBoundingClientRect();
    const nextX = event.clientX - bounds.left, nextY = event.clientY - bounds.top;
    if (!pointer.active) { pointer.easedX = nextX; pointer.easedY = nextY; pointer.previousX = nextX; pointer.previousY = nextY; pointer.active = true; }
    pointer.vx = nextX - pointer.previousX; pointer.vy = nextY - pointer.previousY;
    pointer.x = nextX; pointer.y = nextY;
    addWake(nextX, nextY, event.timeStamp);
    pointer.previousX = nextX; pointer.previousY = nextY;
    if (reduceMotion) { window.cancelAnimationFrame(frame); frame = window.requestAnimationFrame(draw); }
  }
  function clearPointer() { pointer.active = false; pointer.x = -1000; pointer.y = -1000; }
  function handleMotionPreference(e) { reduceMotion = e.matches; window.cancelAnimationFrame(frame); frame = window.requestAnimationFrame(draw); }

  const observer = new ResizeObserver(buildSand); observer.observe(surface);
  const io = new IntersectionObserver(en => { visible = en[0].isIntersecting; }, { threshold: 0 }); io.observe(surface);
  buildSand();
  surface.addEventListener('pointermove', setPointer, { passive: true });
  surface.addEventListener('pointerdown', setPointer, { passive: true });
  surface.addEventListener('pointerleave', clearPointer, { passive: true });
  motionQuery.addEventListener('change', handleMotionPreference);
  frame = window.requestAnimationFrame(draw);

  return () => {
    observer.disconnect(); io.disconnect();
    surface.removeEventListener('pointermove', setPointer);
    surface.removeEventListener('pointerdown', setPointer);
    surface.removeEventListener('pointerleave', clearPointer);
    motionQuery.removeEventListener('change', handleMotionPreference);
    window.cancelAnimationFrame(frame);
  };
}

window.mountSand = mountSand;
