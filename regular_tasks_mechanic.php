<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MECHANIC);

$pageTitle = 'Regulārie uzdevumi';
$pageHeader = 'Mani regulārie uzdevumi';

$currentUser = getCurrentUser();
$errors = [];

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
                    VALUES (?, 'Jauns', 'Procesā', 'Regulārā uzdevuma darbs sākts', ?)
                ");
                $stmt->execute([$task_id, $currentUser['id']]);
                
                $pdo->commit();
                setFlashMessage('success', 'Regulārā uzdevuma darbs sākts!');
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
            
            setFlashMessage('success', 'Regulārā uzdevuma darbs pauzēts!');
            
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
            
            setFlashMessage('success', 'Regulārā uzdevuma darbs atsākts!');
            
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
            
            // Pārbaudīt vai uzdevums pieder lietotājam
            $stmt = $pdo->prepare("SELECT statuss FROM uzdevumi WHERE id = ? AND piešķirts_id = ?");
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
                $stmt = $pdo->prepare("
                    INSERT INTO uzdevumu_vesture 
                    (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                    VALUES (?, ?, 'Pabeigts', ?, ?)
                ");
                $stmt->execute([
                    $task_id, 
                    $task['statuss'], 
                    'Regulārais uzdevums pabeigts' . ($komentars ? ': ' . $komentars : ''), 
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
                        'Regulārais uzdevums pabeigts',
                        "Mehāniķis {$currentUser['vards']} {$currentUser['uzvards']} ir pabeidzis regulāro uzdevumu: {$manager['nosaukums']}",
                        'Statusa maiņa',
                        'Uzdevums',
                        $task_id
                    );
                }
                
                $pdo->commit();
                setFlashMessage('success', 'Regulārais uzdevums pabeigts!');
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
    'periodicitate' => sanitizeInput($_GET['periodicitate'] ?? ''),
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'show_overdue' => isset($_GET['show_overdue']) ? 1 : 0
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'izveidots');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// Validēt kārtošanas parametrus
$allowed_sorts = ['izveidots', 'nosaukums', 'prioritate', 'statuss', 'periodicitate', 'jabeidz_lidz'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'izveidots';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Lapošana
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    // Būvēt vaicājumu
    $where_conditions = ["u.piešķirts_id = ? AND u.veids = 'Regulārais'"];
    $params = [$currentUser['id']];
    
    // Datuma filtrs
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $where_conditions[] = "DATE(u.izveidots) BETWEEN ? AND ?";
        $params[] = $filters['date_from'];
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['statuss'])) {
        $where_conditions[] = "u.statuss = ?";
        $params[] = $filters['statuss'];
    }
    
    if (!empty($filters['prioritate'])) {
        $where_conditions[] = "u.prioritate = ?";
        $params[] = $filters['prioritate'];
    }
    
    if (!empty($filters['periodicitate'])) {
        $where_conditions[] = "r.periodicitate = ?";
        $params[] = $filters['periodicitate'];
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
               r.periodicitas_dienas,
               r.laiks as sablona_laiks,
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
        LIMIT $limit OFFSET $offset
    ";
    
    $params[] = $currentUser['id']; // Priekš aktīvs_darbs subquery
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $regularie_uzdevumi = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "
        SELECT COUNT(*) 
        FROM uzdevumi u
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -1)); // Bez aktīvs_darbs parametra
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Regulāro uzdevumu šabloni, no kuriem man ir piešķirti uzdevumi
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COUNT(u.id) as uzdevumu_skaits,
               SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
               SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvo_skaits,
               SUM(CASE WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavēto_skaits,
               MAX(u.izveidots) as pēdējais_uzdevums,
               GROUP_CONCAT(
                   CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 
                       CONCAT(u.id, '|', u.statuss, '|', IFNULL((SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL), 0))
                   END 
                   SEPARATOR ';'
               ) as aktīvie_uzdevumi_info
        FROM regularo_uzdevumu_sabloni r
        INNER JOIN uzdevumi u ON r.id = u.regulara_uzdevuma_id
        WHERE u.piešķirts_id = ? AND r.aktīvs = 1
        GROUP BY r.id
        ORDER BY aktīvo_skaits DESC, nokavēto_skaits DESC, r.prioritate DESC, r.nosaukums
    ");
    $stmt->execute([$currentUser['id'], $currentUser['id']]);
    $mani_sabloni = $stmt->fetchAll();
    
    // Statistika
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_regulārie,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_regulārie,
            SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_regulārie,
            SUM(CASE WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavēti_regulārie,
            COUNT(DISTINCT u.regulara_uzdevuma_id) as dažādi_šabloni
        FROM uzdevumi u
        WHERE u.piešķirts_id = ? AND u.veids = 'Regulārais'
    ");
    $stmt->execute([$currentUser['id']]);
    $statistika = $stmt->fetch();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot regulāros uzdevumus: " . $e->getMessage();
    $regularie_uzdevumi = [];
    $mani_sabloni = [];
    $statistika = ['kopā_regulārie' => 0, 'pabeigti_regulārie' => 0, 'aktīvi_regulārie' => 0, 'nokavēti_regulārie' => 0, 'dažādi_šabloni' => 0];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Statistika -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-number"><?php echo $statistika['kopā_regulārie']; ?></div>
        <div class="stat-label">Kopā regulārie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);"><?php echo $statistika['pabeigti_regulārie']; ?></div>
        <div class="stat-label">Pabeigti regulārie</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo $statistika['aktīvi_regulārie']; ?></div>
        <div class="stat-label">Aktīvie regulārie</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);"><?php echo $statistika['nokavēti_regulārie']; ?></div>
        <div class="stat-label">Nokavētie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo $statistika['dažādi_šabloni']; ?></div>
        <div class="stat-label">Dažādi šabloni</div>
    </div>
</div>

<!-- Mani regulāro uzdevumu šabloni -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Mani regulāro uzdevumu šabloni</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Šablons</th>
                        <th>Periodicitāte</th>
                        <th>Prioritāte</th>
                        <th>Statistika</th>
                        <th>Pēdējais uzdevums</th>
                        <th>Aktīvie uzdevumi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mani_sabloni)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Jums nav piešķirti regulārie uzdevumi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mani_sabloni as $sablons): ?>
                            <tr class="<?php echo $sablons['nokavēto_skaits'] > 0 || $sablons['aktīvo_skaits'] > 0 ? 'table-warning' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($sablons['nosaukums']); ?></strong>
                                        <?php if ($sablons['nokavēto_skaits'] > 0): ?>
                                            <span class="badge badge-danger">Nokavēti: <?php echo $sablons['nokavēto_skaits']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($sablons['aktīvo_skaits'] > 0): ?>
                                            <span class="badge badge-warning">Aktīvi: <?php echo $sablons['aktīvo_skaits']; ?></span>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($sablons['apraksts'], 0, 100)) . (strlen($sablons['apraksts']) > 100 ? '...' : ''); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $sablons['periodicitate']; ?></strong>
                                        <?php if ($sablons['periodicitas_dienas']): ?>
                                            <br><small class="text-muted">
                                                <?php 
                                                $dienas = json_decode($sablons['periodicitas_dienas'], true);
                                                if ($sablons['periodicitate'] === 'Katru nedēļu' && $dienas) {
                                                    $nedēļas_dienas = ['', 'Pirmdiena', 'Otrdiena', 'Trešdiena', 'Ceturtdiena', 'Piektdiena', 'Sestdiena', 'Svētdiena'];
                                                    echo implode(', ', array_map(function($d) use ($nedēļas_dienas) { return $nedēļas_dienas[$d] ?? $d; }, $dienas));
                                                } elseif ($sablons['periodicitate'] === 'Reizi mēnesī' && $dienas) {
                                                    echo implode(', ', array_map(function($d) { return $d . '.'; }, $dienas)) . ' datums';
                                                }
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($sablons['laiks']): ?>
                                            <br><small class="text-muted">Laiks: <?php echo $sablons['laiks']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($sablons['prioritate']); ?>">
                                        <?php echo $sablons['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <small>Kopā: <?php echo $sablons['uzdevumu_skaits']; ?></small>
                                        <br><small>Pabeigti: <?php echo $sablons['pabeigto_skaits']; ?></small>
                                        <br><small>Aktīvi: <span class="<?php echo $sablons['aktīvo_skaits'] > 0 ? 'text-warning' : ''; ?>"><?php echo $sablons['aktīvo_skaits']; ?></span></small>
                                        <br><small>Nokavēti: <span class="<?php echo $sablons['nokavēto_skaits'] > 0 ? 'text-danger' : ''; ?>"><?php echo $sablons['nokavēto_skaits']; ?></span></small>
                                        <br><small>Efektivitāte: <?php echo $sablons['uzdevumu_skaits'] > 0 ? number_format(($sablons['pabeigto_skaits'] / $sablons['uzdevumu_skaits']) * 100, 1) : 0; ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($sablons['pēdējais_uzdevums']): ?>
                                        <small><?php echo formatDate($sablons['pēdējais_uzdevums']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Nav</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sablons['aktīvie_uzdevumi_info']): ?>
                                        <?php 
                                        $aktīvie_uzdevumi = explode(';', $sablons['aktīvie_uzdevumi_info']);
                                        foreach ($aktīvie_uzdevumi as $uzdevums_info):
                                            if (empty($uzdevums_info)) continue;
                                            $info_parts = explode('|', $uzdevums_info);
                                            $uzdevums_id = $info_parts[0];
                                            $uzdevums_statuss = $info_parts[1];
                                            $aktīvs_darbs = intval($info_parts[2]);
                                        ?>
                                            <div class="task-action-row mb-1">
                                                <small>ID: <?php echo $uzdevums_id; ?></small>
                                                <span class="status-badge <?php echo getStatusClass($uzdevums_statuss); ?> mx-1">
                                                    <?php echo $uzdevums_statuss; ?>
                                                </span>
                                                <?php if ($uzdevums_statuss === 'Jauns'): ?>
                                                    <button onclick="startWork(<?php echo $uzdevums_id; ?>)" class="btn btn-xs btn-success">Sākt</button>
                                                <?php elseif ($uzdevums_statuss === 'Procesā'): ?>
                                                    <?php if ($aktīvs_darbs > 0): ?>
                                                        <button onclick="pauseWork(<?php echo $uzdevums_id; ?>)" class="btn btn-xs btn-warning">Pauzēt</button>
                                                    <?php else: ?>
                                                        <button onclick="resumeWork(<?php echo $uzdevums_id; ?>)" class="btn btn-xs btn-warning">Turpināt</button>
                                                    <?php endif; ?>
                                                    <button onclick="completeTask(<?php echo $uzdevums_id; ?>)" class="btn btn-xs btn-success">Pabeigt</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <small class="text-muted">Nav aktīvo uzdevumu</small>
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

<!-- Filtru josla -->
<div class="filter-bar">
    <form method="GET" id="filterForm" class="filter-row">
        <div class="filter-col">
            <label for="date_from" class="form-label">Datums no</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
        </div>
        
        <div class="filter-col">
            <label for="date_to" class="form-label">Datums līdz</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
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
            <label for="periodicitate" class="form-label">Periodicitāte</label>
            <select id="periodicitate" name="periodicitate" class="form-control">
                <option value="">Visas periodicitātes</option>
                <option value="Katru dienu" <?php echo $filters['periodicitate'] === 'Katru dienu' ? 'selected' : ''; ?>>Katru dienu</option>
                <option value="Katru nedēļu" <?php echo $filters['periodicitate'] === 'Katru nedēļu' ? 'selected' : ''; ?>>Katru nedēļu</option>
                <option value="Reizi mēnesī" <?php echo $filters['periodicitate'] === 'Reizi mēnesī' ? 'selected' : ''; ?>>Reizi mēnesī</option>
                <option value="Reizi ceturksnī" <?php echo $filters['periodicitate'] === 'Reizi ceturksnī' ? 'selected' : ''; ?>>Reizi ceturksnī</option>
                <option value="Reizi gadā" <?php echo $filters['periodicitate'] === 'Reizi gadā' ? 'selected' : ''; ?>>Reizi gadā</option>
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

<!-- Regulāro uzdevumu saraksts -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Regulāro uzdevumu vēsture</h4>
            <span class="text-muted">Atrasti: <?php echo $total_records; ?> uzdevumi</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Periodicitāte</th>
                        <th>Prioritāte</th>
                        <th>Statuss</th>
                        <th>Izveidots</th>
                        <th>Termiņš</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regularie_uzdevumi)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav atrasti regulārie uzdevumi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regularie_uzdevumi as $uzdevums): ?>
                            <tr class="<?php echo $uzdevums['ir_nokavets'] ? 'table-danger' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></strong>
                                        <?php if ($uzdevums['failu_skaits'] > 0): ?>
                                            <span class="badge badge-info" title="Pievienoti faili">📎 <?php echo $uzdevums['failu_skaits']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                            <span class="working-badge" title="Darbs procesā">⏰ Darbs procesā</span>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['ir_nokavets']): ?>
                                            <span class="badge badge-danger">NOKAVĒTS</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($uzdevums['vietas_nosaukums'] ?? ''); ?>
                                        <?php if ($uzdevums['iekartas_nosaukums']): ?>
                                            - <?php echo htmlspecialchars($uzdevums['iekartas_nosaukums']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $uzdevums['periodicitate']; ?>
                                    <?php if ($uzdevums['sablona_laiks']): ?>
                                        <br><small class="text-muted">Laiks: <?php echo $uzdevums['sablona_laiks']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                        <?php echo $uzdevums['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo getStatusClass($uzdevums['statuss']); ?>">
                                        <?php echo $uzdevums['statuss']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo formatDate($uzdevums['izveidots']); ?></small>
                                </td>
                                <td>
                                    <?php if ($uzdevums['jabeidz_lidz']): ?>
                                        <small class="<?php echo $uzdevums['ir_nokavets'] ? 'text-danger' : ''; ?>">
                                            <?php echo formatDate($uzdevums['jabeidz_lidz']); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">Nav norādīts</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewTask(<?php echo $uzdevums['id']; ?>)" 
                                                class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>
                                        
                                        <?php if ($uzdevums['statuss'] === 'Jauns'): ?>
                                            <button onclick="startWork(<?php echo $uzdevums['id']; ?>)" 
                                                    class="btn btn-sm btn-success" title="Sākt darbu">▶</button>
                                        <?php elseif ($uzdevums['statuss'] === 'Procesā'): ?>
                                            <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                                <button onclick="pauseWork(<?php echo $uzdevums['id']; ?>)" 
                                                        class="btn btn-sm btn-warning" title="Pauzēt">⏸</button>
                                            <?php else: ?>
                                                <button onclick="resumeWork(<?php echo $uzdevums['id']; ?>)" 
                                                        class="btn btn-sm btn-warning" title="Turpināt">▶</button>
                                            <?php endif; ?>
                                            <button onclick="completeTask(<?php echo $uzdevums['id']; ?>)" 
                                                    class="btn btn-sm btn-success" title="Pabeigt">✓</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Lapošana -->
<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Iepriekšējā</a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Nākamā &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<!-- Uzdevuma pabeigšanas modāls -->
<div id="completeTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pabeigt regulāro uzdevumu</h3>
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

<script>
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

// Darba sākšana
function startWork(taskId) {
    if (confirm('Vai vēlaties sākt darbu pie šī regulārā uzdevuma?')) {
        submitWorkAction('start_work', taskId);
    }
}

// Darba pauzēšana
function pauseWork(taskId) {
    if (confirm('Vai vēlaties pauzēt darbu pie šī regulārā uzdevuma?')) {
        submitWorkAction('pause_work', taskId);
    }
}

// Darba atsākšana
function resumeWork(taskId) {
    if (confirm('Vai vēlaties atsākt darbu pie šī regulārā uzdevuma?')) {
        submitWorkAction('resume_work', taskId);
    }
}

// Uzdevuma pabeigšana
function completeTask(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('faktiskais_ilgums').value = '';
    document.getElementById('komentars').value = '';
    openModal('completeTaskModal');
}

// Palīgfunkcija darba darbībām
function submitWorkAction(action, taskId) {
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
document.querySelectorAll('#filterForm select, #filterForm input[type="date"], #filterForm input[type="checkbox"]').forEach(element => {
    element.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 11px;
    color: white;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-info {
    background: var(--info-color);
}

.badge-warning {
    background: var(--warning-color);
}

.badge-danger {
    background: var(--danger-color);
}

.working-badge {
    background: var(--warning-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    animation: pulse 1.5s infinite;
}

.btn-group {
    display: flex;
    gap: 2px;
}

.btn-group .btn {
    margin: 0;
    padding: 4px 8px;
    min-width: 32px;
}

.btn-xs {
    padding: 2px 6px;
    font-size: 11px;
    line-height: 1.2;
}

.task-action-row {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.task-action-row .status-badge {
    font-size: 10px;
    padding: 1px 4px;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1);
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .task-action-row {
        flex-direction: column;
        align-items: stretch;
        gap: 2px;
    }
    
    .task-action-row .btn {
        font-size: 10px;
        padding: 1px 4px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>