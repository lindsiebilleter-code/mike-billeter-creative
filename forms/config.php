<?php
// Public, non-secret form settings for js/contact.js (reCAPTCHA site key, timing).
declare(strict_types=1);
require __DIR__ . '/_lib.php';

$s = mbf_settings();
$recaptcha = ($s['recaptcha_enabled'] && $s['recaptcha_site_key'] !== '' && $s['recaptcha_secret_key'] !== '')
  ? ['siteKey' => $s['recaptcha_site_key'], 'action' => 'contact'] : null;

mbf_json_out(200, [
  'enabled' => (bool) $s['enabled'] && !mbf_missing($s),
  'minSeconds' => (int) $s['min_seconds'],
  'maxMessageLength' => (int) $s['max_message_length'],
  'recaptcha' => $recaptcha,
]);
