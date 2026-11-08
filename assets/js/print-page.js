// Tisková stránka štítku (QR): tlačítko Vytisknout
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('printBtn');
    if (btn) btn.addEventListener('click', function () { window.print(); });
});
