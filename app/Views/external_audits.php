<?php
$lights = $lights ?? ['red' => [], 'yellow' => [], 'orange' => [], 'green' => []];
?>

<div class="card" style="margin-bottom: 20px;">
    <h2 style="margin-top: 0;">Externí revize a kontroly dodavatelů</h2>
    <p style="color: var(--text-muted);">
        Evidence revizních lhůt (přesně na den vs. do konce měsíce), dodavatelů, provedení revizí a nahrávání PDF protokolů.
    </p>

    <?php if (!empty($_GET['plan_created'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">Plán externí revize byl uložen.</div>
    <?php elseif (!empty($_GET['plan_deleted'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">Plán externí revize byl odstraněn.</div>
    <?php elseif (!empty($_GET['record_created'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">Provedení externí revize bylo zaznamenáno a další termín byl přepočítán.</div>
    <?php elseif (!empty($_GET['protocol_saved'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">PDF protokol byl připojen a záznam označen jako dokončený.</div>
    <?php elseif (!empty($_GET['supplier_created'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">Nový externí dodavatel byl přidán.</div>
    <?php elseif (!empty($_GET['category_created'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">Nová kategorie revizí byla přidána.</div>
    <?php elseif (!empty($_GET['error'])): ?>
        <div style="padding: 12px; margin-bottom: 14px; border-radius: 6px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"><?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <span style="padding: 6px 12px; border-radius: 6px; background: #fadbd8; color: #922b21; font-weight: bold;">Propadlé: <?= count($lights['red'] ?? []) ?></span>
        <span style="padding: 6px 12px; border-radius: 6px; background: #fef5e7; color: #9c640c; font-weight: bold;">Blížící se: <?= count($lights['orange'] ?? []) ?></span>
        <span style="padding: 6px 12px; border-radius: 6px; background: #fef9e7; color: #7d6608; font-weight: bold;">Čeká na protokol: <?= count($lights['yellow'] ?? []) ?></span>
        <span style="padding: 6px 12px; border-radius: 6px; background: #eafaf1; color: #1e8449; font-weight: bold;">V pořádku: <?= count($lights['green'] ?? []) ?></span>
    </div>
</div>

<?php if (Auth::isManager()): ?>
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-top: 0;">Nový plán externí revize na zařízení</h3>
        <form method="POST" action="index.php?page=external_audit_plan_create">
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label for="ext_asset_id">Zařízení</label>
                <select name="asset_id" id="ext_asset_id" required>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ext_category_id">Kategorie revize</label>
                <select name="audit_category_id" id="ext_category_id" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                                data-interval="<?= (int)$c['default_interval_months'] ?>"
                                data-deadline="<?= htmlspecialchars((string)$c['default_deadline_type'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ext_supplier_id">Výchozí externí dodavatel</label>
                <select name="default_supplier_id" id="ext_supplier_id">
                    <option value="">-- Neurčeno / libovolný dodavatel --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ext_interval_months">Interval revize (měsíce)</label>
                <input type="number" name="interval_months" id="ext_interval_months" min="1" max="120" value="12" required>
            </div>

            <div class="form-group">
                <label for="ext_warning_days">Upozornit předem (dny)</label>
                <input type="number" name="warning_days" id="ext_warning_days" min="0" max="365" value="30" required>
            </div>

            <div class="form-group">
                <label for="ext_deadline_type">Způsob výpočtu platnosti další revize</label>
                <select name="deadline_type" id="ext_deadline_type">
                    <option value="month">Do konce kalendářního měsíce (např. revize elektro)</option>
                    <option value="strict">Přesně na den (např. tlaková zařízení)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="ext_last_exec">Datum poslední provedené revize (volitelné)</label>
                <input type="date" name="last_execution_date" id="ext_last_exec">
            </div>

            <div class="form-group">
                <label for="ext_next_due">Příští termín revize (pokud není vyplněn, dopočítá se automaticky)</label>
                <input type="date" name="next_due_date" id="ext_next_due">
            </div>

            <button type="submit" class="btn btn-primary">Uložit plán revize</button>
        </form>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Plánované externí revize a zadání provedení</h3>
    <?php if (empty($plans)): ?>
        <p style="color: var(--text-muted);">Zatím nejsou založeny žádné plány externích revizí.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Zařízení</th>
                        <th>Kategorie</th>
                        <th>Lhůta</th>
                        <th>Příští termín</th>
                        <th>Dodavatel</th>
                        <th>Zadat provedení revize</th>
                        <?php if (Auth::isManager()): ?><th>Akce</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($plans as $p):
                    $pid = (int)$p['id'];
                    $dueTs = strtotime((string)$p['next_due_date']);
                    $diffDays = (int)floor(($dueTs - strtotime(date('Y-m-d'))) / 86400);
                    $warn = (int)($p['warning_days'] ?? 30);
                    $rowBg = '';
                    if ($diffDays < 0) {
                        $rowBg = '#fdf2f2';
                    } elseif ($diffDays <= $warn) {
                        $rowBg = '#fef9e7';
                    }
                ?>
                    <tr style="background: <?= $rowBg ?>;">
                        <td><strong><?= htmlspecialchars((string)$p['asset_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string)$p['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$p['interval_months'] ?> měs. (<?= (($p['deadline_type'] ?? 'strict') === 'month') ? 'konec měsíce' : 'přesně na den' ?>)</td>
                        <td>
                            <strong><?= date('d.m.Y', $dueTs) ?></strong><br>
                            <small>(Zbývá <?= $diffDays ?> dní)</small>
                            <?php if ((int)($p['waiting_protocol_count'] ?? 0) > 0): ?>
                                <br><span style="color: #9c640c; font-weight: bold;">Čeká na PDF protokol</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)($p['default_supplier_name'] ?? '–'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="POST" action="index.php?page=external_audit_record_create" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 6px; min-width: 240px;">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="plan_id" value="<?= $pid ?>">
                                <input type="date" name="execution_date" value="<?= date('Y-m-d') ?>" required>
                                <select name="supplier_id">
                                    <option value="">-- Dodavatel (výchozí / ručně) --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>" <?= ((int)($p['default_supplier_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="supplier_name" placeholder="Nebo název dodavatele">
                                <input type="text" name="protocol_number" placeholder="Číslo protokolu (volitelné)">
                                <input type="file" name="protocol_pdf" accept=".pdf,application/pdf">
                                <button type="submit" class="btn">Zapsat revizi</button>
                            </form>
                        </td>
                        <?php if (Auth::isManager()): ?>
                            <td>
                                <form method="POST" action="index.php?page=external_audit_plan_delete">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <button type="submit" class="btn btn-danger">Smazat</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;">Historie provedených revizí a PDF protokoly</h3>
    <?php if (empty($records)): ?>
        <p style="color: var(--text-muted);">Zatím nebyly zapsány žádné provedené externí revize.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Datum revize</th>
                        <th>Zařízení</th>
                        <th>Kategorie</th>
                        <th>Dodavatel</th>
                        <th>Další termín</th>
                        <th>Stav a PDF protokol</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r):
                    $rid = (int)$r['id'];
                    $isWaiting = (($r['status'] ?? '') === 'waiting_protocol');
                    $protoFile = (string)($r['protocol_file'] ?? '');
                    $protoUrl = ($protoFile !== '') ? ('index.php?page=file&name=' . urlencode(basename($protoFile))) : '';
                ?>
                    <tr>
                        <td><?= date('d.m.Y', strtotime((string)$r['execution_date'])) ?></td>
                        <td><strong><?= htmlspecialchars((string)$r['asset_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string)$r['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($r['supplier_name'] ?? $r['linked_supplier_name'] ?? '–'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= date('d.m.Y', strtotime((string)$r['next_due_date'])) ?></td>
                        <td>
                            <?php if ($isWaiting): ?>
                                <span style="color: #9c640c; font-weight: bold;">Čeká na PDF protokol</span>
                                <form method="POST" action="index.php?page=external_audit_upload_protocol" enctype="multipart/form-data" style="margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="record_id" value="<?= $rid ?>">
                                    <input type="text" name="protocol_number" placeholder="Číslo protokolu">
                                    <input type="file" name="protocol_pdf" accept=".pdf,application/pdf" required>
                                    <button type="submit" class="btn">Nahrát protokol</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #1e8449; font-weight: bold;">Dokončeno</span>
                                <?php if (!empty($r['protocol_number'])): ?>
                                    <br><small>Č. protokolu: <?= htmlspecialchars((string)$r['protocol_number'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                                <?php if ($protoUrl !== ''): ?>
                                    <br><a href="<?= htmlspecialchars($protoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank">Otevřít PDF protokol</a>
                                <?php endif; ?>
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
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div class="card">
            <h3 style="margin-top: 0;">Externí dodavatelé</h3>
            <form method="POST" action="index.php?page=external_audit_supplier_create">
                <?= Security::csrfField() ?>
                <div class="form-group">
                    <label for="sup_name">Název dodavatele / revizní firmy</label>
                    <input type="text" name="name" id="sup_name" required>
                </div>
                <div class="form-group">
                    <label for="sup_contact">Kontaktní osoba / revizní technik</label>
                    <input type="text" name="contact_person" id="sup_contact">
                </div>
                <div class="form-group">
                    <label for="sup_phone">Telefon</label>
                    <input type="text" name="phone" id="sup_phone">
                </div>
                <div class="form-group">
                    <label for="sup_email">E-mail</label>
                    <input type="email" name="email" id="sup_email">
                </div>
                <div class="form-group">
                    <label for="sup_notes">Poznámka / oprávnění</label>
                    <textarea name="notes" id="sup_notes" rows="2"></textarea>
                </div>
                <button type="submit" class="btn">Přidat dodavatele</button>
            </form>

            <?php if (!empty($suppliers)): ?>
                <ul style="margin-top: 14px; padding-left: 18px;">
                    <?php foreach ($suppliers as $s): ?>
                        <li>
                            <strong><?= htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($s['phone']) || !empty($s['email'])): ?>
                                <small>(<?= htmlspecialchars(trim(($s['phone'] ?? '') . ' ' . ($s['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>)</small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Kategorie externích revizí</h3>
            <form method="POST" action="index.php?page=external_audit_category_create">
                <?= Security::csrfField() ?>
                <div class="form-group">
                    <label for="cat_name">Název kategorie</label>
                    <input type="text" name="name" id="cat_name" placeholder="např. Hromosvody / LPS" required>
                </div>
                <div class="form-group">
                    <label for="cat_interval">Výchozí interval (měsíce)</label>
                    <input type="number" name="default_interval_months" id="cat_interval" min="1" max="120" value="12" required>
                </div>
                <div class="form-group">
                    <label for="cat_deadline">Výchozí typ lhůty</label>
                    <select name="default_deadline_type" id="cat_deadline">
                        <option value="month">Do konce měsíce</option>
                        <option value="strict">Přesně na den</option>
                    </select>
                </div>
                <button type="submit" class="btn">Přidat kategorii</button>
            </form>

            <?php if (!empty($categories)): ?>
                <ul style="margin-top: 14px; padding-left: 18px;">
                    <?php foreach ($categories as $c): ?>
                        <li>
                            <strong><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>(<?= (int)$c['default_interval_months'] ?> měs., <?= (($c['default_deadline_type'] ?? 'strict') === 'month') ? 'konec měsíce' : 'přesně na den' ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="assets/js/page-external-audits.js"></script>
