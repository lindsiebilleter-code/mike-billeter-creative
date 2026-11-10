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
  // Resolves { status: 'sent' | 'unconfigured' }; throws on network / server failure.
  async function sendInquiry(f) {
    const { endpoint, accessKey } = SITE.form;
    if (!endpoint) return { status: 'unconfigured' };
    const payload = { name: f.name.trim(), email: f.email.trim(), message: f.message.trim(), timeline: (f.timeline || '').trim(), subject: subjectFor(f), _subject: subjectFor(f), _replyto: f.email.trim(), from_name: SITE.name };
    if (accessKey) payload.access_key = accessKey;
    const ctrl = new AbortController(); const t = setTimeout(() => ctrl.abort(), 15000);
    try {
      const res = await fetch(endpoint, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload), signal: ctrl.signal });
      if (!res.ok) throw new Error('Delivery failed (' + res.status + ')');
      const data = await res.json().catch(() => ({}));
      if (data && data.success === false) throw new Error(data.message || 'Delivery rejected');
      return { status: 'sent' };
    } finally { clearTimeout(t); }
  }

  function wire(form, opts) {
    opts = opts || {};
    const shownAt = Date.now();
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
      if (fields.company) { setStatus('sent', fields); return; } // honeypot
      if (Date.now() - shownAt < SITE.form.minSeconds * 1000) { setStatus('tooFast', fields); return; }
      if (btn) { btn.disabled = true; btn.textContent = opts.pendingLabel || 'Sending…'; }
      try {
        const r = await sendInquiry(fields);
        setStatus(r.status, fields);
        if (r.status === 'sent') { if (opts.onSent) opts.onSent(); form.reset(); }
      } catch (err) { setStatus('error', fields); }
      finally { if (btn) { btn.disabled = false; btn.textContent = label; } }
    });
  }

  window.MBWContact = { validate, mailtoHref, sendInquiry, wire };
})();
