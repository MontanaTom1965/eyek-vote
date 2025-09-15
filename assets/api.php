<?php
// assets/api.php
// Minimal JSON state API for EYEK. Stores state in assets/status.json

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // same domain in practice
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$store = __DIR__ . '/status.json';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function load_state($path) {
  if (!file_exists($path)) {
    // default empty state
    return [
      "event" => ["venue"=>"", "kj"=>"", "locked"=>false],
      "currentSinger" => ["id"=>"", "name"=>"", "songArtist"=>""],
      "queue" => [], // [{id,name}]
      "voting" => ["isOpen"=>false, "endsAt"=>0, "encore"=>0, "another"=>0, "maybe"=>0],
      "lastResult" => "", // "encore" | "another" | "maybe" | ""
      "version" => time()
    ];
  }
  $raw = file_get_contents($path);
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];
  return $data;
}

function save_state($path, $data) {
  $tmp = $path . '.tmp';
  file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
  rename($tmp, $path);
}

$state = load_state($store);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode($state);
  exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true);
if (!$payload) {
  http_response_code(400);
  echo json_encode(["error"=>"invalid json"]);
  exit;
}

$action = $payload["action"] ?? "";

// --- host updates full state (save button) ---
if ($action === "save_state") {
  // trust the shape sent by host page
  $incoming = $payload["state"] ?? null;
  if (!is_array($incoming)) {
    http_response_code(400);
    echo json_encode(["error"=>"missing state"]);
    exit;
  }
  $incoming["version"] = time();
  save_state($store, $incoming);
  echo json_encode(["ok"=>true, "state"=>$incoming]);
  exit;
}

// --- voting increments (vote page & host vote buttons) ---
if ($action === "vote") {
  $choice = $payload["choice"] ?? "";
  if (!in_array($choice, ["encore","another","maybe"])) {
    http_response_code(400);
    echo json_encode(["error"=>"bad choice"]);
    exit;
  }
  // only count if voting is open
  if (!empty($state["voting"]["isOpen"])) {
    $state["voting"][$choice] = intval($state["voting"][$choice] ?? 0) + 1;
    $state["version"] = time();
    save_state($store, $state);
    echo json_encode(["ok"=>true, "voting"=>$state["voting"]]);
  } else {
    echo json_encode(["ok"=>false, "reason"=>"voting_closed"]);
  }
  exit;
}

// --- control voting window (host page) ---
if ($action === "voting") {
  $cmd = $payload["cmd"] ?? "";
  if ($cmd === "start") {
    $seconds = intval($payload["seconds"] ?? 30);
    $state["voting"] = ["isOpen"=>true, "endsAt"=>time()+$seconds, "encore"=>0, "another"=>0, "maybe"=>0];
    $state["lastResult"] = "";
  } elseif ($cmd === "extend") {
    $extra = intval($payload["seconds"] ?? 15);
    $state["voting"]["endsAt"] = intval($state["voting"]["endsAt"] ?? time()) + $extra;
  } elseif ($cmd === "end") {
    $state["voting"]["isOpen"] = false;
    $e = intval($state["voting"]["encore"] ?? 0);
    $a = intval($state["voting"]["another"] ?? 0);
    $m = intval($state["voting"]["maybe"] ?? 0);
    $max = max($e,$a,$m);
    $result = ($max == $e) ? "encore" : (($max == $a) ? "another" : "maybe");
    $state["lastResult"] = $result;
  } else {
    http_response_code(400);
    echo json_encode(["error"=>"bad voting cmd"]);
    exit;
  }
  $state["version"] = time();
  save_state($store, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

// --- set current singer or manage queue (host page) ---
if ($action === "set_current") {
  $s = $payload["singer"] ?? null; // {id,name,songArtist}
  if (!$s) { http_response_code(400); echo json_encode(["error"=>"missing singer"]); exit; }
  $state["currentSinger"] = $s;
  $state["version"] = time();
  save_state($store, $state);
  echo json_encode(["ok"=>true, "currentSinger"=>$s]);
  exit;
}

if ($action === "set_queue") {
  $q = $payload["queue"] ?? [];
  if (!is_array($q)) $q=[];
  $state["queue"] = $q;
  $state["version"] = time();
  save_state($store, $state);
  echo json_encode(["ok"=>true, "queue"=>$q]);
  exit;
}

if ($action === "event") {
  // update event (venue/kj/locked)
  $evt = $payload["event"] ?? [];
  $state["event"]["venue"]  = trim($evt["venue"] ?? ($state["event"]["venue"] ?? ""));
  $state["event"]["kj"]     = trim($evt["kj"] ?? ($state["event"]["kj"] ?? ""));
  $state["event"]["locked"] = !!($evt["locked"] ?? ($state["event"]["locked"] ?? false));
  $state["version"] = time();
  save_state($store, $state);
  echo json_encode(["ok"=>true, "event"=>$state["event"]]);
  exit;
}

http_response_code(400);
echo json_encode(["error"=>"unknown action"]);
