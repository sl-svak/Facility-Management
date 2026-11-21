<?php

class OutputTemplateModel {

    public static function getAllForUser($assetFilter = '1=1') {
        $pdo = Database::getConnection();
        $sql = "
            SELECT ot.*,
                   a.name AS asset_name,
                   a.department_id,
                   d.name AS department_name,
                   ft.title AS source_form_title,
                   ft.schema_json AS source_schema_json,
                   u.first_name,
                   u.last_name
            FROM output_form_templates ot
            JOIN assets a ON ot.asset_id = a.id
            LEFT JOIN departments d ON a.department_id = d.id
            LEFT JOIN form_templates ft ON ot.source_form_template_id = ft.id
            LEFT JOIN users u ON ot.created_by = u.id
            WHERE a.is_active = 1 AND ($assetFilter)
            ORDER BY a.name ASC, ot.title ASC, ot.id DESC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public static function getForAsset($assetId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT ot.*, ft.title AS source_form_title, ft.schema_json AS source_schema_json
            FROM output_form_templates ot
            LEFT JOIN form_templates ft ON ot.source_form_template_id = ft.id
            WHERE ot.asset_id = ?
            ORDER BY ot.title ASC, ot.id DESC
        ");
        $stmt->execute([(int)$assetId]);
        return $stmt->fetchAll();
    }

    public static function getById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT ot.*, a.name AS asset_name, a.department_id, ft.title AS source_form_title, ft.schema_json AS source_schema_json
            FROM output_form_templates ot
            JOIN assets a ON ot.asset_id = a.id
            LEFT JOIN form_templates ft ON ot.source_form_template_id = ft.id
            WHERE ot.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    /**
     * Vrátí aktivní kontrolní formuláře přiřazené přes pravidla (asset_form_rules) ke konkrétnímu zařízení,
     * včetně rozparsovaného schématu evidovaných parametrů.
     */
    public static function getAssetInspectionFormsWithFields($assetId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT ft.id, ft.title, ft.schema_json
            FROM asset_form_rules afr
            JOIN form_templates ft ON afr.form_template_id = ft.id
            WHERE afr.asset_id = ? AND ft.is_active = 1
            ORDER BY ft.title ASC, ft.id ASC
        ");
        $stmt->execute([(int)$assetId]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $fields = self::extractFieldsFromSchema($row['schema_json'] ?? '[]', (int)$row['id'], (string)$row['title']);
            $result[] = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'fields' => $fields,
            ];
        }
        return $result;
    }

    /**
     * Rozparsuje schema_json kontrolního formuláře na seznam parametrů.
     */
    public static function extractFieldsFromSchema($schemaJson, $formId = 0, $formTitle = '') {
        $decoded = json_decode((string)$schemaJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $fields = [];
        $seen = [];
        foreach ($decoded as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string)($item['label'] ?? ''));
            $fieldId = trim((string)($item['id'] ?? ''));
            if ($fieldId === '') {
                $fieldId = $label !== '' ? $label : ('field_' . $idx);
            }
            if ($label === '') {
                $label = $fieldId;
            }
            $type = trim((string)($item['type'] ?? 'text'));
            if ($type === 'header' || $type === 'section') {
                continue;
            }

            $uniqueKey = $fieldId;
            if (isset($seen[$uniqueKey])) {
                continue;
            }
            $seen[$uniqueKey] = true;

            $fields[] = [
                'id' => $fieldId,
                'label' => $label,
                'type' => $type,
                'unit' => (string)($item['unit'] ?? ''),
                'form_id' => (int)$formId,
                'form_title' => (string)$formTitle,
            ];
        }

        return $fields;
    }

    /**
     * Vrátí sadu ID aktivních kontrolních formulářů přiřazených k zařízení (seřazené pole unikátních int ID).
     */
    public static function getAssetFormSignature($assetId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT afr.form_template_id
            FROM asset_form_rules afr
            JOIN form_templates ft ON afr.form_template_id = ft.id
            WHERE afr.asset_id = ? AND ft.is_active = 1
            ORDER BY afr.form_template_id ASC
        ");
        $stmt->execute([(int)$assetId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($ids);
        return $ids;
    }

    /**
     * Vrátí seznam jiných zařízení (v rámci oprávnění uživatele), která používají stejné kontrolní formuláře
     * jako zdrojové zařízení šablony.
     */
    public static function getCompatibleTargetAssets($sourceAssetId, $assetFilter = '1=1') {
        $sourceSig = self::getAssetFormSignature($sourceAssetId);
        if (empty($sourceSig)) {
            return [];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT a.id, a.name, d.name AS department_name
            FROM assets a
            LEFT JOIN departments d ON a.department_id = d.id
            WHERE a.is_active = 1 AND a.id != " . (int)$sourceAssetId . " AND ($assetFilter)
            ORDER BY a.name ASC
        ");
        $candidates = $stmt->fetchAll();

        $compatible = [];
        foreach ($candidates as $cand) {
            $candSig = self::getAssetFormSignature((int)$cand['id']);
            if ($candSig === $sourceSig) {
                $compatible[] = $cand;
            }
        }

        return $compatible;
    }

    public static function create(array $data) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO output_form_templates (
                asset_id, source_form_template_id, title, paper_code, header_note,
                fields_json, show_signatures, show_photos, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            (int)$data['asset_id'],
            !empty($data['source_form_template_id']) ? (int)$data['source_form_template_id'] : null,
            (string)$data['title'],
            $data['paper_code'] !== '' ? (string)$data['paper_code'] : null,
            $data['header_note'] !== '' ? (string)$data['header_note'] : null,
            (string)$data['fields_json'],
            !empty($data['show_signatures']) ? 1 : 0,
            !empty($data['show_photos']) ? 1 : 0,
            !empty($data['created_by']) ? (int)$data['created_by'] : null,
        ]);
    }

    public static function update($id, array $data) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE output_form_templates
            SET source_form_template_id = ?,
                title = ?,
                paper_code = ?,
                header_note = ?,
                fields_json = ?,
                show_signatures = ?,
                show_photos = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            !empty($data['source_form_template_id']) ? (int)$data['source_form_template_id'] : null,
            (string)$data['title'],
            $data['paper_code'] !== '' ? (string)$data['paper_code'] : null,
            $data['header_note'] !== '' ? (string)$data['header_note'] : null,
            (string)$data['fields_json'],
            !empty($data['show_signatures']) ? 1 : 0,
            !empty($data['show_photos']) ? 1 : 0,
            (int)$id,
        ]);
    }

    public static function copyToAsset($templateId, $targetAssetId, $newTitle = '', $createdBy = null) {
        $tpl = self::getById($templateId);
        if (!$tpl) {
            return false;
        }

        $title = trim((string)$newTitle);
        if ($title === '') {
            $title = (string)$tpl['title'];
        }

        return self::create([
            'asset_id' => (int)$targetAssetId,
            'source_form_template_id' => !empty($tpl['source_form_template_id']) ? (int)$tpl['source_form_template_id'] : null,
            'title' => $title,
            'paper_code' => (string)($tpl['paper_code'] ?? ''),
            'header_note' => (string)($tpl['header_note'] ?? ''),
            'fields_json' => (string)$tpl['fields_json'],
            'show_signatures' => (int)($tpl['show_signatures'] ?? 1),
            'show_photos' => (int)($tpl['show_photos'] ?? 1),
            'created_by' => $createdBy,
        ]);
    }

    public static function delete($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM output_form_templates WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
