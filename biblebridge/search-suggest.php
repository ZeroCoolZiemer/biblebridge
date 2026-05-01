<?php
/**
 * Instant search suggestions — returns JSON for typeahead.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/config.php';

$query   = trim($_GET['q'] ?? '');
$context = trim($_GET['book'] ?? '');

if ($query === '' || mb_strlen($query) < 2) {
    echo '[]';
    exit;
}

$results = [];
$qLower = mb_strtolower($query);

// Context-aware: if query is just a number (chapter) or number:number (chapter:verse)
if ($context && preg_match('/^(\d+)(?::(\d+))?$/', $query, $numMatch)) {
    $contextBookId = slugToBookIdMulti($context, $books, $localized_books);
    if ($contextBookId !== false) {
        $contextBook = $books[$contextBookId];
        $ch = (int)$numMatch[1];
        $v = isset($numMatch[2]) ? (int)$numMatch[2] : null;
        if ($ch >= 1 && $ch <= ($max_chapters[$contextBookId] ?? 999)) {
            $url = $bbBaseUrl . '/read/' . bookToSlug($contextBook) . '/' . $ch;
            $label = $contextBook . ' ' . $ch;
            if ($v) { $url .= '/' . $v; $label .= ':' . $v; }
            $results[] = ['type' => 'ref', 'label' => $label, 'url' => $url];
        }
    }
}

// Match book names for "go to" suggestions
foreach ($books as $bid => $bname) {
    $bnameLower = mb_strtolower($bname);
    $bnameSlug  = strtolower(str_replace(' ', '-', $bname));

    if (str_starts_with($bnameLower, $qLower) || str_starts_with($bnameSlug, $qLower)
        || str_starts_with($qLower, $bnameLower)) {
        $rest = trim(mb_substr($query, mb_strlen($bname)));
        $ch = 1; $v = null;
        if (preg_match('/^(\d+)(?::(\d+))?/', $rest, $m)) {
            $ch = (int)$m[1];
            $v = isset($m[2]) ? (int)$m[2] : null;
        }
        $url = $bbBaseUrl . '/read/' . bookToSlug($bname) . '/' . $ch;
        $label = $bname . ' ' . $ch;
        if ($v) { $url .= '/' . $v; $label .= ':' . $v; }
        $results[] = ['type' => 'ref', 'label' => $label, 'url' => $url];
        if (count($results) >= 5) break;
    }
}

// Also check localized book names
if (count($results) < 5) {
    foreach ($localized_books as $ver => $localBooks) {
        foreach ($localBooks as $bid => $bname) {
            if (str_starts_with(mb_strtolower($bname), $qLower)) {
                $engName = $books[$bid];
                $rest = trim(mb_substr($query, mb_strlen($bname)));
                $ch = 1; $v = null;
                if (preg_match('/^(\d+)(?::(\d+))?/', $rest, $m)) {
                    $ch = (int)$m[1];
                    $v = isset($m[2]) ? (int)$m[2] : null;
                }
                $url = $bbBaseUrl . '/read/' . bookToSlug($engName) . '/' . $ch;
                $label = $bname . ' ' . $ch;
                if ($v) { $url .= '/' . $v; $label .= ':' . $v; }
                $results[] = ['type' => 'ref', 'label' => $label, 'url' => $url];
                if (count($results) >= 5) break 2;
            }
        }
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
