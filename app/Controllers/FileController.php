<?php

class FileController {
    public static function serve() {
        // 1. Ochrana: Vydáváme soubory pouze přihlášeným zaměstnancům
        if (!Auth::isLoggedIn()) {
            http_response_code(403);
            die("Přístup odepřen.");
        }

        $filename = $_GET['name'] ?? '';
        
        // 2. Ochrana proti Directory Traversal (LFI)
        // basename() nekompromisně ořízne cesty typu "../../../etc/passwd" jen na jméno souboru
        $safeFilename = basename($filename);

        if (empty($safeFilename)) {
            http_response_code(400);
            exit;
        }

        $filepath = APP_ROOT . '/assets/uploads/' . $safeFilename;

        if (!file_exists($filepath) || !is_file($filepath)) {
            http_response_code(404);
            die("Soubor nenalezen.");
        }

        // 3. Bezpečné určení MIME typu
        $mimeType = mime_content_type($filepath);
        if (!$mimeType) {
            $mimeType = 'application/octet-stream';
        }

        // 4. Odeslání souboru přes PHP
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filepath));
        
        // Zamezení cachování citlivých dat na veřejných proxy serverech
        header('Cache-Control: private, max-age=86400');
        
        readfile($filepath);
        exit;
    }
}
