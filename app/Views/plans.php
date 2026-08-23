<?php if (!defined('APP_ROOT')) exit; ?>

<!-- Formulář pro vytvoření nového propojení -->
<div class="card" style="border-top: 4px solid var(--primary);">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">add_task</span> Přidat nový plán údržby</h3>
    <p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">Zde určujete, který formulář se má u kterého zařízení pravidelně vyplňovat.</p>
    
    <form method="POST" action="index.php?page=plan_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Zařízení / Stroj *</label>
            <select name="asset_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
                <option value="">-- Vyberte zařízení --</option>
                <?php foreach ($assets as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Šablona formuláře *</label>
            <select name="form_template_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
                <option value="">-- Vyberte formulář --</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="width: 130px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Lhůta (dny) *</label>
            <input type="number" name="period_days" required min="1" placeholder="Např. 7" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>
        
        <div style="width: 130px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">Varovat (dny) *</label>
            <input type="number" name="warning_days" required min="1" placeholder="Např. 2" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>
        
        <div>
            <button type="submit" class="btn-primary" style="padding: 10px 25px;"><span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit plán</button>
        </div>
    </form>
</div>

<!-- Tabulka s aktivními plány -->
<div class="card">
    <h3 style="margin-top:0;">Aktivní plány údržby</h3>
    
    <?php if (empty($rules)): ?>
        <p style="color: #777; font-style: italic;">Zatím nebyl vytvořen žádný plán. Přidejte první výše.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px; color: #333;">Zařízení</th>
                        <th style="padding: 12px; color: #333;">Přiřazený formulář (Úkon)</th>
                        <th style="padding: 12px; color: #333;">Opakování</th>
                        <th style="padding: 12px; color: #333; text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $r): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; font-weight: bold; color: var(--primary);"><?= htmlspecialchars($r['asset_name']) ?></td>
                            <td style="padding: 12px; color: #555;"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em; color:#888;">description</span> <?= htmlspecialchars($r['template_name']) ?></td>
                            <td style="padding: 12px;">
                                Každých <strong><?= $r['period_days'] ?> dní</strong><br>
                                <span style="font-size: 0.85em; color: #e67e22;">(Varování <?= $r['warning_days'] ?> dny předem)</span>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <a href="index.php?page=plan_delete&id=<?= $r['id'] ?>" onclick="return confirm('Opravdu chcete toto pravidlo smazat?');" style="color: #e74c3c; text-decoration: none;" title="Smazat plán">
                                    <span class="material-symbols-outlined">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
