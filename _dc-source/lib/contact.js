import { SITE } from '../site.config.js';
export { SITE };

export const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function validate(f) {
  const e = {};
  if (!f.name || !f.name.trim()) e.name = 'Your name, so I know who I’m writing back to.';
  if (!f.email || !f.email.trim()) e.email = 'An email address, so I can write back.';
  else if (!EMAIL_RE.test(f.email.trim())) e.email = 'That doesn’t look like a complete email address.';
  if (!f.message || !f.message.trim()) e.message = 'A sentence or two about the project is all I need to start.';
  return e;
}

export function subjectFor(f) {
  return `${SITE.subjectPrefix} ${f.name ? 'from ' + f.name.trim() : 'New message'}`;
}

export function mailtoHref(f) {
  const body = [
    f.message ? f.message.trim() : '',
    '',
    f.timeline ? `Timeline: ${f.timeline.trim()}` : '',
    f.name ? `— ${f.name.trim()}` : '',
    f.email ? f.email.trim() : '',
  ].filter((l, i, a) => !(l === '' && a[i - 1] === '')).join('\n');
  return `mailto:${SITE.email}?subject=${encodeURIComponent(subjectFor(f))}&body=${encodeURIComponent(body)}`;
}

// Returns { status: 'sent' | 'unconfigured' }; throws on network / server failure.
export async function sendInquiry(f) {
  const { endpoint, accessKey } = SITE.form;
  if (!endpoint) return { status: 'unconfigured' };
  const payload = {
    name: f.name.trim(),
    email: f.email.trim(),
    message: f.message.trim(),
    timeline: (f.timeline || '').trim(),
    subject: subjectFor(f),
    _subject: subjectFor(f),
    _replyto: f.email.trim(),
    from_name: SITE.name,
  };
  if (accessKey) payload.access_key = accessKey;
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), 15000);
  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: ctrl.signal,
    });
    if (!res.ok) throw new Error(`Delivery failed (${res.status})`);
    const data = await res.json().catch(() => ({}));
    if (data && data.success === false) throw new Error(data.message || 'Delivery rejected');
    return { status: 'sent' };
  } finally {
    clearTimeout(t);
  }
}
