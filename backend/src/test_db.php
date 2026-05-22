<?php
require_once __DIR__ . '/util/Database.php';
require_once __DIR__ . '/config.php';

try {
    $db = new Database();
    $conn = $db->connect();
    echo json_encode([
        'success' => true,
        'message' => 'Connected',
        'mysqli_loaded' => function_exists('mysqli_connect'),
        'connection_type' => gettype($conn)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
