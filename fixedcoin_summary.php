<?php
/**
 * FixedCoin Halving/Network state endpoint
 * - CORS + JSON
 * - 5s cache
 * - Pulls chain data from Explorer (eIquidus)
 * - Computes next halving with CUSTOM schedule
 * - NEW: Calculates average blocks per 24h based on last 100 blocks
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ------------------------------
// Config
// ------------------------------
$CACHE_TTL_SEC      = 5;
$CACHE_DIR          = __DIR__ . '/cache';
$CACHE_FILE         = $CACHE_DIR . '/state.json';

$EXPLORER_HOST      = 'https://explorer.fixedcoin.org';
$API_GETBLOCKCOUNT  = $EXPLORER_HOST . '/api/getblockcount';
$API_GETDIFFICULTY  = $EXPLORER_HOST . '/api/getdifficulty';
$API_GETHASHPS      = $EXPLORER_HOST . '/api/getnetworkhashps';
$API_GETBLOCKHASH   = $EXPLORER_HOST . '/api/getblockhash';
$API_GETBLOCK       = $EXPLORER_HOST . '/api/getblock';
$EXT_GETSUPPLY      = $EXPLORER_HOST . '/ext/getmoneysupply';
$EXT_GETSUMMARY     = $EXPLORER_HOST . '/ext/getsummary';

$BLOCK_TIME_SEC     = 600;  // target block time (10 minutes)
$BLOCKS_TO_ANALYZE  = 100;  // analyze last 100 blocks for average

// ------------------------------
// Cache (serve fresh if younger than TTL)
// ------------------------------
$nowMs = (int) round(microtime(true) * 1000);
if (is_file($CACHE_FILE) && (time() - filemtime($CACHE_FILE) < $CACHE_TTL_SEC)) {
  $raw = file_get_contents($CACHE_FILE);
  if ($raw !== false) {
    echo $raw;
    exit;
  }
}

// ------------------------------
// HTTP helpers
// ------------------------------
function http_get($url, $timeout = 5) {
  $ctx = stream_context_create([
    'http' => [
      'timeout' => $timeout,
      'ignore_errors' => true,
      'header' => "Cache-Control: no-cache\r\n"
    ]
  ]);
  return @file_get_contents($url, false, $ctx);
}

function http_get_json($url, $timeout = 5) {
  $raw = http_get($url, $timeout);
  if ($raw === false) return null;
  $j = json_decode($raw, true);
  if (!is_array($j)) return null;
  return $j;
}

function to_number($val) {
  if (is_numeric($val)) return (float)$val;
  if (!is_string($val)) return 0.0;
  $clean = trim($val);
  $parts = preg_split('/\s+/', $clean);
  $first = $parts[0];
  $first = str_replace([',', ' '], '', $first);
  if (!is_numeric($first)) {
    if (preg_match('/[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?/', $clean, $m)) {
      $first = $m[0];
    }
  }
  return is_numeric($first) ? (float)$first : 0.0;
}

// ------------------------------
// Fetch primitives from endpoints
// ------------------------------
$height_raw = http_get($API_GETBLOCKCOUNT);
$height     = $height_raw !== false ? (int)trim($height_raw) : 0;

$difficulty_raw = http_get($API_GETDIFFICULTY);
$difficulty     = $difficulty_raw !== false ? (float)trim($difficulty_raw) : 0.0;

$hashps_raw = http_get($API_GETHASHPS);
$hashps     = $hashps_raw !== false ? (float)trim($hashps_raw) : 0.0;

$supply_raw = http_get($EXT_GETSUPPLY);
$supply     = $supply_raw !== false ? (float)trim($supply_raw) : 0.0;

// Fallback to /ext/getsummary if any are missing
if ($height <= 0 || $hashps <= 0 || $difficulty <= 0 || $supply <= 0) {
  $sum = http_get_json($EXT_GETSUMMARY);
  if (is_array($sum)) {
    if ($height <= 0) {
      if (isset($sum['height']))      $height = (int)$sum['height'];
      if (isset($sum['blockcount']))  $height = (int)$sum['blockcount'];
      if (isset($sum['blocks']))      $height = (int)$sum['blocks'];
    }
    if ($difficulty <= 0 && isset($sum['difficulty']))
      $difficulty = to_number($sum['difficulty']);
    if ($supply <= 0 && isset($sum['supply']))
      $supply = to_number($sum['supply']);
    if ($hashps <= 0 && isset($sum['hashrate'])) {
      $hr = $sum['hashrate'];
      if (is_numeric($hr)) {
        $hashps = (float)$hr;
      } else if (is_string($hr)) {
        if (preg_match('/([-+]?[0-9]*\.?[0-9]+)\s*([kMGTPEZY]?H)\/s/i', $hr, $m)) {
          $v = (float)$m[1];
          $u = strtoupper($m[2]);
          $scale = [
            'H'  => 0,
            'KH' => 3, 'MH' => 6, 'GH' => 9, 'TH' => 12, 'PH' => 15,
            'EH' => 18, 'ZH' => 21, 'YH' => 24
          ];
          $exp = isset($scale[$u]) ? $scale[$u] : 0;
          $hashps = $v * pow(10, $exp);
        } else {
          $hashps = to_number($hr);
        }
      }
    }
  }
}

// ------------------------------
// NEW: Calculate average blocks per 24h
// ------------------------------
$avgBlocksPer24h = 0;
$actualBlockTime = $BLOCK_TIME_SEC; // default to target

if ($height >= $BLOCKS_TO_ANALYZE) {
  // Get current block hash
  $currentHash = http_get($API_GETBLOCKHASH . '?index=' . $height);
  // Get block 100 blocks ago
  $oldBlockIndex = $height - $BLOCKS_TO_ANALYZE;
  $oldHash = http_get($API_GETBLOCKHASH . '?index=' . $oldBlockIndex);
  
  if ($currentHash !== false && $oldHash !== false) {
    $currentHash = trim($currentHash);
    $oldHash = trim($oldHash);
    
    // Get full block info with timestamps
    $currentBlock = http_get_json($API_GETBLOCK . '?hash=' . $currentHash);
    $oldBlock = http_get_json($API_GETBLOCK . '?hash=' . $oldHash);
    
    if (is_array($currentBlock) && is_array($oldBlock)) {
      $currentTime = isset($currentBlock['time']) ? (int)$currentBlock['time'] : 0;
      $oldTime = isset($oldBlock['time']) ? (int)$oldBlock['time'] : 0;
      
      if ($currentTime > 0 && $oldTime > 0 && $currentTime > $oldTime) {
        $timeDiff = $currentTime - $oldTime;
        $actualBlockTime = $timeDiff / $BLOCKS_TO_ANALYZE;
        $avgBlocksPer24h = 86400 / $actualBlockTime;
      }
    }
  }
}

// If we couldn't calculate, use theoretical value
if ($avgBlocksPer24h <= 0) {
  $avgBlocksPer24h = 86400 / $BLOCK_TIME_SEC; // 144 blocks per day
}

// ------------------------------
// Humanize hashrate helper
// ------------------------------
function human_hashrate($h) {
  $units = ['H/s','kH/s','MH/s','GH/s','TH/s','PH/s','EH/s','ZH/s','YH/s'];
  $idx = 0;
  while ($h >= 1000 && $idx < count($units)-1) { $h /= 1000; $idx++; }
  return [
    'value' => $h,
    'unit'  => $units[$idx],
    'human' => sprintf('%.3f %s', $h, $units[$idx])
  ];
}

// ------------------------------
// FixedCoin halving schedule
// ------------------------------
$halvings = [
  ['name' => 'First halving',   'block' =>   4200,   'to' => 0.5],
  ['name' => 'Second halving',  'block' =>   8400,   'to' => 0.25],
  ['name' => 'Third halving',   'block' =>  12600,   'to' => 0.125],
  ['name' => 'Fourth halving',  'block' =>  16800,   'to' => 0.0625],
  ['name' => 'Fifth halving',   'block' =>  21000,   'to' => 0.03125],
  ['name' => 'Sixth halving',   'block' =>  25200,   'to' => 0.015625],
  ['name' => 'Seventh halving', 'block' =>  29400,   'to' => 0.0078125],
  ['name' => 'Eighth halving',  'block' =>  33600,   'to' => 0.00390625],
  ['name' => 'Final blocks',    'block' => 113400,   'to' => 0],
];

// ------------------------------
// Determine current and next reward
// ------------------------------
function getCurrentReward($height) {
  if ($height == 0) return 1.0;        // Genesis
  if ($height == 1) return 1600.0;     // Premine
  if ($height < 4200) return 1.0;      // Initial
  if ($height < 8400) return 0.5;
  if ($height < 12600) return 0.25;
  if ($height < 16800) return 0.125;
  if ($height < 21000) return 0.0625;
  if ($height < 25200) return 0.03125;
  if ($height < 29400) return 0.015625;
  if ($height < 33600) return 0.0078125;
  if ($height < 113400) {
    // Continue halving pattern
    $interval = 4200;
    $startBlock = 33600;
    $startReward = 0.00390625;
    $halvingsPassed = floor(($height - $startBlock) / $interval);
    return $startReward / pow(2, $halvingsPassed);
  }
  return 0; // No more rewards
}

$currentReward = getCurrentReward($height);

// Find next halving
$nextIdx = null;
for ($i = 0; $i < count($halvings); $i++) {
  if ($height < (int)$halvings[$i]['block']) { 
    $nextIdx = $i; 
    break; 
  }
}

if ($nextIdx === null) {
  // All halvings completed
  $nextReward        = 0;
  $nextHalvingBlock  = 113400;
  $blocksRemaining   = 0;
  $progressPct       = 100;
  $targetHalvingTs   = $nowMs;
} else {
  $nextHalving       = $halvings[$nextIdx];
  $nextHalvingBlock  = (int)$nextHalving['block'];
  $nextReward        = (float)$nextHalving['to'];
  $blocksRemaining   = max(0, $nextHalvingBlock - $height);

  // Progress from previous halving boundary to next
  $prevBoundary      = ($nextIdx === 0) ? 2 : (int)$halvings[$nextIdx - 1]['block'];
  $interval          = max(1, $nextHalvingBlock - $prevBoundary);
  $done              = max(0, $height - $prevBoundary);
  $progressPct       = max(0, min(100, ($done / $interval) * 100));

  // Use actual block time for ETA if available
  $blockTimeToUse = $actualBlockTime > 0 ? $actualBlockTime : $BLOCK_TIME_SEC;
  $targetHalvingTs   = $nowMs + ($blocksRemaining * $blockTimeToUse * 1000);
}

// ------------------------------
// Build response
// ------------------------------
$out = [
  'serverTime'       => $nowMs,
  'as_of_ms'         => $nowMs,
  'block'            => (int)$height,
  'difficulty'       => $difficulty,
  'supply'           => $supply,
  'hashrate'         => human_hashrate($hashps),

  'currentReward'    => $currentReward,
  'nextReward'       => $nextReward,
  'nextHalvingBlock' => $nextHalvingBlock,
  'blocksRemaining'  => (int)$blocksRemaining,
  'progressPct'      => $progressPct,
  'targetHalvingTs'  => (int)$targetHalvingTs,
  
  // NEW: Average blocks per 24h
  'avgBlocksPer24h'  => round($avgBlocksPer24h, 2),
  'actualBlockTime'  => round($actualBlockTime, 1),
];

// ------------------------------
// Persist cache and serve
// ------------------------------
if (!is_dir($CACHE_DIR)) { @mkdir($CACHE_DIR, 0775, true); }

$tmp = json_encode($out, JSON_UNESCAPED_UNICODE);
if ($tmp !== false) {
  @file_put_contents($CACHE_FILE, $tmp, LOCK_EX);

  $mt = @filemtime($CACHE_FILE);
  if ($mt) {
    $out['as_of_ms'] = (int)($mt * 1000);
    $tmp = json_encode($out, JSON_UNESCAPED_UNICODE);
    if ($tmp !== false) { @file_put_contents($CACHE_FILE, $tmp, LOCK_EX); }
  }
}

echo @file_get_contents($CACHE_FILE) ?: $tmp;
?>
