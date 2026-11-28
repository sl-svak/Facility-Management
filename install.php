<?php
// Bezpečnostní nastavení chyb
ini_set('display_errors', 0);
error_reporting(0);

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
session_start();

define('APP_ROOT', __DIR__);
$baseDir     = __DIR__;
$configDir   = $baseDir . '/config';
$configFile  = $configDir . '/config.php';
$lockFile    = $configDir . '/installed.lock';
$pendingFile = $configDir . '/install.pending';

// -----------------------------------------------------------------------------
// 1. TVRDÁ OCHRANA PROTI RE-INSTALACI
// -----------------------------------------------------------------------------
// Instalace je zablokovaná, jakmile existuje installed.lock NEBO config.php.
// Rozhoduje se jen podle souborů, bez připojení k databázi, takže výpadek DB
// nebo smazaný zámek nikdy neotevře instalátor (a nejde tak přepsat config.php).
// Jedinou výjimkou je rozběhnutá instalace: krok 1 zapíše config.php spolu
// s install.pending a stejný náhodný token uloží do session. Pokračovat (krok 2)
// tak může jen prohlížeč, ve kterém instalace začala.
function install_blocked(): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    die('
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #27ae60;">
            <h2 style="font-size: 2em; margin-bottom: 10px;">Systém je již nainstalován</h2>
            <p style="color: #555;">Z bezpečnostních důvodů nelze instalaci spustit znovu. Vraťte se na <a href="index.php" style="color: #2980b9;">Přihlašovací obrazovku</a>.</p>
            <p style="color: #999; font-size: 0.8em; margin-top: 20px;">(Pokud opravdu potřebujete instalaci opakovat, smažte přes FTP soubory config/config.php, config/installed.lock a config/install.pending.)</p>
        </div>
    ');
}

if (file_exists($lockFile)) {
    install_blocked();
}

$pendingToken = is_file($pendingFile) ? trim((string)@file_get_contents($pendingFile)) : '';
$sessionToken = $_SESSION['install_token'] ?? '';
$installInProgress = strlen($pendingToken) >= 32
    && is_string($sessionToken)
    && hash_equals($pendingToken, $sessionToken);

if (file_exists($configFile) && !$installInProgress) {
    install_blocked();
}

$step = (int)($_GET['step'] ?? 1);
if (!in_array($step, [1, 2], true)) {
    $step = 1;
}
if ($installInProgress && $step === 1 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Krok 1 už proběhl, pokračuje se založením administrátora.
    $step = 2;
}
$error = '';

// -----------------------------------------------------------------------------
// 2. ZPRACOVÁNÍ FORMULÁŘŮ A VYTVOŘENÍ SCHÉMATU (CMMS v2.0)
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

                if (!is_dir($configDir)) {
                    @mkdir($configDir, 0755, true);
                }

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

                if (!$installInProgress) {
                    $installToken = bin2hex(random_bytes(32));
                    if (@file_put_contents($pendingFile, $installToken, LOCK_EX) === false) {
                        throw new Exception("Nepodařilo se zapsat do složky config/. Zkontrolujte oprávnění (chmod).");
                    }
                    session_regenerate_id(true);
                    $_SESSION['install_token'] = $installToken;
                    $installInProgress = true;
                }

                if (@file_put_contents($configFile, $configContent) === false) {
                    throw new Exception("Nepodařilo se zapsat do složky config/. Zkontrolujte oprávnění (chmod).");
                }

                $schemaSql = "
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

                    CREATE TABLE IF NOT EXISTS audit_categories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(150) NOT NULL,
                        default_interval_months INT NOT NULL DEFAULT 12,
                        default_deadline_type ENUM('strict', 'month') NOT NULL DEFAULT 'strict',
                        color_hex VARCHAR(20) NOT NULL DEFAULT '#2980b9',
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS external_suppliers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(200) NOT NULL,
                        contact_person VARCHAR(150) NULL,
                        phone VARCHAR(50) NULL,
                        email VARCHAR(150) NULL,
                        notes TEXT NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS external_audit_plans (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_id INT NOT NULL,
                        audit_category_id INT NOT NULL,
                        default_supplier_id INT NULL,
                        interval_months INT NOT NULL DEFAULT 12,
                        warning_days INT NOT NULL DEFAULT 30,
                        deadline_type ENUM('strict', 'month') NOT NULL DEFAULT 'strict',
                        last_execution_date DATE NULL,
                        next_due_date DATE NOT NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_ext_plan_due (next_due_date, is_active),
                        INDEX idx_ext_plan_asset (asset_id),
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (audit_category_id) REFERENCES audit_categories(id),
                        FOREIGN KEY (default_supplier_id) REFERENCES external_suppliers(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS external_audit_records (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        plan_id INT NOT NULL,
                        asset_id INT NOT NULL,
                        supplier_id INT NULL,
                        supplier_name VARCHAR(200) NULL,
                        execution_date DATE NOT NULL,
                        next_due_date DATE NOT NULL,
                        status ENUM('waiting_protocol', 'completed') NOT NULL DEFAULT 'waiting_protocol',
                        protocol_file VARCHAR(255) NULL,
                        protocol_number VARCHAR(100) NULL,
                        notes TEXT NULL,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_ext_rec_plan_status (plan_id, status),
                        INDEX idx_ext_rec_asset (asset_id),
                        FOREIGN KEY (plan_id) REFERENCES external_audit_plans(id) ON DELETE CASCADE,
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (supplier_id) REFERENCES external_suppliers(id) ON DELETE SET NULL,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS output_form_templates (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_id INT NOT NULL,
                        source_form_template_id INT NULL,
                        title VARCHAR(255) NOT NULL,
                        paper_code VARCHAR(100) NULL,
                        header_note TEXT NULL,
                        fields_json MEDIUMTEXT NOT NULL,
                        show_signatures TINYINT(1) NOT NULL DEFAULT 1,
                        show_photos TINYINT(1) NOT NULL DEFAULT 1,
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_out_tpl_asset (asset_id),
                        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                        FOREIGN KEY (source_form_template_id) REFERENCES form_templates(id) ON DELETE SET NULL,
                        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS settings (
                        setting_key VARCHAR(50) PRIMARY KEY,
                        setting_value TEXT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS system_migrations (
                        version VARCHAR(50) PRIMARY KEY,
                        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
                        ('app_name', 'CMMS Cosmonde'),
                        ('favicon_path', ''),
                        ('module_inspections', '1'),
                        ('module_external_audits', '1'),
                        ('module_output_templates', '1'),
                        ('module_tickets', '1');

                    INSERT IGNORE INTO audit_categories (name, default_interval_months, default_deadline_type, color_hex) VALUES
                        ('Revize elektro (do konce měsíce)', 12, 'month', '#2980b9'),
                        ('Tlaková zařízení (přesně na den)', 12, 'strict', '#8e44ad'),
                        ('Plynová a zdvihací zařízení', 12, 'strict', '#d35400');

                    INSERT IGNORE INTO system_migrations (version) VALUES ('2.0');
                ";

                // Každý příkaz zvlášť: vícepříkazové exec() by chybu v druhém a dalším příkazu tiše ignorovalo
                // a instalace by skončila s neúplným schématem.
                foreach (preg_split('/;\s*\n/', $schemaSql) as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }

                foreach (['/assets/uploads', '/app/libs/mpdf/tmp'] as $dir) {
                    if (!is_dir($baseDir . $dir)) {
                        @mkdir($baseDir . $dir, 0755, true);
                    }
                }

                header('Location: install.php?step=2');
                exit;

            } catch (Exception $e) {
                error_log('CMMS install krok 1: ' . $e->getMessage());
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
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Heslo musí obsahovat velká, malá písmena a číslice.';
        } elseif (!file_exists($configFile)) {
            $error = 'Nejprve dokončete krok 1 (připojení k databázi).';
        } else {
            require_once $configFile;
            require_once APP_ROOT . '/app/Core/Database.php';

            try {
                $pdo = Database::getConnection();

                // Bez zámku by jinak kdokoli mohl přes ?step=2 založit dalšího administrátora.
                if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
                    @file_put_contents($lockFile, 'Uzamceno automaticky: ' . date('Y-m-d H:i:s'));
                    @unlink($pendingFile);
                    http_response_code(403);
                    die('Systém je již nainstalován.');
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);

                if ($stmt->fetchColumn() > 0) {
                    $error = 'Uživatelské jméno již existuje.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
                    $stmt->execute([$username, $hashed_password, $first_name, $last_name]);

                    @file_put_contents($lockFile, 'Instalace dokoncena (v2.0): ' . date('Y-m-d H:i:s'));
                    @unlink($pendingFile);
                    unset($_SESSION['install_token']);
                    $deleted = @unlink(__FILE__);

                    echo '<!DOCTYPE html>
                    <html lang="cs">
                    <head>
                        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Instalace dokončena</title>
                        <style>body{font-family:"Segoe UI",Tahoma,sans-serif;background:#f4f7f6;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px;} .box{background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,0.05);max-width:500px;text-align:center;border-top:4px solid #27ae60;} .btn{display:inline-block;background:#2980b9;color:#fff;border:none;padding:12px 25px;border-radius:4px;text-decoration:none;font-size:1.1em;margin-top:20px;font-weight:bold;}</style>
                    </head>
                    <body>
                        <div class="box">
                            <h2 style="margin-top:0;color:#27ae60;">Instalace CMMS v2.0 byla úspěšná!</h2>';

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
                error_log('CMMS install krok 2: ' . $e->getMessage());
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
    <title>Instalace CMMS Systému v2.0</title>
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
        <h2 style="margin-top:0;">CMMS Systém v2.0 – Instalace</h2>
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
