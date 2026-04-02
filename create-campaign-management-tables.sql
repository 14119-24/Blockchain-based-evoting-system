-- Campaign Management Tables for Candidate Dashboard

-- Campaign updates/posts table
CREATE TABLE IF NOT EXISTS campaign_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    post_type ENUM('announcement', 'policy', 'event', 'achievement') DEFAULT 'announcement',
    image_url VARCHAR(255),
    views INT DEFAULT 0,
    engagement_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_created_at (created_at)
);

-- Campaign goals/targets
CREATE TABLE IF NOT EXISTS campaign_goals (
    goal_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    goal_name VARCHAR(255) NOT NULL,
    goal_description TEXT,
    target_votes INT NOT NULL,
    current_progress INT DEFAULT 0,
    status ENUM('pending', 'active', 'completed', 'failed') DEFAULT 'active',
    target_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_status (status)
);

-- Vote disputes/challenges
CREATE TABLE IF NOT EXISTS vote_disputes (
    dispute_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    vote_id INT,
    dispute_type ENUM('illegitimate_vote', 'duplicate_vote', 'system_error', 'fraud_allegation') DEFAULT 'system_error',
    description TEXT,
    evidence_url VARCHAR(255),
    status ENUM('pending', 'investigating', 'resolved', 'rejected') DEFAULT 'pending',
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Blockchain verification records
CREATE TABLE IF NOT EXISTS blockchain_verification (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    vote_count INT,
    block_hash VARCHAR(255),
    verification_timestamp TIMESTAMP,
    merkle_root VARCHAR(255),
    is_verified BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_is_verified (is_verified)
);

-- Demographic tracking
CREATE TABLE IF NOT EXISTS voter_demographics (
    demographic_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    age_group VARCHAR(50),
    gender VARCHAR(20),
    region VARCHAR(100),
    vote_count INT DEFAULT 0,
    percentage DECIMAL(5, 2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id)
);
