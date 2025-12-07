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
        
        // Inicializace historie pokud chybí
        if (!isset($s["history"]) || !is_array($s["history"])) {
            $s["history"] = [];
            if (isset($s["last"]) && $s["last"] && $s["last"] !== $date) {
                $s["history"][] = $s["last"];
            }
        }

        // --- BUGFIX: KONTROLA DUPLICITY ---
        // Pokud už historie obsahuje toto datum, zastavíme akci.
        if (in_array($date, $s["history"])) {
            echo json_encode(["error" => "Tato píseň už byla dnes zapsána!"]);
            exit;
        }

        // Pokud není duplicita, přidáme datum
        $s["history"][] = $date;
        
        // Seřadit sestupně (nejnovější nahoře)
        rsort($s["history"]);

        // Aktualizace "last" podle prvního v historii
        $s["last"] = $s["history"][0];

        // --- BUGFIX: COUNT ---
        // Počet se vždy rovná počtu záznamů v historii. 
        // Tím se zabrání "rozjetí" počítadla a historie.
        $s["count"] = count($s["history"]);
        
        $found = true;
        break;
    }
}

if ($found) {
    // Uložit lokálně
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Spustit synchronizaci s Google Sheets
    if (isset($API_URL) && $API_URL) {
        $syncUrl = $API_URL . "?action=sync_to_sheet";
        $ctx = stream_context_create(['http' => ['timeout' => 1]]); 
        @file_get_contents($syncUrl, false, $ctx);
    }
    
    echo json_encode(["ok" => true]);
} else {
    echo json_encode(["error" => "Song not found"]);
}
?>
