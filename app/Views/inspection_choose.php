<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <h3 style="margin-top:0;">Zařízení: <?= htmlspecialchars($asset['name']) ?></h3>
    <p style="color: var(--text-muted);">K tomuto zařízení je v plánu přiřazeno více úkonů. Zvolte, jaký formulář chcete nyní vyplnit:</p>
    
    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
        <?php foreach ($forms as $f): ?>
            <a href="index.php?page=inspection_fill&asset_id=<?= $asset['id'] ?>&form_id=<?= $f['id'] ?>" class="btn-primary" style="text-align: left; padding: 15px; display: block; font-size: 1.1em;">
                <span class="material-symbols-outlined" style="vertical-align: middle; float: right;">arrow_forward_ios</span>
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 10px;">description</span>
                <?= htmlspecialchars($f['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
