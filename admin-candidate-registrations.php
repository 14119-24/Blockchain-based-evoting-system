<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
        case 'get_pending_candidates':
            handleGetPendingCandidates();
            break;
        
        case 'get_candidate_registration':
            handleGetCandidateRegistration();
            break;
        
        case 'approve_candidate':
            handleApproveCandidate();
            break;
        
        case 'reject_candidate':
            handleRejectCandidate();
            break;
        
        case 'get_candidate_stats':
            handleGetCandidateStats();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Get all pending candidates awaiting approval
 */
function handleGetPendingCandidates() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                candidate_id,
                full_name,
                email,
                phone,
                party,
                verification_status,
                payment_status,
                has_bsc_degree,
                good_conduct,
                created_at
            FROM candidate_registrations
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $candidates,
            'total' => count($candidates)
        ]);
    } catch (PDOException $e) {
        error_log('Get pending candidates error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get candidates']);
    }
}

/**
 * Get specific candidate registration details
 */
function handleGetCandidateRegistration() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM candidate_registrations 
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);
        
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
        error_log('Get candidate details error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get candidate details']);
    }
}

/**
 * Approve candidate registration
 */
function handleApproveCandidate() {
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
        $pdo->beginTransaction();

        // Get candidate info
        $stmt = $pdo->prepare("
            SELECT * FROM candidate_registrations 
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$candidate) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        // Update verification status
        $stmt = $pdo->prepare("
            UPDATE candidate_registrations 
            SET verification_status = 'verified', updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);

        // Insert into candidates table for dashboard access
        $stmt = $pdo->prepare("
            INSERT INTO candidates (
                candidate_id, full_name, email, password_hash, phone, 
                date_of_birth, party, has_bsc_degree, good_conduct,
                campaign_vision, experience, registration_fee, payment_status,
                verification_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                verification_status = 'verified',
                updated_at = NOW()
        ");
        $stmt->execute([
            $candidate['candidate_id'],
            $candidate['full_name'],
            $candidate['email'],
            $candidate['password_hash'],
            $candidate['phone'],
            $candidate['date_of_birth'],
            $candidate['party'],
            $candidate['has_bsc_degree'],
            $candidate['good_conduct'],
            $candidate['campaign_vision'],
            $candidate['experience'],
            $candidate['registration_fee'],
            $candidate['payment_status'],
            'verified'
        ]);

        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, action, description, ip_address, created_at)
            VALUES (?, 'approval', ?, ?, NOW())
        ");
        $stmt->execute([
            $candidateId,
            'Approved by admin. Notes: ' . $notes,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Candidate approved successfully',
            'candidate_name' => $candidate['full_name']
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Approve candidate error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Approval failed']);
    }
}

/**
 * Reject candidate registration
 */
function handleRejectCandidate() {
    global $pdo;
    
    $data = getJsonInput();
    $candidateId = $data['candidate_id'] ?? '';
    $reason = $data['reason'] ?? 'No reason provided';
    
    if (empty($candidateId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Candidate ID required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();

        // Get candidate info
        $stmt = $pdo->prepare("
            SELECT full_name FROM candidate_registrations 
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$candidate) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Candidate not found']);
            return;
        }
        
        // Update verification status
        $stmt = $pdo->prepare("
            UPDATE candidate_registrations 
            SET verification_status = 'rejected', updated_at = NOW()
            WHERE candidate_id = ?
        ");
        $stmt->execute([$candidateId]);

        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO candidate_activity_log (candidate_id, action, description, ip_address, created_at)
            VALUES (?, 'rejection', ?, ?, NOW())
        ");
        $stmt->execute([
            $candidateId,
            'Rejected by admin. Reason: ' . $reason,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Candidate rejected successfully',
            'candidate_name' => $candidate['full_name']
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reject candidate error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Rejection failed']);
    }
}

/**
 * Get candidate statistics
 */
function handleGetCandidateStats() {
    global $pdo;
    
    try {
        // Get stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as paid
            FROM candidate_registrations
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } catch (PDOException $e) {
        error_log('Get stats error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to get statistics']);
    }
}
?>
