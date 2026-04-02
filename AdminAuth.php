<?php

class AdminAuth {
    public const DEFAULT_ADMIN_EMAIL = 'admin@votingsystem.local';
    public const DEFAULT_ADMIN_PASSWORD = 'Admin123!';

    public static function ensureSessionStarted() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function ensureDefaultAdminAccount(PDO $db) {
        $stmt = $db->prepare("
            SELECT voter_id, email, user_type, is_verified
            FROM voters
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->bindValue(':email', self::DEFAULT_ADMIN_EMAIL);
        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            if (($admin['user_type'] ?? '') !== 'admin' || (int) ($admin['is_verified'] ?? 0) !== 1) {
                $update = $db->prepare("
                    UPDATE voters
                    SET user_type = 'admin', is_verified = 1
                    WHERE voter_id = :voter_id
                ");
                $update->bindValue(':voter_id', $admin['voter_id']);
                $update->execute();
            }

            return $admin['voter_id'];
        }

        $voterId = 'ADMIN' . strtoupper(substr(md5(self::DEFAULT_ADMIN_EMAIL), 0, 8));
        $passwordHash = password_hash(self::DEFAULT_ADMIN_PASSWORD, PASSWORD_BCRYPT);
        $nationalIdHash = hash('sha256', 'ADMIN_NATIONAL_ID');

        $insert = $db->prepare("
            INSERT INTO voters (
                voter_id, national_id_hash, full_name, email, password_hash, user_type, is_verified
            ) VALUES (
                :voter_id, :national_id_hash, :full_name, :email, :password_hash, 'admin', 1
            )
        ");
        $insert->execute([
            ':voter_id' => $voterId,
            ':national_id_hash' => $nationalIdHash,
            ':full_name' => 'Administrator',
            ':email' => self::DEFAULT_ADMIN_EMAIL,
            ':password_hash' => $passwordHash
        ]);

        return $voterId;
    }

    public static function isSessionAdmin(PDO $db) {
        self::ensureSessionStarted();

        $sessionUserType = $_SESSION['user_type'] ?? null;
        if ($sessionUserType === 'admin' && !empty($_SESSION['voter_id'])) {
            return true;
        }

        $sessionVoterId = $_SESSION['voter_id'] ?? null;
        $sessionEmail = strtolower($_SESSION['email'] ?? '');

        if (!$sessionVoterId && !$sessionEmail) {
            return false;
        }

        $query = "
            SELECT voter_id, email, user_type, is_verified
            FROM voters
            WHERE " . ($sessionVoterId ? "voter_id = :identifier" : "email = :identifier") . "
            LIMIT 1
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':identifier', $sessionVoterId ?: $sessionEmail);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }

        $email = strtolower($user['email'] ?? '');
        $isAdmin = (($user['user_type'] ?? '') === 'admin' || $email === self::DEFAULT_ADMIN_EMAIL)
            && (int) ($user['is_verified'] ?? 0) === 1;

        if ($isAdmin) {
            $_SESSION['voter_id'] = $user['voter_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['admin_id'] = $user['voter_id'];
            $_SESSION['admin_username'] = $user['email'];
        }

        return $isAdmin;
    }
}
