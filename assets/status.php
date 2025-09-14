<?php
// assets/status.php
// Tiny JSON “state server” for your pages (Host writes, Screen reads)

declare(strict_types=1);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$FILE = __DIR__ . '/status.json';

// Ensure the file exists with a sane default
if (!file_exists($FILE)) {
  $default = [
    "venue" => "",
    "kj" => "",
    "pin" => "",
    "locked" => false,
    "currentSinger" => "",
    "currentSong" => "",
    "artist" => "",
    "phase" => "idle",   // idle | performing | voting | result
    "result" => null,    // encore | another | next
    "updated" => time()
  ];
  file_put_contents($FILE, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function read_state($FILE) {
  $raw = @file_get_contents($FILE);
  if ($raw === false || trim($raw) === "") return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function write_state($FILE, $state) {
  $state['updated'] = time();
  file_put_contents($FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $state;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$state  = read_state($FILE);

if ($method === 'GET') {
  echo json_encode($state);
  exit;
}

if ($method === 'POST') {
  // Read JSON body
  $body = file_get_contents('php://input');
  $payload = json_decode($body, true) ?: [];

  $action = $payload['action'] ?? 'save';

  // Lock rules:
  // - When locked=true, only allow: unlock, reset, or performance fields (singer/song/artist/phase/result).
  // - Venue/KJ/PIN cannot change while locked.
  $locked = (bool)($state['locked'] ?? false);

  $save = $state; // start from current

  if ($action === 'reset') {
    $save = [
      "venue" => "",
      "kj" => "",
      "pin" => "",
      "locked" => false,
      "currentSinger" => "",
      "currentSong" => "",
      "artist" => "",
      "phase" => "idle",
      "result" => null,
      "updated" => time()
    ];
    echo json_encode(write_state($FILE, $save));
    exit;
  }

  if ($action === 'lock') {
    // If they provided venue/kj/pin in this call, accept them first (when not already locked)
    if (!$locked) {
      if (isset($payload['venue'])) $save['venue'] = trim((string)$payload['venue']);
      if (isset($payload['kj']))    $save['kj']    = trim((string)$payload['kj']);
      if (isset($payload['pin']))   $save['pin']   = trim((string)$payload['pin']);
    }
    $save['locked'] = true;
    echo json_encode(write_state($FILE, $save));
    exit;
  }

  if ($action === 'unlock') {
    $save['locked'] = false;
    echo json_encode(write_state($FILE, $save));
    exit;
  }

  if ($action === 'save') {
    // Event setup (blocked when locked)
    if (!$locked) {
      if (isset($payload['venue'])) $save['venue'] = trim((string)$payload['venue']);
      if (isset($payload['kj']))    $save['kj']    = trim((string)$payload['kj']);
      if (isset($payload['pin']))   $save['pin']   = trim((string)$payload['pin']);
      if (isset($payload['locked'])) $save['locked'] = (bool)$payload['locked'];
    }

    // Performance state (always allowed)
    foreach (['currentSinger','currentSong','artist','phase','result'] as $k) {
      if (array_key_exists($k, $payload)) $save[$k] = $payload[$k];
    }

    echo json_encode(write_state($FILE, $save));
    exit;
  }

  // Unknown action
  http_response_code(400);
  echo json_encode(["error" => "Unknown action"]);
  exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
