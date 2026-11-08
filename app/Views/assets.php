<?php 
if (!defined('APP_ROOT')) exit; 

// Zjištění URL adresy tvého hostingu, aby na ni mohl QR kód správně ukázat z mobilu
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = $protocol . $host . $path;
?>

<!-- JavaScript pro tisk štítku s názvem -->

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
                            // QR kód generuje server lokálně (hash zařízení neodchází třetí straně)
                            $qrApiUrl = "index.php?page=asset_qr&id=" . (int)$a['id'];
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
                                <a href="<?= htmlspecialchars($qrApiUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color: var(--success); text-decoration: none; margin-right: 15px;" title="Vytisknout QR kód k nalepení">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">qr_code_2</span> Zobrazit QR kód
                                </a>
                                
                                <a href="index.php?page=asset_edit&id=<?= $a['id'] ?>" style="color: var(--info); text-decoration: none; margin-right: 15px;" title="Upravit zařízení">
									<span class="material-symbols-outlined" style="vertical-align: middle;">edit</span> Upravit
								</a>

                                <form method="POST" action="index.php?page=asset_delete" style="display: inline; margin: 0;">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <button type="submit" data-confirm="Opravdu chcete zařízení vyřadit? Historie údržby zůstane zachována." style="background: none; border: none; padding: 0; cursor: pointer; font: inherit; color: var(--danger);" title="Vyřadit zařízení">
                                        <span class="material-symbols-outlined" style="vertical-align: middle;">delete</span> Smazat
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
