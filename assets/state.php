<?php
// assets/state.php
// Simple state store: writes JSON posted from pages into assets/state.json
// OPTIONAL: set a shared secret to prevent random writes (leave blank to disable)
// Example: set $SECRET = "changeme"; and send header X-STATE-KEY: changeme from your pages.
$SECRET = ""; // e.g. "changeme"

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["error"=>"Method Not Allowed"]);
  exit;
}

if ($SECRET !== "") {
  $hdr = isset($_SERVER['HTTP_X_STATE_KEY']) ? $_SERVER['HTTP_X_STATE_KEY'] : "";
  if ($hdr !== $SECRET) {
    http_response_code(401);
    echo json_encode(["error"=>"Unauthorized"]);
    exit;
  }
}

$raw = file_get_contents("php://input");
if (!$raw) { $raw = "{}"; }

$json = json_decode($raw, true);
if (!is_array($json)) { $json = []; }

$path = __DIR__ . "/state.json";

// Ensure minimal shape & server timestamps
$now = time();
$json["serverUpdatedAt"] = $now;
if (!isset($json["event"])) $json["event"] = ["venue"=>"","kj"=>"","locked"=>false,"pin"=>""];
if (!isset($json["singers"])) $json["singers"] = []; // [{id,name}]
if (!isset($json["current"])) $json["current"] = ["id"=>null,"name"=>"","songArtist"=>""];
if (!isset($json["voting"])) $json["voting"] = [
  "open"=>false,"endsAt"=>0,"extendCount"=>0,
  "counts"=>["encore"=>0,"another"=>0,"maybe"=>0],
  "lastResult"=>null // "encore" | "another" | "maybe"
];

$tmp = $path . ".tmp";
file_put_contents($tmp, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
rename($tmp, $path);

echo json_encode(["ok"=>true,"updatedAt"=>$now]);
