<?php 
if (!defined('APP_ROOT')) exit; 

$isEdit = isset($editTemplate) &&$editTemplate;
$formId = $isEdit ? $editTemplate['id'] : '';
$formTitle =$isEdit ? $editTemplate['title'] : '';$formMinutes = $isEdit ? $editTemplate['estimated_minutes'] : 15;
$formSchema = $isEdit ? $editTemplate['schema_json'] : '[]';
?>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="card" style="border-left: 4px solid var(--success); margin-bottom: 20px;">
        Šablona byla smazána.
        <?php if ((int)($_GET['plans'] ?? 0) > 0): ?>
            Zároveň bylo odstraněno <?= (int)$_GET['plans'] ?> plánů údržby, které ji používaly.
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (($_GET['error'] ?? '') === 'in_use_other'): ?>
    <div class="card" style="border-left: 4px solid var(--danger); margin-bottom: 20px; color: var(--danger);">
        Šablonu nelze smazat, protože je použita v plánech údržby zařízení z jiných úseků. Smazat ji může jen administrátor.
    </div>
<?php elseif (($_GET['error'] ?? '') === 'failed'): ?>
    <div class="card" style="border-left: 4px solid var(--danger); margin-bottom: 20px; color: var(--danger);">
        Šablonu se nepodařilo smazat. Zkuste to prosím znovu.
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card card-primary-top">
        <h3 style="margin-top:0;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">build</span> 
            <?= $isEdit ? 'Upravit šablonu' : 'Vytvořit novou šablonu' ?>
        </h3>
        
        <form method="POST" action="index.php?page=form_create" id="form-builder" data-submit="validateForm">
            <!-- OCHRANA CSRF -->
            <?= Security::csrfField() ?>
            
            <input type="hidden" name="id" value="<?= $formId ?>">

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: var(--text-muted);">Název šablony (např. Kontrola ČOV) *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($formTitle) ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-top: 5px; background: var(--card-bg); color: var(--text-main);">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: var(--text-muted);">Odhadovaný čas (v minutách)</label>
                <input type="number" name="estimated_minutes" value="<?= $formMinutes ?>" min="1" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-top: 5px; background: var(--card-bg); color: var(--text-main);">
            </div>

            <input type="hidden" name="schema_json" id="schema_json" value="<?= htmlspecialchars($formSchema) ?>">

            <div style="margin-top: 20px; padding: 15px; background: var(--background); border-radius: 8px;">
                <strong style="color: var(--text-main);">Struktura formuláře:</strong>
                <div id="preview-area" style="margin-top: 10px; min-height: 50px; background: var(--card-bg); padding: 15px; border: 1px dashed var(--border-color);"></div>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" style="font-weight: bold;" data-action="addField" data-args='["asset_status"]'>
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em;">power_settings_new</span> Provozní stav
                </button>
                <button type="button" class="btn btn-warning" style="font-weight: bold;" data-action="addField" data-args='["photo"]'>
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.1em;">photo_camera</span> Fotografie
                </button>

                <button type="button" class="btn btn-success" data-action="addField" data-args='["radio_ok_ko"]'>+ Tlačítka OK/KO</button>
                <button type="button" class="btn btn-info" data-action="addField" data-args='["radio_yes_no"]'>+ Tlačítka Ano/Ne</button>
                <button type="button" class="btn btn-warning" data-action="addField" data-args='["numeric_limit"]'>+ Číselné měření</button>
                <button type="button" class="btn btn-danger" data-action="addField" data-args='["meter_reading"]'>+ Odečet měřidla</button>
                <button type="button" class="btn btn-primary" data-action="addField" data-args='["textarea"]'>+ Textová poznámka</button>
                <button type="button" class="btn" style="background: #8e44ad; color: #fff;" data-action="addField" data-args='["signature"]'>+ Podpisové pole</button>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
            
            <button type="submit" class="btn-primary btn-block">
                <span class="material-symbols-outlined" style="vertical-align: middle;">save</span> 
                <?= $isEdit ? 'Uložit změny' : 'Uložit šablonu formuláře' ?>
            </button>
            
            <?php if ($isEdit): ?>
                <a href="index.php?page=forms" class="btn" style="background: var(--text-muted); width: 100%; text-align: center; display: block; margin-top: 10px; box-sizing: border-box;">Zrušit úpravy</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;"><span class="material-symbols-outlined" style="vertical-align: middle;">list_alt</span> Uložené šablony</h3>
        <?php if (empty($templates)): ?>
            <p style="color: var(--text-muted); font-style: italic;">Zatím nemáte vytvořené žádné šablony formulářů.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($templates as$tpl): ?>
                    <li style="padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; <?= ($isEdit && $tpl['id'] ==$editTemplate['id']) ? 'background: rgba(41, 128, 185, 0.1);' : '' ?>">
                        <div>
                            <strong style="color: var(--primary); font-size: 1.1em;"><?= htmlspecialchars($tpl['title']) ?></strong><br>
                            <span style="font-size: 0.85em; color: var(--text-muted); display: inline-flex; align-items: center; margin-top: 4px;">
                                <span class="material-symbols-outlined" style="font-size: 1.1em; margin-right: 4px;">schedule</span> 
                                Odhad: <?= $tpl['estimated_minutes'] ?> min 
                                <?php if (!empty($tpl['avg_duration'])): ?>
                                    <?php 
                                        $avgMins = floor($tpl['avg_duration'] / 60);
                                        $avgSecs = round($tpl['avg_duration'] % 60);
                                    ?>
                                    <span style="color: var(--success); font-weight: bold; margin-left: 6px;">(Praxe: <?= $avgMins ?> min <?= $avgSecs ?> s)</span>
                                <?php endif; ?>
                                <span style="margin: 0 6px;">|</span>
                            <?php
                                $u = $usage[(int)$tpl['id']] ?? ['total' => 0, 'names' => [], 'hidden' => 0];
                                $shown = array_slice($u['names'], 0, 8);
                                $more = max(0, count($u['names']) - count($shown));
                                $blocked = !Auth::isAdmin() && $u['hidden'] > 0;
                                $usageText = '';
                                if ($u['total'] > 0) {
                                    $usageText = implode(', ', $shown)
                                        . ($more > 0 ? ' a další (' . $more . ')' : '')
                                        . ($u['hidden'] > 0 ? ($shown ? ' a ' : '') . $u['hidden'] . ' zařízení z jiných úseků' : '');
                                }
                                $confirmMsg = 'Opravdu smazat tuto šablonu? Záznamy již vyplněné podle této šablony zůstanou zachovány.';
                                if ($u['total'] > 0) {
                                    $confirmMsg .= "\n\nŠablona je aktivní v plánech údržby (" . $u['total'] . "): " . $usageText
                                        . ".\nPři smazání budou tyto plány údržby také odstraněny.";
                                }
                            ?>
                            <?php if ($u['total'] > 0): ?>
                                <br><span style="font-size: 0.85em; color: var(--warning);">
                                    <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">calendar_month</span>
                                    Použito v plánech údržby (<?= (int)$u['total'] ?>): <?= htmlspecialchars($usageText) ?>
                                </span>
                            <?php endif; ?> Polí: <?= count(json_decode($tpl['schema_json'], true) ?? []) ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 15px;">
                            <a href="index.php?page=forms&edit_id=<?= $tpl['id'] ?>" style="color: var(--info); text-decoration: none;"><span class="material-symbols-outlined">edit</span></a>
                            <form method="POST" action="index.php?page=form_delete" style="display: inline; margin: 0;">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                                    <?php if ($blocked): ?>
                                        <span class="material-symbols-outlined" style="color: var(--text-muted); cursor: not-allowed;" title="Šablona je použita u zařízení z jiných úseků - smazat ji může jen administrátor">delete</span>
                                    <?php else: ?>
                                    <button type="submit" data-confirm="<?= htmlspecialchars($confirmMsg, ENT_QUOTES, 'UTF-8') ?>" style="background: none; border: none; padding: 0; cursor: pointer; font: inherit; color: var(--danger);" title="Smazat šablonu">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                    <?php endif; ?>
                                </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="schema-data"><?= json_encode(
    json_decode($formSchema, true) ?: [],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<script src="assets/js/page-forms.js?v=<?= filemtime(APP_ROOT . '/assets/js/page-forms.js') ?>"></script>
