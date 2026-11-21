<?php

class ExternalAuditController {

    private static function ensureModuleActive() {
        if (!SettingModel::isModuleEnabled('module_external_audits')) {
            renderView('module_disabled', [
                'pageTitle' => 'Modul vypnut',
                'moduleTitle' => 'Externí revize a dodavatelé',
                'settingKey' => 'module_external_audits'
            ]);
            exit;
        }
    }

    private static function isValidDate($value) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$value, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    private static function getAccessibleAssets() {
        if (Auth::isAdmin()) {
            return AssetModel::getAll();
        }
        $currentUser = UserModel::getById($_SESSION['user_id']);
        $userDepts = $currentUser['departments'] ?? [];
        return empty($userDepts) ? [] : AssetModel::getAll($userDepts);
    }

    private static function saveUploadedProtocolPdf($fileInputName = 'protocol_pdf') {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Nahrání PDF protokolu se nezdařilo.'];
        }

        $tmpName = (string)$_FILES[$fileInputName]['tmp_name'];
        $size = (int)@filesize($tmpName);
        if ($size <= 0 || $size > 15 * 1024 * 1024) {
            return ['path' => null, 'error' => 'Soubor protokolu musí být PDF o velikosti nejvýše 15 MB.'];
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            @finfo_close($finfo);
        }

        $header = (string)@file_get_contents($tmpName, false, null, 0, 5);
        if ($mime !== 'application/pdf' || strncmp($header, '%PDF-', 5) !== 0) {
            return ['path' => null, 'error' => 'Povoleny jsou pouze platné PDF soubory (%PDF).'];
        }

        $uploadDir = APP_ROOT . '/assets/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filename = 'rev_' . bin2hex(random_bytes(8)) . '_' . time() . '.pdf';
        $destPath = $uploadDir . '/' . $filename;

        if (!@move_uploaded_file($tmpName, $destPath)) {
            return ['path' => null, 'error' => 'Nepodařilo se uložit PDF protokol do úložiště.'];
        }

        return ['path' => 'assets/uploads/' . $filename, 'error' => null];
    }

    public static function index() {
        self::ensureModuleActive();

        $deptFilter = Auth::getDepartmentSqlFilter('a');
        $assets = self::getAccessibleAssets();
        $categories = AuditModel::getCategories();
        $suppliers = AuditModel::getSuppliers();
        $plans = AuditModel::getPlansForUser($deptFilter);
        $records = AuditModel::getRecordsForUser($deptFilter, 100);
        $lights = AuditModel::getDashboardTrafficLights();

        renderView('external_audits', [
            'pageTitle' => 'Externí revize a dodavatelé',
            'assets' => $assets,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'plans' => $plans,
            'records' => $records,
            'lights' => $lights,
        ]);
    }

    public static function planCreate() {
        self::ensureModuleActive();
        Auth::requireRole(['admin', 'manager']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=external_audits');
            exit;
        }

        $assetId = (int)($_POST['asset_id'] ?? 0);
        $categoryId = (int)($_POST['audit_category_id'] ?? 0);
        $supplierId = !empty($_POST['default_supplier_id']) ? (int)$_POST['default_supplier_id'] : null;
        $intervalMonths = (int)($_POST['interval_months'] ?? 12);
        $warningDays = (int)($_POST['warning_days'] ?? 30);
        $deadlineType = (string)($_POST['deadline_type'] ?? 'strict');
        $lastExecutionDate = trim((string)($_POST['last_execution_date'] ?? ''));
        $nextDueDate = trim((string)($_POST['next_due_date'] ?? ''));

        if ($assetId <= 0 || !Auth::canAccessAsset($assetId)) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        if ($categoryId <= 0) {
            header('Location: index.php?page=external_audits&error=' . urlencode('Vyberte kategorii revize.'));
            exit;
        }

        foreach ([$lastExecutionDate, $nextDueDate] as $dateInput) {
            if ($dateInput !== '' && !self::isValidDate($dateInput)) {
                header('Location: index.php?page=external_audits&error=' . urlencode('Zadejte platné datum (RRRR-MM-DD).'));
                exit;
            }
        }

        AuditModel::createPlan([
            'asset_id' => $assetId,
            'audit_category_id' => $categoryId,
            'default_supplier_id' => $supplierId,
            'interval_months' => max(1, $intervalMonths),
            'warning_days' => max(0, $warningDays),
            'deadline_type' => ($deadlineType === 'month') ? 'month' : 'strict',
            'last_execution_date' => $lastExecutionDate !== '' ? $lastExecutionDate : null,
            'next_due_date' => $nextDueDate !== '' ? $nextDueDate : null,
        ]);

        header('Location: index.php?page=external_audits&plan_created=1');
        exit;
    }

    public static function planDelete() {
        self::ensureModuleActive();
        Auth::requireRole(['admin', 'manager']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
            $planId = (int)$_POST['id'];
            $plan = AuditModel::getPlanById($planId);
            if ($plan) {
                if (!Auth::canAccessAsset((int)$plan['asset_id'])) {
                    http_response_code(403);
                    die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
                }
                AuditModel::deletePlan($planId);
            }
        }

        header('Location: index.php?page=external_audits&plan_deleted=1');
        exit;
    }

    public static function recordCreate() {
        self::ensureModuleActive();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=external_audits');
            exit;
        }

        $planId = (int)($_POST['plan_id'] ?? 0);
        $executionDate = trim((string)($_POST['execution_date'] ?? date('Y-m-d')));
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $supplierName = trim((string)($_POST['supplier_name'] ?? ''));
        $protocolNumber = trim((string)($_POST['protocol_number'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $plan = AuditModel::getPlanById($planId);
        if (!$plan) {
            http_response_code(404);
            die("Plán externí revize nenalezen.");
        }

        if (!Auth::canAccessAsset((int)$plan['asset_id'])) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        if (!self::isValidDate($executionDate)) {
            header('Location: index.php?page=external_audits&error=' . urlencode('Zadejte platné datum provedení revize.'));
            exit;
        }

        $upload = self::saveUploadedProtocolPdf('protocol_pdf');
        if ($upload['error'] !== null) {
            header('Location: index.php?page=external_audits&error=' . urlencode((string)$upload['error']));
            exit;
        }

        AuditModel::recordExecution($plan, [
            'execution_date' => $executionDate,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'protocol_file' => $upload['path'],
            'protocol_number' => $protocolNumber,
            'notes' => $notes,
        ], $_SESSION['user_id'] ?? null);

        header('Location: index.php?page=external_audits&record_created=1');
        exit;
    }

    public static function uploadProtocol() {
        self::ensureModuleActive();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=external_audits');
            exit;
        }

        $recordId = (int)($_POST['record_id'] ?? 0);
        $protocolNumber = trim((string)($_POST['protocol_number'] ?? ''));

        $record = AuditModel::getRecordById($recordId);
        if (!$record) {
            http_response_code(404);
            die("Záznam revize nenalezen.");
        }

        if (!Auth::canAccessAsset((int)$record['asset_id'])) {
            http_response_code(403);
            die("Bezpečnostní chyba: Nemáte oprávnění k tomuto zařízení.");
        }

        $upload = self::saveUploadedProtocolPdf('protocol_pdf');
        if ($upload['error'] !== null || empty($upload['path'])) {
            $err = $upload['error'] ?? 'Vyberte PDF soubor protokolu.';
            header('Location: index.php?page=external_audits&error=' . urlencode($err));
            exit;
        }

        AuditModel::attachProtocol($recordId, $upload['path'], $protocolNumber);
        header('Location: index.php?page=external_audits&protocol_saved=1');
        exit;
    }

    public static function supplierCreate() {
        self::ensureModuleActive();
        Auth::requireRole(['admin', 'manager']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($_POST['name'] ?? ''));
            $contact = trim((string)($_POST['contact_person'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($name !== '') {
                AuditModel::createSupplier($name, $contact, $phone, $email, $notes);
                header('Location: index.php?page=external_audits&supplier_created=1');
                exit;
            }
        }

        header('Location: index.php?page=external_audits');
        exit;
    }

    public static function categoryCreate() {
        self::ensureModuleActive();
        Auth::requireRole(['admin', 'manager']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($_POST['name'] ?? ''));
            $intervalMonths = (int)($_POST['default_interval_months'] ?? 12);
            $deadlineType = (string)($_POST['default_deadline_type'] ?? 'strict');
            $colorHex = trim((string)($_POST['color_hex'] ?? '#2980b9'));

            if ($name !== '') {
                AuditModel::createCategory($name, $intervalMonths, $deadlineType, $colorHex);
                header('Location: index.php?page=external_audits&category_created=1');
                exit;
            }
        }

        header('Location: index.php?page=external_audits');
        exit;
    }
}
