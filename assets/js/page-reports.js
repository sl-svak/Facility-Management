(function () {
    'use strict';

    var periodModeEl = document.getElementById('report_period_mode');
    var monthGroupEl = document.getElementById('report_month_group');
    var outputModeEl = document.getElementById('report_output_mode');
    var customTplGroupEl = document.getElementById('report_custom_template_group');
    var tplSelectEl = document.getElementById('report_output_template_id');
    var assetSelectEl = document.getElementById('report_asset_id');
    var noTplHintEl = document.getElementById('report_no_tpl_hint');
    var dataEl = document.getElementById('cmms-output-templates-data');

    var templatesByAsset = {};
    if (dataEl && dataEl.textContent) {
        try {
            templatesByAsset = JSON.parse(dataEl.textContent) || {};
        } catch (e) {
            templatesByAsset = {};
        }
    }

    function syncPeriodVisibility() {
        if (!periodModeEl || !monthGroupEl) {
            return;
        }
        monthGroupEl.style.display = (periodModeEl.value === 'month') ? '' : 'none';
    }

    function syncTemplatesForAsset() {
        if (!outputModeEl || !customTplGroupEl || !tplSelectEl || !assetSelectEl) {
            return;
        }
        var isCustom = (outputModeEl.value === 'custom');
        customTplGroupEl.style.display = isCustom ? '' : 'none';

        if (!isCustom) {
            return;
        }

        var assetId = String(assetSelectEl.value || '');
        var list = Array.isArray(templatesByAsset[assetId]) ? templatesByAsset[assetId] : [];

        tplSelectEl.innerHTML = '';
        if (list.length === 0) {
            if (noTplHintEl) {
                noTplHintEl.style.display = '';
            }
            return;
        }

        if (noTplHintEl) {
            noTplHintEl.style.display = 'none';
        }

        list.forEach(function (tpl) {
            var opt = document.createElement('option');
            opt.value = String(tpl.id);
            opt.textContent = tpl.paper_code ? (tpl.title + ' [' + tpl.paper_code + ']') : tpl.title;
            tplSelectEl.appendChild(opt);
        });
    }

    if (periodModeEl) {
        periodModeEl.addEventListener('change', syncPeriodVisibility);
    }
    if (outputModeEl) {
        outputModeEl.addEventListener('change', syncTemplatesForAsset);
    }
    if (assetSelectEl) {
        assetSelectEl.addEventListener('change', syncTemplatesForAsset);
    }

    syncPeriodVisibility();
    syncTemplatesForAsset();
})();
