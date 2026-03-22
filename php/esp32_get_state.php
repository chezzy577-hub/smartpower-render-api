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

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Missing user_id']);
    exit;
}

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

    $stmt = $pdo->prepare("
        SELECT light_enabled, heavy_enabled, override_manual, threshold_exceeded
        FROM user_load_settings
        WHERE user_id = :uid
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();

    $lightOn = (int)($row['light_enabled'] ?? 1);
    $heavyOn = (int)($row['heavy_enabled'] ?? 1);
    $overrideManual = (int)($row['override_manual'] ?? 0);
    $thresholdExceeded = (int)($row['threshold_exceeded'] ?? 0);

    if ($overrideManual === 0 && $thresholdExceeded === 1) {
        $lightOn = 1;
        $heavyOn = 0;
    }

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
        'light_on'           => $lightOn,
        'heavy_on'           => $heavyOn,
        'override_manual'    => $overrideManual,
        'threshold_exceeded' => $thresholdExceeded,
        'rate_per_kwh'       => $rateKwh,
        'threshold'          => $threshold
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
