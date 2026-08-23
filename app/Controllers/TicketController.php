<?php

class TicketController {
    
    // 1. Výpis všech tiketů (závad)
    public static function index() {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->query("
            SELECT t.*, a.name as asset_name, i.created_at as inspection_date 
            FROM tickets t 
            JOIN assets a ON t.asset_id = a.id 
            LEFT JOIN inspections i ON t.inspection_id = i.id 
            ORDER BY FIELD(t.status, 'open', 'closed'), t.created_at DESC
        ");
        $tickets = $stmt->fetchAll();
        
        renderView('tickets', [
            'pageTitle' => 'Úkoly a hlášené závady', 
            'tickets' => $tickets
        ]);
    }

    // 2. Zobrazení detailu závady a formuláře pro její vyřešení
    public static function detail() {
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();
        
        // ZAJIŠTĚNÍ EXISTENCE SLOUPCE PRO FOTKY OPRAVY (Automatická úprava databáze)
        try {
            $pdo->exec("ALTER TABLE tickets ADD COLUMN resolution_photos TEXT NULL AFTER resolution_signature");
        } catch (PDOException $e) {
            // Pokud už sloupec existuje, databáze hodí chybu, kterou můžeme v klidu ignorovat
        }
        
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
        
        renderView('ticket_detail', [
            'pageTitle' => 'Detail závady: ' . $ticket['asset_name'], 
            'ticket' => $ticket
        ]);
    }

    // 3. Uložení opravy (Podpis, poznámka a FOTOGRAFIE OPRAVY)
    public static function resolve() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticket_id = (int)$_POST['ticket_id'];
            $resolution_text = trim($_POST['resolution_text'] ?? '');
            $signature = $_POST['resolution_signature'] ?? '';
            $user_id = $_SESSION['user_id'];
            
            if (empty($resolution_text) || empty($signature)) {
                die("Chyba: Způsob opravy a podpis jsou povinné!");
            }

            // --- ZPRACOVÁNÍ FOTOGRAFIÍ OPRAVY (S KOMPRESÍ) ---
            $uploadedPaths = [];
            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (isset($_POST['resolution_photos_base64']) && is_array($_POST['resolution_photos_base64'])) {
                foreach ($_POST['resolution_photos_base64'] as $i => $base64String) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                        $base64Data = substr($base64String, strpos($base64String, ',') + 1);
                        $decodedData = base64_decode($base64Data);

                        if ($decodedData !== false) {
                            $newFilename = uniqid('oprava_') . '_' . $i . '_' . rand(1000, 9999) . '.jpg'; 
                            $dest = $uploadDir . $newFilename;
                            
                            if (file_put_contents($dest, $decodedData)) {
                                $uploadedPaths[] = $dest;
                            }
                        }
                    }
                }
            }
            
            $photosJson = !empty($uploadedPaths) ? json_encode($uploadedPaths, JSON_UNESCAPED_UNICODE) : null;
            // ----------------------------------------
            
            $pdo = Database::getConnection();
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
            $stmt->execute([$resolution_text, $signature, $photosJson, $user_id, $ticket_id]);
            
            header('Location: index.php?page=tickets&resolved=1');
            exit;
        }
    }
}
