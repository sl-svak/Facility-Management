(function () {

    // Data schématu předává PHP v bloku <script type="application/json"> (nevykonává se, CSP ho neblokuje)
    let schema = JSON.parse(document.getElementById('schema-data').textContent);

    document.addEventListener("DOMContentLoaded", function() {
        schema.forEach(field => { if (!field.id) field.id = field.label; });
        renderPreview();
    });

    function generateId() { return 'f_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5); }

    function addField(type) {
        let promptMsg = "Zadejte název pole:";
        let defaultLabel = (type === 'asset_status') ? "Provozní stav zařízení" : ((type === 'photo') ? "Fotodokumentace" : "");

        let label = prompt(promptMsg, defaultLabel);
        if (!label) return;

        let isRequired = (type === 'asset_status') ? true : confirm("Má být toto pole POVINNÉ k vyplnění?\n\n[OK] = Ano\n[Zrušit] = Ne");
        let min = null, max = null, unit = '';

        if (type === 'numeric_limit') {
            let minStr = prompt("Zadejte MINIMÁLNÍ povolenou hodnotu (nebo nechte prázdné):", "0");
            let maxStr = prompt("Zadejte MAXIMÁLNÍ povolenou hodnotu:", "14");
            unit = prompt("Zadejte jednotku (např. mg/l, °C, bar):", "");
            min = minStr !== "" ? parseFloat(minStr) : null;
            max = maxStr !== "" ? parseFloat(maxStr) : null;
        } else if (type === 'meter_reading') {
            unit = prompt("Zadejte jednotku měřidla:", "m3");
        }

        schema.push({ id: generateId(), type: type, label: label, required: isRequired, min: isNaN(min) ? null : min, max: isNaN(max) ? null : max, unit: unit });
        renderPreview();
    }

    function editField(index) {
        let field = schema[index];
        let label = prompt("Upravte název pole:", field.label);
        if (label === null) return; 
        field.label = label; 

        if (field.type !== 'asset_status') { 
            field.required = confirm("Má být toto pole POVINNÉ k vyplnění?\n\nAktuální stav: " + (field.required ? "Ano" : "Ne"));
        }

        if (field.type === 'numeric_limit') {
            let minStr = prompt("Upravte MINIMÁLNÍ povolenou hodnotu:", field.min !== null ? field.min : "");
            let maxStr = prompt("Upravte MAXIMÁLNÍ povolenou hodnotu:", field.max !== null ? field.max : "");
            field.unit = prompt("Upravte jednotku:", field.unit || "");
            field.min = minStr !== "" ? parseFloat(minStr) : null;
            field.max = maxStr !== "" ? parseFloat(maxStr) : null;
        } else if (field.type === 'meter_reading') {
            field.unit = prompt("Upravte jednotku měřidla:", field.unit || "");
        }
        renderPreview();
    }

    function removeField(index) { schema.splice(index, 1); renderPreview(); }

    function moveField(index, direction) {
        if (direction === 'up' && index > 0) {
            let temp = schema[index - 1]; schema[index - 1] = schema[index]; schema[index] = temp;
        } else if (direction === 'down' && index < schema.length - 1) {
            let temp = schema[index + 1]; schema[index + 1] = schema[index]; schema[index] = temp;
        }
        renderPreview();
    }

    function renderPreview() {
        const previewArea = document.getElementById('preview-area');
        const hiddenInput = document.getElementById('schema_json');
        
        if (schema.length === 0) {
            previewArea.innerHTML = '<em style="color: var(--text-muted);">Zatím nejsou přidána žádná pole.</em>';
            hiddenInput.value = '[]';
            return;
        }

        let html = '';
        // Vnitřní escapování HTML ve vykresleném náhledu
        const esc = (v) => String(v === null || v === undefined ? '' : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
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
                extraInfo = ` <span style="color: var(--warning); font-size:0.85em;">[Min: ${field.min !== null ? esc(field.min) : 'neomezeno'}, Max: ${field.max !== null ? esc(field.max) : 'neomezeno'} ${esc(field.unit)}]</span>`;
            } else if (field.type === 'meter_reading') {
                extraInfo = ` <span style="color: var(--info); font-size:0.85em;">[Odečet: ${esc(field.unit)}]</span>`;
            }

            let requiredStar = field.required ? '<span style="color: var(--danger); font-weight: bold; margin-left: 4px;">*</span>' : '';

            let safeLabel = esc(field.label);

            html += `
                <div style="padding: 10px; border: 1px solid var(--border-color); margin-bottom: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; background: var(--card-bg);">
                    <div style="color: var(--text-main);">
                        <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--text-muted); font-size: 1.2em;">${icon}</span>
                        <strong>${safeLabel}</strong>${requiredStar}${extraInfo}
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <button type="button" data-action="moveField" data-args='[${index},"up"]' style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><span class="material-symbols-outlined">arrow_upward</span></button>
                        <button type="button" data-action="moveField" data-args='[${index},"down"]' style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><span class="material-symbols-outlined">arrow_downward</span></button>
                        <button type="button" data-action="editField" data-args='[${index}]' style="background: none; border: none; color: var(--info); cursor: pointer; margin-left: 5px;"><span class="material-symbols-outlined">edit</span></button>
                        <button type="button" data-action="removeField" data-args='[${index}]' style="background: none; border: none; color: var(--danger); cursor: pointer; margin-left: 5px;"><span class="material-symbols-outlined">close</span></button>
                    </div>
                </div>
            `;
        });
        
        previewArea.innerHTML = html;
        hiddenInput.value = JSON.stringify(schema);
    }

    function validateForm() {
        if (schema.length === 0) { alert('Formulář musí obsahovat alespoň jedno pole!'); return false; }
        return true;
    }

    window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};
    Object.assign(window.CMMS_ACTIONS, { addField: addField, editField: editField, removeField: removeField, moveField: moveField, validateForm: validateForm });
})();
