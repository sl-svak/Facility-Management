<?php
class InspectionModel {
    
    // Uloží novou kontrolu do databáze a vrátí její ID (Nyní vč. doby trvání v sekundách)
    public static function create($asset_id, $form_template_id, $technician_id, $status, $data_json, $duration_seconds = 0) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO inspections (asset_id, form_template_id, technician_id, status, data_json, duration_seconds) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$asset_id, $form_template_id, $technician_id, $status, $data_json, $duration_seconds]);
        return $pdo->lastInsertId();
    }

    // Získá minulá měření pro chytré doplňování spotřeby
    public static function getLastReadings($asset_id, $form_template_id = null) {
        $pdo = Database::getConnection();
        
        if ($form_template_id) {
            // Získá úplně poslední záznam konkrétního formuláře na stroji
            $stmt = $pdo->prepare("SELECT data_json FROM inspections WHERE asset_id = ? AND form_template_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$asset_id, $form_template_id]);
            return $stmt->fetch();
        } else {
            // Získá posledních 5 jakýchkoliv záznamů na stroji
            $stmt = $pdo->prepare("SELECT data_json FROM inspections WHERE asset_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$asset_id]);
            return $stmt->fetchAll();
        }
    }
}
