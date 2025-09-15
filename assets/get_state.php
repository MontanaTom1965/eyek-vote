<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA = __DIR__ . '/state.json';
if (!file_exists($DATA)) {
  // Bootstrap a fresh state
  $state = [
    "serverUpdatedAt" => time(),
    "event" => ["venue"=>"","venuePublic"=>"","date"=>date('Y-m-d'),"kj"=>"","pin"=>"","locked"=>false],
    "singers" => [],
    "current" => ["id"=>null,"name"=>"","songArtist"=>""],
    "voting" => [
      "open"=>false, "endsAt"=>0, "extendCount"=>0,
      "counts"=>["encore"=>0,"another"=>0,"maybe"=>0],
      "lastResult"=>null,
      // track device votes for current singer+song
      "voters"=>[]  // ["<deviceKey>"=>true]
    ],
    "winners" => ["encore"=>[], "another"=>[]]
  ];
  file_put_contents($DATA, json_encode($state, JSON_PRETTY_PRINT));
  echo json_encode($state);
  exit;
}

$fp = fopen($DATA, 'r');
flock($fp, LOCK_SH);
$json = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);
echo $json ?: '{}';
