<?php if (!defined('APP_ROOT')) exit; ?>

<!-- Box pro filtrování záznamů -->
<div class="card card-primary-top">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">filter_alt</span> Filtr záznamů</h3>
    <form method="GET" action="index.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="inspections">
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Zařízení / Stroj</label>
            <select name="asset_id" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: #fff;">
                <option value="0">-- Všechna zařízení --</option>
                <?php foreach($assets as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= ($filter_asset == $a['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Výsledek kontroly</label>
            <select name="status" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: #fff;">
                <option value="">-- Všechny stavy --</option>
                <option value="OK" <?= ($filter_status === 'OK') ? 'selected' : '' ?>>V pořádku (OK)</option>
                <option value="KO" <?= ($filter_status === 'KO') ? 'selected' : '' ?>>Závada (KO)</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn btn-primary">Filtrovat výsledky</button>
            <a href="index.php?page=inspections" class="btn" style="background: #95a5a6; margin-left: 10px;">Zrušit</a>
        </div>
    </form>
</div>

<!-- Samotná tabulka historie -->
<div class="card">
    <h3 style="margin-top:0;">Historie všech provedených úkonů</h3>
    <?php if (empty($inspections)): ?>
        <p style="color: var(--text-muted); font-style: italic;">Nebyly nalezeny žádné záznamy odpovídající vašemu filtru.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Datum a časová náročnost</th>
                        <th>Zařízení</th>
                        <th>Úkon</th>
                        <th>Technik</th>
                        <th>Výsledek</th>
                        <th style="min-width: 250px;">Odpovědi (Detail)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inspections as $insp): ?>
                        <tr style="<?= !empty($insp['ticket_status']) ? 'border-bottom: none;' : '' ?>">
                            <td style="white-space: nowrap; padding-top: 15px;">
                                <span style="font-weight: bold; color: var(--text-main);">
                                    <?= date('d.m.Y H:i', strtotime($insp['created_at'])) ?>
                                </span>
                                <?php if (isset($insp['duration_seconds']) && $insp['duration_seconds'] > 0): ?>
                                    <br>
                                    <span style="font-size: 0.85em; color: var(--text-muted); display: inline-flex; align-items: center; margin-top: 3px;" title="Délka provádění kontroly">
                                        <span class="material-symbols-outlined" style="font-size: 1.2em; margin-right: 4px;">timer</span>
                                        <?= floor($insp['duration_seconds'] / 60) ?> min <?= $insp['duration_seconds'] % 60 ?> s
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: bold; color: var(--primary); padding-top: 15px;"><?= htmlspecialchars($insp['asset_name']) ?></td>
                            <td style="padding-top: 15px;"><?= htmlspecialchars($insp['template_name']) ?></td>
                            <td style="padding-top: 15px;">
                                <?= htmlspecialchars($insp['first_name'] . ' ' . $insp['last_name']) ?>
                            </td>
                            <td style="padding-top: 15px;">
                                <?php if ($insp['status'] === 'OK'): ?>
                                    <span class="badge badge-closed">OK</span>
                                <?php elseif ($insp['status'] === 'Odstaveno'): ?>
                                    <span class="badge" style="background: #7f8c8d; color: #fff;">ODSTÁVKA</span>
                                <?php else: ?>
                                    <span class="badge badge-open">KO</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.9em; background: #fafafa; padding-top: 15px;">
                                <?php 
                                    $data = json_decode($insp['data_json'], true);
                                    if (is_array($data)) {
                                        foreach($data as $key => $val) {
                                            if (is_string($val) && strpos($val, 'data:image/') === 0) {
                                                echo "<div style='margin-bottom: 8px;'><strong>" . htmlspecialchars($key) . ":</strong><br> <img src='{$val}' style='max-height: 40px; mix-blend-mode: multiply; margin-top: 5px;'></div>";
                                            } 
                                            elseif (is_array($val) && isset($val[0]) && strpos((string)$val[0], 'assets/uploads/') === 0) {
                                                echo "<div style='margin-bottom: 8px;'><strong>" . htmlspecialchars($key) . ":</strong><br><div style='display: flex; gap: 8px; flex-wrap: wrap; margin-top: 5px;'>";
                                                foreach($val as $photo) {
                                                    if (file_exists($photo)) {
                                                        echo "<a href='{$photo}' target='_blank' title='Kliknutím zobrazíte plnou velikost'><img src='{$photo}' style='max-height: 80px; border-radius: 4px; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer;'></a>";
                                                    }
                                                }
                                                echo "</div></div>";
                                            }
                                            elseif (is_string($val) && strpos($val, 'assets/uploads/') === 0) {
                                                if (file_exists($val)) {
                                                    echo "<div style='margin-bottom: 8px;'><strong>" . htmlspecialchars($key) . ":</strong><br> <a href='{$val}' target='_blank' title='Kliknutím zobrazíte plnou velikost'><img src='{$val}' style='max-height: 80px; border-radius: 4px; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 5px; cursor: pointer;'></a></div>";
                                                }
                                            }
                                            else {
                                                if (is_array($val)) {
                                                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                                                }
                                                $color = ($val === 'KO' || $val === 'Ne') ? 'var(--danger)' : (($val === 'OK' || $val === 'Ano') ? 'var(--success)' : 'var(--text-main)');
                                                if ($val === 'Odstaveno') $color = '#7f8c8d'; 
                                                
                                                echo "<div style='margin-bottom: 3px;'><strong>" . htmlspecialchars($key) . ":</strong> <span style='color: {$color}; font-weight: bold;'>" . htmlspecialchars((string)$val) . "</span></div>";
                                            }
                                        }
                                    }
                                ?>
                            </td>
                        </tr>
                        
                        <?php if ($insp['ticket_status'] === 'open'): ?>
                            <tr>
                                <td colspan="6" style="padding: 0 15px 15px 15px; background: #fff; border-top: none;">
                                    <div style="padding: 10px 15px; background: #f8d7da; border-left: 4px solid var(--danger); border-radius: 4px; width: 80%;">
                                        <strong style="color: #721c24; display: flex; align-items: center; gap: 5px;">
                                            <span class="material-symbols-outlined" style="font-size: 1.2em;">warning</span> Závada je aktuálně v řešení (otevřený tiket)
                                        </strong>
                                    </div>
                                </td>
                            </tr>
                        <?php elseif ($insp['ticket_status'] === 'closed'): ?>
                            <tr>
                                <td colspan="6" style="padding: 0 15px 15px 15px; background: #fff; border-top: none;">
                                    <div style="padding: 15px; background: #e8f5e9; border-left: 4px solid var(--success); border-radius: 4px; width: 80%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                                        
                                        <div style="flex: 1; min-width: 300px;">
                                            <strong style="color: #155724; display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                                                <span class="material-symbols-outlined" style="font-size: 1.2em;">verified</span> Závada byla odstraněna
                                            </strong>
                                            <div style="color: var(--text-main); margin-bottom: 5px; font-style: italic;">"<?= nl2br(htmlspecialchars($insp['resolution_text'])) ?>"</div>
                                            <div style="color: var(--text-muted); font-size: 0.9em;">
                                                <strong>Technik:</strong> <?= htmlspecialchars($insp['res_first_name'] . ' ' . $insp['res_last_name']) ?> 
                                                (<?= date('d.m.Y H:i', strtotime($insp['resolved_at'])) ?>)
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($insp['resolution_signature'])): ?>
                                            <div>
                                                <img src="<?= $insp['resolution_signature'] ?>" style="max-height: 40px; mix-blend-mode: multiply;">
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- VYKRESLENÍ FOTOGRAFIÍ OPRAVY VE VÝPISU -->
                                        <?php if (!empty($insp['resolution_photos'])): ?>
                                            <?php 
                                            $rPhotos = json_decode($insp['resolution_photos'], true); 
                                            if (is_array($rPhotos) && count($rPhotos) > 0): 
                                            ?>
                                                <div style="flex-basis: 100%; margin-top: 15px; border-top: 1px dashed #c3e6cb; padding-top: 10px;">
                                                    <strong style="color: #155724; display: block; margin-bottom: 5px;">Fotodokumentace opravy:</strong>
                                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                        <?php foreach($rPhotos as $rp): ?>
                                                            <a href="<?= $rp ?>" target="_blank"><img src="<?= $rp ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #a3c2af; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
