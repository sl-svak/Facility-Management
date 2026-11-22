(function () {
// Stránka Externí revize. Funkce se volají z HTML přes data-action / data-change (viz app.js),
// vzhled řídí třídy ze style.css - zde se mění jen hodnoty a viditelnost modálních oken.

// Předvyplnění intervalu a typu lhůty podle zvolené kategorie revize
function applyAuditCategoryDefaults() {
    var select = document.getElementById('ext_category_id');
    if (!select) return;
    var opt = select.options[select.selectedIndex];
    if (!opt) return;

    var interval = parseInt(opt.getAttribute('data-interval'), 10);
    var intervalInput = document.getElementById('ext_interval_months');
    if (intervalInput && interval > 0) intervalInput.value = String(interval);

    var deadline = opt.getAttribute('data-deadline');
    var deadlineSelect = document.getElementById('ext_deadline_type');
    if (deadlineSelect && (deadline === 'month' || deadline === 'strict')) deadlineSelect.value = deadline;
}

function openAuditRecordModal(planId, title, defaultSupplierId) {
    document.getElementById('auditRecordPlanId').value = planId;
    document.getElementById('auditRecordTitle').textContent = title;
    document.getElementById('auditRecordSupplier').value = defaultSupplierId ? String(defaultSupplierId) : '';
    document.getElementById('auditRecordSupplierName').value = '';
    document.getElementById('auditRecordProtocolNo').value = '';
    document.getElementById('auditRecordPdf').value = '';
    document.getElementById('auditRecordModal').style.display = 'flex';
}

function openAuditProtocolModal(recordId, title) {
    document.getElementById('auditProtocolRecordId').value = recordId;
    document.getElementById('auditProtocolTitle').textContent = title;
    document.getElementById('auditProtocolNo').value = '';
    document.getElementById('auditProtocolPdf').value = '';
    document.getElementById('auditProtocolModal').style.display = 'flex';
}

window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};
window.CMMS_ACTIONS.applyAuditCategoryDefaults = applyAuditCategoryDefaults;
window.CMMS_ACTIONS.openAuditRecordModal = openAuditRecordModal;
window.CMMS_ACTIONS.openAuditProtocolModal = openAuditProtocolModal;

applyAuditCategoryDefaults();
})();
