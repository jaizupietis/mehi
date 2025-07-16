<?php
/**
 * AVOTI Task Management sistēmas konfigurācijas fails
 */

// Sesijas konfigurācija
session_start();

// Datubāzes konfigurācija
define('DB_HOST', 'localhost');
define('DB_NAME', 'mehu_uzd');
define('DB_USER', 'tasks');
define('DB_PASS', 'Astalavista1920');
define('DB_CHARSET', 'utf8mb4');

// Sistēmas konfigurācija
define('SITE_URL', 'http://192.168.2.11/mehi');
define('SITE_NAME', 'AVOTI Task Management');
define('COMPANY_NAME', 'SIA "AVOTI"');

// Failu augšupielādes konfigurācija
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Laika zonas uzstādīšana
date_default_timezone_set('Europe/Riga');

// Latviešu lokalizācija
setlocale(LC_TIME, 'lv_LV.UTF-8', 'lv_LV', 'latvian');

// Kļūdu ziņošanas uzstādīšana (izstrādes vidē)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Uzdevumu veidi
define('TASK_TYPE_DAILY', 'Ikdienas');
define('TASK_TYPE_REGULAR', 'Regulārais');

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

// Regulāro uzdevumu periodicitātes
define('PERIOD_DAILY', 'Katru dienu');
define('PERIOD_WEEKLY', 'Katru nedēļu');
define('PERIOD_MONTHLY', 'Reizi mēnesī');
define('PERIOD_QUARTERLY', 'Reizi ceturksnī');
define('PERIOD_YEARLY', 'Reizi gadā');

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
    return strftime('%d. %B %Y, %H:%i', $timestamp);
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

// Regulāro uzdevumu funkcijas
function findLeastBusyMechanic() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT l.id, 
                   COUNT(u.id) as aktīvo_uzdevumu_skaits,
                   SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 3 
                            WHEN u.prioritate = 'Augsta' THEN 2 
                            WHEN u.prioritate = 'Vidēja' THEN 1 
                            ELSE 0 END) as prioritātes_svars
            FROM lietotaji l
            LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
            WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
            GROUP BY l.id
            ORDER BY aktīvo_uzdevumu_skaits ASC, prioritātes_svars ASC, l.id ASC
            LIMIT 1
        ");
        
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        error_log("Kļūda meklējot brīvāko mehāniķi: " . $e->getMessage());
        return null;
    }
}

function shouldCreateRegularTask($periodicitate, $periodicitas_dienas, $laiks = null) {
    $today = date('N'); // 1 = Pirmdiena, 7 = Svētdiena
    $today_date = date('j'); // Mēneša diena
    $current_time = date('H:i');
    
    // Ja ir norādīts laiks, pārbaudīt vai ir pareizais laiks
    if ($laiks && $laiks != $current_time) {
        return false;
    }
    
    switch ($periodicitate) {
        case PERIOD_DAILY:
            return true;
            
        case PERIOD_WEEKLY:
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                return is_array($dienas) && in_array($today, $dienas);
            }
            return false;
            
        case PERIOD_MONTHLY:
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                return is_array($dienas) && in_array($today_date, $dienas);
            }
            return false;
            
        case PERIOD_QUARTERLY:
            // Pirmā mēneša diena ceturksnī
            $month = date('n');
            $quarter_months = [1, 4, 7, 10];
            return in_array($month, $quarter_months) && $today_date == 1;
            
        case PERIOD_YEARLY:
            // 1. janvārī
            return date('m-d') == '01-01';
            
        default:
            return false;
    }
}

function isRegularTaskCreatedToday($template_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM uzdevumi 
            WHERE regulara_uzdevuma_id = ? 
            AND DATE(izveidots) = CURDATE()
        ");
        $stmt->execute([$template_id]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Kļūda pārbaudot uzdevuma esamību: " . $e->getMessage());
        return true; // Drošības dēļ atgriežam true
    }
}

function createRegularTask($template, $mechanic_id, $created_by = 1) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Izveidot uzdevumu
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumi 
            (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, 
             prioritate, piešķirts_id, izveidoja_id, paredzamais_ilgums, regulara_uzdevuma_id)
            VALUES (?, ?, 'Regulārais', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $template['nosaukums'],
            $template['apraksts'],
            $template['vietas_id'],
            $template['iekartas_id'],
            $template['kategorijas_id'],
            $template['prioritate'],
            $mechanic_id,
            $created_by,
            $template['paredzamais_ilgums'],
            $template['id']
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Pievienot vēsturi
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumu_vesture 
            (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
            VALUES (?, NULL, 'Jauns', 'Regulārais uzdevums izveidots automātiski', ?)
        ");
        $stmt->execute([$task_id, $created_by]);
        
        // Paziņot mehāniķim
        createNotification(
            $mechanic_id,
            'Jauns regulārais uzdevums',
            "Jums ir piešķirts regulārais uzdevums: {$template['nosaukums']}",
            'Jauns uzdevums',
            'Uzdevums',
            $task_id
        );
        
        $pdo->commit();
        return $task_id;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Kļūda izveidojot regulāro uzdevumu: " . $e->getMessage());
        return false;
    }
}

function getRegularTaskStatistics($user_id = null) {
    global $pdo;
    
    try {
        $where_clause = $user_id ? "WHERE u.piešķirts_id = ?" : "";
        $params = $user_id ? [$user_id] : [];
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as kopā_regulārie,
                SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_regulārie,
                SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_regulārie,
                COUNT(DISTINCT u.regulara_uzdevuma_id) as dažādi_šabloni
            FROM uzdevumi u
            $where_clause AND u.veids = 'Regulārais'
        ");
        $stmt->execute($params);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Kļūda iegūstot regulāro uzdevumu statistiku: " . $e->getMessage());
        return ['kopā_regulārie' => 0, 'pabeigti_regulārie' => 0, 'aktīvi_regulārie' => 0, 'dažādi_šabloni' => 0];
    }
}

function getActiveRegularTemplates() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT r.*, 
                   v.nosaukums as vietas_nosaukums,
                   COUNT(u.id) as uzdevumu_skaits,
                   SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
                   MAX(u.izveidots) as pēdējais_uzdevums
            FROM regularo_uzdevumu_sabloni r
            LEFT JOIN vietas v ON r.vietas_id = v.id
            LEFT JOIN uzdevumi u ON r.id = u.regulara_uzdevuma_id
            WHERE r.aktīvs = 1
            GROUP BY r.id
            ORDER BY r.prioritate DESC, r.nosaukums
        ");
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Kļūda iegūstot aktīvos regulāros šablonus: " . $e->getMessage());
        return [];
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

// Augšupielādes funkcija
function uploadFile($file, $targetDir = UPLOAD_DIR) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Nederīgs faila parametrs.');
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

// Logging funkcija
function logMessage($message, $logFile = null) {
    if (!$logFile) {
        $logFile = __DIR__ . '/logs/system.log';
    }
    
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}
?>