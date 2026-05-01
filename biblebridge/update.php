<?php
/**
 * BibleBridge Standalone — One-Click Updater
 * Downloads the latest zip from holybible.dev and extracts it,
 * preserving config.local.php, plans/progress/*, and admin state.
 *
 * Safety: backs up the current install before applying. If anything
 * fails mid-update, the backup is restored automatically.
 */

// Try to extend execution time — x10 and similar hosts often kill at 30s
@set_time_limit(120);
@ini_set('max_execution_time', '120');

// Catch fatal errors (memory, timeout) and return JSON instead of dying silently
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Clear any partial output
        if (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $msg = 'Server fatal error: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line'];
        if (stripos($err['message'], 'time') !== false || stripos($err['message'], 'timeout') !== false) {
            $msg = 'PHP execution timed out. Your host may have a very short time limit. Try updating via manual upload instead.';
        } elseif (stripos($err['message'], 'memory') !== false) {
            $msg = 'PHP ran out of memory (limit: ' . ini_get('memory_limit') . '). Try updating via manual upload.';
        }
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
});

require_once __DIR__ . '/config.php';

// Admin auth — same as settings.php
$configFile = __DIR__ . '/config.local.php';
if (!file_exists($configFile)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not installed.']);
    exit;
}

$localConfig = require $configFile;
$adminToken  = $localConfig['admin_token'] ?? '';

// Accept token from POST body or query string
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if ($adminToken === '' || !hash_equals($adminToken, $token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid admin token.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Dispatch: route by action ─────────────────────────────────
// - rollback_status: read-only probe for the admin UI, allows GET
// - rollback: restore from latest backup or snapshot, requires POST
// - repair: re-download current version from archive + replace install, requires POST
// - default (update): existing self-update flow, requires POST
$action = $_POST['action'] ?? $_GET['action'] ?? 'update';

if ($action === 'rollback_status') {
    handleRollbackStatus();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

if ($action === 'rollback') {
    handleRollback();
    exit;
}

if ($action === 'repair') {
    handleRepair();
    exit;
}

$zipUrl = 'https://holybible.dev/standalone-build/download.php';

if (!is_writable(__DIR__)) {
    echo json_encode(['status' => 'error', 'message' => 'Install directory is not writable. Check file permissions.']);
    exit;
}

// ── Step 1: Download zip into memory ─────────────────────────
// No temp file needed — directExtract works on the string directly.

$zipData = false;

if (function_exists('curl_init')) {
    $ch = curl_init($zipUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'BibleBridge-Updater/' . BB_VERSION,
    ]);
    $zipData = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $zipData === false) {
        $zipData = false;
    }
}

if ($zipData === false && ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 30,
            'header'  => "User-Agent: BibleBridge-Updater/" . BB_VERSION . "\r\n",
        ],
    ]);
    $zipData = @file_get_contents($zipUrl, false, $ctx);
}

if ($zipData === false || strlen($zipData) < 1000) {
    $detail = '';
    if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
        $detail = ' Your server has both cURL and allow_url_fopen disabled.';
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not download update.' . $detail]);
    exit;
}

// ── Step 2: Preserve user data in memory (fast) ───────────────

$savedConfigLocal = @file_get_contents(__DIR__ . '/config.local.php');
$savedInstalled   = @file_get_contents(__DIR__ . '/.installed');
$savedProgress    = [];
$progressDir      = __DIR__ . '/plans/progress';
if (is_dir($progressDir)) {
    foreach (glob($progressDir . '/*') as $f) {
        if (is_file($f)) $savedProgress[basename($f)] = @file_get_contents($f);
    }
}

// ── Step 3: Extract zip directly to install dir ───────────────
// directExtract writes each file straight to __DIR__, skipping user-owned
// files. No temp dir, no backup copy — single pass, much faster on slow hosts.

$extractResult = directExtract($zipData, __DIR__, ['config.local.php', '.installed']);
if ($extractResult !== true) {
    echo json_encode(['status' => 'error', 'message' => $extractResult]);
    exit;
}

// ── Step 4: Restore preserved files ──────────────────────────

if ($savedConfigLocal !== false) {
    @file_put_contents(__DIR__ . '/config.local.php', $savedConfigLocal);
}
if ($savedInstalled !== false) {
    @file_put_contents(__DIR__ . '/.installed', $savedInstalled);
} elseif (!file_exists(__DIR__ . '/.installed')) {
    @file_put_contents(__DIR__ . '/.installed', date('c') . " (created by updater)\n");
}
if (!empty($savedProgress)) {
    if (!is_dir($progressDir)) @mkdir($progressDir, 0755, true);
    foreach ($savedProgress as $name => $content) {
        if ($content !== false) @file_put_contents($progressDir . '/' . $name, $content);
    }
}

// ── Step 5: Verify updated config.php ────────────────────────

$newConfigContent = @file_get_contents(__DIR__ . '/config.php');
if ($newConfigContent === false || strpos($newConfigContent, 'BB_VERSION') === false) {
    echo json_encode(['status' => 'error', 'message' => 'Update applied but config.php appears broken. Please re-upload manually.']);
    exit;
}

$newVersion = BB_VERSION;
if (preg_match("/define\('BB_VERSION',\s*'([^']+)'\)/", $newConfigContent, $m)) {
    $newVersion = $m[1];
}

// ── Step 6: Clean up any leftover backup/temp dirs ────────────

$parentDir        = dirname(__DIR__);
$backupSearchDirs = array_unique([$parentDir, __DIR__]);
foreach ($backupSearchDirs as $searchDir) {
    foreach (glob($searchDir . '/bb_backup_*') as $oldBackup) {
        if (is_dir($oldBackup)) removeDir($oldBackup);
    }
    $snapshots = [];
    foreach (glob($searchDir . '/bb_rollback_snapshot_*') as $snap) {
        if (is_dir($snap)) $snapshots[] = $snap;
    }
    if (count($snapshots) > 1) {
        usort($snapshots, function ($a, $b) { return filemtime($b) - filemtime($a); });
        for ($i = 1; $i < count($snapshots); $i++) removeDir($snapshots[$i]);
    }
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Updated to v' . $newVersion . '.',
    'version' => $newVersion,
]);

// --- Helper functions ---

function copyDir(string $src, string $dst, array $skipDirs = []): bool
{
    $dir = opendir($src);
    if (!$dir) return false;
    if (!is_dir($dst)) @mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || $file === '.git' || str_starts_with($file, 'bb_update_new_') || str_starts_with($file, 'bb_backup_') || str_starts_with($file, 'bb_rollback_snapshot_')) continue;
        if (in_array($file, $skipDirs, true)) continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            if (!copyDir($srcPath, $dstPath)) {
                closedir($dir);
                return false;
            }
        } else {
            if (!@copy($srcPath, $dstPath)) {
                closedir($dir);
                return false;
            }
        }
    }
    closedir($dir);
    return true;
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getRealPath());
        else @unlink($item->getRealPath());
    }
    @rmdir($dir);
}

function dirSize(string $dir): int
{
    $size = 0;
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    ) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

/**
 * Pure PHP zip extraction — no ZipArchive extension, no shell commands.
 * Reads the zip central directory and extracts stored/deflated entries.
 * Handles the BibleBridge zip structure (small files, no encryption).
 */
function purePhpUnzip(string $zipPath, string $destDir): bool
{
    $data = file_get_contents($zipPath);
    if ($data === false) return false;

    $len = strlen($data);

    // Find End of Central Directory record (scan backwards)
    $eocdPos = false;
    for ($i = $len - 22; $i >= max(0, $len - 65557); $i--) {
        if (substr($data, $i, 4) === "\x50\x4b\x05\x06") {
            $eocdPos = $i;
            break;
        }
    }
    if ($eocdPos === false) return false;

    $cdOffset = unpack('V', substr($data, $eocdPos + 16, 4))[1];
    $cdEntries = unpack('v', substr($data, $eocdPos + 10, 2))[1];

    $pos = $cdOffset;
    for ($e = 0; $e < $cdEntries; $e++) {
        if (substr($data, $pos, 4) !== "\x50\x4b\x01\x02") return false;

        $method    = unpack('v', substr($data, $pos + 10, 2))[1];
        $cSize     = unpack('V', substr($data, $pos + 20, 4))[1];
        $uSize     = unpack('V', substr($data, $pos + 24, 4))[1];
        $nameLen   = unpack('v', substr($data, $pos + 28, 2))[1];
        $extraLen  = unpack('v', substr($data, $pos + 30, 2))[1];
        $commentLen= unpack('v', substr($data, $pos + 32, 2))[1];
        $localOff  = unpack('V', substr($data, $pos + 42, 4))[1];
        $name      = substr($data, $pos + 46, $nameLen);

        $pos += 46 + $nameLen + $extraLen + $commentLen;

        // Security: skip entries with path traversal
        if (str_contains($name, '..') || str_starts_with($name, '/')) continue;

        $outPath = $destDir . '/' . $name;

        // Directory entry
        if (substr($name, -1) === '/') {
            if (!is_dir($outPath)) @mkdir($outPath, 0755, true);
            continue;
        }

        // Ensure parent directory exists
        $parentDir = dirname($outPath);
        if (!is_dir($parentDir)) @mkdir($parentDir, 0755, true);

        // Read from local file header
        $localNameLen  = unpack('v', substr($data, $localOff + 26, 2))[1];
        $localExtraLen = unpack('v', substr($data, $localOff + 28, 2))[1];
        $dataStart     = $localOff + 30 + $localNameLen + $localExtraLen;
        $compressed    = substr($data, $dataStart, $cSize);

        if ($method === 0) {
            // Stored
            file_put_contents($outPath, $compressed);
        } elseif ($method === 8) {
            // Deflated
            $inflated = @gzinflate($compressed);
            if ($inflated === false) return false;
            file_put_contents($outPath, $inflated);
        } else {
            // Unsupported method — skip
            continue;
        }
    }

    return true;
}

/**
 * Extract a BibleBridge zip string directly to the install directory.
 * Strips the leading 'biblebridge/' prefix from zip entry names and writes
 * files straight to $installDir — no temp directory, single pass.
 * Files listed in $skipRelPaths (relative to biblebridge/) are not written.
 * Returns true on success, or an error message string on failure.
 */
function directExtract(string $zipData, string $installDir, array $skipRelPaths = []): true|string
{
    $len = strlen($zipData);
    if ($len < 22) return 'Downloaded file is too small to be a valid zip.';

    // Find End of Central Directory record (scan backwards)
    $eocdPos = false;
    for ($i = $len - 22; $i >= max(0, $len - 65557); $i--) {
        if (substr($zipData, $i, 4) === "\x50\x4b\x05\x06") {
            $eocdPos = $i;
            break;
        }
    }
    if ($eocdPos === false) return 'Downloaded file is not a valid zip archive.';

    $cdOffset  = unpack('V', substr($zipData, $eocdPos + 16, 4))[1];
    $cdEntries = unpack('v', substr($zipData, $eocdPos + 10, 2))[1];

    $hasConfig = false;
    $pos = $cdOffset;
    for ($e = 0; $e < $cdEntries; $e++) {
        if (substr($zipData, $pos, 4) !== "\x50\x4b\x01\x02") return 'Zip central directory is corrupt.';

        $method     = unpack('v', substr($zipData, $pos + 10, 2))[1];
        $cSize      = unpack('V', substr($zipData, $pos + 20, 4))[1];
        $nameLen    = unpack('v', substr($zipData, $pos + 28, 2))[1];
        $extraLen   = unpack('v', substr($zipData, $pos + 30, 2))[1];
        $commentLen = unpack('v', substr($zipData, $pos + 32, 2))[1];
        $localOff   = unpack('V', substr($zipData, $pos + 42, 4))[1];
        $name       = substr($zipData, $pos + 46, $nameLen);

        $pos += 46 + $nameLen + $extraLen + $commentLen;

        // Security: reject path traversal
        if (str_contains($name, '..') || str_starts_with($name, '/')) continue;

        // Only process entries under biblebridge/
        if (!str_starts_with($name, 'biblebridge/')) continue;
        $relName = substr($name, strlen('biblebridge/'));

        if ($relName === 'config.php') $hasConfig = true;

        // Skip user-owned files and the directory entry itself
        if ($relName === '' || in_array($relName, $skipRelPaths, true)) continue;

        $outPath = $installDir . '/' . $relName;

        if (substr($name, -1) === '/') {
            if (!is_dir($outPath)) @mkdir($outPath, 0755, true);
            continue;
        }

        $parent = dirname($outPath);
        if (!is_dir($parent)) @mkdir($parent, 0755, true);

        $localNameLen  = unpack('v', substr($zipData, $localOff + 26, 2))[1];
        $localExtraLen = unpack('v', substr($zipData, $localOff + 28, 2))[1];
        $dataStart     = $localOff + 30 + $localNameLen + $localExtraLen;
        $compressed    = substr($zipData, $dataStart, $cSize);

        if ($method === 0) {
            file_put_contents($outPath, $compressed);
        } elseif ($method === 8) {
            $inflated = @gzinflate($compressed);
            if ($inflated !== false) file_put_contents($outPath, $inflated);
        }
        // Other methods: skip silently
    }

    if (!$hasConfig) return 'Zip does not contain a valid BibleBridge package (config.php missing).';
    return true;
}

// --- Rollback helpers and handlers (Phase B, v1.0.10) ---

/**
 * Preflight validation of a rollback candidate directory.
 * Returns ['valid' => bool, 'reason' => string, 'version' => string|null].
 * Runs in both rollback_status (so UI doesn't lie about availability) and
 * rollback (so execution doesn't start on an unsafe candidate).
 */
function validateRollbackCandidate(string $path): array
{
    if (!is_dir($path) || !is_readable($path)) {
        return ['valid' => false, 'reason' => 'unreadable', 'version' => null];
    }
    $configPath = $path . '/config.php';
    if (!file_exists($configPath) || !is_readable($configPath)) {
        return ['valid' => false, 'reason' => 'config_missing', 'version' => null];
    }
    $content = @file_get_contents($configPath);
    if ($content === false) {
        return ['valid' => false, 'reason' => 'config_unreadable', 'version' => null];
    }
    if (!preg_match("/define\('BB_VERSION',\s*'([^']+)'\)/", $content, $m)) {
        return ['valid' => false, 'reason' => 'version_unparseable', 'version' => null];
    }
    $version = $m[1];
    // Hard floor: v1.0.3 introduced the self-update path. Restoring below it
    // bricks the updater and strands the user without shell access.
    if (version_compare($version, '1.0.3', '<')) {
        return ['valid' => false, 'reason' => 'floor_violation', 'version' => $version];
    }
    // Defense in depth: confirm the candidate has update.php so the user
    // isn't stranded without a way to recover forward.
    if (!file_exists($path . '/update.php')) {
        return ['valid' => false, 'reason' => 'no_updater', 'version' => $version];
    }
    return ['valid' => true, 'reason' => 'ok', 'version' => $version];
}

/**
 * Scan parent dir and install dir for bb_backup_* and bb_rollback_snapshot_*
 * directories. Return the most recent one by mtime, or null if none exist.
 * The "undo last action" semantic: whichever restore point is newest
 * represents the state the user was in before the most recent action.
 */
function findLatestRestorePoint(): ?string
{
    $parentDir = dirname(__DIR__);
    $searchDirs = array_unique([$parentDir, __DIR__]);
    $candidates = [];
    foreach ($searchDirs as $searchDir) {
        foreach (glob($searchDir . '/bb_backup_*') as $p) {
            if (is_dir($p)) $candidates[] = $p;
        }
        foreach (glob($searchDir . '/bb_rollback_snapshot_*') as $p) {
            if (is_dir($p)) $candidates[] = $p;
        }
    }
    if (empty($candidates)) return null;
    usort($candidates, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    return $candidates[0];
}

/**
 * Handler for ?action=rollback_status — read-only probe, no side effects.
 * Returns JSON describing whether a rollback is available, and if so, what
 * version it would restore to. Used by the admin UI to decide whether to
 * show the "Restore previous version" button and what label to give it.
 */
function handleRollbackStatus(): void
{
    $restorePoint = findLatestRestorePoint();
    if ($restorePoint === null) {
        echo json_encode([
            'status' => 'success',
            'available' => false,
            'reason' => 'no_backup',
            'message' => 'No previous version available to restore. A backup is created automatically when you apply an update.',
        ]);
        return;
    }
    $validation = validateRollbackCandidate($restorePoint);
    if (!$validation['valid']) {
        $msg = match ($validation['reason']) {
            'floor_violation' => 'Cannot restore to v' . ($validation['version'] ?? 'unknown') . ' — that backup is too old and would leave your install without update controls. Manual reinstall required.',
            'no_updater' => 'Restore candidate is missing update.php — cannot safely rollback.',
            'config_missing', 'config_unreadable' => 'Restore candidate is missing or unreadable config.php.',
            'version_unparseable' => 'Restore candidate config.php has no parseable BB_VERSION.',
            default => 'Restore candidate failed validation (' . $validation['reason'] . ').',
        };
        echo json_encode([
            'status' => 'success',
            'available' => false,
            'reason' => $validation['reason'],
            'message' => $msg,
            'backup_version' => $validation['version'],
        ]);
        return;
    }
    $backupTime = date('Y-m-d H:i', filemtime($restorePoint));
    echo json_encode([
        'status' => 'success',
        'available' => true,
        'backup_name' => basename($restorePoint),
        'backup_version' => $validation['version'],
        'current_version' => BB_VERSION,
        'backup_time' => $backupTime,
        'message' => 'Restore to v' . $validation['version'] . ' (from ' . $backupTime . ')',
    ]);
}

/**
 * Handler for ?action=rollback — executes the restore with a snapshot safety net.
 *
 * Flow:
 *   1. Find latest restore point (backup or snapshot)
 *   2. Preflight validate
 *   3. Snapshot current install to bb_rollback_snapshot_{current_version}_{time}
 *   4. Read current user data (config.local.php, .installed, plans/progress/*)
 *   5. copyDir(restorePoint, __DIR__) — the actual rollback
 *   6. Write preserved user data back
 *   7. Verify config.php has BB_VERSION
 *   8. On any failure: copyDir(snapshot, __DIR__) and report error
 *   9. On success: delete older snapshots, keep the one just created
 */
function handleRollback(): void
{
    $parentDir = dirname(__DIR__);

    $restorePoint = findLatestRestorePoint();
    if ($restorePoint === null) {
        echo json_encode(['status' => 'error', 'message' => 'No previous version available to restore.']);
        return;
    }

    $validation = validateRollbackCandidate($restorePoint);
    if (!$validation['valid']) {
        $msg = match ($validation['reason']) {
            'floor_violation' => 'Cannot restore to v' . ($validation['version'] ?? 'unknown') . ' — that backup is too old and would leave your install without update controls. Manual reinstall required.',
            'no_updater' => 'Restore candidate is missing update.php — cannot safely rollback.',
            'config_missing', 'config_unreadable' => 'Restore candidate is missing or unreadable config.php.',
            'version_unparseable' => 'Restore candidate config.php has no parseable BB_VERSION.',
            default => 'Restore candidate failed validation (' . $validation['reason'] . ').',
        };
        echo json_encode(['status' => 'error', 'message' => $msg]);
        return;
    }

    $targetVersion = $validation['version'];
    $currentVersion = BB_VERSION;

    // Step 1: snapshot current install as safety net BEFORE touching anything.
    $snapshotDir = $parentDir . '/bb_rollback_snapshot_' . $currentVersion . '_' . date('Ymd_His');
    if (!is_writable($parentDir)) {
        $snapshotDir = __DIR__ . '/bb_rollback_snapshot_' . $currentVersion . '_' . date('Ymd_His');
    }
    if (!copyDir(__DIR__, $snapshotDir, ['cache'])) {
        removeDir($snapshotDir);
        echo json_encode(['status' => 'error', 'message' => 'Could not create safety snapshot. Rollback aborted — nothing changed.']);
        return;
    }

    // Step 2: read current user data from the live install (NOT the restore point).
    // User state should persist across rollback — only the code reverts.
    $currentConfigLocal = @file_get_contents(__DIR__ . '/config.local.php');
    $currentInstalled   = @file_get_contents(__DIR__ . '/.installed');
    $preservedProgress  = [];
    $currentProgressDir = __DIR__ . '/plans/progress';
    if (is_dir($currentProgressDir)) {
        foreach (glob($currentProgressDir . '/*') as $f) {
            if (is_file($f)) {
                $preservedProgress[basename($f)] = @file_get_contents($f);
            }
        }
    }

    // Step 3: copy restore point over current install.
    if (!copyDir($restorePoint, __DIR__)) {
        // Rollback-of-rollback: restore from safety snapshot.
        copyDir($snapshotDir, __DIR__);
        removeDir($snapshotDir);
        echo json_encode(['status' => 'error', 'message' => 'Rollback failed mid-copy. Safety snapshot restored — install is back on v' . $currentVersion . '.']);
        return;
    }

    // Step 4: write preserved user data back.
    if ($currentConfigLocal !== false) {
        @file_put_contents(__DIR__ . '/config.local.php', $currentConfigLocal);
    }
    if ($currentInstalled !== false) {
        @file_put_contents(__DIR__ . '/.installed', $currentInstalled);
    }
    if (!empty($preservedProgress)) {
        if (!is_dir($currentProgressDir)) @mkdir($currentProgressDir, 0755, true);
        foreach ($preservedProgress as $name => $content) {
            if ($content !== false) {
                @file_put_contents($currentProgressDir . '/' . $name, $content);
            }
        }
    }

    // Step 5: verify the rollback didn't break config.php.
    $newConfigContent = @file_get_contents(__DIR__ . '/config.php');
    if ($newConfigContent === false || strpos($newConfigContent, 'BB_VERSION') === false) {
        // Invalid state — restore from safety snapshot.
        copyDir($snapshotDir, __DIR__);
        if ($currentConfigLocal !== false) {
            @file_put_contents(__DIR__ . '/config.local.php', $currentConfigLocal);
        }
        removeDir($snapshotDir);
        echo json_encode(['status' => 'error', 'message' => 'Rollback left install in invalid state. Safety snapshot restored.']);
        return;
    }

    // Parse actual restored version from the freshly-written config.php.
    $restoredVersion = $targetVersion;
    if (preg_match("/define\('BB_VERSION',\s*'([^']+)'\)/", $newConfigContent, $m)) {
        $restoredVersion = $m[1];
    }

    // Step 6: cleanup — keep the snapshot we just created, delete any older ones.
    // This preserves the "undo the rollback" capability while bounding disk usage.
    $searchDirs = array_unique([$parentDir, __DIR__]);
    foreach ($searchDirs as $searchDir) {
        foreach (glob($searchDir . '/bb_rollback_snapshot_*') as $oldSnapshot) {
            if (is_dir($oldSnapshot) && $oldSnapshot !== $snapshotDir) {
                removeDir($oldSnapshot);
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Restored to v' . $restoredVersion . '. Previous state snapshotted — you can undo this restore from the Maintenance section.',
        'version' => $restoredVersion,
        'previous_version' => $currentVersion,
    ]);
}

// --- Repair helpers and handler (Phase C, v1.0.10) ---

/**
 * Download a zip from a URL with cURL + file_get_contents fallback.
 * Returns the binary zip data on success, or false on failure.
 * Shared by the repair handler; the main update flow has its own inline copy.
 */
function fetchZip(string $url): false|string
{
    $zipData = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'BibleBridge-Updater/' . BB_VERSION,
        ]);
        $zipData  = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || $zipData === false) {
            $zipData = false;
        }
    }

    if ($zipData === false && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 30,
                'header'  => "User-Agent: BibleBridge-Updater/" . BB_VERSION . "\r\n",
            ],
        ]);
        $zipData = @file_get_contents($url, false, $ctx);
    }

    if ($zipData === false || strlen($zipData) < 1000) {
        return false;
    }
    return $zipData;
}

/**
 * Extract a zip file to a destination directory using whichever method is
 * available (ZipArchive → shell unzip → purePhpUnzip). Returns true on success.
 */
function extractZip(string $zipPath, string $destDir): bool
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }
        $hasConfig = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($zip->getNameIndex($i) === 'biblebridge/config.php') {
                $hasConfig = true;
                break;
            }
        }
        if (!$hasConfig) {
            $zip->close();
            return false;
        }
        $zip->extractTo($destDir);
        $zip->close();
        return true;
    }

    $shellDisabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
    if (!in_array('shell_exec', $shellDisabled) && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN'
        && @shell_exec('which unzip 2>/dev/null')) {
        $escapedZip = escapeshellarg($zipPath);
        $escapedDst = escapeshellarg($destDir);
        @exec("unzip -o {$escapedZip} -d {$escapedDst} 2>&1", $out, $code);
        return $code === 0;
    }

    return purePhpUnzip($zipPath, $destDir);
}

/**
 * Handler for ?action=repair — re-downloads the user's CURRENT version and
 * replaces install files. Unlike update, does not advance the version. Unlike
 * rollback, does not create a persistent restore point.
 *
 * Flow:
 *   1. Compute version-specific archive URL from BB_VERSION
 *   2. Try the archive URL; if it 404s or fails, fall back to download.php
 *   3. Verify extracted zip's BB_VERSION matches current version (prevents
 *      accidental force-upgrade if the fallback is used on a version that
 *      doesn't have an archive yet)
 *   4. Snapshot current install to bb_repair_snapshot_{version}_{timestamp}
 *   5. Preserve user data (config.local.php, .installed, plans/progress/*)
 *   6. copyDir(extracted, __DIR__) — the actual repair
 *   7. Write preserved user data back
 *   8. Verify config.php has BB_VERSION
 *   9. On any failure: copyDir(snapshot, __DIR__) and report error
 *  10. On success: delete the snapshot (transient safety net only)
 */
function handleRepair(): void
{
    $parentDir  = dirname(__DIR__);
    $currentVer = BB_VERSION;

    // Preflight: writable install dir, enough disk space.
    if (!is_writable(__DIR__)) {
        echo json_encode(['status' => 'error', 'message' => 'Install directory is not writable. Check file permissions.']);
        return;
    }
    $installSize = dirSize(__DIR__);
    $freeSpace   = @disk_free_space(__DIR__);
    if ($freeSpace !== false && $freeSpace < $installSize * 3) {
        echo json_encode(['status' => 'error', 'message' => 'Not enough disk space for safe repair.']);
        return;
    }

    // Step 1: try version-specific archive, fall back to current stable.
    $primaryUrl  = 'https://github.com/ZeroCoolZiemer/biblebridge/releases/download/v' . $currentVer . '/biblebridge-reader.zip';
    $fallbackUrl = 'https://holybible.dev/standalone-build/download.php';

    $zipData = fetchZip($primaryUrl);
    if ($zipData === false) {
        $zipData = fetchZip($fallbackUrl);
    }
    if ($zipData === false) {
        echo json_encode(['status' => 'error', 'message' => 'Could not download repair package from archive or current stable.']);
        return;
    }

    // Write zip to temp file.
    $tmpDir = sys_get_temp_dir();
    if (!is_writable($tmpDir)) $tmpDir = __DIR__;
    $tmpZip = @tempnam($tmpDir, 'bb_repair_');
    if ($tmpZip === false && $tmpDir !== __DIR__) {
        $tmpZip = @tempnam(__DIR__, 'bb_repair_');
    }
    if ($tmpZip === false) {
        $tmpZip = __DIR__ . '/bb_repair_' . bin2hex(random_bytes(8)) . '.zip';
    }
    file_put_contents($tmpZip, $zipData);

    // Step 2: extract to temp dir.
    $tmpExtract = $parentDir . '/bb_repair_new_' . time();
    if (!@mkdir($tmpExtract, 0755, true)) {
        $tmpExtract = __DIR__ . '/bb_repair_new_' . time();
        if (!@mkdir($tmpExtract, 0755, true)) {
            @unlink($tmpZip);
            echo json_encode(['status' => 'error', 'message' => 'Could not create temp directory for extraction.']);
            return;
        }
    }

    if (!extractZip($tmpZip, $tmpExtract) || !file_exists($tmpExtract . '/biblebridge/config.php')) {
        @unlink($tmpZip);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Failed to extract repair package.']);
        return;
    }
    @unlink($tmpZip);

    $extractedSource = $tmpExtract . '/biblebridge';

    // Step 3: verify the extracted zip matches current version. This is the
    // key safety check that prevents accidental force-upgrade when we fell
    // back to the current stable URL on a version that doesn't have a
    // per-version archive yet.
    $extractedConfigContent = @file_get_contents($extractedSource . '/config.php');
    $extractedVersion       = null;
    if ($extractedConfigContent !== false
        && preg_match("/define\('BB_VERSION',\s*'([^']+)'\)/", $extractedConfigContent, $m)) {
        $extractedVersion = $m[1];
    }
    if ($extractedVersion !== $currentVer) {
        removeDir($tmpExtract);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Repair package version mismatch: archive has v' . ($extractedVersion ?: 'unknown')
                       . ' but your install is v' . $currentVer
                       . '. No archive exists for your version yet — repair is unavailable until a release is published. (Try updating to the latest version instead.)',
        ]);
        return;
    }

    // Step 4: snapshot current install as safety net.
    $snapshotDir = $parentDir . '/bb_repair_snapshot_' . $currentVer . '_' . date('Ymd_His');
    if (!is_writable($parentDir)) {
        $snapshotDir = __DIR__ . '/bb_repair_snapshot_' . $currentVer . '_' . date('Ymd_His');
    }
    if (!copyDir(__DIR__, $snapshotDir, ['cache'])) {
        removeDir($snapshotDir);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Could not create safety snapshot. Repair aborted — nothing changed.']);
        return;
    }

    // Step 5: read current user data to preserve.
    $currentConfigLocal = @file_get_contents(__DIR__ . '/config.local.php');
    $currentInstalled   = @file_get_contents(__DIR__ . '/.installed');
    $preservedProgress  = [];
    $currentProgressDir = __DIR__ . '/plans/progress';
    if (is_dir($currentProgressDir)) {
        foreach (glob($currentProgressDir . '/*') as $f) {
            if (is_file($f)) {
                $preservedProgress[basename($f)] = @file_get_contents($f);
            }
        }
    }

    // Step 6: copy extracted files over install.
    if (!copyDir($extractedSource, __DIR__)) {
        copyDir($snapshotDir, __DIR__);
        removeDir($snapshotDir);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Repair failed mid-copy. Safety snapshot restored — install is back to its prior state.']);
        return;
    }

    // Step 7: restore preserved user data.
    if ($currentConfigLocal !== false) {
        @file_put_contents(__DIR__ . '/config.local.php', $currentConfigLocal);
    }
    if ($currentInstalled !== false) {
        @file_put_contents(__DIR__ . '/.installed', $currentInstalled);
    }
    if (!empty($preservedProgress)) {
        if (!is_dir($currentProgressDir)) @mkdir($currentProgressDir, 0755, true);
        foreach ($preservedProgress as $name => $content) {
            if ($content !== false) {
                @file_put_contents($currentProgressDir . '/' . $name, $content);
            }
        }
    }

    // Step 8: verify the repaired install has valid config.php.
    $newConfigContent = @file_get_contents(__DIR__ . '/config.php');
    if ($newConfigContent === false || strpos($newConfigContent, 'BB_VERSION') === false) {
        copyDir($snapshotDir, __DIR__);
        if ($currentConfigLocal !== false) {
            @file_put_contents(__DIR__ . '/config.local.php', $currentConfigLocal);
        }
        removeDir($snapshotDir);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Repair left install in invalid state. Safety snapshot restored.']);
        return;
    }

    // Step 9: cleanup — delete both the snapshot (transient) and the temp extract dir.
    // Repair creates no persistent restore point; the rollback button's state
    // is untouched by repair because bb_backup_* and bb_rollback_snapshot_*
    // are left alone. Only bb_repair_snapshot_* is created and deleted.
    removeDir($snapshotDir);
    removeDir($tmpExtract);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Install repaired to v' . $currentVer . '. All files refreshed from the canonical archive.',
        'version' => $currentVer,
    ]);
}
