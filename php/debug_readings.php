<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 2;
$limit  = isset($_GET['limit']) ? max(1, min(20, (int)$_GET['limit'])) : 10;

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS readings (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            voltage NUMERIC(10,2) DEFAULT 0,
            current NUMERIC(10,2) DEFAULT 0,
            light_current NUMERIC(10,2) DEFAULT 0,
            heavy_current NUMERIC(10,2) DEFAULT 0,
            kwh NUMERIC(12,6) DEFAULT 0,
            light_on INTEGER DEFAULT 1,
            heavy_on INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare("
        SELECT id, user_id, voltage, current, light_current, heavy_current, kwh, light_on, heavy_on, created_at
        FROM readings
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    $stmt->execute(['uid' => $userId]);

    echo json_encode([
        'ok' => true,
        'rows' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
