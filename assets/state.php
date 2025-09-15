<?php
// assets/state.php
// Simple JSON state service for EYEK (no DB). Requires PHP on SiteGround.

// Where we persist state
$STATE_FILE = __DIR__ . '/state.json';

// Load current state or default
function default_state() {
  return [
    "event" => ["venue"=>"", "kj"=>"", "pin"=>"", "locked"=>false],
    "current" => ["name"=>"", "song"=>""],
    "queue" => [], // array of ["name"=>"Tom"]
    "votes" => ["encore"=>0, "another"=>0, "maybe"=>0],
    "voting" => ["isOpen"=>false, "endsAt"=>0], // unix ms
    "lastResult" => null, // "encore"|"another"|"maybe"|null
    "updated" => round(microtime(true)*1000)
  ];
}

function load_state($file) {
  if (!file_exists($file)) return default_state();
  $raw = file_get_contents($file);
  if ($raw === false || $raw === "") return default_state();
  $data = json_decode($raw, true);
  if (!is_array($data)) return default_state();
  return $data;
}

function save_state($file, $state) {
  $state["updated"] = round(microtime(true)*1000);
  file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'get';
$body = null;
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  if ($raw) { $body = json_decode($raw, true); }
}

$state = load_state($STATE_FILE);

// Helper: require PIN for host mutations (except voting)
function require_pin($state) {
  $pin = isset($_REQUEST['pin']) ? $_REQUEST['pin'] : '';
  if (!isset($state["event"]["pin"])) $state["event"]["pin"] = "";
  if ($pin === "" || $pin !== $state["event"]["pin"]) {
    http_response_code(403);
    echo json_encode(["ok"=>false, "error"=>"PIN required or incorrect"]);
    exit;
  }
}

// GET: always return state
if ($method === 'GET' && $action === 'get') {
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

// POST actions below

if ($action === 'save_event') {
  // Save Venue/KJ/PIN and lock flag
  // requires pin if already set and locked, otherwise no pin if first-time
  $incoming = $body ?: $_POST;
  $venue = trim($incoming['venue'] ?? "");
  $kj    = trim($incoming['kj'] ?? "");
  $pin   = trim($incoming['pin'] ?? "");
  $locked= !!($incoming['locked'] ?? false);

  // If a PIN already exists and locked, require it to change anything
  if (!empty($state["event"]["pin"]) && $state["event"]["locked"]) {
    require_pin($state);
  }

  $state["event"]["venue"] = $venue;
  $state["event"]["kj"]    = $kj;
  if ($pin !== "") { $state["event"]["pin"] = $pin; }
  $state["event"]["locked"]= $locked;

  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'toggle_lock') {
  require_pin($state);
  $state["event"]["locked"] = !($state["event"]["locked"] ?? false);
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'reset_event') {
  require_pin($state);
  $keepVenue = isset($_REQUEST['keepVenue']) ? ($_REQUEST['keepVenue'] === '1') : false;
  $keepKJ    = isset($_REQUEST['keepKJ']) ? ($_REQUEST['keepKJ'] === '1') : false;

  $venue = $keepVenue ? $state["event"]["venue"] : "";
  $kj    = $keepKJ ? $state["event"]["kj"] : "";
  $pin   = $state["event"]["pin"]; // keep same PIN

  $state = default_state();
  $state["event"]["venue"] = $venue;
  $state["event"]["kj"]    = $kj;
  $state["event"]["pin"]   = $pin;

  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'add_singer') {
  require_pin($state);
  $incoming = $body ?: $_POST;
  $name = trim($incoming['name'] ?? "");
  $song = trim($incoming['song'] ?? "");
  if ($name === "") { echo json_encode(["ok"=>false,"error"=>"name required"]); exit; }

  // Add to queue by name only
  $state["queue"][] = ["name"=>$name];
  // current song title is informational; store on current only when set_current
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'remove_singer') {
  require_pin($state);
  $name = trim($_REQUEST['name'] ?? "");
  $state["queue"] = array_values(array_filter($state["queue"], function($s) use ($name) {
    return $s["name"] !== $name;
  }));
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'set_current') {
  require_pin($state);
  $name = trim($_REQUEST['name'] ?? "");
  $song = trim($_REQUEST['song'] ?? "");
  $state["current"] = ["name"=>$name, "song"=>$song];

  // Optional: ensure current exists in queue. Don’t duplicate; it stays until you remove it manually.
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'open_voting') {
  require_pin($state);
  $now = round(microtime(true)*1000);
  $durationMs = intval($_REQUEST['ms'] ?? 30000); // default 30s
  $state["voting"]["isOpen"] = true;
  $state["voting"]["endsAt"] = $now + $durationMs;
  $state["votes"] = ["encore"=>0,"another"=>0,"maybe"=>0];
  $state["lastResult"] = null;
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'extend_voting') {
  require_pin($state);
  if (!$state["voting"]["isOpen"]) { echo json_encode(["ok"=>false,"error"=>"not open"]); exit; }
  $state["voting"]["endsAt"] += 15000; // +15s
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'close_voting') {
  require_pin($state);
  $state["voting"]["isOpen"] = false;
  $state["voting"]["endsAt"] = 0;

  // Compute winner now, once.
  $v = $state["votes"];
  $winner = "encore";
  if ($v["another"] > $v["encore"] && $v["another"] >= $v["maybe"]) $winner = "another";
  if ($v["maybe"]  > $v["encore"] && $v["maybe"]  >  $v["another"]) $winner = "maybe";
  // ties favor “encore” then “another”
  $state["lastResult"] = $winner;

  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

if ($action === 'vote') {
  // Voters do NOT need PIN
  if (!$state["voting"]["isOpen"]) { echo json_encode(["ok"=>false,"error"=>"voting closed"]); exit; }
  $choice = $_REQUEST['choice'] ?? "";
  if (!isset($state["votes"][$choice])) { echo json_encode(["ok"=>false,"error"=>"bad choice"]); exit; }
  // Very light throttling by IP (optional, minimal)
  $state["votes"][$choice] += 1;
  save_state($STATE_FILE, $state);
  echo json_encode(["ok"=>true, "state"=>$state]);
  exit;
}

echo json_encode(["ok"=>false, "error"=>"unknown action"]);
