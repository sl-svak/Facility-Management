<?php 
if (!defined('APP_ROOT')) exit; 

$schema = json_decode($template['schema_json'] ?? '[]', true);
if (!is_array($schema)) { $schema = []; }
?>

<!-- Externí knihovna pro konverzi HEIC z iPhonů (Zůstává pouze zde pro úsporu dat) -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<div class="card card-primary-top" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0; color: var(--text-main);">
                <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">fact_check</span> 
                <?= htmlspecialchars($template['title']) ?>
            </h3>
            <span style="color: var(--text-muted); font-size: 0.9em;">Zařízení: <strong style="color: var(--text-main);"><?= htmlspecialchars($asset['name']) ?></strong></span>
        </div>
        <a href="index.php?page=scan&hash=<?= urlencode($asset['qr_hash']) ?>" class="btn" style="background: var(--text-muted); text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
        </a>
    </div>

    <?php if (empty($schema)): ?>
        <div style="padding: 25px; background: rgba(243, 156, 18, 0.15); color: var(--warning); border-radius: 6px; border: 1px solid var(--warning); text-align: center;">
            <strong>Tato šablona zatím neobsahuje žádné kontrolní otázky.</strong>
        </div>
    <?php else: ?>
        
        <!-- PŘIDÁNY DATA ATRIBUTY data-asset-id A data-template-id PRO JS AUTOSAVE -->
        <form method="POST" action="index.php?page=inspection_save" id="inspectionForm" data-asset-id="<?= $asset['id'] ?>" data-template-id="<?= $template['id'] ?>" onsubmit="return validateInspectionForm();">
            
            <!-- OCHRANA CSRF -->
            <?= Security::csrfField() ?>

            <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
            <input type="hidden" name="form_template_id" value="<?= $template['id'] ?>">
            <input type="hidden" name="duration_seconds" id="durationSeconds" value="0">

            <div style="background: rgba(41, 128, 185, 0.1); padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; color: var(--info); display: flex; align-items: center; justify-content: space-between;">
                <span><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">timer</span> Doba vyplňování</span>
                <strong id="timerDisplay">00:00</strong>
            </div>

            <?php foreach ($schema as $index => $field): ?>
                <?php 
                    $fieldName = $field['label'] ?? ('Položka #' . ($index + 1));
                    if (strpos($fieldName, 'Podpist') !== false) { $fieldName = str_replace('Podpist', 'Podpis', $fieldName); }
                    
                    $fieldId = $field['id'] ?? $fieldName; 
                    
                    $fieldType = $field['type'] ?? 'text';
                    $isRequired = !empty($field['required']);
                    $requiredAttr = $isRequired ? 'required' : '';
                    $isStatusField = ($fieldType === 'asset_status');
                    $blockClass = $isStatusField ? 'form-field-block' : 'form-field-block hidable-block';
                ?>
                <div class="<?= $blockClass ?>">
                    <label class="field-title">
                        <?= htmlspecialchars($fieldName) ?>
                        <?php if ($isRequired): ?><span style="color: var(--danger); margin-left: 3px;">*</span><?php endif; ?>
                    </label>

                    <?php if ($isStatusField): ?>
                        <div style="display: flex; gap: 15px;">
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid var(--info); border-radius: 6px; cursor: pointer; background: rgba(41, 128, 185, 0.1); font-weight: bold; color: var(--info); margin: 0;">
                                <input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="V provozu" checked onchange="toggleFormFields(this.value)" style="margin-right: 5px; transform: scale(1.2);"> V provozu
                            </label>
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid var(--text-muted); border-radius: 6px; cursor: pointer; background: var(--background); font-weight: bold; color: var(--text-muted); margin: 0;">
                                <input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="Odstaveno" onchange="toggleFormFields(this.value)" style="margin-right: 5px; transform: scale(1.2);"> Odstaveno
                            </label>
                        </div>
                    <?php elseif ($fieldType === 'radio_ok_ko' || $fieldType === 'status_ok_ko'): ?>
                        <div style="display: flex; gap: 15px;">
                            <label class="radio-btn-ok"><input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="OK" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> OK (V pořádku)</label>
                            <label class="radio-btn-ko"><input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="KO" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> KO (Závada)</label>
                        </div>
                    <?php elseif ($fieldType === 'radio_yes_no'): ?>
                        <div style="display: flex; gap: 15px;">
                            <label class="radio-btn-neutral"><input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="Ano" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ano</label>
                            <label class="radio-btn-neutral"><input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="Ne" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ne</label>
                        </div>
                    <?php elseif ($fieldType === 'number' || $fieldType === 'numeric_limit' || $fieldType === 'meter_reading'): ?>
                        <?php 
                            $minAttr = isset($field['min']) && $field['min'] !== null ? 'min="' . htmlspecialchars($field['min']) . '"' : '';
                            $maxAttr = isset($field['max']) && $field['max'] !== null ? 'max="' . htmlspecialchars($field['max']) . '"' : '';
                            $unitStr = !empty($field['unit']) ? ' (' . htmlspecialchars($field['unit']) . ')' : '';
                        ?>
                        <input type="number" step="any" <?= $minAttr ?> <?= $maxAttr ?> name="data[<?= htmlspecialchars($fieldId) ?>]" <?= $requiredAttr ?> placeholder="Zadejte hodnotu<?= $unitStr ?>...">
                    <?php elseif ($fieldType === 'textarea'): ?>
                        <textarea name="data[<?= htmlspecialchars($fieldId) ?>]" <?= $requiredAttr ?> placeholder="Textová poznámka..." style="min-height: 100px; resize: vertical;"></textarea>

                    <?php elseif ($fieldType === 'photo'): ?>
                        <div style="background: var(--card-bg); border: 1px dashed var(--border-color); border-radius: 4px; padding: 10px; text-align: center;">
                            <div id="preview_<?= $index ?>" style="display: none; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 15px;"></div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <button type="button" class="btn btn-warning" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-field="<?= htmlspecialchars($fieldId) ?>" onclick="addPhotoInput(<?= $index ?>, this.dataset.field, 'camera')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_camera</span> Vyfotit
                                </button>
                                <button type="button" class="btn btn-info" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-field="<?= htmlspecialchars($fieldId) ?>" onclick="addPhotoInput(<?= $index ?>, this.dataset.field, 'gallery')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_library</span> Z galerie
                                </button>
                            </div>
                            <div id="loading_<?= $index ?>" style="display: none; color: var(--warning); font-size: 0.85em; margin-bottom: 10px;"><span class="material-symbols-outlined" style="vertical-align: middle; animation: spin 1.5s linear infinite;">sync</span> Zpracovávám (HEIC)...</div>
                            <div id="inputs_<?= $index ?>" style="display: none;"></div>
                            <input type="hidden" id="photoReq_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                        </div>

                    <?php elseif ($fieldType === 'signature'): ?>
                        <div style="border: 1px dashed var(--border-color); background: var(--card-bg); border-radius: 4px; padding: 10px; text-align: center;">
                            <canvas id="sigCanvas_<?= $index ?>" width="400" height="150" style="background: #fff; border: 1px solid var(--border-color); touch-action: none; max-width: 100%; border-radius: 4px;"></canvas>
                            <input type="hidden" name="data[<?= htmlspecialchars($fieldId) ?>]" id="sigInput_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                            <br><button type="button" class="btn" onclick="clearInspectionSignature(<?= $index ?>)" style="margin-top: 8px; padding: 6px 12px; background: var(--background); color: var(--text-main); font-weight: bold;">Vymazat podpis</button>
                        </div>
                    <?php else: ?>
                        <input type="text" name="data[<?= htmlspecialchars($fieldId) ?>]" <?= $requiredAttr ?> placeholder="Zadejte text...">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success" style="width: 100%; padding: 16px; font-size: 1.1em; border-radius: 6px; box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2); margin-top: 20px;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Odeslat a uložit
            </button>
        </form>
    <?php endif; ?>
</div>
