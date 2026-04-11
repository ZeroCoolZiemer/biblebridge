<?php
/**
 * Context expansion proxy for the reader.
 * Works in both API mode (remote HTTP) and local mode (direct DB).
 * Uses bb_api_context() from whichever client is loaded by config.php.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config.php';

$reference = trim($_GET['reference'] ?? '');
$version   = strtolower(trim($_GET['version'] ?? 'kjv'));
$window    = min(max((int)($_GET['window'] ?? 2), 0), 10);

if (empty($reference)) {
    echo json_encode(['status' => 'error', 'error' => 'missing_parameter', 'message' => 'Missing reference.']);
    exit;
}

$data = bb_api_context($reference, $version, $window);

if (!empty($GLOBALS['bb_api_rate_limited'])) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'rate_limited']);
    exit;
}

if (!$data || ($data['status'] ?? '') !== 'success') {
    echo json_encode($data ?: ['status' => 'error', 'message' => 'Could not load context.']);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
