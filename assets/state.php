<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA = __DIR__ . '/state.json';
$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(400); echo '{"error":"no body"}'; exit; }
$incoming = json_decode($raw, true);
if (!is_array($incoming)) { http_response_code(400); echo '{"error":"bad json"}'; exit; }

$fp = fopen($DATA, 'c+'); // create if not exists
flock($fp, LOCK_EX);
rewind($fp);
$cur = stream_get_contents($fp);
$state = $cur ? json_decode($cur, true) : [];
if (!is_array($state)) $state = [];

// Preserve voters if client forgot to send it
if (!isset($incoming['voting']['voters']) && isset($state['voting']['voters'])) {
  $incoming['voting']['voters'] = $state['voting']['voters'];
}

$incoming['serverUpdatedAt'] = time();

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($incoming, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
flock($fp, LOCK_UN);
fclose($fp);

echo '{"ok":true}';
