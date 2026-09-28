/**
 * CMMS Cosmonde - Hlavni JavaScript
 * Zajistuje interaktivitu celeho systemu (podpisy, komprese fotek, modaly, ochranu formularu)
 */

document.addEventListener('DOMContentLoaded', function() {

    // 1. OCHRANA PROTI DVOJITEMU ODESLANI
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                const originalText = btn.innerHTML;
                setTimeout(() => {
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
// FUNKCE: PODPISOVA PLATNA (Univerzalni jadro)
// ==========================================
function initSignaturePad(canvas, inputId) {
    const ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#0000cd'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    let drawing = false;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0000cd';
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    };

    const start = (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); };
    const move = (e) => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
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
            const inputsContainer = document.getElementById('hiddenPhotoInputs');
            
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
                    if (radio.hasAttribute('onchange')) radio.dispatchEvent(new Event('change'));
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
            const inputsContainer = document.getElementById('inputs_' + index);
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
// FUNKCE: GRAFY A ANALYTIKA (asset_stats.php)
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    const chartsContainer = document.getElementById('analytics-charts-container');
    
    if (chartsContainer && typeof ApexCharts !== 'undefined') {
        const rawData = chartsContainer.getAttribute('data-charts');
        if (!rawData) return;
        
        const chartData = JSON.parse(rawData);
        
        const isDarkMode = document.body.classList.contains('theme-dark') || 
                          (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && !document.body.classList.contains('theme-light'));
        const axisTextColor = isDarkMode ? '#aaaaaa' : '#777777';
        const markerBgColor = isDarkMode ? '#1e1e1e' : '#ffffff';
        const dataLabelBgColor = isDarkMode ? '#e0e0e0' : '#333333';

        let chartIndex = 0;
        
        Object.values(chartData).forEach(c => {
            chartIndex++;
            const isBar = c.type === 'bar';
            
            const options = {
                series: [{ name: c.title, data: c.points }],
                chart: { type: isBar ? 'bar' : 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
                colors: isBar ? ['#16a085'] : ['#2980b9'],
                plotOptions: { bar: { borderRadius: 5, columnWidth: '45%', dataLabels: { position: 'top' } } },
                stroke: { curve: 'straight', width: isBar ? 0 : 3 },
                dataLabels: {
                    enabled: true, 
                    formatter: function (val) { return (isBar ? '+' : '') + val; },
                    offsetY: isBar ? -20 : 0, 
                    style: { fontSize: '11px', colors: isBar ? ['#16a085'] : [dataLabelBgColor] },
                    background: { enabled: !isBar, borderRadius: 4, borderWidth: 0 }
                },
                xaxis: { 
                    type: 'datetime', 
                    labels: { datetimeUTC: false, format: 'dd.MM. HH:mm', style: { fontSize: '11px', colors: axisTextColor } } 
                },
                yaxis: { 
                    labels: { style: { colors: axisTextColor, fontWeight: 'bold' } } 
                },
                fill: { 
                    type: isBar ? 'solid' : 'gradient', 
                    gradient: { shadeIntensity: 1, opacityFrom: 0.6, opacityTo: 0.1, stops: [0, 90, 100] } 
                },
                tooltip: {
                    theme: isDarkMode ? 'dark' : 'light',
                    x: { format: 'dd.MM.yyyy HH:mm' },
                    y: {
                        formatter: function(val, opts) {
                            if (isBar) {
                                const point = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
                                const totalStr = point.total !== undefined ? ' (Celkový stav: ' + point.total + ')' : '';
                                return '+' + val + totalStr;
                            }
                            return val;
                        }
                    }
                },
                markers: { size: isBar ? 0 : 4, colors: [markerBgColor], strokeColors: '#2980b9', strokeWidth: 2 }
            };

            const chart = new ApexCharts(document.querySelector("#chart-" + chartIndex), options);
            chart.render();
        });
    }
});
