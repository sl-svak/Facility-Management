<?php
class RecordController {
    public static function index() {
        $pdo = Database::getConnection();
        
        $filter_asset = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
        $filter_status = $_GET['status'] ?? '';
        
        $params = [];
        $where = "1=1";
        
        if ($filter_asset > 0) {
            $where .= " AND i.asset_id = ?";
            $params[] = $filter_asset;
        }
        if ($filter_status !== '') {
            $where .= " AND i.status = ?";
            $params[] = $filter_status;
        }
        
        $sql = "
            SELECT i.*, a.name as asset_name, t.title as template_name, 
                   u.first_name, u.last_name,
                   tk.status as ticket_status, tk.resolution_text, tk.resolution_signature, tk.resolution_photos, tk.resolved_at,
                   ru.first_name as res_first_name, ru.last_name as res_last_name
            FROM inspections i
            JOIN assets a ON i.asset_id = a.id
            JOIN form_templates t ON i.form_template_id = t.id
            LEFT JOIN users u ON i.technician_id = u.id
            LEFT JOIN tickets tk ON tk.inspection_id = i.id
            LEFT JOIN users ru ON tk.resolved_by = ru.id
            WHERE $where
            ORDER BY i.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $inspections = $stmt->fetchAll();
        
        $assets = $pdo->query("SELECT id, name FROM assets WHERE is_active = 1 ORDER BY name")->fetchAll();
        
        renderView('inspections', [
            'pageTitle' => 'Záznamy a revize',
            'inspections' => $inspections,
            'assets' => $assets,
            'filter_asset' => $filter_asset,
            'filter_status' => $filter_status
        ]);
    }
}
