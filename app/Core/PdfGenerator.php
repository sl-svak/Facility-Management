<?php

class PdfGenerator {

    private static function bootstrapMpdf() {
        $candidates = [
            APP_ROOT . '/app/libs/mpdf/autoload.php',
            APP_ROOT . '/app/libs/mpdf/vendor/autoload.php',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\Mpdf\\Mpdf')) {
                    return true;
                }
            }
        }

        $srcDir = APP_ROOT . '/app/libs/mpdf/src';
        if (is_dir($srcDir)) {
            spl_autoload_register(function ($class) use ($srcDir) {
                $prefix = 'Mpdf\\';
                if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                    return;
                }
                $rel = substr($class, strlen($prefix));
                $path = $srcDir . '/' . str_replace('\\', '/', $rel) . '.php';
                if (file_exists($path)) {
                    require_once $path;
                }
            });
            if (class_exists('\\Mpdf\\Mpdf')) {
                return true;
            }
        }

        return false;
    }

    public static function renderSafeImageHtml($value, $maxHeightPx = 100) {
        $maxHeightPx = max(20, min(400, (int)$maxHeightPx));

        if (!is_string($value) || $value === '') {
            return '';
        }

        if (strpos($value, 'data:image/') === 0) {
            if (!preg_match('#^data:image/(png|jpe?g|gif);base64,#i', $value)) {
                return '<i>[Formát obrázku v PDF není podporován]</i>';
            }
            $safeSrc = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return '<img src="' . $safeSrc . '" height="' . $maxHeightPx . '" style="margin-right: 8px;" />';
        }

        $safeName = basename($value);
        if ($safeName === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $safeName)) {
            return '<i>[Neplatný název obrázku]</i>';
        }

        $absPath = APP_ROOT . '/assets/uploads/' . $safeName;
        if (!is_file($absPath)) {
            return '<i>[Fotografie nenalezena]</i>';
        }

        $info = @getimagesize($absPath);
        if ($info === false) {
            return '<i>[Neplatná obrazová data]</i>';
        }

        $imageType = $info[2] ?? 0;
        if ($imageType === IMAGETYPE_WEBP) {
            return '<i>[Obrázek WEBP nelze přímo vložit do PDF]</i>';
        }

        if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            return '<i>[Nepodporovaný formát obrázku]</i>';
        }

        $safePath = htmlspecialchars($absPath, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $safePath . '" height="' . $maxHeightPx . '" style="margin-right: 8px;" />';
    }

    public static function outputHtml($html, $filename, array $options = []) {
        if (!self::bootstrapMpdf()) {
            http_response_code(500);
            die("Chyba: Knihovna mPDF není na serveru nainstalována (očekávána v app/libs/mpdf/).");
        }

        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '120');

        $tempDir = APP_ROOT . '/app/libs/mpdf/tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'margin_header' => 6,
            'margin_footer' => 8,
            'default_font' => 'dejavusans',
        ];
        if (is_dir($tempDir) && is_writable($tempDir)) {
            $mpdfConfig['tempDir'] = $tempDir;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $mpdf = new \Mpdf\Mpdf($mpdfConfig);
        $mpdf->SetTitle((string)($options['title'] ?? $filename));
        $mpdf->SetAuthor((string)($options['author'] ?? 'CMMS v2.0'));
        $mpdf->SetCreator('CMMS v2.0 (mPDF)');

        $footerText = (string)($options['footerText'] ?? 'Vygenerováno systémem CMMS v2.0 | Strana {PAGENO} z {nbpg}');
        $mpdf->SetHTMLFooter('<div style="font-size: 8pt; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 4px;">' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '</div>');

        $mpdf->WriteHTML((string)$html);

        $dest = (string)($options['destination'] ?? 'I');
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$filename);

        if ($dest === 'S') {
            return (string)$mpdf->Output($cleanName, \Mpdf\Output\Destination::STRING_RETURN);
        }

        $mpdfDest = ($dest === 'D')
            ? \Mpdf\Output\Destination::DOWNLOAD
            : \Mpdf\Output\Destination::INLINE;

        $mpdf->Output($cleanName, $mpdfDest);
        exit;
    }
}
