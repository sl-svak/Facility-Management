    function onScanSuccess(decodedText, decodedResult) {
        // Zastavit kameru ihned po úspěšném skenu (zabrání vícenásobnému načtení)
        html5QrcodeScanner.clear();
        
        // Haptická odezva při načtení
        if (navigator.vibrate) navigator.vibrate([150]);

        // Směrování podle obsahu QR kódu
        // Pokud kód obsahuje celou webovou adresu (např. https://cosmonde.cz/cmms/index.php?page=scan&hash=XYZ)
        if (decodedText.startsWith('http')) {
            window.location.href = decodedText;
        } else {
            // Pokud obsahuje jen samotný HASH z databáze
            window.location.href = 'index.php?page=scan&hash=' + encodeURIComponent(decodedText);
        }
    }

    // Inicializace skeneru s preferencí zadní kamery telefonu
    var html5QrcodeScanner = new Html5QrcodeScanner(
        "qr-reader", 
        { fps: 10, qrbox: {width: 250, height: 250}, aspectRatio: 1.0 }
    );
    html5QrcodeScanner.render(onScanSuccess);
