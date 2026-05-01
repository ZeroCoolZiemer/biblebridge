<?php
/**
 * Cross-reference endpoint for the reader.
 * Proxies to BibleBridge API and normalizes the response.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config.php';

$reference = trim($_GET['reference'] ?? '');
$version   = strtolower(trim($_GET['v'] ?? 'kjv'));
$limit     = min((int)($_GET['limit'] ?? 8), 20);

if (empty($reference)) {
    echo json_encode(['error' => 'missing reference']);
    exit;
}

$apiData = bb_api_xrefs($reference, $version, $limit);

if (!empty($GLOBALS['bb_api_rate_limited'])) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited', 'reference' => $reference, 'cross_references' => []]);
    exit;
}

if (!$apiData || ($apiData['status'] ?? '') !== 'success') {
    echo json_encode(['reference' => $reference, 'cross_references' => []]);
    exit;
}

$displayBooks = $localized_books[$version] ?? $books;

$results = [];
foreach (($apiData['data'] ?? []) as $xref) {
    $bookName = $xref['book']['name'] ?? null;
    $bookId   = $xref['book']['id'] ?? 0;
    $ch = $xref['chapter'] ?? 0;
    $vs = $xref['verse'] ?? 0;
    $displayName = $displayBooks[$bookId] ?? $bookName;
    $results[] = [
        'reference' => $displayName ? "{$displayName} {$ch}:{$vs}" : ($xref['reference'] ?? null),
        'url'       => $bookName ? $bbBaseUrl . '/read/' . bookToSlug($bookName) . '/' . $ch . '/' . $vs : null,
        'weight'    => $xref['weight'] ?? 0,
        'text'      => $xref['text'] ?? null,
    ];
}

echo json_encode([
    'reference'        => $reference,
    'cross_references' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
