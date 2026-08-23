<?php
/**
 * Vzorový konfigurační soubor.
 * 
 * NÁVOD:
 * 1. Zkopírujte tento soubor a přejmenujte jej na 'config.php'
 * 2. Vyplňte skutečné přihlašovací údaje k vaší MySQL databázi
 * 3. Soubor 'config.php' je chráněn a nikdy jej nenahrávejte na veřejný Git
 */

// --- DATABÁZOVÉ PŘIPOJENÍ ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'nazev_vasi_databaze');
define('DB_USER', 'uzivatel_databaze');
define('DB_PASS', 'tajne_heslo');

// --- SYSTÉMOVÉ CESTY ---
define('APP_ROOT', __DIR__ . '/..');

// Výchozí název aplikace (lze přepsat v administraci)
if (!defined('DEFAULT_APP_NAME')) {
    define('DEFAULT_APP_NAME', 'CMMS Cosmonde');
}