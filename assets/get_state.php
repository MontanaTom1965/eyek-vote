<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA = __DIR__ . '/state.json';

function default_state() {
  return [
    "serverUpdatedAt" => time(),
    "event" => [
      "venue" => "",
      "venuePublic" => "",
      "date" => date('Y-m-d'),
      "kj" => "",
      "hostName" => "",
      "pin" => "",
      "locked" => false
    ],
    "saved" => [
      "venues" => [],
      "kjs" => []
    ],
    "singers" => [],
    "current" => ["id"=>null,"name"=>"","songArtist"=>""],
    "voting" => [
      "open" => false, "endsAt" => 0, "extendCount" => 0,
      "counts" => ["encore"=>0,"another"=>0,"maybe"=>0],
      "lastResult" => null,
      "voters" => []
    ],
    "winners" => ["encore"=>[], "another"=>[]]
  ];
}

if (!file_exists($DATA)) {
  $state = default_state();
  file_put_contents($DATA, json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  echo json_encode($state);
  exit;
}

$fp = fopen($DATA, 'r');
flock($fp, LOCK_SH);
$json = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$state = $json ? json_decode($json, true) : default_state();
if (!isset($state['saved'])) $state['saved'] = ["venues"=>[], "kjs"=>[]];

echo json_encode($state, JSON_UNESCAPED_SLASHES);
