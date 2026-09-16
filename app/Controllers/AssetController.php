<?php
class AssetController {
    
    // 1. Zobrazení stránky se seznamem a aplikovaným filtrem
    public static function index() {
        // Zjištění úseku aktuálně přihlášeného uživatele
        $currentUser = UserModel::getById($_SESSION['user_id']);
        $userDepts = $currentUser['departments'] ?? [];

        // Načtení strojů s filtrem (Model se sám rozhodne, co uživateli ukáže)
        $assets = AssetModel::getAll($userDepts);
        
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
            
            if (!empty($name)) {
                AssetModel::create($name, $description, $department_id);
            }
        }
        header('Location: index.php?page=assets');
        exit;
    }

    // 3. Odstranění zařízení
    public static function delete() {
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            AssetModel::delete($id);
        }
        header('Location: index.php?page=assets');
        exit;
    }

    // 4. Zobrazení formuláře pro úpravu zařízení
    public static function edit() {
        $id = (int)($_GET['id'] ?? 0);
        $asset = AssetModel::getById($id);
        
        if (!$asset) {
            header('Location: index.php?page=assets');
            exit;
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
                AssetModel::update($id, $name, $description, $department_id);
            }
        }
        header('Location: index.php?page=assets');
        exit;
    }
}
