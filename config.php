<?php
/**
 * AVOTI Task Management sistēmas konfigurācijas fails
 */

// Sesijas konfigurācija (tikai ja nav jau startēta un nav CLI režīms)
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

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
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx','csv']);

// Laika zonas uzstādīšana
date_default_timezone_set('Europe/Riga');

// Latviešu lokalizācija
setlocale(LC_TIME, 'lv_LV.UTF-8', 'lv_LV', 'latvian');

// Kļūdu ziņošanas uzstādīšana (izstrādes vidē)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
        return false;
    }

    if (isset($GLOBALS['telegramManager'])) {
        return $GLOBALS['telegramManager']->sendTaskNotification($lietotajaId, $taskTitle, $taskId, $type);
    }

    return false;
}

// Telegram paziņojumu funkcija
function sendProblemTelegramNotification($problemId, $problemTitle) {
    if (defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN)) {
        try {
            global $pdo;
            require_once 'includes/telegram_notifications.php';

            $telegramManager = new TelegramNotificationManager($pdo, TELEGRAM_BOT_TOKEN);

            // Nosūtīt paziņojumus menedžeriem un administratoriem
            $stmt = $pdo->query("
                SELECT id FROM lietotaji 
                WHERE loma IN ('Administrators', 'Menedžeris') 
                AND statuss = 'Aktīvs'
            ");
            $managers = $stmt->fetchAll();

            foreach ($managers as $manager) {
                $telegramManager->sendProblemNotification($manager['id'], $problemTitle, $problemId);
            }

        } catch (Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
        }
    }
}

// Chrome Push Notification funkcija (izmanto polling sistēmu)
function sendProblemPushNotification($problemId, $problemTitle) {
    // Paziņojumi jau ir izveidoti createNotification() funkcijā
    // Polling sistēma ajax/simple_push_notification.php tos apstrādās automātiski
    return true;
}

// Funkcija kritisku problēmu automātiskai pārvēršanai uzdevumā
function createCriticalTaskFromProblem($problemId, $problemData) {
    global $pdo;

    try {
        // Iegūt visus pašlaik strādājošos mehāniķus
        $currentTime = date('H:i:s');
        $currentDate = date('Y-m-d');
        $currentDay = date('N'); // 1=Pirmdiena, 7=Svētdiena

        $stmt = $pdo->prepare("
            SELECT DISTINCT m.id, CONCAT(m.vards, ' ', m.uzvards) as pilns_vards
            FROM lietotaji m
            LEFT JOIN darba_grafiks dg ON m.id = dg.lietotaja_id 
                AND dg.datums = ? 
            WHERE m.loma = 'Mehāniķis' 
                AND m.statuss = 'Aktīvs'
                AND (
                    (dg.maina = 'R' AND ? BETWEEN '07:00:00' AND '16:59:59') OR
                    (dg.maina = 'V' AND (? >= '16:00:00' OR ? <= '01:00:00')) OR
                    (dg.maina IS NULL AND ? BETWEEN '07:00:00' AND '16:59:59')
                )
        ");

        $stmt->execute([$currentDate, $currentTime, $currentTime, $currentTime, $currentTime]);
        $workingMechanics = $stmt->fetchAll();

        if (empty($workingMechanics)) {
            // Ja neviens nestrādā, piešķirt visiem aktīvajiem mehāniķiem
            $stmt = $pdo->query("
                SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
                FROM lietotaji 
                WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs'
                ORDER BY vards, uzvards
            ");
            $workingMechanics = $stmt->fetchAll();
        }

        if (empty($workingMechanics)) {
            error_log("Nav aktīvu mehāniķu kritiskā uzdevuma izveidošanai. Pašreizējais laiks: $currentTime, datums: $currentDate");
            return false;
        }

        error_log("Atrasti " . count($workingMechanics) . " strādājoši mehāniķi kritiskā uzdevuma izveidošanai");

        // Izveidot uzdevumu katram mehāniķim
        $createdTasks = [];
        foreach ($workingMechanics as $mechanic) {
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumi 
                (nosaukums, apraksts, veids, vietas_id, iekartas_id, prioritate, 
                 piešķirts_id, izveidoja_id, problemas_id, paredzamais_ilgums)
                VALUES (?, ?, 'Ikdienas', ?, ?, 'Kritiska', ?, 1, ?, ?)
            ");

            $taskTitle = "KRITISKS: " . $problemData['nosaukums'];
            $taskDescription = "APTURĒTA RAŽOŠANA!\n\n" . $problemData['apraksts'] . 
                              "\n\nŠis uzdevums ir automātiski izveidots no kritiskas problēmas. " .
                              "Tiklīdz kāds mehāniķis sāks darbu, uzdevums tiks noņemts pārējiem.";

            $stmt->execute([
                $taskTitle,
                $taskDescription,
                $problemData['vietas_id'],
                $problemData['iekartas_id'],
                $mechanic['id'],
                $problemId,
                $problemData['aptuvenais_ilgums']
            ]);

            $taskId = $pdo->lastInsertId();
            $createdTasks[] = [
                'task_id' => $taskId,
                'mechanic_id' => $mechanic['id'],
                'mechanic_name' => $mechanic['pilns_vards']
            ];

            // Izveidot paziņojumu mehāniķim
            createNotification(
                $mechanic['id'],
                'KRITISKS UZDEVUMS!',
                "Jums piešķirts kritisks uzdevums: $taskTitle. Ražošana ir apturēta!",
                'Kritisks uzdevums',
                'Uzdevums',
                $taskId
            );
        }

        // NEATJAUNOT problēmas statusu uzreiz - lai paliek "Jauna" līdz mehāniķis sāk darbu
        // Problēma tiks atjaunota uz "Pārvērsta uzdevumā" tikai tad, kad kāds mehāniķis sāks darbu

        // Ierakstīt log failā
        error_log("Kritiska problēma ID:$problemId automātiski pārvērsta uzdevumā. Izveidoti " . count($createdTasks) . " uzdevumi.");

        return $createdTasks;

    } catch (Exception $e) {
        error_log("Kļūda veidojot kritisku uzdevumu: " . $e->getMessage());
        return false;
    }
}

// Function removed - using the one in my_tasks.php instead to avoid redeclaration

// Funkcija kritisko uzdevumu noņemšanai citiem mehāniķiem
function removeCriticalTaskFromOtherMechanics($startedTaskId, $mechanicId) {
    global $pdo;

    try {
        // Iegūt visus mehāniķus, kuriem piešķirts šis kritiskais uzdevums
        $stmt = $pdo->prepare("
            SELECT DISTINCT uzvards, vards, id
            FROM lietotaji
            JOIN uzdevumi ON lietotaji.id = uzdevumi.piešķirts_id
            WHERE uzdevumi.problemas_id = (SELECT problemas_id FROM uzdevumi WHERE id = ?)
                AND uzdevumi.id != ?
                AND uzdevumi.statuss = 'Jauns'
                AND uzdevumi.prioritate = 'Kritiska'
                AND lietotaji.loma = 'Mehāniķis'
                AND lietotaji.statuss = 'Aktīvs'
        ");
        $stmt->execute([$startedTaskId, $startedTaskId]);
        $affectedMechanics = $stmt->fetchAll();

        // Izdzēst visus citus ar šo problēmu saistītos uzdevumus
        $deleteStmt = $pdo->prepare("
            DELETE FROM uzdevumi 
            WHERE problemas_id = (SELECT problemas_id FROM uzdevumi WHERE id = ?)
                AND id != ?
                AND statuss = 'Jauns'
                AND prioritate = 'Kritiska'
        ");
        $deleteStmt->execute([$startedTaskId, $startedTaskId]);

        // Nosūtīt paziņojumus pārējiem mehāniķiem
        foreach ($affectedMechanics as $mechanic) {
            createNotification(
                $mechanic['id'],
                'KRITISKS: Uzdevums noņemts',
                "Kritisks uzdevums ir noņemts, jo to sācis cits mehāniķis",
                'Statusa maiņa',
                'Uzdevums',
                null
            );
        }

        error_log("Noņemti kritiskie uzdevumi citiem mehāniķiem. Uzdevums ID:$startedTaskId sākts.");

        return true;

    } catch (Exception $e) {
        error_log("Kļūda noņemot kritiskos uzdevumus: " . $e->getMessage());
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