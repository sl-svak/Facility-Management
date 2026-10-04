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

        $relativePath = 'assets/uploads/' . $safeFilename;
        $pdo = Database::getConnection();
        
        // Dohledání asset_id podle výskytu souboru v JSON datech, textech, nebo v budoucnu v revizích
        $stmt = $pdo->prepare("
            SELECT asset_id FROM inspections WHERE data_json LIKE ? 
            UNION 
            SELECT asset_id FROM tickets WHERE resolution_photos LIKE ? OR resolution_signature LIKE ?
            /* PŘÍPRAVA PRO MODUL REVIZÍ - ZATÍM ZAKOMENTOVÁNO, DOKUD NEBUDOU TABULKY:
            UNION
            SELECT p.asset_id FROM external_audit_records r JOIN external_audit_plans p ON r.plan_id = p.id WHERE r.protocol_file LIKE ?
            */
            LIMIT 1
        ");
        $searchParam = '%' . $safeFilename . '%';
        
        // Až odkomentuješ revize, přidej do pole execute čtvrtý $searchParam
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
        $asset_id = $stmt->fetchColumn();

        // Pokud je soubor spárován se zařízením, ověříme přístup. 
        if (!$asset_id || !Auth::canAccessAsset($asset_id)) {
            http_response_code(403); die("Bezpečnostní chyba: Nemáte oprávnění k zobrazení tohoto souboru.");
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
