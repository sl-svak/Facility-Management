<?php

class SettingsController {
    
    public static function index() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        renderView('settings', [
            'pageTitle' => 'Globální nastavení systému',
            'settings' => $settings
        ]);
    }

    public static function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo = Database::getConnection();
            
            // Uložení textových nastavení
            $settings = $_POST['settings'] ?? [];
            
            // Zajistíme uložení checkboxu HTTPS (pokud není zaškrtnut, POST ho vůbec nepošle)
            if (!isset($settings['force_https'])) {
                $settings['force_https'] = '0';
            }

            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            // --- BEZPEČNÉ ZPRACOVÁNÍ UPLOADU FAVICONY ---
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['favicon']['tmp_name'];
                $size = filesize($tmpName);

                // 1. Striktní kontrola velikosti (max 2 MB)
                if ($size > 2 * 1024 * 1024) {
                    die("Bezpečnostní chyba: Soubor favicony je příliš velký (max 2 MB).");
                }

                // 2. Kontrola skutečného MIME typu souboru (nikoliv přípony)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                $allowedMimes = ['image/png', 'image/jpeg', 'image/x-icon', 'image/vnd.microsoft.icon'];
                if (!in_array($mime, $allowedMimes)) {
                    die("Bezpečnostní chyba: Nepovolený formát. Povoleny jsou pouze PNG, JPG a ICO (SVG je zakázáno z důvodu XSS rizika).");
                }

                // 3. Validace obrazových dat (Odhalí zfalšované hlavičky)
                $imgInfo = @getimagesize($tmpName);
                if ($imgInfo === false) {
                    die("Bezpečnostní chyba: Soubor neobsahuje platná obrazová data.");
                }

                $destDir = APP_ROOT . '/assets/';
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }

                $destPath = '';

                // 4. Bezpečná sanitizace přes GD knihovnu
                if ($imgInfo[2] === IMAGETYPE_PNG || $imgInfo[2] === IMAGETYPE_JPEG) {
                    $imgString = file_get_contents($tmpName);
                    $img = @imagecreatefromstring($imgString);
                    
                    if ($img) {
                        // Generování unikátního názvu, abychom zamezili cachování starých ikon
                        $destPath = 'assets/favicon_' . time() . '.png';
                        $absoluteDest = APP_ROOT . '/' . $destPath;
                        
                        // Normalizace a překreslení na standardní Favicon rozměr 128x128px
                        $newImg = imagecreatetruecolor(128, 128);
                        
                        // Zachování průhlednosti pro PNG
                        imagealphablending($newImg, false);
                        imagesavealpha($newImg, true);
                        $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                        imagefilledrectangle($newImg, 0, 0, 128, 128, $transparent);
                        
                        // Redukce rozměrů (zbavení payloadu)
                        imagecopyresampled($newImg, $img, 0, 0, 0, 0, 128, 128, imagesx($img), imagesy($img));
                        
                        imagepng($newImg, $absoluteDest, 9);
                        imagedestroy($img);
                        imagedestroy($newImg);
                    } else {
                        die("Chyba při sanitizaci obrázku.");
                    }
                } elseif ($imgInfo[2] === IMAGETYPE_ICO) {
                    // Formát .ico knihovna GD nativně neumí, ale ověřili jsme ho přes MIME a getimagesize()
                    $destPath = 'assets/favicon_' . time() . '.ico';
                    $absoluteDest = APP_ROOT . '/' . $destPath;
                    move_uploaded_file($tmpName, $absoluteDest);
                }

                // 5. Uložení bezpečné cesty do databáze
                if (!empty($destPath)) {
                    $stmt->execute(['favicon_path', $destPath]);
                }
            }
            // ----------------------------------------------------

            header('Location: index.php?page=settings&success=1');
            exit;
        }
    }
}
