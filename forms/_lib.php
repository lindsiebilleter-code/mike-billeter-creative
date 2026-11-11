<?php
// Shared code for the form backend: storage, settings, Resend delivery, reCAPTCHA.
// Included by forms/submit.php, forms/config.php and admin/forms/index.php.
// Requesting this file directly does nothing.
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

const MBF_GUARD = "<?php http_response_code(404); exit; ?>\n";
const MBF_VERSION = '1.0';

// ---------------------------------------------------------------------------
// Storage. Everything lives in one data folder, outside the web root when the
// host allows it (../mb-forms-data next to public_html), otherwise in
// forms/data/ behind an .htaccess deny. Every data file is a .php file that
// exits before its contents, so even a misconfigured server never serves them.
// ---------------------------------------------------------------------------
function mbf_data_dir(): string {
  static $dir = null;
  if ($dir !== null) return $dir;
  $outside = dirname(__DIR__, 2) . '/mb-forms-data';
  $inside = __DIR__ . '/data';
  foreach ([$outside, $inside] as $d) {
    if (@is_dir($d) && @is_writable($d)) return $dir = $d;
  }
  if (@is_writable(dirname($outside)) && @mkdir($outside, 0700, true)) return $dir = $outside;
  if (!@is_dir($inside)) @mkdir($inside, 0700, true);
  if (!@file_exists($inside . '/.htaccess')) {
    @file_put_contents($inside . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    @file_put_contents($inside . '/index.html', '');
  }
  return $dir = $inside;
}

function mbf_path(string $name): string { return mbf_data_dir() . '/' . $name . '.php'; }

function mbf_read_json(string $name, $default = []) {
  $f = mbf_path($name);
  if (!is_file($f)) return $default;
  $raw = (string) @file_get_contents($f);
  if (strpos($raw, MBF_GUARD) === 0) $raw = substr($raw, strlen(MBF_GUARD));
  $data = json_decode($raw, true);
  return is_array($data) ? $data : $default;
}

function mbf_write_json(string $name, array $data): bool {
  $f = mbf_path($name);
  $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  $ok = @file_put_contents($tmp, MBF_GUARD . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
  if ($ok) { @chmod($tmp, 0600); $ok = @rename($tmp, $f); }
  if (!$ok) @unlink($tmp);
  return $ok;
}

function mbf_storage_ok(): bool {
  $d = mbf_data_dir();
  return is_dir($d) && is_writable($d);
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------
function mbf_default_settings(): array {
  return [
    'enabled' => true,
    'resend_api_key' => '',
    'from_name' => 'Mike Billeter Writing',
    'from_email' => '',
    'to' => 'mike.billeter@gmail.com',
    'cc' => '',
    'bcc' => '',
    'subject' => '[Website Inquiry] from {name}',
    'reply_to_sender' => true,
    'autoreply_enabled' => false,
    'autoreply_subject' => 'Thanks for your note',
    'autoreply_body' => "Hi {name},\n\nThanks for reaching out. Your message came through and I'll get back to you within a day or two.\n\nMike Billeter\nMike Billeter Writing",
    'recaptcha_enabled' => false,
    'recaptcha_site_key' => '',
    'recaptcha_secret_key' => '',
    'recaptcha_threshold' => 0.5,
    'recaptcha_low_score' => 'spam', // 'spam' = keep as spam, don't email; 'reject' = show the visitor an error
    'min_seconds' => 3,
    'rate_limit_per_hour' => 5,
    'max_message_length' => 5000,
    'blocked_words' => '',
    'store_entries' => true,
    'store_ip' => true,
    'retention_days' => 0, // 0 = keep forever
    'timezone' => 'America/Chicago',
  ];
}

function mbf_settings(): array {
  return array_merge(mbf_default_settings(), mbf_read_json('settings', []));
}

function mbf_save_settings(array $s): bool {
  $keep = array_intersect_key($s, mbf_default_settings());
  return mbf_write_json('settings', $keep);
}

function mbf_email_list(string $raw): array {
  $out = [];
  foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $e) {
    $e = trim($e);
    if ($e !== '') $out[] = $e;
  }
  return array_values(array_unique($out));
}

function mbf_valid_email(string $e): bool {
  return strlen($e) <= 254 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/** What is still missing before the form can deliver email. */
function mbf_missing(array $s): array {
  $m = [];
  if ($s['resend_api_key'] === '') $m[] = 'Resend API key';
  if ($s['from_email'] === '') $m[] = 'From address';
  if (!mbf_email_list($s['to'])) $m[] = 'Send-to address';
  return $m;
}

function mbf_fill(string $tpl, array $v): string {
  return strtr($tpl, [
    '{name}' => $v['name'] ?? '',
    '{email}' => $v['email'] ?? '',
    '{timeline}' => $v['timeline'] ?? '',
    '{message}' => $v['message'] ?? '',
    '{form}' => $v['form'] ?? '',
    '{date}' => date('M j, Y'),
  ]);
}

// ---------------------------------------------------------------------------
// Entries: one JSON object per line after the guard line.
// ---------------------------------------------------------------------------
function mbf_entries_file(): string { return mbf_path('entries'); }

function mbf_append_entry(array $entry): bool {
  $f = mbf_entries_file();
  $h = @fopen($f, 'c+');
  if (!$h) return false;
  flock($h, LOCK_EX);
  if ((fstat($h)['size'] ?? 0) === 0) { fwrite($h, MBF_GUARD); @chmod($f, 0600); }
  fseek($h, 0, SEEK_END);
  $ok = fwrite($h, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n") !== false;
  fflush($h); flock($h, LOCK_UN); fclose($h);
  return $ok;
}

/** All entries, newest first. */
function mbf_entries(): array {
  $f = mbf_entries_file();
  if (!is_file($f)) return [];
  $h = @fopen($f, 'r');
  if (!$h) return [];
  flock($h, LOCK_SH);
  $out = [];
  while (($line = fgets($h)) !== false) {
    $line = trim($line);
    if ($line === '' || $line[0] !== '{') continue;
    $e = json_decode($line, true);
    if (is_array($e) && isset($e['id'])) $out[] = $e;
  }
  flock($h, LOCK_UN); fclose($h);
  return array_reverse($out);
}

/** Rewrites the entries file keeping only entries for which $keep returns true. Returns how many were removed. */
function mbf_filter_entries(callable $keep): int {
  $f = mbf_entries_file();
  if (!is_file($f)) return 0;
  $h = @fopen($f, 'c+');
  if (!$h) return 0;
  flock($h, LOCK_EX);
  $kept = []; $removed = 0;
  rewind($h);
  while (($line = fgets($h)) !== false) {
    $t = trim($line);
    if ($t === '' || $t[0] !== '{') continue;
    $e = json_decode($t, true);
    if (is_array($e) && !$keep($e)) { $removed++; continue; }
    $kept[] = $t;
  }
  if ($removed) {
    ftruncate($h, 0); rewind($h);
    fwrite($h, MBF_GUARD . ($kept ? implode("\n", $kept) . "\n" : ''));
    fflush($h);
  }
  flock($h, LOCK_UN); fclose($h);
  return $removed;
}

function mbf_apply_retention(array $s): void {
  $days = (int) $s['retention_days'];
  if ($days <= 0) return;
  $cut = time() - $days * 86400;
  mbf_filter_entries(fn($e) => strtotime($e['created'] ?? 'now') >= $cut);
}

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------
function mbf_http_post(string $url, array $headers, string $body, int $timeout = 15): array {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    return [$code, $res === false ? '' : (string) $res, $err];
  }
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
  $res = @file_get_contents($url, false, $ctx);
  $code = 0;
  $hdrs = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
  foreach ($hdrs as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int) $m[1];
  return [$code, $res === false ? '' : (string) $res, $res === false ? 'Request failed' : ''];
}

// ---------------------------------------------------------------------------
// Resend
// ---------------------------------------------------------------------------
function mbf_from(array $s): string {
  $name = trim(str_replace(['"', "\r", "\n"], '', $s['from_name']));
  return $name !== '' ? '"' . $name . '" <' . $s['from_email'] . '>' : $s['from_email'];
}

/** Returns ['ok' => bool, 'id' => ?string, 'error' => ?string]. */
function mbf_resend_send(string $apiKey, array $msg, ?string $idempotencyKey = null): array {
  $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json', 'User-Agent: mb-forms/' . MBF_VERSION];
  if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
  [$code, $body, $err] = mbf_http_post('https://api.resend.com/emails', $headers, json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  $data = json_decode($body, true);
  if ($code >= 200 && $code < 300 && !empty($data['id'])) return ['ok' => true, 'id' => $data['id'], 'error' => null];
  $why = is_array($data) && !empty($data['message']) ? $data['message'] : ($err ?: 'HTTP ' . $code);
  return ['ok' => false, 'id' => null, 'error' => 'Resend: ' . $why];
}

function mbf_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function mbf_notification(array $s, array $e): array {
  $rows = [['Name', $e['name']], ['Email', $e['email']]];
  if ($e['timeline'] !== '') $rows[] = ['Timeline', $e['timeline']];
  $rows[] = ['Form', $e['form']];
  if ($e['page'] !== '') $rows[] = ['Page', $e['page']];
  if (isset($e['recaptcha_score'])) $rows[] = ['reCAPTCHA score', (string) $e['recaptcha_score']];

  $text = "New message from the website contact form.\n\n";
  foreach ($rows as [$k, $v]) $text .= "$k: $v\n";
  $text .= "\n" . $e['message'] . "\n\n— Reply to this email to answer " . $e['name'] . " directly.\n";

  $html = '<div style="font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#0a0a0a;max-width:600px">'
    . '<p style="margin:0 0 16px">New message from the website contact form.</p><table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 20px">';
  foreach ($rows as [$k, $v]) $html .= '<tr><td style="padding:3px 16px 3px 0;color:#6b6b6b;vertical-align:top">' . mbf_h($k) . '</td><td style="padding:3px 0">' . mbf_h($v) . '</td></tr>';
  $html .= '</table><div style="border-top:1px solid #0a0a0a;padding-top:16px;white-space:pre-wrap">' . mbf_h($e['message']) . '</div>'
    . '<p style="margin:24px 0 0;font-size:13px;color:#6b6b6b">Reply to this email to answer ' . mbf_h($e['name']) . ' directly.</p></div>';

  $msg = [
    'from' => mbf_from($s),
    'to' => mbf_email_list($s['to']),
    'subject' => mb_substr(str_replace(["\r", "\n"], ' ', mbf_fill($s['subject'], $e)), 0, 250),
    'text' => $text,
    'html' => $html,
  ];
  if ($cc = mbf_email_list($s['cc'])) $msg['cc'] = $cc;
  if ($bcc = mbf_email_list($s['bcc'])) $msg['bcc'] = $bcc;
  if ($s['reply_to_sender']) $msg['reply_to'] = $e['email'];
  return $msg;
}

function mbf_autoreply(array $s, array $e): array {
  $body = mbf_fill($s['autoreply_body'], $e);
  $msg = [
    'from' => mbf_from($s),
    'to' => [$e['email']],
    'subject' => mb_substr(str_replace(["\r", "\n"], ' ', mbf_fill($s['autoreply_subject'], $e)), 0, 250),
    'text' => $body,
    'html' => '<div style="font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#0a0a0a;max-width:600px;white-space:pre-wrap">' . mbf_h($body) . '</div>',
  ];
  $replyTo = mbf_email_list($s['to'])[0] ?? '';
  if ($replyTo !== '') $msg['reply_to'] = $replyTo;
  return $msg;
}

// ---------------------------------------------------------------------------
// reCAPTCHA v3
// ---------------------------------------------------------------------------
/** Returns ['ok' => bool, 'score' => ?float, 'error' => ?string]. */
function mbf_recaptcha_verify(string $secret, string $token, string $ip): array {
  if ($token === '') return ['ok' => false, 'score' => null, 'error' => 'No reCAPTCHA token'];
  [$code, $body, $err] = mbf_http_post('https://www.google.com/recaptcha/api/siteverify', ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]), 10);
  $d = json_decode($body, true);
  if (!is_array($d)) return ['ok' => false, 'score' => null, 'error' => 'reCAPTCHA unreachable' . ($err ? ": $err" : '')];
  if (empty($d['success'])) return ['ok' => false, 'score' => null, 'error' => 'reCAPTCHA: ' . implode(', ', $d['error-codes'] ?? ['failed'])];
  return ['ok' => true, 'score' => isset($d['score']) ? (float) $d['score'] : null, 'error' => null, 'action' => $d['action'] ?? ''];
}

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------
function mbf_client_ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }

/** Sliding one-hour window per key. Returns false when over the limit. */
function mbf_rate_ok(string $bucket, string $key, int $limit, int $window = 3600): bool {
  if ($limit <= 0) return true;
  $data = mbf_read_json($bucket, []);
  $now = time(); $id = hash('sha256', $key);
  foreach ($data as $k => $times) {
    $data[$k] = array_values(array_filter((array) $times, fn($t) => $t > $now - $window));
    if (!$data[$k]) unset($data[$k]);
  }
  $hits = $data[$id] ?? [];
  if (count($hits) >= $limit) { mbf_write_json($bucket, $data); return false; }
  $hits[] = $now; $data[$id] = $hits;
  mbf_write_json($bucket, $data);
  return true;
}

function mbf_json_out(int $code, array $body): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Robots-Tag: noindex');
  echo json_encode($body, JSON_UNESCAPED_SLASHES);
  exit;
}
