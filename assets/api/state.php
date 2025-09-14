<?php
// Simple JSON state store for EYEK.
// Actions require the current PIN (unless no PIN set yet).
// Stores file at ../state.json

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$stateFile = __DIR__ . '/../state.json';

// Helpers
function load_state($file) {
  if (!file_exists($file)) {
    $initial = [
      "venue" => "",
      "kj" => "",
      "pin" => "",
      "locked" => false,
      "phase" => "welcome", // welcome | performing | result
      "result" => null,     // encore | another | maybe | null
      "current" => ["singer" => "", "song" => ""],
      "updated" => time()
    ];
    file_put_contents($file, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
  return json_decode(file_get_contents($file), true);
}

function save_state($file, $state) {
  $state["updated"] = time();
  file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$method = $_SERVER['REQUEST_METHOD'];
$state = load_state($stateFile);

if ($method === 'GET') {
  echo json_encode($state);
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body["action"] ?? "";
$pin    = $body["pin"]    ?? "";

// Auth rule: if a PIN exists in state, it must match to change anything.
// If no PIN is set yet, allow setting one (set_config).
$hasPin = !empty($state["pin"]);
if ($hasPin && $pin !== $state["pin"]) {
  http_response_code(403);
  echo json_encode(["error" => "Invalid PIN"]);
  exit;
}

switch ($action) {
  case "set_config": // set venue/kj/pin; respects lock
    if ($state["locked"]) {
      http_response_code(423);
      echo json_encode(["error" => "Locked"]);
      exit;
    }
    $state["venue"] = trim($body["venue"] ?? $state["venue"]);
    $state["kj"]    = trim($body["kj"]    ?? $state["kj"]);
    // If provided, allow setting or updating the PIN
    if (isset($body["newPin"])) {
      $state["pin"] = (string)$body["newPin"];
    }
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "lock":
    $state["locked"] = true;
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "unlock":
    $state["locked"] = false;
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "reset_event":
    // Wipe event-specific info
    $state = [
      "venue" => "",
      "kj" => "",
      "pin" => "",         // clearing PIN so you can set a new one
      "locked" => false,
      "phase" => "welcome",
      "result" => null,
      "current" => ["singer" => "", "song" => ""],
      "updated" => time()
    ];
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "set_current":
    $state["current"]["singer"] = trim($body["singer"] ?? "");
    $state["current"]["song"]   = trim($body["song"] ?? "");
    $state["phase"] = "performing";
    $state["result"] = null;
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "show_welcome":
    $state["phase"] = "welcome";
    $state["result"] = null;
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  case "show_result":
    // result: encore | another | maybe
    $result = $body["result"] ?? null;
    if (!in_array($result, ["encore","another","maybe"], true)) {
      http_response_code(400);
      echo json_encode(["error" => "Invalid result"]);
      exit;
    }
    $state["phase"] = "result";
    $state["result"] = $result;
    save_state($stateFile, $state);
    echo json_encode(["ok" => true, "state" => $state]);
    break;

  default:
    http_response_code(400);
    echo json_encode(["error" => "Unknown action"]);
}
