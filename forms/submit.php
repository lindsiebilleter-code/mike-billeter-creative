<?php
// Contact-form endpoint. Accepts a JSON (or form-encoded) POST from js/contact.js,
// checks it for spam, emails it through Resend and keeps a copy for /admin/forms/.
//
// Responses: { success: true } on delivery (and, deliberately, when spam is quietly
// dropped); { success: false, code } otherwise. Codes: disabled, unconfigured,
// invalid, rate_limited, recaptcha, send_failed.
declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); mbf_json_out(405, ['success' => false, 'code' => 'method']); }

// Only accept posts from this site's own pages.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && strcasecmp((string) parse_url($origin, PHP_URL_HOST), preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))) !== 0) {
  mbf_json_out(403, ['success' => false, 'code' => 'origin']);
}

$s = mbf_settings();
if (!$s['enabled']) mbf_json_out(503, ['success' => false, 'code' => 'disabled']);

$raw = file_get_contents('php://input', false, null, 0, 100000) ?: '';
$in = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ? json_decode($raw, true) : $_POST;
if (!is_array($in)) mbf_json_out(400, ['success' => false, 'code' => 'invalid']);
$str = fn($k) => is_scalar($in[$k] ?? null) ? trim(str_replace("\0", '', (string) $in[$k])) : '';

$entry = [
  'id' => bin2hex(random_bytes(8)),
  'created' => gmdate('c'),
  'form' => preg_replace('/[^a-z0-9_-]/i', '', $str('form')) ?: 'contact',
  'page' => mb_substr($str('page'), 0, 300),
  'name' => mb_substr(preg_replace('/[\r\n]+/', ' ', $str('name')), 0, 200),
  'email' => mb_substr($str('email'), 0, 254),
  'timeline' => mb_substr(preg_replace('/[\r\n]+/', ' ', $str('timeline')), 0, 300),
  'message' => mb_substr($str('message'), 0, max(200, (int) $s['max_message_length'])),
  'status' => 'pending',
];
$ip = mbf_client_ip();
if ($s['store_ip']) { $entry['ip'] = $ip; $entry['user_agent'] = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300); }

if ($entry['name'] === '' || $entry['message'] === '' || !mbf_valid_email($entry['email'])) {
  mbf_json_out(422, ['success' => false, 'code' => 'invalid']);
}

$store = function (array $e) use ($s) { if ($s['store_entries']) { mbf_apply_retention($s); mbf_append_entry($e); } };
$spam = function (string $why) use (&$entry, $store) {
  $entry['status'] = 'spam'; $entry['note'] = $why;
  $store($entry);
  mbf_json_out(200, ['success' => true]); // Don't tell bots they were caught.
};

// Honeypot and time-to-submit.
if ($str('company') !== '') $spam('Honeypot field was filled in');
$elapsed = (int) ($in['elapsed'] ?? 0);
if ($s['min_seconds'] > 0 && $elapsed > 0 && $elapsed < $s['min_seconds'] * 1000) $spam('Submitted ' . round($elapsed / 1000, 1) . 's after the page loaded');

// Rate limit per IP.
if (!mbf_rate_ok('ratelimit', $ip, (int) $s['rate_limit_per_hour'])) mbf_json_out(429, ['success' => false, 'code' => 'rate_limited']);

// reCAPTCHA v3.
if ($s['recaptcha_enabled'] && $s['recaptcha_secret_key'] !== '') {
  $r = mbf_recaptcha_verify($s['recaptcha_secret_key'], $str('recaptcha_token'), $ip);
  if ($r['score'] !== null) $entry['recaptcha_score'] = $r['score'];
  $low = !$r['ok'] || ($r['score'] !== null && $r['score'] < (float) $s['recaptcha_threshold']);
  if ($low) {
    $why = $r['ok'] ? 'reCAPTCHA score ' . $r['score'] . ' is below the ' . $s['recaptcha_threshold'] . ' threshold' : $r['error'];
    if ($s['recaptcha_low_score'] === 'reject') {
      $entry['status'] = 'blocked'; $entry['note'] = $why; $store($entry);
      mbf_json_out(403, ['success' => false, 'code' => 'recaptcha']);
    }
    $spam($why);
  }
}

// Blocked words.
foreach (preg_split('/\r?\n/', (string) $s['blocked_words']) ?: [] as $w) {
  $w = trim($w);
  if ($w !== '' && mb_stripos($entry['name'] . ' ' . $entry['email'] . ' ' . $entry['message'], $w) !== false) $spam('Contains blocked word "' . $w . '"');
}

// Deliver.
if (mbf_missing($s)) {
  $entry['status'] = 'not_sent'; $entry['note'] = 'Email delivery not set up: missing ' . implode(', ', mbf_missing($s));
  $store($entry);
  mbf_json_out(503, ['success' => false, 'code' => 'unconfigured']);
}

$res = mbf_resend_send($s['resend_api_key'], mbf_notification($s, $entry), 'entry-' . $entry['id']);
if (!$res['ok']) {
  $entry['status'] = 'failed'; $entry['note'] = $res['error'];
  $store($entry);
  mbf_json_out(502, ['success' => false, 'code' => 'send_failed']);
}
$entry['status'] = 'sent'; $entry['resend_id'] = $res['id'];

if ($s['autoreply_enabled']) {
  $ar = mbf_resend_send($s['resend_api_key'], mbf_autoreply($s, $entry), 'autoreply-' . $entry['id']);
  $entry['autoreply'] = $ar['ok'] ? 'sent' : $ar['error'];
}

$store($entry);
mbf_json_out(200, ['success' => true]);
