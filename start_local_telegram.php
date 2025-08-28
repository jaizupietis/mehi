
<?php
// CLI režīma pārbaude
if (php_sapi_name() !== 'cli') {
    die("Šis skripts ir paredzēts darbam tikai CLI režīmā.\n");
}

// Definēt konstantes pirms config.php ielādes
define('CLI_MODE', true);

require_once 'config.php';

// Konfigurēt daemon režīmu
$isDaemon = true;

// Izveidot log direktoriju
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log funkcija
function daemonLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '/logs/telegram_daemon.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

daemonLog("=== LOKĀLĀ TELEGRAM POLLING PALAIŠANA ===");

if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
    daemonLog("❌ Telegram paziņojumi nav aktivizēti", 'ERROR');
    exit(1);
}

if (!isset($GLOBALS['telegramManager'])) {
    daemonLog("❌ Telegram Manager nav inicializēts", 'ERROR');
    exit(1);
}

daemonLog("✅ Palaižam Telegram polling lokālam serverim...");
daemonLog("📱 Bot: @AVOTI_TMS_Bot");
daemonLog("⏱️ Pārbaudes intervāls: " . (defined('TELEGRAM_POLLING_INTERVAL') ? TELEGRAM_POLLING_INTERVAL : 2) . " sekundes");

// Signal handlers priekš graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        global $running;
        daemonLog("📡 Saņemts SIGTERM, apturšana...", 'INFO');
        $running = false;
    });
    pcntl_signal(SIGINT, function() {
        global $running;
        daemonLog("📡 Saņemts SIGINT, apturšana...", 'INFO');
        $running = false;
    });
}

$telegramManager = $GLOBALS['telegramManager'];
$lastUpdateId = 0;

// Ielādēt pēdējo update ID
$offsetFile = 'telegram_offset.txt';
if (file_exists($offsetFile)) {
    $lastUpdateId = (int)file_get_contents($offsetFile);
}

$interval = defined('TELEGRAM_POLLING_INTERVAL') ? TELEGRAM_POLLING_INTERVAL : 2;
$running = true;
$errorCount = 0;
$maxErrors = 10;

while ($running) {
    try {
        // Process signals
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        if (!$running) break;
        
        $updates = $telegramManager->getUpdates($lastUpdateId + 1, 10, 1);
        
        if ($updates && isset($updates['result']) && !empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                if (!$running) break;
                
                daemonLog("📨 Jauna ziņa: Chat ID " . $update['message']['chat']['id']);
                processUpdate($update);
                $lastUpdateId = $update['update_id'];
            }
            
            // Saglabāt offset
            file_put_contents($offsetFile, $lastUpdateId);
            $errorCount = 0; // Reset error count on success
        }
        
        sleep($interval);
        
    } catch (Exception $e) {
        $errorCount++;
        daemonLog("❌ Kļūda ($errorCount/$maxErrors): " . $e->getMessage(), 'ERROR');
        
        if ($errorCount >= $maxErrors) {
            daemonLog("❌ Pārāk daudz kļūdu, apturšana...", 'FATAL');
            $running = false;
        } else {
            sleep(5 * $errorCount); // Increasing delay
        }
    }
}

daemonLog("🛑 Telegram polling apturēts");

function processUpdate($update) {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];
        
        // Iekļaut funkcijas no telegram_webhook.php
        if (strpos($text, '/start') === 0) {
            handleRegistration($chatId, $from, $text);
        } elseif ($text === '/status') {
            handleStatusRequest($chatId);
        } elseif ($text === '/help') {
            handleHelpRequest($chatId);
        } else {
            handleGeneralMessage($chatId, $text);
        }
    }
}

// Iekļaut visas handleRegistration, handleStatusRequest utt. funkcijas no telegram_webhook.php
function handleRegistration($chatId, $from, $text) {
    global $telegramManager, $pdo;

    $username = $from['username'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $lastName = $from['last_name'] ?? '';

    if ($username) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards, loma
                FROM lietotaji 
                WHERE telegram_username = ? AND statuss = 'Aktīvs'
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                $result = $telegramManager->registerUser(
                    $user['id'],
                    $chatId,
                    $username,
                    $firstName,
                    $lastName
                );

                if ($result) {
                    $message = "✅ <b>Reģistrācija veiksmīga!</b>\n\n";
                    $message .= "👤 <b>Lietotājs:</b> {$user['pilns_vards']}\n";
                    $message .= "🏷️ <b>Loma:</b> {$user['loma']}\n\n";
                    $message .= "Tagad jūs saņemsiet paziņojumus par uzdevumiem.";
                    daemonLog("✅ Reģistrēts lietotājs: {$user['pilns_vards']} (@{$username})");
                } else {
                    $message = "❌ Kļūda reģistrācijā.";
                    daemonLog("❌ Reģistrācijas kļūda lietotājam @{$username}");
                }
            } else {
                $message = "❌ <b>Lietotājs nav atrasts</b>\n\n";
                $message .= "Jūsu @{$username} nav atrasts sistēmā.";
                daemonLog("❌ Lietotājs @{$username} nav atrasts sistēmā");
            }

        } catch (Exception $e) {
            $message = "❌ Sistēmas kļūda.";
            daemonLog("❌ Reģistrācijas kļūda: " . $e->getMessage(), 'ERROR');
        }
    } else {
        $message = "❌ Nepieciešams Telegram username.";
        daemonLog("❌ Lietotājs bez username mēģināja reģistrēties");
    }

    $telegramManager->sendMessage($chatId, $message);
}

function handleStatusRequest($chatId) {
    global $telegramManager, $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT tu.*, l.vards, l.uzvards, l.loma
            FROM telegram_users tu
            JOIN lietotaji l ON tu.lietotaja_id = l.id
            WHERE tu.telegram_chat_id = ? AND tu.is_active = TRUE
        ");
        $stmt->execute([$chatId]);
        $telegramUser = $stmt->fetch();

        if ($telegramUser) {
            $message = "📊 <b>Jūsu statuss</b>\n\n";
            $message .= "👤 {$telegramUser['vards']} {$telegramUser['uzvards']}\n";
            $message .= "🏷️ {$telegramUser['loma']}\n";
            $message .= "🔗 <a href='" . SITE_URL . "'>Sistēma</a>";
            
            daemonLog("📊 Status pieprasīts: {$telegramUser['vards']} {$telegramUser['uzvards']}");
        } else {
            $message = "❌ Reģistrējieties ar /start";
            daemonLog("❌ Nereģistrēts lietotājs pieprasīja statusu: Chat ID $chatId");
        }

    } catch (Exception $e) {
        $message = "❌ Kļūda iegūstot statusu.";
        daemonLog("❌ Status kļūda: " . $e->getMessage(), 'ERROR');
    }

    $telegramManager->sendMessage($chatId, $message);
}

function handleHelpRequest($chatId) {
    global $telegramManager;

    $message = "🤖 <b>AVOTI TMS Bot</b>\n\n";
    $message .= "/start - Reģistrēties\n";
    $message .= "/status - Statuss\n";
    $message .= "/help - Palīdzība\n\n";
    $message .= "🔗 <a href='" . SITE_URL . "'>Sistēma</a>";

    $telegramManager->sendMessage($chatId, $message);
    daemonLog("ℹ️ Palīdzības ziņa nosūtīta uz Chat ID: $chatId");
}

function handleGeneralMessage($chatId, $text) {
    global $telegramManager;

    $message = "🤖 AVOTI TMS bots\n\nReģistrācijai: /start\nPalīdzībai: /help";
    $telegramManager->sendMessage($chatId, $message);
    daemonLog("💬 Vispārīga ziņa: '$text' no Chat ID: $chatId");
}
?>
