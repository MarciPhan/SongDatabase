<?php
/* ========================================= */
/* SOUBOR: logic.php (Backend Logic)         */
/* ========================================= */

// --- AUTOMATICKÉ VERZOVÁNÍ ---
// Hash se změní pokaždé, když uložíš tento soubor.
$lastMod = filemtime(__FILE__); 
// Přidali jsme suffix "-fix", aby prohlížeč poznal, že proběhla oprava a načetl nový script.js
$appVersion = date("Y.m.d-Hi", $lastMod) . ""; 

// Načtení konfigurace
if (file_exists("config.php")) {
    require_once "config.php";
}
if (!isset($LOCAL_DB)) $LOCAL_DB = "data.json";

// 1. NAČTENÍ DAT
$songsData = [];
if (file_exists($LOCAL_DB)) {
    $jsonContent = file_get_contents($LOCAL_DB);
    $songsData = json_decode($jsonContent, true);
}
if (!is_array($songsData)) $songsData = [];

// 2. EXTRAKCE PRO FILTRY
$categories = [];
$tags = [];
$tempos = [];
$totalPlays = 0;

foreach ($songsData as $s) {
    // Kategorie
    if (!empty($s["category"]) && !in_array($s["category"], $categories)) {
        $categories[] = $s["category"];
    }
    // Tempa
    if (!empty($s["tempo"]) && !in_array($s["tempo"], $tempos)) {
        $tempos[] = $s["tempo"];
    }
    // Tagy (oddělené čárkou)
    if (!empty($s["tags"])) {
        foreach (explode(",", $s["tags"]) as $t) {
            $t = trim($t);
            if ($t && !in_array($t, $tags)) $tags[] = $t;
        }
    }
    // Celkový počet hraní
    $totalPlays += (int)($s["count"] ?? 0);
}

// Seřazení
sort($categories);
sort($tags);
sort($tempos);

// 3. DATA PRO GRAF (Top 5)
$topSongs = $songsData;
usort($topSongs, fn($a, $b) => ($b["count"] ?? 0) <=> ($a["count"] ?? 0));
$topSongs = array_slice($topSongs, 0, 5);
?>
