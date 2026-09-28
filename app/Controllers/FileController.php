<?php
// app/Controllers/FileController.php

class FileController {
    public static function serve() {
        // 1. Kontrola, zda je uživatel přihlášen
        if (!Auth::isLoggedIn()) {
            http_response_code(403);
            exit;
        }
        
        $filename = $_GET['name'] ?? '';
        
        // 2. Ochrana proti Directory Traversal (povoleny pouze bezpečné znaky)
        // Toto nedovolí použít lomítka '/' nebo zpětná lomítka '\' pro vyskočení ze složky
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            http_response_code(400);
            die("Neplatný název souboru.");
        }

        $filepath = APP_ROOT . '/assets/uploads/' . $filename;
        
        // 3. Odeslání souboru
        if (file_exists($filepath)) {
            $mime = mime_content_type($filepath);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
        
        http_response_code(404);
        die("Soubor nenalezen.");
    }
}
