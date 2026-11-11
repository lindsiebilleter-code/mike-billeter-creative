<?php
// PHP sites serve index.php before index.html. This makes sure the homepage
// is always the real one (and replaces any placeholder index.php the host added).
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
