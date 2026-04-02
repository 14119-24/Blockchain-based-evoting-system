<?php

class SystemSettings {
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->ensureTables();
    }

    public static function defaults() {
        return [
            'system_name' => 'BlockVote Admin',
            'admin_email' => 'admin@votingsystem.local',
            'support_phone' => '+254 700 000 000',
            'timezone' => 'Africa/Nairobi',
            'refresh_interval' => 30,
            'notifications_enabled' => true,
            'maintenance_mode' => false,
            'allow_voter_registration' => true,
            'allow_candidate_registration' => true,
            'show_live_results' => true
        ];
    }

    public function getAll() {
        $settings = self::defaults();

        try {
            $stmt = $this->db->query("
                SELECT setting_key, setting_value
                FROM system_settings
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $key = $row['setting_key'];
                if (!array_key_exists($key, $settings)) {
                    continue;
                }

                $settings[$key] = $this->castValue($key, $row['setting_value']);
            }
        } catch (PDOException $e) {
            error_log('Load system settings warning: ' . $e->getMessage());
        }

        return $settings;
    }

    public function save(array $incomingSettings, $updatedBy = 'system') {
        $current = $this->getAll();
        $allowedKeys = array_keys(self::defaults());

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $incomingSettings)) {
                continue;
            }

            $current[$key] = $this->normalizeValue($key, $incomingSettings[$key]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
            VALUES (:setting_key, :setting_value, :updated_by, NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");

        foreach ($current as $key => $value) {
            $stmt->bindValue(':setting_key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':setting_value', $this->serializeValue($value), PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', (string) $updatedBy, PDO::PARAM_STR);
            $stmt->execute();
        }

        return $this->getAll();
    }

    public function get($key, $default = null) {
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    public function isEnabled($key, $default = false) {
        $value = $this->get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function ensureTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_by VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        $this->db->exec("
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
            )
        ");
    }

    private function castValue($key, $value) {
        if (in_array($key, ['notifications_enabled', 'maintenance_mode', 'allow_voter_registration', 'allow_candidate_registration', 'show_live_results'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($key === 'refresh_interval') {
            return max(15, (int) $value);
        }

        return (string) $value;
    }

    private function normalizeValue($key, $value) {
        if (in_array($key, ['notifications_enabled', 'maintenance_mode', 'allow_voter_registration', 'allow_candidate_registration', 'show_live_results'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($key === 'refresh_interval') {
            return max(15, min(600, (int) $value));
        }

        return trim((string) $value);
    }

    private function serializeValue($value) {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
?>
