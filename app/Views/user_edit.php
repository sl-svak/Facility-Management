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
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Uživatelské jméno</label>
            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: rgba(0,0,0,0.05); color: var(--text-main); box-sizing: border-box;">
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Jméno *</label>
                <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
            </div>
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Příjmení *</label>
                <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">E-mail</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Organizační úseky</label>
                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; max-height: 125px; overflow-y: auto;">
                    <?php foreach ($departments as $dep): ?>
                        <?php $isChecked = in_array($dep['id'], $user['departments'] ?? []) ? 'checked' : ''; ?>
                        <label style="display: flex; align-items: center; margin-bottom: 6px; cursor: pointer; font-weight: normal; color: var(--text-main);">
                            <input type="checkbox" name="departments[]" value="<?= $dep['id'] ?>" <?= $isChecked ?> style="margin-right: 8px;"> <?= htmlspecialchars($dep['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 0.85em; color: var(--text-muted);">
                        Nevyberete-li nic = Vidí celou firmu
                    </div>
                </div>
            </div>
            
            <div style="flex: 1;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Role v systému *</label>
                <select name="role" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                    <option value="technician" <?= $user['role'] === 'technician' ? 'selected' : '' ?>>Technik</option>
                    <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrátor</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom: 25px; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 6px; border: 1px dashed var(--border-color);">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Nové heslo</label>
            <input type="password" name="password" placeholder="Vyplňte pouze pokud chcete heslo změnit" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05em;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit změny
        </button>
    </form>
</div>
