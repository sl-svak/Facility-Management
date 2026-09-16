<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">library_books</span> Kompletní historie záznamů</h3>
    
    <?php if (empty($inspections)): ?>
        <p style="color: #777; font-style: italic;">Zatím nebyla v systému provedena žádná kontrola.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px; color: #333;">Datum a čas</th>
                        <th style="padding: 12px; color: #333;">Zařízení</th>
                        <th style="padding: 12px; color: #333;">Úkon</th>
                        <th style="padding: 12px; color: #333;">Technik</th>
                        <th style="padding: 12px; color: #333;">Stav</th>
                        <th style="padding: 12px; color: #333; text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inspections as $insp): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; color: #666;"><?= date('d.m.Y H:i', strtotime($insp['created_at'])) ?></td>
                            <td style="padding: 12px; font-weight: bold; color: var(--primary);"><?= htmlspecialchars($insp['asset_name']) ?></td>
                            <td style="padding: 12px; color: #555;"><?= htmlspecialchars($insp['template_name']) ?></td>
                            <td style="padding: 12px; color: #555;"><?= htmlspecialchars($insp['first_name'] . ' ' . $insp['last_name']) ?></td>
                            <td style="padding: 12px;">
                                <?php if ($insp['status'] === 'OK'): ?>
                                    <span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">OK</span>
                                <?php else: ?>
                                    <span style="background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">ZÁVADA</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <a href="index.php?page=inspection_detail&id=<?= $insp['id'] ?>" class="btn-primary" style="padding: 5px 15px; font-size: 0.9em; text-decoration: none;">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
