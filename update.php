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

// Načtení konfigurace a databáze
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
            require_once $dir . $class_name . '.php'; return;
        }
    }
});

// Pokud není uživatel přihlášený administrátor, okamžitě ho zablokujeme
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
// 3. LOGIKA AKTUALIZACE DATABÁZE A INDEXŮ
// -----------------------------------------------------------------------------
try {
    $pdo = Database::getConnection();
    
    // A) Doplnění chybějících sloupců z historických verzí
    try {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN resolution_photos TEXT NULL AFTER resolution_signature");
    } catch (PDOException $e) {
        // Ignorujeme chybu, pokud sloupec již existuje
    }

    // B) Přidání výkonnostních indexů pro rychlé vyhledávání a řazení
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
            // Ignorujeme, pokud index na dané tabulce už náhodou existuje
        }
    }
    
    $message = "Databáze a výkonnostní indexy byly úspěšně aktualizovány.";

    // -----------------------------------------------------------------------------
    // 4. AUTOMATICKÁ DEAKTIVACE (Samodestrukce)
    // -----------------------------------------------------------------------------
    if (@rename(__FILE__, __FILE__ . '.bak')) {
        $message .= "<br><br><strong style='color: #27ae60;'>Skript byl z bezpečnostních důvodů automaticky uzamčen (přejmenován).</strong>";
    } else {
        $message .= "<br><br><strong style='color: #e74c3c;'>POZOR: Server nedovolil automatické přejmenování souboru update.php! Smažte ho prosím neprodleně ručně přes FTP.</strong>";
    }

    echo '
        <div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: #2c3e50;">
            <h2 style="font-size: 2em; color: #27ae60;">Aktualizace dokončena</h2>
            <p style="color: #555; line-height: 1.6;">'.$message.'</p>
            <a href="index.php?page=dashboard" style="display: inline-block; padding: 10px 20px; background: #27ae60; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 15px;">Zpět do aplikace</a>
        </div>
    ';

} catch (Exception $e) {
    // NIKDY nevypisujeme $e->getMessage() přímo uživateli, zapisujeme do logu hostingu
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
