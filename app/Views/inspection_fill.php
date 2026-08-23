<?php 
if (!defined('APP_ROOT')) exit; 

$schema = json_decode($template['schema_json'] ?? '[]', true);
if (!is_array($schema)) {
    $schema = [];
}
?>

<style>
    .form-field-block { margin-bottom: 20px; padding: 15px; background: #f4f6f8; border: 1px solid #dce1e6; border-radius: 8px; transition: all 0.3s ease; }
    .form-field-block:focus-within { background: #eaf4f9; border-color: #3498db; box-shadow: 0 0 8px rgba(52, 152, 219, 0.15); }
    .form-field-block label.field-title { display: block; font-weight: bold; margin-bottom: 10px; color: #2c3e50; font-size: 1.05em; }
    .form-field-block input[type="text"], .form-field-block input[type="number"], .form-field-block textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; background: #fff; font-family: inherit; }
    .form-field-block input:focus, .form-field-block textarea:focus { outline: none; border-color: #3498db; }
    .radio-btn-neutral { flex: 1; text-align: center; padding: 12px; border: 2px solid #ccc; border-radius: 6px; cursor: pointer; background: #fff; font-weight: bold; color: #555; margin: 0; transition: all 0.2s ease; }
    .radio-btn-neutral:hover { background: #f4f6f8; }
    .radio-btn-neutral:has(input:checked) { border-color: #3498db; background: #ebf5fb; color: #3498db; }
    .radio-btn-ok { flex: 1; text-align: center; padding: 12px; border: 2px solid #ccc; border-radius: 6px; cursor: pointer; background: #fff; font-weight: bold; color: #555; margin: 0; transition: all 0.2s ease; }
    .radio-btn-ok:hover { background: #eafaf1; }
    .radio-btn-ok:has(input:checked) { border-color: #27ae60; background: #eafaf1; color: #27ae60; }
    .radio-btn-ko { flex: 1; text-align: center; padding: 12px; border: 2px solid #ccc; border-radius: 6px; cursor: pointer; background: #fff; font-weight: bold; color: #555; margin: 0; transition: all 0.2s ease; }
    .radio-btn-ko:hover { background: #fdeeed; }
    .radio-btn-ko:has(input:checked) { border-color: #e74c3c; background: #fdeeed; color: #e74c3c; }
</style>

<div class="card card-primary-top" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0; color: #2c3e50;">
                <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">fact_check</span> 
                <?= htmlspecialchars($template['title']) ?>
            </h3>
            <span style="color: #777; font-size: 0.9em;">
                Zařízení: <strong><?= htmlspecialchars($asset['name']) ?></strong>
            </span>
        </div>
        <a href="index.php?page=scan&hash=<?= urlencode($asset['qr_hash']) ?>" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
        </a>
    </div>

    <?php if (empty($schema)): ?>
        <div style="padding: 25px; background: #fff3cd; color: #856404; border-radius: 6px; border: 1px solid #ffeeba; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 3em; margin-bottom: 10px;">format_list_bulleted</span><br>
            <strong>Tato šablona zatím neobsahuje žádné kontrolní otázky.</strong>
        </div>
    <?php else: ?>
        <form method="POST" action="index.php?page=inspection_save" id="inspectionForm" onsubmit="return validateInspectionForm();">
            <input type="hidden" name="asset_id" value="<?= $asset['id'] ?>">
            <input type="hidden" name="form_template_id" value="<?= $template['id'] ?>">
            <input type="hidden" name="duration_seconds" id="durationSeconds" value="0">

            <div style="background: #e8f4f8; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.85em; color: #2980b9; display: flex; align-items: center; justify-content: space-between;">
                <span><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">timer</span> Doba vyplňování formuláře</span>
                <strong id="timerDisplay">00:00</strong>
            </div>

            <?php foreach ($schema as $index => $field): ?>
                <?php 
                    $fieldName = $field['label'] ?? ('Položka #' . ($index + 1));
                    $fieldType = $field['type'] ?? 'text';
                    $isRequired = !empty($field['required']);
                    $requiredAttr = $isRequired ? 'required' : '';
                    $isStatusField = ($fieldType === 'asset_status');
                    $blockClass = $isStatusField ? 'form-field-block' : 'form-field-block hidable-block';
                ?>
                <div class="<?= $blockClass ?>">
                    <label class="field-title">
                        <?= htmlspecialchars($fieldName) ?>
                        <?php if ($isRequired): ?><span style="color: #e74c3c; margin-left: 3px;" title="Povinné pole">*</span><?php endif; ?>
                    </label>

                    <?php if ($isStatusField): ?>
                        <div style="display: flex; gap: 15px;">
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid #2980b9; border-radius: 6px; cursor: pointer; background: #ebf5fb; font-weight: bold; color: #2980b9; margin: 0;">
                                <input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="V provozu" checked onchange="toggleFormFields(this.value)" style="margin-right: 5px; transform: scale(1.2);"> V provozu
                            </label>
                            <label style="flex: 1; text-align: center; padding: 12px; border: 2px solid #7f8c8d; border-radius: 6px; cursor: pointer; background: #f4f6f7; font-weight: bold; color: #7f8c8d; margin: 0;">
                                <input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="Odstaveno" onchange="toggleFormFields(this.value)" style="margin-right: 5px; transform: scale(1.2);"> Odstaveno
                            </label>
                        </div>
                    <?php elseif ($fieldType === 'radio_ok_ko' || $fieldType === 'status_ok_ko'): ?>
                        <div style="display: flex; gap: 15px;">
                            <label class="radio-btn-ok"><input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="OK" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> OK (V pořádku)</label>
                            <label class="radio-btn-ko"><input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="KO" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> KO (Závada)</label>
                        </div>
                    <?php elseif ($fieldType === 'radio_yes_no'): ?>
                        <div style="display: flex; gap: 15px;">
                            <label class="radio-btn-neutral"><input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="Ano" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ano</label>
                            <label class="radio-btn-neutral"><input type="radio" name="data[<?= htmlspecialchars($fieldName) ?>]" value="Ne" <?= $requiredAttr ?> style="margin-right: 5px; transform: scale(1.2);"> Ne</label>
                        </div>
                    <?php elseif ($fieldType === 'number' || $fieldType === 'numeric_limit' || $fieldType === 'meter_reading'): ?>
                        <?php 
                            $minAttr = isset($field['min']) && $field['min'] !== null ? 'min="' . htmlspecialchars($field['min']) . '"' : '';
                            $maxAttr = isset($field['max']) && $field['max'] !== null ? 'max="' . htmlspecialchars($field['max']) . '"' : '';
                            $unitStr = !empty($field['unit']) ? ' (' . htmlspecialchars($field['unit']) . ')' : '';
                        ?>
                        <input type="number" step="any" <?= $minAttr ?> <?= $maxAttr ?> name="data[<?= htmlspecialchars($fieldName) ?>]" <?= $requiredAttr ?> placeholder="Zadejte číselnou hodnotu<?= $unitStr ?>...">
                    <?php elseif ($fieldType === 'textarea'): ?>
                        <textarea name="data[<?= htmlspecialchars($fieldName) ?>]" <?= $requiredAttr ?> placeholder="Zadejte textovou poznámku..." style="min-height: 100px; resize: vertical;"></textarea>

                    <?php elseif ($fieldType === 'photo'): ?>
                        <!-- CHYTRÉ FOTOGRAFIE Z MOBILU S KOMPRESÍ NA STRANĚ KLIENTA -->
                        <div style="background: #fff; border: 1px dashed #999; border-radius: 4px; padding: 10px; text-align: center;">
                            <div id="preview_<?= $index ?>" style="display: none; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 15px;"></div>
                            
                            <button type="button" class="btn" style="background: #e67e22; color: #fff; padding: 12px 20px; font-weight: bold; width: 100%; border-radius: 6px; box-shadow: 0 2px 4px rgba(230,126,34,0.3);" data-field="<?= htmlspecialchars($fieldName) ?>" onclick="addPhotoInput(<?= $index ?>, this.dataset.field)">
                                <span class="material-symbols-outlined" style="vertical-align: middle;">add_a_photo</span> Přidat fotku
                            </button>
                            
                            <!-- Skryté pole pro uložení komprimovaných dat -->
                            <div id="inputs_<?= $index ?>" style="display: none;"></div>
                            
                            <!-- Skryté pole pro kontrolu povinnosti fotky -->
                            <input type="hidden" id="photoReq_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                        </div>

                    <?php elseif ($fieldType === 'signature'): ?>
                        <div style="border: 1px dashed #999; background: #fff; border-radius: 4px; padding: 10px; text-align: center;">
                            <canvas id="sigCanvas_<?= $index ?>" width="400" height="150" style="background: #fff; border: 1px solid #ccc; touch-action: none; max-width: 100%; border-radius: 4px;"></canvas>
                            <input type="hidden" name="data[<?= htmlspecialchars($fieldName) ?>]" id="sigInput_<?= $index ?>" data-is-required="<?= $isRequired ? 'true' : 'false' ?>" data-label="<?= htmlspecialchars($fieldName) ?>">
                            <br>
                            <button type="button" onclick="clearSignature(<?= $index ?>)" style="margin-top: 8px; padding: 6px 12px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold;">
                                <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">ink_eraser</span> Vymazat podpis
                            </button>
                        </div>
                    <?php else: ?>
                        <input type="text" name="data[<?= htmlspecialchars($fieldName) ?>]" <?= $requiredAttr ?> placeholder="Zadejte text...">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1em; background: #27ae60; border-radius: 6px; box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2); margin-top: 20px;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Odeslat a uložit záznam
            </button>
        </form>

        <script>
            // --- KOMPRESE FOTEK PŘÍMO V PROHLÍŽEČI MOBILU ---
            function addPhotoInput(index, fieldName) {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.multiple = true; 
                
                fileInput.onchange = function() {
                    if (this.files && this.files.length > 0) {
                        const previewContainer = document.getElementById('preview_' + index);
                        const inputsContainer = document.getElementById('inputs_' + index);
                        previewContainer.style.display = 'flex';
                        
                        for(let i=0; i < this.files.length; i++) {
                            const file = this.files[i];
                            const reader = new FileReader();
                            
                            reader.onload = function(e) {
                                const img = new Image();
                                img.onload = function() {
                                    // Vytvoření skrytého plátna pro kompresi
                                    const canvas = document.createElement('canvas');
                                    const ctx = canvas.getContext('2d');
                                    
                                    let width = img.width;
                                    let height = img.height;
                                    const MAX_DIM = 1200;

                                    if (width > height) {
                                        if (width > MAX_DIM) {
                                            height *= MAX_DIM / width;
                                            width = MAX_DIM;
                                        }
                                    } else {
                                        if (height > MAX_DIM) {
                                            width *= MAX_DIM / height;
                                            height = MAX_DIM;
                                        }
                                    }

                                    canvas.width = width;
                                    canvas.height = height;
                                    ctx.drawImage(img, 0, 0, width, height);
                                    
                                    // Převedení zmenšené fotky na text (Base64) s 80% kvalitou JPEG
                                    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                                    
                                    // 1. Zobrazení náhledu technikovi
                                    const previewImg = document.createElement('img');
                                    previewImg.src = dataUrl;
                                    previewImg.style.height = '100px';
                                    previewImg.style.borderRadius = '4px';
                                    previewImg.style.border = '1px solid #ccc';
                                    previewImg.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                                    previewContainer.appendChild(previewImg);
                                    
                                    // 2. Vytvoření skrytého pole pro odeslání na server
                                    const hiddenInp = document.createElement('input');
                                    hiddenInp.type = 'hidden';
                                    hiddenInp.name = 'photos_base64[' + fieldName + '][]';
                                    hiddenInp.value = dataUrl;
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
                // Ověření podpisů
                const sigInputs = document.querySelectorAll('input[id^="sigInput_"]');
                for (let i = 0; i < sigInputs.length; i++) {
                    const inp = sigInputs[i];
                    if (inp.dataset.isRequired === 'true' && inp.value.trim() === '') {
                        alert("CHYBA: Nebylo vyplněno povinné pole!\n\nProsím, připojte svůj podpis do pole: " + inp.dataset.label);
                        return false;
                    }
                }
                
                // Ověření fotek (hledá se přítomnost skrytých polí s daty)
                const photoReqs = document.querySelectorAll('input[id^="photoReq_"]');
                for (let i = 0; i < photoReqs.length; i++) {
                    const req = photoReqs[i];
                    if (req.dataset.isRequired === 'true') {
                        const idx = req.id.split('_')[1];
                        const hiddenInputs = document.getElementById('inputs_' + idx).querySelectorAll('input[type="hidden"]');
                        
                        if (hiddenInputs.length === 0) {
                            alert("CHYBA: Nebylo vyplněno povinné pole!\n\nProsím, přidejte fotodokumentaci do pole: " + req.dataset.label);
                            return false;
                        }
                    }
                }
                return true; 
            }

            function toggleFormFields(status) {
                const hidableBlocks = document.querySelectorAll('.hidable-block');
                hidableBlocks.forEach(block => {
                    if (status === 'Odstaveno') {
                        block.style.display = 'none'; 
                        const inputs = block.querySelectorAll('input:not([type="hidden"]), textarea');
                        inputs.forEach(inp => { inp.dataset.wasRequired = inp.required; inp.required = false; });
                        
                        const sigs = block.querySelectorAll('input[id^="sigInput_"]');
                        sigs.forEach(sig => { sig.dataset.wasSigRequired = sig.dataset.isRequired; sig.dataset.isRequired = 'false'; });
                        
                        const photoReqs = block.querySelectorAll('input[id^="photoReq_"]');
                        photoReqs.forEach(req => { req.dataset.wasPhotoReq = req.dataset.isRequired; req.dataset.isRequired = 'false'; });
                    } else {
                        block.style.display = 'block'; 
                        const inputs = block.querySelectorAll('input:not([type="hidden"]), textarea');
                        inputs.forEach(inp => { if (inp.dataset.wasRequired === 'true') inp.required = true; });
                        
                        const sigs = block.querySelectorAll('input[id^="sigInput_"]');
                        sigs.forEach(sig => { if (sig.dataset.wasSigRequired === 'true') sig.dataset.isRequired = 'true'; });
                        
                        const photoReqs = block.querySelectorAll('input[id^="photoReq_"]');
                        photoReqs.forEach(req => { if (req.dataset.wasPhotoReq === 'true') req.dataset.isRequired = 'true'; });
                    }
                });
            }

            let secondsCount = 0;
            const timerEl = document.getElementById('timerDisplay');
            const durationInput = document.getElementById('durationSeconds');
            setInterval(() => {
                secondsCount++;
                durationInput.value = secondsCount;
                const m = String(Math.floor(secondsCount / 60)).padStart(2, '0');
                const s = String(secondsCount % 60).padStart(2, '0');
                if (timerEl) timerEl.textContent = `${m}:${s}`;
            }, 1000);

            document.querySelectorAll('canvas[id^="sigCanvas_"]').forEach(canvas => {
                const ctx = canvas.getContext('2d');
                ctx.strokeStyle = '#0000cd'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
                let drawing = false;
                const getPos = (e) => {
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
                };
                const start = (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); };
                const move = (e) => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
                const stop = () => { 
                    if (drawing) { drawing = false; document.getElementById('sigInput_' + canvas.id.split('_')[1]).value = canvas.toDataURL(); }
                };
                canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
                canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);
            });
            function clearSignature(idx) {
                const canvas = document.getElementById('sigCanvas_' + idx);
                if (canvas) { canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); document.getElementById('sigInput_' + idx).value = ''; }
            }
        </script>
    <?php endif; ?>
</div>
