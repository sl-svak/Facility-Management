<?php

class SettingsController {

    public static function index() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        foreach (['module_inspections', 'module_external_audits', 'module_output_templates', 'module_tickets'] as $modKey) {
            if (!isset($settings[$modKey])) {
                $settings[$modKey] = '1';
            }
        }

        renderView('settings', [
            'pageTitle' => 'Globální nastavení systému',
            'settings' => $settings
        ]);
    }

    public static function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo = Database::getConnection();

            $allowedKeys = [
                'app_name',
                'app_font',
                'force_https',
                'shift_start_hour',
                'workweek_days',
                'module_inspections',
                'module_external_audits',
                'module_output_templates',
                'module_tickets',
            ];

            $incoming = (array)($_POST['settings'] ?? []);
            $settings = [];

            foreach ($incoming as $k => $v) {
                if (in_array($k, $allowedKeys, true) && is_scalar($v)) {
                    $settings[$k] = (string)$v;
                }
            }

            if (isset($settings['app_font']) && !in_array($settings['app_font'], ['default', 'condensed', 'system'], true)) {
                $settings['app_font'] = 'default';
            }

            $checkboxKeys = [
                'force_https',
                'module_inspections',
                'module_external_audits',
                'module_output_templates',
                'module_tickets',
            ];
            foreach ($checkboxKeys as $cbKey) {
                $settings[$cbKey] = (isset($settings[$cbKey]) && $settings[$cbKey] === '1') ? '1' : '0';
            }

            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            // --- BEZPEČNÉ ZPRACOVÁNÍ UPLOADU FAVICONY ---
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['favicon']['tmp_name'];
                $size = filesize($tmpName);

                if ($size > 2 * 1024 * 1024) {
                    http_response_code(400);
                    die("Bezpečnostní chyba: Soubor favicony je příliš velký (max 2 MB).");
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                $allowedMimes = ['image/png', 'image/jpeg', 'image/x-icon', 'image/vnd.microsoft.icon'];
                if (!in_array($mime, $allowedMimes, true)) {
                    http_response_code(400);
                    die("Bezpečnostní chyba: Nepovolený formát. Povoleny jsou pouze PNG, JPG a ICO (SVG je zakázáno z důvodu XSS rizika).");
                }

                $imgInfo = @getimagesize($tmpName);
                if ($imgInfo === false) {
                    http_response_code(400);
                    die("Bezpečnostní chyba: Soubor neobsahuje platná obrazová data.");
                }

                $destDir = APP_ROOT . '/assets/';
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }

                $destPath = '';

                if ($imgInfo[2] === IMAGETYPE_PNG || $imgInfo[2] === IMAGETYPE_JPEG) {
                    $imgString = file_get_contents($tmpName);
                    $img = @imagecreatefromstring($imgString);

                    if ($img) {
                        $destPath = 'assets/favicon_' . time() . '.png';
                        $absoluteDest = APP_ROOT . '/' . $destPath;

                        $newImg = imagecreatetruecolor(128, 128);
                        imagealphablending($newImg, false);
                        imagesavealpha($newImg, true);
                        $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                        imagefilledrectangle($newImg, 0, 0, 128, 128, $transparent);

                        imagecopyresampled($newImg, $img, 0, 0, 0, 0, 128, 128, imagesx($img), imagesy($img));

                        imagepng($newImg, $absoluteDest, 9);
                        imagedestroy($img);
                        imagedestroy($newImg);
                    } else {
                        http_response_code(500);
                        die("Chyba při sanitizaci obrázku.");
                    }
                } elseif ($imgInfo[2] === IMAGETYPE_ICO) {
                    $destPath = 'assets/favicon_' . time() . '.ico';
                    $absoluteDest = APP_ROOT . '/' . $destPath;
                    move_uploaded_file($tmpName, $absoluteDest);
                }

                if (!empty($destPath)) {
                    $stmt->execute(['favicon_path', $destPath]);
                }
            }

            header('Location: index.php?page=settings&success=1');
            exit;
        }
    }
}
