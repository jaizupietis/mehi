<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_OPERATOR);

$pageTitle = 'Manas problēmas';
$pageHeader = 'Manas ziņotās problēmas';

$currentUser = getCurrentUser();
$errors = [];

// Filtrēšanas parametri
$filters = [
    'statuss' => sanitizeInput($_GET['statuss'] ?? ''),
    'prioritate' => sanitizeInput($_GET['prioritate'] ?? ''),
    'vieta' => intval($_GET['vieta'] ?? 0),
    'meklēt' => sanitizeInput($_GET['meklēt'] ?? '')
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'izveidots');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// Validēt kārtošanas parametrus
$allowed_sorts = ['izveidots', 'nosaukums', 'prioritate', 'statuss', 'sarezgitibas_pakape'];
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
    // Iegūt filtru datus
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    // Būvēt vaicājumu
    $where_conditions = ["p.zinotajs_id = ?"];
    $params = [$currentUser['id']];
    
    if (!empty($filters['statuss'])) {
        $where_conditions[] = "p.statuss = ?";
        $params[] = $filters['statuss'];
    }
    
    if (!empty($filters['prioritate'])) {
        $where_conditions[] = "p.prioritate = ?";
        $params[] = $filters['prioritate'];
    }
    
    if ($filters['vieta'] > 0) {
        $where_conditions[] = "p.vietas_id = ?";
        $params[] = $filters['vieta'];
    }
    
    if (!empty($filters['meklēt'])) {
        $where_conditions[] = "(p.nosaukums LIKE ? OR p.apraksts LIKE ?)";
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Prioritātes kārtošanas loģika
    $order_clause = "ORDER BY ";
    if ($sort === 'prioritate') {
        $order_clause .= "CASE p.prioritate 
                          WHEN 'Kritiska' THEN 1 
                          WHEN 'Augsta' THEN 2 
                          WHEN 'Vidēja' THEN 3 
                          WHEN 'Zema' THEN 4 
                          END " . ($order === 'DESC' ? 'ASC' : 'DESC') . ", ";
    }
    $order_clause .= "p.$sort $order";
    
    // Galvenais vaicājums
    $sql = "
        SELECT p.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               CONCAT(a.vards, ' ', a.uzvards) as apstradasija_vards,
               (SELECT COUNT(*) FROM faili WHERE tips = 'Problēma' AND saistitas_id = p.id) as failu_skaits,
               (SELECT COUNT(*) FROM uzdevumi WHERE problemas_id = p.id) as uzdevumu_skaits
        FROM problemas p
        LEFT JOIN vietas v ON p.vietas_id = v.id
        LEFT JOIN iekartas i ON p.iekartas_id = i.id
        LEFT JOIN lietotaji a ON p.apstradasija_id = a.id
        $where_clause
        $order_clause
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $problemas = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "
        SELECT COUNT(*) 
        FROM problemas p
        LEFT JOIN vietas v ON p.vietas_id = v.id
        LEFT JOIN iekartas i ON p.iekartas_id = i.id
        LEFT JOIN lietotaji a ON p.apstradasija_id = a.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Iegūt statistiku
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā,
            SUM(CASE WHEN statuss = 'Jauna' THEN 1 ELSE 0 END) as jaunas,
            SUM(CASE WHEN statuss = 'Apskatīta' THEN 1 ELSE 0 END) as apskatītas,
            SUM(CASE WHEN statuss = 'Pārvērsta uzdevumā' THEN 1 ELSE 0 END) as pārvērstas,
            SUM(CASE WHEN statuss = 'Atcelta' THEN 1 ELSE 0 END) as atceltas
        FROM problemas 
        WHERE zinotajs_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot problēmas: " . $e->getMessage();
    $problemas = [];
    $stats = ['kopā' => 0, 'jaunas' => 0, 'apskatītas' => 0, 'pārvērstas' => 0, 'atceltas' => 0];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

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
                placeholder="Meklēt problēmās..."
                value="<?php echo htmlspecialchars($filters['meklēt']); ?>"
            >
        </div>
        
        <div class="filter-col">
            <label for="statuss" class="form-label">Statuss</label>
            <select id="statuss" name="statuss" class="form-control">
                <option value="">Visi statusi</option>
                <option value="Jauna" <?php echo $filters['statuss'] === 'Jauna' ? 'selected' : ''; ?>>Jauna</option>
                <option value="Apskatīta" <?php echo $filters['statuss'] === 'Apskatīta' ? 'selected' : ''; ?>>Apskatīta</option>
                <option value="Pārvērsta uzdevumā" <?php echo $filters['statuss'] === 'Pārvērsta uzdevumā' ? 'selected' : ''; ?>>Pārvērsta uzdevumā</option>
                <option value="Atcelta" <?php echo $filters['statuss'] === 'Atcelta' ? 'selected' : ''; ?>>Atcelta</option>
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
    <button onclick="sortBy('izveidots', '<?php echo $sort === 'izveidots' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'izveidots' ? 'active' : ''; ?>">
        Datuma <?php echo $sort === 'izveidots' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('statuss', '<?php echo $sort === 'statuss' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>')" 
            class="sort-btn <?php echo $sort === 'statuss' ? 'active' : ''; ?>">
        Statusa <?php echo $sort === 'statuss' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
    </button>
</div>

<!-- Statistika -->
<div class="stats-bar mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="stats-summary">
            <span class="stat-item">Kopā: <?php echo $stats['kopā']; ?></span>
            <span class="stat-item new">Jaunas: <?php echo $stats['jaunas']; ?></span>
            <span class="stat-item reviewed">Apskatītas: <?php echo $stats['apskatītas']; ?></span>
            <span class="stat-item converted">Pārvērstas: <?php echo $stats['pārvērstas']; ?></span>
            <span class="stat-item cancelled">Atceltas: <?php echo $stats['atceltas']; ?></span>
        </div>
        <div>
            <a href="report_problem.php" class="btn btn-danger">Ziņot jaunu problēmu</a>
        </div>
    </div>
</div>

<!-- Problēmu saraksts -->
<div class="problems-grid">
    <?php if (empty($problemas)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h4>Nav ziņotu problēmu</h4>
                <p>Jūs vēl neesat ziņojis nevienu problēmu.</p>
                <a href="report_problem.php" class="btn btn-danger">Ziņot problēmu</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($problemas as $problema): ?>
            <div class="problem-card <?php echo strtolower($problema['prioritate']); ?> <?php echo strtolower(str_replace([' ', 'ā'], ['-', 'a'], $problema['statuss'])); ?>">
                <div class="problem-header">
                    <div class="problem-title">
                        <h4><?php echo htmlspecialchars($problema['nosaukums']); ?></h4>
                        <div class="problem-badges">
                            <span class="priority-badge <?php echo getPriorityClass($problema['prioritate']); ?>">
                                <?php echo $problema['prioritate']; ?>
                            </span>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', 'ā'], ['-', 'a'], $problema['statuss'])); ?>">
                                <?php echo $problema['statuss']; ?>
                            </span>
                            <?php if ($problema['failu_skaits'] > 0): ?>
                                <span class="file-badge" title="Pievienoti attēli">📎 <?php echo $problema['failu_skaits']; ?></span>
                            <?php endif; ?>
                            <?php if ($problema['uzdevumu_skaits'] > 0): ?>
                                <span class="task-badge" title="Izveidoti uzdevumi">✓ <?php echo $problema['uzdevumu_skaits']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="problem-body">
                    <div class="problem-meta">
                        <?php if ($problema['vietas_nosaukums']): ?>
                            <div><strong>Vieta:</strong> <?php echo htmlspecialchars($problema['vietas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($problema['iekartas_nosaukums']): ?>
                            <div><strong>Iekārta:</strong> <?php echo htmlspecialchars($problema['iekartas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <div><strong>Sarežģītība:</strong> <?php echo htmlspecialchars($problema['sarezgitibas_pakape']); ?></div>
                        <?php if ($problema['aptuvenais_ilgums']): ?>
                            <div><strong>Aptuvenais ilgums:</strong> <?php echo $problema['aptuvenais_ilgums']; ?> h</div>
                        <?php endif; ?>
                        <?php if ($problema['apstradasija_vards']): ?>
                            <div><strong>Apstrādāja:</strong> <?php echo htmlspecialchars($problema['apstradasija_vards']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="problem-description">
                        <?php echo htmlspecialchars(substr($problema['apraksts'], 0, 200)) . (strlen($problema['apraksts']) > 200 ? '...' : ''); ?>
                    </div>
                </div>
                
                <div class="problem-footer">
                    <div class="problem-actions">
                        <button onclick="viewProblem(<?php echo $problema['id']; ?>)" class="btn btn-sm btn-info">Skatīt detaļas</button>
                        
                        <?php if ($problema['uzdevumu_skaits'] > 0): ?>
                            <button onclick="showRelatedTasks(<?php echo $problema['id']; ?>)" class="btn btn-sm btn-success">Saistītie uzdevumi</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="problem-time">
                        <small>Ziņots: <?php echo formatDate($problema['izveidots']); ?></small>
                        <?php if ($problema['atjaunots'] !== $problema['izveidots']): ?>
                            <br><small>Atjaunots: <?php echo formatDate($problema['atjaunots']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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

<!-- Modālie logi -->

<!-- Problēmas skatīšanas modāls -->
<div id="viewProblemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Problēmas detaļas</h3>
            <button onclick="closeModal('viewProblemModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="problemDetails">
            <!-- Saturs tiks ielādēts ar AJAX -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('viewProblemModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<!-- Saistīto uzdevumu modāls -->
<div id="relatedTasksModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Saistītie uzdevumi</h3>
            <button onclick="closeModal('relatedTasksModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="relatedTasksContent">
            <!-- Saturs tiks ielādēts ar AJAX -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('relatedTasksModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Problēmas detaļu skatīšana
function viewProblem(problemId) {
    fetch(`ajax/get_problem_details.php?id=${problemId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('problemDetails').innerHTML = html;
            openModal('viewProblemModal');
        })
        .catch(error => {
            console.error('Kļūda:', error);
            alert('Kļūda ielādējot problēmas detaļas');
        });
}

// Saistīto uzdevumu skatīšana
function showRelatedTasks(problemId) {
    fetch(`ajax/get_related_tasks.php?problem_id=${problemId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('relatedTasksContent').innerHTML = html;
            openModal('relatedTasksModal');
        })
        .catch(error => {
            console.error('Kļūda:', error);
            alert('Kļūda ielādējot saistītos uzdevumus');
        });
}

// Filtru automātiska iesniegšana
document.querySelectorAll('#filterForm select, #filterForm input').forEach(element => {
    element.addEventListener('change', function() {
        if (this.type !== 'text') {
            document.getElementById('filterForm').submit();
        }
    });
});

// Meklēšanas lauka debounce
let searchTimeout;
document.getElementById('meklēt').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 500);
});
</script>

<style>
/* Problēmu režģa izkārtojums */
.problems-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--spacing-lg);
    margin-top: var(--spacing-lg);
}

@media (max-width: 768px) {
    .problems-grid {
        grid-template-columns: 1fr;
    }
}

/* Problēmu kartes */
.problem-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: all 0.3s ease;
    border-left: 4px solid var(--gray-400);
}

.problem-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.problem-card.kritiska {
    border-left-color: var(--priority-critical);
}

.problem-card.augsta {
    border-left-color: var(--priority-high);
}

.problem-card.vidēja {
    border-left-color: var(--priority-medium);
}

.problem-card.zema {
    border-left-color: var(--priority-low);
}

.problem-card.parversta-uzdevuma {
    background: linear-gradient(135deg, var(--white) 0%, rgba(39, 174, 96, 0.05) 100%);
}

.problem-card.atcelta {
    opacity: 0.7;
    background: linear-gradient(135deg, var(--white) 0%, rgba(149, 165, 166, 0.05) 100%);
}

.problem-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--gray-300);
}

.problem-title h4 {
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.problem-badges {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.problem-body {
    padding: var(--spacing-md);
}

.problem-meta {
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-sm);
}

.problem-meta div {
    margin-bottom: var(--spacing-xs);
    color: var(--gray-600);
}

.problem-description {
    color: var(--gray-700);
    line-height: 1.5;
    margin-bottom: var(--spacing-md);
}

.problem-footer {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-300);
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.problem-actions {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.problem-time {
    color: var(--gray-500);
    font-size: var(--font-size-sm);
}

/* Statistikas josla */
.stats-bar {
    background: var(--white);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
}

.stats-summary {
    display: flex;
    gap: var(--spacing-lg);
    flex-wrap: wrap;
}

.stat-item {
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius);
    font-weight: 500;
    font-size: var(--font-size-sm);
}

.stat-item.new {
    background: rgba(52, 152, 219, 0.1);
    color: var(--info-color);
}

.stat-item.reviewed {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning-color);
}

.stat-item.converted {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success-color);
}

.stat-item.cancelled {
    background: rgba(149, 165, 166, 0.1);
    color: var(--gray-600);
}

/* Statusu iezīmes */
.status-jauna {
    background: var(--info-color);
}

.status-apskatita {
    background: var(--warning-color);
}

.status-parversta-uzdevuma {
    background: var(--success-color);
}

.status-atcelta {
    background: var(--gray-500);
}

/* Papildu iezīmes */
.file-badge {
    background: var(--info-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.task-badge {
    background: var(--success-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

@media (max-width: 480px) {
    .problem-footer {
        flex-direction: column;
        align-items: stretch;
    }
    
    .problem-actions {
        justify-content: center;
    }
    
    .problem-time {
        text-align: center;
    }
    
    .stats-summary {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
}
</style>

<?php include 'includes/footer.php'; ?>