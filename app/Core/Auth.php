<?php

class Auth {

    /**
     * Bezpečné zjištění IP adresy.
     */
    private static function getClientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Zpracuje přihlášení uživatele s dvouvrstvou ochranou proti Brute-Force útokům
     */
    public static function login($username, $password) {
        $pdo = Database::getConnection();
        
        $ip = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // A) Lokální limit: 3 pokusy
        $stmtTargeted = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND username = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmtTargeted->execute([$ip, $username]);
        if ((int)$stmtTargeted->fetchColumn() >= 3) {
            return 'blocked';
        }

        // B) Globální limit: 15 pokusů
        $stmtGlobal = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmtGlobal->execute([$ip]);
        if ((int)$stmtGlobal->fetchColumn() >= 15) {
            return 'blocked';
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $log = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'success', ?)");
            $log->execute([$ip, $username, $userAgent]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['force_password_change'] = $user['force_password_change'];
            
            $_SESSION['theme'] = $user['theme'] ?? 'auto';
            $_SESSION['font_size'] = $user['font_size'] ?? 'normal';
            $_SESSION['qr_mode'] = $user['qr_mode'] ?? 'auto';

            $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $update->execute([$user['id']]);

            return 'success';
        } else {
            $log = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'failed', ?)");
            $log->execute([$ip, $username, $userAgent]);
            
            return 'failed';
        }
    }

    public static function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Ochrana relace (Nově z auditu: Křížová kontrola DB při každém požadavku)
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
            return false;
        }
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT role, is_active, force_password_change FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['is_active'] != 1) {
            self::logout();
            return false;
        }

        $_SESSION['role'] = $user['role'];
        $_SESSION['force_password_change'] = $user['force_password_change'];
        
        return true;
    }

    public static function requireRole($roles) {
        if (!self::isLoggedIn()) {
            header('Location: index.php?page=login');
            exit;
        }
        
        $currentRole = $_SESSION['role'] ?? '';
        
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

    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function isManager() {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
    }

    /**
     * Ochrana proti IDOR
     */
    public static function canAccessAsset($asset_id) {
        if (self::isAdmin()) return true; 

        $user_id = $_SESSION['user_id'] ?? 0;
        require_once APP_ROOT . '/app/Models/UserModel.php';
        $currentUser = UserModel::getById($user_id);
        $userDepts = $currentUser['departments'] ?? [];
        
        if (empty($userDepts)) return false; 

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT department_id FROM assets WHERE id = ?");
        $stmt->execute([(int)$asset_id]);
        $asset = $stmt->fetch();

        if (!$asset) return false;
        if ($asset['department_id'] === null) return true;

        return in_array($asset['department_id'], $userDepts);
    }

    /**
     * Ochrana výpisů
     */
    public static function getDepartmentSqlFilter($tableAlias = 'a') {
        if (self::isAdmin()) return "1=1"; 
        
        $user_id = $_SESSION['user_id'] ?? 0;
        require_once APP_ROOT . '/app/Models/UserModel.php';
        $currentUser = UserModel::getById($user_id);
        $userDepts = $currentUser['departments'] ?? [];
        
        if (empty($userDepts)) return "1=0"; 
        
        $in = implode(',', array_map('intval', $userDepts));
        return "({$tableAlias}.department_id IN ($in) OR {$tableAlias}.department_id IS NULL)";
    }

    /**
     * Validace síly hesla
     */
    public static function validatePasswordPolicy($password) {
        if (strlen($password) < 12) {
            return "Heslo musí obsahovat alespoň 12 znaků.";
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return "Heslo musí obsahovat velká, malá písmena a číslice.";
        }
        return true;
    }
}
