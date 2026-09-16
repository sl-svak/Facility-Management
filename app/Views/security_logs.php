<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top">
    <h3 style="margin-top:0;">
        <span class="material-symbols-outlined" style="vertical-align: middle; color: #e74c3c;">security</span> 
        Bezpečnostní deník (Log přihlášení)
    </h3>
    
    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px;">
        Zde je zobrazeno posledních 200 pokusů o přihlášení. Pokud zaznamenáte více neúspěšných pokusů z jedné IP adresy v krátkém čase, systém danou adresu na 15 minut automaticky zablokuje.
    </p>

    <?php if (empty($logs)): ?>
        <p style="color: #777; font-style: italic;">Zatím nejsou k dispozici žádné záznamy o přihlášení.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px; color: #333;">Datum a čas</th>
                        <th style="padding: 12px; color: #333;">Zadané jméno</th>
                        <th style="padding: 12px; color: #333;">IP adresa</th>
                        <th style="padding: 12px; color: #333;">Výsledek</th>
                        <th style="padding: 12px; color: #333;">Zařízení / Prohlížeč</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px; white-space: nowrap;"><strong><?= date('d.m.Y H:i:s', strtotime($log['attempt_time'])) ?></strong></td>
                            <td style="padding: 12px; font-weight: bold; color: var(--primary);"><?= htmlspecialchars($log['username']) ?></td>
                            <td style="padding: 12px; font-family: monospace; color: #555;"><?= htmlspecialchars($log['ip_address']) ?></td>
                            <td style="padding: 12px;">
                                <?php if ($log['status'] === 'success'): ?>
                                    <span style="background: #eafaf1; color: #27ae60; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85em;">Úspěch</span>
                                <?php else: ?>
                                    <span style="background: #fdeeed; color: #e74c3c; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85em;">Zamítnuto</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; font-size: 0.85em; color: #777;" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                <?= htmlspecialchars(mb_substr($log['user_agent'], 0, 50)) ?>...
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
