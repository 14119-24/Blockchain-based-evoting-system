-- Create candidates table with all eligibility requirements
CREATE TABLE IF NOT EXISTS candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    party VARCHAR(100) NOT NULL,
    has_bsc_degree BOOLEAN DEFAULT FALSE COMMENT 'Must have BSc degree',
    good_conduct BOOLEAN DEFAULT FALSE COMMENT 'Must confirm good conduct',
    campaign_vision LONGTEXT,
    experience LONGTEXT,
    registration_fee DECIMAL(10, 2) DEFAULT 1000 COMMENT 'Registration fee amount',
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejected_reason VARCHAR(255),
    age_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_party (party),
    INDEX idx_verification_status (verification_status),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Candidates table with eligibility tracking';

-- Create candidate activity log table
CREATE TABLE IF NOT EXISTS candidate_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id VARCHAR(50) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Candidate activity audit log';

-- Create candidate payments table
CREATE TABLE IF NOT EXISTS candidate_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 1000,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment (candidate_id, transaction_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Candidate payment tracking for 1000 registration fee';

-- Create candidate verification table
CREATE TABLE IF NOT EXISTS candidate_verification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id VARCHAR(50) NOT NULL,
    admin_id INT,
    verification_type VARCHAR(50) NOT NULL COMMENT 'age, degree, conduct, payment',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    document_url VARCHAR(255),
    notes TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_verification_type (verification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed verification tracking for candidate eligibility';

-- Create index on votes table for candidate votes if not exists
ALTER TABLE votes ADD INDEX IF NOT EXISTS idx_candidate_id (candidate_id);

-- Add eligibility check view
CREATE OR REPLACE VIEW candidate_eligibility_check AS
SELECT 
    c.candidate_id,
    c.full_name,
    c.email,
    c.party,
    CASE 
        WHEN YEAR(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth))) >= 21 THEN 'PASS'
        ELSE 'FAIL'
    END as age_check,
    CASE WHEN c.has_bsc_degree THEN 'PASS' ELSE 'FAIL' END as degree_check,
    CASE WHEN c.good_conduct THEN 'PASS' ELSE 'FAIL' END as conduct_check,
    CASE WHEN cp.payment_status = 'completed' THEN 'PASS' ELSE 'FAIL' END as payment_check,
    CASE 
        WHEN (YEAR(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth))) >= 21 
            AND c.has_bsc_degree 
            AND c.good_conduct 
            AND cp.payment_status = 'completed'
            AND c.verification_status = 'verified')
        THEN 'ELIGIBLE'
        ELSE 'NOT_ELIGIBLE'
    END as overall_status,
    c.created_at
FROM candidates c
LEFT JOIN candidate_payments cp ON c.candidate_id = cp.candidate_id AND cp.payment_status = 'completed';
