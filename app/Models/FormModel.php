<?php

class FormModel {
    public static function getAll() {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM form_templates ORDER BY id DESC")->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function create($title, $schemaJson, $estimatedMinutes = 15) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO form_templates (title, schema_json, estimated_minutes, is_active) VALUES (?, ?, ?, 1)");
        return $stmt->execute([$title, $schemaJson, (int)$estimatedMinutes]);
    }

    public static function update($id, $title, $schemaJson, $estimatedMinutes = 15) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE form_templates SET title = ?, schema_json = ?, estimated_minutes = ? WHERE id = ?");
        return $stmt->execute([$title, $schemaJson, (int)$estimatedMinutes, (int)$id]);
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM form_templates WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
