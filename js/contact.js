// Contact form: validation, mailto fallback, delivery adapter, and DOM wiring.
(function () {
  const SITE = window.MBW_SITE;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function validate(f) {
    const e = {};
    if (!f.name || !f.name.trim()) e.name = 'Your name, so I know who I’m writing back to.';
    if (!f.email || !f.email.trim()) e.email = 'An email address, so I can write back.';
    else if (!EMAIL_RE.test(f.email.trim())) e.email = 'That doesn’t look like a complete email address.';
    if (!f.message || !f.message.trim()) e.message = 'A sentence or two about the project is all I need to start.';
    return e;
  }
  function subjectFor(f) { return SITE.subjectPrefix + ' ' + (f.name ? 'from ' + f.name.trim() : 'New message'); }
  function mailtoHref(f) {
    const body = [f.message ? f.message.trim() : '', '', f.timeline ? 'Timeline: ' + f.timeline.trim() : '', f.name ? '— ' + f.name.trim() : '', f.email ? f.email.trim() : '']
      .filter((l, i, a) => !(l === '' && a[i - 1] === '')).join('\n');
    return 'mailto:' + SITE.email + '?subject=' + encodeURIComponent(subjectFor(f)) + '&body=' + encodeURIComponent(body);
  }
  // Public settings from the backend (reCAPTCHA site key, timing). Fetched once; never blocks the form.
  let remote = null;
  function loadRemoteConfig() {
    if (remote) return remote;
    const url = SITE.form.configUrl;
    remote = (!url || location.protocol === 'file:') ? Promise.resolve(null)
      : fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' }).then(r => (r.ok ? r.json() : null)).catch(() => null);
    remote.then(cfg => {
      if (cfg && cfg.minSeconds >= 0) SITE.form.minSeconds = cfg.minSeconds;
      if (cfg && cfg.recaptcha && cfg.recaptcha.siteKey && !window.grecaptcha) {
        const sc = document.createElement('script');
        sc.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(cfg.recaptcha.siteKey);
        sc.async = true; document.head.appendChild(sc);
      }
    });
    return remote;
  }
  async function recaptchaToken() {
    const cfg = await loadRemoteConfig();
    if (!cfg || !cfg.recaptcha) return '';
    for (let i = 0; i < 50 && !(window.grecaptcha && grecaptcha.execute); i++) await new Promise(r => setTimeout(r, 100));
    if (!window.grecaptcha || !grecaptcha.execute) return '';
    return new Promise(resolve => grecaptcha.ready(() => grecaptcha.execute(cfg.recaptcha.siteKey, { action: cfg.recaptcha.action || 'contact' }).then(resolve, () => resolve(''))));
  }

  // Resolves { status: 'sent' | 'unconfigured' }; throws on network / server failure.
  async function sendInquiry(f, meta) {
    meta = meta || {};
    const { endpoint, accessKey } = SITE.form;
    if (!endpoint) return { status: 'unconfigured' };
    const payload = { name: f.name.trim(), email: f.email.trim(), message: f.message.trim(), timeline: (f.timeline || '').trim(), company: f.company || '', subject: subjectFor(f), _subject: subjectFor(f), _replyto: f.email.trim(), from_name: SITE.name,
      form: /\/y2k\//.test(location.pathname) ? 'contact-y2k' : 'contact', page: location.pathname, elapsed: meta.elapsed || 0, recaptcha_token: await recaptchaToken() };
    if (accessKey) payload.access_key = accessKey;
    const ctrl = new AbortController(); const t = setTimeout(() => ctrl.abort(), 20000);
    try {
      const res = await fetch(endpoint, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload), signal: ctrl.signal });
      const data = await res.json().catch(() => ({}));
      if (data && (data.code === 'disabled' || data.code === 'unconfigured')) return { status: 'unconfigured' };
      if (!res.ok) throw new Error('Delivery failed (' + res.status + ')');
      if (data && data.success === false) throw new Error(data.message || data.code || 'Delivery rejected');
      return { status: 'sent' };
    } finally { clearTimeout(t); }
  }

  function wire(form, opts) {
    opts = opts || {};
    const shownAt = Date.now();
    loadRemoteConfig();
    const read = () => { const g = n => (form.elements[n] ? form.elements[n].value : ''); return { name: g('name'), email: g('email'), message: g('message'), timeline: g('timeline'), company: g('company') }; };
    const statuses = form.querySelectorAll('[data-status]');
    const setStatus = (s, fields) => {
      statuses.forEach(el => { el.hidden = el.dataset.status !== s; });
      if (fields) form.querySelectorAll('[data-mailto]').forEach(a => { a.href = mailtoHref(fields); });
    };
    const setErrors = errors => {
      ['name', 'email', 'message'].forEach(k => {
        const input = form.elements[k]; if (!input) return;
        const err = document.getElementById(input.getAttribute('aria-describedby') || '');
        const has = !!errors[k];
        input.setAttribute('aria-invalid', String(has));
        if (err) { err.hidden = !has; err.textContent = has ? errors[k] : ''; }
      });
    };
    const btn = form.querySelector('button[type="submit"]'); const label = btn ? btn.textContent : '';
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const fields = read(); const errors = validate(fields);
      setErrors(errors); setStatus(null);
      if (Object.keys(errors).length) { const first = ['name', 'email', 'message'].find(k => errors[k]); if (form.elements[first]) form.elements[first].focus(); return; }
      if (fields.company) { setStatus('sent', fields); return; } // honeypot (the server also checks it)
      if (Date.now() - shownAt < SITE.form.minSeconds * 1000) { setStatus('tooFast', fields); return; }
      if (btn) { btn.disabled = true; btn.textContent = opts.pendingLabel || 'Sending…'; }
      try {
        const r = await sendInquiry(fields, { elapsed: Date.now() - shownAt });
        setStatus(r.status, fields);
        if (r.status === 'sent') { if (opts.onSent) opts.onSent(); form.reset(); }
      } catch (err) { setStatus('error', fields); }
      finally { if (btn) { btn.disabled = false; btn.textContent = label; } }
    });
  }

  window.MBWContact = { validate, mailtoHref, sendInquiry, wire };
})();
