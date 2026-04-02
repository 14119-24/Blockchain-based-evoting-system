<?php
// Quick test registration
header('Content-Type: application/json');

$testData = [
    'full_name' => 'Test User ' . time(),
    'email' => 'test' . time() . '@example.com',
    'national_id' => 'ID' . random_int(100000, 999999),
    'password' => 'TestPass123!',
    'confirm_password' => 'TestPass123!',
    'phone' => '+1234567890',
    'dob' => '1990-01-15',
    'address' => '123 Main St',
    'city' => 'Test City',
    'state' => '',
    'zip' => ''
];

// Simulate the registration request
$_GET['action'] = 'register';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $testData;

// Mock the input stream
global $stdin;

// Include the auth handler
require_once(__DIR__ . '/../config/database.php');

class AuthHandler {
    private $db;
    
    public function __construct() {
        try {
            if (!class_exists('Database')) {
                echo json_encode(['success' => false, 'error' => 'Database class not found']);
                exit();
            }
            
            $database = new Database();
            $this->db = $database->getConnection();
            
            if (!$this->db) {
                echo json_encode(['success' => false, 'error' => 'Failed to establish database connection']);
                exit();
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
    
    public function testRegister($data) {
        try {
            // Check if email already exists
            $query = "SELECT voter_id FROM voters WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":email", $data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'error' => 'Email already registered'];
            }
            
            // Check if national ID already exists
            $national_id_hash = hash('sha256', $data['national_id']);
            $query = "SELECT voter_id FROM voters WHERE national_id_hash = :national_id_hash";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":national_id_hash", $national_id_hash);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'error' => 'National ID already registered'];
            }
            
            // Generate voter ID
            $voter_id = "VOTER" . strtoupper(substr(md5(time() . $data['email']), 0, 8));
            
            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Generate key pair
            $public_key = bin2hex(random_bytes(32));
            
            // Insert voter
            $query = "INSERT INTO voters (
                voter_id, national_id_hash, full_name, email, password_hash, 
                public_key, phone, date_of_birth, address, constituency, 
                registration_status, admin_verified
            ) VALUES (
                :voter_id, :national_id_hash, :full_name, :email, :password_hash, 
                :public_key, :phone, :date_of_birth, :address, :constituency, 
                :registration_status, :admin_verified
            )";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":voter_id", $voter_id);
            $stmt->bindParam(":national_id_hash", $national_id_hash);
            $stmt->bindParam(":full_name", $data['full_name']);
            $stmt->bindParam(":email", $data['email']);
            $stmt->bindParam(":password_hash", $password_hash);
            $stmt->bindParam(":public_key", $public_key);
            $stmt->bindValue(":phone", $data['phone'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":date_of_birth", !empty($data['dob']) ? $data['dob'] : null, PDO::PARAM_STR);
            $stmt->bindValue(":address", $data['address'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":constituency", $data['city'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(":registration_status", 'pending', PDO::PARAM_STR);
            $stmt->bindValue(":admin_verified", 0, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Registration successful',
                    'voter_id' => $voter_id
                ];
            } else {
                return ['success' => false, 'error' => 'Registration failed'];
            }
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

$auth = new AuthHandler();
$result = $auth->testRegister($testData);
echo json_encode($result, JSON_PRETTY_PRINT);
?>
