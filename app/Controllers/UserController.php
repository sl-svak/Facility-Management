<?php
class UserController {
    public static function index() {
        $users = UserModel::getAll();
        renderView('users', [
            'pageTitle' => 'Správa uživatelů',
            'users' => $users
        ]);
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username   = trim($_POST['username'] ?? '');
            $password   = $_POST['password'] ?? '';
            $firstName  = trim($_POST['first_name'] ?? '');
            $lastName   = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $role       = $_POST['role'] ?? 'technician';

            if (!empty($username) && !empty($password) && !empty($firstName) && !empty($lastName)) {
                UserModel::create($username, $password, $firstName, $lastName, $role, $email);
            }
        }
        header('Location: index.php?page=users');
        exit;
    }

    public static function edit() {
        $id = (int)($_GET['id'] ?? 0);
        $user = UserModel::getById($id);

        if (!$user) {
            header('Location: index.php?page=users');
            exit;
        }

        renderView('user_edit', [
            'pageTitle' => 'Úprava uživatele: ' . $user['username'],
            'user' => $user
        ]);
    }

    public static function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id         = (int)($_POST['id'] ?? 0);
            $firstName  = trim($_POST['first_name'] ?? '');
            $lastName   = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $role       = $_POST['role'] ?? 'technician';
            $password   = $_POST['password'] ?? '';

            if ($id > 0 && !empty($firstName) && !empty($lastName)) {
                UserModel::update($id, $firstName, $lastName, $email, $role, $password);
            }
        }
        header('Location: index.php?page=users&updated=1');
        exit;
    }

    public static function delete() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && $id != ($_SESSION['user_id'] ?? 0)) {
            UserModel::delete($id);
        }
        header('Location: index.php?page=users');
        exit;
    }
}
