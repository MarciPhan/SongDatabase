<?php
require_once "config.php";
header("Content-Type: application/json");

// Načtení parametrů a ořezání mezer
$songName = trim($_POST["song"] ?? "");
$action = $_POST["action"] ?? ""; // 'add' nebo 'remove' nebo 'update'
$date = trim($_POST["date"] ?? "");

if (empty($songName) || empty($action) || empty($date)) {
    echo json_encode(["error" => "Chybí parametry (song, action, date)."]);
    exit;
}

// Načtení dat
$data = [];
if (file_exists($LOCAL_DB)) {
    $data = json_decode(file_get_contents($LOCAL_DB), true);
}
if (!is_array($data)) $data = [];

$found = false;
$updatedSong = null;

foreach ($data as &$s) {
    if ($s["name"] === $songName) {
        // Inicializace pole, pokud chybí
        if (!isset($s["history"]) || !is_array($s["history"])) $s["history"] = [];
        
        // Pokud existuje pole last, ale historie je prázdná, opravíme to (synchronizace)
        if (empty($s["history"]) && !empty($s["last"])) {
            $s["history"][] = $s["last"];
        }

        if ($action === "add") {
            // Přidat, pokud tam toto datum přesně není
            if (!in_array($date, $s["history"])) {
                $s["history"][] = $date;
            }
        } elseif ($action === "remove") {
            // Odebrat - použití trim pro jistotu porovnání
            $s["history"] = array_values(array_filter($s["history"], fn($d) => trim($d) !== $date));
        } elseif ($action === "update") {
             // Update (smazat staré, přidat nové)
             $oldDate = trim($_POST["old_date"] ?? "");
             if($oldDate) {
                 $s["history"] = array_values(array_filter($s["history"], fn($d) => trim($d) !== $oldDate));
             }
             // Přidat nové, pokud tam není
             if (!in_array($date, $s["history"])) $s["history"][] = $date;
        }

        // --- DŮLEŽITÉ: ÚKLID A PŘEPOČET ---
        
        // 1. Seřadit sestupně (nejnovější nahoře)
        rsort($s["history"]);
        
        // 2. Vynutit přepočet count podle reálné délky historie
        $s["count"] = count($s["history"]);
        
        // 3. Aktualizovat 'last' podle prvního prvku (nebo vymazat, pokud je historie prázdná)
        $s["last"] = $s["history"][0] ?? "";

        $found = true;
        $updatedSong = $s;
        break;
    }
}

if ($found) {
    // Uložení do souboru
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Sync s Google Sheets (volitelné, neblokující)
    if (isset($API_URL) && $API_URL) {
        $ctx = stream_context_create(['http' => ['timeout' => 1]]); 
        @file_get_contents($API_URL . "?action=sync_to_sheet", false, $ctx);
    }
    
    echo json_encode(["ok" => true, "song" => $updatedSong]);
} else {
    echo json_encode(["error" => "Píseň nenalezena."]);
}
?>
