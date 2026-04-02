CREATE DATABASE IF NOT EXISTS `admin`;
USE `admin`;

CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_code VARCHAR(32) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'election_manager', 'review_officer', 'auditor') DEFAULT 'super_admin',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admin_email (email),
    INDEX idx_admin_role (role)
);

CREATE TABLE IF NOT EXISTS elections (
    election_id INT AUTO_INCREMENT PRIMARY KEY,
    election_name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('pending', 'upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'pending',
    created_by VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_election_status (status),
    INDEX idx_election_dates (start_date, end_date)
);

CREATE TABLE IF NOT EXISTS election_candidates (
    election_candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    candidate_id VARCHAR(50) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    party VARCHAR(100),
    symbol VARCHAR(100),
    assigned_by VARCHAR(32),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_election_candidate (election_id, candidate_id),
    FOREIGN KEY (election_id) REFERENCES elections(election_id) ON DELETE CASCADE,
    INDEX idx_ec_candidate (candidate_id)
);

CREATE TABLE IF NOT EXISTS candidate_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    decision ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    notes TEXT,
    decided_by VARCHAR(32),
    decided_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_candidate_decision (decision),
    INDEX idx_candidate_lookup (candidate_id, email)
);

CREATE TABLE IF NOT EXISTS voter_verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(20) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    decision ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    notes TEXT,
    decided_by VARCHAR(32),
    decided_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_voter_verification (voter_id),
    INDEX idx_voter_decision (decision)
);

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_code VARCHAR(32),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_action (action),
    INDEX idx_admin_audit_entity (entity_type, entity_id),
    INDEX idx_admin_audit_created (created_at)
);

CREATE TABLE IF NOT EXISTS admin_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_code VARCHAR(32) NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_session_token (session_token),
    INDEX idx_admin_session_expires (expires_at)
);
