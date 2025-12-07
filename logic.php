<?php
/* ========================================= */
/* SOUBOR: logic.php (Backend Logic)         */
/* ========================================= */

// --- AUTOMATICKÉ VERZOVÁNÍ ---
$lastMod = filemtime(__FILE__); 
$appVersion = date("Y.m.d-Hi", $lastMod) . "-stats"; 

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

// Seřazení filtrů
sort($categories);
sort($tags);
sort($tempos);

// 3. PŘÍPRAVA DAT PRO GRAFY (Top 20 & Flop 20)
// Nejhranější
$topSongs = $songsData;
usort($topSongs, fn($a, $b) => ($b["count"] ?? 0) <=> ($a["count"] ?? 0));
$top20 = array_slice($topSongs, 0, 20);

// Nejméně hrané (Rarity)
$flopSongs = $songsData;
// Řadíme vzestupně podle count
usort($flopSongs, fn($a, $b) => ($a["count"] ?? 0) <=> ($b["count"] ?? 0));
$flop20 = array_slice($flopSongs, 0, 20);

?>
