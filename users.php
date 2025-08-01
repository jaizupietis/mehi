<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Lietotāju pārvaldība';
$pageHeader = 'Lietotāju pārvaldība';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $lietotajvards = sanitizeInput($_POST['lietotajvards'] ?? '');
        $parole = $_POST['parole'] ?? '';
        $vards = sanitizeInput($_POST['vards'] ?? '');
        $uzvards = sanitizeInput($_POST['uzvards'] ?? '');
        $epasts = sanitizeInput($_POST['epasts'] ?? '');
        $telefons = sanitizeInput($_POST['telefons'] ?? '');
        $loma = sanitizeInput($_POST['loma'] ?? '');
        
        // Validācija
        if (empty($lietotajvards) || empty($parole) || empty($vards) || empty($uzvards) || empty($loma)) {
            $errors[] = "Visi obligātie lauki jāaizpilda.";
        }
        
        if (strlen($lietotajvards) < 3) {
            $errors[] = "Lietotājvārds jābūt vismaz 3 rakstzīmes garam.";
        }
        
        if (strlen($parole) < 6) {
            $errors[] = "Parole jābūt vismaz 6 rakstzīmes gara.";
        }
        
        if (!in_array($loma, ['Administrators', 'Menedžeris', 'Operators', 'Mehāniķis'])) {
            $errors[] = "Nederīga loma.";
        }
        
        if (!empty($epasts) && !filter_var($epasts, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Nederīgs e-pasta formāts.";
        }
        
        // Pārbaudīt vai lietotājvārds jau eksistē
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM lietotaji WHERE lietotajvards = ?");
                $stmt->execute([$lietotajvards]);
                if ($stmt->fetch()) {
                    $errors[] = "Lietotājvārds jau tiek izmantots.";
                }
            } catch (PDOException $e) {
                $errors[] = "Kļūda pārbaudot lietotājvārdu.";
            }
        }
        
        // Izveidot lietotāju
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($parole, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO lietotaji 
                    (lietotajvards, parole, vards, uzvards, epasts, telefons, loma)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $lietotajvards,
                    $hashed_password,
                    $vards,
                    $uzvards,
                    $epasts ?: null,
                    $telefons ?: null,
                    $loma
                ]);
                
                setFlashMessage('success', 'Lietotājs veiksmīgi izveidots!');
                
            } catch (PDOException $e) {
                $errors[] = "Kļūda izveidojot lietotāju: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'update_user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $vards = sanitizeInput($_POST['vards'] ?? '');
        $uzvards = sanitizeInput($_POST['uzvards'] ?? '');
        $epasts = sanitizeInput($_POST['epasts'] ?? '');
        $telefons = sanitizeInput($_POST['telefons'] ?? '');
        $loma = sanitizeInput($_POST['loma'] ?? '');
        $statuss = sanitizeInput($_POST['statuss'] ?? 'Aktīvs');
        $jauna_parole = $_POST['jauna_parole'] ?? '';
        
        // Validācija
        if (empty($vards) || empty($uzvards) || empty($loma)) {
            $errors[] = "Visi obligātie lauki jāaizpilda.";
        }
        
        if (!in_array($loma, ['Administrators', 'Menedžeris', 'Operators', 'Mehāniķis'])) {
            $errors[] = "Nederīga loma.";
        }
        
        if (!in_array($statuss, ['Aktīvs', 'Neaktīvs'])) {
            $errors[] = "Nederīgs statuss.";
        }
        
        if (!empty($epasts) && !filter_var($epasts, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Nederīgs e-pasta formāts.";
        }
        
        if (!empty($jauna_parole) && strlen($jauna_parole) < 6) {
            $errors[] = "Jaunā parole jābūt vismaz 6 rakstzīmes gara.";
        }
        
        // Neļaut mainīt savu statusu uz neaktīvu
        if ($user_id == $currentUser['id'] && $statuss === 'Neaktīvs') {
            $errors[] = "Nevar deaktivizēt savu lietotāju.";
        }
        
        // Atjaunot lietotāju
        if (empty($errors)) {
            try {
                if (!empty($jauna_parole)) {
                    $hashed_password = password_hash($jauna_parole, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE lietotaji 
                        SET vards = ?, uzvards = ?, epasts = ?, telefons = ?, loma = ?, statuss = ?, parole = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $vards, $uzvards, $epasts ?: null, $telefons ?: null, 
                        $loma, $statuss, $hashed_password, $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE lietotaji 
                        SET vards = ?, uzvards = ?, epasts = ?, telefons = ?, loma = ?, statuss = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $vards, $uzvards, $epasts ?: null, $telefons ?: null, 
                        $loma, $statuss, $user_id
                    ]);
                }
                
                setFlashMessage('success', 'Lietotājs veiksmīgi atjaunots!');
                
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot lietotāju: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Neļaut dzēst sevi
        if ($user_id == $currentUser['id']) {
            $errors[] = "Nevar dzēst savu lietotāju.";
        } else {
            try {
                // Pārbaudīt vai lietotājam ir saistīti ieraksti
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? OR izveidoja_id = ?) as uzdevumi,
                        (SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ? OR apstradasija_id = ?) as problemas
                ");
                $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                $counts = $stmt->fetch();
                
                if ($counts['uzdevumi'] > 0 || $counts['problemas'] > 0) {
                    $errors[] = "Nevar dzēst lietotāju, kam ir saistīti uzdevumi vai problēmas. Deaktivizējiet lietotāju.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM lietotaji WHERE id = ?");
                    $stmt->execute([$user_id]);
                    setFlashMessage('success', 'Lietotājs veiksmīgi dzēsts!');
                }
            } catch (PDOException $e) {
                $errors[] = "Kļūda dzēšot lietotāju: " . $e->getMessage();
            }
        }
    }
}

// Filtrēšanas parametri
$filters = [
    'loma' => sanitizeInput($_GET['loma'] ?? ''),
    'statuss' => sanitizeInput($_GET['statuss'] ?? ''),
    'meklēt' => sanitizeInput($_GET['meklēt'] ?? '')
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'vards');
$order = sanitizeInput($_GET['order'] ?? 'ASC');

if (!in_array($sort, ['vards', 'uzvards', 'lietotajvards', 'loma', 'statuss', 'izveidots'])) {
    $sort = 'vards';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'ASC';
}

try {
    // Būvēt vaicājumu
    $where_conditions = [];
    $params = [];
    
    if (!empty($filters['loma'])) {
        $where_conditions[] = "loma = ?";
        $params[] = $filters['loma'];
    }
    
    if (!empty($filters['statuss'])) {
        $where_conditions[] = "statuss = ?";
        $params[] = $filters['statuss'];
    }
    
    if (!empty($filters['meklēt'])) {
        $where_conditions[] = "(vards LIKE ? OR uzvards LIKE ? OR lietotajvards LIKE ? OR epasts LIKE ?)";
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
        $params[] = '%' . $filters['meklēt'] . '%';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Galvenais vaicājums
    $sql = "
        SELECT *,
               (SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = lietotaji.id) as uzdevumu_skaits,
               (SELECT COUNT(*) FROM problemas WHERE zinotajs_id = lietotaji.id) as problemu_skaits
        FROM lietotaji 
        $where_clause
        ORDER BY $sort $order
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lietotaji = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot lietotājus: " . $e->getMessage();
    $lietotaji = [];
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
                placeholder="Meklēt lietotājus..."
                value="<?php echo htmlspecialchars($filters['meklēt']); ?>"
            >
        </div>
        
        <div class="filter-col">
            <label for="loma" class="form-label">Loma</label>
            <select id="loma" name="loma" class="form-control">
                <option value="">Visas lomas</option>
                <option value="Administrators" <?php echo $filters['loma'] === 'Administrators' ? 'selected' : ''; ?>>Administrators</option>
                <option value="Menedžeris" <?php echo $filters['loma'] === 'Menedžeris' ? 'selected' : ''; ?>>Menedžeris</option>
                <option value="Operators" <?php echo $filters['loma'] === 'Operators' ? 'selected' : ''; ?>>Operators</option>
                <option value="Mehāniķis" <?php echo $filters['loma'] === 'Mehāniķis' ? 'selected' : ''; ?>>Mehāniķis</option>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="statuss" class="form-label">Statuss</label>
            <select id="statuss" name="statuss" class="form-control">
                <option value="">Visi statusi</option>
                <option value="Aktīvs" <?php echo $filters['statuss'] === 'Aktīvs' ? 'selected' : ''; ?>>Aktīvs</option>
                <option value="Neaktīvs" <?php echo $filters['statuss'] === 'Neaktīvs' ? 'selected' : ''; ?>>Neaktīvs</option>
            </select>
        </div>
        
        <div class="filter-col" style="display: flex; gap: 0.5rem; align-items: end;">
            <button type="submit" class="btn btn-primary">Filtrēt</button>
            <button type="button" onclick="clearFilters()" class="btn btn-secondary">Notīrīt</button>
        </div>
    </form>
</div>

<!-- Darbību josla -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <button onclick="openModal('createUserModal')" class="btn btn-success">Pievienot lietotāju</button>
        <span class="text-muted">Kopā: <?php echo count($lietotaji); ?> lietotāji</span>
    </div>
</div>

<!-- Lietotāju tabula -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <a href="?sort=vards&order=<?php echo $sort === 'vards' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query($filters); ?>">
                                Vārds <?php echo $sort === 'vards' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=lietotajvards&order=<?php echo $sort === 'lietotajvards' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query($filters); ?>">
                                Lietotājvārds <?php echo $sort === 'lietotajvards' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=loma&order=<?php echo $sort === 'loma' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query($filters); ?>">
                                Loma <?php echo $sort === 'loma' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Kontakti</th>
                        <th>
                            <a href="?sort=statuss&order=<?php echo $sort === 'statuss' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query($filters); ?>">
                                Statuss <?php echo $sort === 'statuss' ? ($order === 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Statistika</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lietotaji)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav atrasti lietotāji</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lietotaji as $user): ?>
                            <tr class="<?php echo $user['statuss'] === 'Neaktīvs' ? 'table-muted' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['vards'] . ' ' . $user['uzvards']); ?></strong>
                                        <?php if ($user['id'] == $currentUser['id']): ?>
                                            <span class="badge badge-info">Tu</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user['pēdējā_pieslēgšanās']): ?>
                                        <small class="text-muted">Pēdējoreiz: <?php echo formatDate($user['pēdējā_pieslēgšanās']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['lietotajvards']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo strtolower(str_replace('ā', 'a', $user['loma'])); ?>">
                                        <?php echo $user['loma']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['epasts']): ?>
                                        <div><small>📧 <?php echo htmlspecialchars($user['epasts']); ?></small></div>
                                    <?php endif; ?>
                                    <?php if ($user['telefons']): ?>
                                        <div><small>📞 <?php echo htmlspecialchars($user['telefons']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($user['statuss']); ?>">
                                        <?php echo $user['statuss']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['loma'] === 'Mehāniķis'): ?>
                                        <small>Uzdevumi: <?php echo $user['uzdevumu_skaits']; ?></small>
                                    <?php elseif ($user['loma'] === 'Operators'): ?>
                                        <small>Problēmas: <?php echo $user['problemu_skaits']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                class="btn btn-sm btn-warning" title="Rediģēt">✏</button>
                                        
                                        <?php if ($user['id'] != $currentUser['id']): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo lietotāju?', function() { deleteUser(<?php echo $user['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst"
                                                    <?php echo ($user['uzdevumu_skaits'] > 0 || $user['problemu_skaits'] > 0) ? 'disabled' : ''; ?>>🗑</button>
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

<!-- Modālie logi -->

<!-- Lietotāja izveidošanas modāls -->
<div id="createUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pievienot jaunu lietotāju</h3>
            <button onclick="closeModal('createUserModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="createUserForm" method="POST">
                <input type="hidden" name="action" value="create_user">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_vards" class="form-label">Vārds *</label>
                            <input type="text" id="new_vards" name="vards" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_uzvards" class="form-label">Uzvārds *</label>
                            <input type="text" id="new_uzvards" name="uzvards" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_lietotajvards" class="form-label">Lietotājvārds *</label>
                            <input type="text" id="new_lietotajvards" name="lietotajvards" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_parole" class="form-label">Parole *</label>
                            <input type="password" id="new_parole" name="parole" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_epasts" class="form-label">E-pasts</label>
                            <input type="email" id="new_epasts" name="epasts" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_telefons" class="form-label">Telefons</label>
                            <input type="text" id="new_telefons" name="telefons" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_loma" class="form-label">Loma *</label>
                    <select id="new_loma" name="loma" class="form-control" required>
                        <option value="">Izvēlieties lomu</option>
                        <option value="Administrators">Administrators</option>
                        <option value="Menedžeris">Menedžeris</option>
                        <option value="Operators">Operators</option>
                        <option value="Mehāniķis">Mehāniķis</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('createUserModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('createUserForm').submit()" class="btn btn-success">Izveidot</button>
        </div>
    </div>
</div>

<!-- Lietotāja rediģēšanas modāls -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt lietotāju</h3>
            <button onclick="closeModal('editUserModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editUserForm" method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_vards" class="form-label">Vārds *</label>
                            <input type="text" id="edit_vards" name="vards" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_uzvards" class="form-label">Uzvārds *</label>
                            <input type="text" id="edit_uzvards" name="uzvards" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_epasts" class="form-label">E-pasts</label>
                            <input type="email" id="edit_epasts" name="epasts" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_telefons" class="form-label">Telefons</label>
                            <input type="text" id="edit_telefons" name="telefons" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_loma" class="form-label">Loma *</label>
                            <select id="edit_loma" name="loma" class="form-control" required>
                                <option value="Administrators">Administrators</option>
                                <option value="Menedžeris">Menedžeris</option>
                                <option value="Operators">Operators</option>
                                <option value="Mehāniķis">Mehāniķis</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_statuss" class="form-label">Statuss *</label>
                            <select id="edit_statuss" name="statuss" class="form-control" required>
                                <option value="Aktīvs">Aktīvs</option>
                                <option value="Neaktīvs">Neaktīvs</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_jauna_parole" class="form-label">Jauna parole (atstāt tukšu, ja nemaina)</label>
                    <input type="password" id="edit_jauna_parole" name="jauna_parole" class="form-control">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editUserModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('editUserForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('meklēt');
    
    // Event listeners filtru elementiem
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

// Lietotāja rediģēšana
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_vards').value = user.vards;
    document.getElementById('edit_uzvards').value = user.uzvards;
    document.getElementById('edit_epasts').value = user.epasts || '';
    document.getElementById('edit_telefons').value = user.telefons || '';
    document.getElementById('edit_loma').value = user.loma;
    document.getElementById('edit_statuss').value = user.statuss;
    document.getElementById('edit_jauna_parole').value = '';
    
    openModal('editUserModal');
}

// Lietotāja dzēšana
function deleteUser(userId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_user';
    
    const userInput = document.createElement('input');
    userInput.type = 'hidden';
    userInput.name = 'user_id';
    userInput.value = userId;
    
    form.appendChild(actionInput);
    form.appendChild(userInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Filtru notīrīšana
function clearFilters() {
    window.location.href = 'users.php';
}
</script>

<style>
.role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--white);
}

.role-administrators {
    background: var(--danger-color);
}

.role-menedzris {
    background: var(--warning-color);
}

.role-operators {
    background: var(--info-color);
}

.role-mehanikis {
    background: var(--success-color);
}

.status-aktīvs {
    background: var(--success-color);
    color: var(--white);
}

.status-neaktīvs {
    background: var(--gray-500);
    color: var(--white);
}

.table-muted {
    opacity: 0.6;
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

.btn-group {
    display: flex;
    gap: 2px;
}

.btn-group .btn {
    margin: 0;
    padding: 4px 8px;
    min-width: 32px;
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.col-md-6 {
    flex: 1;
    min-width: 250px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-6 {
        width: 100%;
        flex: none;
    }
}
</style>

<?php include 'includes/footer.php'; ?>