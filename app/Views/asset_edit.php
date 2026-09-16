<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">
            <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">edit_square</span> 
            Úprava zařízení: <?= htmlspecialchars($asset['name']) ?>
        </h3>
        <a href="index.php?page=assets" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
        </a>
    </div>

    <form method="POST" action="index.php?page=asset_update">
        <input type="hidden" name="id" value="<?= $asset['id'] ?>">

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Název zařízení *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($asset['name']) ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Organizační úsek</label>
            <select name="department_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
                <option value="">-- Bez úseku / Celo-firemní --</option>
                <?php foreach ($departments as $dep): ?>
                    <option value="<?= $dep['id'] ?>" <?= ($asset['department_id'] == $dep['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dep['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: var(--text-muted);">Určuje, pod který úsek stroj spadá. Lze kdykoliv změnit.</small>
        </div>

        <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Popis / Umístění</label>
            <input type="text" name="description" value="<?= htmlspecialchars($asset['description'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05em;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit změny
        </button>
    </form>
</div>
