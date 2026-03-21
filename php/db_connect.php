<?php
$host     = "dpg-d6vigrhr0fns73cad3q0-a";
$port     = "5432";
$dbname   = "smart_power";
$username = "smartpower_user";
$password = "EyLoWqbVYTnDxndaRmsbHispohU0XksG";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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
