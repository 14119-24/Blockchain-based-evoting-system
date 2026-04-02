<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/AdminAuth.php';

AdminAuth::ensureSessionStarted();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db = new Database();
$pdo = $db->connect();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

AdminAuth::ensureDefaultAdminAccount($pdo);

// Check admin authentication
if (!AdminAuth::isSessionAdmin($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_candidates':
            handleGetCandidates();
            break;
        
        case 'get_candidate_details':
            handleGetCandidateDetails();
            break;
        
        case 'verify_candidate':
            handleVerifyCandidate();
            break;
        
        case 'reject_candidate':
            handleRejectCandidate();
            break;
        
        case 'process_payment':
            handleProcessPayment();
            break;
        
        case 'get_candidates_stats':
            handleGetCandidatesStats();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Get all candidates with filters
 */
function handleGetCandidates() {
    global $pdo;
    
    $data = getJsonInput();
    $status = $data['status'] ?? 'all';
    $search = $data['search'] ?? '';
    
    try {
        $query = "SELECT * FROM candidates WHERE 1=1";
        $params = [];
        
        if ($status !== 'all') {
            $query .= " AND verification_status = ?";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $query .= " AND (full_name LIKE ? OR email LIKE ? OR party LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format candidate data
        foreach ($candidates as &$candidate) {
            $candidate['age'] = calculateAge($candidate['date_of_birth']);
            $candidate['eligibility_status'] = checkEligibility($candidate);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $candidates,
            'total' => count($candidates)
        ]);
    } catch (PDOException $e) {
        error_log('Get candidates error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get candidates']);
    }
}

/**
 * Get specific candidate details
 */
function handleGetCandidateDetails() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = ?");
        $stmt->execute([$candidateId]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        $candidate['age'] = calculateAge($candidate['date_of_birth']);
        $candidate['eligibility_status'] = checkEligibility($candidate);
        
        // Get payment info
        $stmt = $pdo->prepare("SELECT * FROM candidate_payments WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$candidateId]);
        $candidate['payment_info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get activity log
        $stmt = $pdo->prepare("SELECT * FROM candidate_activity_log WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$candidateId]);
        $candidate['activity_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $candidate
        ]);
    } catch (PDOException $e) {
        error_log('Get candidate details error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get candidate details']);
    }
}

/**
 * Verify candidate candidacy
 */
function handleVerifyCandidate() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    $notes = $data['notes'] ?? '';
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        // Check candidate eligibility before verification
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = ?");
        $stmt->execute([$candidateId]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$candidate) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        // Verify eligibility
        $eligibility = checkEligibility($candidate);
        if (!$eligibility['eligible']) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Candidate does not meet eligibility requirements',
                'failed_checks' => $eligibility['failed_checks']
            ]);
            return;
        }
        
        // Update verification status
        $stmt = $pdo->prepare("
            UPDATE candidates 
            SET verification_status = 'verified', updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);
        
        // Log verification
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, activity_type, description, created_at)
            VALUES (?, 'verification', ?, NOW())
        ");
        $stmt->execute([$candidateId, 'Candidate verified by admin. Notes: ' . $notes]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Candidate verified successfully'
        ]);
    } catch (PDOException $e) {
        error_log('Verify candidate error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Verification failed']);
    }
}

/**
 * Reject candidate candidacy
 */
function handleRejectCandidate() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    $reason = $data['reason'] ?? '';
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE candidates 
            SET verification_status = 'rejected', rejected_reason = ?, updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $stmt->execute([$reason, $candidateId]);
        
        // Log rejection
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, activity_type, description, created_at)
            VALUES (?, 'rejection', ?, NOW())
        ");
        $stmt->execute([$candidateId, 'Candidate rejected. Reason: ' . $reason]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Candidate rejected successfully'
        ]);
    } catch (PDOException $e) {
        error_log('Reject candidate error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Rejection failed']);
    }
}

/**
 * Process candidate payment
 */
function handleProcessPayment() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    $paymentMethod = $data['payment_method'] ?? 'manual';
    $transactionId = $data['transaction_id'] ?? 'MANUAL-' . time();
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        // Check if candidate exists
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = ?");
        $stmt->execute([$candidateId]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        // Create payment record
        $stmt = $pdo->prepare("
            INSERT INTO candidate_payments (candidate_id, amount, payment_method, transaction_id, payment_status, payment_date, created_at)
            VALUES (?, 1000, ?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([$candidateId, $paymentMethod, $transactionId]);
        
        // Update candidate payment status
        $stmt = $pdo->prepare("
            UPDATE candidates 
            SET payment_status = 'completed', updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);
        
        // Log payment
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, activity_type, description, created_at)
            VALUES (?, 'payment', ?, NOW())
        ");
        $stmt->execute([$candidateId, "Payment of 1000 USD processed. Transaction ID: $transactionId"]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully'
        ]);
    } catch (PDOException $e) {
        error_log('Process payment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Payment processing failed']);
    }
}

/**
 * Get candidates statistics
 */
function handleGetCandidatesStats() {
    global $pdo;
    
    try {
        // Total candidates
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidates");
        $stmt->execute();
        $totalCandidates = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Pending verification
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidates WHERE verification_status = 'pending'");
        $stmt->execute();
        $pendingVerification = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Verified candidates
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidates WHERE verification_status = 'verified'");
        $stmt->execute();
        $verifiedCandidates = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Rejected candidates
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidates WHERE verification_status = 'rejected'");
        $stmt->execute();
        $rejectedCandidates = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Payment stats
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM candidates WHERE payment_status = 'completed'");
        $stmt->execute();
        $paymentsCompleted = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Revenue collected
        $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM candidate_payments WHERE payment_status = 'completed'");
        $stmt->execute();
        $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'total_candidates' => (int)$totalCandidates,
                'pending_verification' => (int)$pendingVerification,
                'verified_candidates' => (int)$verifiedCandidates,
                'rejected_candidates' => (int)$rejectedCandidates,
                'payments_completed' => (int)$paymentsCompleted,
                'revenue_collected' => (float)$revenue
            ]
        ]);
    } catch (PDOException $e) {
        error_log('Get stats error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get stats']);
    }
}

/**
 * Helper function: Calculate age
 */
function calculateAge($dob) {
    $dob = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($dob)->y;
}

/**
 * Helper function: Check candidate eligibility
 */
function checkEligibility($candidate) {
    $eligible = true;
    $failedChecks = [];
    
    // Check age
    $age = calculateAge($candidate['date_of_birth']);
    if ($age < 21) {
        $eligible = false;
        $failedChecks[] = "Age must be 21 or older (current age: $age)";
    }
    
    // Check BSc degree
    if (!$candidate['has_bsc_degree']) {
        $eligible = false;
        $failedChecks[] = "Must have BSc degree";
    }
    
    // Check good conduct
    if (!$candidate['good_conduct']) {
        $eligible = false;
        $failedChecks[] = "Must have good conduct";
    }
    
    // Check payment
    if ($candidate['payment_status'] !== 'completed') {
        $eligible = false;
        $failedChecks[] = "Registration fee payment not completed";
    }
    
    return [
        'eligible' => $eligible,
        'failed_checks' => $failedChecks
    ];
}
?>
