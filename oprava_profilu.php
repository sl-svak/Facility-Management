<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';
require_once APP_ROOT . '/app/Core/Database.php';

try { 
    $pdo = Database::getConnection(); 
    echo "<h3>Oprava tabulky users (uživatelská nastavení)</h3>";
    
    $queries = [
        "ALTER TABLE users ADD COLUMN theme VARCHAR(20) DEFAULT 'auto';",
        "ALTER TABLE users ADD COLUMN font_size VARCHAR(20) DEFAULT 'normal';",
        "ALTER TABLE users ADD COLUMN qr_mode VARCHAR(20) DEFAULT 'auto';"
    ];

    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            echo "<span style='color:green;'>✓ Přidán sloupec.</span><br>";
        } catch (Exception $e) {
            echo "<span style='color:orange;'>Sloupec již existuje (nebo chyba): " . $e->getMessage() . "</span><br>";
        }
    }
    
    echo "<br><b>Oprava je hotová! Nyní půjde nastavení profilu normálně uložit.</b><br>";
    echo "<i>Tento soubor (oprava_profilu.php) nyní můžete z FTP smazat.</i>";

} catch (Exception $e) { 
    die("Kritická chyba databáze: " . $e->getMessage()); 
}
?>