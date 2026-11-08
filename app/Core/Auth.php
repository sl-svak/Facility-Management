<?php

class Auth {

    // Časové limity relace (v sekundách)
    const IDLE_TIMEOUT = 7200;      // 2 hodiny nečinnosti
    const ABSOLUTE_TIMEOUT = 43200; // 12 hodin od přihlášení (jedna směna)

    /** Nastaví se na true, pokud byla relace právě ukončena kvůli vypršení limitu. */
    public static $sessionExpired = false;

    // Omezení pokusů o přihlášení (v okně LOGIN_WINDOW_MINUTES)
    const LOGIN_WINDOW_MINUTES = 15;
    const MAX_FAILED_PER_IP_USER = 5; // špatná hesla pro jedno jméno z jedné IP
    const MAX_FAILED_PER_IP = 30;     // všechna špatná hesla z jedné IP (vyšší kvůli sdílené NAT ve firmě)

    // Platný hash náhodného hesla - slouží k vyrovnání doby odpovědi, když uživatel neexistuje
    const DUMMY_HASH = '$2y$10$XL6XieZLHmuOZqelFtbxLeV/Kd2ed5ezwr1BCex0liD/zZaq9iwlO';

    /**
     * Zjištění IP adresy klienta.
     * Hlavička X-Forwarded-For se bere v úvahu jen tehdy, když požadavek přišel z privátní/lokální
     * adresy (tj. přes reverzní proxy hostingu), a použije se poslední záznam, který přidala proxy.
     * Přímý návštěvník si tak IP podvrhnout nemůže.
     */
    private static function getClientIp() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $isPublic = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        if (!$isPublic && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $candidate = end($parts);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }
        return $remote;
    }
    
    /**
     * Zpracuje přihlášení uživatele s dvouvrstvou ochranou proti Brute-Force útokům
     */
    public static function login($username, $password) {
        $pdo = Database::getConnection();
        
        $ip = self::getClientIp();
        $userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $logName = mb_substr((string)$username, 0, 50); // sloupec login_logs.username má 50 znaků

        // Občasné promazání starých záznamů, aby tabulka login_logs nerostla donekonečna
        if (random_int(1, 100) === 1) {
            $pdo->exec("DELETE FROM login_logs WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 180 DAY)");
        }

        $window = (int)self::LOGIN_WINDOW_MINUTES;

        // A) Limit pro dvojici IP + jméno. Po úspěšném přihlášení se počítají jen pozdější neúspěšné pokusy,
        //    takže překlep v hesle nezačne odpočet znovu (a záznamy v bezpečnostním deníku zůstanou).
        $stmtTargeted = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND username = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL $window MINUTE)
            AND attempt_time >= COALESCE(
                (SELECT MAX(s.attempt_time) FROM login_logs s WHERE s.ip_address = ? AND s.username = ? AND s.status = 'success'),
                '1970-01-01 00:00:00')
        ");
        $stmtTargeted->execute([$ip, $logName, $ip, $logName]);
        if ((int)$stmtTargeted->fetchColumn() >= self::MAX_FAILED_PER_IP_USER) {
            return 'blocked';
        }

        // B) Celkový limit pro IP adresu
        $stmtGlobal = $pdo->prepare("
            SELECT COUNT(*) FROM login_logs 
            WHERE ip_address = ? AND status = 'failed' 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL $window MINUTE)
        ");
        $stmtGlobal->execute([$ip]);
        if ((int)$stmtGlobal->fetchColumn() >= self::MAX_FAILED_PER_IP) {
            return 'blocked';
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Pro neexistující účet se ověří falešný hash, aby odpověď netrvala znatelně kratší
        // a nešlo tak zjišťovat, která uživatelská jména existují.
        if (!$user) {
            password_verify((string)$password, self::DUMMY_HASH);
        }

        if ($user && password_verify($password, $user['password'])) {
            $log = $pdo->prepare("INSERT INTO login_logs (ip_address, username, status, user_agent) VALUES (?, ?, 'success', ?)");
            $log->execute([$ip, $logName, $userAgent]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_at'] = time();
            $_SESSION['last_activity'] = time();
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
            $log->execute([$ip, $logName, $userAgent]);
            
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
        
        // Vypršení relace: nečinnost nebo celková doba od přihlášení
        $now = time();
        $loginAt = $_SESSION['login_at'] ?? $now;
        $lastActivity = $_SESSION['last_activity'] ?? $now;
        if (($now - $lastActivity) > self::IDLE_TIMEOUT || ($now - $loginAt) > self::ABSOLUTE_TIMEOUT) {
            self::logout();
            self::$sessionExpired = true;
            return false;
        }
        $_SESSION['login_at'] = $loginAt;
        $_SESSION['last_activity'] = $now;

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
