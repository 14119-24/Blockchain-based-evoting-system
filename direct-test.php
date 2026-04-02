<?php
// Direct test without going through HTTP

// Simulate the POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'register';

$testData = [
    'full_name' => 'Direct Test',
    'email' => 'direct' . time() . '@test.com',
    'national_id' => 'DIRECT' . rand(100000, 999999),
    'password' => 'TestPass123!',
    'confirm_password' => 'TestPass123!',
    'phone' => '+1234567890',
    'dob' => '1990-01-15',
    'address' => 'Test',
    'city' => 'Test',
    'state' => '',
    'zip' => ''
];

// Set content-type header
header('Content-Type: application/json');

// Mock the input stream
$GLOBALS['_REQUEST_BODY'] = json_encode($testData);

// Override file_get_contents for php://input
if (!function_exists('mock_file_get_contents')) {
    function mock_file_get_contents($filename) {
        if ($filename === 'php://input') {
            return $GLOBALS['_REQUEST_BODY'];
        }
        return file_get_contents($filename);
    }
}

// Include and run the auth handler
ob_start();
require_once(__DIR__ . '/../api/auth.php');
$output = ob_get_clean();

echo $output;
?>
