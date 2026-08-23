<?php
// Načtení globálního nastavení pro hlavičku a menu
require_once APP_ROOT . '/app/Models/SettingModel.php';
$globalAppName = SettingModel::get('app_name', 'CMMS Cosmonde');
$globalFavicon = SettingModel::get('favicon_path', '');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- DYNAMICKÝ NÁZEV A FAVICONA -->
    <title><?= htmlspecialchars($globalAppName) ?></title>
    <?php if (!empty($globalFavicon)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($globalFavicon) ?>">
    <?php endif; ?>
    
    <!-- Google Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
    <!-- NÁŠ HLAVNÍ CSS SOUBOR -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Specifické styly pouze pro rozvržení hlavní stránky (kostry) a menu */
        :root {
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --text-light: #ecf0f1;
        }
        
        body { display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 250px; background: var(--sidebar-bg); color: var(--text-light); display: flex; flex-direction: column; transition: 0.3s; z-index: 1000; }
        
        .sidebar-header { padding: 15px 20px; font-size: 1.5em; font-weight: bold; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .logout-btn { color: #e74c3c; text-decoration: none; display: flex; align-items: center; padding: 5px; border-radius: 4px; transition: 0.2s; }
        .logout-btn:hover { color: #c0392b; background: rgba(255,255,255,0.1); }
        .menu-toggle { display: none; background: none; border: none; color: white; cursor: pointer; padding: 5px; margin-right: 10px; }

        .nav-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        .nav-menu li a { display: flex; align-items: center; padding: 15px 20px; color: var(--text-light); text-decoration: none; transition: 0.2s; border-left: 4px solid transparent; }
        .nav-menu li a:hover, .nav-menu li a.active { background: var(--sidebar-hover); border-left-color: var(--primary); }
        .nav-menu li a .material-symbols-outlined { margin-right: 15px; }
        
        .user-info { padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.9em; display: flex; align-items: center; justify-content: center; text-align: center; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { background: #fff; padding: 15px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-wrapper { padding: 20px; flex-grow: 1; }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; flex-direction: column; }
            .sidebar-header { padding: 10px 15px; }
            .menu-toggle { display: block; }
            
            .nav-menu, .user-info { display: none; width: 100%; background: var(--sidebar-bg); }
            
            .sidebar.open .nav-menu, .sidebar.open .user-info { display: flex; flex-direction: column; }
            
            .nav-menu li a { padding: 12px 20px; border-left: none; }
            
            .topbar { padding: 10px 15px; }
            .topbar h2 { font-size: 1.1em; }
            .content-wrapper { padding: 10px; }
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display: flex; align-items: center;">
                <button class="menu-toggle" id="menuToggle" title="Zobrazit/Skrýt menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <!-- DYNAMICKÝ NÁZEV APLIKACE V MENU -->
                <span><?= htmlspecialchars($globalAppName) ?></span>
            </div>
            <a href="index.php?page=logout" class="logout-btn" title="Odhlásit se"><span class="material-symbols-outlined">logout</span></a>
        </div>
        
        <ul class="nav-menu">
            <li><a href="index.php?page=dashboard"><span class="material-symbols-outlined">dashboard</span> <span class="text">Dashboard</span></a></li>
            
            <!-- PRÁVA PRO DISPEČERA I ADMINA -->
            <?php if (Auth::isDispatcher()): ?>
                <li><a href="index.php?page=assets"><span class="material-symbols-outlined">precision_manufacturing</span> <span class="text">Zařízení a stroje</span></a></li>
                <li><a href="index.php?page=forms"><span class="material-symbols-outlined">design_services</span> <span class="text">Šablony formulářů</span></a></li>
                <li><a href="index.php?page=plans"><span class="material-symbols-outlined">calendar_month</span> <span class="text">Plánování údržby</span></a></li>
                <li><a href="index.php?page=reports"><span class="material-symbols-outlined">picture_as_pdf</span> <span class="text">PDF Reporty</span></a></li>
            <?php endif; ?>

            <!-- PRÁVA STRIKTNĚ POUZE PRO ADMINA -->
            <?php if (Auth::isAdmin()): ?>
                <li><a href="index.php?page=users"><span class="material-symbols-outlined">group</span> <span class="text">Správa uživatelů</span></a></li>
                <!-- NOVÁ POLOŽKA: NASTAVENÍ -->
                <li><a href="index.php?page=settings"><span class="material-symbols-outlined">settings</span> <span class="text">Nastavení</span></a></li>
            <?php endif; ?>

            <!-- PŘÍSTUPNÉ VŠEM (VČETNĚ TECHNIKŮ) -->
            <li><a href="index.php?page=inspections"><span class="material-symbols-outlined">fact_check</span> <span class="text">Záznamy a revize</span></a></li>
            <li><a href="index.php?page=tickets"><span class="material-symbols-outlined">assignment_late</span> <span class="text">Úkoly a závady</span></a></li>
        </ul>
        
        <div class="user-info">
            <span>
                <span class="material-symbols-outlined" style="vertical-align: middle; font-size:1.2em;">account_circle</span> 
                <?= htmlspecialchars($_SESSION['first_name'] ?? 'Uživatel') ?>
                <br>
                <?php 
                    // Rozlišení barvy a názvu podle role aktuálního uživatele
                    $role_name = 'Technik';
                    $role_color = '#95a5a6'; // Šedá pro technika
                    
                    if (Auth::isAdmin()) {
                        $role_name = 'Administrátor';
                        $role_color = '#e74c3c'; // Červená pro admina
                    } elseif (Auth::isDispatcher()) {
                        $role_name = 'Dispečer';
                        $role_color = '#f39c12'; // Oranžová pro dispečera
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
            <h2 style="margin:0; font-size: 1.2em; color: #333;"><?= htmlspecialchars($pageTitle ?? 'Přehled') ?></h2>
        </header>
        <div class="content-wrapper">
            <?= $content ?>
        </div>
    </main>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>
