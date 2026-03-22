<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$DB_HOST = "YOUR_DB_HOST";
$DB_NAME = "YOUR_DB_NAME";
$DB_USER = "YOUR_DB_USER";
$DB_PASS = "YOUR_DB_PASSWORD";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "DB connection failed",
        "details" => $e->getMessage()
    ]);
    exit;
}

function json_out($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}
?>
