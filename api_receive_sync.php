<?php
require_once "config.php";

// Povolit přístup zvenčí
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Načíst data z POST requestu
$input = file_get_contents("php://input");

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "No data received"]);
    exit;
}

$data = json_decode($input, true);

// Validace struktury
if (!$data || !isset($data["songs"])) {
    // Pokud přišlo pole přímo (fallback)
    if (is_array($data) && isset($data[0]["name"])) {
        $songsToSave = $data;
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON format"]);
        exit;
    }
} else {
    $songsToSave = $data["songs"];
}

// Bezpečné uložení
$saved = file_put_contents($LOCAL_DB, json_encode($songsToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saved === false) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to write to file. Check permissions."]);
} else {
    echo json_encode(["ok" => true, "count" => count($songsToSave)]);
}
?>
