<?php
// Simple JSON state saver for EYEK (SiteGround PHP).
// Writes JSON posted from the app into assets/status.json.
// Basic protection: shared token.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// --- CONFIG ---
$TOKEN = 'CHANGE_ME_LONG_RANDOM_TOKEN';  // <- set the same token in all pages below
$STATE_FILE = __DIR__ . '/status.json';  // saves alongside this file

// --- AUTH ---
$given = '';
if (isset($_SERVER['HTTP_X_AUTH'])) $given = $_SERVER['HTTP_X_AUTH'];
if (isset($_GET['token'])) $given = $_GET['token'];
if ($TOKEN === '' || $given !== $TOKEN) {
  http_response_code(401);
  echo json_encode(['ok'=>false, 'error'=>'unauthorized']);
  exit;
}

// --- READ BODY ---
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'empty body']);
  exit;
}
$json = json_decode($raw, true);
if (!is_array($json)) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'invalid json']);
  exit;
}

// --- SAFETY: tiny schema guard (minimally) ---
if (!isset($json['event'])) $json['event'] = [];
if (!isset($json['singers'])) $json['singers'] = [];
if (!isset($json['currentIndex'])) $json['currentIndex'] = -1;
if (!isset($json['voting'])) $json['voting'] = ['state'=>'idle','endsAt'=>0,'counts'=>['encore'=>0,'another'=>0,'maybe'=>0]];
if (!isset($json['soundsEnabled'])) $json['soundsEnabled'] = false;

// --- WRITE ATOMICALLY ---
$tmp = $STATE_FILE . '.tmp';
if (file_put_contents($tmp, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) === false) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'write tmp failed']);
  exit;
}
if (!rename($tmp, $STATE_FILE)) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'rename failed']);
  exit;
}

echo json_encode(['ok'=>true]);
