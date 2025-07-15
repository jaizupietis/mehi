<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pageTitle = 'Problēmas';
$pageHeader = 'Problēmu pārvaldība';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status' && isset($_POST['problem_id'], $_POST['new_status'])) {
        $problem_id = intval($_POST['problem_id']);
        $new_status = sanitizeInput($_POST['new_status']);
        $komentars = sanitizeInput($_POST['komentars'] ?? '');
        
        if (in_array($new_status, ['Jauna', 'Apskatīta', 'Pārvērsta uzdevumā', 'Atcelta'])) {
            try {
                $pdo->beginTransaction();
                
                // Atjaunot problēmas statusu
                $stmt = $pdo->prepare("
                    UPDATE problemas 
                    SET statuss = ?, apstradasija_id = ?, atjaunots = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $currentUser['id'], $problem_id]);
                
                // Iegūt problēmas informāciju paziņojumam
                $stmt = $pdo->prepare("
                    SELECT p.nosaukums, p.zinotajs_id, CONCAT(l.vards, ' ', l.uzvards) as zinotaja_vards
                    FROM problemas p
                    LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$problem_id]);
                $problem = $stmt->fetch();
                
                if ($problem) {
                    // Paziņot ziņotājam par statusa maiņu
                    $notification_message = "Jūsu ziņotās problēmas \"{$problem['nosaukums']}\" statuss ir mainīts uz: $new_status";
                    if ($komentars) {
                        $notification_message .= " ($komentars)";
                    }
                    
                    createNotification(
                        $problem['zinotajs_id'],
                        'Problēmas statuss mainīts',
                        $notification_message,
                        'Statusa maiņa',
                        'Problēma',
                        $problem_id
                    );
                }
                
                $pdo->commit();
                setFlashMessage('success', 'Problēmas statuss veiksmīgi mainīts!');
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Kļūda mainot statusu: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_problem' && isset($_POST['problem_id'])) {
        $problem_id = intval($_POST['problem_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai problēmu var dzēst (tikai jaunas vai apskatītas)
            $stmt = $pdo->prepare("SELECT statuss, zinotajs_id FROM problemas WHERE id = ?");
            $stmt->execute([$problem_id]);
            $problem = $stmt->fetch();
            
            if ($problem && in_array($problem['statuss'], ['Jauna', 'Apskatīta'])) {
                // Dzēst saistītos failus
                $stmt = $pdo->prepare("SELECT faila_cels FROM faili WHERE tips = 'Problēma' AND saistitas_id = ?");
                $stmt->execute([$problem_id]);
                $files = $stmt->fetchAll();
                
                foreach ($files as $file) {
                    if (file_exists($file['faila_cels'])) {
                        unlink($file['faila_cels']);
                    }
                }
                
                // Dzēst failu ierakstus
                $stmt = $pdo->prepare("DELETE FROM faili WHERE tips = 'Problēma' AND saistitas_id = ?");
                $stmt->execute([$problem_id]);
                
                // Dzēst problēmu
                $stmt = $pdo->prepare("DELETE FROM problemas WHERE id = ?");
                $stmt->execute([$problem_id]);
                
                // Paziņot ziņotājam
                createNotification(
                    $problem['zinotajs_id'],
                    'Problēma dzēsta',
                    'Jūsu ziņotā problēma ir dzēsta',
                    'Sistēmas',
                    null,
                    null
                );
                
                $pdo->commit();
                setFlashMessage('success', 'Problēma veiksmīgi dzēsta!');
            } else {
                $errors[] = 'Var dzēst tikai jaunas vai apskatītas problēmas.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda dzēšot problēmu: ' . $e->getMessage();
        }
    }
}

// Filtrēšanas parametri
$filters = [
    'statuss' => sanitizeInput($_GET['statuss'] ?? ''),
    'prioritate' => sanitizeInput($_GET['prioritate'] ?? ''),
    'vieta' => intval($_GET['vieta'] ?? 0),
    'zinotajs' => intval($_GET['zinotajs'] ?? 0),
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
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Iegūt filtru datus
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE loma = 'Operators' AND statuss = 'Aktīvs' ORDER BY vards, uzvards");
    $operatori = $stmt->fetchAll();
    
    // Būvēt vaicājumu
    $where_conditions = [];
    $params = [];
    
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
    
    if ($filters['zinotajs'] > 0) {
        $where_conditions[] = "p.zinotajs_id = ?";
        $params[] = $filters['zinotajs'];
    }
    
    if (!empty($filters['meklēt'])) {
        $where_conditions[] = "(p.nosaukums LIKE ? OR p.apraksts LIKE ?)";
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
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
               CONCAT(l.vards, ' ', l.uzvards) as zinotaja_vards,
               CONCAT(a.vards, ' ', a.uzvards) as apstradasija_vards,
               (SELECT COUNT(*) FROM faili WHERE tips = 'Problēma' AND saistitas_id = p.id) as failu_skaits,
               (SELECT COUNT(*) FROM uzdevumi WHERE problemas_id = p.id) as uzdevumu_skaits
        FROM problemas p
        LEFT JOIN vietas v ON p.vietas_id = v.id
        LEFT JOIN iekartas i ON p.iekartas_id = i.id
        LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
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
        LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
        LEFT JOIN lietotaji a ON p.apstradasija_id = a.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot problēmas: " . $e->getMessage();
    $problemas = [];
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
        
        <div class="filter-col">
            <label for="zinotajs" class="form-label">Ziņotājs</label>
            <select id="zinotajs" name="zinotajs" class="form-control">
                <option value="">Visi ziņotāji</option>
                <?php foreach ($operatori as $operators): ?>
                    <option value="<?php echo $operators['id']; ?>" <?php echo $filters['zinotajs'] == $operators['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($operators['pilns_vards']); ?>
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
    <?php
    try {
        $stmt = $pdo->query("SELECT statuss, COUNT(*) as skaits FROM problemas GROUP BY statuss");
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['statuss']] = $row['skaits'];
        }
    } catch (PDOException $e) {
        $stats = [];
    }
    ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="stats-summary">
            <span class="stat-item">Kopā: <?php echo array_sum($stats); ?></span>
            <span class="stat-item new">Jaunas: <?php echo $stats['Jauna'] ?? 0; ?></span>
            <span class="stat-item reviewed">Apskatītas: <?php echo $stats['Apskatīta'] ?? 0; ?></span>
            <span class="stat-item converted">Pārvērstas: <?php echo $stats['Pārvērsta uzdevumā'] ?? 0; ?></span>
        </div>
        <div>
            <span class="text-muted">Atrasti: <?php echo $total_records; ?> ieraksti</span>
        </div>
    </div>
</div>

<!-- Problēmu tabula -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Problēma</th>
                        <th>Ziņotājs</th>
                        <th>Prioritāte</th>
                        <th>Statuss</th>
                        <th>Sarežģītība</th>
                        <th>Izveidots</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($problemas)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav atrasti problēmu ieraksti</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($problemas as $problema): ?>
                            <tr class="<?php echo $problema['statuss'] === 'Jauna' ? 'table-warning' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($problema['nosaukums']); ?></strong>
                                        <?php if ($problema['failu_skaits'] > 0): ?>
                                            <span class="badge badge-info" title="Pievienoti attēli">📎 <?php echo $problema['failu_skaits']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($problema['uzdevumu_skaits'] > 0): ?>
                                            <span class="badge badge-success" title="Izveidoti uzdevumi">✓ <?php echo $problema['uzdevumu_skaits']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($problema['vietas_nosaukums'] ?? ''); ?>
                                        <?php if ($problema['iekartas_nosaukums']): ?>
                                            - <?php echo htmlspecialchars($problema['iekartas_nosaukums']); ?>
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($problema['apraksts'], 0, 100)) . (strlen($problema['apraksts']) > 100 ? '...' : ''); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($problema['zinotaja_vards']); ?></td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($problema['prioritate']); ?>">
                                        <?php echo $problema['prioritate']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $problema['statuss'])); ?>">
                                        <?php echo $problema['statuss']; ?>
                                    </span>
                                    <?php if ($problema['apstradasija_vards']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($problema['apstradasija_vards']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($problema['sarezgitibas_pakape']); ?></small>
                                    <?php if ($problema['aptuvenais_ilgums']): ?>
                                        <br><small class="text-muted"><?php echo $problema['aptuvenais_ilgums']; ?>h</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo formatDate($problema['izveidots']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewProblem(<?php echo $problema['id']; ?>)" class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>
                                        
                                        <?php if (in_array($problema['statuss'], ['Jauna', 'Apskatīta'])): ?>
                                            <button onclick="editProblemStatus(<?php echo $problema['id']; ?>, '<?php echo $problema['statuss']; ?>')" class="btn btn-sm btn-warning" title="Mainīt statusu">✏</button>
                                            <a href="create_task.php?from_problem=<?php echo $problema['id']; ?>" class="btn btn-sm btn-success" title="Izveidot uzdevumu">➕</a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($problema['statuss'], ['Jauna', 'Apskatīta'])): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo problēmu?', function() { deleteProblem(<?php echo $problema['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst problēmu">🗑</button>
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

<!-- Statusa maiņas modāls -->
<div id="editStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Mainīt problēmas statusu</h3>
            <button onclick="closeModal('editStatusModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="statusChangeForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="problem_id" id="statusProblemId">
                
                <div class="form-group">
                    <label for="new_status" class="form-label">Jauns statuss</label>
                    <select id="new_status" name="new_status" class="form-control" required>
                        <option value="Jauna">Jauna</option>
                        <option value="Apskatīta">Apskatīta</option>
                        <option value="Atcelta">Atcelta</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="komentars" class="form-label">Komentārs (neobligāts)</label>
                    <textarea id="komentars" name="komentars" class="form-control" rows="3" placeholder="Pievienot komentāru par statusa maiņu..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>Piezīme:</strong> Lai pārvērstu problēmu uzdevumā, izmantojiet pogu "Izveidot uzdevumu" problēmas sarakstā.
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

// Statusa maiņa
function editProblemStatus(problemId, currentStatus) {
    document.getElementById('statusProblemId').value = problemId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('komentars').value = '';
    openModal('editStatusModal');
}

// Problēmas dzēšana
function deleteProblem(problemId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_problem';
    
    const problemInput = document.createElement('input');
    problemInput.type = 'hidden';
    problemInput.name = 'problem_id';
    problemInput.value = problemId;
    
    form.appendChild(actionInput);
    form.appendChild(problemInput);
    
    document.body.appendChild(form);
    form.submit();
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

.table-warning {
    background: rgba(243, 156, 18, 0.05);
}

.status-jauna {
    background: var(--info-color);
}

.status-apskatīta {
    background: var(--warning-color);
}

.status-pārvērsta-uzdevumā {
    background: var(--success-color);
}

.status-atcelta {
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

.badge-success {
    background: var(--success-color);
}

@media (max-width: 768px) {
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