<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card">
    <h3 style="margin-top:0;">Seznam evidovaných závad</h3>
    <p style="color: #666; margin-bottom: 20px;">Zde se automaticky propisují problémy zjištěné během inspekcí. Nevyřešené závady mají nejvyšší prioritu.</p>
    
    <?php if (empty($tickets)): ?>
        <p style="color: #777; font-style: italic;">Aktuálně nejsou evidovány žádné závady. Skvělá práce!</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px; color: #333; width: 15%;">Datum nahlášení</th>
                        <th style="padding: 12px; color: #333; width: 20%;">Zařízení</th>
                        <th style="padding: 12px; color: #333; width: 40%;">Popis a řešení</th>
                        <th style="padding: 12px; color: #333; width: 10%;">Stav</th>
                        <th style="padding: 12px; color: #333; text-align: right; width: 15%;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; color: #666; vertical-align: top;">
                                <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td style="padding: 12px; font-weight: bold; color: var(--primary); vertical-align: top;">
                                <?= htmlspecialchars($t['asset_name']) ?>
                            </td>
                            <td style="padding: 12px; vertical-align: top;">
                                <div style="color: #444; font-weight: 500;">
                                    <?= htmlspecialchars($t['title']) ?>
                                </div>
                                
                                <!-- UPRAVENO: Automatický výpis způsobu opravy -->
                                <?php if ($t['status'] === 'closed' && !empty($t['resolution_text'])): ?>
                                    <div style="margin-top: 8px; padding: 8px 12px; background: #e8f5e9; border-left: 3px solid #28a745; font-size: 0.9em; color: #155724; border-radius: 0 4px 4px 0;">
                                        <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle; margin-right: 4px;">build_circle</span>
                                        <b>Způsob opravy:</b> <?= nl2br(htmlspecialchars($t['resolution_text'])) ?>
                                        
                                        <?php if (!empty($t['resolved_at'])): ?>
                                            <div style="font-size: 0.85em; color: #3c763d; margin-top: 4px;">
                                                <i>Opraveno: <?= date('d.m.Y H:i', strtotime($t['resolved_at'])) ?></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; vertical-align: top;">
                                <?php if ($t['status'] === 'open'): ?>
                                    <span style="background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold; display: inline-block;">NEVYŘEŠENO</span>
                                <?php else: ?>
                                    <span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold; display: inline-block;">VYŘEŠENO</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: right; vertical-align: top;">
                                <a href="index.php?page=ticket_detail&id=<?= $t['id'] ?>" class="btn-primary" style="padding: 8px 15px; font-size: 0.9em; white-space: nowrap;">
                                    Detail závady
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
