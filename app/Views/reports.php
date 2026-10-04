<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto;">
    <h3 style="margin-top:0; color: var(--text-main);">
        <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--danger);">picture_as_pdf</span> 
        Kniha stroje & Protokoly
    </h3>
    
    <p style="color: var(--text-muted); line-height: 1.5;">
        Zde můžete vygenerovat PDF report pro konkrétní zařízení za vybraný kalendářní rok. 
        Report obsahuje všechny kontroly, zaznamenané hodnoty, fotografie závad, podpisy i případná řešení tiketů.
    </p>

    <!-- Použijeme POST, přesně jak to TCPDF kontrolér očekává -->
    <form method="POST" action="index.php?page=report_generate" target="_blank" style="margin-top: 25px;">
        
        <!-- OCHRANA CSRF -->
        <?= Security::csrfField() ?>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: var(--text-main);">Zařízení / Stroj:</label>
            <select name="asset_id" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1em; background: var(--card-bg); color: var(--text-main);">
                <option value="">-- Vyberte zařízení --</option>
                <?php foreach ($assets as $a): ?>
                    <?php $statusText = $a['operational_status'] === 'stopped' ? ' (Odstaveno)' : ''; ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= $statusText ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: var(--text-main);">Kalendářní rok:</label>
            <select name="year" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1em; background: var(--card-bg); color: var(--text-main);">
                <?php $currentYear = date('Y'); ?>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>>
                        Rok <?= htmlspecialchars($y) ?> <?= ($y == $currentYear) ? '(Aktuální)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-danger btn-block" style="padding: 15px; font-size: 1.1em;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">print</span> Vygenerovat PDF protokol
        </button>
    </form>
</div>
