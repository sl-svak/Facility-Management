<?php

class InspectionController {

    // 1. Zpracování naskenovaného QR kódu a zobrazení rozcestníku
    public static function scan() {
        $hash = trim($_GET['hash'] ?? '');
        $asset = AssetModel::getByHash($hash);

        if (!$asset) {
            renderView('scan_result', [
                'pageTitle' => 'Zařízení nenalezeno',
                'asset' => null,
                'forms' => [],
                'openTickets' => []
            ]);
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT ft.*, afr.period_days 
            FROM form_templates ft
            JOIN asset_form_rules afr ON afr.form_template_id = ft.id
            WHERE afr.asset_id = ? AND ft.is_active = 1
        ");
        $stmt->execute([$asset['id']]);
        $forms = $stmt->fetchAll();

        $stmtTickets = $pdo->prepare("SELECT * FROM tickets WHERE asset_id = ? AND status = 'open' ORDER BY created_at DESC");
        $stmtTickets->execute([$asset['id']]);
        $openTickets = $stmtTickets->fetchAll();

        renderView('scan_result', [
            'pageTitle' => 'Rozcestník: ' . $asset['name'],
            'asset' => $asset,
            'forms' => $forms,
            'openTickets' => $openTickets
        ]);
    }

    // 2. Zobrazení formuláře pro technika
    public static function fill() {
        $asset_id = (int)($_GET['asset_id'] ?? 0);
        $form_id  = (int)($_GET['form_id'] ?? 0);

        $asset = AssetModel::getById($asset_id);
        $template = FormModel::getById($form_id);

        if (!$asset || !$template) {
            header('Location: index.php?page=assets');
            exit;
        }

        renderView('inspection_fill', [
            'pageTitle' => 'Kontrola: ' . $asset['name'],
            'asset' => $asset,
            'template' => $template
        ]);
    }

    // 3. Uložení záznamu (VČETNĚ ZPRACOVÁNÍ FOTOGRAFIÍ)
    public static function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $asset_id         = (int)($_POST['asset_id'] ?? 0);
            $form_template_id = (int)($_POST['form_template_id'] ?? 0);
            $duration_seconds = (int)($_POST['duration_seconds'] ?? 0);
            $technician_id    = $_SESSION['user_id'] ?? null;
            
            $formData = $_POST['data'] ?? [];

            // --- ZPRACOVÁNÍ FOTOGRAFIÍ Z MOBILU (KOMPRESE JIŽ PROBĚHLA V PROHLÍŽEČI) ---
            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (isset($_POST['photos_base64']) && is_array($_POST['photos_base64'])) {
                foreach ($_POST['photos_base64'] as $key => $base64Array) {
                    $uploadedPaths = []; 
                    
                    foreach ($base64Array as $i => $base64String) {
                        // Odstranění hlavičky 'data:image/jpeg;base64,' a získání čistých dat
                        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                            $base64Data = substr($base64String, strpos($base64String, ',') + 1);
                            $decodedData = base64_decode($base64Data);

                            if ($decodedData !== false) {
                                // Vygenerování absolutně unikátního názvu souboru
                                $newFilename = uniqid('foto_') . '_' . $i . '_' . rand(1000, 9999) . '.jpg'; 
                                $dest = $uploadDir . $newFilename;
                                
                                // Uložení fyzického souboru (bez nutnosti náročné paměti GD knihovny)
                                if (file_put_contents($dest, $decodedData)) {
                                    $uploadedPaths[] = $dest;
                                }
                            }
                        }
                    }
                    
                    if (!empty($uploadedPaths)) {
                        $formData[$key] = $uploadedPaths;
                    }
                }
            }
            // ----------------------------------------

            $hasDefect = false;
            $defectNote = '';
            
            // Proměnné pro řízení provozního stavu stroje
            $setToStopped = false;
            $setToRunning = false;

            foreach ($formData as $key => $val) {
                // Detekce nového přepínače provozu
                if ($val === 'Odstaveno') {
                    $setToStopped = true;
                } elseif ($val === 'V provozu') {
                    $setToRunning = true;
                }

                if (is_array($val)) {
                    if (isset($val['status']) && $val['status'] === 'KO') {
                        $hasDefect = true;
                        $defectNote .= "Položka [{$key}]: stav KO. ";
                    }
                } elseif ($val === 'KO' || $val === 'ko') {
                    $hasDefect = true;
                    $defectNote .= "Položka [{$key}]: stav KO. ";
                }
            }

            $pdo = Database::getConnection();

            // Pokud je zařízení odstaveno, nebudeme to brát jako závadu (KO), pouze ho skryjeme z plánu
            if ($setToStopped) {
                $overallStatus = 'Odstaveno';
                $pdo->prepare("UPDATE assets SET operational_status = 'stopped' WHERE id = ?")->execute([$asset_id]);
            } else {
                if ($setToRunning) {
                    $pdo->prepare("UPDATE assets SET operational_status = 'running' WHERE id = ?")->execute([$asset_id]);
                }
                $overallStatus = $hasDefect ? 'KO' : 'OK';
            }

            $dataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("
                INSERT INTO inspections (asset_id, form_template_id, technician_id, status, data_json, duration_seconds) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$asset_id, $form_template_id, $technician_id, $overallStatus, $dataJson, $duration_seconds]);
            $inspectionId = $pdo->lastInsertId();

            // Vytvoření tiketu (pouze pokud je stroj v provozu a má závadu)
            if ($hasDefect && !$setToStopped) {
                $stmtTicket = $pdo->prepare("
                    INSERT INTO tickets (inspection_id, asset_id, title, status) 
                    VALUES (?, ?, ?, 'open')
                ");
                $ticketTitle = "Automatická závada z kontroly #" . $inspectionId . " (" . ($defectNote ?: 'Zjištěn stav KO') . ")";
                $stmtTicket->execute([$inspectionId, $asset_id, $ticketTitle]);
            }

            header('Location: index.php?page=inspections&saved=1');
            exit;
        }
    }

    // 4. Chytrá analytika a detekce grafů
    public static function stats() {
        $asset_id = (int)($_GET['id'] ?? 0);
        $asset = AssetModel::getById($asset_id);

        if (!$asset) {
            renderView('asset_stats', [
                'pageTitle' => 'Statistiky zařízení',
                'asset' => ['name' => 'Neznámé zařízení', 'id' => 0],
                'chartData' => [],
                'errorMessage' => 'Zařízení nebylo nalezeno.'
            ]);
            return;
        }

        $pdo = Database::getConnection();

        // Vytáhneme definici formulářů pro toto konkrétní zařízení a vytvoříme mapu
        $stmtForms = $pdo->prepare("
            SELECT schema_json FROM form_templates 
            WHERE id IN (SELECT DISTINCT form_template_id FROM inspections WHERE asset_id = ?)
        ");
        $stmtForms->execute([$asset_id]);
        $templates = $stmtForms->fetchAll();
        
        $fieldTypes = [];
        foreach ($templates as $tpl) {
            $schema = json_decode($tpl['schema_json'], true);
            if (is_array($schema)) {
                foreach ($schema as $field) {
                    $label = $field['label'] ?? '';
                    $fieldTypes[$label] = $field['type'] ?? 'number';
                }
            }
        }

        $stmt = $pdo->prepare("SELECT created_at, data_json FROM inspections WHERE asset_id = ? ORDER BY created_at ASC LIMIT 100");
        $stmt->execute([$asset_id]);
        $inspections = $stmt->fetchAll();

        $rawSeries = [];
        foreach ($inspections as $insp) {
            // Převod času na milisekundy pro ApexCharts datetime osu
            $timestamp = strtotime($insp['created_at']) * 1000;
            $data = json_decode($insp['data_json'], true);

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_numeric($value)) {
                        if ($value > 1000000000) continue;
                        if (!isset($rawSeries[$key])) {
                            $rawSeries[$key] = [];
                        }
                        $rawSeries[$key][] = ['x' => $timestamp, 'val' => (float)$value];
                    }
                }
            }
        }

        $chartData = [];

        foreach ($rawSeries as $key => $points) {
            // Chytrá detekce kumulativního měřidla
            $isCounter = isset($fieldTypes[$key]) && $fieldTypes[$key] === 'meter_reading';

            if ($isCounter) {
                $deltaPoints = [];
                $prevVal = null;
                foreach ($points as $p) {
                    if ($prevVal !== null) {
                        $diff = round($p['val'] - $prevVal, 2);
                        if ($diff < 0) $diff = $p['val'];
                        $deltaPoints[] = ['x' => $p['x'], 'y' => $diff, 'total' => $p['val']];
                    }
                    $prevVal = $p['val'];
                }
                if (!empty($deltaPoints)) {
                    $chartData[$key] = [
                        'type' => 'bar',
                        'title' => $key . ' (Přírůstek / Spotřeba)',
                        'points' => $deltaPoints,
                        'unit_prefix' => '+'
                    ];
                }
            } else {
                $areaPoints = [];
                foreach ($points as $p) {
                    $areaPoints[] = ['x' => $p['x'], 'y' => $p['val']];
                }
                $chartData[$key] = [
                    'type' => 'area',
                    'title' => $key,
                    'points' => $areaPoints,
                    'unit_prefix' => ''
                ];
            }
        }

        renderView('asset_stats', [
            'pageTitle' => 'Statistiky a grafy: ' . $asset['name'],
            'asset' => $asset,
            'chartData' => $chartData
        ]);
    }
}
