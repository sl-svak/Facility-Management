<?php
$templatesMapJson = json_encode($outputTemplatesByAsset ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>

<div class="card">
    <h2>Export výkazů do PDF (měsíční i celoroční)</h2>
    <p style="color: #555; margin-bottom: 18px;">
        Vyberte konkrétní zařízení, rok a zvolte buď <strong>konkrétní měsíc</strong> (pro průběžný tisk po uzavření měsíce),
        nebo <strong>celý kalendářní rok</strong>. Můžete použít kompletní výpis nebo vlastní výstupní šablonu vázanou na zařízení.
    </p>

    <form method="POST" action="index.php?page=report_generate" target="_blank" id="reportGenerateForm">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label for="report_asset_id">Zařízení</label>
            <select name="asset_id" id="report_asset_id" required>
                <?php foreach ($assets as $a): ?>
                    <option value="<?= (int)$a['id'] ?>">
                        <?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?= (($a['operational_status'] ?? 'running') === 'stopped') ? ' [Odstaveno]' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="report_year">Rok</label>
            <select name="year" id="report_year" required>
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int)$y ?>" <?= ((int)$y === (int)date('Y')) ? 'selected' : '' ?>><?= (int)$y ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="report_period_mode">Rozsah období</label>
            <select name="period_mode" id="report_period_mode">
                <option value="month" selected>Konkrétní měsíc (průběžný měsíční výkaz)</option>
                <option value="year">Celý kalendářní rok</option>
            </select>
        </div>

        <div class="form-group" id="report_month_group">
            <label for="report_month">Měsíc</label>
            <select name="month" id="report_month">
                <?php
                $prevMonth = ((int)date('n') > 1) ? ((int)date('n') - 1) : 12;
                foreach ($months as $mNum => $mName):
                ?>
                    <option value="<?= (int)$mNum ?>" <?= ((int)$mNum === $prevMonth) ? 'selected' : '' ?>>
                        <?= sprintf('%02d', (int)$mNum) ?> – <?= htmlspecialchars((string)$mName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($outputTemplatesEnabled)): ?>
            <div class="form-group">
                <label for="report_output_mode">Typ výstupního formuláře</label>
                <select name="output_mode" id="report_output_mode">
                    <option value="complete">Kompletní formulář (všechny evidované parametry jako dosud)</option>
                    <option value="custom">Vlastní výstupní šablona zařízení</option>
                </select>
            </div>

            <div class="form-group" id="report_custom_template_group" style="display: none;">
                <label for="report_output_template_id">Vybraná šablona pro zařízení</label>
                <select name="output_template_id" id="report_output_template_id"></select>
                <small id="report_no_tpl_hint" style="display: none; color: #b9770e;">
                    Pro toto zařízení zatím není vytvořena žádná vlastní výstupní šablona.
                    <a href="index.php?page=output_templates">Vytvořit šablonu</a>
                </small>
            </div>
        <?php else: ?>
            <input type="hidden" name="output_mode" value="complete">
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Vygenerovat PDF</button>
    </form>
</div>

<script id="cmms-output-templates-data" type="application/json"><?= $templatesMapJson ?></script>
<script src="assets/js/page-reports.js"></script>
