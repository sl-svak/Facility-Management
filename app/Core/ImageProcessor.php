<?php

class ImageProcessor {

    public static function processBase64($base64String, $uploadDir) {
        // 1. Rozdělení hlavičky a samotných dat
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            return false;
        }

        $data = substr($base64String, strpos($base64String, ',') + 1);
        $type = strtolower($type[1]); // např. png, jpeg, webp
        
        // 2. Podporované formáty
        if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return false;
        }

        $decodedData = base64_decode($data);
        if ($decodedData === false) {
            return false;
        }

        // 3. Vytvoření zdrojového obrázku v paměti
        $im = @imagecreatefromstring($decodedData);
        if ($im === false) {
            return false;
        }

        $width = imagesx($im);
        $height = imagesy($im);

        // 4. Výpočet nové velikosti (Ochrana serveru před obřími fotkami z moderních mobilů)
        $maxWidth = 1920;
        $maxHeight = 1080;
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)round($width * $ratio);
            $newHeight = (int)round($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // 5. Vytvoření cílového plátna
        $newIm = imagecreatetruecolor($newWidth, $newHeight);

        // --- KLÍČOVÁ OPRAVA PRO PRŮHLEDNOST (ZABRÁNÍ ČERNÉMU POZADÍ) ---
        if ($type === 'png' || $type === 'webp') {
            // Vypneme prolínání barev, aby se zachovala čistá průhlednost
            imagealphablending($newIm, false);
            // Zapneme ukládání alfa kanálu
            imagesavealpha($newIm, true);
            // Vyplníme plátno 100% průhlednou barvou (místo výchozí černé)
            $transparent = imagecolorallocatealpha($newIm, 255, 255, 255, 127);
            imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // Pro formáty bez průhlednosti (JPEG) vyplníme pozadí bílou barvou
            $white = imagecolorallocate($newIm, 255, 255, 255);
            imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $white);
        }
        // --------------------------------------------------------------

        // 6. Překopírování dat na připravené plátno
        imagecopyresampled($newIm, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // 7. Generování unikátního názvu a cesty
        $filename = 'img_' . uniqid() . '_' . time() . '.' . $type;
        $filepath = rtrim($uploadDir, '/') . '/' . $filename;

        // 8. Uložení fyzického souboru
        $success = false;
        switch ($type) {
            case 'png':
                $success = imagepng($newIm, $filepath, 8); // Úroveň komprese 0-9
                break;
            case 'webp':
                $success = imagewebp($newIm, $filepath, 85); // Kvalita 0-100
                break;
            case 'gif':
                $success = imagegif($newIm, $filepath);
                break;
            default: // jpg, jpeg
                $success = imagejpeg($newIm, $filepath, 85); // Kvalita 0-100
                break;
        }

        // 9. Uvolnění paměti serveru
        imagedestroy($im);
        imagedestroy($newIm);

        return $success ? $filepath : false;
    }
}
