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
    $stmt = $pdo->prepare("
        SELECT light_enabled, heavy_enabled, override_manual, threshold_exceeded
        FROM user_load_settings
        WHERE user_id = :uid
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode([
            'ok' => true,
            'light_on' => 1,
            'heavy_on' => 1,
            'override_manual' => 0,
            'threshold_exceeded' => 0,
            'rate_per_kwh' => 14.50,
            'threshold' => 0
        ]);
        exit;
    }

    $lightOn = (int)$row['light_enabled'];
    $heavyOn = (int)$row['heavy_enabled'];
    $overrideManual = (int)$row['override_manual'];
    $thresholdExceeded = (int)$row['threshold_exceeded'];

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
