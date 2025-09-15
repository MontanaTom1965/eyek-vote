<?php
// assets/get_state.php
// Reads assets/state.json (creates default if missing)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = __DIR__ . "/state.json";
if (!file_exists($path)) {
  $default = [
    "serverUpdatedAt" => time(),
    "event" => ["venue"=>"","kj"=>"","locked"=>false,"pin"=>""],
    "singers" => [],
    "current" => ["id"=>null,"name"=>"","songArtist"=>""],
    "voting" => [
      "open"=>false,"endsAt"=>0,"extendCount"=>0,
      "counts"=>["encore"=>0,"another"=>0,"maybe"=>0],
      "lastResult"=>null
    ]
  ];
  file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
readfile($path);
