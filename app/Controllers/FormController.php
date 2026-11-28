<?php
class FormController {

    /**
     * Šablony jsou společné pro všechny úseky. Ne-administrátor smí šablonu měnit (upravit/smazat)
     * jen tehdy, když ji v plánech údržby používají výhradně zařízení z jeho úseků.
     */
    private static function canModifyTemplate($id) {
        if (Auth::isAdmin()) {
            return true;
        }
        $id = (int)$id;
        $all = FormModel::getUsageMap();
        $mine = FormModel::getUsageMap(Auth::getDepartmentSqlFilter('a'));
        return count($all[$id] ?? []) === count($mine[$id] ?? []);
    }

    public static function index() {
        $templates = FormModel::getAll();
        $editTemplate = null;

        if (isset($_GET['edit_id'])) {
            $editId = (int)$_GET['edit_id'];
            $candidate = FormModel::getById($editId);
            // Smazané šablony a šablony používané jinými úseky nelze otevřít k úpravě
            if (!$candidate || (int)$candidate['is_active'] !== 1) {
                header('Location: index.php?page=forms');
                exit;
            }
            if (!self::canModifyTemplate($editId)) {
                header('Location: index.php?page=forms&error=edit_other');
                exit;
            }
            $editTemplate = $candidate;
        }
        
        // Použití šablon v plánech údržby (pro informaci před smazáním).
        // Uživatel vidí jen zařízení ze svých úseků, ostatní se započtou jen počtem.
        $usageAll = FormModel::getUsageMap();
        $usageVisible = Auth::isAdmin() ? $usageAll : FormModel::getUsageMap(Auth::getDepartmentSqlFilter('a'));
        $usage = [];
        foreach ($templates as $tpl) {
            $tid = (int)$tpl['id'];
            $total = count($usageAll[$tid] ?? []);
            $visible = $usageVisible[$tid] ?? [];
            $usage[$tid] = [
                'total' => $total,
                'names' => array_values(array_unique(array_column($visible, 'asset_name'))),
                'hidden' => $total - count($visible),
            ];
        }

        renderView('forms', [
            'pageTitle' => 'Šablony formulářů',
            'templates' => $templates,
            'editTemplate' => $editTemplate,
            'usage' => $usage
        ]);
    }

    public static function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $schema_json = $_POST['schema_json'] ?? '[]';
            
            // BEZPEČNOSTNÍ OPRAVA: Tvrdá validace JSON formátu (Ochrana proti XSS a JSON Injection)
            json_decode($schema_json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $schema_json = '[]';
            }
            
            $estimated_minutes = (int)($_POST['estimated_minutes'] ?? 0);
            
            if (!empty($title) && $schema_json !== '[]') {
                if ($id > 0) {
                    // BEZPEČNOSTNÍ OPRAVA: upravit lze jen existující aktivní šablonu a ne-administrátor
                    // jen takovou, kterou nepoužívají zařízení z cizích úseků (stejně jako u mazání).
                    $existing = FormModel::getById($id);
                    if (!$existing || (int)$existing['is_active'] !== 1) {
                        header('Location: index.php?page=forms');
                        exit;
                    }
                    if (!self::canModifyTemplate($id)) {
                        header('Location: index.php?page=forms&error=edit_other');
                        exit;
                    }
                    FormModel::update($id, $title, $schema_json, $estimated_minutes);
                } else {
                    FormModel::create($title, $schema_json, $estimated_minutes);
                }
            }
        }
        header('Location: index.php?page=forms');
        exit;
    }

    public static function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $pdo = Database::getConnection();

            // Šablony jsou společné pro všechny úseky. Smazáním se odstraní i plány údržby, takže
            // ne-administrátor nesmí smazat šablonu použitou u zařízení mimo své úseky.
            if (!self::canModifyTemplate($id)) {
                header('Location: index.php?page=forms&error=in_use_other');
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Plány údržby (včetně jejich historie změn - ON DELETE CASCADE) přestanou existovat
                $del = $pdo->prepare("DELETE FROM asset_form_rules WHERE form_template_id = ?");
                $del->execute([$id]);
                $removedPlans = $del->rowCount();

                FormModel::delete($id);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Smazání šablony selhalo: ' . $e->getMessage());
                header('Location: index.php?page=forms&error=failed');
                exit;
            }

            header('Location: index.php?page=forms&deleted=1&plans=' . (int)$removedPlans);
            exit;
        }
        header('Location: index.php?page=forms');
        exit;
    }
}
