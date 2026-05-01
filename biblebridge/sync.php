<?php
/**
 * Cloud Sync proxy — forwards to holybible.dev/reader/sync.
 * Standalone builds don't have their own DB, so we proxy.
 */
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$syncUrl = 'https://holybible.dev/reader/sync.php';

if ($method === 'GET') {
    $code = urlencode(trim($_GET['code'] ?? ''));
    $response = @file_get_contents($syncUrl . '?code=' . $code);
    echo $response ?: json_encode(['error' => 'Sync unavailable']);
    exit;
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($syncUrl, false, $ctx);
    echo $response ?: json_encode(['error' => 'Sync unavailable']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
