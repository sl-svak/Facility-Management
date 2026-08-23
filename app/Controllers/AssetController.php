<?php
class AssetController {
    
    // 1. Zobrazení stránky se seznamem
    public static function index() {
        $assets = AssetModel::getAll();
        
        renderView('assets', [
            'pageTitle' => 'Správa zařízení',
            'assets' => $assets
        ]);
    }

    // 2. Vytvoření nového zařízení po odeslání formuláře
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($name)) {
                // QR kód se generuje automaticky uvnitř modelu
                AssetModel::create($name, $description);
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
}
