<?php
/**
 * Deduplikace + propojení souborů s písněmi
 * Spustit: php link_files.php
 */
require_once "config.php";

$raw = file_get_contents($LOCAL_DB);
$data = json_decode($raw, true);
if (!is_array($data)) die("Chyba čtení songs.json\n");

// Záloha
file_put_contents($LOCAL_DB . ".bak", $raw);
echo "Záloha vytvořena.\n\n";

// Normalizace názvu pro porovnání
function norm($s) {
    $s = mb_strtolower(trim($s), 'UTF-8');
    // Odstraň příponu souboru
    $s = preg_replace('/\.(pdf|xml|sng|txt)$/i', '', $s);
    // Odstraň přídavky typu "- pěvecký sbor", "_kompakt", "_méně akordů", "_rozepsano", " 2"
    $s = preg_replace('/[\s_-]+(pěvecký sbor|kompakt|méně akordů|rozepsano|nevánoční verze)$/u', '', $s);
    $s = preg_replace('/\s+\d+$/', '', $s); // trailing číslo ("Zachránce vzácný 2")
    // Odstraň interpunkci (čárky, tečky, pomlčky...)
    $s = str_replace(['_', '-', ',', '.', '!', '?', ':', ';', '–', '—', '\'', '"'], ' ', $s);
    // Odstraň nadbytečné mezery
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

// === 1. DEDUPLIKACE ===
echo "=== DEDUPLIKACE ===\n";
$unique = [];
$merged = 0;

foreach ($data as $song) {
    $key = norm($song['name']);
    
    if (isset($unique[$key])) {
        $e = &$unique[$key];
        // Sloučit historii
        $h = array_unique(array_merge($e['history'] ?? [], $song['history'] ?? []));
        rsort($h);
        $e['history'] = array_values($h);
        $e['count'] = count($e['history']);
        if (count($e['history']) > 0) $e['last'] = $e['history'][0];
        // Doplnit co chybí
        foreach (['author','category','tempo','tags','pdf','openlp'] as $f) {
            if (empty($e[$f]) && !empty($song[$f])) $e[$f] = $song[$f];
        }
        echo "  Sloučeno: '{$song['name']}' → '{$e['name']}'\n";
        $merged++;
        unset($e);
    } else {
        $unique[$key] = $song;
    }
}
$data = array_values($unique);
echo "Sloučeno: $merged duplicit\nPísní po deduplikaci: " . count($data) . "\n\n";

// === 2. PROPOJENÍ SOUBORŮ ===
echo "=== PROPOJENÍ SOUBORŮ ===\n";

// Buildneme lookup: norm(songname) → index
$lookup = [];
foreach ($data as $i => $song) {
    $lookup[norm($song['name'])] = $i;
}

function linkFiles($dir, $field, &$data, &$lookup) {
    if (!is_dir($dir)) return;
    $linked = 0;
    $unmatched = [];
    
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $key = norm($f);
        
        if (isset($lookup[$key])) {
            $idx = $lookup[$key];
            if (empty($data[$idx][$field])) {
                $data[$idx][$field] = $f;
                $linked++;
                echo "  [{$field}] '{$data[$idx]['name']}' ← $f\n";
            }
        } else {
            $unmatched[] = $f;
        }
    }
    
    echo "  Propojeno: $linked\n";
    if ($unmatched) {
        echo "  Nepropojené:\n";
        foreach ($unmatched as $u) echo "    - $u\n";
    }
}

echo "\n--- PDF ---\n";
linkFiles(__DIR__ . "/pdf/", "pdf", $data, $lookup);

echo "\n--- OpenLP ---\n";
linkFiles(__DIR__ . "/openlp/", "openlp", $data, $lookup);

// Uložit
file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n✅ Hotovo! songs.json aktualizován.\n";
