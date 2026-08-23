<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto;">
    <h3 style="margin-top:0;">
        <span class="material-symbols-outlined" style="vertical-align: middle;">settings</span> Globální nastavení systému
    </h3>
    
    <?php if (isset($_GET['success'])): ?>
        <div style="padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom: 20px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> 
            <strong>Uloženo!</strong> Nastavení bylo úspěšně aktualizováno.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=settings_save" enctype="multipart/form-data">
        
        <!-- DYNAMICKÉ POLE: NÁZEV APLIKACE -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Název aplikace (zobrazí se v hlavičce a menu) *</label>
            <input type="text" name="settings[app_name]" value="<?= htmlspecialchars($settings['app_name'] ?? 'CMMS') ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; font-size: 1.1em;">
        </div>

        <!-- DYNAMICKÉ POLE: REŽIM PRACOVNÍHO TÝDNE PRO PLÁNOVÁNÍ ÚDRŽBY -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Režim plánování údržby *</label>
            <select name="settings[workweek_days]" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; font-size: 1em; background: #fff;">
                <option value="5" <?= (!isset($settings['workweek_days']) || $settings['workweek_days'] == 5) ? 'selected' : '' ?>>
                    5denní provoz (Po - Pá, víkendy se přeskakují)
                </option>
                <option value="7" <?= (isset($settings['workweek_days']) && $settings['workweek_days'] == 7) ? 'selected' : '' ?>>
                    7denní provoz (Nepřetržitě včetně víkendů)
                </option>
            </select>
            <div style="font-size: 0.85em; color: var(--text-muted); margin-top: 5px;">
                Při 5denním provozu se denní kontroly z pátku automaticky naplánují až na pondělí, aby o víkendu nepropadly.
            </div>
        </div>

        <!-- ODDĚLENÉ POLE PRO SOUBOR: FAVICONA -->
        <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Favicona (Ikonka v záložce prohlížeče)</label>
            
            <?php if (!empty($settings['favicon_path'])): ?>
                <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <img src="<?= htmlspecialchars($settings['favicon_path']) ?>" alt="Favicon" style="max-height: 32px; border-radius: 4px; border: 1px solid var(--border-color); padding: 2px; background: #fff;">
                    <span style="font-size: 0.9em; color: var(--success);">Aktuální ikonka</span>
                </div>
            <?php endif; ?>
            
            <input type="file" name="favicon" accept=".png,.ico,.jpg,.svg" style="width: 100%; padding: 10px; background: #f9f9f9; border: 1px dashed var(--border-color); border-radius: 4px; box-sizing: border-box;">
            <div style="font-size: 0.85em; color: var(--text-muted); margin-top: 5px;">Podporované formáty: PNG, ICO, SVG, JPG. Doporučený rozměr 32x32 nebo 64x64 px.</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1em; padding: 12px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit nastavení
        </button>
    </form>
</div>
