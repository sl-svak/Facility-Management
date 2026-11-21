<?php
$appName = htmlspecialchars((string)($settings['app_name'] ?? 'CMMS Cosmonde'), ENT_QUOTES, 'UTF-8');
$appFont = (string)($settings['app_font'] ?? '');
$forceHttps = (($settings['force_https'] ?? '0') === '1');
$shiftStartHour = (int)($settings['shift_start_hour'] ?? 0);
$workweekDays = (int)($settings['workweek_days'] ?? 5);

$modInspections = (($settings['module_inspections'] ?? '1') === '1');
$modExternalAudits = (($settings['module_external_audits'] ?? '1') === '1');
$modOutputTemplates = (($settings['module_output_templates'] ?? '1') === '1');
$modTickets = (($settings['module_tickets'] ?? '1') === '1');
?>

<div class="card">
    <h2>Globální nastavení systému (v2.0)</h2>

    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success" style="padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">
            Nastavení bylo úspěšně uloženo.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=settings_save" enctype="multipart/form-data">
        <?= Security::csrfField() ?>

        <h3>Základní a provozní nastavení</h3>

        <div class="form-group">
            <label for="app_name">Název aplikace</label>
            <input type="text" id="app_name" name="settings[app_name]" value="<?= $appName ?>" required>
        </div>

        <div class="form-group">
            <label for="app_font">Font aplikace (volitelně)</label>
            <input type="text" id="app_font" name="settings[app_font]" value="<?= htmlspecialchars($appFont, ENT_QUOTES, 'UTF-8') ?>" placeholder="např. Inter, Roboto">
        </div>

        <div class="form-group">
            <label for="shift_start_hour">Začátek provozního dne / směny (hodina 0–23)</label>
            <input type="number" id="shift_start_hour" name="settings[shift_start_hour]" min="0" max="23" value="<?= $shiftStartHour ?>">
        </div>

        <div class="form-group">
            <label for="workweek_days">Pracovní dny v týdnu</label>
            <select id="workweek_days" name="settings[workweek_days]">
                <option value="5" <?= $workweekDays === 5 ? 'selected' : '' ?>>5denní provoz (víkendové termíny se přesouvají na pondělí)</option>
                <option value="7" <?= $workweekDays === 7 ? 'selected' : '' ?>>7denní nepřetržitý provoz</option>
            </select>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="settings[force_https]" value="1" <?= $forceHttps ? 'checked' : '' ?>>
                Vynutit přesměrování na HTTPS
            </label>
        </div>

        <div class="form-group">
            <label for="favicon">Vlastní ikona prohlížeče (Favicon – PNG, JPG, ICO, max. 2 MB)</label>
            <input type="file" id="favicon" name="favicon" accept=".png,.jpg,.jpeg,.ico">
            <?php if (!empty($settings['favicon_path'])): ?>
                <div style="margin-top: 8px;">
                    <img src="<?= htmlspecialchars((string)$settings['favicon_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Aktuální favicon" style="width: 32px; height: 32px; vertical-align: middle;">
                    <small>Aktuální ikona</small>
                </div>
            <?php endif; ?>
        </div>

        <hr style="margin: 25px 0;">

        <h3>Aktivní moduly (přizpůsobení pro malé projekty)</h3>
        <p style="color: #555; margin-bottom: 15px;">
            Vypnutím modulu skryjete jeho funkce z menu, dashboardu i pracovních stránek, aby rozhraní zůstalo přehledné.
            <strong>Existující data v databázi se nesmažou</strong> a po opětovném zapnutí modulu zůstanou k dispozici.
        </p>

        <div class="form-group">
            <label>
                <input type="checkbox" name="settings[module_inspections]" value="1" <?= $modInspections ? 'checked' : '' ?>>
                <strong>Kontroly a plány údržby</strong> – pravidelné kontroly strojů, kontrolní formuláře a semafor termínů
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="settings[module_external_audits]" value="1" <?= $modExternalAudits ? 'checked' : '' ?>>
                <strong>Externí revize a dodavatelé</strong> – revize externími firmami, termíny a PDF protokoly
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="settings[module_output_templates]" value="1" <?= $modOutputTemplates ? 'checked' : '' ?>>
                <strong>Vlastní šablony výstupních formulářů</strong> – uživatelsky zvolené parametry pro oficiální tiskové sestavy
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="settings[module_tickets]" value="1" <?= $modTickets ? 'checked' : '' ?>>
                <strong>Správa závad (tikety)</strong> – sledování a uzavírání závad zjištěných při kontrolách
            </label>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Uložit nastavení</button>
    </form>
</div>
