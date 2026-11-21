<?php
// 1. ZÁKAZ VÝPISU CHYB (Ochrana před únikem struktury databáze)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

define('APP_ROOT', __DIR__);
$configFile = APP_ROOT . '/config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    die("Kritická chyba: Konfigurace nebyla nalezena. Nainstalujte systém přes install.php.");
}

require_once $configFile;
require_once APP_ROOT . '/app/Core/Database.php';

// 2. BEZPEČNOSTNÍ ŠTÍT: VYŽADOVÁNÍ ADMINA
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
session_start();

spl_autoload_register(function ($class_name) {
    $dirs = [APP_ROOT . '/app/Core/', APP_ROOT . '/app/Models/', APP_ROOT . '/app/Controllers/'];
    foreach ($dirs as $dir) {
        if (file_exists($dir . $class_name . '.php')) {
            require_once $dir . $class_name . '.php';
            return;
        }
    }
});

if (!class_exists('Auth') || !Auth::isLoggedIn() || !Auth::isAdmin()) {
    http_response_code(403);
    die('
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #e74c3c;">
            <h2 style="font-size: 2em;">Přístup odepřen</h2>
            <p style="color: #555;">Z bezpečnostních důvodů musíte být pro spuštění aktualizace přihlášeni jako <strong>Administrátor</strong>.</p>
            <a href="index.php?page=login" style="display: inline-block; padding: 10px 20px; background: #2980b9; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 15px;">Přejít k přihlášení</a>
        </div>
    ');
}

// -----------------------------------------------------------------------------
// 3. IDEMPOTENTNÍ LOGIKA AKTUALIZACE DATABÁZE PRO CMMS v2.0
// -----------------------------------------------------------------------------
try {
    $pdo = Database::getConnection();

    // A) Doplnění chybějících sloupců ze starších verzí
    try {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN resolution_photos TEXT NULL AFTER resolution_signature");
    } catch (PDOException $e) {
        // Ignorujeme, pokud sloupec již existuje
    }

    // A2) Stav zařízení: doplnění sloupce a sjednocení prázdných hodnot na 'running'
    try {
        $pdo->exec("ALTER TABLE assets ADD COLUMN operational_status VARCHAR(20) DEFAULT 'running'");
    } catch (PDOException $e) {
        // sloupec již existuje
    }
    $pdo->exec("UPDATE assets SET operational_status = 'running' WHERE operational_status IS NULL OR operational_status = ''");

    // B) Výkonnostní indexy
    $indexes = [
        "ALTER TABLE inspections ADD INDEX idx_inspections_created (created_at)",
        "ALTER TABLE inspections ADD INDEX idx_inspections_status (status)",
        "ALTER TABLE tickets ADD INDEX idx_tickets_status_created (status, created_at)",
        "ALTER TABLE login_logs ADD INDEX idx_login_logs_ip_time (ip_address, attempt_time)"
    ];

    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignorujeme, pokud index již existuje
        }
    }

    // C) Nové tabulky pro verzi 2.0 (externí revize, dodavatelé, výstupní šablony)
    // Datový typ cizích klíčů musí přesně odpovídat referencovaným sloupcům (INT vs. INT UNSIGNED),
    // jinak MySQL/MariaDB vytvoření tabulky s FOREIGN KEY odmítne.
    $colType = function ($table, $column) use ($pdo) {
        $st = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $column]);
        $t = $st->fetchColumn();
        return ($t !== false && preg_match('/^[a-z]+(\(\d+\))?( unsigned)?$/i', (string)$t)) ? (string)$t : 'INT';
    };
    $tAsset = $colType('assets', 'id');
    $tUser = $colType('users', 'id');
    $tForm = $colType('form_templates', 'id');

    $statements = [
        "CREATE TABLE IF NOT EXISTS audit_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            default_interval_months INT NOT NULL DEFAULT 12,
            default_deadline_type ENUM('strict', 'month') NOT NULL DEFAULT 'strict',
            color_hex VARCHAR(20) NOT NULL DEFAULT '#2980b9',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS external_suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            contact_person VARCHAR(150) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(150) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS external_audit_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id $tAsset NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS external_audit_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            asset_id $tAsset NOT NULL,
            supplier_id INT NULL,
            supplier_name VARCHAR(200) NULL,
            execution_date DATE NOT NULL,
            next_due_date DATE NOT NULL,
            status ENUM('waiting_protocol', 'completed') NOT NULL DEFAULT 'waiting_protocol',
            protocol_file VARCHAR(255) NULL,
            protocol_number VARCHAR(100) NULL,
            notes TEXT NULL,
            created_by $tUser NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ext_rec_plan_status (plan_id, status),
            INDEX idx_ext_rec_asset (asset_id),
            FOREIGN KEY (plan_id) REFERENCES external_audit_plans(id) ON DELETE CASCADE,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (supplier_id) REFERENCES external_suppliers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS output_form_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id $tAsset NOT NULL,
            source_form_template_id $tForm NULL,
            title VARCHAR(255) NOT NULL,
            paper_code VARCHAR(100) NULL,
            header_note TEXT NULL,
            fields_json MEDIUMTEXT NOT NULL,
            show_signatures TINYINT(1) NOT NULL DEFAULT 1,
            show_photos TINYINT(1) NOT NULL DEFAULT 1,
            created_by $tUser NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_out_tpl_asset (asset_id),
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (source_form_template_id) REFERENCES form_templates(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    // Každý příkaz zvlášť: vícepříkazové exec() by chybu v druhém a dalším příkazu tiše ignorovalo.
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    // D) Výchozí nastavení modulů a výchozí kategorie externích revizí
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('module_inspections', '1'),
        ('module_external_audits', '1'),
        ('module_output_templates', '1'),
        ('module_tickets', '1')");

    // Výchozí kategorie se přidají jen do prázdné tabulky (opakované spuštění nevytvoří duplicity)
    if ((int)$pdo->query("SELECT COUNT(*) FROM audit_categories")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO audit_categories (name, default_interval_months, default_deadline_type, color_hex) VALUES
            ('Revize elektro (do konce měsíce)', 12, 'month', '#2980b9'),
            ('Tlaková zařízení (přesně na den)', 12, 'strict', '#8e44ad'),
            ('Plynová a zdvihací zařízení', 12, 'strict', '#d35400')");
    }

    // E) Sdílený hosting: složka pro dočasné soubory mPDF
    $mpdfTmp = APP_ROOT . '/app/libs/mpdf/tmp';
    if (is_dir(dirname($mpdfTmp)) && !is_dir($mpdfTmp)) {
        @mkdir($mpdfTmp, 0755, true);
    }

    // F) Záznam o verzi 2.0
    try {
        $pdo->exec("INSERT IGNORE INTO system_migrations (version) VALUES ('2.0')");
    } catch (PDOException $e) {
        // Tabulka system_migrations nemusí existovat
    }

    $message = "Databáze, moduly a výkonnostní indexy byly úspěšně aktualizovány na verzi 2.0.";

    // -----------------------------------------------------------------------------
    // 4. AUTOMATICKÁ DEAKTIVACE (Samodestrukce / přejmenování)
    // -----------------------------------------------------------------------------
    if (@rename(__FILE__, __FILE__ . '.bak')) {
        $message .= "<br><br><strong style='color: #27ae60;'>Skript byl z bezpečnostních důvodů automaticky uzamčen (přejmenován).</strong>";
    } else {
        $message .= "<br><br><strong style='color: #e74c3c;'>POZOR: Server nedovolil automatické přejmenování souboru update.php! Smažte ho prosím neprodleně ručně přes FTP.</strong>";
    }

    echo '
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #2c3e50;">
            <h2 style="font-size: 2em; color: #27ae60;">Aktualizace na v2.0 dokončena</h2>
            <p style="color: #555; line-height: 1.6;">' . $message . '</p>
            <a href="index.php?page=dashboard" style="display: inline-block; padding: 10px 20px; background: #27ae60; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 15px;">Zpět do aplikace</a>
        </div>
    ';

} catch (Exception $e) {
    error_log("CMMS Update Error: " . $e->getMessage());

    die('
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #e74c3c;">
            <h2 style="font-size: 2em;">Chyba aktualizace</h2>
            <p style="color: #555;">Při aktualizaci databáze došlo k systémové chybě. Detaily byly bezpečně zapsány do chybového logu na serveru.</p>
            <p style="color: #555;">Zkontrolujte oprávnění nebo kontaktujte vývojáře.</p>
            <a href="index.php?page=dashboard" style="display: inline-block; padding: 10px 20px; background: #95a5a6; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 15px;">Zpět do aplikace</a>
        </div>
    ');
}
