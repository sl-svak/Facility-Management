<?php
require_once APP_ROOT . '/app/Models/SettingModel.php';
$globalAppName = SettingModel::get('app_name', 'CMMS Cosmonde');
$globalFavicon = SettingModel::get('favicon_path', '');
$globalAppFont = SettingModel::get('app_font', 'default'); 

// 1. NAČTENÍ UŽIVATELSKÝch PREFERENCÍ
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
$bodyClassString = implode(' ', $bodyClasses);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2c3e50">
    <link rel="manifest" href="manifest.json">
    
    <title><?= htmlspecialchars($globalAppName) ?></title>
    <?php if (!empty($globalFavicon)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($globalFavicon) ?>">
    <?php endif; ?>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css') ?>">
</head>
<body class="<?= htmlspecialchars($bodyClassString) ?>">
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display: flex; align-items: center;">
                <button class="menu-toggle" id="menuToggle" title="Zobrazit/Skrýt menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <span><?= htmlspecialchars($globalAppName) ?></span>
            </div>
            <a href="index.php?page=logout" class="logout-btn" title="Odhlásit se"><span class="material-symbols-outlined">logout</span></a>
        </div>
        
        <ul class="nav-menu">
            <li><a href="index.php?page=dashboard" class="<?= (!isset($_GET['page']) || $_GET['page'] === 'dashboard') ? 'active' : '' ?>"><span class="material-symbols-outlined">dashboard</span> <span class="text">Dashboard</span></a></li>
            
            <?php if ($userPrefs['qr_mode'] !== 'hide'): ?>
                <li class="qr-menu-item">
                    <a href="index.php?page=qr_reader" class="<?= (isset($_GET['page']) && $_GET['page'] === 'qr_reader') ? 'active' : '' ?>"><span class="material-symbols-outlined">qr_code_scanner</span> <span class="text">Čtečka QR kódů</span></a>
                </li>
            <?php endif; ?>
            
            <?php if (Auth::isManager()): ?>
				<li><a href="index.php?page=departments" class="<?= (isset($_GET['page']) && $_GET['page'] === 'departments') ? 'active' : '' ?>"><span class="material-symbols-outlined">domain</span> <span class="text">Organizační úseky</span></a></li>
                <li><a href="index.php?page=assets" class="<?= (isset($_GET['page']) && $_GET['page'] === 'assets') ? 'active' : '' ?>"><span class="material-symbols-outlined">precision_manufacturing</span> <span class="text">Zařízení a stroje</span></a></li>
                <li><a href="index.php?page=forms" class="<?= (isset($_GET['page']) && $_GET['page'] === 'forms') ? 'active' : '' ?>"><span class="material-symbols-outlined">design_services</span> <span class="text">Šablony formulářů</span></a></li>
                <li><a href="index.php?page=plans" class="<?= (isset($_GET['page']) && $_GET['page'] === 'plans') ? 'active' : '' ?>"><span class="material-symbols-outlined">calendar_month</span> <span class="text">Plánování údržby</span></a></li>
                <li><a href="index.php?page=reports" class="<?= (isset($_GET['page']) && $_GET['page'] === 'reports') ? 'active' : '' ?>"><span class="material-symbols-outlined">picture_as_pdf</span> <span class="text">PDF Reporty</span></a></li>
            <?php endif; ?>

            <?php if (Auth::isAdmin()): ?>
                <li><a href="index.php?page=users" class="<?= (isset($_GET['page']) && $_GET['page'] === 'users') ? 'active' : '' ?>"><span class="material-symbols-outlined">group</span> <span class="text">Správa uživatelů</span></a></li>
                <li><a href="index.php?page=security_logs" class="<?= (isset($_GET['page']) && $_GET['page'] === 'security_logs') ? 'active' : '' ?>"><span class="material-symbols-outlined">shield</span> <span class="text">Bezpečnostní deník</span></a></li>
                <li><a href="index.php?page=settings" class="<?= (isset($_GET['page']) && $_GET['page'] === 'settings') ? 'active' : '' ?>"><span class="material-symbols-outlined">settings</span> <span class="text">Nastavení</span></a></li>
            <?php endif; ?>

            <li><a href="index.php?page=inspections" class="<?= (isset($_GET['page']) && $_GET['page'] === 'inspections') ? 'active' : '' ?>"><span class="material-symbols-outlined">fact_check</span> <span class="text">Záznamy a revize</span></a></li>
            <li><a href="index.php?page=tickets" class="<?= (isset($_GET['page']) && $_GET['page'] === 'tickets') ? 'active' : '' ?>"><span class="material-symbols-outlined">assignment_late</span> <span class="text">Úkoly a závady</span></a></li>
        </ul>
        
        <div class="user-info" onclick="window.location.href='index.php?page=profile'" title="Upravit profil a heslo">
            <span>
                <span class="material-symbols-outlined" style="vertical-align: middle; font-size:1.2em;">account_circle</span> 
                <?= htmlspecialchars($_SESSION['first_name'] ?? 'Uživatel') ?>
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
            <h2 style="margin:0; font-size: 1.2em; color: var(--text-main);"><?= htmlspecialchars($pageTitle ?? 'Přehled') ?></h2>
        </header>
        <div class="content-wrapper">
            <?= $content ?>
        </div>
    </main>

    <!-- PŘIDÁNO: Načtení globálního JavaScriptu pro celý systém -->
    <script src="assets/js/app.js?v=<?= filemtime('assets/js/app.js') ?>"></script>
</body>
</html>
