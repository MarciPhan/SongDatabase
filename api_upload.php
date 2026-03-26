<?php
require_once "config.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["ok" => false, "error" => "Jen POST požadavky."]);
    exit;
}

// Ochrana proti botům
$magic = $_POST['magic'] ?? "";
if ($magic !== $UPLOAD_SECRET) {
    echo json_encode(["ok" => false, "error" => "Neoprávněný přístup (chyba certifikátu)."]);
    exit;
}

// Honeypot (pokud je vyplněno pole, které má být prázdné, je to bot)
if (!empty($_POST['website'])) {
    echo json_encode(["ok" => false, "error" => "Zjištěna aktivita robota."]);
    exit;
}

$songName = $_POST['song'] ?? "";
$type = $_POST['type'] ?? ""; // "pdf" nebo "openlp"
$action = $_POST['action'] ?? "upload";

if (!$songName || !$type) {
    echo json_encode(["ok" => false, "error" => "Chybí parametry (píseň nebo typ)."]);
    exit;
}

// Společná konfigurace složek
$targetDir = "";
if ($type === "pdf") {
    $targetDir = __DIR__ . "/pdf/";
} elseif ($type === "openlp") {
    $targetDir = __DIR__ . "/openlp/";
} else {
    echo json_encode(["ok" => false, "error" => "Neplatný typ."]);
    exit;
}

// --- AKCE: SMAZAT ---
if ($action === "delete") {
    $data = [];
    if (file_exists($LOCAL_DB)) {
        $data = json_decode(file_get_contents($LOCAL_DB), true);
    }
    
    foreach ($data as &$song) {
        if ($song['name'] === $songName) {
            if (!empty($song[$type])) {
                $filePath = $targetDir . $song[$type];
                if (file_exists($filePath)) @unlink($filePath);
                $song[$type] = "";
            }
            break;
        }
    }
    
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["ok" => true]);
    exit;
}

// --- AKCE: NAHRÁT ---
$file = $_FILES['file'] ?? null;
if (!$file) {
    echo json_encode(["ok" => false, "error" => "Chybí soubor k nahrání."]);
    exit;
}

// Max 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(["ok" => false, "error" => "Soubor je příliš velký (max 10MB)."]);
    exit;
}

// Validace přípony
$allowedExts = ($type === "pdf") ? ["pdf"] : ["xml", "sng", "txt", "pdf"];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExts)) {
    echo json_encode(["ok" => false, "error" => "Nepovolená přípona: .$ext"]);
    exit;
}

// Sanitizované jméno souboru
$safeName = preg_replace('/[^a-zA-Z0-9_\-\. \x7f-\xff]/', '', $file['name']);
$targetPath = $targetDir . $safeName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $data = [];
    if (file_exists($LOCAL_DB)) {
        $data = json_decode(file_get_contents($LOCAL_DB), true);
    }
    
    $found = false;
    foreach ($data as &$song) {
        if ($song['name'] === $songName) {
            // Smažeme starý soubor, pokud existoval
            if (!empty($song[$type]) && $song[$type] !== $safeName) {
                $oldPath = $targetDir . $song[$type];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $song[$type] = $safeName;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $data[] = [
            "name" => $songName,
            "author" => "",
            "tempo" => "střední",
            "category" => "ostatní",
            "tags" => "",
            "count" => 0,
            "last" => "",
            "history" => [],
            $type => $safeName
        ];
    }
    
    file_put_contents($LOCAL_DB, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["ok" => true, "filename" => $safeName]);
} else {
    echo json_encode(["ok" => false, "error" => "Chyba při ukládání souboru."]);
}
