<?php

class InspectionController {

    public static function scan() {
        $hash = trim($_GET['hash'] ?? '');
        $asset = AssetModel::getByHash($hash);

        if (!$asset) {
            renderView('scan_result', [
                'pageTitle' => 'Zařízení nenalezeno',
                'asset' => null,
                'forms' => [],
                'completedTodayForms' => [],
                'futureForms' => [],
                'openTickets' => []
            ]);
            return;
        }

        $workweek_days = SettingModel::get('workweek_days', 5);
        $shift_start_hour = (int)SettingModel::get('shift_start_hour', 0);
        $offset_seconds = $shift_start_hour * 3600;
        $logical_now = time() - $offset_seconds;
        $today_logical_midnight = strtotime(date('Y-m-d', $logical_now));

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT ft.*, afr.period_days,
                   (SELECT MAX(created_at) FROM inspections i WHERE i.asset_id = afr.asset_id AND i.form_template_id = ft.id) as last_inspection
            FROM form_templates ft
            JOIN asset_form_rules afr ON afr.form_template_id = ft.id
            WHERE afr.asset_id = ? AND ft.is_active = 1
        ");
        $stmt->execute([$asset['id']]);
        $allForms = $stmt->fetchAll();

        $stmtTickets = $pdo->prepare("SELECT * FROM tickets WHERE asset_id = ? AND status = 'open' ORDER BY created_at DESC");
        $stmtTickets->execute([$asset['id']]);
        $openTickets = $stmtTickets->fetchAll();

        $forms = [];
        $completedTodayForms = []; 
        $futureForms = [];

        foreach ($allForms as $f) {
            if (!$f['last_inspection']) {
                $forms[] = $f; 
                continue;
            }

            $last_real_time = strtotime($f['last_inspection']);
            $last_logical_time = $last_real_time - $offset_seconds;
            $last_logical_date = strtotime(date('Y-m-d', $last_logical_time));
            
            $next_due_logical = strtotime("+{$f['period_days']} days", $last_logical_date);
            
            if ((int)$workweek_days === 5) {
                $day_of_week = date('N', $next_due_logical);
                if ($day_of_week == 6) { $next_due_logical = strtotime("+2 days", $next_due_logical); } 
                elseif ($day_of_week == 7) { $next_due_logical = strtotime("+1 day", $next_due_logical); }
            }
            
            $diff_seconds = $next_due_logical - $today_logical_midnight;
            $days_remaining = (int)round($diff_seconds / 86400);
            
            $f['days_remaining'] = $days_remaining;

            if ($days_remaining <= 0) {
                $forms[] = $f; 
            } else {
                if ($last_logical_date == $today_logical_midnight) {
                    $completedTodayForms[] = $f;
                } else {
                    $futureForms[] = $f; 
                }
            }
        }

        renderView('scan_result', [
            'pageTitle' => 'Rozcestník: ' . $asset['name'],
            'asset' => $asset,
            'forms' => $forms,
            'completedTodayForms' => $completedTodayForms,
            'futureForms' => $futureForms,
            'openTickets' => $openTickets
        ]);
    }

    public static function fill() {
        $asset_id = (int)($_GET['asset_id'] ?? 0);
        $form_id  = (int)($_GET['form_id'] ?? 0);

        $asset = AssetModel::getById($asset_id);
        $template = FormModel::getById($form_id);

        if (!$asset || !$template) { header('Location: index.php?page=assets'); exit; }

        renderView('inspection_fill', [
            'pageTitle' => 'Kontrola: ' . $asset['name'],
            'asset' => $asset,
            'template' => $template
        ]);
    }

    public static function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $asset_id         = (int)($_POST['asset_id'] ?? 0);
            $form_template_id = (int)($_POST['form_template_id'] ?? 0);
            $duration_seconds = (int)($_POST['duration_seconds'] ?? 0);
            $technician_id    = $_SESSION['user_id'] ?? null;
            
            $formData = $_POST['data'] ?? [];

            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

            if (isset($_POST['photos_base64']) && is_array($_POST['photos_base64'])) {
                foreach ($_POST['photos_base64'] as $key => $base64Array) {
                    $uploadedPaths = []; 
                    foreach ($base64Array as $i => $base64String) {
                        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                            $base64Data = substr($base64String, strpos($base64String, ',') + 1);
                            $decodedData = base64_decode($base64Data);
                            if ($decodedData !== false) {
                                $newFilename = uniqid('foto_') . '_' . $i . '_' . rand(1000, 9999) . '.jpg'; 
                                $dest = $uploadDir . $newFilename;
                                if (file_put_contents($dest, $decodedData)) { $uploadedPaths[] = $dest; }
                            }
                        }
                    }
                    if (!empty($uploadedPaths)) { $formData[$key] = $uploadedPaths; }
                }
            }

            // Načtení šablony pro správný překlad ID -> NÁZEV v tiketech
            $template = FormModel::getById($form_template_id);
            $schema = json_decode($template['schema_json'] ?? '[]', true);
            $labelMap = [];
            foreach ($schema as $f) {
                $fId = $f['id'] ?? $f['label'];
                $labelMap[$fId] = $f['label'] ?? $fId;
            }

            $hasDefect = false;
            $defectNote = '';
            $setToStopped = false;
            $setToRunning = false;

            foreach ($formData as $key => $val) {
                if ($val === 'Odstaveno') { $setToStopped = true; } 
                elseif ($val === 'V provozu') { $setToRunning = true; }

                if (is_array($val)) {
                    if (isset($val['status']) && $val['status'] === 'KO') {
                        $hasDefect = true;
                        $displayLabel = $labelMap[$key] ?? $key;
                        $defectNote .= "Položka [{$displayLabel}]: stav KO. ";
                    }
                } elseif ($val === 'KO' || $val === 'ko') {
                    $hasDefect = true;
                    $displayLabel = $labelMap[$key] ?? $key;
                    $defectNote .= "Položka [{$displayLabel}]: stav KO. ";
                }
            }

            $pdo = Database::getConnection();
            if ($setToStopped) {
                $overallStatus = 'Odstaveno';
                $pdo->prepare("UPDATE assets SET operational_status = 'stopped' WHERE id = ?")->execute([$asset_id]);
            } else {
                if ($setToRunning) { $pdo->prepare("UPDATE assets SET operational_status = 'running' WHERE id = ?")->execute([$asset_id]); }
                $overallStatus = $hasDefect ? 'KO' : 'OK';
            }

            $dataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("INSERT INTO inspections (asset_id, form_template_id, technician_id, status, data_json, duration_seconds) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$asset_id, $form_template_id, $technician_id, $overallStatus, $dataJson, $duration_seconds]);
            $inspectionId = $pdo->lastInsertId();

            if ($hasDefect && !$setToStopped) {
                $stmtTicket = $pdo->prepare("INSERT INTO tickets (inspection_id, asset_id, title, status) VALUES (?, ?, ?, 'open')");
                $ticketTitle = "Automatická závada z kontroly #" . $inspectionId . " (" . ($defectNote ?: 'Zjištěn stav KO') . ")";
                $stmtTicket->execute([$inspectionId, $asset_id, $ticketTitle]);
            }

            header('Location: index.php?page=inspections&saved=1');
            exit;
        }
    }

    public static function stats() {
        $asset_id = (int)($_GET['id'] ?? 0);
        $asset = AssetModel::getById($asset_id);

        if (!$asset) {
            renderView('asset_stats', [
                'pageTitle' => 'Statistiky zařízení',
                'asset' => ['name' => 'Neznámé zařízení', 'id' => 0],
                'chartData' => [],
                'errorMessage' => 'Zařízení nebylo nalezeno.'
            ]); return;
        }

        $pdo = Database::getConnection();

        $stmtForms = $pdo->prepare("SELECT schema_json FROM form_templates WHERE id IN (SELECT DISTINCT form_template_id FROM inspections WHERE asset_id = ?)");
        $stmtForms->execute([$asset_id]);
        $templates = $stmtForms->fetchAll();
        
        $fieldTypes = [];
        $labelMap = [];
        foreach ($templates as $tpl) {
            $schema = json_decode($tpl['schema_json'], true);
            if (is_array($schema)) {
                foreach ($schema as $field) {
                    $fId = $field['id'] ?? $field['label'];
                    $fieldTypes[$fId] = $field['type'] ?? 'number';
                    $labelMap[$fId] = $field['label'] ?? $fId;
                }
            }
        }

        $stmt = $pdo->prepare("SELECT created_at, data_json FROM inspections WHERE asset_id = ? ORDER BY created_at ASC LIMIT 100");
        $stmt->execute([$asset_id]);
        $inspections = $stmt->fetchAll();

        $rawSeries = [];
        foreach ($inspections as $insp) {
            $timestamp = strtotime($insp['created_at']) * 1000;
            $data = json_decode($insp['data_json'], true);

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_numeric($value)) {
                        if ($value > 1000000000) continue;
                        if (!isset($rawSeries[$key])) { $rawSeries[$key] = []; }
                        $rawSeries[$key][] = ['x' => $timestamp, 'val' => (float)$value];
                    }
                }
            }
        }

        $chartData = [];

        foreach ($rawSeries as $key => $points) {
            $isCounter = isset($fieldTypes[$key]) && $fieldTypes[$key] === 'meter_reading';
            $displayTitle = $labelMap[$key] ?? $key; // Překlad ID na text pro graf

            if ($isCounter) {
                $deltaPoints = []; $prevVal = null;
                foreach ($points as $p) {
                    if ($prevVal !== null) {
                        $diff = round($p['val'] - $prevVal, 2); if ($diff < 0) $diff = $p['val'];
                        $deltaPoints[] = ['x' => $p['x'], 'y' => $diff, 'total' => $p['val']];
                    }
                    $prevVal = $p['val'];
                }
                if (!empty($deltaPoints)) {
                    $chartData[$key] = [ 'type' => 'bar', 'title' => $displayTitle . ' (Spotřeba)', 'points' => $deltaPoints, 'unit_prefix' => '+' ];
                }
            } else {
                $areaPoints = [];
                foreach ($points as $p) { $areaPoints[] = ['x' => $p['x'], 'y' => $p['val']]; }
                $chartData[$key] = [ 'type' => 'area', 'title' => $displayTitle, 'points' => $areaPoints, 'unit_prefix' => '' ];
            }
        }

        renderView('asset_stats', [
            'pageTitle' => 'Statistiky a grafy: ' . $asset['name'],
            'asset' => $asset,
            'chartData' => $chartData
        ]);
    }
}
