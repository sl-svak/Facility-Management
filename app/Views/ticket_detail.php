<?php if (!defined('APP_ROOT')) exit; ?>

<div style="max-width: 800px; margin: 0 auto;">
    
    <!-- TLAČÍTKO ZPĚT -->
    <div style="margin-bottom: 20px;">
        <a href="index.php?page=tickets" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět na seznam závad
        </a>
    </div>

    <!-- HLAVIČKA ZÁVADY -->
    <div class="card" style="border-top: 4px solid <?= $ticket['status'] === 'open' ? 'var(--danger)' : 'var(--success)' ?>;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0 0 10px 0; color: #2c3e50;">
                    <?= htmlspecialchars($ticket['title']) ?>
                </h2>
                <div style="color: #7f8c8d; font-size: 0.95em;">
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">precision_manufacturing</span> 
                    <strong>Stroj:</strong> <?= htmlspecialchars($ticket['asset_name']) ?><br>
                    
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">person</span> 
                    <strong>Nahlásil:</strong> <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?> 
                    (<?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?>)
                </div>
            </div>
            <div>
                <?php if ($ticket['status'] === 'open'): ?>
                    <span class="badge badge-open" style="font-size: 1.1em; padding: 8px 15px;">K ŘEŠENÍ</span>
                <?php else: ?>
                    <span class="badge badge-closed" style="font-size: 1.1em; padding: 8px 15px;">VYŘEŠENO</span>
                <?php endif; ?>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #ecf0f1; margin: 20px 0;">

        <!-- VÝPIS DAT Z KONTROLY -->
        <h4 style="margin-top: 0; color: #34495e;">Naměřené hodnoty z kontroly:</h4>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef;">
            <?php 
                $data = json_decode($ticket['data_json'], true);
                if (is_array($data)): 
                    foreach($data as $key => $val):
                        if (is_array($val) && isset($val[0]) && strpos((string)$val[0], 'assets/uploads/') === 0): ?>
                            <div style="margin-bottom: 10px;">
                                <strong><?= htmlspecialchars($key) ?>:</strong><br>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                                    <?php foreach($val as $photo): ?>
                                        <a href="<?= $photo ?>" target="_blank">
                                            <img src="<?= $photo ?>" style="max-height: 100px; border-radius: 4px; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (is_string($val) && strpos($val, 'assets/uploads/') === 0): ?>
                            <div style="margin-bottom: 10px;">
                                <strong><?= htmlspecialchars($key) ?>:</strong><br>
                                <a href="<?= $val ?>" target="_blank"><img src="<?= $val ?>" style="max-height: 100px; border-radius: 4px; border: 1px solid #ccc;"></a>
                            </div>
                        <?php elseif (is_string($val) && strpos($val, 'data:image/') === 0): ?>
                            <div style="margin-bottom: 10px;">
                                <strong><?= htmlspecialchars($key) ?>:</strong><br>
                                <img src="<?= $val ?>" style="max-height: 50px; mix-blend-mode: multiply;">
                            </div>
                        <?php else: 
                            if (is_array($val)) $val = json_encode($val);
                            $color = ($val === 'KO' || $val === 'Ne') ? 'var(--danger)' : (($val === 'OK' || $val === 'Ano') ? 'var(--success)' : '#2c3e50');
                        ?>
                            <div style="margin-bottom: 8px; border-bottom: 1px dashed #ddd; padding-bottom: 4px;">
                                <strong style="color: #7f8c8d;"><?= htmlspecialchars($key) ?>:</strong> 
                                <span style="color: <?= $color ?>; font-weight: bold; float: right;"><?= htmlspecialchars((string)$val) ?></span>
                            </div>
                        <?php endif;
                    endforeach;
                else:
                    echo "<i>Žádná detailní data nejsou k dispozici.</i>";
                endif;
            ?>
        </div>
    </div>

    <!-- FORMULÁŘ PRO UZAVŘENÍ NEBO ZOBRAZENÍ ŘEŠENÍ -->
    <?php if ($ticket['status'] === 'open'): ?>
        
        <div class="card" style="background: #eaf2f8; border: 1px solid #3498db;">
            <h3 style="margin-top: 0; color: #2980b9;">Vyřešení závady</h3>
            <form method="POST" action="index.php?page=ticket_resolve" onsubmit="return validateResolution();">
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50;">Způsob opravy a vyměněné díly *</label>
                    <textarea name="resolution_text" id="resolution_text" required placeholder="Popište, jak byla závada odstraněna..." style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #bdc3c7; border-radius: 6px; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>

                <!-- PŘIDÁNÍ FOTOGRAFIÍ OPRAVY -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50;">Fotodokumentace po opravě (volitelné)</label>
                    <div style="background: #fff; border: 1px dashed #95a5a6; border-radius: 6px; padding: 10px; text-align: center;">
                        <div id="preview_resolution" style="display: none; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 15px;"></div>
                        <button type="button" class="btn" style="background: #e67e22; color: #fff; padding: 10px 20px; font-weight: bold;" onclick="addResolutionPhoto()">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">add_a_photo</span> Vyfotit výsledek opravy
                        </button>
                        <div id="inputs_resolution" style="display: none;"></div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50;">Podpis opraváře *</label>
                    <div style="background: #fff; border: 1px dashed #95a5a6; border-radius: 6px; padding: 10px; text-align: center;">
                        <canvas id="sigCanvas" width="400" height="150" style="background: #fff; border: 1px solid #ecf0f1; touch-action: none; max-width: 100%; border-radius: 4px;"></canvas>
                        <input type="hidden" name="resolution_signature" id="sigInput">
                        <br>
                        <button type="button" onclick="clearSignature()" class="btn" style="background: #ecf0f1; color: #333; margin-top: 10px; font-weight: bold;">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">ink_eraser</span> Vymazat podpis
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1em; background: #27ae60; border-color: #27ae60;">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">verified</span> Závada je opravena - Uzavřít tiket
                </button>
            </form>
        </div>

        <script>
            // --- KOMPRESE FOTEK OPRAVY ---
            function addResolutionPhoto() {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.multiple = true; 
                
                fileInput.onchange = function() {
                    if (this.files && this.files.length > 0) {
                        const previewContainer = document.getElementById('preview_resolution');
                        const inputsContainer = document.getElementById('inputs_resolution');
                        previewContainer.style.display = 'flex';
                        
                        for(let i=0; i < this.files.length; i++) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const img = new Image();
                                img.onload = function() {
                                    const canvas = document.createElement('canvas');
                                    const ctx = canvas.getContext('2d');
                                    let width = img.width; let height = img.height; const MAX_DIM = 1200;

                                    if (width > height) { if (width > MAX_DIM) { height *= MAX_DIM / width; width = MAX_DIM; } } 
                                    else { if (height > MAX_DIM) { width *= MAX_DIM / height; height = MAX_DIM; } }

                                    canvas.width = width; canvas.height = height;
                                    ctx.drawImage(img, 0, 0, width, height);
                                    
                                    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                                    
                                    const previewImg = document.createElement('img');
                                    previewImg.src = dataUrl;
                                    previewImg.style.height = '100px';
                                    previewImg.style.borderRadius = '4px';
                                    previewImg.style.border = '1px solid #ccc';
                                    previewImg.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                                    previewContainer.appendChild(previewImg);
                                    
                                    const hiddenInp = document.createElement('input');
                                    hiddenInp.type = 'hidden';
                                    hiddenInp.name = 'resolution_photos_base64[]';
                                    hiddenInp.value = dataUrl;
                                    inputsContainer.appendChild(hiddenInp);
                                };
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(this.files[i]);
                        }
                    }
                };
                fileInput.click(); 
            }

            // --- PODPIS OPRAVÁŘE ---
            const canvas = document.getElementById('sigCanvas');
            const ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#0000cd'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            let drawing = false;

            const getPos = (e) => {
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width; const scaleY = canvas.height / rect.height;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
            };

            const start = (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); };
            const move = (e) => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
            const stop = () => { if (drawing) { drawing = false; document.getElementById('sigInput').value = canvas.toDataURL(); } };

            canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', stop);
            canvas.addEventListener('touchstart', start, { passive: false }); canvas.addEventListener('touchmove', move, { passive: false }); window.addEventListener('touchend', stop);

            function clearSignature() { ctx.clearRect(0, 0, canvas.width, canvas.height); document.getElementById('sigInput').value = ''; }

            function validateResolution() {
                if (document.getElementById('sigInput').value.trim() === '') {
                    alert('Pro uzavření závady je nutné připojit Váš podpis!');
                    return false;
                }
                return true;
            }
        </script>

    <?php else: ?>
        
        <!-- HOTOVÝ TIKET: Zobrazení řešení -->
        <div class="card" style="background: #eafaf1; border: 1px solid #2ecc71;">
            <h3 style="margin-top: 0; color: #27ae60;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> Zpráva o opravě
            </h3>
            
            <p style="color: #2c3e50; font-size: 1.05em; font-style: italic; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #d4efdf;">
                "<?= nl2br(htmlspecialchars($ticket['resolution_text'])) ?>"
            </p>

            <!-- VYKRESLENÍ FOTOGRAFIÍ OPRAVY -->
            <?php 
            if (!empty($ticket['resolution_photos'])) {
                $resPhotos = json_decode($ticket['resolution_photos'], true);
                if (is_array($resPhotos) && count($resPhotos) > 0) {
                    echo '<div style="margin: 15px 0;"><strong>Fotografie po opravě:</strong><br><div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">';
                    foreach ($resPhotos as $rPhoto) {
                        echo '<a href="'.$rPhoto.'" target="_blank"><img src="'.$rPhoto.'" style="max-height: 100px; border-radius: 4px; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></a>';
                    }
                    echo '</div></div>';
                }
            }
            ?>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; flex-wrap: wrap; gap: 15px;">
                <div style="color: #7f8c8d; font-size: 0.95em;">
                    <strong>Opravu provedl:</strong> <?= htmlspecialchars($ticket['res_first_name'] . ' ' . $ticket['res_last_name']) ?><br>
                    <strong>Datum uzavření:</strong> <?= date('d.m.Y H:i', strtotime($ticket['resolved_at'])) ?>
                </div>
                
                <?php if (!empty($ticket['resolution_signature'])): ?>
                    <div style="text-align: right;">
                        <span style="font-size: 0.8em; color: #95a5a6; display: block; margin-bottom: 5px;">Podpis opraváře:</span>
                        <img src="<?= $ticket['resolution_signature'] ?>" style="max-height: 60px; mix-blend-mode: multiply; border-bottom: 1px solid #bdc3c7;">
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
