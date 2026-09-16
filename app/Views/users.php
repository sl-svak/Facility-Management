<?php if (!defined('APP_ROOT')) exit; ?>

<!-- Formulář pro vytvoření nového uživatele -->
<div class="card card-primary-top">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">person_add</span> Přidat nového uživatele</h3>
    
    <?php if (isset($_GET['updated'])): ?>
        <div style="padding: 12px; background: #d4edda; color: #155724; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom: 15px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> 
            Údaje uživatele byly úspěšně aktualizovány.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=user_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Uživatelské jméno *</label>
            <input type="text" name="username" required placeholder="např. karel" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>
        
        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Jméno *</label>
            <input type="text" name="first_name" required placeholder="Karel" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Příjmení *</label>
            <input type="text" name="last_name" required placeholder="Novák" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Heslo *</label>
            <input type="password" name="password" required placeholder="Zvolte heslo" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Organizační úseky</label>
            <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; max-height: 125px; overflow-y: auto;">
                <?php foreach ($departments ?? [] as $dep): ?>
                    <label style="display: flex; align-items: center; margin-bottom: 6px; cursor: pointer; font-weight: normal; color: var(--text-main);">
                        <input type="checkbox" name="departments[]" value="<?= $dep['id'] ?>" style="margin-right: 8px;"> <?= htmlspecialchars($dep['name']) ?>
                    </label>
                <?php endforeach; ?>
                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 0.85em; color: var(--text-muted);">
                    Nevyberete-li nic = Vidí celou firmu
                </div>
            </div>
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Role v systému *</label>
            <select name="role" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                <option value="technician">Technik (Revize a závady)</option>
                <option value="manager">Manager (Správa strojů)</option>
                <option value="admin">Administrátor (Plný přístup)</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px;"><span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Vytvořit</button>
        </div>
    </form>
</div>

<!-- Tabulka existujících uživatelů -->
<div class="card">
    <h3 style="margin-top:0;">Seznam uživatelů v systému</h3>
    
    <?php if (empty($users)): ?>
        <p style="color: var(--text-muted); font-style: italic;">Zatím nebyli vytvořeni žádní další uživatelé.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uživatelské jméno</th>
                        <th>Celé jméno</th>
                        <th>Úsek</th>
                        <th>Role</th>
                        <th>Poslední přihlášení</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td>
                                <?php if (!empty($u['department_name'])): ?>
                                    <span style="background: rgba(41, 128, 185, 0.1); color: var(--info); padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                        <?= htmlspecialchars($u['department_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85em; font-style: italic;">Celo-firemní</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="background: rgba(192, 57, 43, 0.1); color: var(--danger); padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Administrátor</span>
                                <?php elseif ($u['role'] === 'manager'): ?>
                                    <span style="background: rgba(211, 84, 0, 0.1); color: var(--warning); padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Manager</span>
                                <?php else: ?>
                                    <span style="background: rgba(149, 165, 166, 0.1); color: var(--text-muted); padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Technik</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: bold; color: var(--text-muted);">
                                <?= !empty($u['last_login_at']) ? date('d.m.Y H:i', strtotime($u['last_login_at'])) : '<span style="font-style: italic;">Nikdy</span>' ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="index.php?page=user_edit&id=<?= $u['id'] ?>" style="color: var(--info); text-decoration: none; margin-right: 15px;" title="Upravit uživatele">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">edit</span> Upravit
                                </a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="index.php?page=user_delete&id=<?= $u['id'] ?>" onclick="return confirm('Opravdu chcete tohoto uživatele smazat?');" style="color: var(--danger); text-decoration: none;" title="Smazat uživatele">
                                        <span class="material-symbols-outlined" style="vertical-align: middle;">delete</span> Smazat
                                    </a>
                                <?php else: ?>
                                    <span style="color: #aaa; font-style: italic; font-size: 0.85em;">(Přihlášen)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
