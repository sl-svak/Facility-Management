<?php

class TicketController {
    
    public static function index() {
        $pdo = Database::getConnection();
        
        $deptFilter = Auth::getDepartmentSqlFilter('a');
        
        $stmt = $pdo->query("
            SELECT t.*, a.name as asset_name, i.created_at as inspection_date 
            FROM tickets t 
            JOIN assets a ON t.asset_id = a.id 
            LEFT JOIN inspections i ON t.inspection_id = i.id 
            WHERE {$deptFilter}
            ORDER BY FIELD(t.status, 'open', 'closed'), t.created_at DESC
        ");
        $tickets = $stmt->fetchAll();
        
        renderView('tickets', [
            'pageTitle' => 'Úkoly a hlášené závady', 
            'tickets' => $tickets
        ]);
    }

    public static function detail() {
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("
            SELECT t.*, a.name as asset_name, i.data_json, i.created_at as inspection_date,
                   u.first_name, u.last_name,
                   ru.first_name as res_first_name, ru.last_name as res_last_name
            FROM tickets t 
            JOIN assets a ON t.asset_id = a.id 
            LEFT JOIN inspections i ON t.inspection_id = i.id 
            LEFT JOIN users u ON i.technician_id = u.id
            LEFT JOIN users ru ON t.resolved_by = ru.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            header('Location: index.php?page=tickets');
            exit;
        }
        
        if (!Auth::canAccessAsset($ticket['asset_id'])) {
            http_response_code(403);
            die("Přístup odepřen: Závada se týká zařízení z jiného úseku.");
        }
        
        renderView('ticket_detail', [
            'pageTitle' => 'Detail závady: ' . $ticket['asset_name'], 
            'ticket' => $ticket
        ]);
    }

    public static function resolve() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticket_id = (int)$_POST['ticket_id'];
            $resolution_text = trim($_POST['resolution_text'] ?? '');
            $signature = $_POST['resolution_signature'] ?? '';
            $user_id = $_SESSION['user_id'];
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT asset_id FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $ticketAsset = $stmt->fetchColumn();
            
            if (!$ticketAsset || !Auth::canAccessAsset($ticketAsset)) {
                http_response_code(403);
                die("Bezpečnostní chyba: Nemáte oprávnění uzavřít tento tiket.");
            }

            if (empty($resolution_text) || empty($signature)) {
                die("Chyba: Způsob opravy a podpis jsou povinné!");
            }

            $uploadDir = 'assets/uploads/';
            
            // --- BEZPEČNÉ ZPRACOVÁNÍ PODPISU (Stejná pipeline jako fotky) ---
            $signaturePath = null;
            if (strpos($signature, 'data:image/') === 0) {
                $signaturePath = ImageProcessor::processBase64($signature, $uploadDir, 2); // 2 MB limit
                if (!$signaturePath) {
                    http_response_code(400);
                    die("Bezpečnostní chyba: Neplatný formát, velikost nebo poškozená data podpisu.");
                }
            } else {
                http_response_code(400);
                die("Bezpečnostní chyba: Podpis neobsahuje platná obrazová data.");
            }

            // --- BEZPEČNÉ ZPRACOVÁNÍ FOTOGRAFIÍ OPRAVY ---
            $uploadedPaths = [];

            if (isset($_POST['resolution_photos_base64']) && is_array($_POST['resolution_photos_base64'])) {
                @ini_set('memory_limit', '256M');
                @ini_set('max_execution_time', '60');

                $photosToProcess = $_POST['resolution_photos_base64'];
                if (count($photosToProcess) > 5) {
                    $photosToProcess = array_slice($photosToProcess, 0, 5);
                }

                foreach ($photosToProcess as $i => $base64String) {
                    $savedPath = ImageProcessor::processBase64($base64String, $uploadDir, 5);
                    if ($savedPath) {
                        $uploadedPaths[] = $savedPath;
                    }
                }
            }
            
            $photosJson = !empty($uploadedPaths) ? json_encode($uploadedPaths, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = 'closed', 
                    resolution_text = ?, 
                    resolution_signature = ?, 
                    resolution_photos = ?,
                    resolved_at = NOW(), 
                    resolved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$resolution_text, $signaturePath, $photosJson, $user_id, $ticket_id]);
            
            header('Location: index.php?page=tickets&resolved=1');
            exit;
        }
    }
}
