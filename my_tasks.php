<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MECHANIC);

$pageTitle = 'Mani uzdevumi';
$pageHeader = 'Mani uzdevumi';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai uzdevums pieder lietotājam
            $stmt = $pdo->prepare("SELECT statuss FROM uzdevumi WHERE id = ? AND piešķirts_id = ?");
            $stmt->execute([$task_id, $currentUser['id']]);
            $task = $stmt->fetch();
            
            if ($task && $task['statuss'] === 'Jauns') {
                // Mainīt statusu uz "Procesā"
                $stmt = $pdo->prepare("UPDATE uzdevumi SET statuss = 'Procesā', sakuma_laiks = NOW() WHERE id = ?");
                $stmt->execute([$task_id]);
                
                // Sākt darba laika uzskaiti
                $stmt = $pdo->prepare("
                    INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $task_id]);
                
                // Pievienot vēsturi
                $stmt = $pdo->prepare("
                    INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                    VALUES (?, 'Jauns', 'Procesā', 'Darbs sākts', ?)
                ");
                $stmt->execute([$task_id, $currentUser['id']]);
                
                $pdo->commit();
                setFlashMessage('success', 'Darbs sākts!');
            } else {
                $errors[] = 'Nevar sākt darbu pie šī uzdevuma.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda sākot darbu: ' . $e->getMessage();
        }
    }
    
    if ($action === 'pause_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            // Pabeigt pašreizējo darba laika ierakstu
            $stmt = $pdo->prepare("
                UPDATE darba_laiks 
                SET beigu_laiks = NOW(), 
                    stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
                WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
            ");
            $stmt->execute([$task_id, $currentUser['id']]);
            
            setFlashMessage('success', 'Darbs pauzēts!');
            
        } catch (PDOException $e) {
            $errors[] = 'Kļūda pauzējot darbu: ' . $e->getMessage();
        }
    }
    
    if ($action === 'resume_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            // Sākt jaunu darba laika ierakstu
            $stmt = $pdo->prepare("
                INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$currentUser['id'], $task_id]);
            
            setFlashMessage('success', 'Darbs atsākts!');
            
        } catch (PDOException $e) {
            $errors[] = 'Kļūda atsākot darbu: ' . $e->getMessage();
        }
    }
    
    if ($action === 'complete_task' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        $komentars = sanitizeInput($_POST['komentars'] ?? '');
        $faktiskais_ilgums = floatval($_POST['faktiskais_ilgums'] ?? 0);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai uzdevums pieder lietotājam un iegūt uzdevuma veidu
            $stmt = $pdo->prepare("SELECT statuss, veids FROM uzdevumi WHERE id = ? AND piešķirts_id = ?");
            $stmt->execute([$task_id, $currentUser['id']]);
            $task = $stmt->fetch();
            
            if ($task && in_array($task['statuss'], ['Jauns', 'Procesā'])) {
                // Pabeigt darba laika uzskaiti
                $stmt = $pdo->prepare("
                    UPDATE darba_laiks 
                    SET beigu_laiks = NOW(), 
                        stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
                    WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
                ");
                $stmt->execute([$task_id, $currentUser['id']]);
                
                // Aprēķināt kopējo darba laiku
                $stmt = $pdo->prepare("
                    SELECT SUM(stundu_skaits) as kopejais_laiks 
                    FROM darba_laiks 
                    WHERE uzdevuma_id = ? AND lietotaja_id = ?
                ");
                $stmt->execute([$task_id, $currentUser['id']]);
                $total_time = $stmt->fetchColumn() ?: 0;
                
                // Atjaunot uzdevuma statusu
                $stmt = $pdo->prepare("
                    UPDATE uzdevumi 
                    SET statuss = 'Pabeigts', 
                        beigu_laiks = NOW(), 
                        atbildes_komentars = ?,
                        faktiskais_ilgums = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $komentars, 
                    $faktiskais_ilgums ?: $total_time, 
                    $task_id
                ]);
                
                // Pievienot vēsturi
                $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';
                $stmt = $pdo->prepare("
                    INSERT INTO uzdevumu_vesture 
                    (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                    VALUES (?, ?, 'Pabeigts', ?, ?)
                ");
                $stmt->execute([
                    $task_id, 
                    $task['statuss'], 
                    "$uzdevuma_tips pabeigts" . ($komentars ? ': ' . $komentars : ''), 
                    $currentUser['id']
                ]);
                
                // Paziņot menedžerim/administratoram
                $stmt = $pdo->prepare("
                    SELECT u.nosaukums, l.id, l.loma 
                    FROM uzdevumi u, lietotaji l 
                    WHERE u.id = ? AND l.loma IN ('Administrators', 'Menedžeris') AND l.statuss = 'Aktīvs'
                ");
                $stmt->execute([$task_id]);
                $managers = $stmt->fetchAll();
                
                foreach ($managers as $manager) {
                    createNotification(
                        $manager['id'],
                        "$uzdevuma_tips pabeigts",
                        "Mehāniķis {$currentUser['vards']} {$currentUser['uzvards']} ir pabeidzis uzdevumu: {$manager['nosaukums']}",
                        'Statusa maiņa',
                        'Uzdevums',
                        $task_id
                    );
                }
                
                $pdo->commit();
                setFlashMessage('success', "$uzdevuma_tips pabeigts!");
            } else {
                $errors[] = 'Nevar pabeigt šo uzdevumu.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda pabeidzot uzdevumu: ' . $e->getMessage();
        }
    }
}

// Filtrēšanas parametri
$filters = [
    'statuss' => sanitizeInput($_GET['statuss'] ?? ''),
    'prioritate' => sanitizeInput($_GET['prioritate'] ?? ''),
    'vieta' => intval($_GET['vieta'] ?? 0),
    'veids' => sanitizeInput($_GET['veids'] ?? 'Ikdienas'), // Pēc noklusējuma rādīt ikdienas uzdevumus
    'show_overdue' => isset($_GET['show_overdue']) ? 1 : 0
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'prioritate');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// Validēt kārtošanas parametrus
$allowed_sorts = ['izveidots', 'nosaukums', 'prioritate', 'statuss', 'jabeidz_lidz'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'prioritate';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

try {
    // Iegūt filtru datus
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    // Būvēt vaicājumu
    $where_conditions = ["u.piešķirts_id = ?"];
    $params = [$currentUser['id']];
    
    // Uzdevuma veida filtrs
    if (!empty($filters['veids'])) {
        $where_conditions[] = "u.veids = ?";
        $params[] = $filters['veids'];
    }
    
    if (!empty($filters['statuss'])) {
        $where_conditions[] = "u.statuss = ?";
        $params[] = $filters['statuss'];
    }
    
    if (!empty($filters['prioritate'])) {
        $where_conditions[] = "u.prioritate = ?";
        $params[] = $filters['prioritate'];
    }
    
    if ($filters['vieta'] > 0) {
        $where_conditions[] = "u.vietas_id = ?";
        $params[] = $filters['vieta'];
    }
    
    // Nokavēto uzdevumu filtrs
    if ($filters['show_overdue']) {
        $where_conditions[] = "((u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts')) OR u.statuss IN ('Jauns', 'Procesā'))";
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Prioritātes kārtošanas loģika
    $order_clause = "ORDER BY ";
    if ($sort === 'prioritate') {
        $order_clause .= "CASE u.prioritate 
                          WHEN 'Kritiska' THEN 1 
                          WHEN 'Augsta' THEN 2 
                          WHEN 'Vidēja' THEN 3 
                          WHEN 'Zema' THEN 4 
                          END " . ($order === 'DESC' ? 'ASC' : 'DESC') . ", ";
    }
    $order_clause .= "u.$sort $order";
    
    // Galvenais vaicājums
    $sql = "
        SELECT u.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               k.nosaukums as kategorijas_nosaukums,
               r.periodicitate,
               (SELECT COUNT(*) FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = u.id) as failu_skaits,
               (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
               CASE 
                   WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 
                   ELSE 0 
               END as ir_nokavets
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
        $order_clause
    ";
    
    $params[] = $currentUser['id']; // Priekš aktīvs_darbs subquery
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $uzdevumi = $stmt->fetchAll();
    
    // Statistika pa veidiem
    $stmt = $pdo->prepare("
        SELECT 
            u.veids,
            COUNT(*) as kopā,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti,
            SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi,
            SUM(CASE WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavēti
        FROM uzdevumi u
        WHERE u.piešķirts_id = ?
        GROUP BY u.veids
    ");
    $stmt->execute([$currentUser['id']]);
    $statistika_pa_veidiem = [];
    while ($row = $stmt->fetch()) {
        $statistika_pa_veidiem[$row['veids']] = $row;
    }
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot uzdevumus: " . $e->getMessage();
    $uzdevumi = [];
    $statistika_pa_veidiem = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Statistikas kartes pa uzdevumu veidiem -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-number"><?php echo ($statistika_pa_veidiem['Ikdienas']['kopā'] ?? 0); ?></div>
        <div class="stat-label">Ikdienas uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo ($statistika_pa_veidiem['Regulārais']['kopā'] ?? 0); ?></div>
        <div class="stat-label">Regulārie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);">
            <?php 
            $total_active = ($statistika_pa_veidiem['Ikdienas']['aktīvi'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['aktīvi'] ?? 0);
            echo $total_active;
            ?>
        </div>
        <div class="stat-label">Aktīvie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);">
            <?php 
            $total_completed = ($statistika_pa_veidiem['Ikdienas']['pabeigti'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['pabeigti'] ?? 0);
            echo $total_completed;
            ?>
        </div>
        <div class="stat-label">Pabeigti uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);">
            <?php 
            $total_overdue = ($statistika_pa_veidiem['Ikdienas']['nokavēti'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['nokavēti'] ?? 0);
            echo $total_overdue;
            ?>
        </div>
        <div class="stat-label">Nokavētie uzdevumi</div>
    </div>
</div>

<!-- Filtru josla -->
<div class="filter-bar">
    <form method="GET" id="filterForm" class="filter-row">
        <div class="filter-col">
            <label for="veids" class="form-label">Uzdevuma veids</label>
            <select id="veids" name="veids" class="form-control">
                <option value="">Visi veidi</option>
                <option value="Ikdienas" <?php echo $filters['veids'] === 'Ikdienas' ? 'selected' : ''; ?>>Ikdienas uzdevumi</option>
                <option value="Regulārais" <?php echo $filters['veids'] === 'Regulārais' ? 'selected' : ''; ?>>Regulārie uzdevumi</option>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="statuss" class="form-label">Statuss</label>
            <select id="statuss" name="statuss" class="form-control">
                <option value="">Visi statusi</option>
                <option value="Jauns" <?php echo $filters['statuss'] === 'Jauns' ? 'selected' : ''; ?>>Jauns</option>
                <option value="Procesā" <?php echo $filters['statuss'] === 'Procesā' ? 'selected' : ''; ?>>Procesā</option>
                <option value="Pabeigts" <?php echo $filters['statuss'] === 'Pabeigts' ? 'selected' : ''; ?>>Pabeigts</option>
                <option value="Atcelts" <?php echo $filters['statuss'] === 'Atcelts' ? 'selected' : ''; ?>>Atcelts</option>
                <option value="Atlikts" <?php echo $filters['statuss'] === 'Atlikts' ? 'selected' : ''; ?>>Atlikts</option>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="prioritate" class="form-label">Prioritāte</label>
            <select id="prioritate" name="prioritate" class="form-control">
                <option value="">Visas prioritātes</option>
                <option value="Kritiska" <?php echo $filters['prioritate'] === 'Kritiska' ? 'selected' : ''; ?>>Kritiska</option>
                <option value="Augsta" <?php echo $filters['prioritate'] === 'Augsta' ? 'selected' : ''; ?>>Augsta</option>
                <option value="Vidēja" <?php echo $filters['prioritate'] === 'Vidēja' ? 'selected' : ''; ?>>Vidēja</option>
                <option value="Zema" <?php echo $filters['prioritate'] === 'Zema' ? 'selected' : ''; ?>>Zema</option>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="vieta" class="form-label">Vieta</label>
            <select id="vieta" name="vieta" class="form-control">
                <option value="">Visas vietas</option>
                <?php foreach ($vietas as $vieta): ?>
                    <option value="<?php echo $vieta['id']; ?>" <?php echo $filters['vieta'] == $vieta['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-col">
            <label class="form-label">
                <input type="checkbox" name="show_overdue" value="1" <?php echo $filters['show_overdue'] ? 'checked' : ''; ?>> 
                Tikai nokavētie/aktīvie
            </label>
        </div>
        
        <div class="filter-col" style="display: flex; gap: 0.5rem; align-items: end;">
            <button type="submit" class="btn btn-primary">Filtrēt</button>
            <button type="button" onclick="clearFilters()" class="btn btn-secondary">Notīrīt</button>
        </div>
    </form>
</div>

<!-- Kārtošanas kontroles -->
<div class="sort-controls">
    <span>Kārtot pēc:</span>
    <button onclick="sortBy('prioritate', '<?php echo $sort === 'prioritate' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'prioritate' ? 'active' : ''; ?>">
        Prioritātes <?php echo $sort === 'prioritate' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('jabeidz_lidz', '<?php echo $sort === 'jabeidz_lidz' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>')" 
            class="sort-btn <?php echo $sort === 'jabeidz_lidz' ? 'active' : ''; ?>">
        Termiņa <?php echo $sort === 'jabeidz_lidz' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
    </button>
    <button onclick="sortBy('izveidots', '<?php echo $sort === 'izveidots' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'izveidots' ? 'active' : ''; ?>">
        Datuma <?php echo $sort === 'izveidots' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
</div>

<!-- Uzdevumu saraksts -->
<div class="tasks-grid">
    <?php if (empty($uzdevumi)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h4>Nav uzdevumu</h4>
                <p>Jums pašlaik nav piešķirti uzdevumi atbilstoši izvēlētajiem filtriem.</p>
                <?php if (!empty($filters['veids']) || !empty($filters['statuss']) || !empty($filters['prioritate']) || $filters['vieta'] > 0): ?>
                    <p><a href="my_tasks.php" class="btn btn-primary">Skatīt visus uzdevumus</a></p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($uzdevumi as $uzdevums): ?>
            <div class="task-card <?php echo strtolower($uzdevums['prioritate']); ?> <?php echo strtolower(str_replace(' ', '-', $uzdevums['statuss'])); ?> <?php echo $uzdevums['ir_nokavets'] ? 'overdue' : ''; ?> <?php echo $uzdevums['veids'] === 'Regulārais' ? 'regular-task' : ''; ?>">
                <div class="task-header">
                    <div class="task-title">
                        <h4>
                            <?php echo htmlspecialchars($uzdevums['nosaukums']); ?>
                            <?php if ($uzdevums['veids'] === 'Regulārais'): ?>
                                <span class="task-type-badge">Regulārais</span>
                            <?php endif; ?>
                        </h4>
                        <div class="task-badges">
                            <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                <?php echo $uzdevums['prioritate']; ?>
                            </span>
                            <span class="status-badge <?php echo getStatusClass($uzdevums['statuss']); ?>">
                                <?php echo $uzdevums['statuss']; ?>
                            </span>
                            <?php if ($uzdevums['failu_skaits'] > 0): ?>
                                <span class="file-badge" title="Pievienoti faili">📎 <?php echo $uzdevums['failu_skaits']; ?></span>
                            <?php endif; ?>
                            <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                <span class="working-badge" title="Darbs procesā">⏰ Darbs procesā</span>
                            <?php endif; ?>
                            <?php if ($uzdevums['ir_nokavets']): ?>
                                <span class="overdue-badge" title="Nokavēts">⚠️ NOKAVĒTS</span>
                            <?php endif; ?>
                            <?php if ($uzdevums['periodicitate']): ?>
                                <span class="periodicity-badge" title="Periodicitāte"><?php echo $uzdevums['periodicitate']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="task-body">
                    <div class="task-meta">
                        <?php if ($uzdevums['vietas_nosaukums']): ?>
                            <div><strong>Vieta:</strong> <?php echo htmlspecialchars($uzdevums['vietas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['iekartas_nosaukums']): ?>
                            <div><strong>Iekārta:</strong> <?php echo htmlspecialchars($uzdevums['iekartas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['kategorijas_nosaukums']): ?>
                            <div><strong>Kategorija:</strong> <?php echo htmlspecialchars($uzdevums['kategorijas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['jabeidz_lidz']): ?>
                            <div><strong>Termiņš:</strong> 
                                <span class="<?php echo $uzdevums['ir_nokavets'] ? 'text-danger' : ''; ?>">
                                    <?php echo formatDate($uzdevums['jabeidz_lidz']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($uzdevums['paredzamais_ilgums']): ?>
                            <div><strong>Paredzamais ilgums:</strong> <?php echo $uzdevums['paredzamais_ilgums']; ?> h</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-description">
                        <?php echo htmlspecialchars(substr($uzdevums['apraksts'], 0, 200)) . (strlen($uzdevums['apraksts']) > 200 ? '...' : ''); ?>
                    </div>
                </div>
                
                <div class="task-footer">
                    <div class="task-actions">
                        <button onclick="viewTask(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-info">Skatīt detaļas</button>
                        
                        <?php if ($uzdevums['statuss'] === 'Jauns'): ?>
                            <button onclick="startWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-success">Sākt darbu</button>
                        <?php elseif ($uzdevums['statuss'] === 'Procesā'): ?>
                            <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                <button onclick="pauseWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-warning">Pauzēt</button>
                            <?php else: ?>
                                <button onclick="resumeWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-warning">Turpināt</button>
                            <?php endif; ?>
                            <button onclick="completeTask(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-time">
                        <small>Izveidots: <?php echo formatDate($uzdevums['izveidots']); ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modālie logi -->

<!-- Uzdevuma pabeigšanas modāls -->
<div id="completeTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pabeigt uzdevumu</h3>
            <button onclick="closeModal('completeTaskModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="completeTaskForm" method="POST">
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="completeTaskId">
                
                <div class="form-group">
                    <label for="faktiskais_ilgums" class="form-label">Faktiskais izpildes laiks (stundas)</label>
                    <input type="number" id="faktiskais_ilgums" name="faktiskais_ilgums" class="form-control" step="0.1" min="0">
                    <small class="form-text text-muted">Atstājiet tukšu, lai automātiski aprēķinātu no darba laika</small>
                </div>
                
                <div class="form-group">
                    <label for="komentars" class="form-label">Komentārs par paveikto darbu</label>
                    <textarea id="komentars" name="komentars" class="form-control" rows="4" placeholder="Aprakstiet paveikto darbu, izmantotie materiāli, problēmas, u.c."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('completeTaskModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('completeTaskForm').submit()" class="btn btn-success">Pabeigt uzdevumu</button>
        </div>
    </div>
</div>

<!-- Uzdevuma skatīšanas modāls -->
<div id="viewTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Uzdevuma detaļas</h3>
            <button onclick="closeModal('viewTaskModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="taskDetails">
            <!-- Saturs tiks ielādēts ar AJAX -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('viewTaskModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Darba sākšana
function startWork(taskId) {
    if (confirm('Vai vēlaties sākt darbu pie šī uzdevuma?')) {
        submitAction('start_work', taskId);
    }
}

// Darba pauzēšana
function pauseWork(taskId) {
    if (confirm('Vai vēlaties pauzēt darbu pie šī uzdevuma?')) {
        submitAction('pause_work', taskId);
    }
}

// Darba atsākšana
function resumeWork(taskId) {
    if (confirm('Vai vēlaties atsākt darbu pie šī uzdevuma?')) {
        submitAction('resume_work', taskId);
    }
}

// Uzdevuma pabeigšana
function completeTask(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('faktiskais_ilgums').value = '';
    document.getElementById('komentars').value = '';
    openModal('completeTaskModal');
}

// Uzdevuma detaļu skatīšana
function viewTask(taskId) {
    fetch(`ajax/get_task_details.php?id=${taskId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('taskDetails').innerHTML = html;
            openModal('viewTaskModal');
        })
        .catch(error => {
            console.error('Kļūda:', error);
            alert('Kļūda ielādējot uzdevuma detaļas');
        });
}

// Palīgfunkcija POST darbībām
function submitAction(action, taskId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    
    const taskInput = document.createElement('input');
    taskInput.type = 'hidden';
    taskInput.name = 'task_id';
    taskInput.value = taskId;
    
    form.appendChild(actionInput);
    form.appendChild(taskInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Filtru automātiska iesniegšana
document.querySelectorAll('#filterForm select, #filterForm input[type="checkbox"]').forEach(element => {
    element.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>

<style>
/* Uzdevumu režģa izkārtojums */
.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--spacing-lg);
    margin-top: var(--spacing-lg);
}

@media (max-width: 768px) {
    .tasks-grid {
        grid-template-columns: 1fr;
    }
}

/* Uzdevuma kartes */
.task-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: all 0.3s ease;
    border-left: 4px solid var(--gray-400);
    position: relative;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.task-card.kritiska {
    border-left-color: var(--priority-critical);
}

.task-card.augsta {
    border-left-color: var(--priority-high);
}

.task-card.vidēja {
    border-left-color: var(--priority-medium);
}

.task-card.zema {
    border-left-color: var(--priority-low);
}

.task-card.procesā {
    background: linear-gradient(135deg, var(--white) 0%, rgba(243, 156, 18, 0.05) 100%);
}

.task-card.pabeigts {
    opacity: 0.8;
    background: linear-gradient(135deg, var(--white) 0%, rgba(39, 174, 96, 0.05) 100%);
}

.task-card.overdue {
    background: linear-gradient(135deg, var(--white) 0%, rgba(231, 76, 60, 0.05) 100%);
    border-left-color: var(--danger-color) !important;
}

.task-card.regular-task::before {
    content: 'R';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    background: var(--info-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.task-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--gray-300);
}

.task-title h4 {
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.task-badges {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.task-type-badge {
    background: var(--info-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 8px;
}

.overdue-badge {
    background: var(--danger-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    animation: pulse 1.5s infinite;
}

.periodicity-badge {
    background: var(--secondary-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.task-body {
    padding: var(--spacing-md);
}

.task-meta {
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-sm);
}

.task-meta div {
    margin-bottom: var(--spacing-xs);
    color: var(--gray-600);
}

.task-description {
    color: var(--gray-700);
    line-height: 1.5;
    margin-bottom: var(--spacing-md);
}

.task-footer {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-300);
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.task-actions {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.task-time {
    color: var(--gray-500);
    font-size: var(--font-size-sm);
}

/* Papildu iezīmes */
.file-badge {
    background: var(--info-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.working-badge {
    background: var(--warning-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    animation: pulse 1.5s infinite;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

@media (max-width: 480px) {
    .task-footer {
        flex-direction: column;
        align-items: stretch;
    }
    
    .task-actions {
        justify-content: center;
    }
    
    .task-time {
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<?php include 'includes/footer.php'; ?>