<?php 
if (!defined('APP_ROOT')) exit; 

// Zjištění URL adresy tvého hostingu, aby na ni mohl QR kód správně ukázat z mobilu
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = $protocol . $host . $path;
?>

<!-- Formulář pro přidání nového zařízení -->
<div class="card card-primary-top">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">add_circle</span> Přidat nové zařízení</h3>
    <form method="POST" action="index.php?page=asset_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 250px;">
            <label for="name" style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Název zařízení *</label>
            <input type="text" id="name" name="name" required placeholder="Např. Kotelna SO03, Změkčovač ZV1" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div style="flex: 2; min-width: 250px;">
            <label for="description" style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Popis / Umístění</label>
            <input type="text" id="description" name="description" placeholder="Nepovinné detaily, lokace..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
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
                            <td>#<?= $a['id'] ?></td>
                            <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($a['name']) ?></td>
                            <td><?= htmlspecialchars($a['description']) ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                
                                <!-- NOVÉ TLAČÍTKO PRO STATISTIKY A GRAFY -->
                                <a href="index.php?page=asset_stats&id=<?= $a['id'] ?>" style="color: #e67e22; text-decoration: none; margin-right: 15px;" title="Zobrazit vývoj naměřených hodnot v čase">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">monitoring</span> Grafy
                                </a>
                                
                                <!-- TLAČÍTKO PRO RUČNÍ VYPLNĚNÍ Z PC -->
                                <a href="<?= $scanUrl ?>" style="color: #2980b9; text-decoration: none; margin-right: 15px;" title="Otevřít formuláře pro toto zařízení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">edit_document</span> Vyplnit
                                </a>
                                
                                <!-- TLAČÍTKO PRO TISK QR KÓDU -->
                                <a href="#" onclick="window.open('<?= $qrApiUrl ?>', 'QR Kód', 'width=350,height=350'); return false;" style="color: var(--success); text-decoration: none; margin-right: 15px;" title="Vytisknout QR kód k nalepení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">qr_code_2</span> Zobrazit QR kód
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
