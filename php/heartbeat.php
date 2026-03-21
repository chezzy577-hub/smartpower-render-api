<?php
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Heartbeat endpoint is reachable',
    'timestamp' => date('Y-m-d H:i:s')
]);
