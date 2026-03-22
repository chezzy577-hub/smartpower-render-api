<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
if (!hash_equals(API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$userId       = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$voltage      = isset($data['voltage']) ? (float)$data['voltage'] : 0;
$lightCurrent = isset($data['light_current']) ? (float)$data['light_current'] : 0;
$heavyCurrent = isset($data['heavy_current']) ? (float)$data['heavy_current'] : 0;
$deltaKwh     = isset($data['kwh']) ? (float)$data['kwh'] : 0;
$lightOn      = isset($data['light_on']) ? (int)$data['light_on'] : 1;
$heavyOn      = isset($data['heavy_on']) ? (int)$data['heavy_on'] : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing user_id']);
    exit;
}

$currentTotal   = $lightCurrent + $heavyCurrent;
$currentPowerKw = ($voltage * $currentTotal) / 1000.0;

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL UNIQUE,
            rate_per_kwh NUMERIC(10,2) DEFAULT 14.50,
            monthly_threshold NUMERIC(12,3) DEFAULT 0
        )
    ");

    $pdo->prepare("
        INSERT INTO user_settings (user_id, rate_per_kwh, monthly_threshold)
        VALUES (:uid, 14.50, 100)
        ON CONFLICT (user_id) DO NOTHING
    ")->execute(['uid' => $userId]);

    $stmt = $pdo->prepare("
        INSERT INTO readings
        (user_id, voltage, current, light_current, heavy_current, kwh, light_on, heavy_on, created_at)
        VALUES
        (:user_id, :voltage, :current, :light_current, :heavy_current, :kwh, :light_on, :heavy_on, NOW())
    ");
    $stmt->execute([
        'user_id'       => $userId,
        'voltage'       => $voltage,
        'current'       => $currentTotal,
        'light_current' => $lightCurrent,
        'heavy_current' => $heavyCurrent,
        'kwh'           => $deltaKwh,
        'light_on'      => $lightOn,
        'heavy_on'      => $heavyOn
    ]);

    $rateKwh = 14.50;
    $threshold = 0.0;

    $rs = $pdo->prepare("
        SELECT rate_per_kwh, monthly_threshold
        FROM user_settings
        WHERE user_id = :uid
        LIMIT 1
    ");
    $rs->execute(['uid' => $userId]);
    $rsRow = $rs->fetch();

    if ($rsRow) {
        $rateKwh = (float)$rsRow['rate_per_kwh'];
        $threshold = (float)$rsRow['monthly_threshold'];
    }

    echo json_encode([
        'ok'                 => true,
        'message'            => 'Saved',
        'user_id'            => $userId,
        'voltage'            => $voltage,
        'light_current'      => $lightCurrent,
        'heavy_current'      => $heavyCurrent,
        'current_power'      => round($currentPowerKw, 4),
        'delta_kwh'          => $deltaKwh,
        'threshold_exceeded' => 0,
        'light_on'           => $lightOn,
        'heavy_on'           => $heavyOn,
        'rate_per_kwh'       => $rateKwh,
        'threshold'          => $threshold
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Server error',
        'error'   => $e->getMessage()
    ]);
}
