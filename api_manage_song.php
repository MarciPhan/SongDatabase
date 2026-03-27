<?php
require_once "config.php";

header("Content-Type: application/json");

// Zjistíme, o jakou akci jde (add / edit / delete)
$action = $_POST["action"] ?? "add";
$originalName = $_POST["original_name"] ?? ""; // Potřebné pro editaci/mazání (staré jméno)

// Data z formuláře
$name = trim($_POST["name"] ?? "");
$author = trim($_POST["author"] ?? "");
$category = $_POST["category"] ?? "";
$tempo = $_POST["tempo"] ?? "";
$tags = trim($_POST["tags"] ?? "");

// Načtení existujících dat
$data = [];
if (file_exists($LOCAL_DB)) {
    $data = json_decode(file_get_contents($LOCAL_DB), true);
}
if (!is_array($data)) $data = [];

// ==========================================
// AKCE: PŘEPSÁNÍ VŠEHO (OVERWRITE ALL - Pro funkci Zpět)
// ==========================================
if ($action === "overwrite_all") {
    $newDataJson = $_POST["data"] ?? "";
    $decoded = json_decode($newDataJson, true);
    if (!is_array($decoded)) {
        echo json_encode(["error" => "Neplatná data pro přepsání."]);
        exit;
    }
    saveAndSync($decoded);
    exit;
}

// ==========================================
// AKCE: HROMADNÉ MAZÁNÍ (BULK DELETE)
// ==========================================
if ($action === "bulk_delete") {
    $names = $_POST["names"] ?? []; // Pole názvů
    if (empty($names)) {
        echo json_encode(["error" => "Chybí názvy písní ke smazání."]);
        exit;
    }

    $newData = [];
    foreach ($data as $song) {
        if (!in_array($song["name"], $names)) {
            $newData[] = $song;
        }
    }

    saveAndSync($newData);
    exit;
}

// ==========================================
// AKCE: HROMADNÁ AKTUALIZACE (BULK UPDATE)
// ==========================================
if ($action === "bulk_update") {
    $names = $_POST["names"] ?? [];
    if (empty($names)) {
        echo json_encode(["error" => "Chybí názvy písní k úpravě."]);
        exit;
    }

    foreach ($data as &$song) {
        if (in_array($song["name"], $names)) {
            if (isset($_POST["category"])) $song["category"] = $_POST["category"];
            if (isset($_POST["tempo"])) $song["tempo"] = $_POST["tempo"];
            if (isset($_POST["author"])) $song["author"] = $_POST["author"];
            if (isset($_POST["tags"])) $song["tags"] = $_POST["tags"];
        }
    }

    saveAndSync($data);
    exit;
}

// ==========================================
// AKCE: HROMADNÝ ZÁPIS HRANÍ (BULK PLAY)
// ==========================================
if ($action === "bulk_add_history") {
    $names = $_POST["names"] ?? [];
    $date = $_POST["date"] ?? date("Y-m-d");

    if (empty($names)) {
        echo json_encode(["error" => "Chybí názvy písní k zápisu."]);
        exit;
    }

    foreach ($data as &$song) {
        if (in_array($song["name"], $names)) {
            if (!isset($song["history"]) || !is_array($song["history"])) {
                $song["history"] = [];
                if (!empty($song["last"])) $song["history"][] = $song["last"];
            }

            if (!in_array($date, $song["history"])) {
                $song["history"][] = $date;
                rsort($song["history"]);
                $song["last"] = $song["history"][0];
                $song["count"] = count($song["history"]);
            }
        }
    }

    saveAndSync($data);
    exit;
}

// ==========================================
// AKCE: HROMADNÉ ODEBRÁNÍ DATA (BULK REMOVE DATE)
// ==========================================
if ($action === "bulk_remove_history") {
    $names = $_POST["names"] ?? [];
    $date = $_POST["date"] ?? "";

    if (empty($names) || empty($date)) {
        echo json_encode(["error" => "Chybí názvy písní nebo datum k odebrání."]);
        exit;
    }

    foreach ($data as &$song) {
        if (in_array($song["name"], $names) && isset($song["history"])) {
            $song["history"] = array_values(array_filter($song["history"], function($d) use ($date) {
                return $d !== $date;
            }));
            rsort($song["history"]);
            $song["last"] = !empty($song["history"]) ? $song["history"][0] : "";
            $song["count"] = count($song["history"]);
        }
    }

    saveAndSync($data);
    exit;
}

// ==========================================
// AKCE: HROMADNÁ ZMĚNA DATA (BULK REPLACE DATE)
// ==========================================
if ($action === "bulk_replace_history") {
    $names = $_POST["names"] ?? [];
    $oldDate = $_POST["old_date"] ?? "";
    $newDate = $_POST["new_date"] ?? "";

    if (empty($names) || empty($oldDate) || empty($newDate)) {
        echo json_encode(["error" => "Chybí názvy nebo data k nahrazení."]);
        exit;
    }

    foreach ($data as &$song) {
        if (in_array($song["name"], $names) && isset($song["history"])) {
            $found = false;
            foreach ($song["history"] as &$d) {
                if ($d === $oldDate) {
                    $d = $newDate;
                    $found = true;
                }
            }
            if ($found) {
                $song["history"] = array_values(array_unique($song["history"]));
                rsort($song["history"]);
                $song["last"] = !empty($song["history"]) ? $song["history"][0] : "";
                $song["count"] = count($song["history"]);
            }
        }
    }

    saveAndSync($data);
    exit;
}

// ==========================================
// AKCE: MAZÁNÍ (DELETE)
// ==========================================
if ($action === "delete") {
    if (empty($originalName)) {
        echo json_encode(["error" => "Chybí název písně ke smazání."]);
        exit;
    }

    $newData = [];
    $found = false;
    foreach ($data as $song) {
        if ($song["name"] === $originalName) {
            $found = true; // Tuto píseň přeskočíme (tím se smaže)
        } else {
            $newData[] = $song;
        }
    }

    if (!$found) {
        echo json_encode(["error" => "Píseň nebyla nalezena."]);
        exit;
    }

    saveAndSync($newData);
    exit;
}

// ==========================================
// VALIDACE PRO ADD/EDIT
// ==========================================
if (empty($name)) {
    echo json_encode(["error" => "Název písně je povinný!"]);
    exit;
}

// ==========================================
// AKCE: PŘIDÁNÍ (ADD)
// ==========================================
if ($action === "add") {
    // Kontrola duplicit
    foreach ($data as $song) {
        if (strcasecmp($song["name"], $name) === 0) {
            echo json_encode(["error" => "Tato píseň už existuje."]);
            exit;
        }
    }

    $newSong = [
        "name" => $name,
        "author" => $author,
        "tempo" => $tempo,
        "category" => $category,
        "tags" => $tags,
        "count" => 0,
        "last" => "",
        "history" => []
    ];

    $data[] = $newSong;
    saveAndSync($data);
    exit;
}

// ==========================================
// AKCE: ÚPRAVA (EDIT)
// ==========================================
if ($action === "edit") {
    if (empty($originalName)) {
        echo json_encode(["error" => "Chybí původní název písně."]);
        exit;
    }

    $found = false;
    foreach ($data as &$song) {
        if ($song["name"] === $originalName) {
            // Aktualizujeme data
            $song["name"] = $name; // Může se změnit i název
            $song["author"] = $author;
            $song["tempo"] = $tempo;
            $song["category"] = $category;
            $song["tags"] = $tags;
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo json_encode(["error" => "Píseň k úpravě nebyla nalezena."]);
        exit;
    }

    saveAndSync($data);
    exit;
}

// ==========================================
// POMOCNÁ FUNKCE PRO ULOŽENÍ A SYNC
// ==========================================
function saveAndSync($data) {
    global $LOCAL_DB, $API_URL;

    // Seřadit
    usort($data, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    if (file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX)) {
        // Trigger Sync
        if (isset($API_URL) && $API_URL) {
            $syncUrl = $API_URL . "?action=sync_to_sheet";
            $ctx = stream_context_create(['http' => ['timeout' => 1]]); 
            @file_get_contents($syncUrl, false, $ctx);
        }
        echo json_encode(["ok" => true]);
    } else {
        echo json_encode(["error" => "Chyba zápisu do souboru."]);
    }
}
?>