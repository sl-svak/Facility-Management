<?php

class QrGenerator {

    private static function bootstrapQrLib() {
        $candidates = [
            APP_ROOT . '/app/libs/qrcode/autoload.php',
            APP_ROOT . '/app/libs/qrcode/vendor/autoload.php',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\chillerlan\\QRCode\\QRCode')) {
                    return true;
                }
            }
        }

        $srcDir = APP_ROOT . '/app/libs/qrcode/src';
        if (is_dir($srcDir)) {
            spl_autoload_register(function ($class) use ($srcDir) {
                $prefix = 'chillerlan\\QRCode\\';
                if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                    return;
                }
                $rel = substr($class, strlen($prefix));
                $path = $srcDir . '/' . str_replace('\\', '/', $rel) . '.php';
                if (file_exists($path)) {
                    require_once $path;
                }
            });
            if (class_exists('\\chillerlan\\QRCode\\QRCode')) {
                return true;
            }
        }

        return false;
    }

    public static function renderSvg($payload) {
        if (!self::bootstrapQrLib()) {
            return '';
        }

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M,
                'svgAddXmlDeclaration' => false,
                'imageBase64' => false,
            ]);

            $qr = new \chillerlan\QRCode\QRCode($options);
            $svg = (string)$qr->render((string)$payload);

            $pos = strpos($svg, '<svg');
            if ($pos === false) {
                return '';
            }
            $svg = substr($svg, $pos);

            if (strpos($svg, 'shape-rendering=') === false) {
                $svg = preg_replace('/<svg\b/', '<svg shape-rendering="crispEdges"', $svg, 1);
            }

            return $svg;
        } catch (\Throwable $e) {
            error_log('QR generování selhalo: ' . $e->getMessage());
            return '';
        }
    }
}
