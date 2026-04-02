<?php
/**
 * Returns the next productive outgoing hops from a topic slug.
 * GET ?slug={slug}
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/config.php';

$slug = strtolower(trim($_GET['slug'] ?? ''));

if (empty($slug)) {
    echo json_encode(['nodes' => []]);
    exit;
}

$apiData = bb_api_topics($slug);
$nodes = [];
if ($apiData && ($apiData['status'] ?? '') === 'success') {
    foreach (($apiData['related'] ?? []) as $r) {
        if (in_array($r['relation'] ?? '', ['leads-to','results-in','causes','determines','transforms-to'])) {
            $nodes[] = ['slug' => $r['slug'], 'name' => $r['name'], 'relation_type' => $r['relation']];
        }
        if (count($nodes) >= 3) break;
    }
}

echo json_encode(['nodes' => $nodes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
