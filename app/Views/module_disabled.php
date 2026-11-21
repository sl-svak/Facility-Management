<div class="card">
    <h2><?= htmlspecialchars((string)($moduleTitle ?? 'Modul není aktivní'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p style="color: #555; margin: 12px 0 18px 0;">
        Tento modul je v globálním nastavení systému aktuálně vypnutý, aby rozhraní zůstalo přehledné.
        Vaše existující data v databázi zůstávají bezpečně zachována.
    </p>
    <?php if (Auth::isAdmin()): ?>
        <a href="index.php?page=settings" class="btn btn-primary">Přejít do nastavení modulů</a>
    <?php endif; ?>
    <a href="index.php?page=dashboard" class="btn" style="margin-left: 8px;">Zpět na přehled</a>
</div>
