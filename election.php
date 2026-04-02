<?php
// api/election.php
// Dedicated API endpoint for election operations by admin

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Cryptography.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/AdminAuth.php';
require_once __DIR__ . '/admin.php';

session_start();

$db = (new Database())->connect();
AdminAuth::ensureDefaultAdminAccount($db);

if (!AdminAuth::isSessionAdmin($db)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = [];
}

$adminApi = new AdminAPI();

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendError('Method not allowed', 405);
        }
        echo $adminApi->createElection($data);
        break;
    case 'list':
        // A simple election list endpoint (wrapped existing admin get_elections)
        echo $adminApi->getElections($data);
        break;
    default:
        sendError('Invalid action', 400);
}
