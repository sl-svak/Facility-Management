<?php
class ReportController {

    private static function getMonthNamesCs() {
        return [
            1 => 'Leden',
            2 => 'Únor',
            3 => 'Březen',
            4 => 'Duben',
            5 => 'Květen',
            6 => 'Červen',
            7 => 'Červenec',
            8 => 'Srpen',
            9 => 'Září',
            10 => 'Říjen',
            11 => 'Listopad',
            12 => 'Prosinec',
        ];
    }

    public static function index() {
        $pdo = Database::getConnection();

        $assetsFilter = Auth::getDepartmentSqlFilter('assets');
        $assets = $pdo->query("SELECT id, name, operational_status FROM assets WHERE is_active = 1 AND {$assetsFilter} ORDER BY name")->fetchAll();

        $years = $pdo->query("SELECT DISTINCT YEAR(created_at) as y FROM inspections ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($years)) {
            $years = [date('Y')];
        } elseif (!in_array((int)date('Y'), array_map('intval', $years), true)) {
            array_unshift($years, (int)date('Y'));
        }

        $outputTemplatesEnabled = SettingModel::isModuleEnabled('module_output_templates');
        $outputTemplatesByAsset = [];

        if ($outputTemplatesEnabled) {
            foreach ($assets as $a) {
                $aid = (int)$a['id'];
                $outputTemplatesByAsset[$aid] = array_map(function ($t) {
                    return [
                        'id' => (int)$t['id'],
                        'title' => (string)$t['title'],
                        'paper_code' => (string)($t['paper_code'] ?? ''),
                    ];
                }, OutputTemplateModel::getForAsset($aid));
            }
        }

        renderView('reports', [
            'pageTitle' => 'Export do PDF',
            'assets' => $assets,
            'years' => $years,
            'months' => self::getMonthNamesCs(),
            'outputTemplatesEnabled' => $outputTemplatesEnabled,
            'outputTemplatesByAsset' => $outputTemplatesByAsset,
        ]);
    }

    public static function generate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=reports');
            exit;
        }

        $year = (int)($_POST['year'] ?? date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }

        $periodMode = (string)($_POST['period_mode'] ?? 'year');
        if (!in_array($periodMode, ['month', 'year'], true)) {
            $periodMode = 'year';
        }

        $month = (int)($_POST['month'] ?? (int)date('n'));
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }

        $asset_id = (int)($_POST['asset_id'] ?? 0);
        if ($asset_id <= 0 || !Auth::canAccessAsset($asset_id)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $outputMode = (string)($_POST['output_mode'] ?? 'complete');
        $outputTemplateId = (int)($_POST['output_template_id'] ?? 0);
        $selectedOutputTemplate = null;

        if ($outputMode === 'custom') {
            if (!SettingModel::isModuleEnabled('module_output_templates')) {
                http_response_code(400);
                die('Modul vlastních výstupních šablon je vypnutý. Zvolte kompletní formulář.');
            }
            $tpl = $outputTemplateId > 0 ? OutputTemplateModel::getById($outputTemplateId) : null;
            if (!$tpl || (int)$tpl['asset_id'] !== $asset_id) {
                http_response_code(400);
                die('Vybraná výstupní šablona neexistuje nebo nepatří k zvolenému zařízení.');
            }
            $selectedOutputTemplate = $tpl;
        }

        $pdo = Database::getConnection();

        if ($periodMode === 'month') {
            $sql = "
                SELECT i.*, a.name as asset_name, t.title as template_name, t.schema_json,
                       u.first_name, u.last_name,
                       tk.status as ticket_status, tk.resolution_text, tk.resolution_signature, tk.resolution_photos, tk.resolved_at,
                       ru.first_name as res_first_name, ru.last_name as res_last_name
                FROM inspections i
                JOIN assets a ON i.asset_id = a.id
                JOIN form_templates t ON i.form_template_id = t.id
                LEFT JOIN users u ON i.technician_id = u.id
                LEFT JOIN tickets tk ON tk.inspection_id = i.id
                LEFT JOIN users ru ON tk.resolved_by = ru.id
                WHERE YEAR(i.created_at) = ? AND MONTH(i.created_at) = ? AND i.asset_id = ?
                ORDER BY i.created_at ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$year, $month, $asset_id]);
        } else {
            $sql = "
                SELECT i.*, a.name as asset_name, t.title as template_name, t.schema_json,
                       u.first_name, u.last_name,
                       tk.status as ticket_status, tk.resolution_text, tk.resolution_signature, tk.resolution_photos, tk.resolved_at,
                       ru.first_name as res_first_name, ru.last_name as res_last_name
                FROM inspections i
                JOIN assets a ON i.asset_id = a.id
                JOIN form_templates t ON i.form_template_id = t.id
                LEFT JOIN users u ON i.technician_id = u.id
                LEFT JOIN tickets tk ON tk.inspection_id = i.id
                LEFT JOIN users ru ON tk.resolved_by = ru.id
                WHERE YEAR(i.created_at) = ? AND i.asset_id = ?
                ORDER BY i.created_at ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$year, $asset_id]);
        }

        $records = $stmt->fetchAll();

        $monthNames = self::getMonthNamesCs();
        $periodLabel = ($periodMode === 'month')
            ? ($monthNames[$month] . ' ' . $year)
            : ('Celý kalendářní rok ' . $year);

        $assetStmt = $pdo->prepare("SELECT name, description FROM assets WHERE id = ?");
        $assetStmt->execute([$asset_id]);
        $assetRow = $assetStmt->fetch();
        $assetNameRaw = (string)($assetRow['name'] ?? ('Zařízení #' . $asset_id));
        $assetNameSafe = htmlspecialchars($assetNameRaw, ENT_QUOTES, 'UTF-8');

        $html = '<style>
            body { font-family: dejavusans, sans-serif; font-size: 9.5pt; color: #222; }
            h1 { font-size: 15pt; text-align: center; margin: 0 0 4px 0; color: #1f2d3d; }
            .subtitle { text-align: center; font-size: 11pt; color: #555; margin-bottom: 10px; }
            .tpl-banner { background: #eef4fa; border: 1px solid #b8d0eb; padding: 8px 10px; margin-bottom: 14px; font-size: 9pt; }
            table.rec { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            table.rec th, table.rec td { border: 1px solid #888; padding: 6px; vertical-align: top; }
            .hdr-ok { background-color: #f4f6f7; }
            .hdr-ko { background-color: #f8d7da; }
            .hdr-stop { background-color: #e2e3e5; }
            .res-ok { background-color: #e8f5e9; color: #155724; font-weight: bold; }
            .res-open { color: #c0392b; font-weight: bold; }
            .st-ok { color: #1e8449; font-weight: bold; }
            .st-ko { color: #c0392b; font-weight: bold; }
        </style>';

        $html .= '<h1>Kniha stroje: ' . $assetNameSafe . '</h1>';
        $html .= '<div class="subtitle">Výpis kontrol a údržby – ' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</div>';

        $selectedFieldDefs = null;
        $showSignatures = true;
        $showPhotos = true;

        if ($selectedOutputTemplate !== null) {
            $tplTitle = htmlspecialchars((string)$selectedOutputTemplate['title'], ENT_QUOTES, 'UTF-8');
            $paperCode = !empty($selectedOutputTemplate['paper_code'])
                ? ' | Evidenční kód: <strong>' . htmlspecialchars((string)$selectedOutputTemplate['paper_code'], ENT_QUOTES, 'UTF-8') . '</strong>'
                : '';
            $headerNote = !empty($selectedOutputTemplate['header_note'])
                ? '<div style="margin-top: 4px;">' . nl2br(htmlspecialchars((string)$selectedOutputTemplate['header_note'], ENT_QUOTES, 'UTF-8')) . '</div>'
                : '';

            $html .= '<div class="tpl-banner">';
            $html .= '<strong>Výstupní šablona:</strong> ' . $tplTitle . $paperCode;
            $html .= $headerNote;
            $html .= '</div>';

            $decodedFields = json_decode((string)$selectedOutputTemplate['fields_json'], true);
            $selectedFieldDefs = is_array($decodedFields) ? $decodedFields : [];
            $showSignatures = ((int)($selectedOutputTemplate['show_signatures'] ?? 1) === 1);
            $showPhotos = ((int)($selectedOutputTemplate['show_photos'] ?? 1) === 1);
        }

        if (empty($records)) {
            $html .= '<p>Pro vybrané zařízení nebyly v období <strong>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</strong> nalezeny žádné záznamy o kontrolách.</p>';
        } else {
            foreach ($records as $row) {
                $hdrClass = 'hdr-ok';
                if ($row['status'] === 'KO') {
                    $hdrClass = 'hdr-ko';
                } elseif ($row['status'] === 'Odstaveno') {
                    $hdrClass = 'hdr-stop';
                }

                $techName = htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
                $tplName = htmlspecialchars((string)$row['template_name'], ENT_QUOTES, 'UTF-8');
                $statusText = htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8');
                $dateFormatted = date('d.m.Y H:i', strtotime((string)$row['created_at']));

                $html .= '<table class="rec">';
                $html .= '<tr class="' . $hdrClass . '"><td colspan="2">';
                $html .= '<strong>' . $dateFormatted . ' – ' . $tplName . '</strong><br>';
                $html .= '<span>Technik: ' . $techName . ' | Stav: <strong>' . $statusText . '</strong></span>';
                $html .= '</td></tr>';

                $schema = json_decode($row['schema_json'] ?? '[]', true);
                $labelMap = [];
                $typeMap = [];
                if (is_array($schema)) {
                    foreach ($schema as $field) {
                        if (!is_array($field)) {
                            continue;
                        }
                        $fId = $field['id'] ?? ($field['label'] ?? '');
                        if ($fId !== '') {
                            $labelMap[$fId] = $field['label'] ?? $fId;
                            $typeMap[$fId] = (string)($field['type'] ?? 'text');
                        }
                    }
                }

                $data = json_decode($row['data_json'] ?? '[]', true);
                if (!is_array($data)) {
                    $data = [];
                }

                // Seznam položek k vykreslení: buď dle vlastní výstupní šablony, nebo kompletní záznam
                $entriesToRender = [];
                if ($selectedFieldDefs !== null) {
                    foreach ($selectedFieldDefs as $fDef) {
                        if (!is_array($fDef) || empty($fDef['id'])) {
                            continue;
                        }
                        $fid = (string)$fDef['id'];
                        if (!array_key_exists($fid, $data)) {
                            continue;
                        }
                        $val = $data[$fid];
                        $isPhotoVal = (is_array($val) && !empty($val) && is_string($val[0]) && (strpos($val[0], 'assets/uploads/') === 0 || preg_match('/^img_.*?\.(png|jpe?g|gif|webp)$/i', $val[0])))
                            || (is_string($val) && (strpos($val, 'assets/uploads/') === 0 || preg_match('/^img_.*?\.(png|jpe?g|gif|webp)$/i', $val)));
                        $isSigVal = (($typeMap[$fid] ?? '') === 'signature')
                            || (is_string($val) && strpos($val, 'data:image/') === 0);
                        if (($typeMap[$fid] ?? '') === 'photo') {
                            $isPhotoVal = true;
                        }

                        if ($isPhotoVal && !$showPhotos) {
                            continue;
                        }
                        if ($isSigVal && !$showSignatures) {
                            continue;
                        }

                        $displayLabel = !empty($fDef['label'])
                            ? (string)$fDef['label']
                            : (string)($labelMap[$fid] ?? $fid);

                        $entriesToRender[] = [
                            'label' => $displayLabel,
                            'value' => $val,
                        ];
                    }
                } else {
                    foreach ($data as $k => $v) {
                        $entriesToRender[] = [
                            'label' => (string)($labelMap[$k] ?? $k),
                            'value' => $v,
                        ];
                    }
                }

                foreach ($entriesToRender as $entry) {
                    $displayLabel = htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8');
                    $v = $entry['value'];

                    $html .= '<tr><td style="width: 38%;"><strong>' . $displayLabel . ':</strong></td><td style="width: 62%;">';

                    if (is_array($v) && !empty($v) && is_string($v[0]) && (strpos($v[0], 'assets/uploads/') === 0 || preg_match('/^img_.*?\.(png|jpe?g|gif|webp)$/i', $v[0]))) {
                        foreach ($v as $photo) {
                            $html .= PdfGenerator::renderSafeImageHtml($photo, 100);
                        }
                    } elseif (is_string($v) && (strpos($v, 'data:image/') === 0 || strpos($v, 'assets/uploads/') === 0 || preg_match('/^img_.*?\.(png|jpe?g|gif|webp)$/i', $v))) {
                        $html .= PdfGenerator::renderSafeImageHtml($v, 80);
                    } elseif ($v === 'OK') {
                        $html .= '<span class="st-ok">OK (V pořádku)</span>';
                    } elseif ($v === 'KO' || $v === 'ko') {
                        $html .= '<span class="st-ko">KO (Závada)</span>';
                    } elseif (is_array($v)) {
                        $html .= nl2br(htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
                    } else {
                        $html .= nl2br(htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
                    }

                    $html .= '</td></tr>';
                }

                if ($row['status'] === 'KO') {
                    if (!empty($row['resolution_text'])) {
                        $resolvedDate = !empty($row['resolved_at']) ? date('d.m.Y', strtotime((string)$row['resolved_at'])) : 'Neznámé datum';
                        $resAuthor = htmlspecialchars(trim(($row['res_first_name'] ?? '') . ' ' . ($row['res_last_name'] ?? '')), ENT_QUOTES, 'UTF-8');

                        $html .= '<tr><td colspan="2" class="res-ok">ZÁVADA BYLA ODSTRANĚNA</td></tr>';
                        $html .= '<tr><td><strong>Způsob opravy:</strong></td><td>' . nl2br(htmlspecialchars((string)$row['resolution_text'], ENT_QUOTES, 'UTF-8')) . '</td></tr>';
                        $html .= '<tr><td><strong>Vyřešil:</strong></td><td>' . $resAuthor . ' (' . htmlspecialchars($resolvedDate, ENT_QUOTES, 'UTF-8') . ')</td></tr>';

                        if ($showSignatures && !empty($row['resolution_signature'])) {
                            $html .= '<tr><td><strong>Podpis opravy:</strong></td><td>' . PdfGenerator::renderSafeImageHtml($row['resolution_signature'], 50) . '</td></tr>';
                        }

                        if ($showPhotos && !empty($row['resolution_photos'])) {
                            $resPhotos = json_decode((string)$row['resolution_photos'], true);
                            if (is_array($resPhotos) && !empty($resPhotos)) {
                                $html .= '<tr><td><strong>Fotografie z opravy:</strong></td><td>';
                                foreach ($resPhotos as $rp) {
                                    $html .= PdfGenerator::renderSafeImageHtml($rp, 100);
                                }
                                $html .= '</td></tr>';
                            }
                        }
                    } else {
                        $html .= '<tr><td colspan="2" class="res-open">Závada zatím nebyla odstraněna (Čeká na řešení).</td></tr>';
                    }
                }

                $html .= '</table>';
            }
        }

        $suffix = ($periodMode === 'month')
            ? ($year . '_' . sprintf('%02d', $month))
            : (string)$year;
        $fileName = 'Kniha_Stroje_' . $asset_id . '_' . $suffix . '.pdf';

        PdfGenerator::outputHtml($html, $fileName, [
            'title' => 'Kniha stroje – ' . $assetNameRaw . ' (' . $periodLabel . ')',
            'destination' => 'I',
        ]);
    }
}
