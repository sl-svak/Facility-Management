<?php
$modInspections = $modInspections ?? SettingModel::isModuleEnabled('module_inspections');
$modExternalAudits = $modExternalAudits ?? SettingModel::isModuleEnabled('module_external_audits');
$modTickets = $modTickets ?? SettingModel::isModuleEnabled('module_tickets');
$external_lights = $external_lights ?? ['red' => [], 'yellow' => [], 'orange' => [], 'green' => []];

$extAttention = array_merge($external_lights['red'] ?? [], $external_lights['yellow'] ?? [], $external_lights['orange'] ?? []);
?>

<div class="welcome-banner card">
    <h3 style="margin-top: 0;">Vítejte zpět, <?= htmlspecialchars((string)($_SESSION['first_name'] ?? 'uživateli'), ENT_QUOTES, 'UTF-8') ?>!</h3>
    <p style="margin-bottom: 0; color: var(--text-muted);">
        Přehled aktuálního stavu zařízení, kontrol údržby i externích revizí.
    </p>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <div class="card stat-card" style="border-top: 4px solid #2980b9;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="material-symbols-outlined" style="font-size: 32px; color: #2980b9;">precision_manufacturing</span>
            <strong style="font-size: 28px;"><?= (int)($stats['assets_count'] ?? 0) ?> / <?= (int)($stats['assets_total'] ?? 0) ?></strong>
        </div>
        <div style="margin-top: 8px; color: var(--text-muted);">Zařízení v provozu / celkem (odstavená se do provozu nepočítají)</div>
    </div>

    <?php if ($modInspections): ?>
        <div class="card stat-card" style="border-top: 4px solid #27ae60;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="material-symbols-outlined" style="font-size: 32px; color: #27ae60;">fact_check</span>
                <strong style="font-size: 28px;"><?= (int)($stats['inspections_count'] ?? 0) ?></strong>
            </div>
            <div style="margin-top: 8px; color: var(--text-muted);">Provedených kontrol celkem</div>
        </div>
    <?php endif; ?>

    <?php if ($modTickets): ?>
        <div class="card stat-card" style="border-top: 4px solid #e74c3c;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="material-symbols-outlined" style="font-size: 32px; color: #e74c3c;">assignment_late</span>
                <strong style="font-size: 28px;"><?= (int)($stats['open_tickets'] ?? 0) ?></strong>
            </div>
            <div style="margin-top: 8px; color: var(--text-muted);">Otevřených závad k řešení</div>
        </div>
    <?php endif; ?>

    <?php if ($modExternalAudits): ?>
        <div class="card stat-card" style="border-top: 4px solid #8e44ad;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span class="material-symbols-outlined" style="font-size: 32px; color: #8e44ad;">verified</span>
                <strong style="font-size: 28px;"><?= (int)($stats['ext_overdue_or_soon'] ?? 0) ?> / <?= (int)($stats['ext_waiting_protocol'] ?? 0) ?></strong>
            </div>
            <div style="margin-top: 8px; color: var(--text-muted);">Externí revize (termíny / čeká protokol)</div>
        </div>
    <?php endif; ?>
</div>

<?php if ($modInspections): ?>
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid #f39c12;">
        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined">traffic</span>
            <span>Semafor plánované údržby a kontrol</span>
        </h3>
        <p style="color: var(--text-muted); margin-top: 4px;">
            Automatické hlídání termínů interních kontrol. Nejakutnější položky jsou nahoře.
        </p>

        <?php if (empty($traffic_lights)): ?>
            <p style="color: var(--text-muted);">Zatím nejsou nastavena žádná pravidla interních kontrol.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
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
                    <?php foreach ($traffic_lights as $item):
                        $st = (string)($item['status'] ?? 'green');
                        $badgeBg = '#eafaf1';
                        $badgeColor = '#1e8449';
                        $badgeText = 'V POŘÁDKU';
                        if ($st === 'red') {
                            $badgeBg = '#fadbd8';
                            $badgeColor = '#922b21';
                            $badgeText = 'PROPADLÉ';
                        } elseif ($st === 'orange') {
                            $badgeBg = '#fef5e7';
                            $badgeColor = '#9c640c';
                            $badgeText = 'BLÍŽÍ SE';
                        }
                        $lastInsp = !empty($item['last_inspection']) ? date('d.m.Y', strtotime((string)$item['last_inspection'])) : '–';
                        $daysRem = (int)($item['days_remaining'] ?? 0);
                    ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85em; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>;">
                                    <?= $badgeText ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars((string)$item['asset_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string)$item['template_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($lastInsp, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <strong style="color: <?= ($st === 'red') ? '#c0392b' : 'inherit' ?>;">
                                    <?= htmlspecialchars((string)($item['next_due_formatted'] ?? '–'), ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <br>
                                <small style="color: var(--text-muted);">(Zbývá <?= $daysRem ?> dní)</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($modExternalAudits): ?>
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid #8e44ad;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined">verified</span>
                <span>Semafor externích revizí a dodavatelů</span>
            </h3>
            <a href="index.php?page=external_audits" class="btn">Správa externích revizí</a>
        </div>
        <p style="color: var(--text-muted); margin-top: 6px;">
            Samostatný přehled revizí prováděných externími dodavateli (propadlé, blížící se termíny i revize čekající na PDF protokol).
        </p>

        <?php if (empty($extAttention)): ?>
            <p style="color: #1e8449; font-weight: bold;">Všechny plánované externí revize jsou v pořádku a mají doložené protokoly.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Stav</th>
                            <th>Zařízení</th>
                            <th>Kategorie revize</th>
                            <th>Dodavatel</th>
                            <th>Termín / Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($extAttention as $ext):
                        $isWaiting = isset($ext['record_id']);
                        $dRem = (int)($ext['days_remaining'] ?? 0);
                        if ($isWaiting) {
                            $bBg = '#fef9e7';
                            $bCol = '#7d6608';
                            $bLbl = 'ČEKÁ NA PROTOKOL';
                        } elseif ($dRem < 0) {
                            $bBg = '#fadbd8';
                            $bCol = '#922b21';
                            $bLbl = 'PROPADLÁ REVIZE';
                        } else {
                            $bBg = '#fef5e7';
                            $bCol = '#9c640c';
                            $bLbl = 'BLÍŽÍ SE TERMÍN';
                        }
                    ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85em; background: <?= $bBg ?>; color: <?= $bCol ?>;">
                                    <?= $bLbl ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars((string)$ext['asset_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string)$ext['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($ext['supplier_name'] ?? $ext['default_supplier_name'] ?? '–'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <strong><?= htmlspecialchars((string)($ext['next_due_formatted'] ?? '–'), ENT_QUOTES, 'UTF-8') ?></strong>
                                <br>
                                <small style="color: var(--text-muted);">(Zbývá <?= $dRem ?> dní)</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($modInspections): ?>
    <div class="card">
        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined">history</span>
            <span>Historie posledních kontrol</span>
        </h3>

        <?php if (empty($recent_inspections)): ?>
            <p style="color: var(--text-muted);">Zatím nebyly provedeny žádné kontroly.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Zařízení</th>
                            <th>Formulář</th>
                            <th>Technik</th>
                            <th>Stav</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_inspections as $rec): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime((string)$rec['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars((string)$rec['asset_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string)$rec['template_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars((string)$rec['status'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
