<?php
class SettingModel {
    // Načte všechna nastavení najednou
    public static function getAll() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    // Načte konkrétní hodnotu (pokud neexistuje, vrátí výchozí)
    public static function get($key, $default = '') {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    // Uloží nebo přepíše hodnotu
    public static function set($key, $value) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    }
}
