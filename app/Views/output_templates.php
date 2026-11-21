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
?>

<div class="card">
    <h2>Šablony výstupních formulářů (oficiální evidence)</h2>
    <p style="color: #555; margin-bottom: 18px;">
        Sestavte vlastní tiskovou šablonu vázanou na konkrétní zařízení z vybraných evidovaných parametrů jeho kontrolních formulářů.
        Šablonu pak můžete zkopírovat na jiné zařízení se stejnými kontrolními formuláři nebo ji použít při exportu do PDF.
    </p>

    <?php if (!empty($_GET['created'])): ?>
        <div class="alert alert-success" style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">
            Nová výstupní šablona byla vytvořena.
        </div>
    <?php elseif (!empty($_GET['saved'])): ?>
        <div class="alert alert-success" style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">
            Výstupní šablona byla uložena.
        </div>
    <?php elseif (!empty($_GET['copied'])): ?>
        <div class="alert alert-success" style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">
            Výstupní šablona byla zkopírována na cílové zařízení.
        </div>
    <?php elseif (!empty($_GET['deleted'])): ?>
        <div class="alert alert-success" style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf;">
            Výstupní šablona byla odstraněna.
        </div>
    <?php elseif (!empty($_GET['error'])): ?>
        <div class="alert alert-danger" style="padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
            <?= htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="index.php" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="output_templates">
        <label for="filter_asset_id"><strong>Zařízení pro sestavení šablony:</strong></label>
        <select name="asset_id" id="filter_asset_id" data-change="submitForm">
            <?php foreach ($assets as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === (int)$selectedAssetId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selectedAssetId > 0): ?>
        <form method="POST" action="index.php?page=output_template_save" id="outputTemplateForm">
            <?= Security::csrfField() ?>
            <input type="hidden" name="id" value="<?= !empty($editTemplate) ? (int)$editTemplate['id'] : 0 ?>">
            <input type="hidden" name="asset_id" value="<?= (int)$selectedAssetId ?>">

            <div class="form-group">
                <label for="out_tpl_title">Název výstupní šablony</label>
                <input type="text" id="out_tpl_title" name="title"
                       value="<?= htmlspecialchars((string)($editTemplate['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="např. Evidenční list denní kontroly" required>
            </div>

            <div class="form-group">
                <label for="out_tpl_paper_code">Kód / číslo papírové evidence (volitelné)</label>
                <input type="text" id="out_tpl_paper_code" name="paper_code"
                       value="<?= htmlspecialchars((string)($editTemplate['paper_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="např. F-UDR-012">
            </div>

            <div class="form-group">
                <label for="out_tpl_source_form">Hlavní kontrolní formulář (orientační vazba)</label>
                <select id="out_tpl_source_form" name="source_form_template_id">
                    <option value="">-- Všechny kontrolní formuláře zařízení --</option>
                    <?php foreach ($assetFormsWithFields as $fg): ?>
                        <option value="<?= (int)$fg['id'] ?>"
                            <?= (!empty($editTemplate['source_form_template_id']) && (int)$editTemplate['source_form_template_id'] === (int)$fg['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$fg['title'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="out_tpl_header_note">Poznámka / záhlaví pro oficiální tisk</label>
                <textarea id="out_tpl_header_note" name="header_note" rows="2" placeholder="např. Záznam nahrazuje tištěný provozní deník"><?= htmlspecialchars((string)($editTemplate['header_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="show_signatures" value="1"
                        <?= (!isset($editTemplate['show_signatures']) || (int)$editTemplate['show_signatures'] === 1) ? 'checked' : '' ?>>
                    Zobrazovat elektronické podpisy
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="show_photos" value="1"
                        <?= (!isset($editTemplate['show_photos']) || (int)$editTemplate['show_photos'] === 1) ? 'checked' : '' ?>>
                    Zobrazovat fotografie kontrol a oprav
                </label>
            </div>

            <h3>Parametry evidované u tohoto zařízení</h3>
            <?php if (empty($assetFormsWithFields)): ?>
                <p style="color: #7f8c8d;">
                    K tomuto zařízení nejsou zatím přiřazeny žádné aktivní kontrolní formuláře (v sekci Plánování údržby).
                </p>
            <?php else: ?>
                <p style="color: #555; margin-bottom: 10px;">
                    Zaškrtněte parametry, které mají být zahrnuty do výstupního PDF, a případně upravte jejich pořadí nebo název pro tisk:
                </p>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Tisk</th>
                                <th>Kontrolní formulář</th>
                                <th>Evidovaný parametr</th>
                                <th style="width: 110px;">Pořadí</th>
                                <th>Vlastní název pro tisk (volitelné)</th>
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
                                    <input type="checkbox" name="selected_fields[]" value="<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isChecked ? 'checked' : '' ?>>
                                </td>
                                <td><?= htmlspecialchars((string)$fg['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <strong><?= htmlspecialchars((string)$fld['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($fld['unit'])): ?>
                                        <small>(<?= htmlspecialchars((string)$fld['unit'], ENT_QUOTES, 'UTF-8') ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" name="field_order[<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>]"
                                           value="<?= (int)$ordVal ?>" style="width: 80px;">
                                </td>
                                <td>
                                    <input type="text" name="field_custom_label[<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>]"
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

                <button type="submit" class="btn btn-primary" style="margin-top: 16px;">
                    <?= !empty($editTemplate) ? 'Uložit změny šablony' : 'Vytvořit výstupní šablonu' ?>
                </button>
                <?php if (!empty($editTemplate)): ?>
                    <a href="index.php?page=output_templates&asset_id=<?= (int)$selectedAssetId ?>" class="btn" style="margin-top: 16px;">Zrušit úpravu</a>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px;">
    <h3>Existující šablony výstupních formulářů a přenos na jiné zařízení</h3>

    <?php if (empty($templates)): ?>
        <p style="color: #7f8c8d;">Zatím nejsou vytvořeny žádné vlastní výstupní šablony.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Zařízení</th>
                        <th>Název šablony</th>
                        <th>Kód</th>
                        <th>Počet položek</th>
                        <th>Kopírování na jiné zařízení</th>
                        <th>Akce</th>
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
                        <td><?= htmlspecialchars((string)$tpl['asset_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= htmlspecialchars((string)$tpl['title'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string)($tpl['paper_code'] ?? '–'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$itemCount ?></td>
                        <td>
                            <?php if (empty($compatTargets)): ?>
                                <small style="color: #7f8c8d;">Žádné jiné zařízení se stejnými kontrolními formuláři</small>
                            <?php else: ?>
                                <form method="POST" action="index.php?page=output_template_copy" style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="template_id" value="<?= $tid ?>">
                                    <select name="target_asset_id" required>
                                        <?php foreach ($compatTargets as $ct): ?>
                                            <option value="<?= (int)$ct['id'] ?>">
                                                <?= htmlspecialchars((string)$ct['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="new_title" placeholder="Nový název (volitelné)" style="max-width: 170px;">
                                    <button type="submit" class="btn">Kopírovat</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <a href="index.php?page=output_templates&edit_id=<?= $tid ?>" class="btn">Upravit</a>
                            <form method="POST" action="index.php?page=output_template_delete" style="display: inline;" data-confirm="Opravdu smazat tuto výstupní šablonu?">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $tid ?>">
                                <button type="submit" class="btn btn-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
