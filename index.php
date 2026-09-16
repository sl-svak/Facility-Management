<?php
session_start();

// -----------------------------------------------------------------------------
// 0. NASTAVENÍ ZOBRAZOVÁNÍ CHYB (Produkční režim)
// -----------------------------------------------------------------------------
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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
// 2.5 VYNUCENÍ HTTPS Z NASTAVENÍ APLIKACE
// -----------------------------------------------------------------------------
try {
    if (class_exists('SettingModel')) {
        $force_https = SettingModel::get('force_https', '0');

        if ($force_https === '1') {
            $isSecure = false;
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $isSecure = true;
            } 
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $isSecure = true;
            } 
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
                $isSecure = true;
            }

            if (!$isSecure) {
                $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // Tichý pád při inicializaci
}

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
        
        $loginResult = Auth::login($username, $password);
        
        if ($loginResult === 'success') {
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php?page=dashboard';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } elseif ($loginResult === 'blocked') {
            $error = 'Z bezpečnostních důvodů byla vaše IP adresa dočasně zablokována. Zkuste to prosím za 15 minut.';
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
// 4.5 VYNUCENÁ ZMĚNA HESLA
// -----------------------------------------------------------------------------
if (Auth::isLoggedIn() && !empty($_SESSION['force_password_change']) && !in_array($page, ['profile', 'profile_save', 'logout'])) {
    header('Location: index.php?page=profile&forced=1');
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
switch ($page) {
    case 'profile':         UserController::profile(); break;
    case 'profile_preferences': UserController::savePreferences(); break;
    case 'profile_save':    UserController::changePassword(); break;
    case 'qr_reader':       renderView('qr_reader', ['pageTitle' => 'Skenování QR']); break;

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

    case 'assets':          Auth::requireRole(['admin', 'manager']); AssetController::index(); break;
    case 'asset_create':    Auth::requireRole(['admin', 'manager']); AssetController::create(); break;
    case 'asset_delete':    Auth::requireRole(['admin', 'manager']); AssetController::delete(); break;
    case 'asset_edit':      Auth::requireRole(['admin', 'manager']); AssetController::edit(); break;
    case 'asset_update':    Auth::requireRole(['admin', 'manager']); AssetController::update(); break;
    
    case 'departments':        Auth::requireRole(['admin', 'manager']); DepartmentController::index(); break;
    case 'department_create':  Auth::requireRole(['admin', 'manager']); DepartmentController::create(); break;
    case 'department_delete':  Auth::requireRole(['admin', 'manager']); DepartmentController::delete(); break;
    
    case 'forms':           Auth::requireRole(['admin', 'manager']); FormController::index(); break;
    case 'form_create':     Auth::requireRole(['admin', 'manager']); FormController::create(); break;
    case 'form_delete':     Auth::requireRole(['admin', 'manager']); FormController::delete(); break;
    
    case 'plans':           Auth::requireRole(['admin', 'manager']); PlanController::index(); break;
    case 'plan_create':     Auth::requireRole(['admin', 'manager']); PlanController::create(); break;
    case 'plan_delete':     Auth::requireRole(['admin', 'manager']); PlanController::delete(); break;
    
    case 'reports':         Auth::requireRole(['admin', 'manager']); ReportController::index(); break;
    case 'report_generate': Auth::requireRole(['admin', 'manager']); ReportController::generate(); break;

    case 'users':           Auth::requireRole('admin'); UserController::index(); break;
    case 'user_create':     Auth::requireRole('admin'); UserController::create(); break;
    case 'user_edit':       Auth::requireRole('admin'); UserController::edit(); break;
    case 'user_update':     Auth::requireRole('admin'); UserController::update(); break;
    case 'user_delete':     Auth::requireRole('admin'); UserController::delete(); break;
    
    case 'settings':        Auth::requireRole('admin'); SettingsController::index(); break;
    case 'settings_save':   Auth::requireRole('admin'); SettingsController::save(); break;
    
    case 'security_logs':   
        Auth::requireRole('admin'); 
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM login_logs ORDER BY attempt_time DESC LIMIT 200");
        renderView('security_logs', ['pageTitle' => 'Bezpečnostní deník', 'logs' => $stmt->fetchAll()]); 
        break;
        
    default:                DashboardController::index(); break;
}
