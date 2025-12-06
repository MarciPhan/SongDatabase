<?php
// api_search.php
header('Content-Type: application/json');
require_once "config.php";

$q = $_GET['q'] ?? '';

// Pokud je dotaz prázdný, vrátíme prázdné pole
if (trim($q) === '') {
    echo json_encode([]);
    exit;
}

// Načtení dat
$songsData = [];
if (file_exists($LOCAL_DB)) {
    $songsData = json_decode(file_get_contents($LOCAL_DB), true);
}

// Filtrace
$results = [];
foreach ($songsData as $song) {
    // stripos = case-insensitive vyhledávání
    if (stripos($song['name'], $q) !== false) {
        $results[] = $song;
    }
}

// Omezíme počet výsledků na 10, ať neposíláme zbytečně moc dat
$results = array_slice($results, 0, 10);

echo json_encode($results);
?>
