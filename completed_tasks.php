<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MECHANIC);

$pageTitle = 'Pabeigto uzdevumu vēsture';
$pageHeader = 'Pabeigto uzdevumu vēsture';

$currentUser = getCurrentUser();

// Filtru parametru iegūšana
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$completed_from = isset($_GET['completed_from']) ? $_GET['completed_from'] : '';
$completed_to = isset($_GET['completed_to']) ? $_GET['completed_to'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$task_type_filter = isset($_GET['task_type']) ? $_GET['task_type'] : '';

// Lapošana
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // SQL vaicājuma veidošana
    $sql = "SELECT u.*, v.nosaukums as vietas_nosaukums,
                   CASE 
                       WHEN u.veids = 'Ikdienas' THEN 'Ikdienas'
                       WHEN u.veids = 'Regulārais' THEN 'Regulārais'
                       ELSE u.veids 
                   END as veids_display,
                   CASE 
                       WHEN u.prioritate = 'Kritiska' THEN 'Kritiska'
                       WHEN u.prioritate = 'Augsta' THEN 'Augsta'
                       WHEN u.prioritate = 'Vidēja' THEN 'Vidēja'
                       WHEN u.prioritate = 'Zema' THEN 'Zema'
                       ELSE u.prioritate 
                   END as prioritate_display,
                   r.periodicitate,
                   (SELECT COUNT(*) FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = u.id) as failu_skaits
            FROM uzdevumi u 
            LEFT JOIN vietas v ON u.vietas_id = v.id 
            LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
            WHERE u.piešķirts_id = ? AND u.statuss = 'Pabeigts'";

    $params = [$currentUser['id']];
    $param_types = "i";

    // Pievienot meklēšanas filtru
    if (!empty($search)) {
        $sql .= " AND (u.nosaukums LIKE ? OR u.apraksts LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= "ss";
    }

    // Pievienot datuma filtrus
    if (!empty($completed_from)) {
        $sql .= " AND DATE(u.beigu_laiks) >= ?";
        $params[] = $completed_from;
        $param_types .= "s";
    }

    if (!empty($completed_to)) {
        $sql .= " AND DATE(u.beigu_laiks) <= ?";
        $params[] = $completed_to;
        $param_types .= "s";
    }

    // Pievienot prioritātes filtru
    if (!empty($priority_filter) && $priority_filter !== 'all') {
        $sql .= " AND u.prioritate = ?";
        $params[] = $priority_filter;
        $param_types .= "s";
    }

    // Pievienot uzdevuma veida filtru
    if (!empty($task_type_filter) && $task_type_filter !== 'all') {
        $sql .= " AND u.veids = ?";
        $params[] = $task_type_filter;
        $param_types .= "s";
    }

    // Pievieno kārtošanu un lapošanu
    $sql .= " ORDER BY u.beigu_laiks DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";

    // Vaicājuma izpilde
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "SELECT COUNT(*) FROM uzdevumi u 
                  LEFT JOIN vietas v ON u.vietas_id = v.id 
                  WHERE u.piešķirts_id = ? AND u.statuss = 'Pabeigts'";
    $count_params = [$currentUser['id']];
    
    if (!empty($search)) {
        $count_sql .= " AND (u.nosaukums LIKE ? OR u.apraksts LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }
    if (!empty($completed_from)) {
        $count_sql .= " AND DATE(u.beigu_laiks) >= ?";
        $count_params[] = $completed_from;
    }
    if (!empty($completed_to)) {
        $count_sql .= " AND DATE(u.beigu_laiks) <= ?";
        $count_params[] = $completed_to;
    }
    if (!empty($priority_filter) && $priority_filter !== 'all') {
        $count_sql .= " AND u.prioritate = ?";
        $count_params[] = $priority_filter;
    }
    if (!empty($task_type_filter) && $task_type_filter !== 'all') {
        $count_sql .= " AND u.veids = ?";
        $count_params[] = $task_type_filter;
    }
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Statistika
    $stats_sql = "SELECT 
                    COUNT(*) as kopejie_pabeigti,
                    SUM(CASE WHEN u.veids = 'Ikdienas' THEN 1 ELSE 0 END) as ikdienas_pabeigti,
                    SUM(CASE WHEN u.veids = 'Regulārais' THEN 1 ELSE 0 END) as regularie_pabeigti,
                    SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 1 ELSE 0 END) as kritiskie_pabeigti,
                    AVG(u.faktiskais_ilgums) as videjais_ilgums,
                    SUM(CASE WHEN MONTH(u.beigu_laiks) = MONTH(NOW()) AND YEAR(u.beigu_laiks) = YEAR(NOW()) THEN 1 ELSE 0 END) as somenes_pabeigti
                  FROM uzdevumi u 
                  WHERE u.piešķirts_id = ? AND u.statuss = 'Pabeigts'";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$currentUser['id']]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Kļūda completed_tasks.php: " . $e->getMessage());
    $result = [];
    $total_records = 0;
    $total_pages = 0;
    $stats = ['kopejie_pabeigti' => 0, 'ikdienas_pabeigti' => 0, 'regularie_pabeigti' => 0, 'kritiskie_pabeigti' => 0, 'videjais_ilgums' => 0, 'somenes_pabeigti' => 0];
}

include 'includes/header.php';
?>

<!-- Statistikas kartes -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['kopejie_pabeigti']; ?></div>
        <div class="stat-label">Kopā pabeigti uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['somenes_pabeigti']; ?></div>
        <div class="stat-label">Pabeigti šomēnes</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--primary-color);">
        <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['ikdienas_pabeigti']; ?></div>
        <div class="stat-label">Ikdienas uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['regularie_pabeigti']; ?></div>
        <div class="stat-label">Regulārie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['kritiskie_pabeigti']; ?></div>
        <div class="stat-label">Kritiski svarīgie</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['videjais_ilgums'] ? number_format($stats['videjais_ilgums'], 1) : '0'; ?>h</div>
        <div class="stat-label">Vidējais izpildes laiks</div>
    </div>
</div>

<!-- Filtru forma -->
<div class="filter-bar">
    <form method="GET" id="filterForm" class="filter-row">
        <!-- Meklēšana -->
        <div class="filter-col">
            <label class="form-label">Meklēt</label>
            <input type="text" class="form-control" name="search" 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Meklēt uzdevumos...">
        </div>
        
        <!-- Pabeigts no -->
        <div class="filter-col">
            <label class="form-label">Pabeigts no</label>
            <input type="date" class="form-control" name="completed_from" 
                   value="<?php echo htmlspecialchars($completed_from); ?>">
        </div>
        
        <!-- Pabeigts līdz -->
        <div class="filter-col">
            <label class="form-label">Pabeigts līdz</label>
            <input type="date" class="form-control" name="completed_to" 
                   value="<?php echo htmlspecialchars($completed_to); ?>">
        </div>
        
        <!-- Prioritāte -->
        <div class="filter-col">
            <label class="form-label">Prioritāte</label>
            <select name="priority" class="form-control">
                <option value="">Visas prioritātes</option>
                <option value="Kritiska" <?php echo $priority_filter === 'Kritiska' ? 'selected' : ''; ?>>Kritiska</option>
                <option value="Augsta" <?php echo $priority_filter === 'Augsta' ? 'selected' : ''; ?>>Augsta</option>
                <option value="Vidēja" <?php echo $priority_filter === 'Vidēja' ? 'selected' : ''; ?>>Vidēja</option>
                <option value="Zema" <?php echo $priority_filter === 'Zema' ? 'selected' : ''; ?>>Zema</option>
            </select>
        </div>
        
        <!-- Uzdevuma veids -->
        <div class="filter-col">
            <label class="form-label">Uzdevuma veids</label>
            <select name="task_type" class="form-control">
                <option value="">Visi veidi</option>
                <option value="Ikdienas" <?php echo $task_type_filter === 'Ikdienas' ? 'selected' : ''; ?>>Ikdienas</option>
                <option value="Regulārais" <?php echo $task_type_filter === 'Regulārais' ? 'selected' : ''; ?>>Regulārais</option>
            </select>
        </div>
        
        <!-- Pogas -->
        <div class="filter-col" style="display: flex; gap: 0.5rem; align-items: end;">
            <button type="submit" class="btn btn-primary">Filtrēt</button>
            <a href="completed_tasks.php" class="btn btn-secondary">Notīrīt</a>
        </div>
    </form>
</div>

<!-- Rezultātu skaits -->
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <small class="text-muted">Atrasti: <?php echo number_format($total_records); ?> uzdevumi</small>
        <div>
            <a href="my_tasks.php" class="btn btn-outline-primary btn-sm">Atgriezties pie aktīvajiem uzdevumiem</a>
        </div>
    </div>
</div>

<!-- Uzdevumu tabula -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Veids</th>
                        <th>Prioritāte</th>
                        <th>Vieta</th>
                        <th>Pabeigts</th>
                        <th>Pavadītais laiks</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($result) > 0): ?>
                        <?php foreach ($result as $task): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                        <?php if ($task['failu_skaits'] > 0): ?>
                                            <span class="badge badge-info" title="Pievienoti faili">📎 <?php echo $task['failu_skaits']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($task['periodicitate']): ?>
                                            <span class="badge badge-secondary" title="Regulārais uzdevums"><?php echo $task['periodicitate']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($task['apraksts'], 0, 100)) . (strlen($task['apraksts']) > 100 ? '...' : ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($task['veids_display']); ?></td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                        <?php echo htmlspecialchars($task['prioritate_display']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? 'Nav norādīta'); ?></td>
                                <td>
                                    <?php 
                                        if ($task['beigu_laiks']) {
                                            echo formatDate($task['beigu_laiks']);
                                        } else {
                                            echo 'Nav norādīts';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        if ($task['faktiskais_ilgums']) {
                                            echo number_format($task['faktiskais_ilgums'], 1) . 'h';
                                        } elseif ($task['sakuma_laiks'] && $task['beigu_laiks']) {
                                            $start = new DateTime($task['sakuma_laiks']);
                                            $end = new DateTime($task['beigu_laiks']);
                                            $interval = $start->diff($end);
                                            
                                            $time_spent = '';
                                            if ($interval->d > 0) $time_spent .= $interval->d . 'd ';
                                            if ($interval->h > 0) $time_spent .= $interval->h . 'h ';
                                            if ($interval->i > 0) $time_spent .= $interval->i . 'min';
                                            
                                            echo $time_spent ?: '< 1min';
                                        } else {
                                            echo 'Nav aprēķināts';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>
                                        <?php if (!empty($task['atbildes_komentars'])): ?>
                                            <button onclick="viewReport(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success" title="Skatīt atskaiti">📋</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="py-4">
                                    <h5>Nav atrasti pabeigti uzdevumi</h5>
                                    <p class="text-muted">Nav atrasti pabeigti uzdevumi ar norādītajiem kritērijiem.</p>
                                    <?php if (!empty($search) || !empty($completed_from) || !empty($completed_to) || !empty($priority_filter) || !empty($task_type_filter)): ?>
                                        <a href="completed_tasks.php" class="btn btn-primary btn-sm">Skatīt visus pabeigtos uzdevumus</a>
                                    <?php else: ?>
                                        <a href="my_tasks.php" class="btn btn-primary btn-sm">Skatīt aktīvos uzdevumus</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
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

<!-- Atskaites skatīšanas modāls -->
<div id="viewReportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Uzdevuma atskaite</h3>
            <button onclick="closeModal('viewReportModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="reportDetails">
            <!-- Saturs tiks ielādēts ar AJAX -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('viewReportModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.querySelector('input[name="search"]');
    
    // Event listeners filtru elementiem - automātiska forma iesniegšana
    document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(element => {
        element.addEventListener('change', function() {
            form.submit();
        });
    });
    
    // Meklēšanas lauka debounce (ar aizkavi 500ms)
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                form.submit();
            }, 500);
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

// Atskaites skatīšana
function viewReport(taskId) {
    fetch(`ajax/get_task_details.php?id=${taskId}`)
        .then(response => response.text())
        .then(html => {
            // Filtrēt tikai atskaites daļu
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const reportSection = doc.querySelector('.task-description') || doc.querySelector('[class*="komentars"]');
            
            let reportContent = '<div class="task-report">';
            if (reportSection) {
                reportContent += reportSection.outerHTML;
            } else {
                reportContent += '<p>Nav pieejama detalizēta atskaite.</p>';
            }
            reportContent += '</div>';
            
            document.getElementById('reportDetails').innerHTML = reportContent;
            openModal('viewReportModal');
        })
        .catch(error => {
            console.error('Kļūda:', error);
            alert('Kļūda ielādējot atskaiti');
        });
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
    color: white;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-info {
    background: var(--info-color);
}

.badge-secondary {
    background: var(--gray-500);
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

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    align-items: end;
}

.filter-col {
    flex: 1;
    min-width: 150px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-col {
        width: 100%;
        min-width: auto;
    }
}

.task-report {
    background: var(--gray-100);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin: var(--spacing-md) 0;
}

.pagination {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-lg);
    gap: var(--spacing-xs);
}

.pagination a,
.pagination span {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--gray-300);
    color: var(--gray-700);
    text-decoration: none;
    border-radius: var(--border-radius);
    transition: all 0.3s ease;
}

.pagination a:hover {
    background: var(--secondary-color);
    color: var(--white);
    border-color: var(--secondary-color);
}

.pagination .current {
    background: var(--secondary-color);
    color: var(--white);
    border-color: var(--secondary-color);
}
</style>

<?php include 'includes/footer.php'; ?>