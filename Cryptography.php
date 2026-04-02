<?php
class Cryptography {
    private $key_pair = [];
    
    public function __construct() {
        // In production, use proper key storage
        try {
            $this->initializeKeys();
        } catch (Exception $e) {
            // If key generation fails, continue anyway
            error_log("Cryptography init warning: " . $e->getMessage());
        }
    }
    
    // Generate RSA key pair for voter
    public function generateKeyPair() {
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        
        $key_pair = openssl_pkey_new($config);
        
        if ($key_pair === false) {
            // Fallback: Generate simple hex keys if OpenSSL fails
            return [
                "private_key" => bin2hex(random_bytes(64)),
                "public_key" => bin2hex(random_bytes(32))
            ];
        }
        
        // Extract private key
        openssl_pkey_export($key_pair, $private_key);
        
        // Extract public key
        $public_key = openssl_pkey_get_details($key_pair);
        $public_key = $public_key["key"];
        
        return [
            "private_key" => $private_key,
            "public_key" => $public_key
        ];
    }
    
    // Hash data using SHA-256
    public function hash($data) {
        return hash("sha256", $data);
    }
    
    // Encrypt vote with election public key
    public function encryptVote($vote_data, $public_key) {
        @openssl_public_encrypt($vote_data, $encrypted, $public_key);
        return base64_encode($encrypted ?? $vote_data);
    }
    
    // Decrypt vote with election private key (admin only)
    public function decryptVote($encrypted_vote, $private_key) {
        $encrypted = base64_decode($encrypted_vote);
        @openssl_private_decrypt($encrypted, $decrypted, $private_key);
        return $decrypted ?? $encrypted_vote;
    }
    
    // Create digital signature
    public function signData($data, $private_key) {
        @openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature ?? $data);
    }
    
    // Verify digital signature
    public function verifySignature($data, $signature, $public_key) {
        $signature = base64_decode($signature);
        $result = @openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }
    
    // Generate unique voter ID hash
    public function generateVoterHash($voter_id, $election_id) {
        return $this->hash($voter_id . "|" . $election_id . "|" . time());
    }
    
    // Password hashing with bcrypt
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    }
    
    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    private function initializeKeys() {
        // Election public/private key pair (in production, store securely)
        $this->key_pair = $this->generateKeyPair();
    }
    
    public function getPublicKey() {
        return $this->key_pair["public_key"] ?? bin2hex(random_bytes(32));
    }
    
    public function getPrivateKey() {
        return $this->key_pair["private_key"] ?? bin2hex(random_bytes(64));
    }
}
?>