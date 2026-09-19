<?php
class PlanController {
    
    // Zobrazení stránky pro plánování
    public static function index() {
        $assets = AssetModel::getAll();
        $templates = FormModel::getAll();
        $rules = PlanModel::getAll();
        
        // Načtení historie změn plánů pro audit
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT l.*, u.first_name, u.last_name, a.name as asset_name, t.title as template_name
            FROM plan_changes_log l
            LEFT JOIN users u ON l.user_id = u.id
            JOIN asset_form_rules r ON l.rule_id = r.id
            JOIN assets a ON r.asset_id = a.id
            JOIN form_templates t ON r.form_template_id = t.id
            ORDER BY l.created_at DESC
        ");
        $history = $stmt->fetchAll();
        
        renderView('plans', [
            'pageTitle' => 'Plánování údržby',
            'assets' => $assets,
            'templates' => $templates,
            'rules' => $rules,
            'history' => $history
        ]);
    }

    // Uložení nového plánu do databáze
    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $asset_id = (int)$_POST['asset_id'];
            $form_template_id = (int)$_POST['form_template_id'];
            $period_days = (int)$_POST['period_days'];
            $warning_days = (int)$_POST['warning_days'];
            
            if ($asset_id > 0 && $form_template_id > 0 && $period_days > 0) {
                PlanModel::create($asset_id, $form_template_id, $period_days, $warning_days);
            }
        }
        header('Location: index.php?page=plans');
        exit;
    }
    
    // Úprava stávajícího plánu s povinným logováním v TRANSAKCI
    public static function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['id'];
            $new_period = (int)$_POST['period_days'];
            $new_warning = (int)$_POST['warning_days'];
            $reason = trim($_POST['reason']);
            $user_id = $_SESSION['user_id'] ?? null;

            $pdo = Database::getConnection();
            
            // Získání starých hodnot pro uložení do historie
            $stmt = $pdo->prepare("SELECT period_days FROM asset_form_rules WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            if ($old && !empty($reason)) {
                $old_period = $old['period_days'];
                
                try {
                    $pdo->beginTransaction();
                    
                    // 1. Úprava samotného pravidla
                    $upd = $pdo->prepare("UPDATE asset_form_rules SET period_days = ?, warning_days = ? WHERE id = ?");
                    $upd->execute([$new_period, $new_warning, $id]);
                    
                    // 2. Zápis do auditního logu (historie)
                    $log = $pdo->prepare("INSERT INTO plan_changes_log (rule_id, user_id, old_period, new_period, reason) VALUES (?, ?, ?, ?, ?)");
                    $log->execute([$id, $user_id, $old_period, $new_period, $reason]);
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Transakce úpravy plánu selhala: " . $e->getMessage());
                    die("Chyba při ukládání plánu. Zkuste to prosím znovu.");
                }
            }
        }
        header('Location: index.php?page=plans');
        exit;
    }

    // Odstranění plánu
    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            PlanModel::delete($id);
        }
        header('Location: index.php?page=plans');
        exit;
    }
}
