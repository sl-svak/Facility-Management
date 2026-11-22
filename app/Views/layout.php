<?php
require_once APP_ROOT . '/app/Models/SettingModel.php';
$globalAppName = SettingModel::get('app_name', 'CMMS Cosmonde');
$globalFavicon = SettingModel::get('favicon_path', '');
$globalAppFont = SettingModel::get('app_font', 'default');

$modInspections = SettingModel::isModuleEnabled('module_inspections');
$modExternalAudits = SettingModel::isModuleEnabled('module_external_audits');
$modOutputTemplates = SettingModel::isModuleEnabled('module_output_templates');
$modTickets = SettingModel::isModuleEnabled('module_tickets');

// 1. NAČTENÍ UŽIVATELSKÝCH PREFERENCÍ
$userPrefs = ['theme' => 'auto', 'font_size' => 'normal', 'qr_mode' => 'auto'];
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['theme'])) {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT theme, font_size, qr_mode FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $prefs = $stmt->fetch();
            if ($prefs) {
                $_SESSION['theme'] = $prefs['theme'];
                $_SESSION['font_size'] = $prefs['font_size'];
                $_SESSION['qr_mode'] = $prefs['qr_mode'];
            }
        } catch (Exception $e) {}
    }
    $userPrefs['theme'] = $_SESSION['theme'] ?? 'auto';
    $userPrefs['font_size'] = $_SESSION['font_size'] ?? 'normal';
    $userPrefs['qr_mode'] = $_SESSION['qr_mode'] ?? 'auto';
}

// Generování CSS tříd pro body tag podle preferencí uživatele
$bodyClasses = [];
if ($userPrefs['theme'] === 'light') $bodyClasses[] = 'theme-light';
if ($userPrefs['theme'] === 'dark') $bodyClasses[] = 'theme-dark';
if ($userPrefs['font_size'] === 'large') $bodyClasses[] = 'font-large';
if ($userPrefs['qr_mode'] === 'auto') $bodyClasses[] = 'qr-auto';
if ($globalAppFont === 'condensed') $bodyClasses[] = 'app-font-condensed';
if ($globalAppFont === 'system') $bodyClasses[] = 'app-font-system';
$bodyClassString = implode(' ', $bodyClasses);

$currentPage = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2c3e50">
    <link rel="manifest" href="manifest.json">

    <title><?= htmlspecialchars((string)$globalAppName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!empty($globalFavicon)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars((string)$globalFavicon, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <?php if ($globalAppFont === 'condensed'): ?>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime('assets/css/style.css') ?: time() ?>">
</head>
<body class="<?= htmlspecialchars($bodyClassString, ENT_QUOTES, 'UTF-8') ?>">
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display: flex; align-items: center;">
                <button class="menu-toggle" id="menuToggle" title="Zobrazit/Skrýt menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <span><?= htmlspecialchars((string)$globalAppName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <form method="POST" action="index.php?page=logout" style="margin: 0;">
                <?= Security::csrfField() ?>
                <button type="submit" class="logout-btn" title="Odhlásit se" style="background: none; border: none; cursor: pointer; font: inherit;"><span class="material-symbols-outlined">logout</span></button>
            </form>
        </div>

        <ul class="nav-menu">
            <li><a href="index.php?page=dashboard" class="<?= ($currentPage === 'dashboard') ? 'active' : '' ?>"><span class="material-symbols-outlined">dashboard</span> <span class="text">Dashboard</span></a></li>

            <?php if ($modInspections && $userPrefs['qr_mode'] !== 'hide'): ?>
                <li class="qr-menu-item">
                    <a href="index.php?page=qr_reader" class="<?= ($currentPage === 'qr_reader') ? 'active' : '' ?>"><span class="material-symbols-outlined">qr_code_scanner</span> <span class="text">Čtečka QR kódů</span></a>
                </li>
            <?php endif; ?>

            <?php if (Auth::isManager()): ?>
                <li><a href="index.php?page=departments" class="<?= ($currentPage === 'departments') ? 'active' : '' ?>"><span class="material-symbols-outlined">domain</span> <span class="text">Organizační úseky</span></a></li>
                <li><a href="index.php?page=assets" class="<?= ($currentPage === 'assets') ? 'active' : '' ?>"><span class="material-symbols-outlined">precision_manufacturing</span> <span class="text">Zařízení a stroje</span></a></li>
                <?php if ($modInspections): ?>
                    <li><a href="index.php?page=forms" class="<?= ($currentPage === 'forms') ? 'active' : '' ?>"><span class="material-symbols-outlined">design_services</span> <span class="text">Šablony formulářů</span></a></li>
                    <li><a href="index.php?page=plans" class="<?= ($currentPage === 'plans') ? 'active' : '' ?>"><span class="material-symbols-outlined">calendar_month</span> <span class="text">Plánování údržby</span></a></li>
                <?php endif; ?>
                <?php if ($modOutputTemplates): ?>
                    <li><a href="index.php?page=output_templates" class="<?= (strpos((string)$currentPage, 'output_template') === 0) ? 'active' : '' ?>"><span class="material-symbols-outlined">description</span> <span class="text">Výstupní šablony</span></a></li>
                <?php endif; ?>
                <li><a href="index.php?page=reports" class="<?= ($currentPage === 'reports') ? 'active' : '' ?>"><span class="material-symbols-outlined">picture_as_pdf</span> <span class="text">PDF Reporty</span></a></li>
            <?php endif; ?>

            <?php if ($modExternalAudits): ?>
                <li><a href="index.php?page=external_audits" class="<?= (strpos((string)$currentPage, 'external_audit') === 0) ? 'active' : '' ?>"><span class="material-symbols-outlined">verified</span> <span class="text">Externí revize</span></a></li>
            <?php endif; ?>

            <?php if (Auth::isAdmin()): ?>
                <li><a href="index.php?page=users" class="<?= ($currentPage === 'users') ? 'active' : '' ?>"><span class="material-symbols-outlined">group</span> <span class="text">Správa uživatelů</span></a></li>
                <li><a href="index.php?page=security_logs" class="<?= ($currentPage === 'security_logs') ? 'active' : '' ?>"><span class="material-symbols-outlined">shield</span> <span class="text">Bezpečnostní deník</span></a></li>
                <li><a href="index.php?page=settings" class="<?= ($currentPage === 'settings') ? 'active' : '' ?>"><span class="material-symbols-outlined">settings</span> <span class="text">Nastavení</span></a></li>
            <?php endif; ?>

            <?php if ($modInspections): ?>
                <li><a href="index.php?page=inspections" class="<?= ($currentPage === 'inspections') ? 'active' : '' ?>"><span class="material-symbols-outlined">fact_check</span> <span class="text">Záznamy a revize</span></a></li>
            <?php endif; ?>
            <?php if ($modTickets): ?>
                <li><a href="index.php?page=tickets" class="<?= ($currentPage === 'tickets') ? 'active' : '' ?>"><span class="material-symbols-outlined">assignment_late</span> <span class="text">Úkoly a závady</span></a></li>
            <?php endif; ?>
        </ul>

        <div class="user-info" data-href="index.php?page=profile" title="Upravit profil a heslo">
            <span>
                <span class="material-symbols-outlined" style="vertical-align: middle; font-size:1.2em;">account_circle</span>
                <?= htmlspecialchars((string)($_SESSION['first_name'] ?? 'Uživatel'), ENT_QUOTES, 'UTF-8') ?>
                <br>
                <?php
                    $role_name = 'Technik';
                    $role_color = '#95a5a6';

                    if (Auth::isAdmin()) {
                        $role_name = 'Administrátor';
                        $role_color = '#e74c3c';
                    } elseif (Auth::isManager()) {
                        $role_name = 'Manager';
                        $role_color = '#f39c12';
                    }
                ?>
                <span style="font-size: 0.8em; color: <?= $role_color ?>; font-weight: bold;">
                    <?= $role_name ?>
                </span>
            </span>
        </div>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 style="margin:0; font-size: 1.2em; color: var(--text-main);"><?= htmlspecialchars((string)($pageTitle ?? 'Přehled'), ENT_QUOTES, 'UTF-8') ?></h2>
        </header>
        <div class="content-wrapper">
            <?= $content ?>
        </div>
    </main>

    <script src="assets/js/app.js?v=<?= @filemtime('assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
