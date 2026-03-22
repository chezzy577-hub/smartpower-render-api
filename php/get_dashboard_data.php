<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 2;

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL UNIQUE,
            rate_per_kwh NUMERIC(10,2) DEFAULT 14.50,
            monthly_threshold NUMERIC(12,3) DEFAULT 0
        )
    ");

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

    $pdo->prepare("
        INSERT INTO user_settings (user_id, rate_per_kwh, monthly_threshold)
        VALUES (:uid, 14.50, 100)
        ON CONFLICT (user_id) DO NOTHING
    ")->execute(['uid' => $userId]);

    $pdo->prepare("
        INSERT INTO user_load_settings (user_id, light_enabled, heavy_enabled, override_manual, threshold_exceeded)
        VALUES (:uid, 1, 1, 0, 0)
        ON CONFLICT (user_id) DO NOTHING
    ")->execute(['uid' => $userId]);

    $readingStmt = $pdo->prepare("
        SELECT voltage, current, light_current, heavy_current, kwh, light_on, heavy_on, created_at
        FROM readings
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $readingStmt->execute(['uid' => $userId]);
    $reading = $readingStmt->fetch();

    $settingsStmt = $pdo->prepare("
        SELECT rate_per_kwh, monthly_threshold
        FROM user_settings
        WHERE user_id = :uid
        LIMIT 1
    ");
    $settingsStmt->execute(['uid' => $userId]);
    $settings = $settingsStmt->fetch();

    $loadStmt = $pdo->prepare("
        SELECT light_enabled, heavy_enabled, override_manual, threshold_exceeded
        FROM user_load_settings
        WHERE user_id = :uid
        LIMIT 1
    ");
    $loadStmt->execute(['uid' => $userId]);
    $load = $loadStmt->fetch();

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'voltage' => (float)($reading['voltage'] ?? 0),
        'current' => (float)($reading['current'] ?? 0),
        'light_current' => (float)($reading['light_current'] ?? 0),
        'heavy_current' => (float)($reading['heavy_current'] ?? 0),
        'kwh' => (float)($reading['kwh'] ?? 0),
        'light_on' => (int)($load['light_enabled'] ?? 1),
        'heavy_on' => (int)($load['heavy_enabled'] ?? 1),
        'rate_per_kwh' => (float)($settings['rate_per_kwh'] ?? 14.50),
        'monthly_threshold' => (float)($settings['monthly_threshold'] ?? 100),
        'updated_at' => $reading['created_at'] ?? null
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
