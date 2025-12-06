<?php
require_once "config.php";
header("Content-Type: application/json");

// Načtení parametrů
$songName = $_POST["song"] ?? "";
$action = $_POST["action"] ?? ""; // 'add' nebo 'remove'
$date = $_POST["date"] ?? "";

if (empty($songName) || empty($action) || empty($date)) {
    echo json_encode(["error" => "Chybí parametry (song, action, date)."]);
    exit;
}

// Načtení dat
$data = [];
if (file_exists($LOCAL_DB)) {
    $data = json_decode(file_get_contents($LOCAL_DB), true);
}

$found = false;
$updatedSong = null;

foreach ($data as &$s) {
    if ($s["name"] === $songName) {
        // Inicializace pole, pokud chybí
        if (!isset($s["history"]) || !is_array($s["history"])) $s["history"] = [];

        if ($action === "add") {
            // Přidat, pokud neexistuje
            if (!in_array($date, $s["history"])) {
                $s["history"][] = $date;
            }
        } elseif ($action === "remove") {
            // Odebrat
            $s["history"] = array_values(array_filter($s["history"], fn($d) => $d !== $date));
        } elseif ($action === "update") {
             // Update (smazat staré, přidat nové)
             $oldDate = $_POST["old_date"] ?? "";
             if($oldDate) {
                 $s["history"] = array_values(array_filter($s["history"], fn($d) => $d !== $oldDate));
             }
             if (!in_array($date, $s["history"])) $s["history"][] = $date;
        }

        // Seřadit sestupně a přepočítat
        rsort($s["history"]);
        $s["count"] = count($s["history"]);
        $s["last"] = $s["history"][0] ?? "";

        $found = true;
        $updatedSong = $s;
        break;
    }
}

if ($found) {
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Sync s Google Sheets
    if (isset($API_URL) && $API_URL) {
        $ctx = stream_context_create(['http' => ['timeout' => 1]]); 
        @file_get_contents($API_URL . "?action=sync_to_sheet", false, $ctx);
    }
    
    echo json_encode(["ok" => true, "song" => $updatedSong]);
} else {
    echo json_encode(["error" => "Píseň nenalezena."]);
}
?>
