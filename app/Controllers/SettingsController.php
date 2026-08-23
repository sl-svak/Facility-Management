<?php
class SettingsController {
    
    // Zobrazení stránky nastavení
    public static function index() {
        $settings = SettingModel::getAll();
        renderView('settings', [
            'pageTitle' => 'Globální nastavení',
            'settings' => $settings
        ]);
    }

    // Uložení odeslaného formuláře
    public static function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // 1. DYNAMICKÉ ULOŽENÍ VŠECH TEXTOVÝCH HODNOT
            // Zpracuje automaticky cokoliv, co ve formuláři nese jméno settings[něco]
            if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                foreach ($_POST['settings'] as $key => $value) {
                    SettingModel::set($key, trim($value));
                }
            }

            // 2. ZPRACOVÁNÍ SOUBORU (Favicona má specifickou logiku)
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['favicon']['tmp_name'];
                $name = basename($_FILES['favicon']['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Povolené formáty obrázků
                if (in_array($ext, ['ico', 'png', 'svg', 'jpg', 'jpeg'])) {
                    $uploadDir = APP_ROOT . '/assets/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Bezpečné uložení pod stálým jménem
                    $targetFile = $uploadDir . 'favicon.' . $ext;
                    if (move_uploaded_file($tmpName, $targetFile)) {
                        // Přidáme timestamp, aby prohlížeče okamžitě ignorovaly starou cache
                        SettingModel::set('favicon_path', 'assets/favicon.' . $ext . '?v=' . time());
                    }
                }
            }

            header('Location: index.php?page=settings&success=1');
            exit;
        }
    }
}
