-- Create candidate registration table
CREATE TABLE IF NOT EXISTS candidate_registrations (
    candidate_id VARCHAR(50) PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    date_of_birth DATE NOT NULL,
    party VARCHAR(100) NOT NULL,
    has_bsc_degree BOOLEAN NOT NULL DEFAULT FALSE,
    good_conduct BOOLEAN NOT NULL DEFAULT FALSE,
    campaign_vision TEXT,
    experience TEXT,
    registration_fee DECIMAL(10, 2) DEFAULT 5.00,
    payment_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Create candidate activity log table
CREATE TABLE IF NOT EXISTS candidate_activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_registrations(candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);
