<?php if (!defined('APP_ROOT')) exit; ?>
<?php
$modInspections = $modInspections ?? SettingModel::isModuleEnabled('module_inspections');
$modExternalAudits = $modExternalAudits ?? SettingModel::isModuleEnabled('module_external_audits');
$modTickets = $modTickets ?? SettingModel::isModuleEnabled('module_tickets');
$external_lights = $external_lights ?? ['red' => [], 'yellow' => [], 'orange' => [], 'green' => []];

$extAttention = array_merge($external_lights['red'] ?? [], $external_lights['yellow'] ?? [], $external_lights['orange'] ?? []);
?>

<div class="card" style="border-left: 4px solid var(--primary);">
    <h3 class="card-title">Vítejte zpět, <?= htmlspecialchars((string)($_SESSION['first_name'] ?? 'Uživateli'), ENT_QUOTES, 'UTF-8') ?>!</h3>
    <p class="text-muted" style="margin-bottom: 0;">Toto je váš řídící panel. Zde vidíte aktuální stav systému a poslední provedené úkony techniků.</p>
</div>

<!-- Statistické karty -->
<div class="stats-grid">
    <div class="card stat-card stat-info">
        <span class="material-symbols-outlined">precision_manufacturing</span>
        <h2 class="stat-value"><?= (int)($stats['assets_count'] ?? 0) ?> / <?= (int)($stats['assets_total'] ?? 0) ?></h2>
        <p class="stat-label">Zařízení v provozu / celkem</p>
    </div>

    <?php if ($modInspections): ?>
        <div class="card stat-card stat-success">
            <span class="material-symbols-outlined">fact_check</span>
            <h2 class="stat-value"><?= (int)($stats['inspections_count'] ?? 0) ?></h2>
            <p class="stat-label">Provedených kontrol celkem</p>
        </div>
    <?php endif; ?>

    <?php if ($modTickets): ?>
        <div class="card stat-card stat-danger">
            <span class="material-symbols-outlined">assignment_late</span>
            <h2 class="stat-value"><?= (int)($stats['open_tickets'] ?? 0) ?></h2>
            <p class="stat-label">Otevřených závad k řešení</p>
        </div>
    <?php endif; ?>

    <?php if ($modExternalAudits): ?>
        <div class="card stat-card stat-warning">
            <span class="material-symbols-outlined">verified</span>
            <h2 class="stat-value"><?= (int)($stats['ext_overdue_or_soon'] ?? 0) ?> / <?= (int)($stats['ext_waiting_protocol'] ?? 0) ?></h2>
            <p class="stat-label">Externí revize: termíny / čeká protokol</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($modInspections): ?>
<!-- Semafor údržby -->
<div class="card card-accent-warning">
    <h3 class="card-title"><span class="material-symbols-outlined">traffic</span> Semafor plánované údržby</h3>
    <p class="card-intro">Systém automaticky hlídá termíny na základě vašich plánů. Nejakutnější úkony jsou nahoře.</p>

    <?php if (empty($traffic_lights)): ?>
        <p class="empty-note">Zatím nemáte vytvořené žádné Plány údržby.</p>
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
                            <td>
                                <?php if ($tl['status'] === 'red'): ?>
                                    <span class="badge badge-open badge-icon"><span class="material-symbols-outlined">error</span> PROPADLÉ</span>
                                <?php elseif ($tl['status'] === 'orange'): ?>
                                    <span class="badge badge-warning badge-icon"><span class="material-symbols-outlined">warning</span> BLÍŽÍ SE</span>
                                <?php else: ?>
                                    <span class="badge badge-closed badge-icon"><span class="material-symbols-outlined">check_circle</span> V POŘÁDKU</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-strong"><?= htmlspecialchars((string)$tl['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color: var(--text-main);"><?= htmlspecialchars((string)$tl['template_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($tl['last_inspection']) ? date('d.m.Y', strtotime((string)$tl['last_inspection'])) : '---' ?></td>
                            <td class="<?= $tl['status'] === 'red' ? 'due-danger' : ($tl['status'] === 'orange' ? 'due-warning' : 'due-ok') ?>">
                                <?= htmlspecialchars((string)$tl['next_due_formatted'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($tl['status'] !== 'green' && !empty($tl['last_inspection'])): ?>
                                    <br><span class="text-small text-muted" style="font-weight: normal;">(Zbývá <?= (int)$tl['days_remaining'] ?> dní)</span>
                                <?php endif; ?>
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
<!-- Semafor externích revizí -->
<div class="card card-accent-info">
    <div class="card-header-row">
        <h3 class="card-title"><span class="material-symbols-outlined">verified</span> Semafor externích revizí</h3>
        <a href="index.php?page=external_audits" class="btn btn-sm"><span class="material-symbols-outlined">open_in_new</span> Správa externích revizí</a>
    </div>
    <p class="card-intro mt-15">Revize prováděné externími dodavateli: propadlé a blížící se termíny i revize čekající na PDF protokol.</p>

    <?php if (empty($extAttention)): ?>
        <p class="empty-note">Všechny plánované externí revize jsou v pořádku a mají doložené protokoly.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Stav</th>
                        <th>Zařízení</th>
                        <th>Kategorie revize</th>
                        <th>Dodavatel</th>
                        <th>Termín</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($extAttention as $ext):
                    $isWaiting = isset($ext['record_id']);
                    $dRem = (int)($ext['days_remaining'] ?? 0);
                ?>
                    <tr>
                        <td>
                            <?php if ($isWaiting): ?>
                                <span class="badge badge-info badge-icon"><span class="material-symbols-outlined">hourglass_top</span> ČEKÁ NA PROTOKOL</span>
                            <?php elseif ($dRem < 0): ?>
                                <span class="badge badge-open badge-icon"><span class="material-symbols-outlined">error</span> PROPADLÁ REVIZE</span>
                            <?php else: ?>
                                <span class="badge badge-warning badge-icon"><span class="material-symbols-outlined">warning</span> BLÍŽÍ SE TERMÍN</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-strong"><?= htmlspecialchars((string)$ext['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="color: var(--text-main);"><?= htmlspecialchars((string)$ext['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($ext['supplier_name'] ?? $ext['default_supplier_name'] ?? '---'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $isWaiting ? '' : ($dRem < 0 ? 'due-danger' : 'due-warning') ?>">
                            <?= htmlspecialchars((string)($ext['next_due_formatted'] ?? '---'), ENT_QUOTES, 'UTF-8') ?>
                            <br><span class="text-small text-muted" style="font-weight: normal;">(<?= $dRem < 0 ? 'Po termínu ' . abs($dRem) . ' dní' : 'Zbývá ' . $dRem . ' dní' ?>)</span>
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
<!-- Výpis posledních kontrol -->
<div class="card">
    <h3 class="card-title"><span class="material-symbols-outlined">history</span> Historie posledních kontrol</h3>

    <?php if (empty($recent_inspections)): ?>
        <p class="empty-note">Zatím nebyla provedena žádná kontrola. Naskenujte QR kód zařízení a odešlete první formulář.</p>
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
                            <td><?= date('d.m.Y H:i', strtotime((string)$insp['created_at'])) ?></td>
                            <td class="text-primary-strong"><?= htmlspecialchars((string)$insp['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color: var(--text-main);"><?= htmlspecialchars((string)$insp['template_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(trim(($insp['first_name'] ?? '') . ' ' . ($insp['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($insp['status'] === 'OK'): ?>
                                    <span class="badge badge-closed">V POŘÁDKU</span>
                                <?php elseif ($insp['status'] === 'Odstaveno'): ?>
                                    <span class="badge badge-muted">ODSTÁVKA</span>
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
<?php endif; ?>
