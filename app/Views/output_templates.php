<?php if (!defined('APP_ROOT')) exit; ?>
<?php
$editFieldsMap = [];
if (!empty($editTemplate['fields_json'])) {
    $decodedEdit = json_decode((string)$editTemplate['fields_json'], true);
    if (is_array($decodedEdit)) {
        foreach ($decodedEdit as $idx => $ef) {
            if (!is_array($ef) || empty($ef['id'])) {
                continue;
            }
            $editFieldsMap[(string)$ef['id']] = [
                'order' => isset($ef['order']) ? (int)$ef['order'] : (($idx + 1) * 10),
                'label' => (string)($ef['label'] ?? ''),
            ];
        }
    }
}

$flashMessages = [
    'created' => 'Nová výstupní šablona byla vytvořena.',
    'saved'   => 'Výstupní šablona byla uložena.',
    'copied'  => 'Výstupní šablona byla zkopírována na cílové zařízení.',
    'deleted' => 'Výstupní šablona byla odstraněna.',
];
?>

<?php foreach ($flashMessages as $flashKey => $flashText): ?>
    <?php if (!empty($_GET[$flashKey])): ?>
        <div class="alert alert-success"><span class="material-symbols-outlined icon-inline">check_circle</span> <?= htmlspecialchars($flashText, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><span class="material-symbols-outlined icon-inline">error</span> <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card card-primary-top">
    <h3 class="card-title">
        <span class="material-symbols-outlined">description</span>
        <?= !empty($editTemplate) ? 'Úprava výstupní šablony' : 'Nová výstupní šablona (oficiální evidence)' ?>
    </h3>
    <p class="card-intro">
        Sestavte vlastní tiskovou šablonu vázanou na konkrétní zařízení z vybraných evidovaných parametrů jeho kontrolních formulářů.
        Šablonu pak můžete zkopírovat na jiné zařízení se stejnými kontrolními formuláři nebo ji použít při exportu do PDF.
    </p>

    <form method="GET" action="index.php" class="form-stack">
        <input type="hidden" name="page" value="output_templates">
        <div class="form-field">
            <label class="form-label" for="filter_asset_id">Zařízení pro sestavení šablony</label>
            <select name="asset_id" id="filter_asset_id" class="form-control" data-change="submitForm">
                <?php foreach ($assets as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === (int)$selectedAssetId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($selectedAssetId > 0): ?>
        <form method="POST" action="index.php?page=output_template_save" id="outputTemplateForm" class="form-stack">
            <?= Security::csrfField() ?>
            <input type="hidden" name="id" value="<?= !empty($editTemplate) ? (int)$editTemplate['id'] : 0 ?>">
            <input type="hidden" name="asset_id" value="<?= (int)$selectedAssetId ?>">

            <div class="form-row">
                <div class="form-field">
                    <label class="form-label" for="out_tpl_title">Název výstupní šablony *</label>
                    <input type="text" id="out_tpl_title" name="title" class="form-control"
                           value="<?= htmlspecialchars((string)($editTemplate['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="např. Evidenční list denní kontroly" required>
                </div>
                <div class="form-field">
                    <label class="form-label" for="out_tpl_paper_code">Kód papírové evidence</label>
                    <input type="text" id="out_tpl_paper_code" name="paper_code" class="form-control"
                           value="<?= htmlspecialchars((string)($editTemplate['paper_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="např. F-UDR-012">
                </div>
                <div class="form-field">
                    <label class="form-label" for="out_tpl_source_form">Hlavní kontrolní formulář</label>
                    <select id="out_tpl_source_form" name="source_form_template_id" class="form-control">
                        <option value="">-- Všechny formuláře zařízení --</option>
                        <?php foreach ($assetFormsWithFields as $fg): ?>
                            <option value="<?= (int)$fg['id'] ?>"
                                <?= (!empty($editTemplate['source_form_template_id']) && (int)$editTemplate['source_form_template_id'] === (int)$fg['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$fg['title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-field mt-15">
                <label class="form-label" for="out_tpl_header_note">Poznámka / záhlaví pro oficiální tisk</label>
                <textarea id="out_tpl_header_note" name="header_note" class="form-control" placeholder="např. Záznam nahrazuje tištěný provozní deník"><?= htmlspecialchars((string)($editTemplate['header_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-row">
                <label class="form-check">
                    <input type="checkbox" name="show_signatures" value="1"
                        <?= (!isset($editTemplate['show_signatures']) || (int)$editTemplate['show_signatures'] === 1) ? 'checked' : '' ?>>
                    Zobrazovat elektronické podpisy
                </label>
                <label class="form-check">
                    <input type="checkbox" name="show_photos" value="1"
                        <?= (!isset($editTemplate['show_photos']) || (int)$editTemplate['show_photos'] === 1) ? 'checked' : '' ?>>
                    Zobrazovat fotografie kontrol a oprav
                </label>
            </div>

            <h3 class="card-title mt-15"><span class="material-symbols-outlined">checklist</span> Parametry evidované u tohoto zařízení</h3>
            <?php if (empty($assetFormsWithFields)): ?>
                <p class="empty-note">K tomuto zařízení nejsou zatím přiřazeny žádné aktivní kontrolní formuláře (v sekci Plánování údržby).</p>
            <?php else: ?>
                <p class="card-intro">Zaškrtněte parametry, které mají být ve výstupním PDF, a případně upravte jejich pořadí nebo název pro tisk.</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">Tisk</th>
                                <th>Kontrolní formulář</th>
                                <th>Evidovaný parametr</th>
                                <th style="width: 110px;">Pořadí</th>
                                <th>Vlastní název pro tisk</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $orderCounter = 10;
                        foreach ($assetFormsWithFields as $fg):
                            foreach ($fg['fields'] as $fld):
                                $fid = (string)$fld['id'];
                                $isChecked = isset($editFieldsMap[$fid]);
                                $ordVal = $isChecked ? $editFieldsMap[$fid]['order'] : $orderCounter;
                                $custLbl = ($isChecked && $editFieldsMap[$fid]['label'] !== $fld['label']) ? $editFieldsMap[$fid]['label'] : '';
                                $orderCounter += 10;
                        ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="selected_fields[]" value="<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?>>
                                </td>
                                <td><?= htmlspecialchars((string)$fg['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-strong">
                                    <?= htmlspecialchars((string)$fld['label'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($fld['unit'])): ?>
                                        <span class="text-small text-muted">(<?= htmlspecialchars((string)$fld['unit'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" name="field_order[<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>]" class="form-control" value="<?= (int)$ordVal ?>">
                                </td>
                                <td>
                                    <input type="text" name="field_custom_label[<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>]" class="form-control"
                                           value="<?= htmlspecialchars($custLbl, ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="<?= htmlspecialchars((string)$fld['label'], ENT_QUOTES, 'UTF-8') ?>">
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        endforeach;
                        ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <?php if (!empty($editTemplate)): ?>
                        <a href="index.php?page=output_templates&asset_id=<?= (int)$selectedAssetId ?>" class="btn btn-muted">Zrušit úpravu</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success">
                        <span class="material-symbols-outlined">save</span>
                        <?= !empty($editTemplate) ? 'Uložit změny šablony' : 'Vytvořit výstupní šablonu' ?>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="card-title"><span class="material-symbols-outlined">content_copy</span> Existující šablony a přenos na jiné zařízení</h3>

    <?php if (empty($templates)): ?>
        <p class="empty-note">Zatím nejsou vytvořeny žádné vlastní výstupní šablony.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Zařízení</th>
                        <th>Název šablony</th>
                        <th>Kód</th>
                        <th>Položek</th>
                        <th>Kopírování na jiné zařízení</th>
                        <th style="text-align: right;">Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $tpl):
                    $tid = (int)$tpl['id'];
                    $parsed = json_decode((string)$tpl['fields_json'], true);
                    $itemCount = is_array($parsed) ? count($parsed) : 0;
                    $compatTargets = $compatibleTargetsMap[$tid] ?? [];
                ?>
                    <tr>
                        <td class="text-primary-strong"><?= htmlspecialchars((string)$tpl['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-strong"><?= htmlspecialchars((string)$tpl['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($tpl['paper_code'] ?? '---'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$itemCount ?></td>
                        <td>
                            <?php if (empty($compatTargets)): ?>
                                <span class="empty-note text-small">Žádné jiné zařízení se stejnými kontrolními formuláři</span>
                            <?php else: ?>
                                <form method="POST" action="index.php?page=output_template_copy" class="form-row">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="template_id" value="<?= $tid ?>">
                                    <div class="form-field">
                                        <select name="target_asset_id" class="form-control" required>
                                            <?php foreach ($compatTargets as $ct): ?>
                                                <option value="<?= (int)$ct['id'] ?>"><?= htmlspecialchars((string)$ct['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <input type="text" name="new_title" class="form-control" placeholder="Nový název (volitelné)">
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-info btn-sm"><span class="material-symbols-outlined">content_copy</span> Kopírovat</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="index.php?page=output_templates&edit_id=<?= $tid ?>" class="btn btn-warning btn-sm"><span class="material-symbols-outlined">edit</span> Upravit</a>
                            <form method="POST" action="index.php?page=output_template_delete" class="inline-form">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $tid ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Smazat šablonu" data-confirm="Opravdu chcete smazat tuto výstupní šablonu?">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
