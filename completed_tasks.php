<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MECHANIC);

$pageTitle = 'Pabeigto uzdevumu vēsture';
$pageHeader = 'Pabeigto uzdevumu vēsture';

$currentUser = getCurrentUser();
$errors = [];

// Filtrēšanas parametri
$filters = [
    'prioritate' => sanitizeInput($_GET['prioritate'] ?? ''),
    'vieta' => intval($_GET['vieta'] ?? 0),
    'veids' => sanitizeInput($_GET['veids'] ?? ''),
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'meklēt' => sanitizeInput($_GET['meklēt'] ?? '')
];

// Noklusējuma datuma filtri tikai statistikai (neierobežo uzdevumu sarakstu)
$default_date_from = date('Y-m-01', strtotime('-6 months'));
$default_date_to = date('Y-m-d');

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'beigu_laiks');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// Validēt kārtošanas parametrus
$allowed_sorts = ['beigu_laiks', 'nosaukums', 'prioritate', 'faktiskais_ilgums', 'izveidots'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'beigu_laiks';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Lapošana
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Iegūt filtru datus
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    // Būvēt vaicājumu
    $where_conditions = ["u.piešķirts_id = ? AND u.statuss = 'Pabeigts'"];
    $params = [$currentUser['id']];
    
    // Datuma filtrs
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $where_conditions[] = "DATE(u.beigu_laiks) BETWEEN ? AND ?";
        $params[] = $filters['date_from'];
        $params[] = $filters['date_to'];
    } elseif (!empty($filters['date_from'])) {
        $where_conditions[] = "DATE(u.beigu_laiks) >= ?";
        $params[] = $filters['date_from'];
    } elseif (!empty($filters['date_to'])) {
        $where_conditions[] = "DATE(u.beigu_laiks) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['prioritate'])) {
        $where_conditions[] = "u.prioritate = ?";
        $params[] = $filters['prioritate'];
    }
    
    if ($filters['vieta'] > 0) {
        $where_conditions[] = "u.vietas_id = ?";
        $params[] = $filters['vieta'];
    }
    
    if (!empty($filters['veids'])) {
        $where_conditions[] = "u.veids = ?";
        $params[] = $filters['veids'];
    }
    
    if (!empty($filters['meklēt'])) {
        $where_conditions[] = "(u.nosaukums LIKE ? OR u.apraksts LIKE ?)";
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
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
               (SELECT SUM(stundu_skaits) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ?) as kopejais_darba_laiks
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
        $order_clause
        LIMIT $limit OFFSET $offset
    ";
    
    $params[] = $currentUser['id']; // Priekš kopejais_darba_laiks subquery
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pabeigti_uzdevumi = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "
        SELECT COUNT(*) 
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -1)); // Bez kopejais_darba_laiks parametra
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Statistika - izmantot noklusējuma datumus, ja nav filtru
    $stats_where = "u.piešķirts_id = ? AND u.statuss = 'Pabeigts'";
    $stats_params = [$currentUser['id']];
    
    // Statistikai izmantot datuma filtrus vai noklusējuma periodu
    $stats_date_from = !empty($filters['date_from']) ? $filters['date_from'] : $default_date_from;
    $stats_date_to = !empty($filters['date_to']) ? $filters['date_to'] : $default_date_to;
    
    // Pievienot datuma ierobežojumus statistikai
    $stats_where .= " AND DATE(u.beigu_laiks) BETWEEN ? AND ?";
    $stats_params[] = $stats_date_from;
    $stats_params[] = $stats_date_to;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_pabeigti,
            COUNT(CASE WHEN u.veids = 'Ikdienas' THEN 1 END) as ikdienas_pabeigti,
            COUNT(CASE WHEN u.veids = 'Regulārais' THEN 1 END) as regularie_pabeigti,
            AVG(u.faktiskais_ilgums) as videjais_ilgums,
            SUM(u.faktiskais_ilgums) as kopejais_ilgums
        FROM uzdevumi u
        WHERE $stats_where
    ");
    $stmt->execute($stats_params);
    $statistika = $stmt->fetch();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot pabeigtos uzdevumus: " . $e->getMessage();
    $pabeigti_uzdevumi = [];
    $statistika = ['kopā_pabeigti' => 0, 'ikdienas_pabeigti' => 0, 'regularie_pabeigti' => 0, 'videjais_ilgums' => 0, 'kopejais_ilgums' => 0];
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
        <div class="stat-number"><?php echo $statistika['kopā_pabeigti']; ?></div>
        <div class="stat-label">Kopā pabeigti</div>
        <small class="text-muted">
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                <?php echo $filters['date_from'] ?: 'sākums'; ?> - <?php echo $filters['date_to'] ?: 'beigas'; ?>
            <?php else: ?>
                Pēdējie 6 mēneši
            <?php endif; ?>
        </small>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo $statistika['ikdienas_pabeigti']; ?></div>
        <div class="stat-label">Ikdienas uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--secondary-color);">
        <div class="stat-number" style="color: var(--secondary-color);"><?php echo $statistika['regularie_pabeigti']; ?></div>
        <div class="stat-label">Regulārie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo number_format($statistika['videjais_ilgums'], 1); ?>h</div>
        <div class="stat-label">Vidējais ilgums</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);"><?php echo number_format($statistika['kopejais_ilgums'], 1); ?>h</div>
        <div class="stat-label">Kopējais darba laiks</div>
    </div>
</div>

<!-- Filtru josla -->
<div class="filter-bar">
    <form method="GET" id="filterForm" class="filter-row">
        <div class="filter-col">
            <label for="meklēt" class="form-label">Meklēt</label>
            <input 
                type="text" 
                id="meklēt" 
                name="meklēt" 
                class="form-control" 
                placeholder="Meklēt uzdevumos..."
                value="<?php echo htmlspecialchars($filters['meklēt']); ?>"
            >
        </div>
        
        <div class="filter-col">
            <label for="date_from" class="form-label">Pabeigts no</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
        </div>
        
        <div class="filter-col">
            <label for="date_to" class="form-label">Pabeigts līdz</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
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
            <label for="veids" class="form-label">Uzdevuma veids</label>
            <select id="veids" name="veids" class="form-control">
                <option value="">Visi veidi</option>
                <option value="Ikdienas" <?php echo $filters['veids'] === 'Ikdienas' ? 'selected' : ''; ?>>Ikdienas</option>
                <option value="Regulārais" <?php echo $filters['veids'] === 'Regulārais' ? 'selected' : ''; ?>>Regulārais</option>
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
        
        <div class="filter-col" style="display: flex; gap: 0.5rem; align-items: end;">
            <button type="submit" class="btn btn-primary">Filtrēt</button>
            <button type="button" onclick="clearFilters()" class="btn btn-secondary">Notīrīt</button>
        </div>
    </form>
</div>

<!-- Kārtošanas kontroles -->
<div class="sort-controls">
    <span>Kārtot pēc:</span>
    <button onclick="sortBy('beigu_laiks', '<?php echo $sort === 'beigu_laiks' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'beigu_laiks' ? 'active' : ''; ?>">
        Pabeigšanas datuma <?php echo $sort === 'beigu_laiks' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('prioritate', '<?php echo $sort === 'prioritate' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'prioritate' ? 'active' : ''; ?>">
        Prioritātes <?php echo $sort === 'prioritate' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('faktiskais_ilgums', '<?php echo $sort === 'faktiskais_ilgums' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'faktiskais_ilgums' ? 'active' : ''; ?>">
        Ilguma <?php echo $sort === 'faktiskais_ilgums' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
</div>

<!-- Pabeigto uzdevumu tabula -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h4>Pabeigto uzdevumu vēsture</h4>
            <span class="text-muted">Atrasti: <?php echo $total_records; ?> uzdevumi</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Vieta/Iekārta</th>
                        <th>Prioritāte</th>
                        <th>Veids</th>
                        <th>Pabeigts</th>
                        <th>Ilgums</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pabeigti_uzdevumi)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <p>Nav atrasti pabeigti uzdevumi izvēlētajos filtros.</p>
                                <?php if (!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['prioritate']) || !empty($filters['veids']) || !empty($filters['vieta']) || !empty($filters['meklēt'])): ?>
                                    <small class="text-muted">
                                        Mēģiniet mainīt filtrus vai noņemt ierobežojumus.<br>
                                        <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                                            Datuma filtrs: <?php echo $filters['date_from'] ?: 'nav'; ?> līdz <?php echo $filters['date_to'] ?: 'nav'; ?><br>
                                        <?php endif; ?>
                                        <a href="completed_tasks.php" class="btn btn-sm btn-primary mt-2">Rādīt visus pabeigtos uzdevumus</a>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">
                                        Jums vēl nav pabeigtu uzdevumu vai tie ir ārpus pēdējo 6 mēnešu perioda.<br>
                                        <a href="?date_from=&date_to=" class="btn btn-sm btn-primary mt-2">Rādīt visus pabeigtos uzdevumus</a>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pabeigti_uzdevumi as $uzdevums): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></strong>
                                        <?php if ($uzdevums['failu_skaits'] > 0): ?>
                                            <span class="badge badge-info" title="Pievienoti faili">📎 <?php echo $uzdevums['failu_skaits']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['periodicitate']): ?>
                                            <span class="badge badge-secondary" title="Regulārais uzdevums"><?php echo $uzdevums['periodicitate']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($uzdevums['apraksts'], 0, 100)) . (strlen($uzdevums['apraksts']) > 100 ? '...' : ''); ?>
                                    </small>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($uzdevums['vietas_nosaukums']): ?>
                                            <strong><?php echo htmlspecialchars($uzdevums['vietas_nosaukums']); ?></strong>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['iekartas_nosaukums']): ?>
                                            <br><small><?php echo htmlspecialchars($uzdevums['iekartas_nosaukums']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['kategorijas_nosaukums']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($uzdevums['kategorijas_nosaukums']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                        <?php echo $uzdevums['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="task-type-badge <?php echo $uzdevums['veids'] === 'Regulārais' ? 'regular' : 'daily'; ?>">
                                        <?php echo $uzdevums['veids']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo formatDate($uzdevums['beigu_laiks']); ?></strong>
                                    <br><small class="text-muted">Sākts: <?php echo formatDate($uzdevums['sakuma_laiks']); ?></small>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($uzdevums['faktiskais_ilgums']): ?>
                                            <strong><?php echo number_format($uzdevums['faktiskais_ilgums'], 1); ?>h</strong>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['kopejais_darba_laiks'] && $uzdevums['kopejais_darba_laiks'] != $uzdevums['faktiskais_ilgums']): ?>
                                            <br><small class="text-muted">Darba laiks: <?php echo number_format($uzdevums['kopejais_darba_laiks'], 1); ?>h</small>
                                        <?php endif; ?>
                                        <?php if ($uzdevums['paredzamais_ilgums']): ?>
                                            <br><small class="text-muted">Paredzēts: <?php echo number_format($uzdevums['paredzamais_ilgums'], 1); ?>h</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button onclick="viewTask(<?php echo $uzdevums['id']; ?>)" 
                                            class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>
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

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('meklēt');
    
    // Event listeners filtru elementiem
    document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(element => {
        element.addEventListener('change', function() {
            form.submit();
        });
    });
    
    // Meklēšanas lauka debounce
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            form.submit();
        }, 500);
    });
    
    // Filtru poga
    const filterButton = form.querySelector('button[type="submit"]');
    if (filterButton) {
        filterButton.addEventListener('click', function(e) {
            e.preventDefault();
            form.submit();
        });
    }
});

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

// Kārtošanas funkcija
function sortBy(column, direction) {
    const url = new URL(window.location);
    url.searchParams.set('sort', column);
    url.searchParams.set('order', direction);
    // Saglabāt esošos filtrus
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    for (let [key, value] of formData.entries()) {
        if (value) {
            url.searchParams.set(key, value);
        }
    }
    window.location = url;
}

// Filtru notīrīšana
function clearFilters() {
    window.location.href = 'completed_tasks.php';
}
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
    border-radius: 3px;
    margin-left: 5px;
}

.badge-info {
    background: var(--info-color);
    color: white;
}

.badge-secondary {
    background: var(--secondary-color);
    color: white;
}

.task-type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--white);
}

.task-type-badge.daily {
    background: var(--info-color);
}

.task-type-badge.regular {
    background: var(--secondary-color);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .table-responsive {
        font-size: var(--font-size-sm);
    }
}
</style>

<?php include 'includes/footer.php'; ?>