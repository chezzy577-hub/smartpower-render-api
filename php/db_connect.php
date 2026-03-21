<?php
$host     = "sql106.infinityfree.com";
$dbname   = "if0_40551884_smart_power";
$username = "if0_40551884";
$password = "smarthomethesis";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode([
        "ok" => false,
        "message" => "DB connection failed",
        "error" => $e->getMessage()
    ]);
    exit;
}
