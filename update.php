<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';
require_once APP_ROOT . '/app/Core/Database.php';

try { $pdo = Database::getConnection(); } catch (Exception $e) { die("Chyba databáze: " . $e->getMessage()); }

$stmt = $pdo->query("SELECT version FROM system_migrations ORDER BY applied_at DESC LIMIT 1");
$currentVersion = $stmt->fetchColumn() ?: '1.0.0';
echo "<h3>Aktuální verze: $currentVersion</h3>";

$updates = [
    // HISTORICKÝ KROK: Vytvoření tabulky úseků a příprava struktury (přechod z 1.0.1)
    '1.0.2' => [
        "CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "ALTER TABLE assets ADD COLUMN IF NOT EXISTS department_id INT NULL DEFAULT NULL;",
        "ALTER TABLE assets ADD CONSTRAINT fk_asset_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;",
        
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS department_id INT NULL DEFAULT NULL;",
        "ALTER TABLE users ADD CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;"
    ],
    
    // AKTUÁLNÍ KROK: Přechod na vícenásobné úseky (check-boxy)
    '1.0.5' => [
        "CREATE TABLE IF NOT EXISTS user_departments (
            user_id INT NOT NULL,
            department_id INT NOT NULL,
            PRIMARY KEY (user_id, department_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "INSERT IGNORE INTO user_departments (user_id, department_id) SELECT id, department_id FROM users WHERE department_id IS NOT NULL;",
        
        "ALTER TABLE users DROP FOREIGN KEY fk_user_dept;",
        "ALTER TABLE users DROP COLUMN department_id;"
    ]
];

$updated = false;
foreach ($updates as $version => $queries) {
    if (version_compare($version, $currentVersion, '>')) {
        echo "Aplikuji verzi $version...<br>";
        try {
            $pdo->beginTransaction();
            foreach ($queries as $query) { 
                // Ignorujeme chyby duplicitních klíčů/sloupců při historických updatech
                try { $pdo->exec($query); } catch (PDOException $subE) {} 
            }
            $pdo->prepare("INSERT INTO system_migrations (version) VALUES (?)")->execute([$version]);
            $pdo->commit();
            $updated = true;
            echo "<span style='color:green;'>✓ Verze $version úspěšně aplikována.</span><br>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (strpos($e->getMessage(), 'check that column/key exists') !== false) {
                $pdo->query("INSERT IGNORE INTO system_migrations (version) VALUES ('$version')");
                echo "<span style='color:orange;'>✓ Verze $version byla přeskočena (již existuje).</span><br>";
                $updated = true;
            } else {
                die("<span style='color:red;'>Kritická chyba: " . $e->getMessage() . "</span>");
            }
        }
    }
}
if (!$updated) echo "Databáze je aktuální.<br>";
echo "<br><i>Smažte tento soubor (update.php).</i>";
?>
