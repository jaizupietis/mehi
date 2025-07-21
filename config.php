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
define('SITE_NAME', 'Uzdevumu pārvaldības sistēma');
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
?>