<?php
class AssetController {

    public static function index() {
        if (Auth::isAdmin()) {
            $assets = AssetModel::getAll();
        } else {
            $currentUser = UserModel::getById($_SESSION['user_id']);
            $userDepts = $currentUser['departments'] ?? [];
            $assets = empty($userDepts) ? [] : AssetModel::getAll($userDepts);
        }

        $departments = DepartmentModel::getAll();

        renderView('assets', [
            'pageTitle' => 'Správa zařízení',
            'assets' => $assets,
            'departments' => $departments
        ]);
    }

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

        $svg = QrGenerator::renderSvg($scanUrl);

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        $name = htmlspecialchars($asset['name'], ENT_QUOTES, 'UTF-8');
        ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Tisk štítku: <?= $name ?></title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }
        h2 { margin-bottom: 20px; color: #2c3e50; font-size: 24px; word-wrap: break-word; }
        .qr { display: inline-block; background: #fff; border: 1px solid #ccc; padding: 10px; border-radius: 8px; }
        .qr svg { width: 250px; height: 250px; display: block; }
        .err { color: #c0392b; font-weight: bold; }
        button { padding: 12px 24px; font-size: 16px; cursor: pointer; background: #2980b9; color: #fff; border: none; border-radius: 6px; margin-top: 20px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <h2><?= $name ?></h2>
    <?php if ($svg !== ''): ?>
        <div class="qr"><?= $svg ?></div>
        <br><button type="button" id="printBtn">&#128424; Vytisknout štítek</button>
    <?php else: ?>
        <p class="err">QR kód se nepodařilo vygenerovat. Zkontrolujte, že na serveru existuje složka app/libs/qrcode/ a chybový log.</p>
    <?php endif; ?>
    <script src="assets/js/print-page.js"></script>
</body>
</html>
        <?php
        exit;
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

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

    public static function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $status = (($_POST['operational_status'] ?? 'running') === 'stopped') ? 'stopped' : 'running';

            if ($id > 0 && !empty($name)) {
                if (!Auth::canAccessAsset($id)) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
                }

                if (!Auth::isAdmin()) {
                    $currentUser = UserModel::getById($_SESSION['user_id']);
                    $userDepts = $currentUser['departments'] ?? [];
                    if ($department_id === null || !in_array($department_id, $userDepts)) {
                        http_response_code(403);
                        die("Bezpečnostní chyba: Nemáte oprávnění přesunout zařízení do cizího úseku.");
                    }
                }

                AssetModel::update($id, $name, $description, $department_id, $status);
            }
        }
        header('Location: index.php?page=assets');
        exit;
    }
}
