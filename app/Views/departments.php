<?php if (!defined('APP_ROOT')) exit; ?>

<div class="grid-2">
    <!-- Formulář pro vytvoření úseku -->
    <div class="card card-primary-top">
        <h3 style="margin-top: 0; color: var(--primary);">
            <span class="material-symbols-outlined" style="vertical-align: middle;">domain_add</span> Přidat nový úsek
        </h3>
        <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px;">
            Vytvořte organizační jednotky (např. Výroba, Sklad, Kotelna). K těmto úsekům pak budeme přiřazovat jednotlivé stroje a techniky.
        </p>
        
        <form method="POST" action="index.php?page=department_create">
            <!-- OCHRANA CSRF -->
            <?= Security::csrfField() ?>
            
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Název úseku *</label>
            <input type="text" name="name" required placeholder="např. Výroba - linka 1" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-main); margin-bottom: 15px; box-sizing: border-box;">
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Vytvořit úsek
            </button>
        </form>
    </div>

    <!-- Tabulka existujících úseků -->
    <div class="card">
        <h3 style="margin-top: 0;">Seznam existujících úseků</h3>
        
        <?php if (empty($departments)): ?>
            <p style="color: var(--text-muted); font-style: italic;">Zatím nejsou vytvořeny žádné úseky.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Název úseku</th>
                            <th style="text-align: right;">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td style="color: var(--text-muted);">#<?= $d['id'] ?></td>
                                <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($d['name']) ?></td>
                                <td style="text-align: right;">
                                    <a href="index.php?page=department_delete&id=<?= $d['id'] ?>" onclick="return confirm('Opravdu chcete smazat tento úsek? Stroje a uživatelé zařazeni v tomto úseku nebudou smazáni, pouze přejdou do stavu \'Bez úseku\'.');" style="color: var(--danger); text-decoration: none;" title="Smazat úsek">
                                        <span class="material-symbols-outlined" style="vertical-align: middle;">delete</span> Smazat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
