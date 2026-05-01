<?php
/**
 * Topics for a verse — returns JSON.
 * The API doesn't have a per-verse topic lookup yet,
 * so this returns empty for now (upgrade path).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$reference = trim($_GET['reference'] ?? '');

echo json_encode([
    'reference' => $reference,
    'topics'    => [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
