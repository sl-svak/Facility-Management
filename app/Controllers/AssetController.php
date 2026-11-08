<?php
class AssetController {
    
    // 1. Zobrazení stránky se seznamem a aplikovaným filtrem
    public static function index() {
        if (Auth::isAdmin()) {
            $assets = AssetModel::getAll();
        } else {
            $currentUser = UserModel::getById($_SESSION['user_id']);
            $userDepts = $currentUser['departments'] ?? [];
            $assets = empty($userDepts) ? [] : AssetModel::getAll($userDepts);
        }
        
        // Načtení všech úseků pro roletku ve formuláři
        $departments = DepartmentModel::getAll();
        
        renderView('assets', [
            'pageTitle' => 'Správa zařízení',
            'assets' => $assets,
            'departments' => $departments
        ]);
    }

    // QR kód zařízení generovaný lokálně (TCPDF) - hash zařízení neopouští server
    public static function qr() {
        $id = (int)($_GET['id'] ?? 0);
        $asset = AssetModel::getById($id);
        if (!$asset || !Auth::canAccessAsset($id)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9.-]+(:\d+)?$/', $host)) {
            http_response_code(400);
            die('Neplatná hlavička Host.');
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $scanUrl = ($isHttps ? 'https://' : 'http://') . $host . $path . '/index.php?page=scan&hash=' . urlencode($asset['qr_hash']);

        require_once APP_ROOT . '/app/tcpdf/tcpdf_barcodes_2d.php';
        $barcode = new TCPDF2DBarcode($scanUrl, 'QRCODE,M');
        $svg = $barcode->getBarcodeSVGcode(6, 6, 'black');

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: image/svg+xml');
        header('Cache-Control: private, no-store');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'');
        echo $svg;
        exit;
    }

    // 2. Vytvoření nového zařízení po odeslání formuláře
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            
            // BEZPEČNOSTNÍ ZÁMEK: Manažer může přidělovat stroje jen do svých úseků
            if (!Auth::isAdmin()) {
                $currentUser = UserModel::getById($_SESSION['user_id']);
                $userDepts = $currentUser['departments'] ?? [];
                if ($department_id === null || !in_array($department_id, $userDepts)) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Zařízení musí být přiřazeno do vašeho úseku.");
                }
            }
            
            if (!empty($name)) {
                AssetModel::create($name, $description, $department_id);
            }
        }
        header('Location: index.php?page=assets');
        exit;
    }

    // 3. Odstranění zařízení (Ochrana proti CSRF přes POST)
    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            if (!Auth::canAccessAsset($id)) {
                http_response_code(403);
                die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
            }
            
            AssetModel::delete($id);
        }
        header('Location: index.php?page=assets');
        exit;
    }

    // 4. Zobrazení formuláře pro úpravu zařízení
    public static function edit() {
        $id = (int)($_GET['id'] ?? 0);
        $asset = AssetModel::getById($id);
        
        if (!$asset || !Auth::canAccessAsset($id)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $departments = DepartmentModel::getAll();
        
        renderView('asset_edit', [
            'pageTitle' => 'Úprava zařízení: ' . $asset['name'],
            'asset' => $asset,
            'departments' => $departments
        ]);
    }

    // 5. Uložení upraveného zařízení
    public static function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            
            if ($id > 0 && !empty($name)) {
                // 1. Zkontrolujeme, zda má uživatel právo upravovat tento konkrétní stroj
                if (!Auth::canAccessAsset($id)) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
                }
                
                // 2. BEZPEČNOSTNÍ ZÁMEK: Zkontrolujeme, zda stroj nepřesouvá do cizího úseku
                if (!Auth::isAdmin()) {
                    $currentUser = UserModel::getById($_SESSION['user_id']);
                    $userDepts = $currentUser['departments'] ?? [];
                    if ($department_id === null || !in_array($department_id, $userDepts)) {
                        http_response_code(403);
                        die("Bezpečnostní chyba: Nemáte oprávnění přesunout zařízení do cizího úseku.");
                    }
                }

                AssetModel::update($id, $name, $description, $department_id);
            }
        }
        header('Location: index.php?page=assets');
        exit;
    }
}
