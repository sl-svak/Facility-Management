<?php
class PlanController {
    
    // Zobrazení stránky pro plánování
    public static function index() {
        $assets = AssetModel::getAll();
        $templates = FormModel::getAll();
        $rules = PlanModel::getAll();
        
        renderView('plans', [
            'pageTitle' => 'Plánování údržby',
            'assets' => $assets,
            'templates' => $templates,
            'rules' => $rules
        ]);
    }

    // Uložení nového plánu do databáze
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $asset_id = (int)$_POST['asset_id'];
            $form_template_id = (int)$_POST['form_template_id'];
            $period_days = (int)$_POST['period_days'];
            $warning_days = (int)$_POST['warning_days'];
            
            if ($asset_id > 0 && $form_template_id > 0 && $period_days > 0) {
                PlanModel::create($asset_id, $form_template_id, $period_days, $warning_days);
            }
        }
        header('Location: index.php?page=plans');
        exit;
    }

    // Odstranění plánu
    public static function delete() {
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            PlanModel::delete($id);
        }
        header('Location: index.php?page=plans');
        exit;
    }
}
