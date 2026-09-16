<?php if (!defined('APP_ROOT')) exit; ?>

<div style="max-width: 600px; margin: 0 auto;">
    
    <?php if ($forced): ?>
        <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffeeba; text-align: center;">
            <strong>Z bezpečnostních důvodů si prosím nastavte nové osobní heslo.</strong><br>
            <span style="font-size: 0.9em;">Až do změny hesla nelze systém používat.</span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">error</span> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- 1. KARTA: NASTAVENÍ VZHLEDU -->
    <?php if (!$forced): ?>
    <div class="card card-primary-top">
        <h3 style="margin-top: 0; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">palette</span> Nastavení vzhledu a chování
        </h3>
        
        <form method="POST" action="index.php?page=profile_preferences">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Barevný motiv</label>
                <select name="theme" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                    <option value="auto" <?= ($user['theme'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automaticky (podle systému)</option>
                    <option value="light" <?= ($user['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Vynutit Světlý režim</option>
                    <option value="dark" <?= ($user['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Vynutit Tmavý režim</option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Velikost písma (na mobilním telefonu)</label>
                <select name="font_size" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                    <option value="normal" <?= ($user['font_size'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Standardní</option>
                    <option value="large" <?= ($user['font_size'] ?? '') === 'large' ? 'selected' : '' ?>>Zvětšené (pro lepší čitelnost v terénu)</option>
                </select>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Čtečka QR kódů v levém menu</label>
                <select name="qr_mode" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                    <option value="auto" <?= ($user['qr_mode'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automaticky (Zobrazit na mobilu, skrýt na PC)</option>
                    <option value="show" <?= ($user['qr_mode'] ?? '') === 'show' ? 'selected' : '' ?>>Vždy zobrazit zástupce</option>
                    <option value="hide" <?= ($user['qr_mode'] ?? '') === 'hide' ? 'selected' : '' ?>>Vždy skrýt (skenuji z adresního řádku)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit osobní nastavení
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- 2. KARTA: ZMĚNA HESLA -->
    <div class="card card-primary-top">
        <h3 style="margin-top: 0; color: var(--primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">lock</span> Změna hesla
        </h3>
        <form method="POST" action="index.php?page=profile_save">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Současné heslo</label>
                <input type="password" name="current_password" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Nové heslo</label>
                <input type="password" name="new_password" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
                <small style="color: var(--text-muted);">Minimální délka: 6 znaků</small>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Potvrzení nového hesla</label>
                <input type="password" name="confirm_password" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <span class="material-symbols-outlined" style="vertical-align: middle;">key</span> Uložit nové heslo
            </button>
            
            <?php if (!$forced && !$success): ?>
                <a href="index.php?page=dashboard" class="btn btn-block" style="margin-top: 10px; background: #95a5a6; color: white;">Zpět na Dashboard</a>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <a href="index.php?page=dashboard" class="btn btn-block" style="margin-top: 10px; background: #27ae60; color: white;">Pokračovat do systému</a>
            <?php endif; ?>
        </form>
    </div>
</div>
