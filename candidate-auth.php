<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../core/MpesaService.php';
require_once __DIR__ . '/../core/SystemSettings.php';

// Initialize database connection for all handlers
$db = new Database();
$pdo = $db->connect();
$systemSettings = $pdo ? new SystemSettings($pdo) : null;
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'candidate_register':
            handleCandidateRegister();
            break;
        
        case 'candidate_login':
            handleCandidateLogin();
            break;
        
        case 'check_candidate_session':
            handleCheckCandidateSession();
            break;
        
        case 'candidate_logout':
            handleCandidateLogout();
            break;
        
        case 'initiate_mpesa_payment':
            handleInitiateMpesaPayment();
            break;
        
        case 'check_payment_status':
            handleCheckPaymentStatus();
            break;
        
        case 'forgot_password':
            handleForgotPassword();
            break;
        
        case 'verify_reset_token':
            handleVerifyResetToken();
            break;
        
        case 'reset_password':
            handleResetPassword();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

// Get JSON input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function getValidCandidateParties() {
    return [
        'Progress Party',
        'Unity Party',
        'Future Party',
        'Green Alliance',
        'Rada Party'
    ];
}

function normalizeKenyanPhone($phone) {
    $phone = trim((string) $phone);
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return $phone;
    }

    if (strlen($digits) === 12 && strpos($digits, '254') === 0) {
        return '0' . substr($digits, 3);
    }

    if (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
        return '0' . $digits;
    }

    if (strlen($digits) === 10 && $digits[0] === '0') {
        return $digits;
    }

    return $digits;
}

function formatPhoneForMpesa($phone) {
    $phone = trim((string) $phone);
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 12 && strpos($digits, '254') === 0) {
        return $digits;
    }

    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '254' . substr($digits, 1);
    }

    if (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
        return '254' . $digits;
    }

    return '';
}

function resolvePaymentPhoneForRequest($data) {
    $candidates = [
        $data['phone_number'] ?? '',
        $data['candidate_data']['payment_phone'] ?? '',
        $data['candidate_data']['phone'] ?? ''
    ];

    foreach ($candidates as $candidate) {
        $formatted = formatPhoneForMpesa($candidate);
        if ($formatted !== '') {
            return $formatted;
        }
    }

    return '';
}

function validateCandidateRegistrationData($data, $pdo, $options = []) {
    $checkDuplicates = $options['check_duplicates'] ?? true;

    if (!is_array($data)) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Invalid registration payload'
        ];
    }

    $normalized = [
        'full_name' => trim((string) ($data['full_name'] ?? '')),
        'email' => strtolower(trim((string) ($data['email'] ?? ''))),
        'password' => (string) ($data['password'] ?? ''),
        'phone' => normalizeKenyanPhone($data['phone'] ?? ''),
        'date_of_birth' => trim((string) ($data['date_of_birth'] ?? '')),
        'party' => trim((string) ($data['party'] ?? '')),
        'has_bsc_degree' => !empty($data['has_bsc_degree']),
        'good_conduct' => !empty($data['good_conduct']),
        'campaign_vision' => trim((string) ($data['campaign_vision'] ?? '')),
        'experience' => trim((string) ($data['experience'] ?? '')),
        'payment_phone' => normalizeKenyanPhone($data['payment_phone'] ?? ''),
        'payment_transaction_id' => trim((string) ($data['payment_transaction_id'] ?? ''))
    ];

    $requiredFields = [
        'full_name' => 'full_name',
        'email' => 'email',
        'password' => 'password',
        'phone' => 'phone',
        'date_of_birth' => 'date_of_birth',
        'party' => 'party',
        'campaign_vision' => 'campaign_vision',
        'experience' => 'experience'
    ];

    foreach ($requiredFields as $key => $label) {
        if ($normalized[$key] === '') {
            return [
                'valid' => false,
                'status' => 400,
                'error' => "Missing required field: $label"
            ];
        }
    }

    if (!filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Invalid email address'
        ];
    }

    try {
        $dob = new DateTime($normalized['date_of_birth']);
    } catch (Exception $e) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Invalid date of birth'
        ];
    }

    $today = new DateTime();
    $age = $today->diff($dob)->y;

    if ($age < 21) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'You must be at least 21 years old'
        ];
    }

    if (!$normalized['has_bsc_degree']) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'BSc degree is required'
        ];
    }

    if (!$normalized['good_conduct']) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Good conduct confirmation is required'
        ];
    }

    if (!in_array($normalized['party'], getValidCandidateParties(), true)) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Invalid party selection'
        ];
    }

    if ($checkDuplicates) {
        $stmt = $pdo->prepare("
            SELECT candidate_id
            FROM candidate_registrations
            WHERE LOWER(email) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$normalized['email']]);

        if ($stmt->fetchColumn()) {
            return [
                'valid' => false,
                'status' => 409,
                'error' => 'Email already registered'
            ];
        }

        $stmt = $pdo->prepare("
            SELECT candidate_id
            FROM candidate_registrations
            WHERE phone = ?
            LIMIT 1
        ");
        $stmt->execute([$normalized['phone']]);

        if ($stmt->fetchColumn()) {
            return [
                'valid' => false,
                'status' => 409,
                'error' => 'Phone number already registered as a candidate'
            ];
        }
    }

    return [
        'valid' => true,
        'data' => $normalized
    ];
}

function resolveCandidatePaymentStatus($data, $pdo) {
    if (empty($data['payment_transaction_id'])) {
        return [
            'valid' => true,
            'payment_status' => 'pending'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT transaction_id, phone_number, status
        FROM payment_requests
        WHERE transaction_id = ?
        LIMIT 1
    ");
    $stmt->execute([$data['payment_transaction_id']]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Unable to verify your payment. Please try again.'
        ];
    }

    if ($payment['status'] !== 'completed') {
        return [
            'valid' => false,
            'status' => 400,
            'error' => 'Payment has not been confirmed yet'
        ];
    }

    if (!empty($data['payment_phone'])) {
        $requestPhone = normalizeKenyanPhone($payment['phone_number'] ?? '');
        if ($requestPhone !== '' && $requestPhone !== $data['payment_phone']) {
            return [
                'valid' => false,
                'status' => 400,
                'error' => 'Payment phone number does not match the confirmed payment'
            ];
        }
    }

    return [
        'valid' => true,
        'payment_status' => 'completed'
    ];
}

/**
 * Handle candidate registration
 */
function handleCandidateRegister() {
    global $pdo, $systemSettings;
    
    $data = getJsonInput();

    if ($systemSettings && !$systemSettings->isEnabled('allow_candidate_registration', true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Candidate registration is currently disabled']);
        return;
    }

    $validation = validateCandidateRegistrationData($data, $pdo);
    if (!$validation['valid']) {
        http_response_code($validation['status']);
        echo json_encode(['success' => false, 'error' => $validation['error']]);
        return;
    }

    $data = $validation['data'];

    $paymentCheck = resolveCandidatePaymentStatus($data, $pdo);
    if (!$paymentCheck['valid']) {
        http_response_code($paymentCheck['status']);
        echo json_encode(['success' => false, 'error' => $paymentCheck['error']]);
        return;
    }
    
    // Hash password
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Generate candidate ID
    $candidateId = 'CAN-' . strtoupper(bin2hex(random_bytes(8)));
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO candidate_registrations (
                candidate_id, full_name, email, password_hash, phone, 
                date_of_birth, party, has_bsc_degree, good_conduct,
                campaign_vision, experience, registration_fee, payment_status,
                verification_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $candidateId,
            $data['full_name'],
            $data['email'],
            $passwordHash,
            $data['phone'],
            $data['date_of_birth'],
            $data['party'],
            $data['has_bsc_degree'] ? 1 : 0,
            $data['good_conduct'] ? 1 : 0,
            $data['campaign_vision'],
            $data['experience'],
            5.00, // Registration fee in KES
            $paymentCheck['payment_status'],
            'pending', // Verification status
        ]);
        
        // Log activity
        logActivity($candidateId, 'registration', 'Candidate registered', $pdo);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful. Please proceed to payment.',
            'data' => [
                'candidate_id' => $candidateId,
                'full_name' => $data['full_name'],
                'email' => $data['email']
            ]
        ]);
    } catch (PDOException $e) {
        error_log('Candidate registration error: ' . $e->getMessage());
        http_response_code(500);

        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $message = stripos($e->getMessage(), 'phone') !== false
                ? 'Phone number already registered as a candidate'
                : 'Email already registered';

            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $message]);
            return;
        }

        echo json_encode(['success' => false, 'error' => 'Registration failed']);
    }
}

/**
 * Handle candidate login
 */
function handleCandidateLogin() {
    global $pdo;
    
    $data = getJsonInput();
    
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email and password required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT candidate_id, full_name, email, password_hash, 
                   party, verification_status, payment_status
            FROM candidate_registrations 
            WHERE email = ?
        ");
        $stmt->execute([$data['email']]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            return;
        }
        
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (!password_verify($data['password'], $candidate['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            return;
        }
        
        // Check verification status
        if ($candidate['verification_status'] !== 'verified') {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'error' => 'Your candidacy is pending verification. Please wait for admin approval.'
            ]);
            return;
        }
        
        // Set session
        $_SESSION['candidate_id'] = $candidate['candidate_id'];
        $_SESSION['candidate_email'] = $candidate['email'];
        $_SESSION['candidate_name'] = $candidate['full_name'];
        $_SESSION['user_type'] = 'candidate';
        
        // Log activity
        logActivity($candidate['candidate_id'], 'login', 'Candidate logged in', $pdo);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'candidate_id' => $candidate['candidate_id'],
                'full_name' => $candidate['full_name'],
                'email' => $candidate['email'],
                'party' => $candidate['party']
            ]
        ]);
    } catch (PDOException $e) {
        error_log('Candidate login error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Login failed']);
    }
}

/**
 * Check candidate session
 */
function handleCheckCandidateSession() {
    if (empty($_SESSION['candidate_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT candidate_id, full_name, email, phone, party, 
                   campaign_vision, experience, verification_status, 
                   payment_status, created_at
            FROM candidate_registrations 
            WHERE candidate_id = ?
        ");
        $stmt->execute([$_SESSION['candidate_id']]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $candidate
        ]);
    } catch (PDOException $e) {
        error_log('Check session error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Session check failed']);
    }
}

/**
 * Handle candidate logout
 */
function handleCandidateLogout() {
    // Log activity before clearing session
    if (!empty($_SESSION['candidate_id'])) {
        logActivity($_SESSION['candidate_id'], 'logout', 'Candidate logged out', $GLOBALS['pdo']);
    }
    
    session_destroy();
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleForgotPassword() {
    global $pdo;

    $data = getJsonInput();
    $email = trim($data['email'] ?? '');

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }

    try {
        // Find candidate by email
        $findStmt = $pdo->prepare("
            SELECT candidate_id, full_name, email
            FROM candidate_registrations
            WHERE LOWER(email) = LOWER(?)
            LIMIT 1
        ");
        $findStmt->execute([$email]);

        if ($findStmt->rowCount() === 0) {
            // Return success even if email not found (security best practice)
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'If an account exists with that email, a reset link will be sent'
            ]);
            return;
        }

        $candidate = $findStmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $resetToken);
        $expiryTime = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Ensure password_reset_tokens table exists
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id VARCHAR(50),
                    token_hash VARCHAR(255),
                    expires_at DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    used_at DATETIME NULL,
                    INDEX(token_hash),
                    INDEX(candidate_id)
                )
            ");
        } catch (PDOException $e) {
            error_log('Token table creation warning: ' . $e->getMessage());
        }

        // Store token hash in database
        $storeStmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (candidate_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ");
        $storeStmt->execute([$candidate['candidate_id'], $tokenHash, $expiryTime]);

        // Send reset email
        $resetLink = app_public_url('candidate-reset-password.html') . '?token=' . rawurlencode($resetToken);
        $emailBody = "
            <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Password Reset Request</h2>
                    <p>Hi {$candidate['full_name']},</p>
                    <p>We received a request to reset your password. Click the link below to create a new password:</p>
                    <p><a href='{$resetLink}' style='background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
                    <p>Or copy this link: {$resetLink}</p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, you can safely ignore this email.</p>
                    <hr>
                    <small>BlockVote System - Candidate Portal</small>
                </body>
            </html>
        ";

        // Send email
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@blockvote.local\r\n";

        $subject = "BlockVote - Password Reset Request";
        
        if (mail($candidate['email'], $subject, $emailBody, $headers)) {
            error_log("Reset email sent to: " . $candidate['email']);
            logActivity($candidate['candidate_id'], 'forgot_password_requested', 'Password reset requested', $pdo);
        } else {
            error_log("Failed to send reset email to: " . $candidate['email']);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with that email, a reset link will be sent'
        ]);
    } catch (PDOException $e) {
        error_log('Candidate forgot password error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to process request']);
    }
}

function handleVerifyResetToken() {
    global $pdo;

    $data = getJsonInput();
    $token = trim($data['token'] ?? '');

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token is required']);
        return;
    }

    try {
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare("
            SELECT candidate_id, expires_at, used_at
            FROM password_reset_tokens
            WHERE token_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired reset token']);
            return;
        }

        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if token is expired
        if (strtotime($tokenRecord['expires_at']) < time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Reset token has expired']);
            return;
        }

        // Check if token already used
        if ($tokenRecord['used_at']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This reset token has already been used']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'candidate_id' => $tokenRecord['candidate_id'],
            'message' => 'Token is valid'
        ]);
    } catch (PDOException $e) {
        error_log('Token verification error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Verification failed']);
    }
}

function handleResetPassword() {
    global $pdo;

    $data = getJsonInput();
    $token = trim($data['token'] ?? '');
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
        return;
    }

    try {
        $tokenHash = hash('sha256', $token);

        // Verify token and get candidate
        $stmt = $pdo->prepare("
            SELECT candidate_id, expires_at, used_at
            FROM password_reset_tokens
            WHERE token_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid reset token']);
            return;
        }

        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validate token
        if (strtotime($tokenRecord['expires_at']) < time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Reset token has expired']);
            return;
        }

        if ($tokenRecord['used_at']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This reset token has already been used']);
            return;
        }

        $candidateId = $tokenRecord['candidate_id'];
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Begin transaction
        $pdo->beginTransaction();

        // Update passwords
        $updateRegistration = $pdo->prepare("
            UPDATE candidate_registrations
            SET password_hash = ?, updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $updateRegistration->execute([$passwordHash, $candidateId]);

        try {
            $updateCandidate = $pdo->prepare("
                UPDATE candidates
                SET password_hash = ?, updated_at = NOW()
                WHERE candidate_id = ?
            ");
            $updateCandidate->execute([$passwordHash, $candidateId]);
        } catch (PDOException $e) {
            error_log('Candidate password sync warning: ' . $e->getMessage());
        }

        // Mark token as used
        $markUsed = $pdo->prepare("
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE token_hash = ?
        ");
        $markUsed->execute([$tokenHash]);

        $pdo->commit();

        logActivity($candidateId, 'password_reset_completed', 'Password reset completed successfully', $pdo);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.'
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Password reset error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Password reset failed']);
    }
}

/**
 * Log activity
 */
function logActivity($candidateId, $type, $description, $pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, action, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$candidateId, $type, $description]);
    } catch (PDOException $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

/**
 * Handle M-Pesa payment initiation
 */
function handleInitiateMpesaPayment() {
    global $pdo, $systemSettings;

    $data = getJsonInput();

    $phoneNumber = resolvePaymentPhoneForRequest($data);

    if ($phoneNumber === '' || empty($data['amount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields or invalid phone number']);
        return;
    }

    if ($systemSettings && !$systemSettings->isEnabled('allow_candidate_registration', true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Candidate registration is currently disabled']);
        return;
    }

    if (!empty($data['candidate_data']) && is_array($data['candidate_data'])) {
        $data['candidate_data']['payment_phone'] = $phoneNumber;
        $validation = validateCandidateRegistrationData($data['candidate_data'], $pdo);
        if (!$validation['valid']) {
            http_response_code($validation['status']);
            echo json_encode(['success' => false, 'error' => $validation['error']]);
            return;
        }
    }

    $amount = floatval($data['amount']);
    $description = $data['description'] ?? 'Candidate Registration Fee';
    
    try {
        // Generate transaction ID
        $transactionId = 'TXN-' . time() . '-' . rand(10000, 99999);
        
        // Store payment request in database
        $stmt = $pdo->prepare("
            INSERT INTO payment_requests (transaction_id, phone_number, amount, currency, status, created_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 MINUTE))
        ");
        $stmt->execute([$transactionId, $phoneNumber, $amount, 'KES', 'pending']);
        
        // Call real M-Pesa API to send payment prompt
        $mpesa = new MpesaService();
        $stkResponse = $mpesa->stkPush(
            $phoneNumber,
            $amount,
            $transactionId,
            $description
        );
        
        // Update with M-Pesa checkout request ID
        $stmt = $pdo->prepare("
            UPDATE payment_requests 
            SET mpesa_response_code = ? 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$stkResponse['checkout_request_id'], $transactionId]);
        
        error_log("M-Pesa STK Push sent: TxnID=$transactionId, Phone=$phoneNumber, Amount=$amount KES");
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment prompt sent to your phone',
            'transaction_id' => $transactionId,
            'phone_number' => $phoneNumber,
            'amount' => $amount
        ]);
    } catch (PDOException $e) {
        error_log('Payment initiation DB error: ' . $e->getMessage());
        http_response_code(500);
        // Include DB error message for local debugging
        echo json_encode(['success' => false, 'error' => 'Failed to save payment request', 'db_error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log('M-Pesa API error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Handle payment status check
 */
function handleCheckPaymentStatus() {
    $data = getJsonInput();

    $transactionId = trim((string) ($data['transaction_id'] ?? ''));
    $phoneNumber = formatPhoneForMpesa($data['phone_number'] ?? '');

    if ($transactionId === '' && $phoneNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing transaction_id or phone_number']);
        return;
    }
    
    global $pdo;
    
    try {
        // Check payment status in database
        $sql = "SELECT * FROM payment_requests WHERE ";
        $params = [];
        
        if ($transactionId !== '') {
            $sql .= "transaction_id = ?";
            $params[] = $transactionId;
        } else {
            $sql .= "phone_number = ? ORDER BY created_at DESC LIMIT 1";
            $params[] = $phoneNumber;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'payment_status' => 'pending',
                'message' => 'Payment not yet confirmed'
            ]);
            return;
        }
        
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If payment is already completed or cancelled, return stored status
        if ($payment['status'] !== 'pending') {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'transaction_id' => $payment['transaction_id'],
                'phone_number' => $payment['phone_number'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'payment_status' => $payment['status'],
                'message' => ucfirst($payment['status'])
            ]);
            return;
        }
        
        // If payment is pending and has M-Pesa checkout ID, query M-Pesa API
        if (!empty($payment['mpesa_response_code'])) {
            try {
                $mpesa = new MpesaService();
                $queryResponse = $mpesa->querySTKPushStatus($payment['mpesa_response_code']);
                
                if ($queryResponse['success']) {
                    $status = $queryResponse['status'];
                    
                    // Update database with latest status from M-Pesa
                    if ($status !== 'pending') {
                        $updateStmt = $pdo->prepare("
                            UPDATE payment_requests 
                            SET status = ? 
                            WHERE transaction_id = ?
                        ");
                        $updateStmt->execute([$status, $payment['transaction_id']]);
                        
                        error_log("Payment status updated: TxnID={$payment['transaction_id']}, Status=$status");
                        
                        // Auto-complete registration if payment is confirmed
                        if ($status === 'completed') {
                            autoCompleteCandidateRegistration($payment['phone_number'], $pdo);
                        }
                    }
                    
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'transaction_id' => $payment['transaction_id'],
                        'phone_number' => $payment['phone_number'],
                        'amount' => $payment['amount'],
                        'currency' => $payment['currency'],
                        'payment_status' => $status,
                        'message' => $queryResponse['result_description'] ?? ucfirst($status)
                    ]);
                } else {
                    // Query failed, but return pending status
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'transaction_id' => $payment['transaction_id'],
                        'phone_number' => $payment['phone_number'],
                        'amount' => $payment['amount'],
                        'currency' => $payment['currency'],
                        'payment_status' => 'pending',
                        'message' => 'Waiting for payment confirmation'
                    ]);
                }
            } catch (Exception $e) {
                error_log("M-Pesa Query Error: " . $e->getMessage());
                // Return pending status if query fails
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'transaction_id' => $payment['transaction_id'],
                    'phone_number' => $payment['phone_number'],
                    'amount' => $payment['amount'],
                    'currency' => $payment['currency'],
                    'payment_status' => 'pending',
                    'message' => 'Waiting for payment confirmation'
                ]);
            }
        } else {
            // No M-Pesa response yet, return pending
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'transaction_id' => $payment['transaction_id'],
                'phone_number' => $payment['phone_number'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'payment_status' => $payment['status'],
                'created_at' => $payment['created_at']
            ]);
        }
    } catch (PDOException $e) {
        error_log('Payment status check error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to check payment status']);
    }
}

/**
 * Auto-complete candidate registration after payment confirmation
 */
function autoCompleteCandidateRegistration($phoneNumber, $pdo) {
    try {
        $normalizedPhone = normalizeKenyanPhone($phoneNumber);

        // Find candidate with pending payment status
        $stmt = $pdo->prepare("
            SELECT candidate_id, full_name, email, phone 
            FROM candidate_registrations 
            WHERE phone = ? AND payment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$normalizedPhone !== '' ? $normalizedPhone : $phoneNumber]);
        
        if ($stmt->rowCount() === 0) {
            error_log("No pending candidate found for auto-completion: $phoneNumber");
            return;
        }
        
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update payment status to completed in registrations
        $updateStmt = $pdo->prepare("
            UPDATE candidate_registrations 
            SET payment_status = 'completed', updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $updateStmt->execute([$candidate['candidate_id']]);

        // Insert into canonical candidates table so admins can manage/verify
        try {
            // Check if already exists in candidates table
            $check = $pdo->prepare("SELECT candidate_id FROM candidates WHERE candidate_id = ? LIMIT 1");
            $check->execute([$candidate['candidate_id']]);

            if ($check->rowCount() === 0) {
                // Get full registration row to populate candidates table
                $fullStmt = $pdo->prepare("SELECT * FROM candidate_registrations WHERE candidate_id = ? LIMIT 1");
                $fullStmt->execute([$candidate['candidate_id']]);
                $full = $fullStmt->fetch(PDO::FETCH_ASSOC);

                $insert = $pdo->prepare("
                    INSERT INTO candidates (candidate_id, full_name, email, password_hash, phone, date_of_birth, party, has_bsc_degree, good_conduct, campaign_vision, experience, registration_fee, payment_status, verification_status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $insert->execute([
                    $full['candidate_id'],
                    $full['full_name'],
                    $full['email'],
                    $full['password_hash'] ?? null,
                    $full['phone'] ?? null,
                    $full['date_of_birth'] ?? null,
                    $full['party'] ?? null,
                    isset($full['has_bsc_degree']) ? (int)$full['has_bsc_degree'] : 0,
                    isset($full['good_conduct']) ? (int)$full['good_conduct'] : 0,
                    $full['campaign_vision'] ?? null,
                    $full['experience'] ?? null,
                    $full['registration_fee'] ?? 5.00,
                    'completed', // payment_status
                    $full['verification_status'] ?? 'pending'
                ]);
            }
        } catch (PDOException $e) {
            error_log('Insert into candidates table failed: ' . $e->getMessage());
        }

        // Log activity
        $logStmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, action, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $candidate['candidate_id'],
            'PAYMENT_CONFIRMED',
            'Registration auto-completed after payment confirmation',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        error_log("Candidate registration auto-completed and inserted: ID={$candidate['candidate_id']}, Phone=$phoneNumber");
    } catch (PDOException $e) {
        error_log("Auto-completion error: " . $e->getMessage());
    }
}
?>


