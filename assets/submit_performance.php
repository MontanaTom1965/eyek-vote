<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$DATA = __DIR__ . '/state.json';
$CSV  = __DIR__ . '/performances.csv';

// Read current state for context
$state = [];
if (file_exists($DATA)) {
  $state = json_decode(file_get_contents($DATA), true);
}

$body = json_decode(file_get_contents('php://input'), true);
$singer = trim($body['singer'] ?? ($state['current']['name'] ?? ''));
$song   = trim($body['song']   ?? ($state['current']['songArtist'] ?? ''));

$event  = $state['event'] ?? [];
$venue = trim($event['venue'] ?? '');
$venuePublic = trim($event['venuePublic'] ?? '');
$date  = trim($event['date'] ?? date('Y-m-d'));
$kj    = trim($event['kj'] ?? '');
$host  = trim($event['hostName'] ?? '');

if (!file_exists($CSV)) {
  file_put_contents($CSV, "timestamp,date,venue,venue_public,kj,host,singer,song\n");
}
$line = sprintf(
  "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
  date('c'), $date,
  str_replace('"','""',$venue),
  str_replace('"','""',$venuePublic),
  str_replace('"','""',$kj),
  str_replace('"','""',$host),
  str_replace('"','""',$singer),
  str_replace('"','""',$song)
);
file_put_contents($CSV, $line, FILE_APPEND);

echo json_encode(["ok"=>true]);
