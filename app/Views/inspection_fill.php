<?php 
if (!defined('APP_ROOT')) exit; 

$schema = json_decode($template['schema_json'] ?? '[]', true);
if (!is_array($schema)) { $schema = []; }
?>

<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<style>
    .form-field-block { margin-bottom: 20px; padding: 15px; background: #f4f6f8; border: 1px solid #dce1e6; border-radius: 8px; transition: all 0.3s ease; }
    .form-field-block:focus-within { background: #eaf4f9; border-color: #3498db; box-shadow: 0 0 8px rgba(52, 152, 219, 0.15); }
    .form-field-block label.field-title { display: block; font-weight: bold; margin-bottom: 10px; color: #2c3e50; font-size: 1.05em; }
    .form-field-block input[type="text"], .form-field-block input[type="number"], .form-field-block textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; background: #fff; font-family: inherit; }
    .form-field-block input:focus, .form-field-block textarea:focus { outline: none; border-color: #3498db; }
    .radio-btn-neutral, .radio-btn-ok, .radio-btn-ko { flex: 1; text-align: center; padding: 12px; border: 2px solid #ccc; border-radius: 6px; cursor: pointer; background: #fff; font-weight: bold; color: #555; margin: 0; transition: all 0.2s ease; }
    .radio-btn-neutral:hover { background: #f4f6f8; } .radio-btn-neutral:has(input:checked) { border-color: #3498db; background: #ebf5fb; color: #3498db; }
    .radio-btn-ok:hover { background: #eafaf1; } .radio-btn-ok:has(input:checked) { border-color: #27ae60; background: #eafaf1; color: #27ae60; }
    .radio-btn-ko:hover { background: #fdeeed; } .radio-btn-ko:has(input:checked) { border-color: #e74c3c; background: #fdeeed; color: #e74c3c; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<div class="card card-primary-top" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0; color: #2c3e50;">
                <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">fact_check</span> 
                <?= htmlspecialchars($template['title']) ?>
            </h3>
            <span style="color: #777; font-size: 0.9em;">Zařízení: <strong><?= htmlspecialchars($asset['name']) ?></strong></span>
        </div>
        <a href="index.php?page=scan&hash=<?= urlencode($asset['qr_hash']) ?>" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
        </a>
    </div>

    <?php if (empty($schema)): ?>
        <div style="padding: 25px; background: #fff3cd; color: #856404; border-radius: 6px; border: 1px solid #ffeeba; text-align: center;">
            <strong>Tato šablona zatím neobsahuje žádné kontrolní otázky.</strong>
        </div>
    <?php else: ?>
        <form method="POST" action="index.php?page=inspection_save" id="inspectionForm" onsubmit="return validateInspectionForm();">
            <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
            <input type="hidden" name="form_template_id" value="<?= $template['id'] ?>">
            <input type="hidden" name="duration_seconds" id="durationSeconds" value="0">

            <div style="background: #e8f4f8; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; color: #2980b9; display: flex; align-items: center; justify-content: space-between;">
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
                        <?php if ($isRequired): ?><span style="color: #e74c3c; margin-left: 3px;">*</span><?php endif; ?>
                    </label>

                    <?php if ($isStatusField): ?>
                        <div style="display: flex; gap: 15px;">
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid #2980b9; border-radius: 6px; cursor: pointer; background: #ebf5fb; font-weight: bold; color: #2980b9; margin: 0;">
                                <input type="radio" name="data[<?= htmlspecialchars($fieldId) ?>]" value="V provozu" checked onchange="toggleFormFields(this.value)" style="margin-right: 5px; transform: scale(1.2);"> V provozu
                            </label>
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid #7f8c8d; border-radius: 6px; cursor: pointer; background: #f4f6f7; font-weight: bold; color: #7f8c8d; margin: 0;">
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
                        <div style="background: #fff; border: 1px dashed #999; border-radius: 4px; padding: 10px; text-align: center;">
                            <div id="preview_<?= $index ?>" style="display: none; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 15px;"></div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <button type="button" class="btn" style="flex: 1; background: #e67e22; color: #fff; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-field="<?= htmlspecialchars($fieldId) ?>" onclick="addPhotoInput(<?= $index ?>, this.dataset.field, 'camera')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_camera</span> Vyfotit
                                </button>
                                <button type="button" class="btn" style="flex: 1; background: #3498db; color: #fff; padding: 12px 10px; font-weight: bold; border-radius: 6px;" data-field="<?= htmlspecialchars($fieldId) ?>" onclick="addPhotoInput(<?= $index ?>, this.dataset.field, 'gallery')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_library</span> Z galerie
                                </button>
                            </div>
                            <div id="loading_<?= $index ?>" style="display: none; color: #e67e22; font-size: 0.85em; margin-bottom: 10px;"><span class="material-symbols-outlined" style="vertical-align: middle; animation: spin 1.5s linear infinite;">sync</span> Zpracovávám (HEIC)...</div>
                            <div id="inputs_<?= $index ?>" style="display: none;"></div>
                            <input type="hidden" id="photoReq_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                        </div>

                    <?php elseif ($fieldType === 'signature'): ?>
                        <div style="border: 1px dashed #999; background: #fff; border-radius: 4px; padding: 10px; text-align: center;">
                            <canvas id="sigCanvas_<?= $index ?>" width="400" height="150" style="background: #fff; border: 1px solid #ccc; touch-action: none; max-width: 100%; border-radius: 4px;"></canvas>
                            <input type="hidden" name="data[<?= htmlspecialchars($fieldId) ?>]" id="sigInput_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                            <br><button type="button" onclick="clearSignature(<?= $index ?>)" style="margin-top: 8px; padding: 6px 12px; background: #e0e0e0; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Vymazat podpis</button>
                        </div>
                    <?php else: ?>
                        <input type="text" name="data[<?= htmlspecialchars($fieldId) ?>]" <?= $requiredAttr ?> placeholder="Zadejte text...">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1em; background: #27ae60; border-radius: 6px; box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2); margin-top: 20px;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Odeslat a uložit
            </button>
        </form>

        <script>
            // --- AUTOSAVE FUNKCE ---
            const autoSaveKey = 'cmms_autosave_<?= htmlspecialchars($asset['id']) ?>_<?= htmlspecialchars($template['id']) ?>';

            function saveFormData() {
                const formData = {};
                document.querySelectorAll('#inspectionForm input[type="text"], #inspectionForm input[type="number"], #inspectionForm textarea').forEach(input => {
                    if (input.name) formData[input.name] = input.value;
                });
                document.querySelectorAll('#inspectionForm input[type="radio"]:checked').forEach(radio => {
                    if (radio.name) formData[radio.name] = radio.value;
                });
                localStorage.setItem(autoSaveKey, JSON.stringify(formData));
            }

            function restoreFormData() {
                const saved = localStorage.getItem(autoSaveKey);
                if (saved) {
                    try {
                        const formData = JSON.parse(saved);
                        let restoredCount = 0;
                        
                        document.querySelectorAll('#inspectionForm input[type="text"], #inspectionForm input[type="number"], #inspectionForm textarea').forEach(input => {
                            if (input.name && formData[input.name] !== undefined) {
                                input.value = formData[input.name];
                                restoredCount++;
                            }
                        });
                        
                        document.querySelectorAll('#inspectionForm input[type="radio"]').forEach(radio => {
                            if (radio.name && formData[radio.name] === radio.value) {
                                radio.checked = true;
                                restoredCount++;
                                if (radio.hasAttribute('onchange')) {
                                    radio.dispatchEvent(new Event('change'));
                                }
                            }
                        });

                        if (restoredCount > 0) {
                            const banner = document.createElement('div');
                            banner.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle;">settings_backup_restore</span> Rozepsaná data byla automaticky obnovena.';
                            banner.style.cssText = 'background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; border: 1px solid #c3e6cb; font-weight: bold; transition: opacity 0.5s;';
                            document.getElementById('inspectionForm').insertBefore(banner, document.getElementById('inspectionForm').firstChild);
                            setTimeout(() => banner.style.opacity = '0', 4500);
                            setTimeout(() => banner.style.display = 'none', 5000);
                        }
                    } catch (e) {}
                }
            }

            window.addEventListener('DOMContentLoaded', () => {
                if(document.getElementById('inspectionForm')) {
                    restoreFormData();
                    document.getElementById('inspectionForm').addEventListener('input', saveFormData);
                    document.getElementById('inspectionForm').addEventListener('change', saveFormData);
                }
            });
            // -----------------------

            function addPhotoInput(index, fieldId, source) {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                if (source === 'camera') { fileInput.accept = 'image/*'; fileInput.capture = 'environment'; } 
                else { fileInput.accept = 'image/*, .heic, .heif'; fileInput.multiple = true; }
                
                fileInput.onchange = async function() {
                    if (this.files && this.files.length > 0) {
                        const previewContainer = document.getElementById('preview_' + index);
                        const inputsContainer = document.getElementById('inputs_' + index);
                        const loadingIndicator = document.getElementById('loading_' + index);
                        previewContainer.style.display = 'flex';
                        
                        for(let i=0; i < this.files.length; i++) {
                            let file = this.files[i];
                            if (file.name.toLowerCase().endsWith('.heic') || file.type === 'image/heic') {
                                if (loadingIndicator) loadingIndicator.style.display = 'block';
                                try {
                                    const convertedBlob = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.8 });
                                    file = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob; 
                                } catch (err) { alert("Fotografii HEIC se nepodařilo zpracovat."); continue; } 
                                finally { if (loadingIndicator) loadingIndicator.style.display = 'none'; }
                            }
                            
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const img = new Image();
                                img.onload = function() {
                                    const canvas = document.createElement('canvas');
                                    const ctx = canvas.getContext('2d');
                                    let width = img.width, height = img.height; const MAX_DIM = 1200;
                                    if (width > height) { if (width > MAX_DIM) { height *= MAX_DIM / width; width = MAX_DIM; } } 
                                    else { if (height > MAX_DIM) { width *= MAX_DIM / height; height = MAX_DIM; } }
                                    canvas.width = width; canvas.height = height; ctx.drawImage(img, 0, 0, width, height);
                                    
                                    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                                    const previewImg = document.createElement('img');
                                    previewImg.src = dataUrl; previewImg.style.height = '100px'; previewImg.style.borderRadius = '4px'; previewImg.style.border = '1px solid #ccc';
                                    previewContainer.appendChild(previewImg);
                                    
                                    const hiddenInp = document.createElement('input');
                                    hiddenInp.type = 'hidden'; hiddenInp.name = 'photos_base64[' + fieldId + '][]'; hiddenInp.value = dataUrl;
                                    inputsContainer.appendChild(hiddenInp);
                                };
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                };
                fileInput.click(); 
            }

            function validateInspectionForm() {
                const sigInputs = document.querySelectorAll('input[id^="sigInput_"]');
                for (let i = 0; i < sigInputs.length; i++) {
                    if (sigInputs[i].dataset.isRequired === 'true' && sigInputs[i].value.trim() === '') {
                        alert("Nebylo vyplněno povinné pole: " + sigInputs[i].dataset.label); return false;
                    }
                }
                const photoReqs = document.querySelectorAll('input[id^="photoReq_"]');
                for (let i = 0; i < photoReqs.length; i++) {
                    if (photoReqs[i].dataset.isRequired === 'true') {
                        const idx = photoReqs[i].id.split('_')[1];
                        if (document.getElementById('inputs_' + idx).querySelectorAll('input[type="hidden"]').length === 0) {
                            alert("Chybí fotodokumentace v poli: " + photoReqs[i].dataset.label); return false;
                        }
                    }
                }
                
                // Vyčištění paměti po úspěšném odeslání
                localStorage.removeItem(autoSaveKey);
                return true; 
            }

            function toggleFormFields(status) {
                document.querySelectorAll('.hidable-block').forEach(block => {
                    if (status === 'Odstaveno') {
                        block.style.display = 'none'; 
                        block.querySelectorAll('input:not([type="hidden"]), textarea').forEach(inp => { inp.dataset.wasRequired = inp.required; inp.required = false; });
                        block.querySelectorAll('input[id^="sigInput_"], input[id^="photoReq_"]').forEach(inp => { inp.dataset.wasReq = inp.dataset.isRequired; inp.dataset.isRequired = 'false'; });
                    } else {
                        block.style.display = 'block'; 
                        block.querySelectorAll('input:not([type="hidden"]), textarea').forEach(inp => { if (inp.dataset.wasRequired === 'true') inp.required = true; });
                        block.querySelectorAll('input[id^="sigInput_"], input[id^="photoReq_"]').forEach(inp => { if (inp.dataset.wasReq === 'true') inp.dataset.isRequired = 'true'; });
                    }
                });
            }

            let secondsCount = 0;
            setInterval(() => {
                secondsCount++; document.getElementById('durationSeconds').value = secondsCount;
                document.getElementById('timerDisplay').textContent = String(Math.floor(secondsCount / 60)).padStart(2, '0') + ':' + String(secondsCount % 60).padStart(2, '0');
            }, 1000);

            document.querySelectorAll('canvas[id^="sigCanvas_"]').forEach(canvas => {
                const ctx = canvas.getContext('2d'); ctx.strokeStyle = '#0000cd'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
                let drawing = false;
                const getPos = (e) => { const rect = canvas.getBoundingClientRect(); return { x: ((e.touches ? e.touches[0].clientX : e.clientX) - rect.left) * (canvas.width / rect.width), y: ((e.touches ? e.touches[0].clientY : e.clientY) - rect.top) * (canvas.height / rect.height) }; };
                const start = (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); };
                const move = (e) => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
                const stop = () => { if (drawing) { drawing = false; document.getElementById('sigInput_' + canvas.id.split('_')[1]).value = canvas.toDataURL(); } };
                canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
                canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);
            });
            function clearSignature(idx) { const c = document.getElementById('sigCanvas_' + idx); c.getContext('2d').clearRect(0, 0, c.width, c.height); document.getElementById('sigInput_' + idx).value = ''; }
        </script>
    <?php endif; ?>
</div>
