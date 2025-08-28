<?php
/**
 * AVOTI Task Management sistēmas konfigurācijas fails
 */

// Sesijas konfigurācija (tikai ja nav jau startēta un nav CLI režīms)
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    // Pārbaudīt vai pieprasījums nāk no Android aplikācijas
    $is_android_app = false;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    if (strpos($user_agent, 'CapacitorWebView') !== false || 
        strpos($origin, 'capacitor://') !== false ||
        strpos($referer, 'capacitor://') !== false ||
        strpos($user_agent, 'wv') !== false ||
        strpos($user_agent, 'AVOTI TMS') !== false ||
        strpos($user_agent, 'Android') !== false && strpos($user_agent, 'wv') !== false) {
        $is_android_app = true;
    }

    if ($is_android_app) {
        // Android aplikācijas konfigurācija
        ini_set('session.cookie_httponly', 0); // Atļaut JavaScript piekļuvi
        ini_set('session.cookie_secure', 0);   // HTTP (nevis HTTPS)
        ini_set('session.cookie_samesite', 'Lax'); // Mainīts no 'None' uz 'Lax'
        ini_set('session.cookie_domain', ''); // Tukšs domain Android
        ini_set('session.cookie_path', '/');
        ini_set('session.save_path', sys_get_temp_dir());
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
        ini_set('session.cache_limiter', 'nocache');
    } else {
        // Pārlūkprogrammas konfigurācija
        ini_set('session.cookie_httponly', 1); // Drošāks pārlūkprogrammām
        ini_set('session.cookie_secure', 0);   // HTTP (nevis HTTPS)
        ini_set('session.cookie_samesite', 'Lax'); // Darbojas ar HTTP
    }

    // Kopējie iestatījumi
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 86400); // 24 stundas
    ini_set('session.gc_maxlifetime', 86400);

    session_start();
}

// CORS headers Capacitor aplikācijai
$allowed_origins = [
    'capacitor://localhost',
    'http://localhost',
    'http://192.168.2.11',
    'https://192.168.2.11',
    'https://localhost',
    'capacitor-electron://-'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins) || strpos($origin, 'capacitor://') === 0) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cookie, Set-Cookie");
header("Access-Control-Expose-Headers: Set-Cookie");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Datubāzes konfigurācija
define('DB_HOST', 'localhost');
define('DB_NAME', 'mehu_uzd');
define('DB_USER', 'tasks');
define('DB_PASS', 'Astalavista1920');
define('DB_CHARSET', 'utf8mb4');

// Sistēmas konfigurācija
// Lokālam darbam ar ngrok - mainiet uz savu ngrok URL
define('SITE_URL', 'http://192.168.2.11/mehi'); // Nomainiet uz sava servera IP
define('SITE_NAME', 'Uzdevumu pārvaldības sistēma');
define('COMPANY_NAME', 'SIA "AVOTI"');

// Failu augšupielādes konfigurācija
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx','csv']);

// Laika zonas uzstādīšana
date_default_timezone_set('Europe/Riga');

// Latviešu lokalizācija
setlocale(LC_TIME, 'lv_LV.UTF-8', 'lv_LV', 'latvian');

// Kļūdu ziņošanas uzstādīšana (izstrādes vidē)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOKĀLĀS KONFIGURĀCIJAS PIEZĪMES:
// 1. Mainiet DB_USER un DB_PASS uz jūsu MySQL credentials
// 2. Atjauniniet SITE_URL uz jūsu lokālo ceļu
// 3. Pārbaudiet Apache mod_rewrite moduli
// 4. Iestatiet 777 tiesības uploads/ direktorijam

// UTF-8 kodējuma uzstādīšana
ini_set('default_charset', 'utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');

// Lietotāju lomas
define('ROLE_ADMIN', 'Administrators');
define('ROLE_MANAGER', 'Menedžeris');
define('ROLE_OPERATOR', 'Operators');
define('ROLE_MECHANIC', 'Mehāniķis');

// Uzdevumu statusi
define('TASK_STATUS_NEW', 'Jauns');
define('TASK_STATUS_IN_PROGRESS', 'Procesā');
define('TASK_STATUS_COMPLETED', 'Pabeigts');
define('TASK_STATUS_CANCELLED', 'Atcelts');
define('TASK_STATUS_POSTPONED', 'Atlikts');

// Problēmu statusi
define('PROBLEM_STATUS_NEW', 'Jauna');
define('PROBLEM_STATUS_REVIEWED', 'Apskatīta');
define('PROBLEM_STATUS_CONVERTED', 'Pārvērsta uzdevumā');
define('PROBLEM_STATUS_CANCELLED', 'Atcelta');

// Prioritātes
define('PRIORITY_LOW', 'Zema');
define('PRIORITY_MEDIUM', 'Vidēja');
define('PRIORITY_HIGH', 'Augsta');
define('PRIORITY_CRITICAL', 'Kritiska');

// Datubāzes pieslēgšanas klase
class Database {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Datubāzes pieslēgšanās kļūda: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// Globālā datubāzes pieslēgšana
$db = new Database();
$pdo = $db->getConnection();

// Pievienot jaunās kolonnas, ja tās neeksistē
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM lietotaji LIKE 'nokluseta_vietas_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE lietotaji ADD COLUMN nokluseta_vietas_id INT(11) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE lietotaji ADD FOREIGN KEY (nokluseta_vietas_id) REFERENCES vietas(id) ON DELETE SET NULL");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM lietotaji LIKE 'noklusetas_iekartas_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE lietotaji ADD COLUMN noklusetas_iekartas_id INT(11) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE lietotaji ADD FOREIGN KEY (noklusetas_iekartas_id) REFERENCES iekartas(id) ON DELETE SET NULL");
    }
} catch (PDOException $e) {
    error_log("Datubāzes kolonnu pievienošanas kļūda: " . $e->getMessage());
}


// Palīgfunkcijas
function getCurrentUser() {
    global $pdo;
    if (isset($_SESSION['lietotaja_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM lietotaji WHERE id = ? AND statuss = 'Aktīvs'");
        $stmt->execute([$_SESSION['lietotaja_id']]);
        return $stmt->fetch();
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['lietotaja_id']);
}

function hasRole($roles) {
    $user = getCurrentUser();
    if (!$user) return false;

    if (is_string($roles)) {
        return $user['loma'] === $roles;
    }

    if (is_array($roles)) {
        return in_array($user['loma'], $roles);
    }

    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'd.m.Y H:i') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function formatLatvianDate($date) {
    if (!$date) return '';
    $timestamp = strtotime($date);

    $latvian_months = [
        1 => 'janvāris', 2 => 'februāris', 3 => 'marts', 4 => 'aprīlis', 
        5 => 'maijs', 6 => 'jūnijs', 7 => 'jūlijs', 8 => 'augusts',
        9 => 'septembris', 10 => 'oktobris', 11 => 'novembris', 12 => 'decembris'
    ];

    $day = date('d', $timestamp);
    $month = $latvian_months[date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return "$day. $month $year";
}

function formatDateOnly($date) {
    if (!$date) return '';
    return date('d.m.Y', strtotime($date));
}

function formatDateTime($date) {
    if (!$date) return '';
    return date('d.m.Y H:i', strtotime($date));
}

function formatTimeOnly($date) {
    if (!$date) return '';
    return date('H:i', strtotime($date));
}

function formatLatvianDatetime($date) {
    if (!$date) return '';
    $timestamp = strtotime($date);

    $latvian_months = [
        1 => 'janvāris', 2 => 'februāris', 3 => 'marts', 4 => 'aprīlis', 
        5 => 'maijs', 6 => 'jūnijs', 7 => 'jūlijs', 8 => 'augusts',
        9 => 'septembris', 10 => 'oktobris', 11 => 'novembris', 12 => 'decembris'
    ];

    $day = date('d', $timestamp);
    $month = $latvian_months[date('n', $timestamp)];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);

    return "$day. $month $year, $time";
}

function getPriorityClass($priority) {
    switch ($priority) {
        case PRIORITY_CRITICAL:
            return 'priority-critical';
        case PRIORITY_HIGH:
            return 'priority-high';
        case PRIORITY_MEDIUM:
            return 'priority-medium';
        case PRIORITY_LOW:
            return 'priority-low';
        default:
            return 'priority-medium';
    }
}

function getStatusClass($status) {
    switch ($status) {
        case TASK_STATUS_NEW:
            return 'status-new';
        case TASK_STATUS_IN_PROGRESS:
            return 'status-progress';
        case TASK_STATUS_COMPLETED:
            return 'status-completed';
        case TASK_STATUS_CANCELLED:
            return 'status-cancelled';
        case TASK_STATUS_POSTPONED:
            return 'status-postponed';
        default:
            return 'status-new';
    }
}

function createNotification($lietotaja_id, $virsraksts, $zinojums, $tips, $saistitas_tips = null, $saistitas_id = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pazinojumi 
            (lietotaja_id, virsraksts, zinojums, tips, saistitas_tips, saistitas_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$lietotaja_id, $virsraksts, $zinojums, $tips, $saistitas_tips, $saistitas_id]);
    } catch (PDOException $e) {
        error_log("Kļūda izveidojot paziņojumu: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotificationCount($lietotaja_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pazinojumi WHERE lietotaja_id = ? AND skatīts = 0");
        $stmt->execute([$lietotaja_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// CSRF aizsardzība
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Lapas galvenes funkcionālitāte
function getPageTitle($title = '') {
    $baseTitle = SITE_NAME;
    return $title ? "$title - $baseTitle" : $baseTitle;
}

// Kļūdu ziņojumu sistēma
function setFlashMessage($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    if (isset($_SESSION['flash'])) {
        $messages = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $messages;
    }
    return [];
}

// Telegram Notifications konfigurācija
define('TELEGRAM_NOTIFICATIONS_ENABLED', true);
define('TELEGRAM_BOT_TOKEN', '8126777622:AAFBvEIT6qxGnkYaaXXE-KQ-I_bzK3JpDyg'); // Jūsu bot token

// Telegram helper funkcijas
function sendTaskTelegramNotification($lietotajaId, $taskTitle, $taskId, $type = 'new_task') {
    global $telegramManager;

    if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
        return ['success' => false, 'error' => 'Telegram notifications disabled'];
    }

    if (!isset($telegramManager)) {
        return ['success' => false, 'error' => 'Telegram Manager not initialized'];
    }

    try {
        $result = $telegramManager->sendTaskNotification($lietotajaId, $taskTitle, $taskId, $type);
        error_log("Telegram task notification result for user $lietotajaId: " . json_encode($result));
        return $result;
    } catch (Exception $e) {
        error_log("Error sending Telegram task notification: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}



/**
 * Nosūtīt Telegram paziņojumu par problēmu (wrapper funkcija) 
 */
function sendProblemTelegramNotification($userId, $problemTitle, $problemId) {
    global $telegramManager;
    
    if (!isset($telegramManager)) {
        return ['success' => false, 'error' => 'Telegram Manager not initialized'];
    }
    
    try {
        return $telegramManager->sendProblemNotification($userId, $problemTitle, $problemId);
    } catch (Exception $e) {
        error_log("sendProblemTelegramNotification error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Nosūtīt push paziņojumu par problēmu
 */
function sendProblemPushNotification($problemId, $problemTitle) {
    global $pdo;
    
    try {
        // Iegūt problēmas informāciju
        $stmt = $pdo->prepare("
            SELECT p.*, l.vards as zinotaja_vards, l.uzvards as zinotaja_uzvards
            FROM problemas p
            LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
            WHERE p.id = ?
        ");
        $stmt->execute([$problemId]);
        $problem = $stmt->fetch();
        
        if (!$problem) {
            return ['success' => false, 'error' => 'Problem not found'];
        }
        
        // Iegūt visus aktīvos lietotājus (menedžerus un administratorus)
        $stmt = $pdo->query("
            SELECT id FROM lietotaji 
            WHERE loma IN ('Administrators', 'Menedžeris') 
            AND statuss = 'Aktīvs'
        ");
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $successCount = 0;
        $errors = [];
        
        foreach ($recipients as $userId) {
            try {
                createNotification(
                    $userId,
                    'Jauna problēma ziņota',
                    "Operators {$problem['zinotaja_vards']} {$problem['zinotaja_uzvards']} ir ziņojis jaunu problēmu: $problemTitle",
                    'Jauna problēma',
                    'Problēma',
                    $problemId
                );
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "Failed to create notification for user $userId: " . $e->getMessage();
                error_log("Push notification error for user $userId: " . $e->getMessage());
            }
        }
        
        return [
            'success' => $successCount > 0,
            'sent' => $successCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        error_log("Error sending problem push notification: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}



/**
 * Nosūtīt Telegram paziņojumu par statusa maiņu
 */
function sendStatusChangePushNotification($taskId, $mechanicId, $taskTitle, $newStatus, $comment = '') {
    // Šī funkcija var tikt paplašināta nākotnē ar push paziņojumiem
    // Pagaidām vienkārši logojam
    error_log("Status change notification for task $taskId, mechanic $mechanicId: $newStatus");

    // Var pievienot Telegram paziņojumu arī par statusa maiņu
    if ($newStatus === 'Pabeigts') {
        sendTaskTelegramNotification($mechanicId, $taskTitle, $taskId, 'task_completed');
    }
}


// Function removed - using the one in my_tasks.php instead to avoid redeclaration

// Funkcija kritisko uzdevumu izveidošanai no problēmas
function createCriticalTaskFromProblem($problemId, $problemData) {
    global $pdo;
    
    try {
        // Iegūt visus aktīvos mehāniķus un pārbaudīt darba grafiku
        $today = date('Y-m-d');
        $current_hour = intval(date('H'));
        
        // Noteikt pašreizējo maiņu
        $current_shift = null;
        if ($current_hour >= 7 && $current_hour < 16) {
            $current_shift = 'R'; // Rīta maiņa 07:00-16:00
        } elseif ($current_hour >= 16 || $current_hour < 1) {
            $current_shift = 'V'; // Vakara maiņa 16:00-01:00
        }
        
        error_log("CRITICAL TASK CREATION: Current shift: " . ($current_shift ?? 'none') . ", hour: $current_hour, date: $today");
        
        $stmt = $pdo->prepare("
            SELECT l.id, CONCAT(l.vards, ' ', l.uzvards) as pilns_vards, l.vards, l.uzvards,
                   CASE 
                       WHEN dg.maina IS NOT NULL THEN dg.maina
                       ELSE 'Nav_grafika'
                   END as maina_status
            FROM lietotaji l
            LEFT JOIN darba_grafiks dg ON l.id = dg.lietotaja_id AND dg.datums = ?
            WHERE l.loma = 'Mehāniķis' 
            AND l.statuss = 'Aktīvs'
            AND (
                (dg.maina = ? AND dg.maina != 'B') OR  -- Strādā pašreizējā maiņā (nav brīvdiena)
                (dg.maina IS NULL)                      -- Nav grafika (pieņemam ka strādā)
            )
            AND COALESCE(dg.maina, 'Nav_grafika') != 'B'  -- Papildu pārbaude - izslēgt brīvdienas
            ORDER BY l.vards, l.uzvards
        ");
        $stmt->execute([$today, $current_shift]);
        $available_mechanics = $stmt->fetchAll();
        
        error_log("CRITICAL: First query found " . count($available_mechanics) . " mechanics with shift $current_shift");
        
        // Ja nav atrasti mehāniķi pašreizējā maiņā, iegūt visus aktīvos mehāniķus
        if (empty($available_mechanics)) {
            error_log("CRITICAL: No mechanics found for current shift ($current_shift), getting all active mechanics");
            $stmt = $pdo->prepare("
                SELECT l.id, CONCAT(l.vards, ' ', l.uzvards) as pilns_vards, l.vards, l.uzvards, 
                       COALESCE(dg.maina, 'Nav_grafika') as maina_status
                FROM lietotaji l
                LEFT JOIN darba_grafiks dg ON l.id = dg.lietotaja_id AND dg.datums = ?
                WHERE l.loma = 'Mehāniķis' 
                AND l.statuss = 'Aktīvs'
                AND COALESCE(dg.maina, 'Nav_grafika') != 'B'  -- Izslēgt mehāniķus ar brīvdienu
                ORDER BY l.vards, l.uzvards
            ");
            $stmt->execute([$today]);
            $available_mechanics = $stmt->fetchAll();
            error_log("CRITICAL: Found " . count($available_mechanics) . " total active mechanics");
        }
        
        if (empty($available_mechanics)) {
            error_log("CRITICAL: No active mechanics found at all!");
            return false;
        }
        
        error_log("CRITICAL TASK CREATION: Found " . count($available_mechanics) . " available mechanics for shift: $current_shift at time: " . date('H:i:s'));
        foreach ($available_mechanics as $mech) {
            error_log("Available mechanic: {$mech['pilns_vards']} (ID: {$mech['id']}, Shift status: {$mech['maina_status']})");
        }
        
        $createdTasks = [];
        
        foreach ($available_mechanics as $mechanic) {
            // Izveidot uzdevumu katram pieejamajam mehāniķim
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumi 
                (nosaukums, apraksts, prioritate, statuss, piešķirts_id, problemas_id, 
                 vietas_id, iekartas_id, paredzamais_ilgums, izveidoja_id, jabeidz_lidz, daudziem_mehāniķiem)
                VALUES (?, ?, 'Kritiska', 'Jauns', ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
            ");
            
            $success = $stmt->execute([
                "🚨 KRITISKS: " . $problemData['nosaukums'],
                "⚠️ KRITISKA PROBLĒMA - TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! ⚠️\n\n" . 
                $problemData['apraksts'] . 
                "\n\n🔴 ŠIS IR KRITISKS UZDEVUMS - RAŽOŠANA VAR BŪT APTURĒTA!\n" .
                "🔴 PIRMAIS MEHĀNIĶIS, KURŠ PABEIGS UZDEVUMU, AUTOMĀTISKI NOŅEMS TO CITIEM!\n" .
                "🔴 JA ESAT SĀCIS DARBU, JUMS BŪTU JĀPABEIDZ!",
                $mechanic['id'],
                $problemId,
                $problemData['vietas_id'] ?: null,
                $problemData['iekartas_id'] ?: null,
                $problemData['aptuvenais_ilgums'] ?: 1,
                getCurrentUser()['id'] ?? 1
            ]);
            
            if ($success) {
                $taskId = $pdo->lastInsertId();
                
                $createdTasks[] = [
                    'task_id' => $taskId,
                    'mechanic_id' => $mechanic['id'],
                    'mechanic_name' => $mechanic['pilns_vards'],
                    'shift_status' => $mechanic['maina_status']
                ];
                
                // Izveidot paziņojumu mehāniķim
                createNotification(
                    $mechanic['id'],
                    '🚨 KRITISKS UZDEVUMS!',
                    "Jums piešķirts kritisks uzdevums: " . $problemData['nosaukums'] . ". TŪLĪTĒJA RĪCĪBA NEPIECIEŠAMA! Pirmais kas pabeigs noņems uzdevumu citiem.",
                    'Jauns uzdevums',
                    'Uzdevums',
                    $taskId
                );
                
                // Nosūtīt Telegram paziņojumu
                try {
                    if (isset($GLOBALS['telegramManager'])) {
                        $result = $GLOBALS['telegramManager']->sendTaskNotification($mechanic['id'], "🚨 KRITISKS: " . $problemData['nosaukums'], $taskId, 'new_task');
                        if ($result['success']) {
                            error_log("CRITICAL: Telegram notification sent successfully for task $taskId to mechanic {$mechanic['id']}");
                        } else {
                            error_log("CRITICAL: Failed to send Telegram notification for task $taskId: " . ($result['error'] ?? 'Unknown error'));
                        }
                    } else {
                        error_log("CRITICAL: Telegram Manager not available for task $taskId");
                    }
                } catch (Exception $e) {
                    error_log("CRITICAL: Telegram notification error for task $taskId: " . $e->getMessage());
                }
                
                error_log("CRITICAL: Created task ID: $taskId for mechanic: " . $mechanic['pilns_vards'] . " (shift: {$mechanic['maina_status']})");
            } else {
                error_log("CRITICAL: Failed to create task for mechanic: " . $mechanic['pilns_vards']);
            }
        }
        
        if (!empty($createdTasks)) {
            // Atjaunot problēmas statusu
            $stmt = $pdo->prepare("UPDATE problemas SET statuss = 'Pārvērsta uzdevumā' WHERE id = ?");
            $stmt->execute([$problemId]);
            
            error_log("CRITICAL: Successfully created " . count($createdTasks) . " critical tasks for problem ID: $problemId");
        }
        
        return $createdTasks;
        
    } catch (PDOException $e) {
        error_log("CRITICAL: Database error creating critical tasks: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("CRITICAL: System error creating critical tasks: " . $e->getMessage());
        return false;
    }
}

// Funkcija kritisko uzdevumu noņemšanai citiem mehāniķiem (tikai tiem kas nav sākuši)
function removeCriticalTaskFromOtherMechanics($completedTaskId, $mechanicId) {
    global $pdo;

    try {
        // Iegūt pabeigto uzdevuma problēmas ID
        $stmt = $pdo->prepare("SELECT problemas_id FROM uzdevumi WHERE id = ?");
        $stmt->execute([$completedTaskId]);
        $problemId = $stmt->fetchColumn();
        
        if (!$problemId) {
            error_log("Nav atrasta problēmas ID uzdevumam: $completedTaskId");
            return false;
        }

        // Iegūt visus mehāniķus, kuriem ir šīs problēmas uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.id as uzdevuma_id, u.piešķirts_id, u.statuss, 
                   CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards,
                   (SELECT COUNT(*) FROM darba_laiks dl WHERE dl.uzdevuma_id = u.id AND dl.lietotaja_id = u.piešķirts_id) as darba_ieraksti
            FROM uzdevumi u
            JOIN lietotaji l ON u.piešķirts_id = l.id
            WHERE u.problemas_id = ? 
                AND u.id != ?
                AND u.prioritate = 'Kritiska'
                AND l.loma = 'Mehāniķis'
                AND l.statuss = 'Aktīvs'
        ");
        $stmt->execute([$problemId, $completedTaskId]);
        $relatedTasks = $stmt->fetchAll();

        $removedCount = 0;
        $preservedCount = 0;

        foreach ($relatedTasks as $task) {
            // Ja mehāniķis nav sācis darbu (nav darba laika ierakstu), noņemt uzdevumu
            if ($task['darba_ieraksti'] == 0 && $task['statuss'] === 'Jauns') {
                $deleteStmt = $pdo->prepare("DELETE FROM uzdevumi WHERE id = ?");
                if ($deleteStmt->execute([$task['uzdevuma_id']])) {
                    $removedCount++;
                    
                    // Paziņot mehāniķim par uzdevuma noņemšanu
                    createNotification(
                        $task['piešķirts_id'],
                        '🚨 KRITISKS uzdevums noņemts',
                        "Kritisks uzdevums ir noņemts, jo cits mehāniķis to jau ir pabeidzis. Problēma atrisināta.",
                        'Uzdevuma noņemšana',
                        'Uzdevums',
                        null
                    );
                    
                    error_log("Noņemts kritisks uzdevums ID: {$task['uzdevuma_id']} mehāniķim: {$task['mehaniķa_vards']} (nav sācis darbu)");
                }
            } else {
                // Mehāniķis ir sācis darbu - saglabāt uzdevumu, bet mainīt prioritāti
                $updateStmt = $pdo->prepare("
                    UPDATE uzdevumi 
                    SET prioritate = 'Augsta',
                        apraksts = CONCAT(apraksts, '\n\n⚠️ UZMANĪBU: Cits mehāniķis jau ir atrisinājis šo problēmu. Jūs varat pabeigt savu darbu vai atcelt uzdevumu.'),
                        nosaukums = REPLACE(nosaukums, '🚨 KRITISKS:', '⚠️ JĀPABEIDZ:')
                    WHERE id = ?
                ");
                if ($updateStmt->execute([$task['uzdevuma_id']])) {
                    $preservedCount++;
                    
                    // Paziņot mehāniķim par izmaiņām
                    createNotification(
                        $task['piešķirts_id'],
                        '⚠️ KRITISKS uzdevums atrisināts',
                        "Cits mehāniķis jau ir atrisinājis šo kritisku problēmu, bet jūs varat pabeigt savu sākto darbu vai atcelt uzdevumu.",
                        'Uzdevuma atjaunināšana',
                        'Uzdevums',
                        $task['uzdevuma_id']
                    );
                    
                    error_log("Saglabāts uzdevums ID: {$task['uzdevuma_id']} mehāniķim: {$task['mehaniķa_vards']} (jau sācis darbu)");
                }
            }
        }

        error_log("Kritiskā problēma ID: $problemId atrisināta. Noņemti: $removedCount uzdevumi, saglabāti: $preservedCount uzdevumi (mehāniķi sākuši darbu).");

        return true;

    } catch (Exception $e) {
        error_log("Kļūda noņemot/atjauninot kritiskos uzdevumus: " . $e->getMessage());
        return false;
    }
}




// Augšupielādes funkcija
function uploadFile($file, $targetDir = UPLOAD_DIR) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Nederīgs fails parametrs.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Fails nav augšupielādēts.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Fails ir pārāk liels.');
        default:
            throw new RuntimeException('Nezināma kļūda.');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Fails ir pārāk liels.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        throw new RuntimeException('Faila tips nav atļauts.');
    }

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            throw new RuntimeException('Nevarēja izveidot direktoriju.');
        }
    }

    $filename = sprintf('%s_%s.%s',
        uniqid(),
        date('Y-m-d_H-i-s'),
        $extension
    );

    $targetPath = $targetDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Nevarēja saglabāt failu.');
    }

    return [
        'originalais_nosaukums' => $file['name'],
        'saglabatais_nosaukums' => $filename,
        'faila_cels' => $targetPath,
        'faila_tips' => $mimeType,
        'faila_izmers' => $file['size']
    ];
}



// Inicializēt Telegram paziņojumu pārvaldnieku
$telegramManager = null;

if (defined('TELEGRAM_NOTIFICATIONS_ENABLED') && TELEGRAM_NOTIFICATIONS_ENABLED && 
    defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN) && isset($pdo)) {
    try {
        require_once __DIR__ . '/includes/telegram_notifications.php';
        $telegramManager = new TelegramNotificationManager($pdo, TELEGRAM_BOT_TOKEN);
        $GLOBALS['telegramManager'] = $telegramManager;

        error_log("Telegram Manager initialized successfully");
    } catch (Exception $e) {
        error_log("Telegram Manager initialization failed: " . $e->getMessage());
    }
}

?>