(function () {
// Stránka PDF Reporty. Funkce se volají z HTML přes data-change (viz app.js).
// Viditelnost se řídí atributem hidden, vzhled třídami ze style.css.

var templatesByAsset = {};
var dataEl = document.getElementById('cmms-output-templates-data');
if (dataEl && dataEl.textContent) {
    try { templatesByAsset = JSON.parse(dataEl.textContent) || {}; } catch (e) { templatesByAsset = {}; }
}

// Pole měsíce má smysl jen u měsíčního výkazu
function syncReportPeriod() {
    var mode = document.getElementById('report_period_mode');
    var group = document.getElementById('report_month_group');
    if (!mode || !group) return;
    group.hidden = (mode.value !== 'month');
}

// Nabídka vlastních výstupních šablon podle vybraného zařízení
function syncReportTemplates() {
    var outputMode = document.getElementById('report_output_mode');
    var group = document.getElementById('report_custom_template_group');
    var select = document.getElementById('report_output_template_id');
    var asset = document.getElementById('report_asset_id');
    var hint = document.getElementById('report_no_tpl_hint');
    if (!outputMode || !group || !select || !asset) return;

    var isCustom = (outputMode.value === 'custom');
    group.hidden = !isCustom;
    select.required = false;
    if (!isCustom) return;

    var list = Array.isArray(templatesByAsset[String(asset.value || '')]) ? templatesByAsset[String(asset.value || '')] : [];
    select.innerHTML = '';
    list.forEach(function (tpl) {
        var opt = document.createElement('option');
        opt.value = String(tpl.id);
        opt.textContent = tpl.paper_code ? (tpl.title + ' [' + tpl.paper_code + ']') : tpl.title;
        select.appendChild(opt);
    });
    select.hidden = (list.length === 0);
    select.required = (list.length > 0);
    if (hint) hint.hidden = (list.length > 0);
}

window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};
window.CMMS_ACTIONS.syncReportPeriod = syncReportPeriod;
window.CMMS_ACTIONS.syncReportTemplates = syncReportTemplates;

syncReportPeriod();
syncReportTemplates();
})();
