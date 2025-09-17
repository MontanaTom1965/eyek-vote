<?php
// /assets/get_state.php
// Reads state.json and returns it with serverNow + no-cache headers

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$statePath = __DIR__ . '/state.json';

// Load current state.json or default
if (!file_exists($statePath)) {
  $state = [
    "serverUpdatedAt" => time(),
    "event" => ["venue"=>"", "venuePublic"=>"", "date"=>date('Y-m-d'), "kj"=>"", "hostName"=>"", "pin"=>"", "locked"=>false],
    "saved" => ["venues"=>[], "kjs"=>[]],
    "singers" => [],
    "current" => ["id"=>null, "name"=>"", "songArtist"=>""],
    "voting" => ["open"=>false, "prepUntil"=>0, "endsAt"=>0, "extendCount"=>0, "counts"=>["encore"=>0,"another"=>0,"maybe"=>0], "lastResult"=>null, "voters"=>[]],
    "winners" => ["encore"=>[], "another"=>[]]
  ];
} else {
  $raw = file_get_contents($statePath);
  $state = json_decode($raw, true);
  if (!is_array($state)) {
    $state = [];
  }
}

// Add serverNow in ms epoch for client sync
$state['serverNow'] = (int) floor(microtime(true) * 1000);

// Output JSON
echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
