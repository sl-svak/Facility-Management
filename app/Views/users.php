<?php if (!defined('APP_ROOT')) exit; ?>

<!-- Formulář pro vytvoření nového uživatele -->
<div class="card" style="border-top: 4px solid var(--primary); background: #fdfbf7;">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">person_add</span> Přidat nového uživatele</h3>
    
    <?php if (isset($_GET['updated'])): ?>
        <div style="padding: 12px; background: #d4edda; color: #155724; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom: 15px;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> 
            Údaje uživatele byly úspěšně aktualizovány.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=user_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Uživatelské jméno *</label>
            <input type="text" name="username" required placeholder="např. karel" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>
        
        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Jméno *</label>
            <input type="text" name="first_name" required placeholder="Karel" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Příjmení *</label>
            <input type="text" name="last_name" required placeholder="Novák" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">E-mail</label>
            <input type="email" name="email" placeholder="karel@firma.cz" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Heslo *</label>
            <input type="password" name="password" required placeholder="Zvolte heslo" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Role v systému *</label>
            <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
                <option value="technician">Technik (Vyplňování revizí a závad)</option>
                <option value="dispatcher">Dispečer (Správa strojů, šablon a plánů)</option>
                <option value="admin">Administrátor (Plná správa systému)</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px;"><span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Vytvořit účet</button>
        </div>
    </form>
</div>

<!-- Tabulka existujících uživatelů -->
<div class="card">
    <h3 style="margin-top:0;">Seznam uživatelů v systému</h3>
    
    <?php if (empty($users)): ?>
        <p style="color: #777; font-style: italic;">Zatím nebyli vytvořeni žádní další uživatelé.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px; color: #333;">ID</th>
                        <th style="padding: 12px; color: #333;">Uživatelské jméno</th>
                        <th style="padding: 12px; color: #333;">Celé jméno</th>
                        <th style="padding: 12px; color: #333;">E-mail</th>
                        <th style="padding: 12px; color: #333;">Role</th>
                        <th style="padding: 12px; color: #333;">Vytvořeno</th>
                        <th style="padding: 12px; color: #333; text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; color: #666;">#<?= $u['id'] ?></td>
                            <td style="padding: 12px; font-weight: bold; color: var(--primary);"><?= htmlspecialchars($u['username']) ?></td>
                            <td style="padding: 12px; color: #333;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td style="padding: 12px; color: #555;"><?= htmlspecialchars($u['email'] ?? '---') ?></td>
                            <td style="padding: 12px;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="background: #fde8e4; color: #c0392b; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Administrátor</span>
                                <?php elseif ($u['role'] === 'dispatcher'): ?>
                                    <span style="background: #fef5e7; color: #d35400; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Dispečer</span>
                                <?php else: ?>
                                    <span style="background: #eaeded; color: #5d6d7e; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">Technik</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; color: #666;"><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                            <td style="padding: 12px; text-align: right; white-space: nowrap;">
                                <a href="index.php?page=user_edit&id=<?= $u['id'] ?>" style="color: #2980b9; text-decoration: none; margin-right: 15px;" title="Upravit uživatele">
                                    <span class="material-symbols-outlined" style="vertical-align: middle;">edit</span> Upravit
                                </a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="index.php?page=user_delete&id=<?= $u['id'] ?>" onclick="return confirm('Opravdu chcete tohoto uživatele smazat?');" style="color: #e74c3c; text-decoration: none;" title="Smazat uživatele">
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
