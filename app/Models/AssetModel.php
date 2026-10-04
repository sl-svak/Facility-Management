<?php
class AssetModel {
    
    // Získá pouze aktivní (nesmazaná) zařízení
    public static function getAll($user_departments = []) {
        $pdo = Database::getConnection();
        if (!empty($user_departments)) {
            $in = implode(',', array_map('intval', $user_departments));
            return $pdo->query("
                SELECT a.*, d.name as department_name 
                FROM assets a 
                LEFT JOIN departments d ON a.department_id = d.id 
                WHERE (a.department_id IN ($in) OR a.department_id IS NULL) AND a.is_active = 1
                ORDER BY a.id DESC
            ")->fetchAll();
        } else {
            return $pdo->query("
                SELECT a.*, d.name as department_name 
                FROM assets a 
                LEFT JOIN departments d ON a.department_id = d.id 
                WHERE a.is_active = 1
                ORDER BY a.id DESC
            ")->fetchAll();
        }
    }

    // Získá i smazaná zařízení (užitečné pro reporty a zachování historie kontrol)
    public static function getAllIncludingDeleted() {
        $pdo = Database::getConnection();
        return $pdo->query("
            SELECT a.*, d.name as department_name 
            FROM assets a 
            LEFT JOIN departments d ON a.department_id = d.id 
            ORDER BY a.id DESC
        ")->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function getByHash($hash) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE qr_hash = ?");
        $stmt->execute([$hash]);
        return $stmt->fetch();
    }

    public static function create($name, $description = null, $department_id = null) {
        $pdo = Database::getConnection();
        $qrHash = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO assets (name, description, qr_hash, department_id, is_active) VALUES (?, ?, ?, ?, 1)");
        return $stmt->execute([$name, $description, $qrHash, $department_id]);
    }

    public static function update($id, $name, $description = null, $department_id = null) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE assets SET name = ?, description = ?, department_id = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $department_id, (int)$id]);
    }

    // SOFT DELETE: Místo fyzického smazání pouze skryjeme zařízení
    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE assets SET is_active = 0 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
