<?php
/**
 * scripts/import_locations.php
 * One-time importer: reads ph-json files → populates municipalities + barangays.
 * Safe to re-run: duplicates are skipped via INSERT IGNORE.
 * Run via CLI:     php scripts/import_locations.php
 * Run via browser: http://localhost/lumina-pos/scripts/import_locations.php
 *   (restrict access in production after use)
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('BASE', dirname(__DIR__));
require_once BASE . '/db.php';

$isCli = PHP_SAPI === 'cli';

function out(string $msg): void {
    global $isCli;
    echo $isCli ? $msg . PHP_EOL : nl2br(htmlspecialchars($msg)) . '<br>';
    if (!$isCli) ob_flush(); flush();
}

if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Import Locations</title>'
       . '<style>body{font-family:monospace;padding:2rem;background:#111;color:#0f0;font-size:.9rem;}</style></head><body>';
}

$timeStart  = microtime(true);
$memStart   = memory_get_usage();

// ── File paths ────────────────────────────────────────────────────────────────
$dataDir      = BASE . '/storage/data/ph-json';
$provinceFile = $dataDir . '/province.json';
$cityFile     = $dataDir . '/city.json';
$barangayFile = $dataDir . '/barangay.json';

foreach ([$provinceFile, $cityFile, $barangayFile] as $f) {
    if (!is_readable($f)) {
        out("ERROR: Cannot read file: $f");
        exit(1);
    }
}

// ── Load JSON ─────────────────────────────────────────────────────────────────
out('Loading JSON files...');
$provinces  = json_decode(file_get_contents($provinceFile), true);
$cities     = json_decode(file_get_contents($cityFile),     true);
$barangays  = json_decode(file_get_contents($barangayFile), true);

if (!is_array($provinces) || !is_array($cities) || !is_array($barangays)) {
    out('ERROR: JSON parse failed. Check file integrity.');
    exit(1);
}

out(sprintf('Loaded: %d provinces, %d municipalities, %d barangays',
    count($provinces), count($cities), count($barangays)));

// ── Build province lookup: province_code → province_name ──────────────────────
$provinceMap = [];
foreach ($provinces as $p) {
    $provinceMap[$p['province_code']] = $p['province_name'];
}

// ── Check if already imported ─────────────────────────────────────────────────
$conn = getConnection();
$existingCount = (int)$conn->query('SELECT COUNT(*) FROM municipalities')->fetch_row()[0];
if ($existingCount > 0) {
    out("Already imported: {$existingCount} municipalities found. Running in SKIP-DUPLICATE mode.");
}

// ── Import municipalities ─────────────────────────────────────────────────────
out('Importing municipalities...');

$stmtMun = $conn->prepare(
    'INSERT IGNORE INTO municipalities (province, municipality, psgc_code)
     VALUES (?, ?, ?)'
);

$munImported  = 0;
$munSkipped   = 0;
$cityCodeToId = []; // city_code → municipalities.id

$conn->begin_transaction();
try {
    foreach ($cities as $city) {
        $provinceName = $provinceMap[$city['province_code']] ?? 'Unknown';
        $cityName     = $city['city_name'];
        $psgcCode     = $city['psgc_code'] ?? null;

        $stmtMun->bind_param('sss', $provinceName, $cityName, $psgcCode);
        $stmtMun->execute();

        if ($stmtMun->affected_rows > 0) {
            $munImported++;
            $cityCodeToId[$city['city_code']] = $conn->insert_id;
        } else {
            $munSkipped++;
        }
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    out('FATAL: Municipality import failed: ' . $e->getMessage());
    exit(1);
}
$stmtMun->close();

out("Municipalities: {$munImported} imported, {$munSkipped} skipped.");

// ── Build city_code → municipality_id map for ALL (including pre-existing) ────
$result = $conn->query('SELECT id, municipality, psgc_code FROM municipalities');
$allMuns = $result->fetch_all(MYSQLI_ASSOC);

// Rebuild full city_code → id map using psgc_code prefix match
// psgc_code on municipality = city psgc_code (e.g. "012801000")
// city_code = first 6 chars of psgc_code (e.g. "012801")
$psgcToMunId = [];
foreach ($allMuns as $m) {
    if ($m['psgc_code']) {
        $psgcToMunId[$m['psgc_code']] = $m['id'];
    }
}

// Also fill from the import run's in-memory map
foreach ($cities as $city) {
    if (!isset($cityCodeToId[$city['city_code']]) && isset($psgcToMunId[$city['psgc_code']])) {
        $cityCodeToId[$city['city_code']] = $psgcToMunId[$city['psgc_code']];
    }
}

// ── Import barangays ──────────────────────────────────────────────────────────
out('Importing barangays...');

$stmtBrgy = $conn->prepare(
    'INSERT IGNORE INTO barangays (municipality_id, name, psgc_code)
     VALUES (?, ?, ?)'
);

$brgyImported = 0;
$brgySkipped  = 0;
$brgyErrors   = 0;
$batchSize    = 500;
$batch        = 0;

$conn->begin_transaction();
try {
    foreach ($barangays as $brgy) {
        $munId    = $cityCodeToId[$brgy['city_code']] ?? null;
        $psgcCode = $brgy['brgy_code'] ?? null;

        if (!$munId) {
            $brgyErrors++;
            continue;
        }

        $name = $brgy['brgy_name'];
        $stmtBrgy->bind_param('iss', $munId, $name, $psgcCode);
        $stmtBrgy->execute();

        if ($stmtBrgy->affected_rows > 0) {
            $brgyImported++;
        } else {
            $brgySkipped++;
        }

        $batch++;
        if ($batch % $batchSize === 0) {
            $conn->commit();
            $conn->begin_transaction();
            out("  ...{$batch} barangays processed");
        }
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    out('FATAL: Barangay import failed: ' . $e->getMessage());
    exit(1);
}
$stmtBrgy->close();
$conn->close();

// ── Summary ───────────────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $timeStart, 2);
$memUsed = round((memory_get_peak_usage() - $memStart) / 1048576, 2);

out('');
out('=== IMPORT COMPLETE ===');
out("Municipalities imported : {$munImported}");
out("Municipalities skipped  : {$munSkipped}");
out("Barangays imported      : {$brgyImported}");
out("Barangays skipped       : {$brgySkipped}");
out("Barangays with no match : {$brgyErrors}");
out("Execution time          : {$elapsed}s");
out("Peak memory used        : {$memUsed} MB");

if (!$isCli) {
    echo '</body></html>';
}
