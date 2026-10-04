<?php

class TicketController {
    
    /**
     * Vypíše seznam závad s ohledem na oprávnění přihlášeného uživatele
     */
    public static function index() {
        $pdo = Database::getConnection();
        
        $deptFilter = Auth::getDepartmentSqlFilter('a');
        $status = $_GET['status'] ?? 'open';
        $statusCondition = ($status === 'open') ? "t.status = 'open'" : ($status === 'closed' ? "t.status = 'closed'" : "1=1");
        
        $stmt = $pdo->query("
            SELECT t.*, a.name as asset_name
            FROM tickets t
            JOIN assets a ON t.asset_id = a.id
            WHERE $statusCondition AND $deptFilter
            ORDER BY t.created_at DESC
        ");
        $tickets = $stmt->fetchAll();
        
        renderView('tickets', [
            'pageTitle' => 'Správa závad a úkolů',
            'tickets' => $tickets,
            'currentStatus' => $status
        ]);
    }

    /**
     * Formulář pro ruční vytvoření závady (např. z externího PDF protokolu)
     */
    public static function create() {
        $asset_id = (int)($_GET['asset_id'] ?? 0);
        $inspection_id = (int)($_GET['inspection_id'] ?? 0);

        if ($asset_id > 0 && !Auth::canAccessAsset($asset_id)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $pdo = Database::getConnection();
        
        $deptFilter = Auth::getDepartmentSqlFilter('assets');
        $stmtAssets = $pdo->query("SELECT id, name FROM assets WHERE is_active = 1 AND $deptFilter ORDER BY name ASC");
        $assets = $stmtAssets->fetchAll();

        renderView('ticket_create', [
            'pageTitle' => 'Nahlásit novou závadu',
            'assets' => $assets,
            'preselected_asset_id' => $asset_id,
            'preselected_inspection_id' => $inspection_id
        ]);
    }

    /**
     * Zpracování uložení nové ruční závady (Bez sloupce created_by)
     */
    public static function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=tickets');
            exit;
        }

        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $inspection_id = !empty($_POST['inspection_id']) ? (int)$_POST['inspection_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!$asset_id || empty($title)) {
            http_response_code(400);
            die("Chyba: Chybí povinná data (Zařízení nebo Název závady).");
        }

        if (!Auth::canAccessAsset($asset_id)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění zapisovat k tomuto zařízení.");
        }

        $pdo = Database::getConnection();
        
        // Zde byl odstraněn požadavek na created_by
        $stmt = $pdo->prepare("
            INSERT INTO tickets (asset_id, inspection_id, title, status) 
            VALUES (?, ?, ?, 'open')
        ");
        
        $stmt->execute([
            $asset_id, 
            $inspection_id, 
            $title . (!empty($description) ? ' - ' . $description : '')
        ]);

        if (isset($_POST['save_and_add'])) {
            header("Location: index.php?page=ticket_create&asset_id={$asset_id}&inspection_id={$inspection_id}&success=1");
        } else {
            header("Location: index.php?page=tickets");
        }
        exit;
    }

    /**
     * Zobrazí detail konkrétního tiketu (závady) - Vráceno na původní dotaz
     */
    public static function detail() {
        if (empty($_GET['id'])) {
            header('Location: index.php?page=tickets');
            exit;
        }
        
        $ticket_id = (int)$_GET['id'];
        $pdo = Database::getConnection();
        
        // Zde byl odstraněn JOIN na cu (autor tiketu)
        $stmt = $pdo->prepare("
            SELECT t.*, a.name as asset_name,
                   u.first_name, u.last_name,
                   i.data_json, i.created_at as inspection_date,
                   ru.first_name as res_first_name, ru.last_name as res_last_name
            FROM tickets t
            JOIN assets a ON t.asset_id = a.id
            LEFT JOIN inspections i ON t.inspection_id = i.id
            LEFT JOIN users u ON i.technician_id = u.id
            LEFT JOIN users ru ON t.resolved_by = ru.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            http_response_code(404);
            die("Tiket nenalezen.");
        }
        
        if (!Auth::canAccessAsset($ticket['asset_id'])) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k zobrazení tohoto zařízení a jeho tiketů.");
        }
        
        renderView('ticket_detail', [
            'pageTitle' => 'Detail hlášení #' . $ticket['id'],
            'ticket' => $ticket
        ]);
    }

    /**
     * Zpracuje odeslaný formulář pro vyřešení závady
     */
    public static function resolve() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=tickets');
            exit;
        }
        
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $resolution_text = $_POST['resolution_text'] ?? '';
        $signature = $_POST['resolution_signature'] ?? '';
        
        if (!$ticket_id || empty($resolution_text) || empty($signature)) {
            http_response_code(400);
            die("Chyba: Chybí povinná data (text řešení nebo podpis).");
        }
        
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT asset_id, status FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            http_response_code(404);
            die("Tiket nenalezen.");
        }
        
        if (!Auth::canAccessAsset($ticket['asset_id'])) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k úpravě tohoto tiketu.");
        }
        
        if ($ticket['status'] !== 'open') {
            http_response_code(400);
            die("Bezpečnostní chyba: Tento tiket je již uzavřen a nelze jej přepsat.");
        }
        
        $savedPhotos = [];
        if (!empty($_POST['resolution_photos_base64']) && is_array($_POST['resolution_photos_base64'])) {
            require_once APP_ROOT . '/app/Core/ImageProcessor.php';
            $uploadDir = APP_ROOT . '/assets/uploads';
            
            $photosToProcess = array_slice($_POST['resolution_photos_base64'], 0, 5);
            
            foreach ($photosToProcess as $base64) {
                $path = ImageProcessor::processBase64($base64, $uploadDir);
                if ($path) {
                    $savedPhotos[] = 'assets/uploads/' . basename($path);
                }
            }
        }
        
        $photosJson = !empty($savedPhotos) ? json_encode($savedPhotos) : null;
        
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare("
                UPDATE tickets 
                SET status = 'closed', 
                    resolution_text = ?, 
                    resolution_signature = ?, 
                    resolution_photos = ?, 
                    resolved_by = ?, 
                    resolved_at = NOW() 
                WHERE id = ?
            ");
            
            $update->execute([
                $resolution_text,
                $signature,
                $photosJson,
                $_SESSION['user_id'],
                $ticket_id
            ]);

            // Bezpečnostní oprava: Pokud bylo zařízení odstaveno, vrátíme ho do provozu
            $pdo->prepare("UPDATE assets SET operational_status = 'running' WHERE id = ?")->execute([$ticket['asset_id']]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            die("Chyba při ukládání řešení závady.");
        }
        
        header('Location: index.php?page=ticket_detail&id=' . $ticket_id);
        exit;
    }
}
