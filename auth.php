<?php
// c:\xampp\htdocs\voting_system\api\auth.php
// Authentication API Handler for Blockchain Voting System

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Import required classes
$configPath = __DIR__ . '/../config/database.php';
$cryptoPath = __DIR__ . '/../core/Cryptography.php';
$validatorPath = __DIR__ . '/../core/Validator.php';
$adminAuthPath = __DIR__ . '/../core/AdminAuth.php';
$systemSettingsPath = __DIR__ . '/../core/SystemSettings.php';

if (file_exists($configPath)) {
    require_once($configPath);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit();
}

if (file_exists($cryptoPath)) {
    require_once($cryptoPath);
}

if (file_exists($validatorPath)) {
    require_once($validatorPath);
}

if (file_exists($adminAuthPath)) {
    require_once($adminAuthPath);
}

if (file_exists($systemSettingsPath)) {
    require_once($systemSettingsPath);
}

class AuthHandler {
    private $db;
    private $crypto;
    private $systemSettings;
    
    public function __construct() {
        try {
            if (!class_exists('Database')) {
                $this->errorResponse("Database class not found", null, 500);
            }
            
            $database = new Database();
            
            // Try to get connection
            if (method_exists($database, 'getConnection')) {
                $this->db = $database->getConnection();
            } elseif (method_exists($database, 'connect')) {
                $this->db = $database->connect();
            } else {
                $this->errorResponse("Database connection method not found", null, 500);
            }
            
            if (!$this->db) {
                $this->errorResponse("Failed to establish database connection", null, 500);
            }

            if (class_exists('AdminAuth')) {
                AdminAuth::ensureDefaultAdminAccount($this->db);
            }

            if (class_exists('SystemSettings')) {
                $this->systemSettings = new SystemSettings($this->db);
            }
            
            // Initialize cryptography if available
            if (class_exists('Cryptography')) {
                $this->crypto = new Cryptography();
            }
        } catch (Exception $e) {
            $this->errorResponse("Database connection failed: " . $e->getMessage(), null, 500);
        }
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'register':
                $this->register();
                break;
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'check_session':
                $this->checkSession();
                break;
            case 'verify_voter':
                $this->verifyVoter();
                break;
            case 'forgot_password':
                $this->forgotPassword();
                break;
            default:
                $this->errorResponse("Invalid action", null, 400);
        }
    }
    
    private function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse("Method not allowed", null, 405);
            return;
        }

        if ($this->systemSettings && !$this->systemSettings->isEnabled('allow_voter_registration', true)) {
            $this->errorResponse("Voter registration is currently disabled", null, 403);
            return;
        }
        
        // Get JSON data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            $this->errorResponse("Invalid JSON data", null, 400);
            return;
        }
        
        // Validate input
        $validation = $this->validateRegistration($data);
        if (!$validation['is_valid']) {
            $this->errorResponse("Validation failed", $validation['errors'], 400);
            return;
        }
        
        // Sanitize data
        $data = $this->sanitizeData($data);
        
        try {
            // Check if email already exists
            $query = "SELECT voter_id FROM voters WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":email", $data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $this->errorResponse("Email already registered", ['email' => 'Email is already registered'], 400);
                return;
            }
            
            // Check if national ID already exists
            $national_id_hash = hash('sha256', $data['national_id']);
            $query = "SELECT voter_id FROM voters WHERE national_id_hash = :national_id_hash";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":national_id_hash", $national_id_hash);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $this->errorResponse("National ID already registered", ['national_id' => 'National ID is already registered'], 400);
                return;
            }
            
            // Generate voter ID
            $voter_id = "VOTER" . strtoupper(substr(md5(time() . $data['email']), 0, 8));
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Generate key pair for voter (simplified)
            $public_key = bin2hex(random_bytes(32));
            $private_key = bin2hex(random_bytes(64));
            
            // Insert voter using the live schema columns.
            $query = "INSERT INTO voters (
                voter_id, national_id_hash, full_name, email, password_hash,
                public_key, private_key_encrypted, phone, dob, address, city,
                state, zip, is_verified, user_type
            ) VALUES (
                :voter_id, :national_id_hash, :full_name, :email, :password_hash,
                :public_key, :private_key_encrypted, :phone, :dob, :address, :city,
                :state, :zip, :is_verified, :user_type
            )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":voter_id", $voter_id);
            $stmt->bindParam(":national_id_hash", $national_id_hash);
            $stmt->bindParam(":full_name", $data['full_name']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password_hash", $password_hash);
            $stmt->bindParam(":public_key", $public_key);
            $stmt->bindValue(":private_key_encrypted", $private_key, PDO::PARAM_STR);
            $stmt->bindValue(":phone", $data['phone'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":dob", !empty($data['dob']) ? $data['dob'] : null, PDO::PARAM_STR);
            $stmt->bindValue(":address", $data['address'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":city", $data['city'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":state", $data['state'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":zip", $data['zip'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":is_verified", 0, PDO::PARAM_INT);
            $stmt->bindValue(":user_type", 'voter', PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                // Log the action
                $this->logAudit($voter_id, "REGISTRATION", "New voter registered: " . $data['email']);
                
                // Return success response
                $this->successResponse([
                    "message" => "Registration successful",
                    "voter_id" => $voter_id,
                    "next_step" => "Please wait for admin verification"
                ]);
            } else {
                $this->errorResponse("Registration failed", null, 500);
            }
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $this->errorResponse("Database error occurred", null, 500);
        }
    }
    
    private function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse("Method not allowed", null, 405);
            return;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Support both 'email' (voter) and 'username' (admin) parameters
        $email = $data['email'] ?? $data['username'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!isset($email) || !isset($password)) {
            $this->errorResponse("Missing email/username or password", null, 400);
            return;
        }
        
        try {
            if (class_exists('AdminAuth')) {
                AdminAuth::ensureDefaultAdminAccount($this->db);
            }

            $query = "SELECT voter_id, full_name, email, password_hash, is_verified, user_type FROM voters WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse("Invalid email or password", null, 401);
                return;
            }
            
            $voter = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (!password_verify($password, $voter['password_hash'])) {
                $this->errorResponse("Invalid email or password", null, 401);
                return;
            }
            
            // Determine user type.
            // Some project setup scripts created the default admin account without setting user_type='admin'.
            // Accept admin if user record is admin (or default email admin account), even if 'username' field is not sent.
            $isDefaultAdminAccount = class_exists('AdminAuth')
                ? strtolower($voter['email']) === AdminAuth::DEFAULT_ADMIN_EMAIL
                : strtolower($voter['email']) === 'admin@votingsystem.local';
            $isVerified = (int)$voter['is_verified'] === 1;
            $is_admin = $isVerified && (
                $voter['user_type'] === 'admin' || $isDefaultAdminAccount
            );
            $user_type = $is_admin ? 'admin' : 'voter';

            // Normalize the default admin account in the database for future requests.
            if ($is_admin && $voter['user_type'] !== 'admin') {
                try {
                    $promoteQuery = "UPDATE voters SET user_type = 'admin', is_verified = 1 WHERE voter_id = :voter_id";
                    $promoteStmt = $this->db->prepare($promoteQuery);
                    $promoteStmt->bindParam(":voter_id", $voter['voter_id']);
                    $promoteStmt->execute();
                } catch (PDOException $e) {
                    error_log("Admin normalization warning: " . $e->getMessage());
                }
            }
            
            // Set session
            $_SESSION['voter_id'] = $voter['voter_id'];
            $_SESSION['email'] = $voter['email'];
            $_SESSION['full_name'] = $voter['full_name'];
            $_SESSION['user_type'] = $user_type;
            
            // Set admin-specific session variables if admin
            if ($is_admin) {
                $_SESSION['admin_id'] = $voter['voter_id'];
                $_SESSION['admin_role'] = 'super_admin'; // Default role for admin users
                $_SESSION['admin_username'] = $voter['email'];
            }
            
            // Log the action
            $this->logAudit($voter['voter_id'], "LOGIN", "User logged in as " . $user_type);
            
            $this->successResponse([
                "message" => "Login successful",
                "voter_id" => $voter['voter_id'],
                "email" => $voter['email'],
                "full_name" => $voter['full_name'],
                "user_type" => $user_type,
                "username" => $voter['email']
            ]);
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $this->errorResponse("Database error occurred", null, 500);
        }
    }
    
    private function logout() {
        if (isset($_SESSION['voter_id'])) {
            $voter_id = $_SESSION['voter_id'];
            $this->logAudit($voter_id, "LOGOUT", "User logged out");
        }
        
        session_destroy();
        $this->successResponse(["message" => "Logged out successfully"]);
    }
    
    private function checkSession() {
        if (isset($_SESSION['voter_id'])) {
            $userType = $_SESSION['user_type'] ?? 'voter';
            $isVerified = 0;
            $hasVoted = 0;

            if (class_exists('AdminAuth') && AdminAuth::isSessionAdmin($this->db)) {
                $userType = 'admin';
            }

            try {
                $query = "SELECT is_verified, has_voted FROM voters WHERE voter_id = :voter_id LIMIT 1";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":voter_id", $_SESSION['voter_id']);
                $stmt->execute();
                $sessionVoter = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($sessionVoter) {
                    $isVerified = (int) ($sessionVoter['is_verified'] ?? 0);
                    $hasVoted = (int) ($sessionVoter['has_voted'] ?? 0);
                }
            } catch (PDOException $e) {
                error_log("Check session lookup warning: " . $e->getMessage());
            }

            $this->successResponse([
                "voter_id" => $_SESSION['voter_id'],
                "email" => $_SESSION['email'],
                "full_name" => $_SESSION['full_name'],
                "user_type" => $userType,
                "is_verified" => $isVerified,
                "has_voted" => $hasVoted
            ]);
        } else {
            $this->errorResponse("Not authenticated", null, 401);
        }
    }
    
    private function validateRegistration($data) {
        $errors = [];
        
        // Full name validation
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        } elseif (strlen($data['full_name']) < 3) {
            $errors['full_name'] = 'Full name must be at least 3 characters';
        }
        
        // Email validation
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        // National ID validation
        if (empty($data['national_id'])) {
            $errors['national_id'] = 'National ID is required';
        } elseif (strlen($data['national_id']) < 5) {
            $errors['national_id'] = 'National ID must be at least 5 characters';
        }
        
        // Password validation
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        // Confirm password validation
        if (empty($data['confirm_password'])) {
            $errors['confirm_password'] = 'Confirm password is required';
        } elseif ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function sanitizeData($data) {
        return [
            'full_name' => htmlspecialchars($data['full_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'email' => filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'national_id' => htmlspecialchars($data['national_id'] ?? '', ENT_QUOTES, 'UTF-8'),
            'password' => $data['password'] ?? '',
            'phone' => htmlspecialchars($data['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
            'dob' => $data['dob'] ?? '',
            'address' => htmlspecialchars($data['address'] ?? '', ENT_QUOTES, 'UTF-8'),
            'city' => htmlspecialchars($data['city'] ?? '', ENT_QUOTES, 'UTF-8'),
            'state' => htmlspecialchars($data['state'] ?? '', ENT_QUOTES, 'UTF-8'),
            'zip' => htmlspecialchars($data['zip'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }
    
    private function logAudit($voter_id, $action, $details) {
        try {
            $query = "INSERT INTO audit_logs (voter_id, action, details, timestamp) 
                     VALUES (:voter_id, :action, :details, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":voter_id", $voter_id);
            $stmt->bindParam(":action", $action);
            $stmt->bindParam(":details", $details);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
    
    private function successResponse($data) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        exit();
    }
    
    private function errorResponse($message, $errors = null, $statusCode = 400) {
        http_response_code($statusCode);
        $response = [
            'success' => false,
            'error' => $message
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit();
    }
    
    private function verifyVoter() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse("Method not allowed", null, 405);
            return;
        }
        
        // Check if user is admin
        $isAdmin = class_exists('AdminAuth')
            ? AdminAuth::isSessionAdmin($this->db)
            : (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');

        if (!$isAdmin) {
            $this->errorResponse("Unauthorized: Admin access required", null, 403);
            return;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['voter_id'])) {
            $this->errorResponse("Missing voter_id", null, 400);
            return;
        }
        
        try {
            // Update voter's verification status (schema uses is_verified)
            $query = "UPDATE voters SET is_verified = 1 WHERE voter_id = :voter_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":voter_id", $data['voter_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->errorResponse("Voter not found", null, 404);
                return;
            }
            
            // Log the action
            $this->logAudit($data['voter_id'], "VERIFY", "Voter verified by admin");
            
            $this->successResponse([
                "message" => "Voter verified successfully",
                "voter_id" => $data['voter_id']
            ]);
            
        } catch (PDOException $e) {
            error_log("Voter verification error: " . $e->getMessage());
            $this->errorResponse("Database error occurred", null, 500);
        }
    }

    private function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse("Method not allowed", null, 405);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $email = trim($data['email'] ?? '');
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
            $this->errorResponse("Email, new password, and confirm password are required", null, 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorResponse("Invalid email format", null, 400);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->errorResponse("Password must be at least 8 characters", null, 400);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->errorResponse("Passwords do not match", null, 400);
            return;
        }

        try {
            $findQuery = "SELECT voter_id FROM voters WHERE email = :email LIMIT 1";
            $findStmt = $this->db->prepare($findQuery);
            $findStmt->bindParam(":email", $email);
            $findStmt->execute();

            $voter = $findStmt->fetch(PDO::FETCH_ASSOC);
            if (!$voter) {
                $this->errorResponse("No voter account found with that email", null, 404);
                return;
            }

            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateQuery = "UPDATE voters SET password_hash = :password_hash WHERE voter_id = :voter_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(":password_hash", $passwordHash);
            $updateStmt->bindParam(":voter_id", $voter['voter_id']);
            $updateStmt->execute();

            $this->logAudit($voter['voter_id'], "PASSWORD_RESET", "Voter password reset");

            $this->successResponse([
                "message" => "Password reset successfully"
            ]);
        } catch (PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $this->errorResponse("Database error occurred", null, 500);
        }
    }
}

// Handle the request
$auth = new AuthHandler();
$auth->handleRequest();
?>
