<?php

class ImageProcessor {
    
    // Ochrana proti Pixel Flood útokům (Maximálně 20 Megapixelů, např. 5000x4000)
    private static $MAX_PIXELS = 20000000;

    public static function processBase64($base64String, $uploadDir, $maxMb = 5) {
        if (empty($base64String)) return null;

        // 1. Kontrola formátu a extrakce dat
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            return null;
        }
        
        $extension = strtolower($type[1]);
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            return null;
        }

        $base64Data = substr($base64String, strpos($base64String, ',') + 1);
        $decodedData = base64_decode($base64Data, true);
        
        if ($decodedData === false) return null;

        // 2. OCHRANA: Velikost payloadu (v bajtech)
        $maxBytes = $maxMb * 1024 * 1024;
        if (strlen($decodedData) > $maxBytes) {
            error_log("Zablokován velký soubor: " . strlen($decodedData) . " B");
            return null;
        }

        // 3. Rychlé zjištění rozměrů BEZ alokace obrazu do RAM
        $imageInfo = @getimagesizefromstring($decodedData);
        if (!$imageInfo) return null;

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // 4. OCHRANA PROTI MEMORY DoS (Decompression Bomb)
        $totalPixels = $width * $height;
        if ($totalPixels > self::$MAX_PIXELS) {
            error_log("Odmítnut útok Pixel Flood! Obrázek má $totalPixels pixelů.");
            return null;
        }

        // -----------------------------------------------------------
        // AŽ NYNÍ JE ZCELA BEZPEČNÉ ROZBALIT OBRÁZEK DO PAMĚTI RAM
        // -----------------------------------------------------------
        $img = @imagecreatefromstring($decodedData);
        if (!$img) return null;

        // 5. Automatický Resize (na max 1920x1080) pro úsporu místa na disku
        $maxWidth = 1920;
        $maxHeight = 1080;
        
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            
            // Zachování průhlednosti
            if ($extension === 'png' || $extension === 'webp') {
                imagealphablending($newImg, false);
                imagesavealpha($newImg, true);
                $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
                imagefilledrectangle($newImg, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($img);
            $img = $newImg;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Vždy převádíme do moderního a bezpečného WebP formátu
        $filename = 'img_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.webp';
        $filepath = $uploadDir . $filename;

        imagewebp($img, $filepath, 80);
        imagedestroy($img);

        return $filepath;
    }
}
