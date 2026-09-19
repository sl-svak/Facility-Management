<?php 
if (!defined('APP_ROOT')) exit; 

// Zjištění URL adresy tvého hostingu, aby na ni mohl QR kód správně ukázat z mobilu
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = $protocol . $host . $path;
?>

<!-- JavaScript pro tisk štítku s názvem -->
<script>
function printQrCode(qrUrl, assetName) {
    var win = window.open('', '_blank', 'width=400,height=550');
    win.document.write('<!DOCTYPE html><html><head><title>Tisk štítku: ' + assetName + '</title>');
    win.document.write('<style>');
    win.document.write('body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }');
    win.document.write('h2 { margin-bottom: 20px; color: #2c3e50; font-size: 24px; word-wrap: break-word; }');
    win.document.write('img { max-width: 100%; height: auto; border: 1px solid #ccc; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }');
    win.document.write('button { padding: 12px 24px; font-size: 16px; cursor: pointer; background: #2980b9; color: white; border: none; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 20px; }');
    win.document.write('button:hover { background: #3498db; }');
    win.document.write('@media print { button { display: none; } }'); // Skryje tlačítko při samotném tisku
    win.document.write('</style></head><body>');
    
    win.document.write('<h2>' + assetName + '</h2>');
    win.document.write('<img src="' + qrUrl + '" alt="QR Kód">');
    win.document.write('<br><button onclick="window.print()">🖨️ Vytisknout štítek</button>');
    
    win.document.write('</body></html>');
    win.document.close();
}
</script>

<!-- Formulář pro přidání nového zařízení -->
<div class="card card-primary-top">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">add_circle</span> Přidat nové zařízení</h3>
    <form method="POST" action="index.php?page=asset_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        
        <!-- OCHRANA CSRF -->
        <?= Security::csrfField() ?>

        <div style="flex: 1; min-width: 200px;">
            <label for="name" style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Název zařízení *</label>
            <input type="text" id="name" name="name" required placeholder="Např. Kotelna SO03, Změkčovač ZV1" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label for="department_id" style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Organizační úsek</label>
            <select id="department_id" name="department_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
                <option value="">-- Bez úseku / Celo-firemní --</option>
                <?php foreach ($departments ?? [] as $dep): ?>
                    <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 200px;">
            <label for="description" style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Popis / Umístění</label>
            <input type="text" id="description" name="description" placeholder="Nepovinné detaily, lokace..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">save</span> Uložit</button>
        </div>
    </form>
</div>

<!-- Výpis aktivních zařízení z databáze -->
<div class="card">
    <h3 style="margin-top:0;">Seznam zařízení v systému</h3>
    
    <?php if (empty($assets)): ?>
        <p style="color: var(--text-muted); font-style: italic;">Zatím nebyla přidána žádná zařízení.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Název zařízení</th>
                        <th>Úsek</th>
                        <th>Popis</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $a): ?>
                        <?php 
                            // Odkaz přímo na mobilní formulář tohoto stroje (včetně zabezpečovacího hashe)
                            $scanUrl = $baseUrl . "/index.php?page=scan&hash=" . $a['qr_hash'];
                            // Generování obrázku QR kódu z odkazu (přes zdarma dostupné API)
                            $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($scanUrl);
                        ?>
                        <tr>
                            <td style="color: var(--text-muted);">#<?= $a['id'] ?></td>
                            <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($a['name']) ?></td>
                            <td>
                                <?php if (!empty($a['department_name'])): ?>
                                    <span style="background: rgba(41, 128, 185, 0.1); color: var(--info); padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                        <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">domain</span>
                                        <?= htmlspecialchars($a['department_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85em; font-style: italic;">Bez úseku</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($a['description']) ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                
                                <!-- TLAČÍTKO PRO STATISTIKY A GRAFY -->
                                <a href="index.php?page=asset_stats&id=<?= $a['id'] ?>" style="color: #e67e22; text-decoration: none; margin-right: 15px;" title="Zobrazit vývoj naměřených hodnot v čase">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">monitoring</span> Grafy
                                </a>
                                
                                <!-- TLAČÍTKO PRO RUČNÍ VYPLNĚNÍ Z PC -->
                                <a href="<?= $scanUrl ?>" style="color: #2980b9; text-decoration: none; margin-right: 15px;" title="Otevřít formuláře pro toto zařízení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">edit_document</span> Vyplnit
                                </a>
                                
                                <!-- UPRAVENÉ TLAČÍTKO PRO TISK QR KÓDU S NÁZVEM -->
                                <a href="#" onclick="printQrCode('<?= $qrApiUrl ?>', '<?= htmlspecialchars(addslashes($a['name'])) ?>'); return false;" style="color: var(--success); text-decoration: none; margin-right: 15px;" title="Vytisknout QR kód k nalepení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">qr_code_2</span> Zobrazit QR kód
                                </a>
                                
                                <a href="index.php?page=asset_edit&id=<?= $a['id'] ?>" style="color: var(--info); text-decoration: none; margin-right: 15px;" title="Upravit zařízení">
									<span class="material-symbols-outlined" style="vertical-align: middle;">edit</span> Upravit
								</a>

                                <a href="index.php?page=asset_delete&id=<?= $a['id'] ?>" onclick="return confirm('Opravdu chcete zařízení vyřadit? Historie údržby zůstane zachována.');" style="color: var(--danger); text-decoration: none;" title="Vyřadit zařízení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">delete</span> Smazat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
