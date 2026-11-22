<?php if (!defined('APP_ROOT')) exit; ?>
<?php
$templatesMapJson = json_encode($outputTemplatesByAsset ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$prevMonth = ((int)date('n') > 1) ? ((int)date('n') - 1) : 12;
?>

<div class="card card-primary-top card-narrow">
    <h3 class="card-title">
        <span class="material-symbols-outlined" style="color: var(--danger);">picture_as_pdf</span>
        Kniha stroje &amp; Protokoly
    </h3>

    <p class="text-muted" style="line-height: 1.5;">
        Vygenerujte PDF výkaz pro konkrétní zařízení za <strong>vybraný měsíc</strong> (průběžný tisk po uzavření měsíce)
        nebo za <strong>celý kalendářní rok</strong>. Výkaz obsahuje kontroly, zaznamenané hodnoty, fotografie závad, podpisy i řešení tiketů.
    </p>

    <form method="POST" action="index.php?page=report_generate" target="_blank" class="form-stack mt-15">
        <?= Security::csrfField() ?>

        <div class="form-field">
            <label class="form-label" for="report_asset_id">Zařízení / Stroj *</label>
            <select name="asset_id" id="report_asset_id" class="form-control" required data-change="syncReportTemplates">
                <option value="">-- Vyberte zařízení --</option>
                <?php foreach ($assets as $a): ?>
                    <option value="<?= (int)$a['id'] ?>">
                        <?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?><?= (($a['operational_status'] ?? 'running') === 'stopped') ? ' (Odstaveno)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label class="form-label" for="report_year">Kalendářní rok *</label>
                <select name="year" id="report_year" class="form-control" required>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int)$y ?>" <?= ((int)$y === (int)date('Y')) ? 'selected' : '' ?>>
                            Rok <?= (int)$y ?><?= ((int)$y === (int)date('Y')) ? ' (Aktuální)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label" for="report_period_mode">Rozsah období</label>
                <select name="period_mode" id="report_period_mode" class="form-control" data-change="syncReportPeriod">
                    <option value="month" selected>Konkrétní měsíc</option>
                    <option value="year">Celý kalendářní rok</option>
                </select>
            </div>
        </div>

        <div class="form-field mt-15" id="report_month_group">
            <label class="form-label" for="report_month">Měsíc</label>
            <select name="month" id="report_month" class="form-control">
                <?php foreach ($months as $mNum => $mName): ?>
                    <option value="<?= (int)$mNum ?>" <?= ((int)$mNum === $prevMonth) ? 'selected' : '' ?>>
                        <?= sprintf('%02d', (int)$mNum) ?> – <?= htmlspecialchars((string)$mName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($outputTemplatesEnabled)): ?>
            <div class="form-field">
                <label class="form-label" for="report_output_mode">Typ výstupního formuláře</label>
                <select name="output_mode" id="report_output_mode" class="form-control" data-change="syncReportTemplates">
                    <option value="complete">Kompletní formulář (všechny evidované parametry)</option>
                    <option value="custom">Vlastní výstupní šablona zařízení</option>
                </select>
            </div>

            <div class="form-field" id="report_custom_template_group" hidden>
                <label class="form-label" for="report_output_template_id">Výstupní šablona</label>
                <select name="output_template_id" id="report_output_template_id" class="form-control"></select>
                <span class="form-hint" id="report_no_tpl_hint" hidden>
                    Pro toto zařízení zatím není vytvořena žádná výstupní šablona.
                    <a href="index.php?page=output_templates">Vytvořit šablonu</a>
                </span>
            </div>
        <?php else: ?>
            <input type="hidden" name="output_mode" value="complete">
        <?php endif; ?>

        <button type="submit" class="btn btn-danger btn-block" style="padding: 15px; font-size: 1.1em;">
            <span class="material-symbols-outlined">print</span> Vygenerovat PDF protokol
        </button>
    </form>
</div>

<script type="application/json" id="cmms-output-templates-data"><?= $templatesMapJson ?></script>
<script src="assets/js/page-reports.js?v=<?= filemtime(APP_ROOT . '/assets/js/page-reports.js') ?>"></script>
