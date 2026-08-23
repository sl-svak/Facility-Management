<?php
class TicketModel {
    
    // Získá všechny závady chytře seřazené (nejdříve otevřené)
    public static function getAllOrdered() {
        $pdo = Database::getConnection();
        $sql = "SELECT t.*, a.name as asset_name 
                FROM tickets t 
                JOIN assets a ON t.asset_id = a.id 
                ORDER BY CASE WHEN t.status = 'open' THEN 1 ELSE 2 END, t.created_at DESC";
        return $pdo->query($sql)->fetchAll();
    }

    // Získá kompletní detaily jednoho tiketu včetně historie a podpisů
    public static function getDetailById($id) {
        $pdo = Database::getConnection();
        $sql = "SELECT t.*, a.name as asset_name, i.data_json, i.created_at as insp_date, 
                       u.first_name, u.last_name, f.title as form_title,
                       ru.first_name as res_first_name, ru.last_name as res_last_name
                FROM tickets t 
                JOIN assets a ON t.asset_id = a.id 
                LEFT JOIN inspections i ON t.inspection_id = i.id
                LEFT JOIN form_templates f ON i.form_template_id = f.id
                LEFT JOIN users u ON i.technician_id = u.id
                LEFT JOIN users ru ON t.resolved_by = ru.id
                WHERE t.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Získá pouze nevyřešené závady pro konkrétní zařízení (např. pro skenování QR)
    public static function getOpenByAssetId($asset_id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, title, created_at FROM tickets WHERE asset_id = ? AND status = 'open' ORDER BY created_at DESC");
        $stmt->execute([$asset_id]);
        return $stmt->fetchAll();
    }

    // Založí nový tiket při selhání inspekce
    public static function create($inspection_id, $asset_id, $title) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO tickets (inspection_id, asset_id, title, status) VALUES (?, ?, ?, 'open')");
        return $stmt->execute([$inspection_id, $asset_id, $title]);
    }

    // Vyřeší tiket
    public static function resolve($ticket_id, $resolution_text, $signature_base64, $user_id) {
        $pdo = Database::getConnection();

        // Bezpečnostní pojistka pro hostitelskou databázi
        try { $pdo->exec("ALTER TABLE tickets ADD COLUMN resolution_text TEXT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE tickets ADD COLUMN resolution_signature TEXT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE tickets ADD COLUMN resolved_by INT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE tickets ADD COLUMN resolved_at TIMESTAMP NULL"); } catch (Exception $e) {}

        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed', resolution_text = ?, resolution_signature = ?, resolved_by = ?, resolved_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$resolution_text, $signature_base64, $user_id, $ticket_id]);
    }
}
