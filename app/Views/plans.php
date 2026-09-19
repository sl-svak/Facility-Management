<?php if (!defined('APP_ROOT')) exit; ?>

<!-- Formulář pro vytvoření nového propojení -->
<div class="card" style="border-top: 4px solid var(--primary);">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">add_task</span> Přidat nový plán údržby</h3>
    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">Zde určujete, který formulář se má u kterého zařízení pravidelně vyplňovat.</p>
    
    <form method="POST" action="index.php?page=plan_create" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <!-- OCHRANA CSRF -->
        <?= Security::csrfField() ?>

        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-main);">Zařízení / Stroj *</label>
            <select name="asset_id" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                <option value="">-- Vyberte zařízení --</option>
                <?php foreach ($assets as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-main);">Šablona formuláře *</label>
            <select name="form_template_id" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-main);">
                <option value="">-- Vyberte formulář --</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="width: 130px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-main);">Lhůta (dny) *</label>
            <input type="number" name="period_days" required min="1" placeholder="Např. 7" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>
        
        <div style="width: 130px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-main);">Varovat (dny) *</label>
            <input type="number" name="warning_days" required min="1" placeholder="Např. 2" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--card-bg); color: var(--text-main);">
        </div>
        
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px;"><span class="material-symbols-outlined" style="vertical-align: middle;">save</span> Uložit plán</button>
        </div>
    </form>
</div>

<!-- Tabulka s aktivními plány -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h3 style="margin-top:0; color: var(--text-main);">Aktivní plány údržby</h3>
        <button type="button" class="btn" style="background: var(--primary); color: #fff;" onclick="document.getElementById('historyModal').style.display='flex'">
            <span class="material-symbols-outlined" style="vertical-align: middle;">history</span> Historie změn lhůt
        </button>
    </div>
    
    <?php if (empty($rules)): ?>
        <p style="color: var(--text-muted); font-style: italic; margin-top: 20px;">Zatím nebyl vytvořen žádný plán. Přidejte první výše.</p>
    <?php else: ?>
        <div class="table-responsive" style="margin-top: 15px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Zařízení</th>
                        <th>Přiřazený formulář (Úkon)</th>
                        <th>Opakování</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $r): ?>
                        <tr>
                            <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($r['asset_name']) ?></td>
                            <td style="color: var(--text-main);"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em; color: var(--text-muted);">description</span> <?= htmlspecialchars($r['template_name']) ?></td>
                            <td>
                                <span style="color: var(--text-main);">Každých <strong><?= $r['period_days'] ?> dní</strong></span><br>
                                <span style="font-size: 0.85em; color: var(--warning);">(Varování <?= $r['warning_days'] ?> dny předem)</span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-warning" style="padding: 6px 12px; font-size: 0.9em; margin-right: 5px;" 
                                    onclick="openEditModal(<?= $r['id'] ?>, <?= $r['period_days'] ?>, <?= $r['warning_days'] ?>, '<?= htmlspecialchars(addslashes($r['asset_name'])) ?>')">
                                    <span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: middle;">edit</span> Upravit
                                </button>
                                
                                <a href="index.php?page=plan_delete&id=<?= $r['id'] ?>" onclick="return confirm('Opravdu chcete tento plán smazat? Přestanou se generovat kontroly.');" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.9em; text-decoration: none;">
                                    <span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: middle;">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL: Úprava lhůty -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 100%; max-width: 500px; margin: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <h3 style="margin-top:0; color: var(--primary);">Změna lhůty plánu</h3>
        <p id="editAssetName" style="font-weight: bold; color: var(--text-main); margin-bottom: 20px; font-size: 1.1em;"></p>
        
        <form method="POST" action="index.php?page=plan_update">
            <!-- OCHRANA CSRF -->
            <?= Security::csrfField() ?>

            <!-- Tady chyběla patřičná ID atributy pro JavaScript! -->
            <input type="hidden" name="id" id="edit_id">
            
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Nová lhůta (dny) *</label>
                    <input type="number" name="period_days" id="edit_period" required min="1" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--background); color: var(--text-main);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--text-muted);">Varovat předem (dny) *</label>
                    <input type="number" name="warning_days" id="edit_warning" required min="1" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background: var(--background); color: var(--text-main);">
                </div>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--danger);">
                    Důvod změny (Povinné pro audit) *
                </label>
                <textarea name="reason" required placeholder="Např.: Zkráceno kvůli časté poruchovosti stroje..." style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; min-height: 80px; background: var(--background); color: var(--text-main);"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn" style="background: var(--text-muted); color: #fff;">Zrušit</button>
                <button type="submit" class="btn btn-success">Uložit změnu a logovat</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Historie změn -->
<div id="historyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 100%; max-width: 800px; margin: 20px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
            <h3 style="margin: 0; color: var(--primary);">Auditní deník: Změny lhůt</h3>
            <button type="button" onclick="document.getElementById('historyModal').style.display='none'" style="background:none; border:none; font-size:1.5em; cursor:pointer; color: var(--text-muted);">&times;</button>
        </div>
        
        <?php if (empty($history)): ?>
            <p style="color: var(--text-muted); font-style: italic;">Zatím nebyly provedeny žádné změny v plánech.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($history as $h): ?>
                    <li style="padding: 15px; border-bottom: 1px solid var(--border-color); background: var(--background); margin-bottom: 10px; border-left: 4px solid var(--info); border-radius: 0 4px 4px 0;">
                        <div style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 5px;">
                            <strong><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></strong> | Změnil: <span style="color: var(--text-main);"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></span>
                        </div>
                        <div style="font-weight: bold; margin-bottom: 8px; color: var(--primary);">
                            <?= htmlspecialchars($h['asset_name']) ?> <span style="color: var(--text-main); font-weight: normal;">(<?= htmlspecialchars($h['template_name']) ?>)</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <span style="color: var(--text-muted);">Změna lhůty:</span> <del style="color: var(--danger);"><?= $h['old_period'] ?> dní</del> 
                            <span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle; color: var(--text-muted); margin: 0 5px;">arrow_forward</span> 
                            <strong style="color: var(--success);"><?= $h['new_period'] ?> dní</strong>
                        </div>
                        <div style="font-style: italic; color: var(--text-main); background: rgba(0,0,0,0.05); padding: 10px; border-radius: 4px; border-left: 2px solid var(--border-color);">
                            "<?= htmlspecialchars($h['reason']) ?>"
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
