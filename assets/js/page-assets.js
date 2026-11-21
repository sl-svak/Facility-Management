(function () {
function printQrCode(qrUrl, assetName) {
    var win = window.open('', '_blank', 'width=400,height=550');
    if (!win) return;
    var doc = win.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title></title><style>' +
        'body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }' +
        'h2 { margin-bottom: 20px; color: #2c3e50; font-size: 24px; word-wrap: break-word; }' +
        'img { width: 250px; height: 250px; background: #fff; border: 1px solid #ccc; padding: 10px; border-radius: 8px; }' +
        'button { padding: 12px 24px; font-size: 16px; cursor: pointer; background: #2980b9; color: white; border: none; border-radius: 6px; margin-top: 20px; }' +
        '@media print { button { display: none; } }' +
        '</style></head><body><h2></h2><img alt="QR Kód"><br><button>🖨️ Vytisknout štítek</button></body></html>');
    doc.close();
    // Název se vkládá jako text (ne jako HTML), takže nelze vložit škodlivý kód
    doc.title = 'Tisk štítku: ' + assetName;
    doc.querySelector('h2').textContent = assetName;
    doc.querySelector('img').src = new URL(qrUrl, window.location.href).href;
    doc.querySelector('button').onclick = function () { win.print(); };
}

window.CMMS_ACTIONS = window.CMMS_ACTIONS || {};
window.CMMS_ACTIONS.printQrCode = printQrCode;
})();
