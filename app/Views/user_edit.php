<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">
            <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">manage_accounts</span> 
            Úprava uživatele: <?= htmlspecialchars($user['username']) ?>
        </h3>
        <a href="index.php?page=users" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
        </a>
    </div>

    <form method="POST" action="index.php?page=user_update">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Uživatelské jméno</label>
            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #eee; box-sizing: border-box;">
            <small style="color: #888;">Uživatelské jméno slouží jako identifikátor přihlášení a nelze měnit.</small>
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Jméno *</label>
                <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Příjmení *</label>
                <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">E-mail</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="karel@firma.cz" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Role v systému *</label>
            <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
                <option value="technician" <?= $user['role'] === 'technician' ? 'selected' : '' ?>>Technik (Vyplňování revizí a závad)</option>
                <option value="dispatcher" <?= $user['role'] === 'dispatcher' ? 'selected' : '' ?>>Dispečer (Správa strojů, šablon a plánů)</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrátor (Plná správa systému)</option>
            </select>
        </div>

        <div style="margin-bottom: 25px; background: #fafafa; padding: 15px; border-radius: 6px; border: 1px dashed #ccc;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Nové heslo</label>
            <input type="password" name="password" placeholder="Vyplňte pouze pokud chcete heslo změnit" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            <small style="color: #777;">Pokud ponecháte pole prázdné, zůstane původní heslo.</small>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05em;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit změny
        </button>
    </form>
</div>
