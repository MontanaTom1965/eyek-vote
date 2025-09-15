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

// Preserve voters if client omitted
if (isset($state['voting']['voters']) && !isset($incoming['voting']['voters'])) {
  $incoming['voting']['voters'] = $state['voting']['voters'];
}

// Ensure saved lists exist
if (!isset($incoming['saved'])) $incoming['saved'] = ["venues"=>[], "kjs"=>[]];
if (!isset($state['saved']))    $state['saved']    = ["venues"=>[], "kjs"=>[]];

// Normalize PIN to digits only
if (isset($incoming['event']['pin'])) {
  $incoming['event']['pin'] = preg_replace('/\D+/', '', (string)$incoming['event']['pin']);
}

// Maintain saved lists (unique, most-recent first, max 50)
function push_unique(&$arr, $val) {
  $val = trim((string)$val);
  if ($val === '') return;
  $arr = array_values(array_filter($arr, fn($x)=>strcasecmp($x,$val)!==0));
  array_unshift($arr, $val);
  $arr = array_slice($arr, 0, 50);
}
if (!isset($incoming['saved']['venues'])) $incoming['saved']['venues'] = $state['saved']['venues'] ?? [];
if (!isset($incoming['saved']['kjs']))    $incoming['saved']['kjs']    = $state['saved']['kjs'] ?? [];

if (!empty($incoming['event']['venue'])) push_unique($incoming['saved']['venues'], $incoming['event']['venue']);
if (!empty($incoming['event']['kj']))    push_unique($incoming['saved']['kjs'],    $incoming['event']['kj']);

$incoming['serverUpdatedAt'] = time();

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($incoming, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
flock($fp, LOCK_UN);
fclose($fp);

echo '{"ok":true}';
