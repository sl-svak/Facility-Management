(function () {
function openEditModal(id, period, warning, assetName) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_period').value = period;
    document.getElementById('edit_warning').value = warning;
    document.getElementById('editAssetName').textContent = assetName;
    document.getElementById('editModal').style.display = 'flex';
}

window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};
window.CMMS_ACTIONS.openEditModal = openEditModal;
})();
