<?php

class FileController {
    public static function serve() {
        if (!Auth::isLoggedIn()) {
            http_response_code(403); die("Přístup odepřen.");
        }

        $filename = $_GET['name'] ?? '';
        $safeFilename = basename($filename);
        if ($safeFilename === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $safeFilename)) {
            http_response_code(400);
            die("Neplatný název souboru.");
        }

        $filepath = APP_ROOT . '/assets/uploads/' . $safeFilename;
        if (!file_exists($filepath) || !is_file($filepath)) {
            http_response_code(404); die("Soubor nenalezen.");
        }

        $pdo = Database::getConnection();

        // Dohledání VŠECH zařízení, ke kterým je soubor přiřazen (přesná shoda názvu v uvozovkách).
        // Přístup je povolen jen tehdy, když má uživatel právo ke každému z nich - zabrání to
        // "podvržení" cizího názvu souboru do vlastní revize.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $safeFilename);
        $quoted  = '%' . $escaped . '"%';
        $suffix  = '%' . $escaped;
        $stmt = $pdo->prepare("
            SELECT asset_id FROM inspections WHERE data_json LIKE ?
            UNION
            SELECT asset_id FROM tickets WHERE resolution_photos LIKE ? OR resolution_signature LIKE ?
        ");
        $stmt->execute([$quoted, $quoted, $suffix]);
        $assetIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($assetIds)) {
            // Soubor není k ničemu přiřazen (osiřelý) - smí ho vidět jen administrátor.
            if (!Auth::isAdmin()) {
                http_response_code(403); die("Bezpečnostní chyba: Nemáte oprávnění k zobrazení tohoto souboru.");
            }
        } else {
            foreach ($assetIds as $assetId) {
                if (!Auth::canAccessAsset($assetId)) {
                    http_response_code(403); die("Bezpečnostní chyba: Nemáte oprávnění k zobrazení tohoto souboru.");
                }
            }
        }

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        // PŘIDÁNO: Podpora pro PDF protokoly z budoucího modulu revizí
        $mimeTypes = [
            'png' => 'image/png', 
            'jpg' => 'image/jpeg', 
            'jpeg' => 'image/jpeg', 
            'webp' => 'image/webp', 
            'heic' => 'image/heic',
            'pdf' => 'application/pdf'
        ];
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        while (ob_get_level()) { ob_end_clean(); }

        header('Content-Type: ' . $mimeType);
        // PŘIDÁNO: Říká prohlížeči, aby se pokusil PDF/obrázek zobrazit přímo v okně
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=86400');
        readfile($filepath); exit;
    }
}
