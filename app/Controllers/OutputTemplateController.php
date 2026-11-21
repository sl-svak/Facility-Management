<?php

class OutputTemplateController {

    private static function ensureModuleActive() {
        if (!SettingModel::isModuleEnabled('module_output_templates')) {
            renderView('module_disabled', [
                'pageTitle' => 'Modul vypnut',
                'moduleTitle' => 'Vlastní šablony výstupních formulářů',
                'settingKey' => 'module_output_templates'
            ]);
            exit;
        }
    }

    private static function getAccessibleAssets() {
        if (Auth::isAdmin()) {
            return AssetModel::getAll();
        }
        $currentUser = UserModel::getById($_SESSION['user_id']);
        $userDepts = $currentUser['departments'] ?? [];
        return empty($userDepts) ? [] : AssetModel::getAll($userDepts);
    }

    public static function index() {
        self::ensureModuleActive();

        $assets = self::getAccessibleAssets();
        $selectedAssetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
        if ($selectedAssetId === 0 && !empty($assets)) {
            $selectedAssetId = (int)$assets[0]['id'];
        }

        if ($selectedAssetId > 0 && !Auth::canAccessAsset($selectedAssetId)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $editTemplate = null;
        if (!empty($_GET['edit_id'])) {
            $candidate = OutputTemplateModel::getById((int)$_GET['edit_id']);
            if ($candidate) {
                if (!Auth::canAccessAsset((int)$candidate['asset_id'])) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění k této šabloně.");
                }
                $editTemplate = $candidate;
                $selectedAssetId = (int)$candidate['asset_id'];
            }
        }

        $assetFormsWithFields = $selectedAssetId > 0
            ? OutputTemplateModel::getAssetInspectionFormsWithFields($selectedAssetId)
            : [];

        $deptFilter = Auth::getDepartmentSqlFilter('a');
        $templates = OutputTemplateModel::getAllForUser($deptFilter);

        $compatibleTargetsMap = [];
        foreach ($templates as $tpl) {
            $tid = (int)$tpl['id'];
            $compatibleTargetsMap[$tid] = OutputTemplateModel::getCompatibleTargetAssets((int)$tpl['asset_id'], $deptFilter);
        }

        renderView('output_templates', [
            'pageTitle' => 'Šablony výstupních formulářů',
            'assets' => $assets,
            'selectedAssetId' => $selectedAssetId,
            'assetFormsWithFields' => $assetFormsWithFields,
            'templates' => $templates,
            'editTemplate' => $editTemplate,
            'compatibleTargetsMap' => $compatibleTargetsMap,
        ]);
    }

    public static function save() {
        self::ensureModuleActive();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=output_templates');
            exit;
        }

        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $assetId = (int)($_POST['asset_id'] ?? 0);
        $sourceFormId = !empty($_POST['source_form_template_id']) ? (int)$_POST['source_form_template_id'] : null;
        $title = trim((string)($_POST['title'] ?? ''));
        $paperCode = trim((string)($_POST['paper_code'] ?? ''));
        $headerNote = trim((string)($_POST['header_note'] ?? ''));
        $showSignatures = !empty($_POST['show_signatures']) ? 1 : 0;
        $showPhotos = !empty($_POST['show_photos']) ? 1 : 0;

        if ($assetId <= 0 || !Auth::canAccessAsset($assetId)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        if ($id > 0) {
            $existing = OutputTemplateModel::getById($id);
            if (!$existing || !Auth::canAccessAsset((int)$existing['asset_id'])) {
                http_response_code(403);
                die("Bezpečnostní chyba: Nemáte oprávnění upravovat tuto šablonu.");
            }
            $assetId = (int)$existing['asset_id'];
        }

        if ($title === '') {
            header('Location: index.php?page=output_templates&asset_id=' . $assetId . '&error=' . urlencode('Vyplňte název výstupní šablony.'));
            exit;
        }

        // Povolené parametry pouze z kontrolních formulářů přiřazených k tomuto zařízení
        $formsWithFields = OutputTemplateModel::getAssetInspectionFormsWithFields($assetId);
        $allowedFieldMap = [];
        foreach ($formsWithFields as $formGroup) {
            foreach ($formGroup['fields'] as $f) {
                $fid = (string)$f['id'];
                if (!isset($allowedFieldMap[$fid])) {
                    $allowedFieldMap[$fid] = $f;
                }
            }
        }

        $selectedIds = $_POST['selected_fields'] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = [];
        }
        $orderMap = $_POST['field_order'] ?? [];
        if (!is_array($orderMap)) {
            $orderMap = [];
        }
        $customLabels = $_POST['field_custom_label'] ?? [];
        if (!is_array($customLabels)) {
            $customLabels = [];
        }

        $chosenItems = [];
        foreach ($selectedIds as $rawId) {
            $fid = trim((string)$rawId);
            if ($fid === '' || !isset($allowedFieldMap[$fid])) {
                continue;
            }
            $orderVal = isset($orderMap[$fid]) ? (int)$orderMap[$fid] : 100;
            $customLabel = trim((string)($customLabels[$fid] ?? ''));
            $chosenItems[] = [
                'id' => $fid,
                'label' => $customLabel !== '' ? $customLabel : (string)$allowedFieldMap[$fid]['label'],
                'original_label' => (string)$allowedFieldMap[$fid]['label'],
                'order' => $orderVal,
            ];
        }

        usort($chosenItems, function ($a, $b) {
            return ($a['order'] <=> $b['order']);
        });

        $normalizedFields = [];
        foreach ($chosenItems as $idx => $item) {
            $normalizedFields[] = [
                'id' => $item['id'],
                'label' => $item['label'],
                'original_label' => $item['original_label'],
                'order' => ($idx + 1) * 10,
            ];
        }

        if (empty($normalizedFields)) {
            header('Location: index.php?page=output_templates&asset_id=' . $assetId . '&error=' . urlencode('Vyberte alespoň jeden sledovaný parametr z kontrolního formuláře zařízení.'));
            exit;
        }

        $payload = [
            'asset_id' => $assetId,
            'source_form_template_id' => $sourceFormId,
            'title' => $title,
            'paper_code' => $paperCode,
            'header_note' => $headerNote,
            'fields_json' => json_encode($normalizedFields, JSON_UNESCAPED_UNICODE),
            'show_signatures' => $showSignatures,
            'show_photos' => $showPhotos,
            'created_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($id > 0) {
            OutputTemplateModel::update($id, $payload);
            header('Location: index.php?page=output_templates&asset_id=' . $assetId . '&saved=1');
        } else {
            OutputTemplateModel::create($payload);
            header('Location: index.php?page=output_templates&asset_id=' . $assetId . '&created=1');
        }
        exit;
    }

    public static function copy() {
        self::ensureModuleActive();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=output_templates');
            exit;
        }

        $templateId = (int)($_POST['template_id'] ?? 0);
        $targetAssetId = (int)($_POST['target_asset_id'] ?? 0);
        $newTitle = trim((string)($_POST['new_title'] ?? ''));

        $tpl = OutputTemplateModel::getById($templateId);
        if (!$tpl) {
            http_response_code(404);
            die("Šablona nenalezena.");
        }

        if (!Auth::canAccessAsset((int)$tpl['asset_id']) || !Auth::canAccessAsset($targetAssetId)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k cílovému nebo zdrojovému zařízení.");
        }

        $deptFilter = Auth::getDepartmentSqlFilter('a');
        $compatible = OutputTemplateModel::getCompatibleTargetAssets((int)$tpl['asset_id'], $deptFilter);
        $compatibleIds = array_map(function ($row) {
            return (int)$row['id'];
        }, $compatible);

        if (!in_array($targetAssetId, $compatibleIds, true)) {
            header('Location: index.php?page=output_templates&asset_id=' . (int)$tpl['asset_id'] . '&error=' . urlencode('Cílové zařízení nepoužívá stejnou sadu kontrolních formulářů jako zdrojové zařízení.'));
            exit;
        }

        $ok = OutputTemplateModel::copyToAsset($templateId, $targetAssetId, $newTitle, $_SESSION['user_id'] ?? null);
        if ($ok) {
            header('Location: index.php?page=output_templates&asset_id=' . $targetAssetId . '&copied=1');
        } else {
            header('Location: index.php?page=output_templates&asset_id=' . (int)$tpl['asset_id'] . '&error=' . urlencode('Kopírování šablony se nezdařilo.'));
        }
        exit;
    }

    public static function delete() {
        self::ensureModuleActive();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $tpl = OutputTemplateModel::getById($id);
            if ($tpl) {
                if (!Auth::canAccessAsset((int)$tpl['asset_id'])) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění smazat tuto šablonu.");
                }
                OutputTemplateModel::delete($id);
                header('Location: index.php?page=output_templates&asset_id=' . (int)$tpl['asset_id'] . '&deleted=1');
                exit;
            }
        }
        header('Location: index.php?page=output_templates');
        exit;
    }
}
