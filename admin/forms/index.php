<?php
// /admin/forms/ — form delivery settings, spam protection and the entry log.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/forms/_lib.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$nonce = base64_encode(random_bytes(12));
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

session_name('mbforms');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

$self = strtok($_SERVER['REQUEST_URI'] ?? '/admin/forms/', '?');
$auth = mbf_read_json('auth', []);
$hasPassword = !empty($auth['hash']);
$authed = $hasPassword && !empty($_SESSION['ok']) && ($_SESSION['at'] ?? 0) > time() - 4 * 3600 && ($_SESSION['pw'] ?? '') === substr($auth['hash'], -12);
if ($authed) $_SESSION['at'] = time();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function go(string $url, ?string $flash = null, string $kind = 'ok'): void {
  if ($flash !== null) $_SESSION['flash'] = [$kind, $flash];
  header('Location: ' . $url, true, 303); exit;
}
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? '')); }
function h($s): string { return mbf_h((string) $s); }
function tz(array $s): DateTimeZone { try { return new DateTimeZone($s['timezone']); } catch (Exception $e) { return new DateTimeZone('UTC'); } }
function when(string $iso, array $s, string $fmt = 'M j, Y g:i a'): string { try { return (new DateTime($iso))->setTimezone(tz($s))->format($fmt); } catch (Exception $e) { return $iso; } }
function login_session(array $auth): void { session_regenerate_id(true); $_SESSION['ok'] = true; $_SESSION['at'] = time(); $_SESSION['pw'] = substr($auth['hash'], -12); }

$s = mbf_settings();
$action = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (string) ($_POST['action'] ?? '') : '';
$errors = [];
$formValues = null;

// ----- Unauthenticated actions -------------------------------------------
if ($action !== '' && !csrf_ok()) go($self, 'That form expired. Please try again.', 'err');

if ($action === 'setup' && !$hasPassword) {
  $pw = (string) ($_POST['password'] ?? '');
  if (strlen($pw) < 10) $errors['password'] = 'Use at least 10 characters.';
  elseif ($pw !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = 'The two passwords don’t match.';
  elseif (!mbf_storage_ok()) $errors['password'] = 'The server won’t let this page save files. Check folder permissions.';
  else {
    $auth = ['hash' => password_hash($pw, PASSWORD_DEFAULT), 'created' => gmdate('c')];
    if (mbf_write_json('auth', $auth)) { login_session($auth); go($self, 'Password set. You’re signed in.'); }
    $errors['password'] = 'Couldn’t save the password. Check folder permissions.';
  }
}
if ($action === 'login' && $hasPassword) {
  if (!mbf_rate_ok('login-throttle', mbf_client_ip(), 8, 900)) $errors['password'] = 'Too many attempts. Wait 15 minutes and try again.';
  elseif (password_verify((string) ($_POST['password'] ?? ''), $auth['hash'])) {
    if (password_needs_rehash($auth['hash'], PASSWORD_DEFAULT)) { $auth['hash'] = password_hash((string) $_POST['password'], PASSWORD_DEFAULT); mbf_write_json('auth', $auth); }
    login_session($auth); go($self);
  } else { usleep(400000); $errors['password'] = 'That password isn’t right.'; }
}
if ($action === 'logout') { $_SESSION = []; session_destroy(); go($self, null); }

// ----- Authenticated actions ---------------------------------------------
if ($authed && $action === 'save_settings') {
  $p = $_POST; $n = $s;
  foreach (['enabled', 'reply_to_sender', 'autoreply_enabled', 'recaptcha_enabled', 'store_entries', 'store_ip'] as $k) $n[$k] = !empty($p[$k]);
  foreach (['from_name', 'from_email', 'to', 'cc', 'bcc', 'subject', 'autoreply_subject', 'recaptcha_site_key', 'timezone'] as $k) $n[$k] = trim(str_replace(["\r", "\n"], ' ', (string) ($p[$k] ?? '')));
  foreach (['autoreply_body', 'blocked_words'] as $k) $n[$k] = trim(str_replace("\r\n", "\n", (string) ($p[$k] ?? '')));
  foreach (['resend_api_key', 'recaptcha_secret_key'] as $k) {
    $v = trim((string) ($p[$k] ?? ''));
    if (!empty($p[$k . '_clear'])) $n[$k] = '';
    elseif ($v !== '') $n[$k] = $v;
  }
  $n['recaptcha_threshold'] = round(max(0, min(1, (float) ($p['recaptcha_threshold'] ?? 0.5))), 1);
  $n['recaptcha_low_score'] = ($p['recaptcha_low_score'] ?? '') === 'reject' ? 'reject' : 'spam';
  $n['min_seconds'] = max(0, min(60, (int) ($p['min_seconds'] ?? 3)));
  $n['rate_limit_per_hour'] = max(0, min(1000, (int) ($p['rate_limit_per_hour'] ?? 5)));
  $n['max_message_length'] = max(500, min(20000, (int) ($p['max_message_length'] ?? 5000)));
  $n['retention_days'] = max(0, min(3650, (int) ($p['retention_days'] ?? 0)));

  if ($n['from_email'] !== '' && !mbf_valid_email($n['from_email'])) $errors['from_email'] = 'Enter a full email address, like forms@mikebilleter.com.';
  foreach (['to' => 'Send to', 'cc' => 'CC', 'bcc' => 'BCC'] as $k => $label) {
    $bad = array_filter(mbf_email_list($n[$k]), fn($e) => !mbf_valid_email($e));
    if ($bad) $errors[$k] = 'Not a valid address: ' . implode(', ', $bad);
  }
  if ($n['enabled'] && !mbf_email_list($n['to'])) $errors['to'] = 'Add at least one address to send to.';
  if ($n['resend_api_key'] !== '' && strpos($n['resend_api_key'], 're_') !== 0) $errors['resend_api_key'] = 'Resend API keys start with “re_”. Check that you copied the whole key.';
  if ($n['subject'] === '') $errors['subject'] = 'Add a subject line.';
  if ($n['recaptcha_enabled'] && ($n['recaptcha_site_key'] === '' || $n['recaptcha_secret_key'] === '')) $errors['recaptcha_site_key'] = 'reCAPTCHA needs both a site key and a secret key before it can be turned on.';
  if (!in_array($n['timezone'], DateTimeZone::listIdentifiers(), true)) $errors['timezone'] = 'Pick a time zone from the list.';

  if ($errors) { $formValues = $n; $_GET['tab'] = 'settings'; }
  elseif (mbf_save_settings($n)) go($self, 'Settings saved.');
  else { $formValues = $n; $errors['_'] = 'Couldn’t save. The server isn’t letting this page write files.'; }
}

if ($authed && $action === 'test_email') {
  $miss = mbf_missing($s);
  if ($miss) go($self, 'Save these first: ' . implode(', ', $miss) . '.', 'err');
  $sample = ['name' => 'Test Sender', 'email' => mbf_email_list($s['to'])[0], 'timeline' => 'Whenever', 'message' => "This is a test from mikebilleter.com/admin/forms.\n\nIf you're reading it, form delivery works.", 'form' => 'test', 'page' => '/admin/forms/'];
  $msg = mbf_notification($s, $sample);
  $msg['subject'] = '[Test] ' . $msg['subject'];
  $r = mbf_resend_send($s['resend_api_key'], $msg);
  if ($r['ok']) go($self, 'Test email sent to ' . implode(', ', array_merge(mbf_email_list($s['to']), mbf_email_list($s['cc']))) . '. Check the inbox (and spam folder).');
  go($self, 'The test didn’t send. ' . $r['error'], 'err');
}

if ($authed && $action === 'delete_entries') {
  $ids = array_map('strval', (array) ($_POST['ids'] ?? []));
  $n = $ids ? mbf_filter_entries(fn($e) => !in_array($e['id'], $ids, true)) : 0;
  go($self . '?tab=entries', $n . ' ' . ($n === 1 ? 'entry' : 'entries') . ' deleted.');
}
if ($authed && $action === 'delete_spam') {
  $n = mbf_filter_entries(fn($e) => !in_array($e['status'] ?? '', ['spam', 'blocked'], true));
  go($self . '?tab=entries', $n . ' spam ' . ($n === 1 ? 'entry' : 'entries') . ' deleted.');
}

if ($authed && $action === 'change_password') {
  $cur = (string) ($_POST['current'] ?? ''); $pw = (string) ($_POST['password'] ?? '');
  if (!password_verify($cur, $auth['hash'])) $errors['current'] = 'That isn’t the current password.';
  elseif (strlen($pw) < 10) $errors['password'] = 'Use at least 10 characters.';
  elseif ($pw !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = 'The two passwords don’t match.';
  else {
    $auth['hash'] = password_hash($pw, PASSWORD_DEFAULT);
    if (mbf_write_json('auth', $auth)) { login_session($auth); go($self . '?tab=account', 'Password changed. Other signed-in browsers have been signed out.'); }
    $errors['password'] = 'Couldn’t save the new password.';
  }
  $_GET['tab'] = 'account';
}

// ----- Entries: filtering, export ----------------------------------------
$statusLabels = ['sent' => 'Sent', 'failed' => 'Failed', 'not_sent' => 'Not sent', 'spam' => 'Spam', 'blocked' => 'Blocked'];
$filterStatus = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$all = $authed ? mbf_entries() : [];
$filtered = array_values(array_filter($all, function ($e) use ($filterStatus, $q) {
  $st = $e['status'] ?? '';
  if ($filterStatus === 'inbox' && in_array($st, ['spam', 'blocked'], true)) return false;
  if ($filterStatus === 'spam' && !in_array($st, ['spam', 'blocked'], true)) return false;
  if ($filterStatus === 'problems' && !in_array($st, ['failed', 'not_sent'], true)) return false;
  if ($q !== '' && mb_stripos(($e['name'] ?? '') . ' ' . ($e['email'] ?? '') . ' ' . ($e['message'] ?? '') . ' ' . ($e['timeline'] ?? ''), $q) === false) return false;
  return true;
}));

if ($authed && ($_GET['export'] ?? '') === 'csv') {
  $rows = isset($_GET['all']) ? $all : $filtered;
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="form-entries-' . (new DateTime('now', tz($s)))->format('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  $cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v; // keep spreadsheets from running formulas
  fputcsv($out, ['Date', 'Name', 'Email', 'Timeline', 'Message', 'Status', 'Note', 'Form', 'Page', 'reCAPTCHA score', 'Auto-reply', 'IP', 'User agent', 'ID']);
  foreach ($rows as $e) fputcsv($out, array_map($cell, [when($e['created'] ?? '', $s, 'Y-m-d H:i'), $e['name'] ?? '', $e['email'] ?? '', $e['timeline'] ?? '', $e['message'] ?? '', $statusLabels[$e['status'] ?? ''] ?? ($e['status'] ?? ''), $e['note'] ?? '', $e['form'] ?? '', $e['page'] ?? '', $e['recaptcha_score'] ?? '', $e['autoreply'] ?? '', $e['ip'] ?? '', $e['user_agent'] ?? '', $e['id'] ?? '']));
  fclose($out); exit;
}

$tab = $authed ? (in_array($_GET['tab'] ?? '', ['settings', 'entries', 'account'], true) ? $_GET['tab'] : 'settings') : 'login';
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$v = $formValues ?? $s;
$missing = mbf_missing($s);
$spamCount = count(array_filter($all, fn($e) => in_array($e['status'] ?? '', ['spam', 'blocked'], true)));
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
$err = fn($k) => isset($errors[$k]) ? '<p class="field-err" id="err-' . h($k) . '">' . h($errors[$k]) . '</p>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . h($k) . '"' : '';
$chk = fn($k) => !empty($v[$k]) ? ' checked' : '';
$tail = fn($key) => $key === '' ? '' : '…' . substr($key, -4);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Form Settings · Mike Billeter Creative</title>
<link rel="icon" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap">
<style>
:root { --ink: #0a0a0a; --paper: #fff; --muted: #6b6b6b; --line: #d9d9d9; --green: #0b7a3e; --red: #b3261e; --wash: #f5f5f3; }
* { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body { margin: 0; background: var(--paper); color: var(--ink); font: 400 16px/1.5 'Space Grotesk', system-ui, -apple-system, Segoe UI, sans-serif; }
a { color: inherit; text-underline-offset: 3px; }
a:hover { color: var(--green); }
:focus-visible { outline: 2px solid var(--green); outline-offset: 3px; }
.wrap { max-width: 980px; margin: 0 auto; padding: 0 clamp(16px, 3vw, 40px); }
header.top { border-bottom: 1px solid var(--ink); }
header.top .wrap { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px 24px; padding-top: 20px; padding-bottom: 16px; }
.brand { font-weight: 500; letter-spacing: -0.01em; }
.brand span { color: var(--muted); font-weight: 400; }
nav.tabs { display: flex; gap: 4px 20px; flex-wrap: wrap; }
nav.tabs a { text-decoration: none; padding: 4px 0; border-bottom: 2px solid transparent; font-weight: 500; }
nav.tabs a[aria-current="page"] { border-color: var(--green); }
nav.tabs .count { color: var(--muted); font-weight: 400; }
h1 { font-size: clamp(32px, 5vw, 52px); font-weight: 500; letter-spacing: -0.035em; line-height: 1.02; margin: 40px 0 8px; }
.sub { margin: 0 0 32px; color: var(--muted); max-width: 40rem; }
.flash { margin: 24px 0 0; padding: 12px 16px; border-left: 3px solid var(--green); background: var(--wash); }
.flash.err { border-color: var(--red); }
.status { display: flex; flex-wrap: wrap; gap: 8px 28px; padding: 16px 0; border-top: 1px solid var(--ink); border-bottom: 1px solid var(--line); margin-bottom: 8px; font-size: 15px; }
.status b { font-weight: 500; }
.dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 7px; vertical-align: 1px; background: var(--green); }
.dot.off { background: var(--red); }
.dot.idle { background: #bdbdbd; }
fieldset { border: 0; border-top: 1px solid var(--ink); margin: 40px 0 0; padding: 20px 0 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 20px 28px; }
legend { float: left; width: 100%; font-size: 22px; font-weight: 500; letter-spacing: -0.02em; padding: 0; margin: 0 0 4px; }
legend + * { clear: left; }
.lede { grid-column: 1 / -1; margin: -8px 0 0; color: var(--muted); font-size: 15px; max-width: 46rem; }
.full { grid-column: 1 / -1; }
label.f { display: block; font-weight: 500; font-size: 15px; margin-bottom: 4px; }
.hint { display: block; color: var(--muted); font-size: 14px; margin-top: 4px; }
input[type=text], input[type=email], input[type=password], input[type=number], input[type=search], select, textarea {
  width: 100%; font: inherit; color: var(--ink); background: var(--paper); border: 1px solid #bdbdbd; border-radius: 6px; padding: 10px 12px; min-height: 44px; }
textarea { min-height: 120px; resize: vertical; }
input:focus, select:focus, textarea:focus { border-color: var(--ink); outline: 2px solid var(--green); outline-offset: 1px; }
[aria-invalid="true"] { border-color: var(--red); }
.field-err { color: var(--red); font-size: 14px; margin: 6px 0 0; }
.toggle { display: flex; gap: 10px; align-items: flex-start; font-weight: 500; font-size: 15px; cursor: pointer; }
.toggle input { width: 18px; height: 18px; margin: 2px 0 0; accent-color: var(--green); flex: none; }
.toggle small { display: block; font-weight: 400; color: var(--muted); font-size: 14px; }
.range { display: flex; align-items: center; gap: 14px; }
.range input { flex: 1; accent-color: var(--green); }
.range output { font-variant-numeric: tabular-nums; font-weight: 500; min-width: 2.5ch; }
.scale { display: flex; justify-content: space-between; color: var(--muted); font-size: 13px; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font: 500 16px/1 'Space Grotesk', system-ui, sans-serif; padding: 12px 22px; min-height: 46px; border-radius: 999px; border: 1px solid var(--ink); background: var(--paper); color: var(--ink); cursor: pointer; text-decoration: none; }
.btn:hover { background: var(--ink); color: var(--paper); }
.btn.primary { background: var(--ink); color: var(--paper); }
.btn.primary:hover { background: var(--green); border-color: var(--green); }
.btn.danger { border-color: var(--red); color: var(--red); }
.btn.danger:hover { background: var(--red); color: var(--paper); }
.btn.small { font-size: 14px; padding: 8px 14px; min-height: 36px; }
.actions { position: sticky; bottom: 0; background: rgba(255,255,255,.96); border-top: 1px solid var(--ink); margin-top: 40px; padding: 14px 0; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.actions .note { color: var(--muted); font-size: 14px; }
code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .9em; background: var(--wash); padding: 1px 5px; border-radius: 4px; }
.card { max-width: 420px; margin: 12vh auto 0; padding: 0 16px; }
.card form { display: grid; gap: 16px; }
.filters { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; border-top: 1px solid var(--ink); padding: 16px 0; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chips a { text-decoration: none; border: 1px solid #bdbdbd; border-radius: 999px; padding: 5px 12px; font-size: 14px; }
.chips a[aria-current="page"] { background: var(--ink); color: var(--paper); border-color: var(--ink); }
.search { display: flex; gap: 8px; flex: 1 1 260px; max-width: 420px; }
.table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
table { width: 100%; border-collapse: collapse; font-size: 15px; }
th, td { text-align: left; padding: 12px 10px 12px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
th { font-weight: 500; font-size: 13px; color: var(--muted); }
td.when { white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--muted); }
td.msg { max-width: 34ch; color: #333; }
td.msg a { text-decoration: none; display: block; }
.who b { font-weight: 500; display: block; }
.who span { color: var(--muted); font-size: 14px; overflow-wrap: anywhere; }
.pill { display: inline-block; font-size: 13px; padding: 2px 9px; border-radius: 999px; background: var(--wash); white-space: nowrap; }
.pill.sent { background: #e3f2e9; color: var(--green); }
.pill.failed, .pill.not_sent { background: #fbe9e7; color: var(--red); }
.pill.spam, .pill.blocked { background: #eee; color: var(--muted); }
.bulk { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 14px 0; }
.empty { padding: 48px 0; color: var(--muted); }
.pager { display: flex; gap: 16px; padding: 16px 0; }
dl.detail { display: grid; grid-template-columns: minmax(110px, max-content) 1fr; gap: 10px 24px; margin: 0; padding: 20px 0; border-top: 1px solid var(--ink); }
dl.detail dt { color: var(--muted); font-size: 15px; }
dl.detail dd { margin: 0; overflow-wrap: anywhere; }
.message-body { white-space: pre-wrap; border-top: 1px solid var(--line); padding: 20px 0; font-size: 18px; line-height: 1.55; max-width: 44rem; }
.split { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 40px; }
.foot { color: var(--muted); font-size: 13px; padding: 40px 0; }
.sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
</head>
<body>
<?php if ($tab === 'login'): ?>
<main class="card">
  <p class="brand">Mike Billeter Creative <span>· Forms</span></p>
  <?php if (!$hasPassword): ?>
    <h1>Set a password</h1>
    <p class="sub">This is the first visit to the form settings. Choose the password you’ll use to sign in here from now on.</p>
    <?php if (!mbf_storage_ok()): ?><p class="flash err">This server isn’t letting the page save files, so nothing can be stored yet. Make sure PHP can write to the folder above the website, or to <code>/forms/</code>.</p><?php endif; ?>
    <form method="post" action="<?= h($self) ?>"><?= $csrf ?><input type="hidden" name="action" value="setup">
      <div><label class="f" for="pw">New password</label><input id="pw" type="password" name="password" autocomplete="new-password" minlength="10" required<?= $inv('password') ?>><span class="hint">At least 10 characters.</span><?= $err('password') ?></div>
      <div><label class="f" for="pw2">Type it again</label><input id="pw2" type="password" name="confirm" autocomplete="new-password" required<?= $inv('confirm') ?>><?= $err('confirm') ?></div>
      <div><button class="btn primary" type="submit">Set password and sign in</button></div>
    </form>
  <?php else: ?>
    <h1>Sign in</h1>
    <?php if ($flash): ?><p class="flash <?= $flash[0] === 'err' ? 'err' : '' ?>"><?= h($flash[1]) ?></p><?php endif; ?>
    <form method="post" action="<?= h($self) ?>"><?= $csrf ?><input type="hidden" name="action" value="login">
      <div><label class="f" for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" required autofocus<?= $inv('password') ?>><?= $err('password') ?></div>
      <div><button class="btn primary" type="submit">Sign in</button></div>
    </form>
  <?php endif; ?>
</main>
<?php else: ?>
<header class="top"><div class="wrap">
  <span class="brand">Mike Billeter Creative <span>· Forms</span></span>
  <nav class="tabs" aria-label="Sections">
    <a href="?tab=settings"<?= $tab === 'settings' ? ' aria-current="page"' : '' ?>>Settings</a>
    <a href="?tab=entries&amp;status=inbox"<?= $tab === 'entries' ? ' aria-current="page"' : '' ?>>Entries <span class="count"><?= count($all) - $spamCount ?></span></a>
    <a href="?tab=account"<?= $tab === 'account' ? ' aria-current="page"' : '' ?>>Account</a>
  </nav>
</div></header>
<main class="wrap">
<?php if ($flash): ?><p class="flash <?= $flash[0] === 'err' ? 'err' : '' ?>" role="status"><?= h($flash[1]) ?></p><?php endif; ?>
<?php if (isset($errors['_'])): ?><p class="flash err"><?= h($errors['_']) ?></p><?php endif; ?>

<?php if ($tab === 'settings'): ?>
  <h1>Form settings</h1>
  <p class="sub">Where contact-form messages go, what they look like, and how spam is kept out. Applies to both the modern and 1999 contact pages.</p>
  <div class="status" aria-label="Current status">
    <span><span class="dot <?= !$s['enabled'] ? 'idle' : ($missing ? 'off' : '') ?>"></span><b>Delivery:</b> <?= !$s['enabled'] ? 'Paused' : ($missing ? 'Not set up — needs ' . h(implode(', ', $missing)) : 'Ready') ?></span>
    <span><span class="dot <?= $s['recaptcha_enabled'] ? '' : 'idle' ?>"></span><b>reCAPTCHA:</b> <?= $s['recaptcha_enabled'] ? 'On, threshold ' . h(number_format((float) $s['recaptcha_threshold'], 1)) : 'Off' ?></span>
    <span><span class="dot <?= $s['store_entries'] ? '' : 'idle' ?>"></span><b>Entries:</b> <?= $s['store_entries'] ? count($all) . ' saved' : 'Not saved' ?></span>
  </div>
  <?php if ($errors && !isset($errors['_'])): ?><p class="flash err" role="alert">Nothing was saved. Fix the <?= count($errors) === 1 ? 'field' : count($errors) . ' fields' ?> marked below.</p><?php endif; ?>

  <form method="post" action="<?= h($self) ?>?tab=settings" novalidate><?= $csrf ?><input type="hidden" name="action" value="save_settings">

  <fieldset>
    <legend>Sending with Resend</legend>
    <p class="lede">Create a key at resend.com → API Keys with <b>Sending access</b>, limited to your domain. The from address must be on a domain you’ve verified in Resend → Domains.</p>
    <div class="full">
      <label class="f" for="resend_api_key">Resend API key</label>
      <input id="resend_api_key" type="password" name="resend_api_key" autocomplete="off" spellcheck="false" placeholder="<?= $s['resend_api_key'] !== '' ? 'Saved key ' . h($tail($s['resend_api_key'])) . ' — leave blank to keep it' : 're_…' ?>"<?= $inv('resend_api_key') ?>>
      <span class="hint">Stored on the server only; it’s never shown again or sent to visitors’ browsers.</span><?= $err('resend_api_key') ?>
      <?php if ($s['resend_api_key'] !== ''): ?><label class="toggle" style="margin-top:8px"><input type="checkbox" name="resend_api_key_clear" value="1"> Remove the saved key</label><?php endif; ?>
    </div>
    <div><label class="f" for="from_name">From name</label><input id="from_name" type="text" name="from_name" value="<?= h($v['from_name']) ?>"><span class="hint">Shown as the sender in the inbox.</span></div>
    <div><label class="f" for="from_email">From address</label><input id="from_email" type="email" name="from_email" value="<?= h($v['from_email']) ?>" placeholder="forms@mikebilleter.com" spellcheck="false"<?= $inv('from_email') ?>><span class="hint">Must be on your verified domain.</span><?= $err('from_email') ?></div>
    <label class="toggle full"><input type="checkbox" name="enabled" value="1"<?= $chk('enabled') ?>><span>Accept form submissions<small>Turn off to pause the form. Visitors get the pre-filled “send by email instead” link.</small></span></label>
  </fieldset>

  <fieldset>
    <legend>Recipients</legend>
    <p class="lede">Separate multiple addresses with commas.</p>
    <div class="full"><label class="f" for="to">Send to</label><input id="to" type="text" name="to" value="<?= h($v['to']) ?>" spellcheck="false" inputmode="email"<?= $inv('to') ?>><?= $err('to') ?></div>
    <div><label class="f" for="cc">CC</label><input id="cc" type="text" name="cc" value="<?= h($v['cc']) ?>" spellcheck="false" inputmode="email"<?= $inv('cc') ?>><?= $err('cc') ?></div>
    <div><label class="f" for="bcc">BCC</label><input id="bcc" type="text" name="bcc" value="<?= h($v['bcc']) ?>" spellcheck="false" inputmode="email"<?= $inv('bcc') ?>><?= $err('bcc') ?></div>
    <label class="toggle full"><input type="checkbox" name="reply_to_sender" value="1"<?= $chk('reply_to_sender') ?>><span>Reply goes to the visitor<small>Hitting Reply answers the person who filled out the form, not the from address.</small></span></label>
  </fieldset>

  <fieldset>
    <legend>The email</legend>
    <div class="full"><label class="f" for="subject">Subject line</label><input id="subject" type="text" name="subject" value="<?= h($v['subject']) ?>"<?= $inv('subject') ?>><span class="hint">Placeholders: <code>{name}</code> <code>{email}</code> <code>{timeline}</code> <code>{date}</code></span><?= $err('subject') ?></div>
  </fieldset>

  <fieldset>
    <legend>Auto-reply to the visitor</legend>
    <p class="lede">An optional confirmation sent to the person who filled out the form. Replies to it go to the first “Send to” address.</p>
    <label class="toggle full"><input type="checkbox" name="autoreply_enabled" value="1"<?= $chk('autoreply_enabled') ?>><span>Send an auto-reply</span></label>
    <div class="full"><label class="f" for="autoreply_subject">Auto-reply subject</label><input id="autoreply_subject" type="text" name="autoreply_subject" value="<?= h($v['autoreply_subject']) ?>"></div>
    <div class="full"><label class="f" for="autoreply_body">Auto-reply message</label><textarea id="autoreply_body" name="autoreply_body" rows="7"><?= h($v['autoreply_body']) ?></textarea><span class="hint">Plain text. Same placeholders as the subject, plus <code>{message}</code>.</span></div>
  </fieldset>

  <fieldset>
    <legend>reCAPTCHA v3</legend>
    <p class="lede">Invisible spam scoring from Google. Register the site at google.com/recaptcha/admin, choose <b>Score based (v3)</b>, add <code>mikebilleter.com</code> as a domain, then paste both keys here.</p>
    <label class="toggle full"><input type="checkbox" name="recaptcha_enabled" value="1"<?= $chk('recaptcha_enabled') ?>><span>Use reCAPTCHA on the contact forms</span></label>
    <div><label class="f" for="recaptcha_site_key">Site key</label><input id="recaptcha_site_key" type="text" name="recaptcha_site_key" value="<?= h($v['recaptcha_site_key']) ?>" spellcheck="false" autocomplete="off"<?= $inv('recaptcha_site_key') ?>><span class="hint">Public. Loaded on the contact page.</span><?= $err('recaptcha_site_key') ?></div>
    <div>
      <label class="f" for="recaptcha_secret_key">Secret key</label>
      <input id="recaptcha_secret_key" type="password" name="recaptcha_secret_key" autocomplete="off" spellcheck="false" placeholder="<?= $s['recaptcha_secret_key'] !== '' ? 'Saved ' . h($tail($s['recaptcha_secret_key'])) . ' — leave blank to keep it' : '' ?>">
      <span class="hint">Private. Stays on the server.</span>
      <?php if ($s['recaptcha_secret_key'] !== ''): ?><label class="toggle" style="margin-top:8px"><input type="checkbox" name="recaptcha_secret_key_clear" value="1"> Remove the saved secret</label><?php endif; ?>
    </div>
    <div>
      <label class="f" for="recaptcha_threshold">Score threshold</label>
      <div class="range"><input id="recaptcha_threshold" type="range" name="recaptcha_threshold" min="0" max="1" step="0.1" value="<?= h(number_format((float) $v['recaptcha_threshold'], 1)) ?>"><output for="recaptcha_threshold" id="thr-out"><?= h(number_format((float) $v['recaptcha_threshold'], 1)) ?></output></div>
      <div class="scale" aria-hidden="true"><span>0.0 lets everything in</span><span>1.0 strictest</span></div>
      <span class="hint">Google scores each visitor from 0.0 (bot) to 1.0 (human). Below this score counts as spam. 0.5 is Google’s suggested start.</span>
    </div>
    <div>
      <label class="f" for="recaptcha_low_score">When a score is too low</label>
      <select id="recaptcha_low_score" name="recaptcha_low_score">
        <option value="spam"<?= $v['recaptcha_low_score'] === 'spam' ? ' selected' : '' ?>>Save it as spam, don’t email it</option>
        <option value="reject"<?= $v['recaptcha_low_score'] === 'reject' ? ' selected' : '' ?>>Show the visitor an error with the email link</option>
      </select>
      <span class="hint">Either way it’s kept under Entries → Spam, so nothing real is lost.</span>
    </div>
  </fieldset>

  <fieldset>
    <legend>Other spam protection</legend>
    <div><label class="f" for="min_seconds">Minimum seconds on the page</label><input id="min_seconds" type="number" name="min_seconds" min="0" max="60" value="<?= h($v['min_seconds']) ?>"><span class="hint">Faster submissions are treated as bots. 0 turns this off.</span></div>
    <div><label class="f" for="rate_limit_per_hour">Messages per hour from one visitor</label><input id="rate_limit_per_hour" type="number" name="rate_limit_per_hour" min="0" max="1000" value="<?= h($v['rate_limit_per_hour']) ?>"><span class="hint">0 means no limit.</span></div>
    <div><label class="f" for="max_message_length">Longest message allowed (characters)</label><input id="max_message_length" type="number" name="max_message_length" min="500" max="20000" step="500" value="<?= h($v['max_message_length']) ?>"></div>
    <div class="full"><label class="f" for="blocked_words">Blocked words and addresses</label><textarea id="blocked_words" name="blocked_words" rows="4" placeholder="One per line, e.g. SEO services" spellcheck="false"><?= h($v['blocked_words']) ?></textarea><span class="hint">A message whose name, email or text contains any of these is saved as spam and not emailed. Not case-sensitive.</span></div>
    <p class="lede full">A hidden “honeypot” field is always on: bots that fill it in are quietly filed as spam.</p>
  </fieldset>

  <fieldset>
    <legend>Saved entries</legend>
    <label class="toggle full"><input type="checkbox" name="store_entries" value="1"<?= $chk('store_entries') ?>><span>Keep a copy of every submission here<small>A backup if an email ever goes missing, and where spam lands.</small></span></label>
    <label class="toggle full"><input type="checkbox" name="store_ip" value="1"<?= $chk('store_ip') ?>><span>Record the visitor’s IP address and browser<small>Useful for spotting spam patterns.</small></span></label>
    <div><label class="f" for="retention_days">Delete entries after (days)</label><input id="retention_days" type="number" name="retention_days" min="0" max="3650" value="<?= h($v['retention_days']) ?>"><span class="hint">0 keeps them forever.</span></div>
    <div><label class="f" for="timezone">Time zone for dates</label><select id="timezone" name="timezone"<?= $inv('timezone') ?>><?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::AMERICA | DateTimeZone::UTC) as $z): ?><option<?= $v['timezone'] === $z ? ' selected' : '' ?>><?= h($z) ?></option><?php endforeach; ?></select><?= $err('timezone') ?></div>
  </fieldset>

  <div class="actions">
    <button class="btn primary" type="submit">Save settings</button>
    <button class="btn" type="submit" form="test-form"<?= $missing ? ' disabled title="Save an API key, from address and send-to address first"' : '' ?>>Send a test email</button>
    <span class="note">The test uses the saved settings. Save first if you changed anything.</span>
  </div>
  </form>
  <form id="test-form" method="post" action="<?= h($self) ?>"><?= $csrf ?><input type="hidden" name="action" value="test_email"></form>

<?php elseif ($tab === 'entries'):
  $id = (string) ($_GET['id'] ?? '');
  $one = null;
  if ($id !== '') foreach ($all as $e) if ($e['id'] === $id) { $one = $e; break; }
  if ($one):
    $st = $one['status'] ?? ''; ?>
  <p style="margin-top:32px"><a href="?tab=entries&amp;status=<?= in_array($st, ['spam', 'blocked'], true) ? 'spam' : 'inbox' ?>">← All entries</a></p>
  <h1><?= h($one['name'] ?? '') ?></h1>
  <p class="sub"><span class="pill <?= h($st) ?>"><?= h($statusLabels[$st] ?? $st) ?></span> &nbsp;<?= h(when($one['created'] ?? '', $s, 'l, F j, Y \a\t g:i a')) ?></p>
  <dl class="detail">
    <dt>Email</dt><dd><a href="mailto:<?= h($one['email'] ?? '') ?>?subject=<?= rawurlencode('Re: your note') ?>"><?= h($one['email'] ?? '') ?></a></dd>
    <?php if (($one['timeline'] ?? '') !== ''): ?><dt>Timeline</dt><dd><?= h($one['timeline']) ?></dd><?php endif; ?>
    <?php if (!empty($one['note'])): ?><dt>Note</dt><dd><?= h($one['note']) ?></dd><?php endif; ?>
    <dt>Form</dt><dd><?= h($one['form'] ?? '') ?><?= !empty($one['page']) ? ' · ' . h($one['page']) : '' ?></dd>
    <?php if (isset($one['recaptcha_score'])): ?><dt>reCAPTCHA</dt><dd><?= h($one['recaptcha_score']) ?></dd><?php endif; ?>
    <?php if (!empty($one['autoreply'])): ?><dt>Auto-reply</dt><dd><?= h($one['autoreply']) ?></dd><?php endif; ?>
    <?php if (!empty($one['ip'])): ?><dt>IP</dt><dd><?= h($one['ip']) ?></dd><?php endif; ?>
    <?php if (!empty($one['user_agent'])): ?><dt>Browser</dt><dd><?= h($one['user_agent']) ?></dd><?php endif; ?>
  </dl>
  <div class="message-body"><?= h($one['message'] ?? '') ?></div>
  <div class="actions">
    <a class="btn primary" href="mailto:<?= h($one['email'] ?? '') ?>?subject=<?= rawurlencode('Re: your note') ?>">Reply by email</a>
    <form method="post" action="<?= h($self) ?>" data-confirm="Delete this entry? This can’t be undone."><?= $csrf ?><input type="hidden" name="action" value="delete_entries"><input type="hidden" name="ids[]" value="<?= h($one['id']) ?>"><button class="btn danger" type="submit">Delete</button></form>
  </div>
  <?php else:
    $per = 50; $pages = max(1, (int) ceil(count($filtered) / $per)); $page = max(1, min($pages, (int) ($_GET['p'] ?? 1)));
    $rows = array_slice($filtered, ($page - 1) * $per, $per);
    $qs = fn(array $o) => '?' . http_build_query(array_merge(['tab' => 'entries', 'status' => $filterStatus, 'q' => $q], $o));
    $problems = count(array_filter($all, fn($e) => in_array($e['status'] ?? '', ['failed', 'not_sent'], true))); ?>
  <h1>Entries</h1>
  <p class="sub">Every contact-form submission<?= $s['store_entries'] ? '' : ' (saving is currently turned off in Settings)' ?>. Dates are in <?= h($s['timezone']) ?>.</p>
  <div class="filters">
    <nav class="chips" aria-label="Filter">
      <a href="<?= h($qs(['status' => 'inbox', 'p' => 1])) ?>"<?= $filterStatus === 'inbox' ? ' aria-current="page"' : '' ?>>Inbox <?= count($all) - $spamCount ?></a>
      <a href="<?= h($qs(['status' => 'spam', 'p' => 1])) ?>"<?= $filterStatus === 'spam' ? ' aria-current="page"' : '' ?>>Spam <?= $spamCount ?></a>
      <?php if ($problems): ?><a href="<?= h($qs(['status' => 'problems', 'p' => 1])) ?>"<?= $filterStatus === 'problems' ? ' aria-current="page"' : '' ?>>Didn’t send <?= $problems ?></a><?php endif; ?>
      <a href="<?= h($qs(['status' => '', 'p' => 1])) ?>"<?= $filterStatus === '' ? ' aria-current="page"' : '' ?>>All <?= count($all) ?></a>
    </nav>
    <form class="search" method="get" role="search"><input type="hidden" name="tab" value="entries"><input type="hidden" name="status" value="<?= h($filterStatus) ?>"><label class="sr" for="q">Search entries</label><input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, email, message"><button class="btn small" type="submit">Search</button></form>
  </div>
  <div class="bulk">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn small" href="<?= h($qs(['export' => 'csv'])) ?>">Download these (CSV)</a>
      <a class="btn small" href="?tab=entries&amp;export=csv&amp;all=1">Download all (CSV)</a>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn small danger" type="submit" form="bulk" data-needs-selection disabled>Delete selected</button>
      <?php if ($filterStatus === 'spam' && $spamCount): ?><form method="post" action="<?= h($self) ?>" data-confirm="Delete all <?= $spamCount ?> spam entries?"><?= $csrf ?><input type="hidden" name="action" value="delete_spam"><button class="btn small danger" type="submit">Empty spam</button></form><?php endif; ?>
    </div>
  </div>
  <?php if (!$rows): ?>
    <p class="empty"><?= $q !== '' ? 'Nothing matches “' . h($q) . '”.' : ($all ? 'Nothing here.' : 'No submissions yet. They’ll appear here as soon as someone uses the contact form.') ?></p>
  <?php else: ?>
  <form id="bulk" method="post" action="<?= h($self) ?>" data-confirm="Delete the selected entries? This can’t be undone."><?= $csrf ?><input type="hidden" name="action" value="delete_entries">
  <div class="table-wrap"><table>
    <thead><tr><th scope="col"><label class="sr" for="all">Select all</label><input id="all" type="checkbox" data-select-all></th><th scope="col">Received</th><th scope="col">From</th><th scope="col">Message</th><th scope="col">Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $e): $st = $e['status'] ?? ''; $link = '?tab=entries&id=' . rawurlencode($e['id']); ?>
      <tr>
        <td><label class="sr" for="c<?= h($e['id']) ?>">Select</label><input id="c<?= h($e['id']) ?>" type="checkbox" name="ids[]" value="<?= h($e['id']) ?>"></td>
        <td class="when"><?= h(when($e['created'] ?? '', $s, 'M j, Y')) ?><br><?= h(when($e['created'] ?? '', $s, 'g:i a')) ?></td>
        <td class="who"><b><a href="<?= h($link) ?>"><?= h($e['name'] ?? '') ?></a></b><span><?= h($e['email'] ?? '') ?></span></td>
        <td class="msg"><a href="<?= h($link) ?>"><?= h(mb_strimwidth(preg_replace('/\s+/', ' ', $e['message'] ?? ''), 0, 120, '…')) ?></a></td>
        <td><span class="pill <?= h($st) ?>"<?= !empty($e['note']) ? ' title="' . h($e['note']) . '"' : '' ?>><?= h($statusLabels[$st] ?? $st) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  </form>
  <?php if ($pages > 1): ?><nav class="pager" aria-label="Pages"><?php if ($page > 1): ?><a href="<?= h($qs(['p' => $page - 1])) ?>">← Newer</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="<?= h($qs(['p' => $page + 1])) ?>">Older →</a><?php endif; ?></nav><?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>

<?php else: /* account */ ?>
  <h1>Account</h1>
  <p class="sub">One password protects this page. There’s no reset email: if it’s lost, delete <code>auth.php</code> from the forms data folder on the server and the next visit will ask for a new one.</p>
  <div class="split">
    <form method="post" action="<?= h($self) ?>" style="display:grid;gap:16px;align-content:start"><?= $csrf ?><input type="hidden" name="action" value="change_password">
      <h2 style="font-weight:500;font-size:22px;margin:0;letter-spacing:-0.02em">Change password</h2>
      <div><label class="f" for="cur">Current password</label><input id="cur" type="password" name="current" autocomplete="current-password" required<?= $inv('current') ?>><?= $err('current') ?></div>
      <div><label class="f" for="npw">New password</label><input id="npw" type="password" name="password" autocomplete="new-password" minlength="10" required<?= $inv('password') ?>><span class="hint">At least 10 characters.</span><?= $err('password') ?></div>
      <div><label class="f" for="npw2">New password again</label><input id="npw2" type="password" name="confirm" autocomplete="new-password" required<?= $inv('confirm') ?>><?= $err('confirm') ?></div>
      <div><button class="btn primary" type="submit">Change password</button></div>
    </form>
    <div style="display:grid;gap:16px;align-content:start">
      <h2 style="font-weight:500;font-size:22px;margin:0;letter-spacing:-0.02em">Sign out</h2>
      <p style="margin:0;color:var(--muted)">You’re signed out automatically after 4 hours without activity.</p>
      <form method="post" action="<?= h($self) ?>"><?= $csrf ?><input type="hidden" name="action" value="logout"><button class="btn" type="submit">Sign out</button></form>
      <h2 style="font-weight:500;font-size:22px;margin:16px 0 0;letter-spacing:-0.02em">Server</h2>
      <p style="margin:0;color:var(--muted);font-size:15px">Data is stored <?= strpos(mbf_data_dir(), dirname(__DIR__, 2)) === 0 ? 'inside the website folder (protected)' : 'outside the public website folder' ?>. PHP <?= h(PHP_VERSION) ?><?= function_exists('curl_init') ? '' : ', no cURL' ?>.</p>
    </div>
  </div>
<?php endif; ?>
</main>
<footer class="wrap foot">Form backend v<?= MBF_VERSION ?> · <a href="/">View the site</a></footer>
<?php endif; ?>
<script nonce="<?= h($nonce) ?>">
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
const thr = document.getElementById('recaptcha_threshold'), out = document.getElementById('thr-out');
if (thr && out) thr.addEventListener('input', () => { out.textContent = Number(thr.value).toFixed(1); });
const all = document.querySelector('[data-select-all]'), bulk = document.getElementById('bulk'), del = document.querySelector('[data-needs-selection]');
if (bulk) {
  const boxes = () => [...bulk.querySelectorAll('input[name="ids[]"]')];
  const sync = () => { const n = boxes().filter(b => b.checked).length; if (del) { del.disabled = !n; del.textContent = n ? 'Delete selected (' + n + ')' : 'Delete selected'; } if (all) { all.checked = n && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; } };
  if (all) all.addEventListener('change', () => { boxes().forEach(b => { b.checked = all.checked; }); sync(); });
  bulk.addEventListener('change', sync);
}
</script>
</body>
</html>
