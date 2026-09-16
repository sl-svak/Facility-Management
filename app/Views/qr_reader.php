<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top" style="max-width: 600px; margin: 0 auto;">
    
    <?php if (isset($_GET['saved'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; text-align: center; font-weight: bold;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span> 
            Data uložena. Můžete skenovat další zařízení.
        </div>
    <?php endif; ?>

    <h3 style="margin-top: 0; color: var(--primary); text-align: center;">
        <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">qr_code_scanner</span> 
        Skener zařízení
    </h3>
    <p style="color: var(--text-muted); font-size: 0.9em; text-align: center; margin-bottom: 20px;">
        Povolte přístup ke kameře a namiřte telefon na QR kód zařízení.
    </p>
    
    <div id="qr-reader" style="width: 100%; border-radius: 8px; overflow: hidden; border: 2px solid var(--border-color); background: #000;"></div>
</div>

<style>
    /* Stylování prvků knihovny, aby zapadly do našeho designu */
    #qr-reader button {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 15px;
        font-family: inherit;
        font-weight: bold;
    }
    #qr-reader a { color: var(--info); display: none; }
    #qr-reader__scan_region { min-height: 250px; }
</style>

<!-- Načtení knihovny pro čtení QR -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
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
</script>
