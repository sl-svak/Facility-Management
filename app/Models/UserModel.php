<?php
class UserModel {
    public static function getAll() {
        $pdo = Database::getConnection();
        return $pdo->query("
            SELECT u.*, GROUP_CONCAT(d.name SEPARATOR ', ') as department_name 
            FROM users u 
            LEFT JOIN user_departments ud ON u.id = ud.user_id 
            LEFT JOIN departments d ON ud.department_id = d.id 
            GROUP BY u.id 
            ORDER BY u.id ASC
        ")->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$id]);
        $user = $stmt->fetch();
        if ($user) {
            $stmtDept = $pdo->prepare("SELECT department_id FROM user_departments WHERE user_id = ?");
            $stmtDept->execute([(int)$id]);
            $user['departments'] = $stmtDept->fetchAll(PDO::FETCH_COLUMN); // Vrací pole ID úseků
        }
        return $user;
    }

    public static function create($username, $password, $firstName, $lastName, $role, $email = null, $departments = []) {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (username, password, force_password_change, first_name, last_name, role, email, is_active) VALUES (?, ?, 1, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $role, $email]);
            $userId = $pdo->lastInsertId();

            if (!empty($departments)) {
                $stmtDept = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
                foreach ($departments as $depId) { $stmtDept->execute([$userId, (int)$depId]); }
            }
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack(); return false;
        }
    }

    public static function update($id, $firstName, $lastName, $email, $role, $password = null, $departments = []) {
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $role, password_hash($password, PASSWORD_DEFAULT), (int)$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $role, (int)$id]);
            }

            $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?")->execute([(int)$id]);
            if (!empty($departments)) {
                $stmtDept = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
                foreach ($departments as $depId) { $stmtDept->execute([(int)$id, (int)$depId]); }
            }
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack(); return false;
        }
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
