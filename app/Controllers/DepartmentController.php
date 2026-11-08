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

    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Smazáním úseku by jeho zařízení ztratila přiřazení a stala by se
                // viditelnou pro všechny uživatele, proto neprázdný úsek smazat nelze.
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE department_id = ?");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    header('Location: index.php?page=departments&error=not_empty');
                    exit;
                }
                DepartmentModel::delete($id);
            }
        }
        header('Location: index.php?page=departments');
        exit;
    }
}
