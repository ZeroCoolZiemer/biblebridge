<?php
/**
 * BibleBridge Standalone — One-Click Updater
 * Downloads the latest zip from holybible.dev and extracts it,
 * preserving config.local.php, plans/progress/*, and admin state.
 *
 * Safety: backs up the current install before applying. If anything
 * fails mid-update, the backup is restored automatically.
 */

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

$zipUrl = $_POST['zip_url'] ?? '';
if (!$zipUrl || !str_starts_with($zipUrl, 'https://holybible.dev/')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid zip URL.']);
    exit;
}

// Preflight checks — need ZipArchive or shell unzip
$hasZipArchive = class_exists('ZipArchive');
$hasUnzip      = !$hasZipArchive && (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') && @shell_exec('which unzip 2>/dev/null');
if (!$hasZipArchive && !$hasUnzip) {
    echo json_encode(['status' => 'error', 'message' => 'No zip support available. Install PHP zip extension or the unzip command.']);
    exit;
}

if (!is_writable(__DIR__)) {
    echo json_encode(['status' => 'error', 'message' => 'Install directory is not writable. Check file permissions.']);
    exit;
}

// Check disk space — need room for backup + extraction (~3x current size)
$installSize = dirSize(__DIR__);
$freeSpace   = @disk_free_space(__DIR__);
if ($freeSpace !== false && $freeSpace < $installSize * 3) {
    echo json_encode(['status' => 'error', 'message' => 'Not enough disk space for safe update.']);
    exit;
}

// ── Step 1: Download zip ──────────────────────────────────────

$tmpZip = tempnam(sys_get_temp_dir(), 'bb_update_');
$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 30,
        'header'  => "User-Agent: BibleBridge-Updater/" . BB_VERSION . "\r\n",
    ],
]);
$zipData = @file_get_contents($zipUrl, false, $ctx);
if ($zipData === false || strlen($zipData) < 1000) {
    @unlink($tmpZip);
    echo json_encode(['status' => 'error', 'message' => 'Could not download update. Try again later.']);
    exit;
}
file_put_contents($tmpZip, $zipData);

// ── Step 2: Validate & extract zip ──────────────────────────────

$parentDir  = dirname(__DIR__);
$tmpExtract = $parentDir . '/bb_update_new_' . time();
if (!@mkdir($tmpExtract, 0755, true)) {
    @unlink($tmpZip);
    echo json_encode(['status' => 'error', 'message' => 'Could not create temp directory for extraction.']);
    exit;
}

if ($hasZipArchive) {
    $zip = new ZipArchive();
    $res = $zip->open($tmpZip);
    if ($res !== true) {
        @unlink($tmpZip);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Downloaded file is corrupt or not a valid zip.']);
        exit;
    }
    // Verify the zip contains expected structure
    $hasConfig = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if ($zip->getNameIndex($i) === 'biblebridge/config.php') {
            $hasConfig = true;
            break;
        }
    }
    if (!$hasConfig) {
        $zip->close();
        @unlink($tmpZip);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Zip does not contain a valid BibleBridge package.']);
        exit;
    }
    $zip->extractTo($tmpExtract);
    $zip->close();
} else {
    // Fallback: shell unzip
    $escapedZip = escapeshellarg($tmpZip);
    $escapedDst = escapeshellarg($tmpExtract);
    @exec("unzip -o {$escapedZip} -d {$escapedDst} 2>&1", $out, $code);
    if ($code !== 0) {
        @unlink($tmpZip);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Failed to extract zip (unzip exit code ' . $code . ').']);
        exit;
    }
    // Verify expected structure
    if (!file_exists($tmpExtract . '/biblebridge/config.php')) {
        @unlink($tmpZip);
        removeDir($tmpExtract);
        echo json_encode(['status' => 'error', 'message' => 'Zip does not contain a valid BibleBridge package.']);
        exit;
    }
}
@unlink($tmpZip);

$extractedSource = $tmpExtract . '/biblebridge';
if (!is_dir($extractedSource)) {
    removeDir($tmpExtract);
    echo json_encode(['status' => 'error', 'message' => 'Unexpected zip structure.']);
    exit;
}

// ── Step 4: Back up current install ───────────────────────────

$backupDir = $parentDir . '/bb_backup_' . BB_VERSION . '_' . date('Ymd_His');
$backedUp  = copyDir(__DIR__, $backupDir);
if (!$backedUp) {
    removeDir($tmpExtract);
    removeDir($backupDir);
    echo json_encode(['status' => 'error', 'message' => 'Could not create backup. Update aborted — nothing changed.']);
    exit;
}

// ── Step 5: Copy new files over current install ───────────────

$ok = copyDir($extractedSource, __DIR__);
removeDir($tmpExtract);

if (!$ok) {
    // Restore from backup
    copyDir($backupDir, __DIR__);
    removeDir($backupDir);
    echo json_encode(['status' => 'error', 'message' => 'Update failed mid-copy. Rolled back to previous version.']);
    exit;
}

// ── Step 6: Restore preserved files ───────────────────────────

// config.local.php — always keep the user's config
$backupConfig = $backupDir . '/config.local.php';
if (file_exists($backupConfig)) {
    copy($backupConfig, __DIR__ . '/config.local.php');
}

// .installed sentinel — preserve or create to prevent accidental re-provisioning
$backupInstalled = $backupDir . '/.installed';
if (file_exists($backupInstalled)) {
    copy($backupInstalled, __DIR__ . '/.installed');
} elseif (!file_exists(__DIR__ . '/.installed')) {
    @file_put_contents(__DIR__ . '/.installed', date('c') . " (created by updater)\n");
}

// plans/progress — reading plan progress
$backupProgress = $backupDir . '/plans/progress';
if (is_dir($backupProgress)) {
    $progressDir = __DIR__ . '/plans/progress';
    if (!is_dir($progressDir)) @mkdir($progressDir, 0755, true);
    foreach (glob($backupProgress . '/*') as $f) {
        if (is_file($f)) copy($f, $progressDir . '/' . basename($f));
    }
}

// ── Step 7: Verify the update didn't break config.php ─────────

$newConfigContent = @file_get_contents(__DIR__ . '/config.php');
if ($newConfigContent === false || strpos($newConfigContent, 'BB_VERSION') === false) {
    // Critical file missing or broken — rollback
    copyDir($backupDir, __DIR__);
    if (file_exists($backupConfig)) copy($backupConfig, __DIR__ . '/config.local.php');
    removeDir($backupDir);
    echo json_encode(['status' => 'error', 'message' => 'Updated config.php appears broken. Rolled back to previous version.']);
    exit;
}

// Read new version from the freshly updated config.php
$newVersion = BB_VERSION;
if (preg_match("/define\('BB_VERSION',\s*'([^']+)'\)/", $newConfigContent, $m)) {
    $newVersion = $m[1];
}

// ── Step 8: Clean up old backups (keep only most recent) ──────

foreach (glob($parentDir . '/bb_backup_*') as $oldBackup) {
    if (is_dir($oldBackup) && $oldBackup !== $backupDir) {
        removeDir($oldBackup);
    }
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Updated to v' . $newVersion . '. Previous version backed up.',
    'version' => $newVersion,
]);

// --- Helper functions ---

function copyDir(string $src, string $dst): bool
{
    $dir = opendir($src);
    if (!$dir) return false;
    if (!is_dir($dst)) @mkdir($dst, 0755, true);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || $file === '.git') continue;
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
