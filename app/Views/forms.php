<?php 
if (!defined('APP_ROOT')) exit; 

// Příprava dat podle toho, zda vytváříme nebo upravujeme
$isEdit = isset($editTemplate) && $editTemplate;
$formId = $isEdit ? $editTemplate['id'] : '';
$formTitle = $isEdit ? $editTemplate['title'] : '';
$formMinutes = $isEdit ? $editTemplate['estimated_minutes'] : 15;
$formSchema = $isEdit ? $editTemplate['schema_json'] : '[]';
?>

<div class="grid-2">
    
    <!-- Levý sloupec: Nástroj pro tvorbu formuláře -->
    <div class="card card-primary-top">
        <h3 style="margin-top:0;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">build</span> 
            <?= $isEdit ? 'Upravit šablonu' : 'Vytvořit novou šablonu' ?>
        </h3>
        
        <form method="POST" action="index.php?page=form_create" id="form-builder" onsubmit="return validateForm();">
            <input type="hidden" name="id" value="<?= $formId ?>">

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: var(--text-muted);">Název šablony (např. Kontrola ČOV) *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($formTitle) ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-top: 5px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: var(--text-muted);">Odhadovaný čas (v minutách)</label>
                <input type="number" name="estimated_minutes" value="<?= $formMinutes ?>" min="1" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-top: 5px;">
            </div>

            <input type="hidden" name="schema_json" id="schema_json" value="<?= htmlspecialchars($formSchema) ?>">

            <div style="margin-top: 20px; padding: 15px; background: var(--background); border-radius: 8px;">
                <strong>Struktura formuláře:</strong>
                <div id="preview-area" style="margin-top: 10px; min-height: 50px; background: #fff; padding: 15px; border: 1px dashed #ccc;">
                    <em style="color: #999;">Zatím nejsou přidána žádná pole. Použijte tlačítka níže.</em>
                </div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn" style="background: #2c3e50; color: #fff; font-weight: bold;" onclick="addField('asset_status')">
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em;">power_settings_new</span> Provozní stav
                </button>
                <button type="button" class="btn" style="background: #e67e22; color: #fff; font-weight: bold;" onclick="addField('photo')">
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em;">photo_camera</span> Fotografie
                </button>

                <button type="button" class="btn btn-success" onclick="addField('radio_ok_ko')">+ Tlačítka OK/KO</button>
                <button type="button" class="btn" style="background: #3498db; color: #fff;" onclick="addField('radio_yes_no')">+ Tlačítka Ano/Ne</button>
                <button type="button" class="btn btn-warning" onclick="addField('numeric_limit')">+ Číselné měření</button>
                <button type="button" class="btn btn-danger" onclick="addField('meter_reading')">+ Odečet měřidla</button>
                <button type="button" class="btn btn-info" onclick="addField('textarea')">+ Textová poznámka</button>
                <button type="button" class="btn" style="background: #8e44ad; color: #fff;" onclick="addField('signature')">+ Podpisové pole</button>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
            
            <button type="submit" class="btn-primary btn-block">
                <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> 
                <?= $isEdit ? 'Uložit změny' : 'Uložit šablonu formuláře' ?>
            </button>
            
            <?php if ($isEdit): ?>
                <a href="index.php?page=forms" class="btn" style="background: #95a5a6; width: 100%; text-align: center; display: block; margin-top: 10px; box-sizing: border-box;">Zrušit úpravy</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Pravý sloupec: Seznam uložených šablon -->
    <div class="card">
        <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">list_alt</span> Uložené šablony</h3>
        <?php if (empty($templates)): ?>
            <p style="color: var(--text-muted); font-style: italic;">Zatím nemáte vytvořené žádné šablony formulářů.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($templates as $tpl): ?>
                    <li style="padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; <?= ($isEdit && $tpl['id'] == $editTemplate['id']) ? 'background: #eaf2f8;' : '' ?>">
                        <div>
                            <strong style="color: var(--primary); font-size: 1.1em;"><?= htmlspecialchars($tpl['title']) ?></strong><br>
                            <span style="font-size: 0.85em; color: var(--text-muted); display: inline-flex; align-items: center; margin-top: 4px;">
                                <span class="material-symbols-outlined" style="font-size: 1.1em; margin-right: 4px;">schedule</span> 
                                Odhad: <?= $tpl['estimated_minutes'] ?> min 
                                
                                <?php if (!empty($tpl['avg_duration'])): ?>
                                    <?php 
                                        $avgMins = floor($tpl['avg_duration'] / 60);
                                        $avgSecs = round($tpl['avg_duration'] % 60);
                                    ?>
                                    <span style="color: var(--success); font-weight: bold; margin-left: 6px;" title="Skutečný průměrný čas techniků">
                                        (Praxe: <?= $avgMins ?> min <?= $avgSecs ?> s)
                                    </span>
                                <?php endif; ?>

                                <span style="margin: 0 6px;">|</span> Polí: <?= count(json_decode($tpl['schema_json'], true) ?? []) ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 15px;">
                            <a href="index.php?page=forms&edit_id=<?= $tpl['id'] ?>" style="color: var(--info); text-decoration: none;" title="Upravit">
                                <span class="material-symbols-outlined">edit</span>
                            </a>
                            <a href="index.php?page=form_delete&id=<?= $tpl['id'] ?>" onclick="return confirm('Opravdu smazat tuto šablonu? Záznamy již vyplněné podle této šablony zůstanou zachovány.');" style="color: var(--danger); text-decoration: none;" title="Smazat">
                                <span class="material-symbols-outlined">delete</span>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
    let schema = <?= $formSchema ?>;

    document.addEventListener("DOMContentLoaded", function() {
        renderPreview();
    });

    function addField(type) {
        let promptMsg = "Zadejte název pole (např. 'Hladina pH', 'Zapnutá filtrace'):";
        let defaultLabel = "";

        if (type === 'asset_status') {
            promptMsg = "Zadejte název pro sledování provozu:";
            defaultLabel = "Provozní stav zařízení";
        } else if (type === 'photo') {
            promptMsg = "Zadejte instrukci pro fotku (např. 'Vyfoťte celkový stav stroje'):";
            defaultLabel = "Fotodokumentace";
        }

        let label = prompt(promptMsg, defaultLabel);
        if (!label) return;

        let isRequired = false;
        if (type !== 'asset_status') { 
            isRequired = confirm("Má být toto pole POVINNÉ k vyplnění?\n\n[OK] = Ano, technik ho musí vyplnit\n[Zrušit] = Ne, pole bude volitelné");
        } else {
            isRequired = true;
        }

        let min = null;
        let max = null;
        let unit = '';

        if (type === 'numeric_limit') {
            let minStr = prompt("Zadejte MINIMÁLNÍ povolenou hodnotu (nebo nechcete-li hlídat, nechte prázdné):", "0");
            let maxStr = prompt("Zadejte MAXIMÁLNÍ povolenou hodnotu:", "14");
            unit = prompt("Zadejte jednotku (např. mg/l, °C, bar):", "");
            
            min = minStr !== "" ? parseFloat(minStr) : null;
            max = maxStr !== "" ? parseFloat(maxStr) : null;
        } else if (type === 'meter_reading') {
            unit = prompt("Zadejte jednotku měřidla (např. m3, kWh):", "m3");
        }

        schema.push({ 
            type: type, 
            label: label, 
            required: isRequired, 
            min: isNaN(min) ? null : min, 
            max: isNaN(max) ? null : max, 
            unit: unit 
        });
        renderPreview();
    }

    function removeField(index) {
        schema.splice(index, 1);
        renderPreview();
    }

    function moveField(index, direction) {
        if (direction === 'up' && index > 0) {
            let temp = schema[index - 1];
            schema[index - 1] = schema[index];
            schema[index] = temp;
        } else if (direction === 'down' && index < schema.length - 1) {
            let temp = schema[index + 1];
            schema[index + 1] = schema[index];
            schema[index] = temp;
        }
        renderPreview();
    }

    function renderPreview() {
        const previewArea = document.getElementById('preview-area');
        const hiddenInput = document.getElementById('schema_json');
        
        if (schema.length === 0) {
            previewArea.innerHTML = '<em style="color: #999;">Zatím nejsou přidána žádná pole. Použijte tlačítka níže.</em>';
            hiddenInput.value = '[]';
            return;
        }

        let html = '';
        schema.forEach((field, index) => {
            let icon = 'rule';
            if(field.type === 'textarea') icon = 'edit_note';
            if(field.type === 'signature') icon = 'draw';
            if(field.type === 'numeric_limit') icon = 'straighten';
            if(field.type === 'meter_reading') icon = 'speed';
            if(field.type === 'radio_yes_no') icon = 'toggle_on';
            if(field.type === 'asset_status') icon = 'power_settings_new';
            if(field.type === 'photo') icon = 'photo_camera';

            let extraInfo = '';
            if (field.type === 'numeric_limit') {
                extraInfo = ` <span style="color:#d35400; font-size:0.85em;">[Min: ${field.min !== null ? field.min : 'neomezeno'}, Max: ${field.max !== null ? field.max : 'neomezeno'} ${field.unit}]</span>`;
            } else if (field.type === 'meter_reading') {
                extraInfo = ` <span style="color:#2980b9; font-size:0.85em;">[Odečet měřidla: ${field.unit}]</span>`;
            } else if (field.type === 'asset_status') {
                extraInfo = ` <span style="color:#2c3e50; font-size:0.85em; font-weight:bold;">[Přepínač: V provozu / Odstaveno]</span>`;
            } else if (field.type === 'photo') {
                extraInfo = ` <span style="color:#e67e22; font-size:0.85em; font-weight:bold;">[Fotoaparát mobilu]</span>`;
            }

            let requiredStar = field.required ? '<span style="color: #e74c3c; font-weight: bold; margin-left: 4px;" title="Povinné pole">*</span>' : '';

            html += `
                <div style="padding: 10px; border: 1px solid var(--border-color); margin-bottom: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
                    <div>
                        <span class="material-symbols-outlined" style="vertical-align: middle; color: #888; font-size: 1.2em;">${icon}</span>
                        <strong>${field.label}</strong>${requiredStar}${extraInfo}
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <button type="button" onclick="moveField(${index}, 'up')" style="background: none; border: none; color: #888; cursor: pointer;" title="Posunout nahoru">
                            <span class="material-symbols-outlined">arrow_upward</span>
                        </button>
                        <button type="button" onclick="moveField(${index}, 'down')" style="background: none; border: none; color: #888; cursor: pointer;" title="Posunout dolů">
                            <span class="material-symbols-outlined">arrow_downward</span>
                        </button>
                        <button type="button" onclick="removeField(${index})" style="background: none; border: none; color: var(--danger); cursor: pointer; margin-left: 10px;" title="Odebrat">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
            `;
        });
        
        previewArea.innerHTML = html;
        hiddenInput.value = JSON.stringify(schema);
    }

    function validateForm() {
        if (schema.length === 0) {
            alert('Formulář musí obsahovat alespoň jedno pole!');
            return false;
        }
        return true;
    }
</script>
