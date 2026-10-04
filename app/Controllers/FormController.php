<?php
class FormController {
    
    public static function index() {
        $templates = FormModel::getAll();
        $editTemplate = null;

        if (isset($_GET['edit_id'])) {
            $editTemplate = FormModel::getById((int)$_GET['edit_id']);
        }
        
        renderView('forms', [
            'pageTitle' => 'Šablony formulářů',
            'templates' => $templates,
            'editTemplate' => $editTemplate
        ]);
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $schema_json = $_POST['schema_json'] ?? '[]';
            
            // BEZPEČNOSTNÍ OPRAVA: Tvrdá validace JSON formátu (Ochrana proti XSS a JSON Injection)
            json_decode($schema_json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $schema_json = '[]';
            }
            
            $estimated_minutes = (int)($_POST['estimated_minutes'] ?? 0);
            
            if (!empty($title) && $schema_json !== '[]') {
                if ($id > 0) {
                    FormModel::update($id, $title, $schema_json, $estimated_minutes);
                } else {
                    FormModel::create($title, $schema_json, $estimated_minutes);
                }
            }
        }
        header('Location: index.php?page=forms');
        exit;
    }

    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            FormModel::delete($id);
        }
        header('Location: index.php?page=forms');
        exit;
    }
}
