<?php
class ReportController {
    
    public static function index() {
        $pdo = Database::getConnection();
        
        $assets = $pdo->query("SELECT id, name, operational_status FROM assets WHERE is_active = 1 ORDER BY name")->fetchAll();
        
        $years = $pdo->query("SELECT DISTINCT YEAR(created_at) as y FROM inspections ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($years)) {
            $years = [date('Y')];
        }
        
        renderView('reports', [
            'pageTitle' => 'Export do PDF',
            'assets' => $assets,
            'years' => $years
        ]);
    }

    public static function generate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=reports');
            exit;
        }

        $year = (int)$_POST['year'];
        $asset_id = (int)$_POST['asset_id']; 
        
        $pdo = Database::getConnection();

        // PŘIDÁNO tk.resolution_photos DO SQL DOTAZU
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
            WHERE YEAR(i.created_at) = ? AND i.asset_id = ?
            ORDER BY i.created_at ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$year, $asset_id]);
        $records = $stmt->fetchAll();

        $tcpdfPath = APP_ROOT . '/app/tcpdf/tcpdf.php';
        if (!file_exists($tcpdfPath)) {
            die("Chyba: Knihovna TCPDF nebyla nalezena na serveru.");
        }

        if (!defined('K_PATH_CACHE')) {
            define('K_PATH_CACHE', APP_ROOT . '/app/tcpdf/');
        }

        error_reporting(0);
        ini_set('display_errors', 0);
        while (ob_get_level()) { ob_end_clean(); }

        require_once $tcpdfPath;

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CMMS Cosmonde');
        $pdf->SetAuthor('Systém Údržby');
        $pdf->SetTitle('Kniha_Stroje_' . $year);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true); 
        $pdf->SetFooterMargin(15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->SetFont('dejavusans', '', 10);

        if (empty($records)) {
            $pdf->AddPage();
            $pdf->Write(0, "Pro vybrané zařízení nebyly v roce $year nalezeny žádné záznamy o údržbě.");
        } else {
            $current_asset = null;

            foreach ($records as $row) {
                if ($current_asset !== $row['asset_id']) {
                    $pdf->AddPage();
                    $pdf->SetFont('dejavusans', 'B', 16);
                    $pdf->Cell(0, 10, 'Kniha stroje: ' . $row['asset_name'], 0, 1, 'C');
                    $pdf->SetFont('dejavusans', '', 12);
                    $pdf->Cell(0, 10, 'Výpis údržby pro rok ' . $year, 0, 1, 'C');
                    $pdf->Ln(5);
                    $current_asset = $row['asset_id'];
                }

                $html = '<table border="1" cellpadding="6">';
                $bgColor = ($row['status'] === 'KO') ? '#f8d7da' : '#f9f9f9';
                if ($row['status'] === 'Odstaveno') $bgColor = '#e2e3e5';
                
                $html .= '<tr style="background-color: '.$bgColor.';">';
                $html .= '<td colspan="2"><h3 style="margin:0;">' . date('d.m.Y H:i', strtotime($row['created_at'])) . ' – ' . htmlspecialchars($row['template_name']) . '</h3>';
                $html .= '<b>Technik:</b> ' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' | <b>Stav:</b> ' . htmlspecialchars($row['status']) . '</td></tr>';

                $data = json_decode($row['data_json'], true);
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        $html .= '<tr><td width="40%"><b>' . htmlspecialchars($k) . ':</b></td><td width="60%">';
                        
                        if (is_string($v) && strpos($v, 'data:image/') === 0) {
                            $imgData = preg_replace('#^data:image/[^;]+;base64,#', '', $v);
                            $html .= '<img src="@' . $imgData . '" height="40" />';
                        } 
                        elseif (is_array($v) && isset($v[0]) && strpos((string)$v[0], 'assets/uploads/') === 0) {
                            $html .= '<br>';
                            foreach ($v as $photo) {
                                $photoPath = APP_ROOT . '/' . $photo;
                                if (file_exists($photoPath)) {
                                    $html .= '<img src="' . $photoPath . '" height="100" style="margin-right: 10px; margin-bottom: 5px;" /> ';
                                }
                            }
                            $html .= '<br>';
                        }
                        elseif (is_string($v) && strpos($v, 'assets/uploads/') === 0) {
                            $photoPath = APP_ROOT . '/' . $v;
                            if (file_exists($photoPath)) {
                                $html .= '<br><img src="' . $photoPath . '" height="100" /><br>';
                            } else {
                                $html .= '<i>Fotografie nenalezena</i>';
                            }
                        } 
                        else {
                            if ($v === 'OK') {
                                $html .= '<b style="color: #27ae60;">OK (V pořádku)</b>';
                            } elseif ($v === 'KO' || $v === 'ko') {
                                $html .= '<b style="color: #e74c3c;">KO (Závada)</b>';
                            } else {
                                if (is_array($v)) {
                                    $html .= nl2br(htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE)));
                                } else {
                                    $html .= nl2br(htmlspecialchars((string)$v));
                                }
                            }
                        }
                        
                        $html .= '</td></tr>';
                    }
                }

                if ($row['status'] === 'KO') {
                    if (!empty($row['resolution_text'])) {
                        $html .= '<tr style="background-color: #e8f5e9;"><td colspan="2" style="color: #155724;"><b>ZÁVADA BYLA ODSTRANĚNA:</b></td></tr>';
                        $html .= '<tr><td><b>Způsob opravy:</b></td><td>' . nl2br(htmlspecialchars($row['resolution_text'])) . '</td></tr>';
                        
                        $resolved_date = !empty($row['resolved_at']) ? date('d.m.Y', strtotime($row['resolved_at'])) : 'Neznámé datum';
                        $html .= '<tr><td><b>Vyřešil:</b></td><td>' . htmlspecialchars($row['res_first_name'] . ' ' . $row['res_last_name']) . ' (' . $resolved_date . ')</td></tr>';
                        
                        if (!empty($row['resolution_signature'])) {
                            $imgData = preg_replace('#^data:image/[^;]+;base64,#', '', $row['resolution_signature']);
                            $html .= '<tr><td><b>Podpis opravy:</b></td><td><img src="@' . $imgData . '" height="40" /></td></tr>';
                        }
                        
                        // ZOBRAZENÍ FOTEK OPRAVY DO PDF
                        if (!empty($row['resolution_photos'])) {
                            $resPhotos = json_decode($row['resolution_photos'], true);
                            if (is_array($resPhotos) && count($resPhotos) > 0) {
                                $html .= '<tr><td><b>Fotografie z opravy:</b></td><td>';
                                foreach ($resPhotos as $rp) {
                                    $rpPath = APP_ROOT . '/' . $rp;
                                    if (file_exists($rpPath)) {
                                        $html .= '<img src="' . $rpPath . '" height="100" style="margin-right: 10px; margin-bottom: 5px;" /> ';
                                    }
                                }
                                $html .= '</td></tr>';
                            }
                        }

                    } else {
                        $html .= '<tr><td colspan="2" style="color: red;"><b>Závada zatím nebyla odstraněna (Čeká na řešení).</b></td></tr>';
                    }
                }

                $html .= '</table><br><br>';
                
                $pdf->SetFont('dejavusans', '', 10);
                @$pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        @$pdf->Output('Kniha_Stroje_' . $year . '.pdf', 'I');
        exit;
    }
}
