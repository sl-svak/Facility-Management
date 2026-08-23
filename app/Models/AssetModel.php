<?php

class AssetModel {
    public static function getAll() {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM assets ORDER BY id DESC")->fetchAll();
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

    public static function create($name, $description = null) {
        $pdo = Database::getConnection();
        $qrHash = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO assets (name, description, qr_hash, is_active) VALUES (?, ?, ?, 1)");
        return $stmt->execute([$name, $description, $qrHash]);
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
