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

    // Metody pro layout.php (DOPLNĚNO)
    public static function isAdmin(): bool {
        return self::getRole() === 'admin';
    }

    public static function isDispatcher(): bool {
        $role = self::getRole();
        return $role === 'admin' || $role === 'dispatcher';
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

    // Přihlášení uživatele
    public static function login(string $username, string $password): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Jméno může být uloženo v 'first_name' nebo jen v 'name' (bezpečnostní sjednocení)
            $firstName = $user['first_name'] ?? 'Uživatel';
            $lastName = $user['last_name'] ?? '';
            $_SESSION['first_name'] = trim($firstName);
            $_SESSION['name'] = trim($firstName . ' ' . $lastName);
            
            $_SESSION['role'] = $user['role'] ?? 'technician';
            return true;
        }
        return false;
    }

    // Odhlášení
    public static function logout(): void {
        $_SESSION = [];
        if (session_id() !== '' || headers_sent()) {
            session_destroy();
        }
    }
}
