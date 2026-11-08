/**
 * CMMS Cosmonde - Hlavni JavaScript
 * Zajistuje interaktivitu celeho systemu (podpisy, komprese fotek, modaly, ochranu formularu)
 */

// ==========================================
// OBSLUHA UDÁLOSTÍ BEZ INLINE ATRIBUTŮ (kvůli CSP bez 'unsafe-inline')
// Atributy v HTML: data-confirm, data-href, data-modal-open/close,
// data-action (+ data-args jako JSON pole), data-change, data-submit.
// Volat lze jen funkce zaregistrované v window.CMMS_ACTIONS.
// ==========================================
window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};

function cmmsParseArgs(el) {
    try {
        const parsed = JSON.parse(el.getAttribute('data-args') || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (err) { return []; }
}

document.addEventListener('click', function (e) {
    const confirmEl = e.target.closest('[data-confirm]');
    if (confirmEl && !window.confirm(confirmEl.getAttribute('data-confirm'))) {
        e.preventDefault();
        e.stopPropagation();
        return;
    }

    const openEl = e.target.closest('[data-modal-open]');
    if (openEl) {
        const m = document.getElementById(openEl.getAttribute('data-modal-open'));
        if (m) m.style.display = 'flex';
    }
    const closeEl = e.target.closest('[data-modal-close]');
    if (closeEl) {
        const m = document.getElementById(closeEl.getAttribute('data-modal-close'));
        if (m) m.style.display = 'none';
    }

    const hrefEl = e.target.closest('[data-href]');
    if (hrefEl) {
        window.location.href = hrefEl.getAttribute('data-href');
        return;
    }

    const actionEl = e.target.closest('[data-action]');
    if (actionEl) {
        const fn = Object.prototype.hasOwnProperty.call(window.CMMS_ACTIONS, actionEl.getAttribute('data-action'))
            ? window.CMMS_ACTIONS[actionEl.getAttribute('data-action')] : null;
        if (typeof fn === 'function') {
            const result = fn.apply(actionEl, cmmsParseArgs(actionEl));
            if (result === false || actionEl.tagName === 'A') e.preventDefault();
        }
    }
});

document.addEventListener('change', function (e) {
    const el = e.target.closest('[data-change]');
    if (!el) return;
    const name = el.getAttribute('data-change');
    if (Object.prototype.hasOwnProperty.call(window.CMMS_ACTIONS, name) && typeof window.CMMS_ACTIONS[name] === 'function') {
        window.CMMS_ACTIONS[name].call(el, el.value);
    }
});

// Capture fáze: validace proběhne dřív než ostatní posluchače odeslání formuláře
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || !form.getAttribute || !form.hasAttribute('data-submit')) return;
    const name = form.getAttribute('data-submit');
    if (Object.prototype.hasOwnProperty.call(window.CMMS_ACTIONS, name) && typeof window.CMMS_ACTIONS[name] === 'function') {
        if (window.CMMS_ACTIONS[name].call(form, e) === false) e.preventDefault();
    }
}, true);

// Service worker (offline záloha statických souborů)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js').catch(function () {});
    });
}

document.addEventListener('DOMContentLoaded', function() {

    // 1. OCHRANA PROTI DVOJITEMU ODESLANI
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                const originalText = btn.innerHTML;
                setTimeout(() => {
                    if (e.defaultPrevented) return; // validace formulář zastavila - tlačítko nezamykat
                    btn.disabled = true;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle;">autorenew</span> Ukládám...';
                    
                    if (this.action.includes('report') || this.action.includes('export')) {
                        setTimeout(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }, 3000);
                    }
                }, 10);
            }
        });
    });

    // 2. MOBILNI MENU
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // 3. RESENI TIKETU (Platno pro podpis v detailu)
    const sigCanvas = document.getElementById('signatureCanvas');
    if (sigCanvas) {
        initSignaturePad(sigCanvas, 'signatureInput');
    }

    // 4. KONTROLNI FORMULARE (Autosave, Timer, Vícenasobne podpisy)
    const inspectionForm = document.getElementById('inspectionForm');
    if (inspectionForm) {
        restoreFormData();
        inspectionForm.addEventListener('input', saveFormData);
        inspectionForm.addEventListener('change', saveFormData);

        let secondsCount = 0;
        const durationInput = document.getElementById('durationSeconds');
        const timerDisplay = document.getElementById('timerDisplay');
        if (durationInput && timerDisplay) {
            setInterval(() => {
                secondsCount++; 
                durationInput.value = secondsCount;
                timerDisplay.textContent = String(Math.floor(secondsCount / 60)).padStart(2, '0') + ':' + String(secondsCount % 60).padStart(2, '0');
            }, 1000);
        }

        document.querySelectorAll('canvas[id^="sigCanvas_"]').forEach(canvas => {
            const idx = canvas.id.split('_')[1];
            initSignaturePad(canvas, 'sigInput_' + idx);
        });
    }
});


// ==========================================
// FUNKCE: PODPISOVA PLATNA (Univerzalni jadro s opravou kurzoru)
// ==========================================
function initSignaturePad(canvas, inputId) {
    const ctx = canvas.getContext('2d');
    let drawing = false;

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;

        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = canvas.width; tempCanvas.height = canvas.height;
        if (canvas.width > 0) tempCanvas.getContext('2d').drawImage(canvas, 0, 0);

        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;

        ctx.drawImage(tempCanvas, 0, 0, tempCanvas.width, tempCanvas.height, 0, 0, canvas.width, canvas.height);
        ctx.lineWidth = 2 * ratio;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0000cd';
    }

    window.addEventListener('resize', resizeCanvas);
    setTimeout(resizeCanvas, 150);
    resizeCanvas();

    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        return { 
            x: (clientX - rect.left) * (canvas.width / rect.width), 
            y: (clientY - rect.top) * (canvas.height / rect.height) 
        };
    };

    const start = (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); if(e.cancelable) e.preventDefault(); };
    const move = (e) => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); if(e.cancelable) e.preventDefault(); };
    const stop = () => { 
        if (drawing) { 
            drawing = false; 
            ctx.closePath();
            const inp = document.getElementById(inputId);
            if (inp) inp.value = canvas.toDataURL('image/png'); 
        } 
    };

    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop); canvas.addEventListener('mouseout', stop);
    canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);
}

function clearSignature() { 
    const canvas = document.getElementById('signatureCanvas');
    if(canvas) {
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); 
        document.getElementById('signatureInput').value = ''; 
    }
}

function clearInspectionSignature(idx) { 
    const canvas = document.getElementById('sigCanvas_' + idx); 
    if(canvas) {
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); 
        document.getElementById('sigInput_' + idx).value = ''; 
    }
}

// ==========================================
// FUNKCE: RESENI ZAVAD (Fotky z oprav)
// ==========================================
async function addResolutionPhoto(source) {
    const fileInput = document.createElement('input');
    fileInput.type = 'file'; 
    if (source === 'camera') { fileInput.accept = 'image/*'; fileInput.capture = 'environment'; } 
    else { fileInput.accept = 'image/*, .heic, .heif'; fileInput.multiple = true; }
    
    fileInput.onchange = async function() {
        if (this.files && this.files.length > 0) {
            const previewContainer = document.getElementById('photoPreview');
            
            for(let i=0; i < this.files.length; i++) {
                let file = this.files[i];
                
                if (file.name.toLowerCase().endsWith('.heic') || file.type === 'image/heic') {
                    try {
                        const convertedBlob = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.8 });
                        file = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob; 
                    } catch (err) { alert("Fotografii HEIC se nepodařilo zpracovat."); continue; } 
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
                        let width = img.width, height = img.height; const MAX_DIM = 1200;
                        if (width > height) { if (width > MAX_DIM) { height *= MAX_DIM / width; width = MAX_DIM; } } 
                        else { if (height > MAX_DIM) { width *= MAX_DIM / height; height = MAX_DIM; } }
                        canvas.width = width; canvas.height = height; ctx.drawImage(img, 0, 0, width, height);
                        
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                        
                        const wrapper = document.createElement('div');
                        wrapper.style.display = 'inline-block';
                        
                        const previewImg = document.createElement('img');
                        previewImg.src = dataUrl; previewImg.style.height = '80px'; previewImg.style.borderRadius = '4px'; previewImg.style.border = '1px solid #ccc';
                        
                        const hiddenInp = document.createElement('input');
                        hiddenInp.type = 'hidden'; hiddenInp.name = 'resolution_photos_base64[]'; hiddenInp.value = dataUrl;
                        
                        wrapper.appendChild(previewImg);
                        wrapper.appendChild(hiddenInp);
                        previewContainer.appendChild(wrapper);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    };
    fileInput.click(); 
}

function validateResolution() {
    const canvas = document.getElementById('signatureCanvas');
    const blank = document.createElement('canvas');
    blank.width = canvas.width; blank.height = canvas.height;
    if (canvas.toDataURL() === blank.toDataURL()) {
        alert("Prosím, podepište formulář před uložením.");
        return false;
    }
    document.getElementById('signatureInput').value = canvas.toDataURL('image/png');
    return true;
}

// ==========================================
// FUNKCE: KONTROLY A REVIZE (Fotky, Autosave)
// ==========================================
function getAutoSaveKey() {
    const form = document.getElementById('inspectionForm');
    if (!form) return null;
    return 'cmms_autosave_' + form.dataset.assetId + '_' + form.dataset.templateId;
}

function saveFormData() {
    const key = getAutoSaveKey();
    if(!key) return;
    const formData = {};
    document.querySelectorAll('#inspectionForm input[type="text"], #inspectionForm input[type="number"], #inspectionForm textarea').forEach(input => {
        if (input.name) formData[input.name] = input.value;
    });
    document.querySelectorAll('#inspectionForm input[type="radio"]:checked').forEach(radio => {
        if (radio.name) formData[radio.name] = radio.value;
    });
    localStorage.setItem(key, JSON.stringify(formData));
}

function restoreFormData() {
    const key = getAutoSaveKey();
    if(!key) return;
    const saved = localStorage.getItem(key);
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
                    radio.checked = true; restoredCount++;
                    if (radio.hasAttribute('data-change')) radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            if (restoredCount > 0) {
                const form = document.getElementById('inspectionForm');
                const banner = document.createElement('div');
                banner.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle;">settings_backup_restore</span> Rozepsaná data byla automaticky obnovena.';
                banner.style.cssText = 'background: rgba(39, 174, 96, 0.15); color: var(--success); padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; border: 1px solid var(--success); font-weight: bold; transition: opacity 0.5s;';
                form.insertBefore(banner, form.firstChild);
                setTimeout(() => banner.style.opacity = '0', 4500);
                setTimeout(() => banner.style.display = 'none', 5000);
            }
        } catch (e) {}
    }
}

async function addPhotoInput(index, fieldId, source) {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    if (source === 'camera') { fileInput.accept = 'image/*'; fileInput.capture = 'environment'; } 
    else { fileInput.accept = 'image/*, .heic, .heif'; fileInput.multiple = true; }
    
    fileInput.onchange = async function() {
        if (this.files && this.files.length > 0) {
            const previewContainer = document.getElementById('preview_' + index);
            const loadingIndicator = document.getElementById('loading_' + index);
            
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
                        const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
                        let width = img.width, height = img.height; const MAX_DIM = 1200;
                        if (width > height) { if (width > MAX_DIM) { height *= MAX_DIM / width; width = MAX_DIM; } } 
                        else { if (height > MAX_DIM) { width *= MAX_DIM / height; height = MAX_DIM; } }
                        canvas.width = width; canvas.height = height; ctx.drawImage(img, 0, 0, width, height);
                        
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                        const wrapper = document.createElement('div');
                        wrapper.style.display = 'inline-block';
                        
                        const previewImg = document.createElement('img');
                        previewImg.src = dataUrl; previewImg.style.height = '80px'; previewImg.style.borderRadius = '4px'; previewImg.style.border = '1px solid #ccc';
                        
                        const hiddenInp = document.createElement('input');
                        hiddenInp.type = 'hidden'; hiddenInp.name = 'photos_base64[' + fieldId + '][]'; hiddenInp.value = dataUrl;
                        
                        wrapper.appendChild(previewImg);
                        wrapper.appendChild(hiddenInp);
                        previewContainer.appendChild(wrapper);
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
        const canvas = document.getElementById('sigCanvas_' + sigInputs[i].id.split('_')[1]);
        const blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        if (canvas.toDataURL() === blank.toDataURL()) {
            if (sigInputs[i].dataset.isRequired === 'true') {
                alert("Nebylo vyplněno povinné pole: " + sigInputs[i].dataset.label); return false;
            }
        } else {
            sigInputs[i].value = canvas.toDataURL('image/png');
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
    
    const key = getAutoSaveKey();
    if(key) localStorage.removeItem(key);
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

// ==========================================
// FUNKCE: OVLÁDÁNÍ ROLETKY ÚSEKŮ (z users.php)
// ==========================================
function toggleDropdown() {
    const dropdown = document.getElementById("deptDropdown");
    if (dropdown) dropdown.classList.toggle("active");
}

function updateDropdownText() {
    const checkboxes = document.querySelectorAll('#deptDropdown input[type="checkbox"]:checked');
    const title = document.getElementById("dropdownTitle");
    if (!title) return;

    if (checkboxes.length === 0) {
        title.textContent = "-- Zvolte úseky --";
    } else if (checkboxes.length === 1) {
        title.textContent = checkboxes[0].parentElement.textContent.trim();
    } else {
        title.textContent = "Vybráno úseků: " + checkboxes.length;
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.custom-dropdown')) {
        document.querySelectorAll('.custom-dropdown.active').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    }
});

// Registrace funkcí volaných z HTML přes data-action / data-change / data-submit
Object.assign(window.CMMS_ACTIONS, {
    clearSignature: clearSignature,
    clearInspectionSignature: clearInspectionSignature,
    addResolutionPhoto: addResolutionPhoto,
    addPhotoInput: addPhotoInput,
    validateResolution: validateResolution,
    validateInspectionForm: validateInspectionForm,
    toggleFormFields: toggleFormFields,
    toggleDropdown: toggleDropdown,
    updateDropdownText: updateDropdownText
});
