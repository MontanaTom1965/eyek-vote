<?php
// assets/state.php
// Simple JSON file “state server” for EYEK. Reads/Writes assets/status.json
// SECURITY: uses event PIN as a shared secret for writes. Good enough for this use.
// PHP must be enabled on SiteGround (it is by default).

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$STATE_FILE = __DIR__ . '/status.json';

// ---------- helpers ----------
function now_ms() { return (int)(microtime(true) * 1000); }
function read_state($file) {
  if (!file_exists($file)) {
    $init = [
      "venue" => "",
      "kj" => "",
      "pin" => "",
      "locked" => false,
      "singers" => [], // each: {id, name, lastSong, status}
      "currentSingerId" => null,
      "voting" => [
        "open" => false,
        "endsAt" => 0,
        "encore" => 0,
        "another" => 0,
        "nexttime" => 0,
        "extended" => 0
      ],
      "lastResult" => null, // {singerId, result, at}
      "updatedAt" => now_ms()
    ];
    file_put_contents($file, json_encode($init, JSON_PRETTY_PRINT));
    return $init;
  }
  $raw = file_get_contents($file);
  $json = json_decode($raw, true);
  if (!is_array($json)) $json = [];
  // basic defaults
  $json += ["venue"=>"","kj"=>"","pin"=>"","locked"=>false,"singers"=>[],"currentSingerId"=>null,
            "voting"=>["open"=>false,"endsAt"=>0,"encore"=>0,"another"=>0,"nexttime"=>0,"extended"=>0],
            "lastResult"=>null,"updatedAt"=>now_ms()];
  return $json;
}
function write_state($file, $state) {
  $state["updatedAt"] = now_ms();
  $tmp = $file . ".tmp";
  $fp = fopen($tmp, 'c+');
  if (!$fp) { http_response_code(500); echo json_encode(["error"=>"cannot open temp"]); exit; }
  if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
  }
  fclose($fp);
  rename($tmp, $file);
}

function require_pin(&$state, $pin) {
  if (!isset($state["pin"]) || $state["pin"] === "") {
    // allow first-time set with any non-empty pin
    if (!$pin) { http_response_code(400); echo json_encode(["error"=>"PIN required to initialize"]); exit; }
    return true;
  }
  if (!$pin || $pin !== $state["pin"]) {
    http_response_code(403); echo json_encode(["error"=>"Invalid PIN"]); exit;
  }
  return true;
}
function find_singer_index($state, $id) {
  foreach ($state["singers"] as $i => $s) if ($s["id"] === $id) return $i;
  return -1;
}
function new_id() { return substr(bin2hex(random_bytes(6)), 0, 8); }

// ---------- route ----------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');

$state = read_state($STATE_FILE);

// auto-close voting if time passed (for all GETs)
$now = now_ms();
if ($state["voting"]["open"] && $now >= $state["voting"]["endsAt"]) {
  $state["voting"]["open"] = false;
  write_state($STATE_FILE, $state);
}

switch ($action) {
  case 'get':
    echo json_encode($state);
    break;

  case 'saveMeta': { // venue, kj, pin, lock
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $venue = trim($_POST['venue'] ?? "");
    $kj    = trim($_POST['kj'] ?? "");
    $newPin= trim($_POST['newPin'] ?? ""); // optional change
    $locked= ($_POST['locked'] ?? '') === 'true';

    if ($venue !== "") $state["venue"] = $venue;
    if ($kj !== "")    $state["kj"] = $kj;
    if ($newPin !== "") $state["pin"] = $newPin;
    $state["locked"] = $locked;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true, "state"=>$state]);
    break;
  }

  case 'lock': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $state["locked"] = true;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'unlock': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $state["locked"] = false;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'resetEvent': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $keepPin = $state["pin"];
    $state = [
      "venue" => "",
      "kj" => "",
      "pin" => $keepPin, // keep same PIN so host isn’t kicked out
      "locked" => false,
      "singers" => [],
      "currentSingerId" => null,
      "voting" => ["open"=>false,"endsAt"=>0,"encore"=>0,"another"=>0,"nexttime"=>0,"extended"=>0],
      "lastResult" => null,
      "updatedAt" => now_ms()
    ];
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'addSinger': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    if ($state["locked"]) { http_response_code(400); echo json_encode(["error"=>"Locked"]); exit; }
    $name = trim($_POST['name'] ?? "");
    $song = trim($_POST['song'] ?? "");
    if ($name === "") { http_response_code(400); echo json_encode(["error"=>"Singer name required"]); exit; }
    $id = new_id();
    $state["singers"][] = ["id"=>$id, "name"=>$name, "lastSong"=>$song, "status"=>"queued"];
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true, "id"=>$id]);
    break;
  }

  case 'removeSinger': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $id = $_POST['id'] ?? '';
    $i = find_singer_index($state, $id);
    if ($i >= 0) {
      if ($state["currentSingerId"] === $id) $state["currentSingerId"] = null;
      array_splice($state["singers"], $i, 1);
      write_state($STATE_FILE, $state);
    }
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'setCurrent': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $id = $_POST['id'] ?? '';
    $i = find_singer_index($state, $id);
    if ($i < 0) { http_response_code(404); echo json_encode(["error"=>"Singer not found"]); exit; }
    $state["currentSingerId"] = $id;
    // reset voting tallies when a new current is set
    $state["voting"] = ["open"=>false,"endsAt"=>0,"encore"=>0,"another"=>0,"nexttime"=>0,"extended"=>0];
    // mark status visible
    $state["singers"][$i]["status"] = "current";
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'startVoting': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    if (!$state["currentSingerId"]) { http_response_code(400); echo json_encode(["error"=>"No current singer"]); exit; }
    $duration = (int)($_POST['seconds'] ?? 30);
    $state["voting"]["open"] = true;
    $state["voting"]["encore"] = 0;
    $state["voting"]["another"] = 0;
    $state["voting"]["nexttime"] = 0;
    $state["voting"]["extended"] = 0;
    $state["voting"]["endsAt"] = now_ms() + ($duration * 1000);
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'extendVoting': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    if (!$state["voting"]["open"]) { http_response_code(400); echo json_encode(["error"=>"Voting not open"]); exit; }
    $extra = (int)($_POST['seconds'] ?? 15);
    $state["voting"]["endsAt"] += $extra * 1000;
    $state["voting"]["extended"] += $extra;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'endVoting': {
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    $state["voting"]["open"] = false;

    $e = $state["voting"]["encore"];
    $a = $state["voting"]["another"];
    $n = $state["voting"]["nexttime"];
    $result = "nexttime";
    $max = max($e, $a, $n);
    if ($max === $e) $result = "encore";
    elseif ($max === $a) $result = "another";
    else $result = "nexttime";

    $sid = $state["currentSingerId"];
    if ($sid) {
      $i = find_singer_index($state, $sid);
      if ($i >= 0) {
        $state["singers"][$i]["status"] = $result;
        if ($result === "nexttime") {
          // remove from list
          array_splice($state["singers"], $i, 1);
          $state["currentSingerId"] = null;
        }
      }
    }
    $state["lastResult"] = ["singerId"=>$sid, "result"=>$result, "at"=>now_ms()];
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true, "result"=>$result]);
    break;
  }

  case 'hostVote': { // host can quickly register one vote
    $pin = $_POST['pin'] ?? '';
    require_pin($state, $pin);
    if (!$state["voting"]["open"]) { http_response_code(400); echo json_encode(["error"=>"Voting not open"]); exit; }
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ["encore","another","nexttime"])) { http_response_code(400); echo json_encode(["error"=>"Bad vote"]); exit; }
    $state["voting"][$type] += 1;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  case 'vote': { // public vote, no PIN
    if (!$state["voting"]["open"]) { http_response_code(400); echo json_encode(["error"=>"Voting closed"]); exit; }
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ["encore","another","nexttime"])) { http_response_code(400); echo json_encode(["error"=>"Bad vote"]); exit; }
    $state["voting"][$type] += 1;
    write_state($STATE_FILE, $state);
    echo json_encode(["ok"=>true]);
    break;
  }

  default:
    http_response_code(404);
    echo json_encode(["error"=>"Unknown action"]);
}
