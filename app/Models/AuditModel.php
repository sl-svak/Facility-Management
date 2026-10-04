<?php

class AuditModel {
    
    /**
     * Vypočítá datum další revize na základě data provedení, intervalu a typu legislativy
     */
    public static function calculateNextDueDate($executionDate, $intervalMonths, $deadlineType) {
        $baseTime = strtotime($executionDate);
        if (!$baseTime) {
            return null;
        }

        // Přidání požadovaného počtu měsíců
        $futureTime = strtotime("+$intervalMonths months", $baseTime);

        if ($deadlineType === 'month') {
            // Elektro a podobné: Platí do posledního dne v kalendářním měsíci
            return date('Y-m-t', $futureTime);
        } else {
            // Tlak a podobné: Platí přesně na den ("strict")
            return date('Y-m-d', $futureTime);
        }
    }

    /**
     * Získá blížící se a propadlé revize pro zobrazení na Dashboardu (Ochrana IDOR zapnuta)
     */
    public static function getDashboardTrafficLights() {
        $pdo = Database::getConnection();
        $deptFilterJoin = " AND " . Auth::getDepartmentSqlFilter('a');
        
        $stmt = $pdo->prepare("
            SELECT p.*, a.name as asset_name, c.name as category_name, c.color_hex
            FROM external_audit_plans p
            JOIN assets a ON p.asset_id = a.id
            JOIN audit_categories c ON p.audit_category_id = c.id
            WHERE p.is_active = 1 AND a.is_active = 1 AND a.operational_status != 'stopped'
            $deptFilterJoin
        ");
        $stmt->execute();
        $plans = $stmt->fetchAll();

        $lights = ['red' => [], 'yellow' => [], 'green' => []];
        $today = new DateTime('midnight');

        foreach ($plans as $plan) {
            // Nejprve zkontrolujeme, zda nečekáme na dodání PDF protokolu (stav "Waiting for protocol")
            $stmtRecord = $pdo->prepare("
                SELECT id, execution_date, supplier_name 
                FROM external_audit_records 
                WHERE plan_id = ? AND status = 'waiting_protocol' 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtRecord->execute([$plan['id']]);
            $waitingRecord = $stmtRecord->fetch();

            if ($waitingRecord) {
                // ŽLUTÁ: Fyzicky hotovo, ale chybí byrokracie
                $plan['record_id'] = $waitingRecord['id'];
                $plan['execution_date'] = $waitingRecord['execution_date'];
                $plan['supplier_name'] = $waitingRecord['supplier_name'];
                $lights['yellow'][] = $plan;
                continue;
            }

            // Pokud nečekáme na protokol, zkontrolujeme platnost termínu
            $dueDate = new DateTime($plan['next_due_date']);
            $diffDays = (int)$today->diff($dueDate)->format('%R%a');

            if ($diffDays < 0) {
                // ČERVENÁ: Propadlá revize
                $plan['days_remaining'] = $diffDays;
                $lights['red'][] = $plan;
            } elseif ($diffDays <= 30) {
                // ORANŽOVÁ (do červené složky): Blíží se termín do 30 dnů (k objednání)
                $plan['days_remaining'] = $diffDays;
                $lights['red'][] = $plan;
            } else {
                // ZELENÁ: Vše platné
                $plan['days_remaining'] = $diffDays;
                $lights['green'][] = $plan;
            }
        }

        // Seřadit červenou sekci od nejkritičtějších (nejvíce v minusu)
        usort($lights['red'], function($a, $b) {
            return $a['days_remaining'] <=> $b['days_remaining'];
        });

        return $lights;
    }
}