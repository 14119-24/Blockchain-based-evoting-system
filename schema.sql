-- Blockchain Voting System Database Schema

-- Create voters table
CREATE TABLE IF NOT EXISTS voters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(20) UNIQUE NOT NULL,
    national_id_hash VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    public_key TEXT,
    private_key_encrypted TEXT,
    phone VARCHAR(20),
    dob DATE,
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip VARCHAR(20),
    is_verified BOOLEAN DEFAULT FALSE,
    has_voted BOOLEAN DEFAULT FALSE,
    user_type ENUM('voter', 'admin') DEFAULT 'voter',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_voter_id (voter_id),
    INDEX idx_email (email),
    INDEX idx_is_verified (is_verified)
);

-- Create elections table
CREATE TABLE IF NOT EXISTS elections (
    election_id INT AUTO_INCREMENT PRIMARY KEY,
    election_name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('pending', 'ongoing', 'completed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
);

-- Create candidates table
CREATE TABLE IF NOT EXISTS candidates (
    candidate_id VARCHAR(50) PRIMARY KEY,
    election_id INT NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    party VARCHAR(255),
    symbol VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id),
    INDEX idx_election_id (election_id)
);

-- Create votes table
CREATE TABLE IF NOT EXISTS votes (
    vote_id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    voter_id VARCHAR(20) NOT NULL,
    candidate_id VARCHAR(50) NOT NULL,
    encrypted_vote TEXT NOT NULL,
    vote_hash VARCHAR(255) UNIQUE NOT NULL,
    block_hash VARCHAR(255),
    transaction_id VARCHAR(255) UNIQUE,
    signature TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id),
    UNIQUE KEY unique_voter_election (voter_id, election_id),
    INDEX idx_election_id (election_id),
    INDEX idx_voter_id (voter_id),
    INDEX idx_timestamp (timestamp)
);

-- Create blockchain_blocks table
CREATE TABLE IF NOT EXISTS blockchain_blocks (
    block_id INT AUTO_INCREMENT PRIMARY KEY,
    block_hash VARCHAR(255) UNIQUE NOT NULL,
    previous_hash VARCHAR(255),
    merkle_root VARCHAR(255),
    timestamp DATETIME NOT NULL,
    nonce INT DEFAULT 0,
    difficulty INT DEFAULT 0,
    transactions_count INT DEFAULT 0,
    miner VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_block_hash (block_hash),
    INDEX idx_timestamp (timestamp)
);

-- Create admin blockchain explorer blocks table
CREATE TABLE IF NOT EXISTS blocks (
    block_id INT AUTO_INCREMENT PRIMARY KEY,
    block_hash VARCHAR(255) UNIQUE NOT NULL,
    previous_hash VARCHAR(255),
    merkle_root VARCHAR(255),
    nonce INT DEFAULT 0,
    transactions_count INT DEFAULT 0,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_block_hash (block_hash),
    INDEX idx_block_timestamp (timestamp)
);

-- Create admin blockchain explorer transactions table
CREATE TABLE IF NOT EXISTS transactions (
    tx_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(255) UNIQUE NOT NULL,
    block_hash VARCHAR(255) NOT NULL,
    voter_id_hash VARCHAR(255),
    encrypted_vote LONGTEXT,
    digital_signature LONGTEXT,
    election_id INT,
    candidate_id INT,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_transaction_block_hash (block_hash),
    INDEX idx_transaction_timestamp (timestamp),
    INDEX idx_transaction_election (election_id)
);

-- Create audit_logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(20),
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_voter_id (voter_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
);

-- Create verification_queue table
CREATE TABLE IF NOT EXISTS verification_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT,
    verification_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(voter_id),
    INDEX idx_status (status)
);

-- Create session_tokens table
CREATE TABLE IF NOT EXISTS session_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(20) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(voter_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
);

-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_by VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create support requests table
CREATE TABLE IF NOT EXISTS support_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'general',
    priority VARCHAR(50) NOT NULL DEFAULT 'medium',
    message TEXT NOT NULL,
    contact_email VARCHAR(255),
    created_by VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_status (status),
    INDEX idx_support_created_at (created_at)
);
