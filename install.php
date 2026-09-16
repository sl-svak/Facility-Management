<?php
session_start();

$baseDir    = __DIR__;
$configDir  = $baseDir . '/config';
$configFile = $configDir . '/config.php';

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

if (file_exists($configFile)) {
    require_once $configFile;
    if (defined('APP_ROOT')) {
        require_once APP_ROOT . '/app/Core/Database.php';
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            if ($stmt && (int)$stmt->fetchColumn() > 0) {
                header('Location: index.php?page=login');
                exit;
            } elseif ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'GET') {
                header('Location: install.php?step=2');
                exit;
            }
        } catch (Exception $e) {}
    }
}

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

                $configContent = "<?php\n" .
                    "define('DB_HOST', " . var_export($db_host, true) . ");\n" .
                    "define('DB_NAME', " . var_export($db_name, true) . ");\n" .
                    "define('DB_USER', " . var_export($db_user, true) . ");\n" .
                    "define('DB_PASS', " . var_export($db_pass, true) . ");\n" .
                    "define('APP_ROOT', __DIR__ . '/..');\n";
                
                if (@file_put_contents($configFile, $configContent) === false) {
                    throw new Exception("Nepodařilo se zapsat do souboru config.php.");
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
                        user_agent VARCHAR(255)
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

                    CREATE TABLE IF NOT EXISTS inspections (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_id INT NOT NULL,
                        form_template_id INT NOT NULL,
                        technician_id INT NULL,
                        status VARCHAR(20) NOT NULL,
                        data_json MEDIUMTEXT NOT NULL,
                        duration_seconds INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                        resolved_by INT NULL,
                        resolved_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
                    INSERT IGNORE INTO system_migrations (version) VALUES ('1.0.5');
                ");

                header('Location: install.php?step=2');
                exit;

            } catch (Exception $e) {
                $error = 'Chyba databáze: ' . $e->getMessage();
            }
        }
    } elseif ($step === 2) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');

        if (empty($username) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = 'Vyplňte prosím všechna pole.';
        } else {
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

                    header('Location: install.php?step=3');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Chyba: ' . $e->getMessage();
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
                <label>Heslo</label><input type="password" name="password" required>
                <button type="submit" class="btn" style="background: #27ae60;">Dokončit instalaci</button>
            </form>
        <?php elseif ($step === 3): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">Instalace úspěšná!</div>
            <a href="index.php?page=login" class="btn" style="text-align: center; text-decoration: none; display: block; box-sizing: border-box; background: #27ae60;">Přihlásit se</a>
        <?php endif; ?>
    </div>
</body>
</html>
