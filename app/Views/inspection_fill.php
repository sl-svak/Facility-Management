<?php
$schema = json_decode((string)($template['schema_json'] ?? '[]'), true);
if (!is_array($schema)) {
    $schema = [];
}
$lastValues = $lastValues ?? [];
$lastInspectionMeta = $lastInspectionMeta ?? null;
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="margin: 0;"><?= htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p style="margin: 4px 0 0 0; color: var(--text-muted);">
                Zařízení: <strong><?= htmlspecialchars((string)$asset['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($template['estimated_minutes'])): ?>
                    | Odhadovaná doba: ~<?= (int)$template['estimated_minutes'] ?> min
                <?php endif; ?>
            </p>
        </div>
        <button type="button" class="btn" data-action="markAllOk">
            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">done_all</span>
            Označit vše jako OK
        </button>
    </div>

    <?php if ($lastInspectionMeta !== null): ?>
        <div style="margin-top: 12px; padding: 10px 14px; border-radius: 6px; background: rgba(41, 128, 185, 0.08); border: 1px solid rgba(41, 128, 185, 0.25);">
            <strong>Předchozí kontrola:</strong>
            <?= date('d.m.Y H:i', strtotime((string)$lastInspectionMeta['created_at'])) ?>
            (stav: <strong><?= htmlspecialchars((string)$lastInspectionMeta['status'], ENT_QUOTES, 'UTF-8') ?></strong>)
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=inspection_save" id="inspectionForm" style="margin-top: 18px;" data-asset-id="<?= (int)$asset['id'] ?>" data-template-id="<?= (int)$template['id'] ?>" data-submit="validateInspectionForm">
        <?= Security::csrfField() ?>
        <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
        <input type="hidden" name="form_template_id" value="<?= (int)$template['id'] ?>">
        <input type="hidden" name="duration_seconds" id="durationSeconds" value="0">

        <div style="background: rgba(41, 128, 185, 0.1); padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; color: var(--info); display: flex; align-items: center; justify-content: space-between;">
            <span><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">timer</span> Doba vyplňování</span>
            <strong id="timerDisplay">00:00</strong>
        </div>

        <?php foreach ($schema as $index => $field):
            if (!is_array($field)) {
                continue;
            }
            $fieldName = (string)($field['label'] ?? ('Položka #' . ($index + 1)));
            // ID musí odpovídat serverové validaci (InspectionController::save): id, jinak původní label
            $fieldId = (string)($field['id'] ?? ($field['label'] ?? ('Položka #' . ($index + 1))));
            if (strpos($fieldName, 'Podpist') !== false) {
                $fieldName = str_replace('Podpist', 'Podpis', $fieldName);
            }
            $fieldType = (string)($field['type'] ?? 'text');
            $isRequired = !empty($field['required']);
            $requiredAttr = $isRequired ? 'required' : '';
            $isStatusField = ($fieldType === 'asset_status');
            $blockClass = $isStatusField ? 'form-field-block' : 'form-field-block hidable-block';
            $nameAttr = htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8');

            $prevVal = $lastValues[$fieldId] ?? null;
            $prevText = '';
            if (is_string($prevVal) || is_numeric($prevVal)) {
                $prevText = (string)$prevVal;
                if (strpos($prevText, 'assets/uploads/') === 0 || strpos($prevText, 'data:image/') === 0) {
                    $prevText = '';
                }
            } elseif (is_array($prevVal) && isset($prevVal['status'])) {
                $prevText = (string)$prevVal['status'];
            }
        ?>
            <div class="<?= $blockClass ?>">
                <label class="field-title">
                    <?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($isRequired): ?><span style="color: var(--danger); margin-left: 3px;">*</span><?php endif; ?>
                </label>

                <?php if ($prevText !== '' && !in_array($fieldType, ['photo', 'signature'], true)): ?>
                    <div style="margin-bottom: 8px; font-size: 0.88em; color: var(--text-muted);">
                        Minule: <strong><?= htmlspecialchars($prevText, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($isStatusField): ?>
                    <div style="display: flex; gap: 15px;">
                        <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid var(--info); border-radius: 6px; cursor: pointer; background: rgba(41, 128, 185, 0.1); font-weight: bold; color: var(--info); margin: 0;">
                            <input type="radio" name="data[<?= $nameAttr ?>]" value="V provozu" checked data-change="toggleFormFields" style="margin-right: 5px; transform: scale(1.2);"> V provozu
                        </label>
                        <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid var(--text-muted); border-radius: 6px; cursor: pointer; background: var(--background); font-weight: bold; color: var(--text-muted); margin: 0;">
                            <input type="radio" name="data[<?= $nameAttr ?>]" value="Odstaveno" data-change="toggleFormFields" style="margin-right: 5px; transform: scale(1.2);"> Odstaveno
                        </label>
                    </div>
                <?php elseif ($fieldType === 'radio_ok_ko' || $fieldType === 'status_ok_ko'): ?>
                    <div style="display: flex; gap: 15px;">
                        <label class="radio-btn-ok"><input type="radio" name="data[<?= $nameAttr ?>]" value="OK" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> OK (V pořádku)</label>
                        <label class="radio-btn-ko"><input type="radio" name="data[<?= $nameAttr ?>]" value="KO" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> KO (Závada)</label>
                    </div>
                <?php elseif ($fieldType === 'radio_yes_no'): ?>
                    <div style="display: flex; gap: 15px;">
                        <label class="radio-btn-neutral"><input type="radio" name="data[<?= $nameAttr ?>]" value="Ano" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ano</label>
                        <label class="radio-btn-neutral"><input type="radio" name="data[<?= $nameAttr ?>]" value="Ne" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ne</label>
                    </div>
                <?php elseif ($fieldType === 'number' || $fieldType === 'numeric_limit' || $fieldType === 'meter_reading'): ?>
                    <?php
                        $unitStr = !empty($field['unit']) ? ' (' . htmlspecialchars((string)$field['unit'], ENT_QUOTES, 'UTF-8') . ')' : '';
                        $limitHelp = '';
                        if ($fieldType === 'numeric_limit') {
                            $minTxt = (isset($field['min']) && $field['min'] !== null && $field['min'] !== '') ? htmlspecialchars((string)$field['min'], ENT_QUOTES, 'UTF-8') : '-';
                            $maxTxt = (isset($field['max']) && $field['max'] !== null && $field['max'] !== '') ? htmlspecialchars((string)$field['max'], ENT_QUOTES, 'UTF-8') : '-';
                            $limitHelp = "<div style='font-size: 0.85em; color: var(--warning); margin-top: 5px;'><span class='material-symbols-outlined' style='font-size: 1em; vertical-align: middle;'>info</span> Povolený rozsah: <strong>$minTxt až $maxTxt$unitStr</strong>. Zápis hodnoty mimo tento rozsah automaticky vygeneruje závadu!</div>";
                        }
                    ?>
                    <input type="number" step="any" name="data[<?= $nameAttr ?>]" <?= $requiredAttr ?> placeholder="Zadejte hodnotu<?= $unitStr ?>...">
                    <?= $limitHelp ?>
                <?php elseif ($fieldType === 'textarea'): ?>
                    <textarea name="data[<?= $nameAttr ?>]" <?= $requiredAttr ?> placeholder="Textová poznámka..." style="min-height: 100px; resize: vertical;"></textarea>

                <?php elseif ($fieldType === 'photo'): ?>
                    <div style="background: var(--card-bg); border: 1px dashed var(--border-color); border-radius: 4px; padding: 10px; text-align: center;">
                        <div id="preview_<?= (int)$index ?>" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 15px;"></div>

                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <button type="button" class="btn btn-warning" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-action="addPhotoInput" data-args="<?= htmlspecialchars(json_encode([(int)$index, (string)$fieldId, 'camera']), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="material-symbols-outlined" style="vertical-align: middle;">photo_camera</span> Vyfotit
                            </button>
                            <button type="button" class="btn btn-info" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-action="addPhotoInput" data-args="<?= htmlspecialchars(json_encode([(int)$index, (string)$fieldId, 'gallery']), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="material-symbols-outlined" style="vertical-align: middle;">photo_library</span> Z galerie
                            </button>
                        </div>
                        <div id="loading_<?= (int)$index ?>" style="display: none; color: var(--warning); font-size: 0.85em; margin-bottom: 10px;"><span class="material-symbols-outlined" style="vertical-align: middle; animation: spin 1.5s linear infinite;">sync</span> Zpracovávám (HEIC)...</div>
                        <div id="inputs_<?= (int)$index ?>" style="display: none;"></div>

                        <input type="hidden" id="photoReq_<?= (int)$index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                <?php elseif ($fieldType === 'signature'): ?>
                    <div style="border: 1px dashed var(--border-color); background: var(--card-bg); border-radius: 4px; padding: 10px; text-align: center;">
                        <canvas id="sigCanvas_<?= (int)$index ?>" width="400" height="150" style="background: #fff; border: 1px solid var(--border-color); touch-action: none; max-width: 100%; border-radius: 4px;"></canvas>
                        <input type="hidden" name="data[<?= $nameAttr ?>]" id="sigInput_<?= (int)$index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>">
                        <br><button type="button" class="btn" data-action="clearInspectionSignature" data-args="[<?= (int)$index ?>]" style="margin-top: 8px; padding: 6px 12px; background: var(--background); color: var(--text-main); font-weight: bold;">Vymazat podpis</button>
                    </div>
                <?php else: ?>
                    <input type="text" name="data[<?= $nameAttr ?>]" <?= $requiredAttr ?> placeholder="Zadejte text...">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-success" style="width: 100%; padding: 16px; font-size: 1.1em; border-radius: 6px; box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2); margin-top: 20px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Odeslat a uložit
        </button>
        <a href="index.php?page=qr_reader" class="btn" style="margin-top: 10px; display: block; text-align: center;">Zpět</a>
    </form>
</div>
