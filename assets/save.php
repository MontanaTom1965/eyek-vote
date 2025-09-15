<?php
// assets/save.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // OK on simple app like this
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$store = __DIR__ . '/status.json';
if (!file_exists($store)) {
  file_put_contents($store, json_encode([
    "event" => ["venue"=>"", "kj"=>"", "pin"=>"", "locked"=>false, "startedAt"=>0],
    "current" => ["singer"=>"", "songArtist"=>"", "status"=>"idle", "voteEndsAt"=>0],
    "singers" => [],
    "results" => [],
    "lastChange" => time()
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$incoming = json_decode(file_get_contents('php://input'), true);
if (!$incoming || !isset($incoming['pin']) || !isset($incoming['data'])) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Bad request"]);
  exit;
}

$pin = trim((string)$incoming['pin']);
$data = $incoming['data'];

$current = json_decode(file_get_contents($store), true);
if (!is_array($current)) { $current = []; }

// PIN logic: if no PIN set yet, allow setting it now. Otherwise must match.
$existingPin = isset($current['event']['pin']) ? (string)$current['event']['pin'] : "";
if ($existingPin !== "" && $pin !== $existingPin) {
  http_response_code(403);
  echo json_encode(["ok"=>false, "error"=>"Invalid PIN"]);
  exit;
}

// Merge $data into $current safely
function deep_merge($a, $b) {
  foreach ($b as $k => $v) {
    if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
      $a[$k] = deep_merge($a[$k], $v);
    } else {
      $a[$k] = $v;
    }
  }
  return $a;
}

$updated = deep_merge($current, $data);
$updated['lastChange'] = time();

// Write atomically
$tmp = $store . '.tmp';
file_put_contents($tmp, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
rename($tmp, $store);

echo json_encode(["ok"=>true, "updated"=>$updated]);
