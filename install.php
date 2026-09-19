<?php
// Bezpečnostní nastavení chyb
ini_set('display_errors', 0);
error_reporting(0);

session_start();

$baseDir    = __DIR__;
$configDir  = $baseDir . '/config';
$configFile = $configDir . '/config.php';
$lockFile   = $configDir . '/installed.lock';

// -----------------------------------------------------------------------------
// 1. NEPRŮSTŘELNÁ OCHRANA PROTI RE-INSTALACI (ZÁMEK)
// -----------------------------------------------------------------------------
if (file_exists($lockFile)) {
    http_response_code(403);
    die('
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #27ae60;">
            <h2 style="font-size: 2em; margin-bottom: 10px;">Systém je již nainstalován</h2>
            <p style="color: #555;">Z bezpečnostních důvodů nelze instalaci spustit znovu. Vraťte se na <a href="index.php" style="color: #2980b9;">Přihlašovací obrazovku</a>.</p>
            <p style="color: #999; font-size: 0.8em; margin-top: 20px;">(Pokud opravdu potřebujete instalaci opakovat, musíte přes FTP smazat soubor config/installed.lock)</p>
        </div>
    ');
}

$step = (int)($_GET['step'] ?? 1);
$error = '';

// Dodatečná kontrola pro případ, že existuje config, ale chybí lock file.
if (file_exists($configFile) && $step === 1) {
    require_once $configFile;
    if (defined('APP_ROOT')) {
        require_once APP_ROOT . '/app/Core/Database.php';
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            if ($stmt && (int)$stmt->fetchColumn() > 0) {
                // Systém se zdá být nainstalovaný (má uživatele), vytvoříme chybějící zámek a zamítneme přístup
                @file_put_contents($lockFile, 'Uzamceno automaticky: ' . date('Y-m-d H:i:s'));
                header('Location: index.php?page=login');
                exit;
            } else {
                header('Location: install.php?step=2');
                exit;
            }
        } catch (Exception $e) {
            // Při výpadku DB nesmíme padat tiše do instalace
        }
    }
}

// -----------------------------------------------------------------------------
// 2. ZPRACOVÁNÍ FORMULÁŘŮ A VYTVOŘENÍ SCHÉMATU
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';

        if (empty($db_name) || empty($db_user)) {
            $error = 'Vyplňte prosím název databáze a uživatelské jméno.';
        } else {
            try {
                $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                if (!is_dir($configDir)) @mkdir($configDir, 0777, true);

                // Bezpečný zápis config.php s ochranou proti přímému zobrazení
                $configContent = "<?php\n" .
                    "// Ochrana proti přímému přístupu\n" .
                    "if (!defined('APP_ROOT')) {\n" .
                    "    http_response_code(403);\n" .
                    "    exit('Přímý přístup není povolen.');\n" .
                    "}\n\n" .
                    "define('DB_HOST', " . var_export($db_host, true) . ");\n" .
                    "define('DB_NAME', " . var_export($db_name, true) . ");\n" .
                    "define('DB_USER', " . var_export($db_user, true) . ");\n" .
                    "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
                
                if (@file_put_contents($configFile, $configContent) === false) {
                    throw new Exception("Nepodařilo se zapsat do složky config/. Zkontrolujte oprávnění (chmod).");
                }

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS departments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(150) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(100) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        force_password_change TINYINT(1) NOT NULL DEFAULT 0,
                        first_name VARCHAR(100) NOT NULL,
                        last_name VARCHAR(100) NOT NULL,
                        role VARCHAR(50) DEFAULT 'technician',
                        is_active TINYINT(1) DEFAULT 1,
                        theme VARCHAR(20) DEFAULT 'auto',
                        font_size VARCHAR(20) DEFAULT 'normal',
                        qr_mode VARCHAR(20) DEFAULT 'auto',
                        email VARCHAR(150) NULL,
                        last_login_at DATETIME DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS user_departments (
                        user_id INT NOT NULL,
                        department_id INT NOT NULL,
                        PRIMARY KEY (user_id, department_id),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS login_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        ip_address VARCHAR(45) NOT NULL,
                        username VARCHAR(50) NOT NULL,
                        status ENUM('success', 'failed') NOT NULL,
                        user_agent VARCHAR(255),
                        INDEX idx_ip_time (ip_address, attempt_time)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                    CREATE TABLE IF NOT EXISTS assets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        department_id INT NULL,
                        description TEXT NULL,
                        qr_hash VARCHAR(64) NOT NULL UNIQUE,
                        is_active TINYINT(1) DEFAULT 1,
                        operational_status VARCHAR(20) DEFAULT 'running',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS form_templates (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        schema_json TEXT NOT NULL,
                        estimated_minutes INT DEFAULT 15,
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS asset_form_rules (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_id INT NOT NULL,
                        form_template_id INT NOT NULL,
                        period_days INT NOT NULL,
                        warning_days INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (form_template_id) REFERENCES form_templates(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS plan_changes_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        rule_id INT NOT NULL,
                        user_id INT NULL,
                        old_period INT NOT NULL,
                        new_period INT NOT NULL,
                        reason TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (rule_id) REFERENCES asset_form_rules(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS inspections (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_id INT NOT NULL,
                        form_template_id INT NOT NULL,
                        technician_id INT NULL,
                        status VARCHAR(20) NOT NULL,
                        data_json MEDIUMTEXT NOT NULL,
                        duration_seconds INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_status (status),
                        INDEX idx_created_at (created_at),
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (form_template_id) REFERENCES form_templates(id) ON DELETE CASCADE,
                        FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS tickets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        inspection_id INT NULL,
                        asset_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        status VARCHAR(20) DEFAULT 'open',
                        resolution_text TEXT NULL,
                        resolution_signature MEDIUMTEXT NULL,
                        resolution_photos TEXT NULL,
                        resolved_by INT NULL,
                        resolved_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_status_created (status, created_at),
                        FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE SET NULL,
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS settings (
                        setting_key VARCHAR(50) PRIMARY KEY,
                        setting_value TEXT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS system_migrations (
                        version VARCHAR(50) PRIMARY KEY,
                        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('app_name', 'CMMS Cosmonde'), ('favicon_path', '');
                    INSERT IGNORE INTO system_migrations (version) VALUES ('1.0.7');
                ");

                header('Location: install.php?step=2');
                exit;

            } catch (Exception $e) {
                // Nikdy nezobrazujeme detailní chybu uživateli v produkci!
                $error = 'Chyba databáze: Zkontrolujte prosím přihlašovací údaje a ujistěte se, že databáze existuje.';
            }
        }
    } elseif ($step === 2) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');

        if (empty($username) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = 'Vyplňte prosím všechna pole.';
        } elseif (mb_strlen($password) < 12) {
            $error = 'Z bezpečnostních důvodů musí mít heslo administrátora minimálně 12 znaků.';
        } else {
            define('APP_ROOT', __DIR__);
            require_once $configFile;
            require_once APP_ROOT . '/app/Core/Database.php';

            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Uživatelské jméno již existuje.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
                    $stmt->execute([$username, $hashed_password, $first_name, $last_name]);

                    // UZAMČENÍ INSTALACE
                    @file_put_contents($lockFile, 'Instalace dokoncena: ' . date('Y-m-d H:i:s'));
                    
                    // POKUS O TRVALÉ SMAZÁNÍ
                    $deleted = @unlink(__FILE__);

                    // Okamžité vykreslení zprávy o úspěchu (Vyhneme se přesměrování do zamčeného/smazaného skriptu)
                    echo '<!DOCTYPE html>
                    <html lang="cs">
                    <head>
                        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Instalace dokončena</title>
                        <style>body{font-family:"Segoe UI",Tahoma,sans-serif;background:#f4f7f6;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px;} .box{background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,0.05);max-width:500px;text-align:center;border-top:4px solid #27ae60;} .btn{display:inline-block;background:#2980b9;color:#fff;border:none;padding:12px 25px;border-radius:4px;text-decoration:none;font-size:1.1em;margin-top:20px;font-weight:bold;}</style>
                    </head>
                    <body>
                        <div class="box">
                            <h2 style="margin-top:0;color:#27ae60;">Instalace byla úspěšná!</h2>';
                            
                            if ($deleted) {
                                echo '<div style="background:#eafaf1;color:#27ae60;padding:15px;border-radius:4px;border:1px solid #c3e6cb;margin:20px 0;">Instalační soubor byl z bezpečnostních důvodů <strong>trvale smazán</strong>.</div>';
                            } else {
                                echo '<div style="background:#fff3cd;color:#856404;padding:15px;border-radius:4px;border:1px solid #ffeeba;margin:20px 0;text-align:left;">
                                    <strong>POZOR: Server nedovolil automatické smazání.</strong><br>
                                    Systém je sice chráněn datovým zámkem, ale pro stoprocentní jistotu se prosím připojte na FTP a <strong>smažte soubor install.php ručně</strong>.
                                </div>';
                            }
                            
                            echo '<a href="index.php?page=login" class="btn">Přejít k přihlášení</a>
                        </div>
                    </body>
                    </html>';
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Došlo k chybě při vytváření administrátora.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace CMMS Systému</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f4f7f6; margin: 0; padding: 40px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 500px; border-top: 4px solid #2980b9; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; margin-top: 15px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #2980b9; color: #fff; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1.1em; margin-top: 25px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0;">CMMS Systém – Instalace</h2>
        <?php if (!empty($error)): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="install.php?step=1">
                <label>Databázový hostitel</label><input type="text" name="db_host" value="localhost" required>
                <label>Název databáze</label><input type="text" name="db_name" required>
                <label>Uživatelské jméno</label><input type="text" name="db_user" required>
                <label>Heslo k databázi</label><input type="password" name="db_pass">
                <button type="submit" class="btn">Pokračovat a vytvořit tabulky</button>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="POST" action="install.php?step=2">
                <label>Uživatelské jméno (login)</label><input type="text" name="username" required>
                <label>Jméno</label><input type="text" name="first_name" required>
                <label>Příjmení</label><input type="text" name="last_name" required>
                <label>Heslo administrátora (min. 12 znaků)</label><input type="password" name="password" minlength="12" required>
                <button type="submit" class="btn" style="background: #27ae60;">Dokončit instalaci</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
