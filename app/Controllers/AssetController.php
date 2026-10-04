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

    // 2. Vytvoření nového zařízení po odeslání formuláře
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            
            // BEZPEČNOSTNÍ ZÁMEK: Manažer může přidělovat stroje jen do svých úseků
            if ($department_id !== null && !Auth::isAdmin()) {
                $currentUser = UserModel::getById($_SESSION['user_id']);
                $userDepts = $currentUser['departments'] ?? [];
                if (!in_array($department_id, $userDepts)) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění přiřadit zařízení do tohoto úseku.");
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
                if ($department_id !== null && !Auth::isAdmin()) {
                    $currentUser = UserModel::getById($_SESSION['user_id']);
                    $userDepts = $currentUser['departments'] ?? [];
                    if (!in_array($department_id, $userDepts)) {
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
