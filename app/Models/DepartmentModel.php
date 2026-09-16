<?php
class DepartmentModel {
    
    public static function getAll() {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($name) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
        return $stmt->execute([$name]);
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        // Poznámka: Stroje a uživatelé mají nastaveno ON DELETE SET NULL, 
        // takže pokud smažeš úsek, nikomu se nesmaže účet, jen mu úsek zmizí.
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
