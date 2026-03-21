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
    $thresholdExceeded = 0;
    $finalLight = $lightOn;
    $finalHeavy = $heavyOn;

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
        'threshold_exceeded' => $thresholdExceeded,
        'light_on'           => $finalLight,
        'heavy_on'           => $finalHeavy,
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
