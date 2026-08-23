<?php
class PlanModel {
    
    // Načte všechna aktivní pravidla pro údržbu včetně názvů zařízení a šablon
    public static function getAll() {
        $pdo = Database::getConnection();
        $sql = "
            SELECT r.id, a.name as asset_name, t.title as template_name, r.period_days, r.warning_days 
            FROM asset_form_rules r
            JOIN assets a ON r.asset_id = a.id
            JOIN form_templates t ON r.form_template_id = t.id
            ORDER BY a.name ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    // Uloží nový plán (pravidlo)
    public static function create($asset_id, $form_template_id, $period_days, $warning_days) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO asset_form_rules (asset_id, form_template_id, period_days, warning_days) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$asset_id, $form_template_id, $period_days, $warning_days]);
    }

    // Trvale smaže plán údržby
    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM asset_form_rules WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
