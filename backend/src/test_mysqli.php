<?php
header('Content-Type: application/json');
echo json_encode([
    'mysqli_loaded' => extension_loaded('mysqli'),
    'class_exists' => class_exists('mysqli'),
    'php_sapi' => php_sapi_name(),
    'php_version' => PHP_VERSION,
]);
