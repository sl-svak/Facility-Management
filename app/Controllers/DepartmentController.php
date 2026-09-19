<?php
class DepartmentController {
    
    public static function index() {
        $departments = DepartmentModel::getAll();
        renderView('departments', [
            'pageTitle' => 'Správa úseků (Oddělení)',
            'departments' => $departments
        ]);
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            if (!empty($name)) {
                DepartmentModel::create($name);
            }
        }
        header('Location: index.php?page=departments');
        exit;
    }

    // Smazání úseku (PŘEPSÁNO NA POST)
    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                DepartmentModel::delete($id);
            }
        }
        header('Location: index.php?page=departments');
        exit;
    }
}
