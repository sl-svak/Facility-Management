<?php
class DashboardController {
    public static function index() {
        $pdo = Database::getConnection();

        require_once APP_ROOT . '/app/Models/UserModel.php';
        $currentUser = UserModel::getById($_SESSION['user_id']);
        $userDepts = $currentUser['departments'] ?? [];

        $deptFilterAsset = "";
        $deptFilterJoin = "";
        $params = [];
        
        if (!empty($userDepts)) {
            $in = implode(',', array_map('intval', $userDepts));
            $deptFilterAsset = " AND (department_id IN ($in) OR department_id IS NULL)";
            $deptFilterJoin = " AND (a.department_id IN ($in) OR a.department_id IS NULL)";
        }

        $stats = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE is_active = 1 AND operational_status = 'running'" . $deptFilterAsset);
        $stmt->execute($params);
        $stats['assets_count'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(i.id) FROM inspections i JOIN assets a ON i.asset_id = a.id WHERE 1=1" . $deptFilterJoin);
        $stmt->execute($params);
        $stats['inspections_count'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(t.id) FROM tickets t JOIN assets a ON t.asset_id = a.id WHERE t.status = 'open'" . $deptFilterJoin);
        $stmt->execute($params);
        $stats['open_tickets'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT i.id, i.status, i.created_at, a.name as asset_name, t.title as template_name, u.first_name, u.last_name
            FROM inspections i
            JOIN assets a ON i.asset_id = a.id
            JOIN form_templates t ON i.form_template_id = t.id
            LEFT JOIN users u ON i.technician_id = u.id
            WHERE 1=1 $deptFilterJoin
            ORDER BY i.created_at DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $recent_inspections = $stmt->fetchAll();
        
        $workweek_days = SettingModel::get('workweek_days', 5);
        $shift_start_hour = (int)SettingModel::get('shift_start_hour', 0);
        
        $offset_seconds = $shift_start_hour * 3600;
        $logical_now = time() - $offset_seconds;
        $today_logical_midnight = strtotime(date('Y-m-d', $logical_now));

        $stmt = $pdo->prepare("
            SELECT 
                r.id, 
                a.id as asset_id,
                a.name as asset_name, 
                a.operational_status,
                t.title as template_name, 
                r.period_days, 
                r.warning_days,
                (SELECT MAX(created_at) FROM inspections i WHERE i.asset_id = r.asset_id AND i.form_template_id = r.form_template_id) as last_inspection
            FROM asset_form_rules r
            JOIN assets a ON r.asset_id = a.id
            JOIN form_templates t ON r.form_template_id = t.id
            WHERE a.is_active = 1 AND t.is_active = 1 $deptFilterJoin
        ");
        $stmt->execute($params);
        $rules = $stmt->fetchAll();

        $traffic_lights = [];

        foreach ($rules as $rule) {
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
                $item['days_remaining'] = -1; 
            } else {
                $last_real_time = strtotime($rule['last_inspection']);
                $last_logical_time = $last_real_time - $offset_seconds;
                $last_logical_date = strtotime(date('Y-m-d', $last_logical_time));
                
                $next_due_logical = strtotime("+{$rule['period_days']} days", $last_logical_date);
                
                if ((int)$workweek_days === 5) {
                    $day_of_week = date('N', $next_due_logical);
                    if ($day_of_week == 6) { 
                        $next_due_logical = strtotime("+2 days", $next_due_logical);
                    } elseif ($day_of_week == 7) { 
                        $next_due_logical = strtotime("+1 day", $next_due_logical);
                    }
                }
                
                $item['next_due_formatted'] = date('d.m.Y', $next_due_logical);
                
                $diff_seconds = $next_due_logical - $today_logical_midnight;
                $item['days_remaining'] = (int)round($diff_seconds / 86400);
            }

            if ($item['days_remaining'] < 0) {
                $item['status'] = 'red'; 
                $item['sort_score'] = 1;

                $ticketTitle = "Opomenutá kontrola: " . $rule['template_name'] . " (termín: " . $item['next_due_formatted'] . ")";
                
                $stmtCheck = $pdo->prepare("SELECT id FROM tickets WHERE asset_id = ? AND title = ?");
                $stmtCheck->execute([$rule['asset_id'], $ticketTitle]);
                
                if (!$stmtCheck->fetchColumn()) {
                    $stmtInsert = $pdo->prepare("INSERT INTO tickets (asset_id, title, status) VALUES (?, ?, 'open')");
                    $stmtInsert->execute([$rule['asset_id'], $ticketTitle]);
                    $stats['open_tickets']++;
                }

            } elseif ($item['days_remaining'] <= $rule['warning_days']) {
                $item['status'] = 'orange'; 
                $item['sort_score'] = 2;
            } else {
                $item['status'] = 'green'; 
                $item['sort_score'] = 3;
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
