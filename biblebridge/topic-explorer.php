<?php
/**
 * Topic Explorer — /topics/{slug}
 * Fetches topic data from the BibleBridge API (standalone version).
 */

require_once __DIR__ . '/config.php';

$slug = strtolower(trim($_GET['slug'] ?? ''));

require_once __DIR__ . '/topic-explorer-api.php';
