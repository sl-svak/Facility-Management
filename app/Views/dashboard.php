<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card" style="border-left: 4px solid var(--primary); background: #fdfbf7;">
    <h3 style="margin-top: 0;">Vítejte zpět, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Uživateli') ?>!</h3>
    <p style="margin-bottom: 0;">Toto je váš řídící panel. Zde vidíte aktuální stav systému a poslední provedené úkony techniků.</p>
</div>

<!-- Tři hlavní statistické karty -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <div class="card card-primary-top" style="text-align: center; margin-bottom: 0; border-top: 4px solid #3498db;">
        <span class="material-symbols-outlined" style="font-size: 3em; color: #3498db;">precision_manufacturing</span>
        <h2 style="font-size: 2.5em; margin: 10px 0; color: #2c3e50;"><?= $stats['assets_count'] ?></h2>
        <p style="color: var(--text-muted); margin: 0; font-weight: bold;">Aktivních zařízení</p>
    </div>
    
    <div class="card" style="border-top: 4px solid var(--success); text-align: center; margin-bottom: 0;">
        <span class="material-symbols-outlined" style="font-size: 3em; color: var(--success);">fact_check</span>
        <h2 style="font-size: 2.5em; margin: 10px 0; color: #2c3e50;"><?= $stats['inspections_count'] ?></h2>
        <p style="color: var(--text-muted); margin: 0; font-weight: bold;">Provedených kontrol celkem</p>
    </div>
    
    <div class="card" style="border-top: 4px solid var(--danger); text-align: center; margin-bottom: 0;">
        <span class="material-symbols-outlined" style="font-size: 3em; color: var(--danger);">assignment_late</span>
        <h2 style="font-size: 2.5em; margin: 10px 0; color: #2c3e50;"><?= $stats['open_tickets'] ?></h2>
        <p style="color: var(--text-muted); margin: 0; font-weight: bold;">Otevřených závad k řešení</p>
    </div>
</div>

<!-- Semafor údržby -->
<div class="card" style="border-top: 4px solid var(--warning);">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">traffic</span> Semafor plánované údržby</h3>
    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">Systém automaticky hlídá termíny na základě vašich plánů. Nejakutnější úkony jsou nahoře.</p>
    
    <?php if (empty($traffic_lights)): ?>
        <p style="color: var(--text-muted); font-style: italic;">Zatím nemáte vytvořené žádné Plány údržby.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Stav</th>
                        <th>Zařízení</th>
                        <th>Úkon</th>
                        <th>Poslední kontrola</th>
                        <th>Termín další kontroly</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($traffic_lights as $tl): ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <?php if ($tl['status'] === 'red'): ?>
                                    <span class="badge badge-open" style="display: inline-flex; align-items: center; gap: 5px;">
                                        <span class="material-symbols-outlined" style="font-size: 1.2em;">error</span> PROPADLÉ
                                    </span>
                                <?php elseif ($tl['status'] === 'orange'): ?>
                                    <span class="badge" style="background: #fff3cd; color: #856404; display: inline-flex; align-items: center; gap: 5px;">
                                        <span class="material-symbols-outlined" style="font-size: 1.2em;">warning</span> BLÍŽÍ SE
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-closed" style="display: inline-flex; align-items: center; gap: 5px;">
                                        <span class="material-symbols-outlined" style="font-size: 1.2em;">check_circle</span> V POŘÁDKU
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: bold; color: var(--text-main);"><?= htmlspecialchars($tl['asset_name']) ?></td>
                            <td><?= htmlspecialchars($tl['template_name']) ?></td>
                            <td><?= $tl['last_inspection'] ? date('d.m.Y', strtotime($tl['last_inspection'])) : '---' ?></td>
                            <td style="font-weight: bold; color: <?= $tl['status'] === 'red' ? 'var(--danger)' : ($tl['status'] === 'orange' ? 'var(--warning)' : 'var(--success)') ?>;">
                                <?= htmlspecialchars($tl['next_due_formatted']) ?>
                                <?php if ($tl['status'] !== 'green' && $tl['last_inspection']): ?>
                                    <br><span style="font-size: 0.8em; font-weight: normal;">(Zbývá <?= $tl['days_remaining'] ?> dní)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Výpis posledních kontrol -->
<div class="card">
    <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">history</span> Historie posledních kontrol</h3>
    
    <?php if (empty($recent_inspections)): ?>
        <p style="color: var(--text-muted); font-style: italic;">Zatím nebyla provedena žádná kontrola. Naskenujte QR kód zařízení a odešlete první formulář.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Datum a čas</th>
                        <th>Zařízení</th>
                        <th>Úkon (Formulář)</th>
                        <th>Technik</th>
                        <th>Výsledek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_inspections as $insp): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($insp['created_at'])) ?></td>
                            <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($insp['asset_name']) ?></td>
                            <td><?= htmlspecialchars($insp['template_name']) ?></td>
                            <td><?= htmlspecialchars($insp['first_name'] . ' ' . $insp['last_name']) ?></td>
                            <td>
                                <?php if ($insp['status'] === 'OK'): ?>
                                    <span class="badge badge-closed">V POŘÁDKU</span>
                                <?php elseif ($insp['status'] === 'Odstaveno'): ?>
                                    <span class="badge" style="background: #7f8c8d; color: #fff;">ODSTÁVKA</span>
                                <?php else: ?>
                                    <span class="badge badge-open">ZÁVADA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
