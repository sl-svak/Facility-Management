<?php
class FormController {
    
    // Zobrazení stránky s tvůrcem formulářů a seznamem existujících
    public static function index() {
        $templates = FormModel::getAll();
        $editTemplate = null;

        // Pokud uživatel klikl na úpravu, načteme data konkrétní šablony
        if (isset($_GET['edit_id'])) {
            $editTemplate = FormModel::getById((int)$_GET['edit_id']);
        }
        
        renderView('forms', [
            'pageTitle' => 'Šablony formulářů',
            'templates' => $templates,
            'editTemplate' => $editTemplate
        ]);
    }

    // Uložení naklikaného formuláře (Zvládá INSERT i UPDATE)
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $schema_json = $_POST['schema_json'] ?? '[]';
            $estimated_minutes = (int)($_POST['estimated_minutes'] ?? 0);
            
            // Základní kontrola
            if (!empty($title) && $schema_json !== '[]') {
                if ($id > 0) {
                    // Aktualizace existujícího
                    FormModel::update($id, $title, $schema_json, $estimated_minutes);
                } else {
                    // Tvorba nového
                    FormModel::create($title, $schema_json, $estimated_minutes);
                }
            }
        }
        header('Location: index.php?page=forms');
        exit;
    }

    // Smazání šablony
    public static function delete() {
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            FormModel::delete($id);
        }
        header('Location: index.php?page=forms');
        exit;
    }
}
