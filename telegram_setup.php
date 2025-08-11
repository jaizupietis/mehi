
<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Telegram iestatījumi';
$pageHeader = 'Telegram paziņojumu konfigurācija';

$errors = [];
$success = [];

// Apstrādāt darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'set_webhook') {
        try {
            if (isset($GLOBALS['telegramManager'])) {
                $webhookUrl = SITE_URL . '/telegram_webhook.php';
                $result = $GLOBALS['telegramManager']->setWebhook($webhookUrl);
                
                if ($result) {
                    $success[] = "Webhook iestatīts veiksmīgi: $webhookUrl";
                } else {
                    $errors[] = "Neizdevās iestatīt webhook";
                }
            } else {
                $errors[] = "Telegram Manager nav inicializēts";
            }
        } catch (Exception $e) {
            $errors[] = "Kļūda iestatot webhook: " . $e->getMessage();
        }
    }
    
    if ($action === 'test_bot') {
        try {
            if (isset($GLOBALS['telegramManager'])) {
                // Atrast pirmo admin
                $stmt = $pdo->query("
                    SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
                    FROM lietotaji 
                    WHERE loma = 'Administrators' AND statuss = 'Aktīvs' 
                    LIMIT 1
                ");
                $admin = $stmt->fetch();
                
                if ($admin) {
                    $result = $GLOBALS['telegramManager']->sendTaskNotification(
                        $admin['id'], 
                        'Testa uzdevums', 
                        999, 
                        'new_task'
                    );
                    
                    if ($result['success']) {
                        $success[] = "Testa ziņojums nosūtīts veiksmīgi!";
                    } else {
                        $errors[] = "Neizdevās nosūtīt testa ziņojumu: " . ($result['error'] ?? 'Nezināma kļūda');
                    }
                } else {
                    $errors[] = "Nav atrasts administratora konts testēšanai";
                }
            } else {
                $errors[] = "Telegram Manager nav inicializēts";
            }
        } catch (Exception $e) {
            $errors[] = "Kļūda testējot botu: " . $e->getMessage();
        }
    }
}

// Iegūt webhook informāciju
$webhookInfo = null;
if (isset($GLOBALS['telegramManager'])) {
    try {
        $webhookInfo = $GLOBALS['telegramManager']->getWebhookInfo();
    } catch (Exception $e) {
        error_log("Error getting webhook info: " . $e->getMessage());
    }
}

// Iegūt reģistrēto lietotāju statistiku
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as kopejie,
            COUNT(CASE WHEN tu.is_active = 1 THEN 1 END) as aktīvie,
            COUNT(CASE WHEN l.loma = 'Mehāniķis' THEN 1 END) as mehāniķi,
            COUNT(CASE WHEN l.loma = 'Menedžeris' THEN 1 END) as menedžeri,
            COUNT(CASE WHEN l.loma = 'Administrators' THEN 1 END) as administratori
        FROM telegram_users tu
        JOIN lietotaji l ON tu.lietotaja_id = l.id
    ");
    $telegramStats = $stmt->fetch();
    
    // Reģistrēto lietotāju saraksts
    $stmt = $pdo->query("
        SELECT 
            tu.*,
            CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
            l.loma,
            l.statuss
        FROM telegram_users tu
        JOIN lietotaji l ON tu.lietotaja_id = l.id
        ORDER BY tu.registered_at DESC
        LIMIT 50
    ");
    $telegramUsers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot statistiku: " . $e->getMessage();
    $telegramStats = null;
    $telegramUsers = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <ul class="mb-0">
            <?php foreach ($success as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Konfigurācijas statuss -->
<div class="card mb-4">
    <div class="card-header">
        <h3>🔧 Telegram konfigurācija</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5>Statuss</h5>
                <p>
                    <strong>Telegram paziņojumi:</strong> 
                    <span class="badge <?php echo TELEGRAM_NOTIFICATIONS_ENABLED ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo TELEGRAM_NOTIFICATIONS_ENABLED ? 'Aktivizēti' : 'Deaktivizēti'; ?>
                    </span>
                </p>
                <p>
                    <strong>Bot Token:</strong> 
                    <span class="badge <?php echo defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN) ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN) ? 'Iestatīts' : 'Nav iestatīts'; ?>
                    </span>
                </p>
                <p>
                    <strong>Manager:</strong> 
                    <span class="badge <?php echo isset($GLOBALS['telegramManager']) ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo isset($GLOBALS['telegramManager']) ? 'Inicializēts' : 'Nav inicializēts'; ?>
                    </span>
                </p>
            </div>
            
            <div class="col-md-6">
                <h5>Webhook informācija</h5>
                <?php if ($webhookInfo && $webhookInfo['ok']): ?>
                    <p><strong>URL:</strong> <?php echo htmlspecialchars($webhookInfo['result']['url'] ?? 'Nav iestatīts'); ?></p>
                    <p><strong>Pēdējā kļūda:</strong> <?php echo htmlspecialchars($webhookInfo['result']['last_error_message'] ?? 'Nav'); ?></p>
                    <p><strong>Ziņojumu skaits:</strong> <?php echo $webhookInfo['result']['pending_update_count'] ?? 0; ?></p>
                <?php else: ?>
                    <p class="text-muted">Nav pieejama webhook informācija</p>
                <?php endif; ?>
            </div>
        </div>
        
        <hr>
        
        <div class="btn-group" role="group">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="set_webhook">
                <button type="submit" class="btn btn-primary">
                    🔗 Iestatīt Webhook
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_bot">
                <button type="submit" class="btn btn-warning">
                    🧪 Testēt botu
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Statistika -->
<?php if ($telegramStats): ?>
<div class="card mb-4">
    <div class="card-header">
        <h3>📊 Lietotāju statistika</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <div class="stat-card">
                    <h4><?php echo $telegramStats['kopejie']; ?></h4>
                    <p>Kopējie</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <h4><?php echo $telegramStats['aktīvie']; ?></h4>
                    <p>Aktīvie</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <h4><?php echo $telegramStats['mehāniķi']; ?></h4>
                    <p>Mehāniķi</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <h4><?php echo $telegramStats['menedžeri']; ?></h4>
                    <p>Menedžeri</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <h4><?php echo $telegramStats['administratori']; ?></h4>
                    <p>Administratori</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reģistrēto lietotāju saraksts -->
<?php if (!empty($telegramUsers)): ?>
<div class="card">
    <div class="card-header">
        <h3>👥 Reģistrētie Telegram lietotāji</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Lietotājs</th>
                        <th>Loma</th>
                        <th>Telegram</th>
                        <th>Chat ID</th>
                        <th>Reģistrēts</th>
                        <th>Pēdējā ziņa</th>
                        <th>Statuss</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($telegramUsers as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['pilns_vards']); ?></td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo htmlspecialchars($user['loma']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['telegram_username']): ?>
                                @<?php echo htmlspecialchars($user['telegram_username']); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($user['telegram_first_name'] . ' ' . $user['telegram_last_name']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($user['telegram_chat_id']); ?></small>
                        </td>
                        <td><?php echo formatDate($user['registered_at']); ?></td>
                        <td>
                            <?php if ($user['last_message_at']): ?>
                                <?php echo formatDate($user['last_message_at']); ?>
                            <?php else: ?>
                                <span class="text-muted">Nav</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $user['is_active'] ? 'Aktīvs' : 'Neaktīvs'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Instrukcijas -->
<div class="card mt-4">
    <div class="card-header">
        <h3>📖 Lietošanas instrukcijas</h3>
    </div>
    <div class="card-body">
        <h5>Lietotājiem:</h5>
        <ol>
            <li>Atrodiet botu Telegram: <code>@JŪSU_BOT_USERNAME</code></li>
            <li>Nosūtiet ziņojumu: <code>/start</code></li>
            <li>Bots mēģinās reģistrēt jūs, izmantojot jūsu Telegram username</li>
            <li>Ja reģistrācija ir veiksmīga, sāksiet saņemt paziņojumus</li>
        </ol>
        
        <h5 class="mt-4">Administratoriem:</h5>
        <ul>
            <li>Pārliecinieties, ka webhook ir iestatīts</li>
            <li>Testējiet botu ar "Testēt botu" pogu</li>
            <li>Pārbaudiet lietotāju reģistrācijas šajā lapā</li>
            <li>Ja nepieciešams, mainiet bot token config.php failā</li>
        </ul>
        
        <div class="alert alert-info mt-3">
            <strong>Piezīme:</strong> Lietotāju Telegram username jāsakrīt ar viņu sistēmas username, lai reģistrācija būtu automātiska.
        </div>
    </div>
</div>

<style>
.stat-card {
    text-align: center;
    padding: 20px;
    background: var(--gray-100);
    border-radius: var(--border-radius);
    margin-bottom: 15px;
}

.stat-card h4 {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.stat-card p {
    margin: 0;
    color: var(--gray-600);
    font-weight: 500;
}

.badge-success {
    background-color: var(--success-color);
}

.badge-danger {
    background-color: var(--danger-color);
}

.badge-info {
    background-color: var(--info-color);
}

.btn-group form {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .row {
        text-align: center;
    }
    
    .btn-group {
        display: block;
    }
    
    .btn-group form {
        display: block;
        margin-bottom: 10px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
