<?php
require_once "config.php";
header("Content-Type: application/json; charset=utf-8");

$checks = [];

// 1. Cesta k DB
$checks["db_path"] = $LOCAL_DB;
$checks["db_realpath"] = realpath($LOCAL_DB) ?: "NEEXISTUJE";

// 2. Existuje soubor?
$checks["db_exists"] = file_exists($LOCAL_DB);

// 3. Je čitelný?
$checks["db_readable"] = is_readable($LOCAL_DB);

// 4. Je zapisovatelný?
$checks["db_writable"] = is_writable($LOCAL_DB);

// 5. Je adresář zapisovatelný?
$dir = dirname($LOCAL_DB);
$checks["dir_path"] = $dir;
$checks["dir_writable"] = is_writable($dir);

// 6. Uživatel webserveru
$checks["php_user"] = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user();

// 7. Oprávnění souboru
if (file_exists($LOCAL_DB)) {
    $checks["db_perms"] = substr(sprintf('%o', fileperms($LOCAL_DB)), -4);
    $checks["db_owner"] = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($LOCAL_DB))['name'] : fileowner($LOCAL_DB);
    $checks["db_size"] = filesize($LOCAL_DB) . " bytes";
}

// 8. Test zápisu
$testFile = $dir . "/_write_test_" . time() . ".tmp";
$writeResult = @file_put_contents($testFile, "test");
if ($writeResult !== false) {
    $checks["write_test"] = "OK - zápis funguje";
    @unlink($testFile);
} else {
    $checks["write_test"] = "SELHALO - nelze zapisovat do adresáře!";
    $checks["last_error"] = error_get_last();
}

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
