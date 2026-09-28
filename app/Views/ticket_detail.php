<?php if (!defined('APP_ROOT')) exit; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2><?= htmlspecialchars($pageTitle) ?></h2>
    <a href="index.php?page=tickets" class="btn" style="background: #7f8c8d;">Zpět na seznam</a>
</div>

<div class="card">
    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        
        <!-- Levý sloupec: Informace o závadě -->
        <div style="flex: 1; min-width: 300px;">
            <h3 style="margin-top: 0; color: var(--primary);">Informace o hlášení</h3>
            <table class="table">
                <tr>
                    <td style="width: 150px; color: var(--text-muted);">Založeno:</td>
                    <td><strong><?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?></strong></td>
                </tr>
                <tr>
                    <td style="color: var(--text-muted);">Zařízení:</td>
                    <td><strong><?= htmlspecialchars($ticket['asset_name']) ?></strong></td>
                </tr>
                <tr>
                    <td style="color: var(--text-muted);">Popis závady:</td>
                    <td style="color: var(--danger); font-weight: bold;"><?= htmlspecialchars($ticket['title']) ?></td>
                </tr>
                <tr>
                    <td style="color: var(--text-muted);">Stav:</td>
                    <td>
                        <?php if ($ticket['status'] === 'open'): ?>
                            <span class="badge badge-open">K ŘEŠENÍ (Otevřeno)</span>
                        <?php else: ?>
                            <span class="badge badge-closed">VYŘEŠENO (Uzavřeno)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if (!empty($ticket['inspection_id'])): ?>
                <h4 style="margin-top: 25px;">Údaje z revize, která závadu odhalila</h4>
                <div style="background: #fafafa; padding: 15px; border-radius: 4px; border: 1px solid #eee; font-size: 0.9em;">
                    <div style="margin-bottom: 10px;">
                        <strong>Kontroloval:</strong> <?= htmlspecialchars($ticket['first_name'] . ' ' .$ticket['last_name']) ?> <br>
                        <strong>Kdy:</strong> <?= date('d.m.Y H:i', strtotime($ticket['inspection_date'])) ?>
                    </div>
                    <?php 
                        $data = json_decode($ticket['data_json'], true);
                        if (is_array($data)) {
                            foreach($data as $key =>$val) {
                                if (is_string($val) && strpos($val, 'data:image/') === 0) {
                                    $safeVal = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                                    echo "<div style='margin-bottom: 5px;'><strong>{$key}:</strong><br> <img src='{$safeVal}' style='max-height: 40px; mix-blend-mode: multiply;'></div>";
                                } 
                                elseif (is_array($val) && isset($val[0]) && strpos((string)$val[0], 'assets/uploads/') === 0) {
                                    echo "<div style='margin-bottom: 5px;'><strong>{$key}:</strong><br>";
                                    foreach($val as$photo) {
                                        if (file_exists($photo)) {
                                            $secureUrl = 'index.php?page=file&name=' . urlencode(basename($photo));
                                            echo "<a href='{$secureUrl}' target='_blank'><img src='{$secureUrl}' style='max-height: 60px; margin-right: 5px; border-radius: 4px; border: 1px solid #ccc;'></a>";
                                        }
                                    }
                                    echo "</div>";
                                }
                                elseif (is_string($val) && strpos($val, 'assets/uploads/') === 0) {
                                    if (file_exists($val)) {
                                        $secureUrl = 'index.php?page=file&name=' . urlencode(basename($val));
                                        echo "<div style='margin-bottom: 5px;'><strong>{$key}:</strong><br> <a href='{$secureUrl}' target='_blank'><img src='{$secureUrl}' style='max-height: 40px; mix-blend-mode: multiply;'></a></div>";
                                    }
                                }
                                else {
                                    if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                                    $color = ($val === 'KO') ? 'var(--danger)' : 'var(--text-main)';
                                    echo "<div style='margin-bottom: 3px;'><strong>{$key}:</strong> <span style='color: {$color};'>" . htmlspecialchars((string)$val) . "</span></div>";
                                }
                            }
                        }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pravý sloupec: Řešení / Formulář -->
        <div style="flex: 1; min-width: 300px;">
            <?php if ($ticket['status'] === 'open'): ?>
                <div style="background: #e8f4f8; padding: 20px; border-radius: 8px; border: 1px solid #bce0ee;">
                    <h3 style="margin-top: 0; color: #2980b9;">Vyřešit závadu</h3>
                    
                    <form method="POST" action="index.php?page=ticket_resolve" id="resolveForm" onsubmit="return validateResolution();">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                        
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Způsob opravy (Poznámka)</label>
                        <textarea name="resolution_text" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 15px;" required placeholder="Popište, jak byla závada odstraněna..."></textarea>
                        
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Fotografie opravy (Volitelné)</label>
                        
                        <div style="background: #fff; border: 1px dashed #ccc; border-radius: 4px; padding: 10px; text-align: center; margin-bottom: 15px;">
                            <div id="photoPreview" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-bottom: 10px;"></div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn btn-warning" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" onclick="addResolutionPhoto('camera')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_camera</span> Vyfotit
                                </button>
                                <button type="button" class="btn btn-info" style="flex: 1; padding: 12px 10px; font-weight: bold; border-radius: 6px;" onclick="addResolutionPhoto('gallery')">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">photo_library</span> Z galerie
                                </button>
                            </div>
                            
                            <div id="hiddenPhotoInputs"></div>
                        </div>

                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Podpis technika</label>
                        <div style="border: 1px solid #ccc; background: #fff; border-radius: 4px; margin-bottom: 10px;">
                            <canvas id="signatureCanvas" width="350" height="150" style="width: 100%; touch-action: none; cursor: crosshair;"></canvas>
                        </div>
                        <button type="button" class="btn" onclick="clearSignature()" style="background: #95a5a6; padding: 6px 12px; font-size: 0.9em; margin-bottom: 15px;">Vymazat podpis</button>
                        
                        <input type="hidden" name="resolution_signature" id="signatureInput">
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1em; padding: 12px; background: #27ae60;">Uložit opravu a uzavřít tiket</button>
                    </form>
                </div>
            <?php else: ?>
                <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb;">
                    <h3 style="margin-top: 0; color: #155724; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined">check_circle</span> Údaje o vyřešení
                    </h3>
                    
                    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 5px;">Způsob opravy:</p>
                    <div style="font-size: 1.1em; margin-bottom: 20px; font-style: italic;">
                        "<?= nl2br(htmlspecialchars($ticket['resolution_text'])) ?>"
                    </div>
                    
                    <?php if (!empty($ticket['resolution_photos'])): ?>
                        <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 5px;">Fotografie z opravy:</p>
                        <?php $resPhotos = json_decode($ticket['resolution_photos'], true); ?>
                        <?php if (is_array($resPhotos) && count($resPhotos) > 0): ?>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                                <?php foreach ($resPhotos as$rp): ?>
                                    <?php $secureUrl = 'index.php?page=file&name=' . urlencode(basename($rp)); ?>
                                    <a href="<?= $secureUrl ?>" target="_blank"><img src="<?= $secureUrl ?>" style="max-height: 100px; border-radius: 4px; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div style="display: flex; gap: 20px; border-top: 1px solid #c3e6cb; padding-top: 15px; align-items: center;">
                        <div>
                            <p style="color: var(--text-muted); font-size: 0.9em; margin: 0 0 5px 0;">Odpovědný technik:</p>
                            <strong><?= htmlspecialchars($ticket['res_first_name'] . ' ' .$ticket['res_last_name']) ?></strong><br>
                            <span style="font-size: 0.85em; color: var(--text-muted);"><?= date('d.m.Y H:i', strtotime($ticket['resolved_at'])) ?></span>
                        </div>
                        <?php if (!empty($ticket['resolution_signature'])): ?>
                            <div style="text-align: right; flex-grow: 1;">
                                <p style="color: var(--text-muted); font-size: 0.8em; margin: 0 0 2px 0;">Podpis:</p>
                                <?php 
                                    $sigVal =$ticket['resolution_signature'];
                                    $sigSrc = (strpos($sigVal, 'data:image/') === 0) 
                                        ? htmlspecialchars($sigVal, ENT_QUOTES, 'UTF-8') 
                                        : 'index.php?page=file&name=' . urlencode(basename($sigVal));
                                ?>
                                <img src="<?= $sigSrc ?>" alt="Podpis technika" style="max-height: 50px; mix-blend-mode: multiply;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
