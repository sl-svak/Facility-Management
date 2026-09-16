<?php

class Auth {
    
    // Zjištění, zda je uživatel přihlášen
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    // Vrátí aktuální roli (nebo 'guest')
    public static function getRole(): string {
        return $_SESSION['role'] ?? 'guest';
    }

    // Metody pro layout.php
    public static function isAdmin(): bool {
        return self::getRole() === 'admin';
    }

	public static function isManager(): bool {
        $role = self::getRole();
        return $role === 'admin' || $role === 'manager';
    }

    // Ověření, zda má uživatel požadovanou roli
    public static function hasRole($roles): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        $userRole = self::getRole();
        if ($userRole === 'admin') {
            return true;
        }

        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }

        return $userRole === $roles;
    }

    // Ochrana stránek (Vyhazovač) - podporuje string i array
    public static function requireRole($roles): void {
        if (!self::isLoggedIn()) {
            header('Location: index.php?page=login');
            exit;
        }

        $userRole = self::getRole();
        
        // Administrátor má přístup do všech sekcí
        if ($userRole === 'admin') {
            return;
        }

        $allowedRoles = is_array($roles) ? $roles : [$roles];

        if (!in_array($userRole, $allowedRoles, true)) {
            header('Location: index.php?page=dashboard&access_denied=1');
            exit;
        }
    }

    // Přihlášení uživatele (Návratový typ je nyní string pro odlišení blokace)
    public static function login(string $username, string $password): string {
        $pdo = Database::getConnection();
        
        // 1. Získání reálné IP adresy (ošetření proxy)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip = trim(explode(',', $ip)[0]);
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Neznámý prohlížeč', 0, 255);

        // 2. Kontrola zablokování (Max 5 chyb za 15 minut)
        $blockStmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE ip_address = ? AND status = 'failed' AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $blockStmt->execute([$ip]);
        $failedAttempts = $blockStmt->fetchColumn();

        if ($failedAttempts >= 5) {
            return 'blocked'; 
        }

        // 3. Ověření uživatele
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // ÚSPĚCH
            $logStmt = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'success', ?)");
            $logStmt->execute([$ip, $username, $userAgent]);

            $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            $firstName = $user['first_name'] ?? 'Uživatel';
            $lastName = $user['last_name'] ?? '';
            $_SESSION['first_name'] = trim($firstName);
            $_SESSION['name'] = trim($firstName . ' ' . $lastName);
            
            $_SESSION['role'] = $user['role'] ?? 'technician';
            
            // NOVÝ ŘÁDEK: Uložení informace o nutnosti změny hesla
            $_SESSION['force_password_change'] = !empty($user['force_password_change']);
            
            return 'success';
        } else {
            // CHYBA
            $logStmt = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'failed', ?)");
            $logStmt->execute([$ip, $username, $userAgent]);
            
            return 'invalid';
        }
    }

    // Odhlášení
    public static function logout(): void {
        $_SESSION = [];
        if (session_id() !== '' || headers_sent()) {
            session_destroy();
        }
    }
}
