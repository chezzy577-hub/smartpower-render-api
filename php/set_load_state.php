<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$userId = isset($data['user_id']) ? (int)$data['user_id'] : 2;
$light  = isset($data['light_on']) ? (int)$data['light_on'] : 1;
$heavy  = isset($data['heavy_on']) ? (int)$data['heavy_on'] : 1;
$manual = isset($data['override_manual']) ? (int)$data['override_manual'] : 1;

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_load_settings (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL UNIQUE,
            light_enabled INTEGER DEFAULT 1,
            heavy_enabled INTEGER DEFAULT 1,
            override_manual INTEGER DEFAULT 0,
            threshold_exceeded INTEGER DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO user_load_settings
            (user_id, light_enabled, heavy_enabled, override_manual, threshold_exceeded, updated_at)
        VALUES
            (:uid, :light, :heavy, :manual, 0, NOW())
        ON CONFLICT (user_id)
        DO UPDATE SET
            light_enabled = EXCLUDED.light_enabled,
            heavy_enabled = EXCLUDED.heavy_enabled,
            override_manual = EXCLUDED.override_manual,
            updated_at = NOW()
    ");
    $stmt->execute([
        'uid' => $userId,
        'light' => $light,
        'heavy' => $heavy,
        'manual' => $manual
    ]);

    echo json_encode([
        'ok' => true,
        'light_on' => $light,
        'heavy_on' => $heavy,
        'override_manual' => $manual
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
