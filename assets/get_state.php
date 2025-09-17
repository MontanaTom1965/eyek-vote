<?php
/**
 * EYEK • get_state.php  —  COMPLETE DROP-IN
 *
 * What this does:
 *   • Returns the current shared state as JSON for Host/Screen/Vote/Board.
 *   • Adds `serverNow` (ms epoch) so client countdowns can sync precisely.
 *   • Sends proper JSON/no-cache headers.
 *
 * How it finds $state:
 *   1) If a local builder file exists (get_state_core.php) and sets $state, we use it.
 *      - This is for setups where your original logic lives in a separate file.
 *   2) Else, if /assets/state.json exists, we load that.
 *      - Many EYEK setups write state to a JSON file that other endpoints update.
 *   3) Else, we fall back to a safe default closed state.
 *
 * NOTE: If your previous get_state.php already built $state inline,
 *       and you’re replacing it with this file, make sure that logic
 *       is moved to get_state_core.php (same folder) OR that your
 *       other endpoints keep /assets/state.json updated.
 */

// -----------------------------
// (1) Try to include a local builder that sets $state
//     If you don't have this file, no problem—code continues.
// -----------------------------
$state = null; // ensure defined
$corePath = __DIR__ . DIRECTORY_SEPARATOR . 'get_state_core.php';
if (is_file($corePath)) {
  // The core file should define $state as an array.
  // We suppress warnings in case the file echoes content; we only want $state.
  try {
    require $corePath; // expected to set $state
  } catch (\Throwable $e) {
    // ignore; we'll try JSON file fallback
  }
}

// -----------------------------
// (2) If still no $state, try JSON snapshot (e.g., written by state.php/vote.php)
//     Adjust $jsonPath if your snapshot lives elsewhere.
// -----------------------------
if (!is_array($state)) {
  $jsonPath = __DIR__ . DIRECTORY_SEPARATOR . 'state.json';
  if (is_file($jsonPath)) {
    $raw = @file_get_contents($jsonPath);
    if ($raw !== false) {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $state = $decoded;
      }
    }
  }
}

// -----------------------------
// (3) Safe default if nothing else provided state
// -----------------------------
if (!is_array($state)) {
  $state = [
    'phase'       => 'closed',               // 'closed' | 'prep' | 'open'
    'prepUntil'   => 0,                      // ms epoch
    'endsAt'      => 0,                      // ms epoch
    'counts'      => ['encore'=>0, 'another'=>0, 'maybe'=>0],
    'singer'      => null,
    'winner'      => null,
    'winType'     => null,                   // 'encore' | 'another' | 'maybe' | null
    'venuePublic' => null,
    // 'rotation'  => [ ['name'=>'Sam','badge'=>'encore'], ['name'=>'Jill','badge'=>'another'] ],
  ];
}

// -----------------------------
// (4) Add server time (ms epoch) so clients can compute offset
// -----------------------------
$state['serverNow'] = (int) floor(microtime(true) * 1000);

// -----------------------------
// (5) Output JSON with safe headers
// -----------------------------
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode($state, JSON_UNESCAPED_UNICODE);
