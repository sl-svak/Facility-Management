<?php

class ImageProcessor {
    
    /**
     * Bezpečně zpracuje Base64 obrázek, ověří ho, zmenší a uloží jako čistý JPEG.
     * 
     * @param string $base64String Zdrojový řetězec z formuláře
     * @param string $uploadDir Cílová složka (např. 'assets/uploads/')
     * @param int $maxMb Maximální povolená velikost (před dekódováním) v MB
     * @return string|false Relativní cesta k uloženému souboru nebo false při chybě
     */
    public static function processBase64($base64String, $uploadDir, $maxMb = 5) {
        // 1. Předběžná kontrola velikosti Base64 řetězce (Zabránění memory exhaustion)
        $maxBytes = $maxMb * 1024 * 1024;
        $approxSize = strlen($base64String) * 0.75; // Base64 je o 33% větší než binárka
        
        if ($approxSize > $maxBytes) {
            error_log("ImageProcessor: Obrázek překročil limit {$maxMb} MB.");
            return false;
        }

        // 2. Extrakce hlavičky a dat
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64String, $matches)) {
            error_log("ImageProcessor: Neplatný formát Base64 URI.");
            return false;
        }
        $base64Data = $matches[2];

        // 3. Striktní dekódování
        $decodedData = base64_decode($base64Data, true);
        if ($decodedData === false) {
            error_log("ImageProcessor: Selhalo striktní dekódování Base64.");
            return false;
        }

        // 4. Skutečná kontrola MIME typu a rozměrů (ochrana proti podvržení)
        $imageInfo = @getimagesizefromstring($decodedData);
        if ($imageInfo === false) {
            error_log("ImageProcessor: Data neobsahují platný obrázek.");
            return false;
        }

        // Povolíme pouze JPEG, PNG a WEBP
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if (!in_array($imageInfo[2], $allowedTypes)) {
            error_log("ImageProcessor: Nepodporovaný formát obrázku (detekováno: {$imageInfo[2]}).");
            return false;
        }

        // 5. Načtení do GD knihovny (Tím se zbavíme škodlivého kódu v EXIF a pod.)
        $img = @imagecreatefromstring($decodedData);
        if (!$img) {
            return false;
        }

        // 6. Optimalizace rozměrů (Maximálně 1200px delší strana pro úsporu místa a paměti)
        $width = imagesx($img);
        $height = imagesy($img);
        $maxDim = 1200;

        if ($width > $maxDim || $height > $maxDim) {
            $ratio = $width / $height;
            if ($ratio > 1) {
                $newWidth = $maxDim;
                $newHeight = (int)($maxDim / $ratio);
            } else {
                $newHeight = $maxDim;
                $newWidth = (int)($maxDim * $ratio);
            }

            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            
            // Zachování průhlednosti pro případné PNG
            if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_WEBP) {
                imagealphablending($newImg, false);
                imagesavealpha($newImg, true);
                $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                imagefilledrectangle($newImg, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($img);
            $img = $newImg;
        }

        // 7. Zajištění existence cílové složky
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        // 8. Uložení jako standardizovaný JPEG (Kvalita 80% je ideální kompromis)
        // Vygenerujeme zcela unikátní a bezpečný název souboru
        $filename = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $destPath = rtrim($uploadDir, '/') . '/' . $filename;

        // Vždy ukládáme jako JPEG pro konzistenci
        $success = imagejpeg($img, $destPath, 80);
        imagedestroy($img);

        return $success ? $destPath : false;
    }
}
