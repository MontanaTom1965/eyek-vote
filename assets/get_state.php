<?php
/**
 * EYEK • get_state.php — Standalone
 *
 * Purpose:
 *   - Returns the current shared state as JSON for Host/Screen/Vote/Board.
 *   - Adds `serverNow` (ms epoch) so client countdowns can sync precisely.
 *   - Sends proper JSON + no-cache headers.
 *
 * Assumes:
 *   - Your state is being stored in /assets/state.json by state.php, vote.php, etc.
 *   - If that file doesn’t exist yet, it falls back to a safe “closed” default.
 */

// -----------------------------
// (1) Load state.json if it exists
// -----------------------------
$state = null;
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

// -----------------------------
// (2) If no state file, safe default
// -----------------------------
if (!is_array($state)) {
    $state = [
        'phase'       => 'closed',               // 'closed' | 'prep' | 'open'
        'prepUntil'   => 0,                      // ms epoch
        'endsAt'      => 0,                      // ms epoch
        'counts'      => ['encore'=>0,'another'=>0,'maybe'=>0],
        'singer'      => null,
        'winner'      => null,
        'winType'     => null,                   // 'encore'|'another'|'maybe'|null
        'venuePublic' => null,
        // 'rotation'  => [ ['name'=>'Sam','badge'=>'encore'], ... ],
    ];
}

// -----------------------------
// (3) Add server time (ms epoch)
// -----------------------------
$state['serverNow'] = (int) floor(microtime(true) * 1000);

// -----------------------------
// (4) Output JSON with headers
// -----------------------------
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode($state, JSON_UNESCAPED_UNICODE);
