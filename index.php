<?php
// Front controller for clean URLs. nginx/Apache serve real files directly and send
// everything else here, so:
//   /                → index.html
//   /services        → services.html  (also /y2k/services → y2k/services.html)
//   /services.html   → 301 to /services   (when the server routes it here)
//   /services/       → 301 to /services
//   /index(.html)    → 301 to /
//   anything else    → 404.html with a real 404 status
// Also works as the router for local development:  php -S localhost:8000 index.php
declare(strict_types=1);

$root = __DIR__;
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
$qs   = (string) parse_url($uri, PHP_URL_QUERY);
$qs   = $qs === '' ? '' : '?' . $qs;

if (PHP_SAPI === 'cli-server') {
  $file = realpath($root . $path);
  if ($file && is_file($file) && strpos($file, $root) === 0 && $file !== __FILE__) {
    if (substr($file, -4) === '.php') { chdir(dirname($file)); $_SERVER['SCRIPT_FILENAME'] = $file; require $file; return true; }
    return false;
  }
  if ($file && $file !== $root && is_dir($file) && substr($path, -1) === '/' && is_file($file . '/index.php')) { $_SERVER['SCRIPT_FILENAME'] = $file . '/index.php'; chdir($file); require $file . '/index.php'; return true; }
  if ($file && is_dir($file) && substr($path, -1) === '/' && is_file($file . '/index.html')) { header('Content-Type: text/html; charset=utf-8'); readfile($file . '/index.html'); return true; }
}

function moved(string $to): void { header('Location: ' . $to, true, 301); exit; }

// Always use HTTPS. Plain-http requests on port 80 are sent to the https address;
// a TLS-terminating proxy in front (X-Forwarded-Proto: https) is left alone.
if (PHP_SAPI !== 'cli-server') {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  if (!$https && (string) ($_SERVER['SERVER_PORT'] ?? '') === '80' && preg_match('/^(www\.)?mikebilleter\.com$/i', (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
    moved('https://mikebilleter.com' . $uri);
  }
  if ($https) header('Strict-Transport-Security: max-age=15768000');
}
function page(string $file, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: text/html; charset=utf-8');
  readfile($file);
  exit;
}

if (strpos($path, "\0") !== false || strpos($path, '..') !== false) page($root . '/404.html', 404);

if ($path === '/' || $path === '/index.php') {
  if ($path === '/index.php') moved('/' . $qs);
  page($root . '/index.html');
}
if (preg_match('#^(/(?:y2k/)?)index(?:\.html)?$#', $path, $m)) moved($m[1] . $qs);
if (preg_match('#^(/[a-z0-9/_-]+)\.html$#i', $path, $m) && is_file($root . $m[0])) moved($m[1] . $qs);
if ($path !== '/' && substr($path, -1) === '/' && !is_dir($root . $path)) moved(rtrim($path, '/') . $qs);
if (preg_match('#^/[a-z0-9_-]+(?:/[a-z0-9_-]+)?$#i', $path) && is_file($root . $path . '.html') && basename($path) !== '404') {
  page($root . $path . '.html');
}
page($root . '/404.html', 404);
