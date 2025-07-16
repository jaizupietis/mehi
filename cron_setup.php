<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Cron iestatījumi';
$pageHeader = 'Regulāro uzdevumu automātiskā izpilde';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Cron failu ceļi
$cron_file = __DIR__ . '/cron_scheduler.php';
$log_file = __DIR__ . '/logs/cron_scheduler.log';

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_scheduler') {
        // Manuāli palaist scheduler
        ob_start();
        include $cron_file;
        $output = ob_get_contents();
        ob_end_clean();
        
        setFlashMessage('info', 'Scheduler ir izpildīts manuāli. Pārbaudiet log failu, lai redzētu rezultātus.');
    }
    
    if ($action === 'clear_logs') {
        // Notīrīt log failu
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            setFlashMessage('success', 'Log fails ir notīrīts.');
        }
    }
}

// Iegūt sistēmas informāciju
$system_info = [
    'php_version' => phpversion(),
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'cron_file_exists' => file_exists($cron_file),
    'cron_file_executable' => is_executable($cron_file),
    'log_file_exists' => file_exists($log_file),
    'log_file_writable' => is_writable(dirname($log_file)),
];

// Lasīt log failu
$log_content = '';
if (file_exists($log_file)) {
    $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = implode("\n", array_slice($log_lines, -50)); // Pēdējās 50 rindas
}

// Iegūt regulāro uzdevumu statistiku
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as kopā_šabloni,
            SUM(CASE WHEN aktīvs = 1 THEN 1 ELSE 0 END) as aktīvie_šabloni,
            (SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND DATE(izveidots) = CURDATE()) as šodienas_uzdevumi,
            (SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND WEEK(izveidots) = WEEK(NOW())) as nedēļas_uzdevumi
        FROM regularo_uzdevumu_sabloni
    ");
    $statistika = $stmt->fetch();
    
    // Nākamie plānotie uzdevumi
    $stmt = $pdo->query("
        SELECT r.nosaukums, r.periodicitate, r.laiks, r.prioritate,
               CASE 
                   WHEN r.periodicitate = 'Katru dienu' THEN DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                   WHEN r.periodicitate = 'Katru nedēļu' THEN DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   WHEN r.periodicitate = 'Reizi mēnesī' THEN DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                   WHEN r.periodicitate = 'Reizi ceturksnī' THEN DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                   WHEN r.periodicitate = 'Reizi gadā' THEN DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
               END as nākamais_datums
        FROM regularo_uzdevumu_sabloni r
        WHERE r.aktīvs = 1
        ORDER BY nākamais_datums ASC, r.prioritate DESC
        LIMIT 10
    ");
    $nākamie_uzdevumi = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda iegūstot statistiku: " . $e->getMessage();
    $statistika = ['kopā_šabloni' => 0, 'aktīvie_šabloni' => 0, 'šodienas_uzdevumi' => 0, 'nedēļas_uzdevumi' => 0];
    $nākamie_uzdevumi = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Sistēmas informācija -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Sistēmas informācija</h4>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>PHP versija:</strong></td>
                        <td><?php echo $system_info['php_version']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Servera laiks:</strong></td>
                        <td><?php echo $system_info['server_time']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Laika zona:</strong></td>
                        <td><?php echo $system_info['timezone']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cron fails:</strong></td>
                        <td>
                            <?php if ($system_info['cron_file_exists']): ?>
                                <span class="text-success">✓ Eksistē</span>
                            <?php else: ?>
                                <span class="text-danger">✗ Neeksistē</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Log fails:</strong></td>
                        <td>
                            <?php if ($system_info['log_file_exists']): ?>
                                <span class="text-success">✓ Eksistē</span>
                            <?php else: ?>
                                <span class="text-warning">⚠ Neeksistē</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Regulāro uzdevumu statistika</h4>
            </div>
            <div class="card-body">
                <div class="stats-mini">
                    <div class="stat-mini">
                        <div class="stat-number"><?php echo $statistika['kopā_šabloni']; ?></div>
                        <div class="stat-label">Kopā šabloni</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-number"><?php echo $statistika['aktīvie_šabloni']; ?></div>
                        <div class="stat-label">Aktīvie šabloni</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-number"><?php echo $statistika['šodienas_uzdevumi']; ?></div>
                        <div class="stat-label">Šodienas uzdevumi</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-number"><?php echo $statistika['nedēļas_uzdevumi']; ?></div>
                        <div class="stat-label">Nedēļas uzdevumi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cron iestatījumi -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Cron iestatījumi</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h5>Cron job iestatīšana</h5>
            <p>Lai automātiski izpildītu regulāros uzdevumus, nepieciešams iestatīt cron job serverī.</p>
            <p><strong>Ieteicamais cron iestatījums:</strong></p>
            <pre><code>0 * * * * /usr/bin/php <?php echo $cron_file; ?></code></pre>
            <p><small>Šis iestatījums palaidīs scheduler katru stundu.</small></p>
        </div>
        
        <div class="alert alert-warning">
            <h5>Alternatīvs iestatījums</h5>
            <p>Ja nevarat iestatīt cron job, varat izmantot manuālo palaišanu:</p>
            <pre><code>*/15 * * * * /usr/bin/wget -O - -q -t 1 <?php echo SITE_URL; ?>/cron_scheduler.php</code></pre>
            <p><small>Šis iestatījums palaidīs scheduler katras 15 minūtes, izmantojot wget.</small></p>
        </div>
        
        <div class="mt-3">
            <h5>Manuālā testēšana</h5>
            <p>Varat arī palaist scheduler manuāli, lai testētu funkcionalitāti:</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_scheduler">
                <button type="submit" class="btn btn-primary">Palaist scheduler tagad</button>
            </form>
        </div>
    </div>
</div>

<!-- Nākamie plānotie uzdevumi -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Nākamie plānotie uzdevumi</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Periodicitāte</th>
                        <th>Laiks</th>
                        <th>Prioritāte</th>
                        <th>Aprēķinātais nākamais datums</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nākamie_uzdevumi)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nav plānotu uzdevumu</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nākamie_uzdevumi as $uzdevums): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></td>
                                <td><?php echo $uzdevums['periodicitate']; ?></td>
                                <td><?php echo $uzdevums['laiks'] ?? 'Nav norādīts'; ?></td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                        <?php echo $uzdevums['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($uzdevums['nākamais_datums']): ?>
                                        <?php echo formatDate($uzdevums['nākamais_datums'], 'd.m.Y'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nevar aprēķināt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Log faila skatīšana -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Scheduler logs (pēdējās 50 rindas)</h4>
            <div>
                <button onclick="refreshLogs()" class="btn btn-sm btn-info">Atjaunot</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Vai tiešām vēlaties notīrīt log failu?')">Notīrīt</button>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($log_content)): ?>
            <div class="alert alert-info">Log fails ir tukšs vai neeksistē.</div>
        <?php else: ?>
            <pre id="logContent" class="log-content"><?php echo htmlspecialchars($log_content); ?></pre>
        <?php endif; ?>
    </div>
</div>

<script>
// Log faila atjaunošana
function refreshLogs() {
    fetch('<?php echo $log_file; ?>?t=' + Date.now())
        .then(response => response.text())
        .then(data => {
            const lines = data.trim().split('\n');
            const lastLines = lines.slice(-50).join('\n');
            document.getElementById('logContent').textContent = lastLines;
        })
        .catch(error => {
            console.error('Kļūda atjaunojot logs:', error);
        });
}

// Automātiska log atjaunošana katras 30 sekundes
setInterval(refreshLogs, 30000);
</script>

<style>
.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: var(--spacing-md);
}

.stat-mini {
    text-align: center;
    padding: var(--spacing-sm);
    background: var(--gray-100);
    border-radius: var(--border-radius);
}

.stat-mini .stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--secondary-color);
}

.stat-mini .stat-label {
    font-size: var(--font-size-sm);
    color: var(--gray-600);
    margin-top: var(--spacing-xs);
}

.log-content {
    background: var(--gray-900);
    color: var(--gray-100);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    font-family: 'Courier New', monospace;
    font-size: var(--font-size-sm);
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.col-md-6 {
    flex: 1;
    min-width: 300px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-6 {
        width: 100%;
        flex: none;
    }
    
    .stats-mini {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
}
</style>

<?php include 'includes/footer.php'; ?>