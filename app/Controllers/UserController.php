<?php
class UserController {

    public static function profile() {
        $forced = isset($_GET['forced']);
        $error = $_SESSION['profile_error'] ?? '';
        $success = $_SESSION['profile_success'] ?? '';
        unset($_SESSION['profile_error'], $_SESSION['profile_success']);

        $user = UserModel::getById($_SESSION['user_id']);

        renderView('profile', [
            'pageTitle' => 'Můj profil',
            'forced' => $forced,
            'error' => $error,
            'success' => $success,
            'user' => $user
        ]);
    }

    public static function savePreferences() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $theme = in_array($_POST['theme'], ['auto', 'light', 'dark']) ? $_POST['theme'] : 'auto';
            $fontSize = in_array($_POST['font_size'], ['normal', 'large']) ? $_POST['font_size'] : 'normal';
            $qrMode = in_array($_POST['qr_mode'], ['auto', 'show', 'hide']) ? $_POST['qr_mode'] : 'auto';
            $userId = $_SESSION['user_id'];

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE users SET theme = ?, font_size = ?, qr_mode = ? WHERE id = ?");
            $stmt->execute([$theme, $fontSize, $qrMode, $userId]);

            $_SESSION['theme'] = $theme;
            $_SESSION['font_size'] = $fontSize;
            $_SESSION['qr_mode'] = $qrMode;

            $_SESSION['profile_success'] = 'Nastavení vzhledu a chování bylo uloženo.';
        }
        header('Location: index.php?page=profile');
        exit;
    }

    public static function changePassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=profile');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $userId = $_SESSION['user_id'];
        $redirectSuffix = !empty($_SESSION['force_password_change']) ? '&forced=1' : '';

        if ($newPassword !== $confirmPassword) {
            $_SESSION['profile_error'] = 'Nová hesla se neshodují.';
            header('Location: index.php?page=profile' . $redirectSuffix); exit;
        }
        if (strlen($newPassword) < 6) {
            $_SESSION['profile_error'] = 'Nové heslo musí mít alespoň 6 znaků.';
            header('Location: index.php?page=profile' . $redirectSuffix); exit;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $_SESSION['profile_error'] = 'Současné heslo není správné.';
            header('Location: index.php?page=profile' . $redirectSuffix); exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
        $updateStmt->execute([$newHash, $userId]);

        $_SESSION['force_password_change'] = false; 
        $_SESSION['profile_success'] = 'Heslo bylo úspěšně změněno. Nyní můžete pokračovat v práci.';
        header('Location: index.php?page=profile'); exit;
    }

    public static function index() {
        $users = UserModel::getAll();
        $departments = DepartmentModel::getAll();
        renderView('users', [
            'pageTitle' => 'Správa uživatelů',
            'users' => $users,
            'departments' => $departments
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
            $departments = $_POST['departments'] ?? [];

            if (!empty($username) && !empty($password) && !empty($firstName) && !empty($lastName)) {
                UserModel::create($username, $password, $firstName, $lastName, $role, $email, $departments);
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

        $departments = DepartmentModel::getAll();

        renderView('user_edit', [
            'pageTitle' => 'Úprava uživatele: ' . $user['username'],
            'user' => $user,
            'departments' => $departments
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
            $departments = $_POST['departments'] ?? [];

            if ($id > 0 && !empty($firstName) && !empty($lastName)) {
                UserModel::update($id, $firstName, $lastName, $email, $role, $password, $departments);
            }
        }
        header('Location: index.php?page=users&updated=1');
        exit;
    }

    // 6. Odstranění uživatele (PŘEPSÁNO NA POST)
    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $id != ($_SESSION['user_id'] ?? 0)) {
                UserModel::delete($id);
            }
        }
        header('Location: index.php?page=users');
        exit;
    }
}
