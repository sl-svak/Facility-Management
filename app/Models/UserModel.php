<?php
class UserModel {
    public static function getAll() {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function create($username, $password, $firstName, $lastName, $role, $email = null) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role, email, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        return $stmt->execute([$username, $hashedPassword, $firstName, $lastName, $role, $email]);
    }

    public static function update($id, $firstName, $lastName, $email, $role, $password = null) {
        $pdo = Database::getConnection();
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
            return $stmt->execute([$firstName, $lastName, $email, $role, $hashedPassword, (int)$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
            return $stmt->execute([$firstName, $lastName, $email, $role, (int)$id]);
        }
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
