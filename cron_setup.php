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
    
    if ($action === 'test_notifications') {
        // Testēt paziņojumu sistēmu
        try {
            // Iekļaut cron_scheduler.php funkcijas
            require_once $cron_file;
            
            // Izsaukt testa funkciju (ja tā eksistē)
            if (function_exists('testNotificationSystem')) {
                $test_result = testNotificationSystem();
                if ($test_result) {
                    setFlashMessage('success', 'Paziņojumu sistēma darbojas pareizi! Pārbaudiet paziņojumus savā profilā.');
                } else {
                    setFlashMessage('error', 'Paziņojumu sistēmā ir problēmas. Pārbaudiet log failu.');
                }
            } else {
                // Manuāls paziņojuma tests
                $stmt = $pdo->query("
                    SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
                    FROM lietotaji 
                    WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' 
                    LIMIT 1
                ");
                $mechanic = $stmt->fetch();
                
                if ($mechanic) {
                    $result = createNotification(
                        $mechanic['id'],
                        'Testa paziņojums',
                        'Šis ir testa paziņojums no sistēmas administratora - ' . date('Y-m-d H:i:s'),
                        'Sistēmas',
                        null,
                        null
                    );
                    
                    if ($result) {
                        setFlashMessage('success', "Testa paziņojums nosūtīts mehāniķim: {$mechanic['pilns_vards']}");
                    } else {
                        setFlashMessage('error', 'Neizdevās izveidot testa paziņojumu.');
                    }
                } else {
                    setFlashMessage('error', 'Nav aktīvu mehāniķu, kam nosūtīt testa paziņojumu.');
                }
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Kļūda testējot paziņojumus: ' . $e->getMessage());
        }
    }
    
    if ($action === 'clear_logs') {
        // Notīrīt log failu
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            setFlashMessage('success', 'Log fails ir notīrīts.');
        }
    }
    
    if ($action === 'debug_scheduler') {
        // Palaist scheduler ar debug režīmu
        $debug_command = "php " . escapeshellarg($cron_file) . " --debug 2>&1";
        $debug_output = shell_exec($debug_command);
        
        if ($debug_output) {
            setFlashMessage('info', 'Debug izvads: <pre>' . htmlspecialchars($debug_output) . '</pre>');
        } else {
            setFlashMessage('warning', 'Debug komanda izpildīta, bet nav izvads. Pārbaudiet log failu.');
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
    'log_file_size' => file_exists($log_file) ? filesize($log_file) : 0,
];

// Lasīt log failu
$log_content = '';
$log_lines_count = 0;
if (file_exists($log_file)) {
    $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_lines_count = count($log_lines);
    $log_content = implode("\n", array_slice($log_lines, -100)); // Pēdējās 100 rindas
}

// Iegūt regulāro uzdevumu statistiku
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as kopā_šabloni,
            SUM(CASE WHEN aktīvs = 1 THEN 1 ELSE 0 END) as aktīvie_šabloni,
            (SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND DATE(izveidots) = CURDATE()) as šodienas_uzdevumi,
            (SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND WEEK(izveidots) = WEEK(NOW())) as nedēļas_uzdevumi,
            (SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND MONTH(izveidots) = MONTH(NOW())) as mēneša_uzdevumi
        FROM regularo_uzdevumu_sabloni
    ");
    $statistika = $stmt->fetch();
    
    // Pēdējie izveidotie regulārie uzdevumi
    $stmt = $pdo->query("
        SELECT u.id, u.nosaukums, u.izveidots, u.statuss,
               CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards,
               r.nosaukums as šablona_nosaukums
        FROM uzdevumi u
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        WHERE u.veids = 'Regulārais'
        ORDER BY u.izveidots DESC
        LIMIT 10
    ");
    $pēdējie_uzdevumi = $stmt->fetchAll();
    
    // Nākamie plānotie uzdevumi ar detalizētāku informāciju
    $stmt = $pdo->query("
        SELECT r.id, r.nosaukums, r.periodicitate, r.laiks, r.prioritate, r.periodicitas_dienas,
               COUNT(u.id) as izveidoto_skaits,
               MAX(u.izveidots) as pēdējais_izveidots,
               CASE 
                   WHEN r.periodicitate = 'Katru dienu' THEN 
                       CASE WHEN TIME(NOW()) < r.laiks THEN CURDATE() ELSE DATE_ADD(CURDATE(), INTERVAL 1 DAY) END
                   WHEN r.periodicitate = 'Katru nedēļu' THEN DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                   WHEN r.periodicitate = 'Reizi mēnesī' THEN DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                   WHEN r.periodicitate = 'Reizi ceturksnī' THEN DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                   WHEN r.periodicitate = 'Reizi gadā' THEN DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
               END as aprēķinātais_nākamais_datums,
               CASE WHEN EXISTS(SELECT 1 FROM uzdevumi WHERE regulara_uzdevuma_id = r.id AND DATE(izveidots) = CURDATE()) 
                    THEN 1 ELSE 0 END as šodien_izveidots
        FROM regularo_uzdevumu_sabloni r
        LEFT JOIN uzdevumi u ON r.id = u.regulara_uzdevuma_id
        WHERE r.aktīvs = 1
        GROUP BY r.id
        ORDER BY aprēķinātais_nākamais_datums ASC, r.prioritate DESC
        LIMIT 15
    ");
    $nākamie_uzdevumi = $stmt->fetchAll();
    
    // Paziņojumu statistika
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as kopā_paziņojumi,
            SUM(CASE WHEN skatīts = 0 THEN 1 ELSE 0 END) as nelasītie_paziņojumi,
            SUM(CASE WHEN tips = 'Jauns uzdevums' AND DATE(izveidots) = CURDATE() THEN 1 ELSE 0 END) as šodienas_uzdevumu_paziņojumi
        FROM pazinojumi
    ");
    $paziņojumu_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda iegūstot statistiku: " . $e->getMessage();
    $statistika = ['kopā_šabloni' => 0, 'aktīvie_šabloni' => 0, 'šodienas_uzdevumi' => 0, 'nedēļas_uzdevumi' => 0, 'mēneša_uzdevumi' => 0];
    $nākamie_uzdevumi = [];
    $pēdējie_uzdevumi = [];
    $paziņojumu_stats = ['kopā_paziņojumi' => 0, 'nelasītie_paziņojumi' => 0, 'šodienas_uzdevumu_paziņojumi' => 0];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Sistēmas informācija un paziņojumu statistika -->
<div class="row mb-4">
    <div class="col-md-4">
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
                                <span class="text-success">✓ Eksistē (<?php echo round($system_info['log_file_size']/1024, 1); ?>KB, <?php echo $log_lines_count; ?> rindas)</span>
                            <?php else: ?>
                                <span class="text-warning">⚠ Neeksistē</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
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
                        <div class="stat-number"><?php echo $statistika['mēneša_uzdevumi']; ?></div>
                        <div class="stat-label">Mēneša uzdevumi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Paziņojumu statistika</h4>
            </div>
            <div class="card-body">
                <div class="stats-mini">
                    <div class="stat-mini">
                        <div class="stat-number"><?php echo $paziņojumu_stats['kopā_paziņojumi']; ?></div>
                        <div class="stat-label">Kopā paziņojumi</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-number text-warning"><?php echo $paziņojumu_stats['nelasītie_paziņojumi']; ?></div>
                        <div class="stat-label">Nelasītie</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-number text-success"><?php echo $paziņojumu_stats['šodienas_uzdevumu_paziņojumi']; ?></div>
                        <div class="stat-label">Šodienas uzdevumi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cron iestatījumi un testēšana -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Cron iestatījumi un testēšana</h4>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div class="alert alert-info">
                    <h5>Cron job iestatīšana</h5>
                    <p>Lai automātiski izpildītu regulāros uzdevumus, nepieciešams iestatīt cron job serverī.</p>
                    <p><strong>Ieteicamais cron iestatījums (katru stundu):</strong></p>
                    <pre><code>0 * * * * /usr/bin/php <?php echo $cron_file; ?></code></pre>
                    <p><strong>Testēšanai (katras 5 minūtes):</strong></p>
                    <pre><code>*/5 * * * * /usr/bin/php <?php echo $cron_file; ?></code></pre>
                </div>
                
                <div class="alert alert-warning">
                    <h5>Alternatīvs iestatījums (ja nav SSH piekļuves)</h5>
                    <p>Ja nevarat iestatīt cron job, varat izmantot web-based scheduler:</p>
                    <pre><code>*/15 * * * * /usr/bin/wget -O - -q -t 1 <?php echo SITE_URL; ?>/cron_scheduler.php</code></pre>
                </div>
            </div>
            
            <div class="col-md-4">
                <h5>Testēšanas darbības</h5>
                <div class="d-flex flex-column gap-2">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="test_scheduler">
                        <button type="submit" class="btn btn-primary w-100">Palaist scheduler tagad</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="test_notifications">
                        <button type="submit" class="btn btn-success w-100">Testēt paziņojumu sistēmu</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="debug_scheduler">
                        <button type="submit" class="btn btn-info w-100">Debug scheduler</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Vai tiešām vēlaties notīrīt log failu?')">Notīrīt log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pēdējie izveidotie regulārie uzdevumi -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Pēdējie izveidotie regulārie uzdevumi</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Uzdevums</th>
                        <th>Šablons</th>
                        <th>Mehāniķis</th>
                        <th>Statuss</th>
                        <th>Izveidots</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pēdējie_uzdevumi)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nav izveidoti regulārie uzdevumi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pēdējie_uzdevumi as $uzdevums): ?>
                            <tr>
                                <td><?php echo $uzdevums['id']; ?></td>
                                <td><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></td>
                                <td><?php echo htmlspecialchars($uzdevums['šablona_nosaukums'] ?? 'Nav norādīts'); ?></td>
                                <td><?php echo htmlspecialchars($uzdevums['mehaniķa_vards']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo getStatusClass($uzdevums['statuss']); ?>">
                                        <?php echo $uzdevums['statuss']; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($uzdevums['izveidots']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
                        <th>Šablons</th>
                        <th>Periodicitāte</th>
                        <th>Laiks</th>
                        <th>Prioritāte</th>
                        <th>Pēdējoreiz izveidots</th>
                        <th>Šodien izveidots</th>
                        <th>Aprēķinātais nākamais</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nākamie_uzdevumi)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav plānotu uzdevumu</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nākamie_uzdevumi as $uzdevums): ?>
                            <tr class="<?php echo $uzdevums['šodien_izveidots'] ? 'table-success' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></strong>
                                    <br><small class="text-muted">Izveidoti: <?php echo $uzdevums['izveidoto_skaits']; ?></small>
                                </td>
                                <td>
                                    <?php echo $uzdevums['periodicitate']; ?>
                                    <?php if ($uzdevums['periodicitas_dienas']): ?>
                                        <br><small class="text-muted">
                                            <?php 
                                            $dienas = json_decode($uzdevums['periodicitas_dienas'], true);
                                            if ($uzdevums['periodicitate'] === 'Katru nedēļu' && $dienas) {
                                                $nedēļas_dienas = ['', 'P', 'O', 'T', 'C', 'Pk', 'S', 'Sv'];
                                                echo implode(', ', array_map(function($d) use ($nedēļas_dienas) { return $nedēļas_dianas[$d] ?? $d; }, $dienas));
                                            } elseif ($uzdevums['periodicitate'] === 'Reizi mēnesī' && $dienas) {
                                                echo implode(', ', array_map(function($d) { return $d . '.'; }, $dienas));
                                            }
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $uzdevums['laiks'] ?? 'Nav norādīts'; ?></td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                        <?php echo $uzdevums['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($uzdevums['pēdējais_izveidots']): ?>
                                        <?php echo formatDate($uzdevums['pēdējais_izveidots']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nav</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($uzdevums['šodien_izveidots']): ?>
                                        <span class="text-success">✓ Jā</span>
                                    <?php else: ?>
                                        <span class="text-muted">Nē</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($uzdevums['aprēķinātais_nākamais_datums']): ?>
                                        <?php echo formatDate($uzdevums['aprēķinātais_nākamais_datums'], 'd.m.Y'); ?>
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
            <h4>Scheduler logs (pēdējās 100 rindas)</h4>
            <div>
                <button onclick="refreshLogs()" class="btn btn-sm btn-info">Atjaunot</button>
                <button onclick="autoRefreshToggle()" class="btn btn-sm btn-secondary" id="autoRefreshBtn">Auto-refresh: OFF</button>
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
let autoRefreshInterval = null;
let autoRefreshEnabled = false;

// Log faila atjaunošana
function refreshLogs() {
    fetch('<?php echo $log_file; ?>?t=' + Date.now())
        .then(response => response.text())
        .then(data => {
            const lines = data.trim().split('\n');
            const lastLines = lines.slice(-100).join('\n');
            document.getElementById('logContent').textContent = lastLines;
            
            // Automātiski scroll uz leju
            const logElement = document.getElementById('logContent');
            logElement.scrollTop = logElement.scrollHeight;
        })
        .catch(error => {
            console.error('Kļūda atjaunojot logs:', error);
        });
}

// Auto-refresh toggle
function autoRefreshToggle() {
    const btn = document.getElementById('autoRefreshBtn');
    
    if (autoRefreshEnabled) {
        clearInterval(autoRefreshInterval);
        autoRefreshEnabled = false;
        btn.textContent = 'Auto-refresh: OFF';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
    } else {
        autoRefreshInterval = setInterval(refreshLogs, 10000); // Katras 10 sekundes
        autoRefreshEnabled = true;
        btn.textContent = 'Auto-refresh: ON';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-success');
    }
}

// Sākotnēji refresh logs kad lapa ielādējas
document.addEventListener('DOMContentLoaded', function() {
    refreshLogs();
});
</script>

<style>
.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: var(--spacing-sm);
}

.stat-mini {
    text-align: center;
    padding: var(--spacing-xs);
    background: var(--gray-100);
    border-radius: var(--border-radius);
}

.stat-mini .stat-number {
    font-size: 1.2rem;
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
    max-height: 500px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.col-md-4 {
    flex: 1;
    min-width: 280px;
}

.col-md-8 {
    flex: 2;
    min-width: 400px;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.1);
}

.gap-2 {
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-4,
    .col-md-8 {
        width: 100%;
        flex: none;
    }
    
    .stats-mini {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    }
    
    .d-flex.flex-column {
        gap: 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>