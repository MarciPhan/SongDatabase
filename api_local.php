<?php
require_once "config.php";

header("Content-Type: application/json");

$song = $_POST["song"] ?? null;
$date = $_POST["date"] ?? date("Y-m-d");

if (!$song) {
    echo json_encode(["error" => "missing song"]);
    exit;
}

// Načtení dat
$data = [];
if (file_exists($LOCAL_DB)) {
    $data = json_decode(file_get_contents($LOCAL_DB), true);
}
if (!is_array($data)) $data = [];

$found = false;

foreach ($data as &$s) {
    if ($s["name"] === $song) {
        $s["count"]++;
        
        // 1. Aktualizace "last" (pro rychlé třídění)
        if (!isset($s["last"]) || $date > $s["last"]) {
            $s["last"] = $date;
        }

        // 2. Aktualizace "history" (pole všech dat)
        if (!isset($s["history"]) || !is_array($s["history"])) {
            $s["history"] = [];
            // Zachování starého data, pokud existovalo
            if (isset($s["last"]) && $s["last"] && $s["last"] !== $date) {
                $s["history"][] = $s["last"];
            }
        }

        // Přidat nové datum, pokud tam ještě není
        if (!in_array($date, $s["history"])) {
            $s["history"][] = $date;
            // Seřadit sestupně (nejnovější nahoře)
            rsort($s["history"]);
        }
        
        $found = true;
        break;
    }
}

if ($found) {
    // Uložit lokálně
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Spustit synchronizaci s Google Sheets (pokud je URL v configu)
    if (isset($API_URL) && $API_URL) {
        $syncUrl = $API_URL . "?action=sync_to_sheet";
        
        // Voláme asynchronně/s timeoutem, abychom nečekali na odpověď Googlu
        $ctx = stream_context_create(['http' => ['timeout' => 1]]); 
        @file_get_contents($syncUrl, false, $ctx);
    }
    
    echo json_encode(["ok" => true]);
} else {
    echo json_encode(["error" => "Song not found"]);
}
?>
