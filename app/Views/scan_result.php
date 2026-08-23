<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top">
    <?php if (empty($asset)): ?>
        <div style="padding: 25px; background: #fdeeed; color: #c0392b; border-radius: 6px; border: 1px solid #f5c6cb; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 3em; margin-bottom: 10px;">qr_code_2</span><br>
            <strong>Naskenovaný kód neodpovídá žádnému zařízení.</strong><br>
            Zkontrolujte správnost QR kódu nebo zaregistrujte zařízení v sekci <em>Správa zařízení</em>.
            <div style="margin-top: 15px;">
                <a href="index.php?page=assets" class="btn btn-primary">Přejít na seznam zařízení</a>
            </div>
        </div>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
            <div>
                <h3 style="margin: 0; color: #2c3e50;">
                    <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--primary);">precision_manufacturing</span> 
                    <?= htmlspecialchars($asset['name']) ?>
                </h3>
                <span style="color: #777; font-size: 0.9em;"><?= htmlspecialchars($asset['description'] ?? 'Bez popisu') ?></span>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="index.php?page=asset_stats&id=<?= $asset['id'] ?>" class="btn" style="background: #e67e22; text-decoration: none;">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">monitoring</span> Grafy & Analytika
                </a>
                <a href="index.php?page=assets" class="btn" style="background: #95a5a6; text-decoration: none;">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět
                </a>
            </div>
        </div>

        <?php if (!empty($openTickets)): ?>
            <div style="margin-bottom: 25px; padding: 15px; background: #fdeeed; border-left: 4px solid #e74c3c; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #c0392b;">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">warning</span> 
                    Otevřené závady na tomto zařízení (<?= count($openTickets) ?>)
                </h4>
                <ul style="margin: 0; padding-left: 20px; color: #555;">
                    <?php foreach ($openTickets as $t): ?>
                        <li style="margin-bottom: 5px;">
                            <strong><?= htmlspecialchars($t['title']) ?></strong> (z <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?>)
                            - <a href="index.php?page=ticket_detail&id=<?= $t['id'] ?>" style="color: #c0392b; font-weight: bold;">Vyřešit</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h4 style="color: #2c3e50; margin-bottom: 10px;">Dostupné kontrolní formuláře a revize</h4>

        <?php if (empty($forms)): ?>
            <div style="padding: 25px; background: #e8f4f8; color: #2980b9; border-radius: 6px; border: 1px solid #bce8f1; text-align: center;">
                <span class="material-symbols-outlined" style="font-size: 3em; margin-bottom: 10px;">assignment_late</span><br>
                <strong>K tomuto zařízení zatím není přiřazen žádný kontrolní formulář.</strong><br>
                Přiřazení šablony a nastavení periody provádí dispečer v sekci <em>Plánování údržby</em>.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($forms as $f): ?>
                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 15px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
                        <div>
                            <h4 style="margin: 0 0 5px 0; color: #2c3e50;"><?= htmlspecialchars($f['title']) ?></h4>
                            <div style="font-size: 0.85em; color: #777; margin-bottom: 15px;">
                                Perioda: <strong>každých <?= (int)$f['period_days'] ?> dní</strong>
                            </div>
                        </div>
                        <a href="index.php?page=inspection_fill&asset_id=<?= $asset['id'] ?>&form_id=<?= $f['id'] ?>" class="btn btn-primary" style="text-align: center; text-decoration: none; display: block; padding: 10px;">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">edit_note</span> Vyplnit kontrolu
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
