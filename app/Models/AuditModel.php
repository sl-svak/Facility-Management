<?php

class AuditModel {

    public static function calculateNextDueDate($executionDate, $intervalMonths, $deadlineType) {
        $intervalMonths = max(1, (int)$intervalMonths);
        $baseTime = strtotime((string)$executionDate);
        if (!$baseTime) {
            return null;
        }

        $futureTime = strtotime('+' . $intervalMonths . ' months', $baseTime);
        if (!$futureTime) {
            return null;
        }

        if ($deadlineType === 'month') {
            return date('Y-m-t', $futureTime);
        }
        return date('Y-m-d', $futureTime);
    }

    public static function getCategories($includeInactive = false) {
        $pdo = Database::getConnection();
        $where = $includeInactive ? '1=1' : 'is_active = 1';
        return $pdo->query("SELECT * FROM audit_categories WHERE $where ORDER BY name ASC, id ASC")->fetchAll();
    }

    public static function createCategory($name, $defaultIntervalMonths, $defaultDeadlineType, $colorHex = '#2980b9') {
        $pdo = Database::getConnection();
        $deadline = ($defaultDeadlineType === 'month') ? 'month' : 'strict';
        $interval = max(1, (int)$defaultIntervalMonths);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$colorHex) ? (string)$colorHex : '#2980b9';

        $stmt = $pdo->prepare("
            INSERT INTO audit_categories (name, default_interval_months, default_deadline_type, color_hex, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        return $stmt->execute([(string)$name, $interval, $deadline, $color]);
    }

    public static function getSuppliers($includeInactive = false) {
        $pdo = Database::getConnection();
        $where = $includeInactive ? '1=1' : 'is_active = 1';
        return $pdo->query("SELECT * FROM external_suppliers WHERE $where ORDER BY name ASC, id ASC")->fetchAll();
    }

    public static function createSupplier($name, $contactPerson = '', $phone = '', $email = '', $notes = '') {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO external_suppliers (name, contact_person, phone, email, notes, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        return $stmt->execute([
            (string)$name,
            $contactPerson !== '' ? (string)$contactPerson : null,
            $phone !== '' ? (string)$phone : null,
            $email !== '' ? (string)$email : null,
            $notes !== '' ? (string)$notes : null,
        ]);
    }

    public static function getPlansForUser($assetFilter = '1=1') {
        $pdo = Database::getConnection();
        $sql = "
            SELECT p.*,
                   a.name AS asset_name,
                   a.operational_status,
                   d.name AS department_name,
                   c.name AS category_name,
                   c.color_hex,
                   s.name AS default_supplier_name,
                   (SELECT COUNT(*) FROM external_audit_records r WHERE r.plan_id = p.id AND r.status = 'waiting_protocol') AS waiting_protocol_count
            FROM external_audit_plans p
            JOIN assets a ON p.asset_id = a.id
            LEFT JOIN departments d ON a.department_id = d.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            LEFT JOIN external_suppliers s ON p.default_supplier_id = s.id
            WHERE p.is_active = 1 AND a.is_active = 1 AND ($assetFilter)
            ORDER BY p.next_due_date ASC, a.name ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function getPlanById($planId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT p.*,
                   a.name AS asset_name,
                   a.department_id,
                   c.name AS category_name,
                   s.name AS default_supplier_name
            FROM external_audit_plans p
            JOIN assets a ON p.asset_id = a.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            LEFT JOIN external_suppliers s ON p.default_supplier_id = s.id
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$planId]);
        return $stmt->fetch();
    }

    public static function createPlan(array $data) {
        $pdo = Database::getConnection();
        $intervalMonths = max(1, (int)($data['interval_months'] ?? 12));
        $warningDays = max(0, (int)($data['warning_days'] ?? 30));
        $deadlineType = (($data['deadline_type'] ?? 'strict') === 'month') ? 'month' : 'strict';
        $lastExec = !empty($data['last_execution_date']) ? (string)$data['last_execution_date'] : null;

        if (!empty($data['next_due_date'])) {
            $nextDue = (string)$data['next_due_date'];
        } elseif ($lastExec !== null) {
            $nextDue = (string)(self::calculateNextDueDate($lastExec, $intervalMonths, $deadlineType) ?? date('Y-m-d', strtotime('+' . $intervalMonths . ' months')));
        } else {
            $nextDue = (string)(self::calculateNextDueDate(date('Y-m-d'), $intervalMonths, $deadlineType) ?? date('Y-m-d'));
        }

        $stmt = $pdo->prepare("
            INSERT INTO external_audit_plans (
                asset_id, audit_category_id, default_supplier_id,
                interval_months, warning_days, deadline_type,
                last_execution_date, next_due_date, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        return $stmt->execute([
            (int)$data['asset_id'],
            (int)$data['audit_category_id'],
            !empty($data['default_supplier_id']) ? (int)$data['default_supplier_id'] : null,
            $intervalMonths,
            $warningDays,
            $deadlineType,
            $lastExec,
            $nextDue,
        ]);
    }

    public static function deletePlan($planId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE external_audit_plans SET is_active = 0 WHERE id = ?");
        return $stmt->execute([(int)$planId]);
    }

    public static function getRecordsForUser($assetFilter = '1=1', $limit = 100) {
        $pdo = Database::getConnection();
        $limit = max(1, min(500, (int)$limit));
        $sql = "
            SELECT r.*,
                   a.name AS asset_name,
                   c.name AS category_name,
                   c.color_hex,
                   s.name AS linked_supplier_name,
                   u.first_name,
                   u.last_name
            FROM external_audit_records r
            JOIN assets a ON r.asset_id = a.id
            JOIN external_audit_plans p ON r.plan_id = p.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            LEFT JOIN external_suppliers s ON r.supplier_id = s.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE a.is_active = 1 AND ($assetFilter)
            ORDER BY r.execution_date DESC, r.id DESC
            LIMIT $limit
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function getRecordById($recordId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, a.name AS asset_name, a.department_id, c.name AS category_name
            FROM external_audit_records r
            JOIN assets a ON r.asset_id = a.id
            JOIN external_audit_plans p ON r.plan_id = p.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([(int)$recordId]);
        return $stmt->fetch();
    }

    /**
     * Zapíše provedenou externí revizi, přepočte další termín na plánu a vrátí ID nového záznamu.
     */
    public static function recordExecution(array $plan, array $input, $userId = null) {
        $pdo = Database::getConnection();

        $executionDate = (string)$input['execution_date'];
        $intervalMonths = max(1, (int)($plan['interval_months'] ?? 12));
        $deadlineType = (($plan['deadline_type'] ?? 'strict') === 'month') ? 'month' : 'strict';

        $nextDueDate = self::calculateNextDueDate($executionDate, $intervalMonths, $deadlineType);
        if (!$nextDueDate) {
            $nextDueDate = date('Y-m-d', strtotime('+' . $intervalMonths . ' months', strtotime($executionDate)));
        }

        $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : (!empty($plan['default_supplier_id']) ? (int)$plan['default_supplier_id'] : null);
        $supplierName = trim((string)($input['supplier_name'] ?? ''));

        if ($supplierName === '' && $supplierId !== null) {
            $stSup = $pdo->prepare("SELECT name FROM external_suppliers WHERE id = ?");
            $stSup->execute([$supplierId]);
            $foundName = $stSup->fetchColumn();
            if ($foundName !== false) {
                $supplierName = (string)$foundName;
            }
        }
        if ($supplierName === '') {
            $supplierName = 'Externí dodavatel';
        }

        $protocolFile = !empty($input['protocol_file']) ? (string)$input['protocol_file'] : null;
        $protocolNumber = trim((string)($input['protocol_number'] ?? ''));
        $notes = trim((string)($input['notes'] ?? ''));
        $status = ($protocolFile !== null) ? 'completed' : 'waiting_protocol';

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO external_audit_records (
                    plan_id, asset_id, supplier_id, supplier_name,
                    execution_date, next_due_date, status,
                    protocol_file, protocol_number, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                (int)$plan['id'],
                (int)$plan['asset_id'],
                $supplierId,
                $supplierName,
                $executionDate,
                $nextDueDate,
                $status,
                $protocolFile,
                $protocolNumber !== '' ? $protocolNumber : null,
                $notes !== '' ? $notes : null,
                $userId ? (int)$userId : null,
            ]);
            $recordId = (int)$pdo->lastInsertId();

            $upd = $pdo->prepare("
                UPDATE external_audit_plans
                SET last_execution_date = ?, next_due_date = ?
                WHERE id = ?
            ");
            $upd->execute([$executionDate, $nextDueDate, (int)$plan['id']]);

            $pdo->commit();
            return $recordId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function attachProtocol($recordId, $protocolFile, $protocolNumber = '') {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE external_audit_records
            SET protocol_file = ?,
                protocol_number = COALESCE(NULLIF(?, ''), protocol_number),
                status = 'completed'
            WHERE id = ?
        ");
        return $stmt->execute([
            (string)$protocolFile,
            trim((string)$protocolNumber),
            (int)$recordId,
        ]);
    }

    /**
     * Vrátí přehled externích revizí rozdělený na čekající na protokol (žlutá),
     * propadlé (červená), blížící se (oranžová) a v pořádku (zelená).
     */
    public static function getDashboardTrafficLights() {
        $pdo = Database::getConnection();
        $deptFilterJoin = " AND " . Auth::getDepartmentSqlFilter('a');

        $stmt = $pdo->prepare("
            SELECT p.*,
                   a.name AS asset_name,
                   a.operational_status,
                   c.name AS category_name,
                   c.color_hex,
                   s.name AS default_supplier_name
            FROM external_audit_plans p
            JOIN assets a ON p.asset_id = a.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            LEFT JOIN external_suppliers s ON p.default_supplier_id = s.id
            WHERE p.is_active = 1 AND a.is_active = 1 AND a.operational_status != 'stopped'
            $deptFilterJoin
        ");
        $stmt->execute();
        $plans = $stmt->fetchAll();

        $lights = ['red' => [], 'yellow' => [], 'orange' => [], 'green' => []];
        $today = new DateTime('today');

        foreach ($plans as $plan) {
            $stmtRecord = $pdo->prepare("
                SELECT id, execution_date, supplier_name, protocol_number
                FROM external_audit_records
                WHERE plan_id = ? AND status = 'waiting_protocol'
                ORDER BY id DESC LIMIT 1
            ");
            $stmtRecord->execute([(int)$plan['id']]);
            $waitingRecord = $stmtRecord->fetch();

            $dueDate = new DateTime((string)$plan['next_due_date']);
            $diffDays = (int)$today->diff($dueDate)->format('%R%a');
            $plan['days_remaining'] = $diffDays;
            $plan['next_due_formatted'] = $dueDate->format('d.m.Y');

            if ($waitingRecord) {
                $plan['record_id'] = (int)$waitingRecord['id'];
                $plan['execution_date'] = (string)$waitingRecord['execution_date'];
                $plan['supplier_name'] = (string)$waitingRecord['supplier_name'];
                $lights['yellow'][] = $plan;
                continue;
            }

            $warnDays = max(0, (int)($plan['warning_days'] ?? 30));
            if ($diffDays < 0) {
                $lights['red'][] = $plan;
            } elseif ($diffDays <= $warnDays) {
                $lights['orange'][] = $plan;
            } else {
                $lights['green'][] = $plan;
            }
        }

        usort($lights['red'], function ($a, $b) {
            return ($a['days_remaining'] <=> $b['days_remaining']);
        });
        usort($lights['orange'], function ($a, $b) {
            return ($a['days_remaining'] <=> $b['days_remaining']);
        });

        return $lights;
    }
}
