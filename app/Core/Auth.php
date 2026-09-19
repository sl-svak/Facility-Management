<?php

class Auth {

    /**
     * Bezpečné zjištění IP adresy.
     * Striktně ignoruje podvrhnutelné hlavičky (X-Forwarded-For) 
     * a spoléhá pouze na vrstvu TCP spojení z webového serveru.
     */
    private static function getClientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Zpracuje přihlášení uživatele s dvouvrstvou ochranou proti Brute-Force útokům
     * 
     * @param string $username
     * @param string $password
     * @return string 'success', 'failed', 'blocked'
     */
    public static function login($username, $password) {
        $pdo = Database::getConnection();
        
        // Použití nové bezpečné metody pro IP
        $ip = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // ---------------------------------------------------------------------
        // 1. OBRANA PROTI BRUTE-FORCE A ZABEZPEČENÍ NATU (DVOUVRSTVÝ MODEL)
        // ---------------------------------------------------------------------
        
        // A) Lokální limit: 5 pokusů pro konkrétní kombinaci IP + Uživatelské jméno
        // (Ochrání konkrétní účet, ale nezablokuje kolegy ve stejné kanceláři)
        $stmtTargeted = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND username = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmtTargeted->execute([$ip, $username]);
        if ((int)$stmtTargeted->fetchColumn() >= 5) {
            return 'blocked';
        }

        // B) Globální limit: 25 pokusů pro danou IP adresu bez ohledu na jméno
        // (Zastaví plošné zkoušení slovníků a hádání jmen z jedné adresy)
        $stmtGlobal = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmtGlobal->execute([$ip]);
        if ((int)$stmtGlobal->fetchColumn() >= 25) {
            return 'blocked';
        }

        // ---------------------------------------------------------------------
        // 2. OVĚŘENÍ IDENTITY A HESLA
        // ---------------------------------------------------------------------
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Ověříme hash hesla
        if ($user && password_verify($password, $user['password'])) {
            
            // Zápis úspěchu do bezpečnostního deníku
            $log = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'success', ?)");
            $log->execute([$ip, $username, $userAgent]);

            // Bezpečné naplnění relačních dat (Session)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['force_password_change'] = $user['force_password_change'];
            
            // Načtení uživatelských preferencí vzhledu
            $_SESSION['theme'] = $user['theme'] ?? 'auto';
            $_SESSION['font_size'] = $user['font_size'] ?? 'normal';
            $_SESSION['qr_mode'] = $user['qr_mode'] ?? 'auto';

            // Aktualizace času posledního přihlášení
            $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $update->execute([$user['id']]);

            return 'success';
        } else {
            // Zápis neúspěchu do bezpečnostního deníku (navyšuje počítadla z kroku 1)
            $log = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'failed', ?)");
            $log->execute([$ip, $username, $userAgent]);
            
            return 'failed';
        }
    }

    /**
     * Odhlášení uživatele a zničení session dat
     */
    public static function logout() {
        // Vymaže všechna data ze session pole
        $_SESSION = [];
        
        // Zničí relační cookie v prohlížeči klienta
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Zničí samotnou session na serveru
        session_destroy();
    }

    /**
     * Zkontroluje, zda je uživatel aktuálně přihlášen
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * Ochrana routy (vyhazovač) – zkontroluje požadované role a v případě neshody ukončí skript
     * 
     * @param string|array $roles Povolená role nebo pole rolí (např. 'admin' nebo ['admin', 'manager'])
     */
    public static function requireRole($roles) {
        if (!self::isLoggedIn()) {
            header('Location: index.php?page=login');
            exit;
        }
        
        $currentRole = $_SESSION['role'] ?? '';
        
        // Pokud je zadána jen jedna role jako string, převedeme ji na pole
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        if (!in_array($currentRole, $roles)) {
            http_response_code(403);
            die('
                <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #e74c3c;">
                    <h2 style="font-size: 2em; margin-bottom: 10px;">Přístup odepřen (403)</h2>
                    <p>Pro zobrazení této stránky nemáte dostatečné oprávnění.</p>
                    <a href="index.php?page=dashboard" style="display: inline-block; padding: 10px 20px; background: #34495e; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;">Zpět na přehled</a>
                </div>
            ');
        }
    }

    /**
     * Pomocná metoda pro rychlé zjištění, zda je uživatel administrátor
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Pomocná metoda pro zjištění, zda je uživatel manažer (nebo admin)
     */
    public static function isManager() {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
    }
}
