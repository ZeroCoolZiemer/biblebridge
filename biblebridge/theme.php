<?php
require_once __DIR__ . '/config.php';

$preset = $_GET['p'] ?? 'default';
if (!array_key_exists($preset, BB_THEME_PRESETS)) $preset = 'default';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('Vary: Accept-Encoding');

echo bbGetThemeCssRules($preset);
