<?php
// Test that login endpoint is working
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'none';
$method = $_SERVER['REQUEST_METHOD'];

echo json_encode([
    'action' => $action,
    'method' => $method,
    'status' => 'test_received'
]);
?>
