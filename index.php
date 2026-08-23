<?php
session_start();

// nastavení blokování / zobrazení chybových hlášení
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// 1. Kontrola konfigurace (Instalátor)
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    header('Location: install.php');
    exit;
}

define('APP_ROOT', __DIR__);
require_once $configFile;

// -----------------------------------------------------------------------------
// 2. CHYTRÝ AUTOLOADER (Automatické načítání tříd)
// Tímto nahrazujeme všechny předchozí 'require_once'
// -----------------------------------------------------------------------------
spl_autoload_register(function ($class_name) {
    $directories = [
        APP_ROOT . '/app/Core/',
        APP_ROOT . '/app/Models/',
        APP_ROOT . '/app/Controllers/'
    ];
    
    foreach ($directories as $directory) {
        if (file_exists($directory . $class_name . '.php')) {
            require_once $directory . $class_name . '.php';
            return;
        }
    }
});

// -----------------------------------------------------------------------------
// 3. ZPRACOVÁNÍ ODHLÁŠENÍ A PŘIHLÁŠENÍ (LOGIN LOGIKA)
// -----------------------------------------------------------------------------
$page = $_GET['page'] ?? 'dashboard';

if ($page === 'logout') {
    Auth::logout();
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'login') {
    if (Auth::isLoggedIn()) {
        header('Location: index.php?page=dashboard');
        exit;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (Auth::login($username, $password)) {
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php?page=dashboard';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Neplatné přihlašovací údaje nebo neaktivní účet.';
        }
    }
    
    require APP_ROOT . '/app/Views/login.php';
    exit; 
}

// -----------------------------------------------------------------------------
// 4. OCHRANA PŘÍSTUPU PRO NEPŘIHLÁŠENÉ NÁVŠTĚVNÍKY
// -----------------------------------------------------------------------------
if (!Auth::isLoggedIn()) {
    if ($page !== 'dashboard') {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    }
    header('Location: index.php?page=login');
    exit;
}

// -----------------------------------------------------------------------------
// 5. GLOBÁLNÍ FUNKCE PRO VYKRESLENÍ ŠABLONY (VIEW)
// -----------------------------------------------------------------------------
function renderView($viewName, $variables = []) {
    extract($variables); 
    ob_start(); 
    $viewPath = APP_ROOT . "/app/Views/{$viewName}.php";
    
    if (file_exists($viewPath)) {
        require $viewPath;
    } else {
        echo "<h2>Chyba 404: Šablona {$viewName} nenalezena.</h2>";
    }
    
    $content = ob_get_clean(); 
    require APP_ROOT . '/app/Views/layout.php';
}

// -----------------------------------------------------------------------------
// 6. ROUTER S OCHRANOU ROLÍ (VYHAZOVAČ)
// -----------------------------------------------------------------------------
// Tříúrovňový systém oprávnění
// Úroveň 1: Technik (Všichni přihlášení)
// Úroveň 2: Dispečer + Admin
// Úroveň 3: Admin
// -----------------------------------------------------------------------------
switch ($page) {
    
    // --- ÚROVEŇ 1: PŘÍSTUP PRO VŠECHNY (Technik, Dispečer, Admin) ---
    case 'dashboard':       DashboardController::index(); break;
    case 'scan':            InspectionController::scan(); break;
    case 'inspection_fill': InspectionController::fill(); break;
    case 'inspection_save': InspectionController::save(); break;
    case 'tickets':         TicketController::index(); break;
    case 'ticket_detail':   TicketController::detail(); break;
    case 'ticket_resolve':  TicketController::resolve(); break;
    case 'inspections':     RecordController::index(); break;
    
    case 'asset_stats':
        require_once APP_ROOT . '/app/Controllers/InspectionController.php';
        InspectionController::stats();
        break;

    // --- ÚROVEŇ 2: PŘÍSTUP POUZE PRO DISPEČERY A ADMINY ---
    case 'assets':          Auth::requireRole(['admin', 'dispatcher']); AssetController::index(); break;
    case 'asset_create':    Auth::requireRole(['admin', 'dispatcher']); AssetController::create(); break;
    case 'asset_delete':    Auth::requireRole(['admin', 'dispatcher']); AssetController::delete(); break;
    
    case 'forms':           Auth::requireRole(['admin', 'dispatcher']); FormController::index(); break;
    case 'form_create':     Auth::requireRole(['admin', 'dispatcher']); FormController::create(); break;
    case 'form_delete':     Auth::requireRole(['admin', 'dispatcher']); FormController::delete(); break;
    
    case 'plans':           Auth::requireRole(['admin', 'dispatcher']); PlanController::index(); break;
    case 'plan_create':     Auth::requireRole(['admin', 'dispatcher']); PlanController::create(); break;
    case 'plan_delete':     Auth::requireRole(['admin', 'dispatcher']); PlanController::delete(); break;
    
    case 'reports':         Auth::requireRole(['admin', 'dispatcher']); ReportController::index(); break;
    case 'report_generate': Auth::requireRole(['admin', 'dispatcher']); ReportController::generate(); break;

    // --- ÚROVEŇ 3: PŘÍSTUP POUZE PRO ADMINY ---
    case 'users':           Auth::requireRole('admin'); UserController::index(); break;
    case 'user_create':     Auth::requireRole('admin'); UserController::create(); break;
    case 'user_edit':       Auth::requireRole('admin'); UserController::edit(); break;
    case 'user_update':     Auth::requireRole('admin'); UserController::update(); break;
    case 'user_delete':     Auth::requireRole('admin'); UserController::delete(); break;
    
    case 'settings':        Auth::requireRole('admin'); SettingsController::index(); break;
    case 'settings_save':   Auth::requireRole('admin'); SettingsController::save(); break;
    
    default:                DashboardController::index(); break;
}
