<?php
class DashboardController {
    public static function index() {
        $pdo = Database::getConnection();

        // AUTO-PATCH DATABÁZE: Přidání sloupce pro provozní stav (pokud ještě neexistuje)
        try {
            $pdo->exec("ALTER TABLE assets ADD COLUMN operational_status VARCHAR(20) DEFAULT 'running'");
        } catch (Exception $e) { 
            // Ignorujeme chybu, sloupec už zřejmě existuje
        }

        // 1. ZÁKLADNÍ STATISTIKY (počítáme jen stroje v provozu)
        $stats = [];
        $stats['assets_count'] = $pdo->query("SELECT COUNT(*) FROM assets WHERE is_active = 1 AND operational_status = 'running'")->fetchColumn();
        $stats['inspections_count'] = $pdo->query("SELECT COUNT(*) FROM inspections")->fetchColumn();
        $stats['open_tickets'] = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();

        // 2. HISTORIE POSLEDNÍCH KONTROL
        $stmt = $pdo->query("
            SELECT i.id, i.status, i.created_at, a.name as asset_name, t.title as template_name, u.first_name, u.last_name
            FROM inspections i
            JOIN assets a ON i.asset_id = a.id
            JOIN form_templates t ON i.form_template_id = t.id
            LEFT JOIN users u ON i.technician_id = u.id
            ORDER BY i.created_at DESC
            LIMIT 10
        ");
        $recent_inspections = $stmt->fetchAll();

        // 3. SEMAFOR ÚDRŽBY
        
        // Načtení nastavení pracovního týdne (výchozí hodnota je 5 dní, pokud není nastaveno jinak)
        $workweek_days = SettingModel::get('workweek_days', 5);
        
        $stmt = $pdo->query("
            SELECT 
                r.id, 
                a.name as asset_name, 
                a.operational_status,
                t.title as template_name, 
                r.period_days, 
                r.warning_days,
                (SELECT MAX(created_at) FROM inspections i WHERE i.asset_id = r.asset_id AND i.form_template_id = r.form_template_id) as last_inspection
            FROM asset_form_rules r
            JOIN assets a ON r.asset_id = a.id
            JOIN form_templates t ON r.form_template_id = t.id
            WHERE a.is_active = 1 AND t.is_active = 1
        ");
        $rules = $stmt->fetchAll();

        $traffic_lights = [];
        $now = time();

        foreach ($rules as $rule) {
            // IGNOROVÁNÍ ODSTAVENÝCH STROJŮ
            if ($rule['operational_status'] === 'stopped') {
                continue; 
            }

            $item = [
                'asset_name' => $rule['asset_name'],
                'template_name' => $rule['template_name'],
                'last_inspection' => $rule['last_inspection'],
                'status' => 'green',
                'days_remaining' => 0
            ];

            if (!$rule['last_inspection']) {
                $item['status'] = 'red';
                $item['next_due_formatted'] = 'Ihned (Nekontrolováno)';
                $item['sort_score'] = 1;
            } else {
                $last_time = strtotime($rule['last_inspection']);
                $next_due = strtotime("+{$rule['period_days']} days", $last_time);
                
                // --- CHYTRÁ KOREKCE NA VÍKENDY ---
                if ((int)$workweek_days === 5) {
                    $day_of_week = date('N', $next_due); // Vrací 1 (Po) až 7 (Ne)
                    
                    if ($day_of_week == 6) { 
                        // Termín vychází na sobotu -> Posuneme o 2 dny na pondělí
                        $next_due = strtotime("+2 days", $next_due);
                    } elseif ($day_of_week == 7) { 
                        // Termín vychází na neděli -> Posuneme o 1 den na pondělí
                        $next_due = strtotime("+1 day", $next_due);
                    }
                }
                
                $warning_time = strtotime("-{$rule['warning_days']} days", $next_due);
                
                $item['next_due_formatted'] = date('d.m.Y', $next_due);
                $diff = $next_due - $now;
                $item['days_remaining'] = floor($diff / (60 * 60 * 24));

                if ($now > $next_due) {
                    $item['status'] = 'red'; 
                    $item['sort_score'] = 1;
                } elseif ($now >= $warning_time) {
                    $item['status'] = 'orange'; 
                    $item['sort_score'] = 2;
                } else {
                    $item['status'] = 'green'; 
                    $item['sort_score'] = 3;
                }
            }
            $traffic_lights[] = $item;
        }

        usort($traffic_lights, function($a, $b) {
            if ($a['sort_score'] == $b['sort_score']) {
                return $a['days_remaining'] <=> $b['days_remaining'];
            }
            return $a['sort_score'] <=> $b['sort_score'];
        });

        renderView('dashboard', [
            'pageTitle' => 'Dashboard - Přehled',
            'stats' => $stats,
            'recent_inspections' => $recent_inspections,
            'traffic_lights' => $traffic_lights
        ]);
    }
}
