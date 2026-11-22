<?php if (!defined('APP_ROOT')) exit; ?>

<div class="card card-primary-top card-narrow">
    <h3 class="card-title">
        <span class="material-symbols-outlined" style="color: var(--text-muted);">toggle_off</span>
        <?= htmlspecialchars((string)($moduleTitle ?? 'Modul není aktivní'), ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <p class="text-muted">
        Tento modul je v nastavení systému vypnutý. Vaše existující data v databázi zůstávají zachována.
    </p>
    <div class="form-actions">
        <a href="index.php?page=dashboard" class="btn btn-muted">Zpět na přehled</a>
        <?php if (Auth::isAdmin()): ?>
            <a href="index.php?page=settings" class="btn btn-primary"><span class="material-symbols-outlined">settings</span> Nastavení modulů</a>
        <?php endif; ?>
    </div>
</div>
