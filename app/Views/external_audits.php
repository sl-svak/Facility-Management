<?php if (!defined('APP_ROOT')) exit; ?>
<?php
$lights = $lights ?? ['red' => [], 'yellow' => [], 'orange' => [], 'green' => []];

$flashMessages = [
    'plan_created'     => 'Plán externí revize byl uložen.',
    'plan_deleted'     => 'Plán externí revize byl odstraněn.',
    'record_created'   => 'Provedení externí revize bylo zaznamenáno a další termín byl přepočítán.',
    'protocol_saved'   => 'PDF protokol byl připojen a záznam označen jako dokončený.',
    'supplier_created' => 'Nový externí dodavatel byl přidán.',
    'category_created' => 'Nová kategorie revizí byla přidána.',
];
$todayTs = strtotime(date('Y-m-d'));
?>

<?php foreach ($flashMessages as $flashKey => $flashText): ?>
    <?php if (!empty($_GET[$flashKey])): ?>
        <div class="alert alert-success"><span class="material-symbols-outlined icon-inline">check_circle</span> <?= htmlspecialchars($flashText, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><span class="material-symbols-outlined icon-inline">error</span> <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Souhrn stavů -->
<div class="card card-primary-top">
    <h3 class="card-title"><span class="material-symbols-outlined">verified</span> Externí revize a dodavatelé</h3>
    <p class="card-intro">Evidence revizních lhůt (přesně na den nebo do konce měsíce), dodavatelů, provedení revizí a PDF protokolů. Revize jsou vždy navázané na konkrétní zařízení.</p>
    <div class="badge-row">
        <span class="badge badge-open badge-icon"><span class="material-symbols-outlined">error</span> Propadlé: <?= count($lights['red'] ?? []) ?></span>
        <span class="badge badge-warning badge-icon"><span class="material-symbols-outlined">warning</span> Blížící se: <?= count($lights['orange'] ?? []) ?></span>
        <span class="badge badge-info badge-icon"><span class="material-symbols-outlined">hourglass_top</span> Čeká na protokol: <?= count($lights['yellow'] ?? []) ?></span>
        <span class="badge badge-closed badge-icon"><span class="material-symbols-outlined">check_circle</span> V pořádku: <?= count($lights['green'] ?? []) ?></span>
    </div>
</div>

<?php if (Auth::isManager()): ?>
<!-- Nový plán revize -->
<div class="card card-primary-top">
    <h3 class="card-title"><span class="material-symbols-outlined">add_task</span> Přidat plán externí revize</h3>
    <p class="card-intro">Určete, které zařízení podléhá jaké externí revizi a v jakém intervalu. Interval a typ lhůty se předvyplní podle kategorie.</p>

    <?php if (empty($assets)): ?>
        <p class="empty-note">Nejprve založte zařízení v sekci Zařízení a stroje.</p>
    <?php elseif (empty($categories)): ?>
        <p class="empty-note">Nejprve níže přidejte alespoň jednu kategorii revizí.</p>
    <?php else: ?>
    <form method="POST" action="index.php?page=external_audit_plan_create">
        <?= Security::csrfField() ?>

        <div class="form-row">
            <div class="form-field">
                <label class="form-label" for="ext_asset_id">Zařízení / Stroj *</label>
                <select name="asset_id" id="ext_asset_id" class="form-control" required>
                    <option value="">-- Vyberte zařízení --</option>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label" for="ext_category_id">Kategorie revize *</label>
                <select name="audit_category_id" id="ext_category_id" class="form-control" required data-change="applyAuditCategoryDefaults">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                                data-interval="<?= (int)$c['default_interval_months'] ?>"
                                data-deadline="<?= htmlspecialchars((string)$c['default_deadline_type'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label" for="ext_supplier_id">Výchozí dodavatel</label>
                <select name="default_supplier_id" id="ext_supplier_id" class="form-control">
                    <option value="">-- Neurčeno --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field-sm">
                <label class="form-label" for="ext_interval_months">Interval (měsíce) *</label>
                <input type="number" name="interval_months" id="ext_interval_months" class="form-control" min="1" max="120" value="12" required>
            </div>

            <div class="form-field-sm">
                <label class="form-label" for="ext_warning_days">Varovat (dny) *</label>
                <input type="number" name="warning_days" id="ext_warning_days" class="form-control" min="0" max="365" value="30" required>
            </div>

            <div class="form-field">
                <label class="form-label" for="ext_deadline_type">Platnost další revize</label>
                <select name="deadline_type" id="ext_deadline_type" class="form-control">
                    <option value="month">Do konce kalendářního měsíce</option>
                    <option value="strict">Přesně na den</option>
                </select>
            </div>

            <div class="form-field">
                <label class="form-label" for="ext_last_exec">Poslední provedená revize</label>
                <input type="date" name="last_execution_date" id="ext_last_exec" class="form-control">
            </div>

            <div class="form-field">
                <label class="form-label" for="ext_next_due">Příští termín</label>
                <input type="date" name="next_due_date" id="ext_next_due" class="form-control" title="Pokud není vyplněn, dopočítá se automaticky">
            </div>

            <div>
                <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Uložit plán</button>
            </div>
        </div>
        <span class="form-hint">Příští termín můžete nechat prázdný, dopočítá se z poslední revize a intervalu.</span>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Plány revizí -->
<div class="card">
    <h3 class="card-title"><span class="material-symbols-outlined">event_upcoming</span> Plánované externí revize</h3>

    <?php if (empty($plans)): ?>
        <p class="empty-note">Zatím nejsou založeny žádné plány externích revizí.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Stav</th>
                        <th>Zařízení</th>
                        <th>Kategorie</th>
                        <th>Lhůta</th>
                        <th>Příští termín</th>
                        <th>Dodavatel</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($plans as $p):
                    $pid = (int)$p['id'];
                    $dueTs = strtotime((string)$p['next_due_date']);
                    $diffDays = (int)floor(($dueTs - $todayTs) / 86400);
                    $warn = (int)($p['warning_days'] ?? 30);
                    $isWaiting = (int)($p['waiting_protocol_count'] ?? 0) > 0;
                    $dueClass = ($diffDays < 0) ? 'due-danger' : (($diffDays <= $warn) ? 'due-warning' : 'due-ok');
                ?>
                    <tr>
                        <td>
                            <?php if ($isWaiting): ?>
                                <span class="badge badge-info badge-icon"><span class="material-symbols-outlined">hourglass_top</span> ČEKÁ NA PROTOKOL</span>
                            <?php elseif ($diffDays < 0): ?>
                                <span class="badge badge-open badge-icon"><span class="material-symbols-outlined">error</span> PROPADLÉ</span>
                            <?php elseif ($diffDays <= $warn): ?>
                                <span class="badge badge-warning badge-icon"><span class="material-symbols-outlined">warning</span> BLÍŽÍ SE</span>
                            <?php else: ?>
                                <span class="badge badge-closed badge-icon"><span class="material-symbols-outlined">check_circle</span> V POŘÁDKU</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-primary-strong"><?= htmlspecialchars((string)$p['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-strong"><?= htmlspecialchars((string)$p['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            Každých <strong><?= (int)$p['interval_months'] ?> měs.</strong><br>
                            <span class="text-small"><?= (($p['deadline_type'] ?? 'strict') === 'month') ? 'do konce měsíce' : 'přesně na den' ?>, varování <?= $warn ?> dní předem</span>
                        </td>
                        <td class="<?= $dueClass ?>">
                            <?= date('d.m.Y', $dueTs) ?><br>
                            <span class="text-small text-muted"><?= $diffDays < 0 ? ('Po termínu ' . abs($diffDays) . ' dní') : ('Zbývá ' . $diffDays . ' dní') ?></span>
                        </td>
                        <td><?= htmlspecialchars((string)($p['default_supplier_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" class="btn btn-success btn-sm"
                                data-action="openAuditRecordModal"
                                data-args="<?= htmlspecialchars(json_encode([$pid, (string)$p['asset_name'] . ' – ' . (string)$p['category_name'], (int)($p['default_supplier_id'] ?? 0)]), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="material-symbols-outlined">task_alt</span> Zapsat revizi
                            </button>
                            <?php if (Auth::isManager()): ?>
                                <form method="POST" action="index.php?page=external_audit_plan_delete" class="inline-form">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Smazat plán" data-confirm="Opravdu chcete tento plán externí revize smazat? Historie provedených revizí zůstane zachována.">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Historie revizí -->
<div class="card">
    <h3 class="card-title"><span class="material-symbols-outlined">history</span> Historie provedených revizí a PDF protokoly</h3>

    <?php if (empty($records)): ?>
        <p class="empty-note">Zatím nebyly zapsány žádné provedené externí revize.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Datum revize</th>
                        <th>Zařízení</th>
                        <th>Kategorie</th>
                        <th>Dodavatel</th>
                        <th>Další termín</th>
                        <th>Protokol</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r):
                    $rid = (int)$r['id'];
                    $isWaiting = (($r['status'] ?? '') === 'waiting_protocol');
                    $protoFile = (string)($r['protocol_file'] ?? '');
                    $protoUrl = ($protoFile !== '') ? ('index.php?page=file&name=' . urlencode(basename($protoFile))) : '';
                    $supplierLabel = (string)($r['supplier_name'] ?? $r['linked_supplier_name'] ?? '—');
                ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime((string)$r['execution_date'])) ?></td>
                        <td class="text-primary-strong"><?= htmlspecialchars((string)$r['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-strong"><?= htmlspecialchars((string)$r['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($supplierLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= date('d.m.Y', strtotime((string)$r['next_due_date'])) ?></td>
                        <td>
                            <?php if ($isWaiting): ?>
                                <span class="badge badge-info badge-icon"><span class="material-symbols-outlined">hourglass_top</span> ČEKÁ NA PROTOKOL</span>
                            <?php else: ?>
                                <span class="badge badge-closed badge-icon"><span class="material-symbols-outlined">check_circle</span> DOKONČENO</span>
                            <?php endif; ?>
                            <?php if (!empty($r['protocol_number'])): ?>
                                <br><span class="text-small">Č. <?= htmlspecialchars((string)$r['protocol_number'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ($protoUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($protoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-info btn-sm">
                                    <span class="material-symbols-outlined">picture_as_pdf</span> Protokol
                                </a>
                            <?php endif; ?>
                            <?php if ($isWaiting): ?>
                                <button type="button" class="btn btn-warning btn-sm"
                                    data-action="openAuditProtocolModal"
                                    data-args="<?= htmlspecialchars(json_encode([$rid, (string)$r['asset_name'] . ' – ' . (string)$r['category_name'] . ' (' . date('d.m.Y', strtotime((string)$r['execution_date'])) . ')']), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="material-symbols-outlined">upload_file</span> Nahrát protokol
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (Auth::isManager()): ?>
<div class="grid-2">
    <!-- Dodavatelé -->
    <div class="card">
        <h3 class="card-title"><span class="material-symbols-outlined">engineering</span> Externí dodavatelé</h3>
        <form method="POST" action="index.php?page=external_audit_supplier_create" class="form-stack">
            <?= Security::csrfField() ?>
            <div class="form-field">
                <label class="form-label" for="sup_name">Název dodavatele / revizní firmy *</label>
                <input type="text" name="name" id="sup_name" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label class="form-label" for="sup_contact">Kontaktní osoba</label>
                    <input type="text" name="contact_person" id="sup_contact" class="form-control">
                </div>
                <div class="form-field">
                    <label class="form-label" for="sup_phone">Telefon</label>
                    <input type="text" name="phone" id="sup_phone" class="form-control">
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="sup_email">E-mail</label>
                <input type="email" name="email" id="sup_email" class="form-control">
            </div>
            <div class="form-field">
                <label class="form-label" for="sup_notes">Poznámka / oprávnění</label>
                <textarea name="notes" id="sup_notes" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">person_add</span> Přidat dodavatele</button>
        </form>

        <?php if (!empty($suppliers)): ?>
            <div class="table-responsive mt-15">
                <table class="table">
                    <thead><tr><th>Dodavatel</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td class="text-strong"><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= htmlspecialchars((string)($s['contact_person'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($s['phone']) || !empty($s['email'])): ?>
                                    <br><span class="text-small"><?= htmlspecialchars(trim(($s['phone'] ?? '') . ' ' . ($s['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Kategorie -->
    <div class="card">
        <h3 class="card-title"><span class="material-symbols-outlined">category</span> Kategorie externích revizí</h3>
        <form method="POST" action="index.php?page=external_audit_category_create" class="form-stack">
            <?= Security::csrfField() ?>
            <div class="form-field">
                <label class="form-label" for="cat_name">Název kategorie *</label>
                <input type="text" name="name" id="cat_name" class="form-control" placeholder="např. Hromosvody / LPS" required>
            </div>
            <div class="form-row">
                <div class="form-field-sm">
                    <label class="form-label" for="cat_interval">Interval (měs.) *</label>
                    <input type="number" name="default_interval_months" id="cat_interval" class="form-control" min="1" max="120" value="12" required>
                </div>
                <div class="form-field">
                    <label class="form-label" for="cat_deadline">Výchozí typ lhůty</label>
                    <select name="default_deadline_type" id="cat_deadline" class="form-control">
                        <option value="month">Do konce měsíce</option>
                        <option value="strict">Přesně na den</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">add</span> Přidat kategorii</button>
        </form>

        <?php if (!empty($categories)): ?>
            <div class="table-responsive mt-15">
                <table class="table">
                    <thead><tr><th>Kategorie</th><th>Výchozí lhůta</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="text-strong"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$c['default_interval_months'] ?> měs., <?= (($c['default_deadline_type'] ?? 'strict') === 'month') ? 'do konce měsíce' : 'přesně na den' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: Zápis provedené revize -->
<div id="auditRecordModal" class="modal-overlay">
    <div class="card modal-card">
        <h3 class="card-title">Zápis provedené revize</h3>
        <p id="auditRecordTitle" class="modal-subtitle"></p>

        <form method="POST" action="index.php?page=external_audit_record_create" enctype="multipart/form-data" class="form-stack">
            <?= Security::csrfField() ?>
            <input type="hidden" name="plan_id" id="auditRecordPlanId">

            <div class="form-field">
                <label class="form-label" for="auditRecordDate">Datum provedení *</label>
                <input type="date" name="execution_date" id="auditRecordDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-field">
                <label class="form-label" for="auditRecordSupplier">Dodavatel</label>
                <select name="supplier_id" id="auditRecordSupplier" class="form-control">
                    <option value="">-- Výchozí dle plánu / zadat ručně --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="auditRecordSupplierName">Nebo název dodavatele</label>
                <input type="text" name="supplier_name" id="auditRecordSupplierName" class="form-control">
            </div>
            <div class="form-field">
                <label class="form-label" for="auditRecordProtocolNo">Číslo protokolu</label>
                <input type="text" name="protocol_number" id="auditRecordProtocolNo" class="form-control">
            </div>
            <div class="form-field">
                <label class="form-label" for="auditRecordPdf">PDF protokol</label>
                <input type="file" name="protocol_pdf" id="auditRecordPdf" class="form-control" accept=".pdf,application/pdf">
                <span class="form-hint">Bez protokolu se revize uloží jako „čeká na protokol“ a PDF můžete nahrát později.</span>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-muted" data-modal-close="auditRecordModal">Zrušit</button>
                <button type="submit" class="btn btn-success">Uložit revizi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Dodatečné nahrání protokolu -->
<div id="auditProtocolModal" class="modal-overlay">
    <div class="card modal-card">
        <h3 class="card-title">Nahrání PDF protokolu</h3>
        <p id="auditProtocolTitle" class="modal-subtitle"></p>

        <form method="POST" action="index.php?page=external_audit_upload_protocol" enctype="multipart/form-data" class="form-stack">
            <?= Security::csrfField() ?>
            <input type="hidden" name="record_id" id="auditProtocolRecordId">

            <div class="form-field">
                <label class="form-label" for="auditProtocolNo">Číslo protokolu</label>
                <input type="text" name="protocol_number" id="auditProtocolNo" class="form-control">
            </div>
            <div class="form-field">
                <label class="form-label" for="auditProtocolPdf">PDF protokol *</label>
                <input type="file" name="protocol_pdf" id="auditProtocolPdf" class="form-control" accept=".pdf,application/pdf" required>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-muted" data-modal-close="auditProtocolModal">Zrušit</button>
                <button type="submit" class="btn btn-success">Nahrát protokol</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/page-external-audits.js?v=<?= filemtime(APP_ROOT . '/assets/js/page-external-audits.js') ?>"></script>
