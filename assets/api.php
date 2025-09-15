<?php
// Simple JSON-backed state store for EYEK.
// Place in assets/api.php on the same domain as host/screen/vote pages.

// --- CONFIG ---
$file = __DIR__ . '/status.json';
$default = [
  "event" => ["venue" => "", "kj" => "", "pin" => "", "locked" => false],
  "singers" => [],                // [{id, name, history:[], tally:{encore,another,maybe}}]
  "currentSingerId" => null,
  "voting" => [                   // live voting session
    "open" => false,
    "endsAt" => 0,                // epoch ms
    "votes" => ["encore"=>0,"another"=>0,"maybe"=>0]
  ],
  "lastResult" => null,           // { singerId, outcome, at }
  "version" => time()
];

// --- Helpers ---
function load_state($file, $default) {
  if (!file_exists($file)) return $default;
  $raw = @file_get_contents($file);
  if ($raw === false) return $default;
  $data = @json_decode($raw, true);
  return is_array($data) ? $data : $default;
}
function save_state($file, $state) {
  $state['version'] = time();
  @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function json_out($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}
function require_pin_or_unlocked($state, $pin){
  if ($state['event']['locked'] && $pin !== $state['event']['pin']) {
    json_out(["ok"=>false,"error"=>"locked","msg"=>"Event is locked; valid PIN required."], 403);
  }
}

$cmd = $_GET['cmd'] ?? ($_POST['cmd'] ?? 'get');
$pin = $_GET['pin'] ?? ($_POST['pin'] ?? '');
$state = load_state($file, $default);

// Normalize choice
$validChoices = ["encore","another","maybe"];

// --- Commands ---
switch ($cmd) {
  case 'get':
    json_out(["ok"=>true,"state"=>$state]);

  case 'set_event': {
    // expects venue, kj, pin, locked?  (one Save button behavior)
    $venue = trim($_POST['venue'] ?? '');
    $kj    = trim($_POST['kj'] ?? '');
    $newPin= trim($_POST['pin'] ?? '');
    $locked= isset($_POST['locked']) ? filter_var($_POST['locked'], FILTER_VALIDATE_BOOL) : $state['event']['locked'];
    require_pin_or_unlocked($state, $pin);
    $state['event']['venue'] = $venue;
    $state['event']['kj']    = $kj;
    if ($newPin !== '') $state['event']['pin'] = $newPin;
    $state['event']['locked']= $locked;
    save_state($file, $state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'lock': {
    // toggle lock immediately
    $locked = filter_var($_POST['locked'] ?? 'true', FILTER_VALIDATE_BOOL);
    // allow locking without pin; require pin to unlock
    if ($locked === false) require_pin_or_unlocked($state, $pin);
    $state['event']['locked'] = $locked;
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'reset_event': {
    require_pin_or_unlocked($state, $pin);
    $keep = $state['event']; // keep venue/kj/pin/lock as-is
    $state = $default;
    $state['event'] = $keep;
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'add_singer': {
    require_pin_or_unlocked($state, $pin);
    $name = trim($_POST['name'] ?? '');
    $song = trim($_POST['song'] ?? '');
    if ($name==='') json_out(["ok"=>false,"error"=>"bad_singer"],400);
    $id = bin2hex(random_bytes(6));
    $state['singers'][] = [
      "id"=>$id,
      "name"=>$name,
      "history"=>$song!=='' ? [$song] : [],
      "tally"=>["encore"=>0,"another"=>0,"maybe"=>0]
    ];
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state,"id"=>$id]);
  }

  case 'remove_singer': {
    require_pin_or_unlocked($state, $pin);
    $id = $_POST['id'] ?? '';
    $state['singers'] = array_values(array_filter($state['singers'], fn($s)=>$s['id']!==$id));
    if ($state['currentSingerId']===$id) $state['currentSingerId']=null;
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'set_current': {
    require_pin_or_unlocked($state, $pin);
    $id = $_POST['id'] ?? '';
    $exists = array_filter($state['singers'], fn($s)=>$s['id']===$id);
    if (!$exists) json_out(["ok"=>false,"error"=>"no_singer"],404);
    $state['currentSingerId'] = $id;
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'start_vote': {
    require_pin_or_unlocked($state, $pin);
    $dur = intval($_POST['duration'] ?? 30);
    if ($dur < 5) $dur = 5;
    $state['voting'] = [
      "open"=>true,
      "endsAt"=>intval(microtime(true)*1000) + $dur*1000,
      "votes"=>["encore"=>0,"another"=>0,"maybe"=>0]
    ];
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'extend_vote': {
    require_pin_or_unlocked($state, $pin);
    $sec = intval($_POST['seconds'] ?? 15);
    if ($state['voting']['open']) {
      $state['voting']['endsAt'] += $sec*1000;
      save_state($file,$state);
    }
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'end_vote': {
    require_pin_or_unlocked($state, $pin);
    $state['voting']['endsAt'] = intval(microtime(true)*1000) - 1;
    $state['voting']['open'] = false;
    // decide outcome
    $v = $state['voting']['votes'];
    arsort($v); // highest first
    $winner = array_key_first($v); // "encore"|"another"|"maybe"
    $sid = $state['currentSingerId'];
    if ($sid) {
      foreach ($state['singers'] as &$s) {
        if ($s['id']===$sid) { $s['tally'][$winner] += 1; break; }
      }
    }
    $state['lastResult'] = ["singerId"=>$sid, "outcome"=>$winner, "at"=>intval(microtime(true)*1000)];
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'vote': {
    // public vote; no PIN
    if (!$state['voting']['open']) json_out(["ok"=>false,"error"=>"closed"],409);
    $choice = strtolower(trim($_POST['choice'] ?? ''));
    if (!in_array($choice,$validChoices,true)) json_out(["ok"=>false,"error"=>"bad_choice"],400);
    $now = intval(microtime(true)*1000);
    if ($now > ($state['voting']['endsAt'] ?? 0)) {
      $state['voting']['open'] = false;
    } else {
      $state['voting']['votes'][$choice] += 1;
    }
    save_state($file,$state);
    json_out(["ok"=>true,"state"=>$state]);
  }

  case 'host_vote': {
    // host vote just increments like a public vote; requires PIN or unlocked
    require_pin_or_unlocked($state, $pin);
    $choice = strtolower(trim($_POST['choice'] ?? ''));
    if (!in_array($choice,$validChoices,true)) json_out(["ok"=>false,"error"=>"bad_choice"],400);
    if ($state['voting']['open']) {
      $state['voting']['votes'][$choice] += 1;
      save_state($file,$state);
    }
    json_out(["ok"=>true,"state"=>$state]);
  }

  default:
    json_out(["ok"=>false,"error"=>"unknown_cmd"],400);
}
