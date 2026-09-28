<?php
// app/Core/ImageProcessor.php

class ImageProcessor {
    
    /**
     * Bezpečně zpracuje, ověří a uloží Base64 obrázek.
     * 
     * @param string $base64String Vstupní data z POST požadavku
     * @param string $uploadDir Cílová složka pro uložení
     * @return string|false Název uloženého souboru (např. 'img_6489...jpg') nebo false při chybě
     */
    public static function saveSecureBase64Image($base64String, $uploadDir = APP_ROOT . '/assets/uploads/') {
        // 1. Ochrana prázdného vstupu
        if (empty($base64String)) {
            return false;
        }

        // 2. Rozdělení hlavičky a samotných dat (data:image/jpeg;base64,...)
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            error_log("ImageProcessor: Neplatný formát Base64 data URI.");
            return false;
        }

        // 3. Bezpečné dekódování dat
        $data = substr($base64String, strpos($base64String, ',') + 1);
        $decodedData = base64_decode($data, true);

        if ($decodedData === false) {
            error_log("ImageProcessor: Nepodařilo se dekódovat Base64 řetězec.");
            return false;
        }

        // 4. Striktní validace skutečného obsahu pomocí finfo (odhalí maskované skripty)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decodedData);

        // Seznam povolených MIME typů a jejich odpovídajících přípon
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            error_log("ImageProcessor: Zablokován upload nepovoleného MIME typu: " . $mimeType);
            return false;
        }

        // 5. Vygenerování absolutně bezpečného a unikátního názvu souboru
        $extension = $allowedMimeTypes[$mimeType];
        $safeFilename = 'img_' . uniqid() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $targetFile = rtrim($uploadDir, '/') . '/' . $safeFilename;

        // Volitelně: Ujistíme se, že cílová složka existuje
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 6. Uložení souboru na disk
        if (file_put_contents($targetFile, $decodedData)) {
            // Bezpečnostní vrstva navíc: překreslení obrázku přes GD knihovnu
            // Toto zničí jakýkoliv škodlivý kód ukrytý v EXIF metadatech (tzv. Polyglot soubory)
            self::recreateImage($targetFile, $mimeType);
            
            return $safeFilename;
        }

        error_log("ImageProcessor: Nepodařilo se zapsat soubor na disk.");
        return false;
    }

    /**
     * Načte a znovu uloží obrázek, čímž zničí skrytý malware v metadatech.
     */
    private static function recreateImage($filePath, $mimeType) {
        $image = null;
        switch ($mimeType) {
            case 'image/jpeg': $image = @imagecreatefromjpeg($filePath); break;
            case 'image/png':  $image = @imagecreatefrompng($filePath); break;
            case 'image/gif':  $image = @imagecreatefromgif($filePath); break;
            case 'image/webp': $image = @imagecreatefromwebp($filePath); break;
        }

        if ($image) {
            // --- OPRAVA PRO PODPISY: Uložení průhlednosti (Alpha channel) ---
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            // Přepíšeme původní soubor čistou verzí
            switch ($mimeType) {
                case 'image/jpeg': imagejpeg($image, $filePath, 90); break;
                case 'image/png':  imagepng($image, $filePath); break;
                case 'image/gif':  imagegif($image, $filePath); break;
                case 'image/webp': imagewebp($image, $filePath, 90); break;
            }
            imagedestroy($image);
        }
    }
}
