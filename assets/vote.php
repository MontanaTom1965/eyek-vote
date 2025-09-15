<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA = __DIR__ . '/state.json';
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$choice = $body['choice'] ?? '';
$deviceId = $body['deviceId'] ?? '';
if (!in_array($choice, ['encore','another','maybe'], true) || !$deviceId) {
  http_response_code(400); echo '{"error":"bad vote"}'; exit;
}

$fp = fopen($DATA, 'c+');
flock($fp, LOCK_EX);
rewind($fp);
$cur = stream_get_contents($fp);
$state = $cur ? json_decode($cur, true) : null;
if (!$state) { flock($fp, LOCK_UN); fclose($fp); http_response_code(409); echo '{"error":"no state"}'; exit; }

$v =& $state['voting'];
$c =& $state['current'];
$e =& $state['event'];

if (!$v['open']) { flock($fp, LOCK_UN); fclose($fp); http_response_code(409); echo '{"error":"voting_closed"}'; exit; }

// Per-device per singer+song key
$songKey = strtolower(trim(($c['id']??'').'|'.($c['songArtist']??'')));
$devKey  = $songKey.'|'.$deviceId;

if (!isset($v['voters'])) $v['voters'] = [];
if (isset($v['voters'][$devKey])) {
  flock($fp, LOCK_UN); fclose($fp);
  http_response_code(429); echo '{"error":"already_voted"}'; exit;
}

$v['counts'][$choice] = ($v['counts'][$choice] ?? 0) + 1;
$v['voters'][$devKey] = true;
$state['serverUpdatedAt'] = time();

ftruncate($fp, 0); rewind($fp);
fwrite($fp, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
flock($fp, LOCK_UN); fclose($fp);

echo '{"ok":true}';
