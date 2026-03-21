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

$mac = strtoupper(trim($_GET['mac'] ?? ''));
if (empty($mac)) {
    echo json_encode(['ok' => false, 'message' => 'Missing mac']);
    exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS esp32_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mac_address VARCHAR(20) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            device_name VARCHAR(80) DEFAULT 'SmartPower ESP32',
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("
        SELECT user_id, device_name
        FROM esp32_devices
        WHERE mac_address = :mac
        LIMIT 1
    ");
    $stmt->execute(['mac' => $mac]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'ok'          => true,
            'user_id'     => (int)$row['user_id'],
            'device_name' => $row['device_name'],
            'registered'  => true
        ]);
    } else {
        $userId = 2;

        $pdo->prepare("
            INSERT INTO esp32_devices (mac_address, user_id, device_name)
            VALUES (:mac, :uid, 'SmartPower ESP32')
        ")->execute([
            'mac' => $mac,
            'uid' => $userId
        ]);

        echo json_encode([
            'ok'          => true,
            'user_id'     => $userId,
            'device_name' => 'SmartPower ESP32',
            'registered'  => false
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
