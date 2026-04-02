<?php
class Validator {
    // Validate voter registration data
    public static function validateRegistration($data) {
        $errors = [];
        
        // Full name validation
        if (!isset($data['full_name']) || strlen(trim($data['full_name'])) < 3) {
            $errors['full_name'] = "Full name must be at least 3 characters";
        }
        
        // Email validation
        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Valid email is required";
        }
        
        // National ID validation
        if (!isset($data['national_id']) || strlen($data['national_id']) < 5) {
            $errors['national_id'] = "Valid national ID is required";
        }
        
        // Password validation
        if (!isset($data['password']) || strlen($data['password']) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        }
        
        // Password confirmation
        if (isset($data['password']) && isset($data['confirm_password']) && 
            $data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = "Passwords do not match";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // Validate vote submission
    public static function validateVote($data) {
        $errors = [];
        
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            $errors['election_id'] = "Valid election ID is required";
        }
        
        if (!isset($data['candidate_id']) || !is_numeric($data['candidate_id'])) {
            $errors['candidate_id'] = "Valid candidate selection is required";
        }
        
        if (!isset($data['signature']) || empty($data['signature'])) {
            $errors['signature'] = "Digital signature is required";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // Validate election creation
    public static function validateElection($data) {
        $errors = [];
        
        if (!isset($data['election_name']) || strlen(trim($data['election_name'])) < 5) {
            $errors['election_name'] = "Election name must be at least 5 characters";
        }
        
        if (!isset($data['start_date']) || strtotime($data['start_date']) === false) {
            $errors['start_date'] = "Valid start date is required";
        }
        
        if (!isset($data['end_date']) || strtotime($data['end_date']) === false) {
            $errors['end_date'] = "Valid end date is required";
        }
        
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $start = strtotime($data['start_date']);
            $end = strtotime($data['end_date']);
            
            if ($end <= $start) {
                $errors['end_date'] = "End date must be after start date";
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // Sanitize input data
    public static function sanitize($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitize($value);
            }
        } else {
            $input = trim($input);
            $input = stripslashes($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        
        return $input;
    }
    
    // Validate admin login
    public static function validateAdminLogin($data) {
        $errors = [];
        
        if (!isset($data['username']) || empty(trim($data['username']))) {
            $errors['username'] = "Username is required";
        }
        
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = "Password is required";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // Validate candidate addition
    public static function validateCandidate($data) {
        $errors = [];
        
        if (!isset($data['candidate_name']) || strlen(trim($data['candidate_name'])) < 3) {
            $errors['candidate_name'] = "Candidate name is required";
        }
        
        if (!isset($data['election_id']) || !is_numeric($data['election_id'])) {
            $errors['election_id'] = "Valid election is required";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // Check if string contains SQL injection patterns
    public static function checkSQLInjection($input) {
        $patterns = [
            '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE)\b/i',
            '/--/',
            '/;/',
            '/\b(OR|AND)\b\s*[\d\'\"]\s*=\s*[\d\'\"]/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    // Validate file upload
    public static function validateFile($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 2097152) {
        $errors = [];
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error";
            return $errors;
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Allowed: " . implode(', ', $allowed_types);
        }
        
        if ($file['size'] > $max_size) {
            $errors[] = "File too large. Maximum size: " . ($max_size / 1024 / 1024) . "MB";
        }
        
        return $errors;
    }
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || time() > $_SESSION['csrf_token_expiry']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_expiry'] = time() + 3600; // 1 hour
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verify CSRF token
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_expiry']) ||
            time() > $_SESSION['csrf_token_expiry']) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>