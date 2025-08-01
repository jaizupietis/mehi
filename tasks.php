<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pageTitle = 'Uzdevumi';
$pageHeader = 'Uzdevumu pārvaldība';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_status' && isset($_POST['task_id'], $_POST['new_status'])) {
        $task_id = intval($_POST['task_id']);
        $new_status = sanitizeInput($_POST['new_status']);
        $komentars = sanitizeInput($_POST['komentars'] ?? '');
        
        if (in_array($new_status, ['Jauns', 'Procesā', 'Pabeigts', 'Atcelts', 'Atlikts'])) {
            try {
                $pdo->beginTransaction();
                
                // Iegūt pašreizējo statusu
                $stmt = $pdo->prepare("SELECT statuss, piešķirts_id FROM uzdevumi WHERE id = ?");
                $stmt->execute([$task_id]);
                $task = $stmt->fetch();
                
                if ($task) {
                    // Atjaunot uzdevuma statusu
                    $stmt = $pdo->prepare("
                        UPDATE uzdevumi 
                        SET statuss = ?, 
                            beigu_laiks = CASE WHEN ? = 'Pabeigts' THEN NOW() ELSE beigu_laiks END
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status, $new_status, $task_id]);
                    
                    // Pievienot vēsturi
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture 
                        (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$task_id, $task['statuss'], $new_status, $komentars, $currentUser['id']]);
                    
                    // Izveidot paziņojumu mehāniķim
                    createNotification(
                        $task['piešķirts_id'],
                        'Uzdevuma statuss mainīts',
                        "Uzdevuma statuss ir mainīts uz: $new_status" . ($komentars ? " ($komentars)" : ''),
                        'Statusa maiņa',
                        'Uzdevums',
                        $task_id
                    );
                    
                    $pdo->commit();
                    setFlashMessage('success', 'Uzdevuma statuss veiksmīgi mainīts!');
                } else {
                    $errors[] = 'Uzdevums nav atrasts.';
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Kļūda mainot statusu: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_task' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai uzdevumu var dzēst (tikai jauns status)
            $stmt = $pdo->prepare("SELECT statuss, piešķirts_id FROM uzdevumi WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if ($task && $task['statuss'] === 'Jauns') {
                // Dzēst saistītos failus
                $stmt = $pdo->prepare("SELECT faila_cels FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = ?");
                $stmt->execute([$task_id]);
                $files = $stmt->fetchAll();
                
                foreach ($files as $file) {
                    if (file_exists($file['faila_cels'])) {
                        unlink($file['faila_cels']);
                    }
                }
                
                // Dzēst failu ierakstus
                $stmt = $pdo->prepare("DELETE FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = ?");
                $stmt->execute([$task_id]);
                
                // Dzēst uzdevumu
                $stmt = $pdo->prepare("DELETE FROM uzdevumi WHERE id = ?");
                $stmt->execute([$task_id]);
                
                // Paziņot mehāniķim
                createNotification(
                    $task['piešķirts_id'],
                    'Uzdevums dzēsts',
                    'Jums piešķirtais uzdevums ir dzēsts',
                    'Sistēmas',
                    null,
                    null
                );
                
                $pdo->commit();
                setFlashMessage('success', 'Uzdevums veiksmīgi dzēsts!');
            } else {
                $errors[] = 'Var dzēst tikai jaunus uzdevumus.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda dzēšot uzdevumu: ' . $e->getMessage();
        }
    }
}

// Filtrēšanas parametri
$filters = [
    'statuss' => sanitizeInput($_GET['statuss'] ?? ''),
    'prioritate' => sanitizeInput($_GET['prioritate'] ?? ''),
    'vieta' => intval($_GET['vieta'] ?? 0),
    'mehaniķis' => intval($_GET['mehaniķis'] ?? 0),
    'veids' => sanitizeInput($_GET['veids'] ?? ''),
    'meklēt' => sanitizeInput($_GET['meklēt'] ?? '')
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'izveidots');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

// Validēt kārtošanas parametrus
$allowed_sorts = ['izveidots', 'nosaukums', 'prioritate', 'statuss', 'jabeidz_lidz', 'piešķirts_id'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'izveidots';
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
    
    $stmt = $pdo->query("SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' ORDER BY vards, uzvards");
    $mehaniki = $stmt->fetchAll();
    
    // Būvēt vaicājumu
    $where_conditions = [];
    $params = [];
    
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
    
    if ($filters['mehaniķis'] > 0) {
        $where_conditions[] = "u.piešķirts_id = ?";
        $params[] = $filters['mehaniķis'];
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
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Galvenais vaicājums (bez regulāro uzdevumu ierobežojuma)
    $sql = "
        SELECT u.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards,
               CONCAT(e.vards, ' ', e.uzvards) as izveidoja_vards,
               r.periodicitate,
               (SELECT COUNT(*) FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = u.id) as failu_skaits
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
        ORDER BY u.$sort $order
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $uzdevumi = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "
        SELECT COUNT(*) 
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot uzdevumus: " . $e->getMessage();
    $uzdevumi = [];
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
                placeholder="Meklēt uzdevumos..."
                value="<?php echo htmlspecialchars($filters['meklēt']); ?>"
            >
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
            <label for="mehaniķis" class="form-label">Mehāniķis</label>
            <select id="mehaniķis" name="mehaniķis" class="form-control">
                <option value="">Visi mehāniķi</option>
                <?php foreach ($mehaniki as $mehaniķis): ?>
                    <option value="<?php echo $mehaniķis['id']; ?>" <?php echo $filters['mehaniķis'] == $mehaniķis['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($mehaniķis['pilns_vards']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="veids" class="form-label">Veids</label>
            <select id="veids" name="veids" class="form-control">
                <option value="">Visi veidi</option>
                <option value="Ikdienas" <?php echo $filters['veids'] === 'Ikdienas' ? 'selected' : ''; ?>>Ikdienas</option>
                <option value="Regulārais" <?php echo $filters['veids'] === 'Regulārais' ? 'selected' : ''; ?>>Regulārais</option>
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
    <button onclick="sortBy('izveidots', '<?php echo $sort === 'izveidots' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'izveidots' ? 'active' : ''; ?>">
        Datuma <?php echo $sort === 'izveidots' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('nosaukums', '<?php echo $sort === 'nosaukums' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>')" 
            class="sort-btn <?php echo $sort === 'nosaukums' ? 'active' : ''; ?>">
        Nosaukuma <?php echo $sort === 'nosaukums' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
    </button>
    <button onclick="sortBy('prioritate', '<?php echo $sort === 'prioritate' && $order === 'DESC' ? 'ASC' : 'DESC'; ?>')" 
            class="sort-btn <?php echo $sort === 'prioritate' ? 'active' : ''; ?>">
        Prioritātes <?php echo $sort === 'prioritate' ? ($order === 'DESC' ? '↓' : '↑') : ''; ?>
    </button>
    <button onclick="sortBy('statuss', '<?php echo $sort === 'statuss' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>')" 
            class="sort-btn <?php echo $sort === 'statuss' ? 'active' : ''; ?>">
        Statusa <?php echo $sort === 'statuss' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
    </button>
</div>

<!-- Darbību josla -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="create_task.php" class="btn btn-primary">Izveidot uzdevumu</a>
        <span class="text-muted">Atrasti: <?php echo $total_records; ?> uzdevumi</span>
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
                        <th>Mehāniķis</th>
                        <th>Prioritāte</th>
                        <th>Statuss</th>
                        <th>Termiņš</th>
                        <th>Izveidots</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($uzdevumi)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav atrasti uzdevumi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($uzdevumi as $uzdevums): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($uzdevums['nosaukums']); ?></strong>
                                        <?php if ($uzdevums['failu_skaits'] > 0): ?>
                                            <span class="badge badge-info" title="Pievienoti faili">📎 <?php echo $uzdevums['failu_skaits']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($uzdevums['vietas_nosaukums'] ?? ''); ?>
                                        <?php if ($uzdevums['iekartas_nosaukums']): ?>
                                            - <?php echo htmlspecialchars($uzdevums['iekartas_nosaukums']); ?>
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($uzdevums['apraksts'], 0, 100)) . (strlen($uzdevums['apraksts']) > 100 ? '...' : ''); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($uzdevums['mehaniķa_vards']); ?></td>
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
                                    <?php if ($uzdevums['jabeidz_lidz']): ?>
                                        <small class="<?php echo strtotime($uzdevums['jabeidz_lidz']) < time() && $uzdevums['statuss'] != 'Pabeigts' ? 'text-danger' : ''; ?>">
                                            <?php echo formatDate($uzdevums['jabeidz_lidz']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo formatDate($uzdevums['izveidots']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewTask(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>
                                        
                                        <?php if ($uzdevums['statuss'] !== 'Pabeigts' && $uzdevums['statuss'] !== 'Atcelts'): ?>
                                            <button onclick="editTaskStatus(<?php echo $uzdevums['id']; ?>, '<?php echo $uzdevums['statuss']; ?>')" class="btn btn-sm btn-warning" title="Mainīt statusu">✏</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($uzdevums['statuss'] === 'Jauns'): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo uzdevumu?', function() { deleteTask(<?php echo $uzdevums['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst uzdevumu">🗑</button>
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

<!-- Modālie logi -->

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

<!-- Statusa maiņas modāls -->
<div id="editStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Mainīt uzdevuma statusu</h3>
            <button onclick="closeModal('editStatusModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="statusChangeForm" method="POST">
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="task_id" id="statusTaskId">
                
                <div class="form-group">
                    <label for="new_status" class="form-label">Jauns statuss</label>
                    <select id="new_status" name="new_status" class="form-control" required>
                        <option value="Jauns">Jauns</option>
                        <option value="Procesā">Procesā</option>
                        <option value="Pabeigts">Pabeigts</option>
                        <option value="Atcelts">Atcelts</option>
                        <option value="Atlikts">Atlikts</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="komentars" class="form-label">Komentārs (neobligāts)</label>
                    <textarea id="komentars" name="komentars" class="form-control" rows="3" placeholder="Pievienot komentāru par statusa maiņu..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editStatusModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('statusChangeForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('meklēt');
    
    // Event listeners filtru elementiem (bez meklēšanas lauka)
    document.querySelectorAll('#filterForm select').forEach(element => {
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

// Statusa maiņa
function editTaskStatus(taskId, currentStatus) {
    document.getElementById('statusTaskId').value = taskId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('komentars').value = '';
    openModal('editStatusModal');
}

// Uzdevuma dzēšana
function deleteTask(taskId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_task';
    
    const taskInput = document.createElement('input');
    taskInput.type = 'hidden';
    taskInput.name = 'task_id';
    taskInput.value = taskId;
    
    form.appendChild(actionInput);
    form.appendChild(taskInput);
    
    document.body.appendChild(form);
    form.submit();
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
    window.location.href = 'tasks.php';
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
.btn-group {
    display: flex;
    gap: 2px;
}

.btn-group .btn {
    margin: 0;
    padding: 4px 8px;
    min-width: 32px;
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 11px;
    background: var(--info-color);
    color: white;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-info {
    background: var(--info-color);
}
</style>

<?php include 'includes/footer.php'; ?>