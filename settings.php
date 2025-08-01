<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Iestatījumi';
$pageHeader = 'Sistēmas iestatījumi';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Vietas pārvaldība
    if ($action === 'add_vieta') {
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        
        if (empty($nosaukums)) {
            $errors[] = "Vietas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO vietas (nosaukums, apraksts) VALUES (?, ?)");
                $stmt->execute([$nosaukums, $apraksts]);
                setFlashMessage('success', 'Vieta veiksmīgi pievienota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda pievienojot vietu: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit_vieta' && isset($_POST['vieta_id'])) {
        $vieta_id = intval($_POST['vieta_id']);
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $aktīvs = isset($_POST['aktīvs']) ? 1 : 0;
        
        if (empty($nosaukums)) {
            $errors[] = "Vietas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE vietas SET nosaukums = ?, apraksts = ?, aktīvs = ? WHERE id = ?");
                $stmt->execute([$nosaukums, $apraksts, $aktīvs, $vieta_id]);
                setFlashMessage('success', 'Vieta veiksmīgi atjaunota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot vietu: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_vieta' && isset($_POST['vieta_id'])) {
        $vieta_id = intval($_POST['vieta_id']);
        
        try {
            // Pārbaudīt vai vieta tiek izmantota
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM uzdevumi WHERE vietas_id = ?) as uzdevumi,
                    (SELECT COUNT(*) FROM problemas WHERE vietas_id = ?) as problemas,
                    (SELECT COUNT(*) FROM iekartas WHERE vietas_id = ?) as iekartas
            ");
            $stmt->execute([$vieta_id, $vieta_id, $vieta_id]);
            $usage = $stmt->fetch();
            
            if ($usage['uzdevumi'] > 0 || $usage['problemas'] > 0 || $usage['iekartas'] > 0) {
                $errors[] = "Nevar dzēst vietu, kas tiek izmantota uzdevumos, problēmās vai iekārtās.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM vietas WHERE id = ?");
                $stmt->execute([$vieta_id]);
                setFlashMessage('success', 'Vieta veiksmīgi dzēsta!');
            }
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot vietu: " . $e->getMessage();
        }
    }
    
    // Iekārtu pārvaldība
    if ($action === 'add_iekarta') {
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $vietas_id = intval($_POST['vietas_id'] ?? 0);
        
        if (empty($nosaukums)) {
            $errors[] = "Iekārtas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO iekartas (nosaukums, apraksts, vietas_id) VALUES (?, ?, ?)");
                $stmt->execute([$nosaukums, $apraksts, $vietas_id ?: null]);
                setFlashMessage('success', 'Iekārta veiksmīgi pievienota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda pievienojot iekārtu: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit_iekarta' && isset($_POST['iekarta_id'])) {
        $iekarta_id = intval($_POST['iekarta_id']);
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $vietas_id = intval($_POST['vietas_id'] ?? 0);
        $aktīvs = isset($_POST['aktīvs']) ? 1 : 0;
        
        if (empty($nosaukums)) {
            $errors[] = "Iekārtas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE iekartas SET nosaukums = ?, apraksts = ?, vietas_id = ?, aktīvs = ? WHERE id = ?");
                $stmt->execute([$nosaukums, $apraksts, $vietas_id ?: null, $aktīvs, $iekarta_id]);
                setFlashMessage('success', 'Iekārta veiksmīgi atjaunota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot iekārtu: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_iekarta' && isset($_POST['iekarta_id'])) {
        $iekarta_id = intval($_POST['iekarta_id']);
        
        try {
            // Pārbaudīt vai iekārta tiek izmantota
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM uzdevumi WHERE iekartas_id = ?) as uzdevumi,
                    (SELECT COUNT(*) FROM problemas WHERE iekartas_id = ?) as problemas
            ");
            $stmt->execute([$iekarta_id, $iekarta_id]);
            $usage = $stmt->fetch();
            
            if ($usage['uzdevumi'] > 0 || $usage['problemas'] > 0) {
                $errors[] = "Nevar dzēst iekārtu, kas tiek izmantota uzdevumos vai problēmās.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM iekartas WHERE id = ?");
                $stmt->execute([$iekarta_id]);
                setFlashMessage('success', 'Iekārta veiksmīgi dzēsta!');
            }
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot iekārtu: " . $e->getMessage();
        }
    }
    
    // Kategoriju pārvaldība
    if ($action === 'add_kategorija') {
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        
        if (empty($nosaukums)) {
            $errors[] = "Kategorijas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO uzdevumu_kategorijas (nosaukums, apraksts) VALUES (?, ?)");
                $stmt->execute([$nosaukums, $apraksts]);
                setFlashMessage('success', 'Kategorija veiksmīgi pievienota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda pievienojot kategoriju: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'edit_kategorija' && isset($_POST['kategorija_id'])) {
        $kategorija_id = intval($_POST['kategorija_id']);
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $aktīvs = isset($_POST['aktīvs']) ? 1 : 0;
        
        if (empty($nosaukums)) {
            $errors[] = "Kategorijas nosaukums ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE uzdevumu_kategorijas SET nosaukums = ?, apraksts = ?, aktīvs = ? WHERE id = ?");
                $stmt->execute([$nosaukums, $apraksts, $aktīvs, $kategorija_id]);
                setFlashMessage('success', 'Kategorija veiksmīgi atjaunota!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot kategoriju: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'delete_kategorija' && isset($_POST['kategorija_id'])) {
        $kategorija_id = intval($_POST['kategorija_id']);
        
        try {
            // Pārbaudīt vai kategorija tiek izmantota
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE kategorijas_id = ?");
            $stmt->execute([$kategorija_id]);
            $usage = $stmt->fetchColumn();
            
            if ($usage > 0) {
                $errors[] = "Nevar dzēst kategoriju, kas tiek izmantota uzdevumos.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM uzdevumu_kategorijas WHERE id = ?");
                $stmt->execute([$kategorija_id]);
                setFlashMessage('success', 'Kategorija veiksmīgi dzēsta!');
            }
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot kategoriju: " . $e->getMessage();
        }
    }
}

// Iegūt datus
try {
    // Vietas
    $stmt = $pdo->query("
        SELECT v.*, 
               (SELECT COUNT(*) FROM iekartas WHERE vietas_id = v.id) as iekartu_skaits,
               (SELECT COUNT(*) FROM uzdevumi WHERE vietas_id = v.id) as uzdevumu_skaits,
               (SELECT COUNT(*) FROM problemas WHERE vietas_id = v.id) as problemu_skaits
        FROM vietas v 
        ORDER BY v.nosaukums
    ");
    $vietas = $stmt->fetchAll();
    
    // Iekārtas
    $stmt = $pdo->query("
        SELECT i.*, v.nosaukums as vietas_nosaukums,
               (SELECT COUNT(*) FROM uzdevumi WHERE iekartas_id = i.id) as uzdevumu_skaits,
               (SELECT COUNT(*) FROM problemas WHERE iekartas_id = i.id) as problemu_skaits
        FROM iekartas i 
        LEFT JOIN vietas v ON i.vietas_id = v.id
        ORDER BY v.nosaukums, i.nosaukums
    ");
    $iekartas = $stmt->fetchAll();
    
    // Kategorijas
    $stmt = $pdo->query("
        SELECT k.*,
               (SELECT COUNT(*) FROM uzdevumi WHERE kategorijas_id = k.id) as uzdevumu_skaits
        FROM uzdevumu_kategorijas k 
        ORDER BY k.nosaukums
    ");
    $kategorijas = $stmt->fetchAll();
    
    // Aktīvās vietas izvēlnei
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $aktivas_vietas = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot datus: " . $e->getMessage();
    $vietas = $iekartas = $kategorijas = $aktivas_vietas = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Navigācijas ciļņi -->
<div class="settings-tabs mb-4">
    <button class="tab-button active" onclick="showTab('vietas')">Vietas</button>
    <button class="tab-button" onclick="showTab('iekartas')">Iekārtas</button>
    <button class="tab-button" onclick="showTab('kategorijas')">Kategorijas</button>
</div>

<!-- Vietu pārvaldība -->
<div id="vietas-tab" class="tab-content active">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Vietu pārvaldība</h3>
        <button onclick="openModal('addVietaModal')" class="btn btn-success">Pievienot vietu</button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nosaukums</th>
                            <th>Apraksts</th>
                            <th>Statuss</th>
                            <th>Statistika</th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vietas as $vieta): ?>
                            <tr class="<?php echo !$vieta['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($vieta['nosaukums']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($vieta['apraksts'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $vieta['aktīvs'] ? 'status-aktīvs' : 'status-neaktīvs'; ?>">
                                        <?php echo $vieta['aktīvs'] ? 'Aktīvs' : 'Neaktīvs'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        Iekārtas: <?php echo $vieta['iekartu_skaits']; ?><br>
                                        Uzdevumi: <?php echo $vieta['uzdevumu_skaits']; ?><br>
                                        Problēmas: <?php echo $vieta['problemu_skaits']; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editVieta(<?php echo htmlspecialchars(json_encode($vieta)); ?>)" 
                                                class="btn btn-sm btn-warning" title="Rediģēt">✏</button>
                                        
                                        <?php if ($vieta['iekartu_skaits'] == 0 && $vieta['uzdevumu_skaits'] == 0 && $vieta['problemu_skaits'] == 0): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo vietu?', function() { deleteVieta(<?php echo $vieta['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst">🗑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Iekārtu pārvaldība -->
<div id="iekartas-tab" class="tab-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Iekārtu pārvaldība</h3>
        <button onclick="openModal('addIekartaModal')" class="btn btn-success">Pievienot iekārtu</button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nosaukums</th>
                            <th>Vieta</th>
                            <th>Apraksts</th>
                            <th>Statuss</th>
                            <th>Statistika</th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($iekartas as $iekarta): ?>
                            <tr class="<?php echo !$iekarta['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($iekarta['nosaukums']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($iekarta['vietas_nosaukums'] ?? 'Nav norādīta'); ?></td>
                                <td><?php echo htmlspecialchars($iekarta['apraksts'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $iekarta['aktīvs'] ? 'status-aktīvs' : 'status-neaktīvs'; ?>">
                                        <?php echo $iekarta['aktīvs'] ? 'Aktīvs' : 'Neaktīvs'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        Uzdevumi: <?php echo $iekarta['uzdevumu_skaits']; ?><br>
                                        Problēmas: <?php echo $iekarta['problemu_skaits']; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editIekarta(<?php echo htmlspecialchars(json_encode($iekarta)); ?>)" 
                                                class="btn btn-sm btn-warning" title="Rediģēt">✏</button>
                                        
                                        <?php if ($iekarta['uzdevumu_skaits'] == 0 && $iekarta['problemu_skaits'] == 0): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo iekārtu?', function() { deleteIekarta(<?php echo $iekarta['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst">🗑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Kategoriju pārvaldība -->
<div id="kategorijas-tab" class="tab-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Uzdevumu kategoriju pārvaldība</h3>
        <button onclick="openModal('addKategorijaModal')" class="btn btn-success">Pievienot kategoriju</button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nosaukums</th>
                            <th>Apraksts</th>
                            <th>Statuss</th>
                            <th>Uzdevumu skaits</th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategorijas as $kategorija): ?>
                            <tr class="<?php echo !$kategorija['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($kategorija['nosaukums']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($kategorija['apraksts'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $kategorija['aktīvs'] ? 'status-aktīvs' : 'status-neaktīvs'; ?>">
                                        <?php echo $kategorija['aktīvs'] ? 'Aktīvs' : 'Neaktīvs'; ?>
                                    </span>
                                </td>
                                <td><?php echo $kategorija['uzdevumu_skaits']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editKategorija(<?php echo htmlspecialchars(json_encode($kategorija)); ?>)" 
                                                class="btn btn-sm btn-warning" title="Rediģēt">✏</button>
                                        
                                        <?php if ($kategorija['uzdevumu_skaits'] == 0): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo kategoriju?', function() { deleteKategorija(<?php echo $kategorija['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst">🗑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modālie logi -->

<!-- Vietas pievienošanas modāls -->
<div id="addVietaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pievienot jaunu vietu</h3>
            <button onclick="closeModal('addVietaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addVietaForm" method="POST">
                <input type="hidden" name="action" value="add_vieta">
                
                <div class="form-group">
                    <label for="vieta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="vieta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="vieta_apraksts" class="form-label">Apraksts</label>
                    <textarea id="vieta_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('addVietaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('addVietaForm').submit()" class="btn btn-success">Pievienot</button>
        </div>
    </div>
</div>

<!-- Vietas rediģēšanas modāls -->
<div id="editVietaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt vietu</h3>
            <button onclick="closeModal('editVietaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editVietaForm" method="POST">
                <input type="hidden" name="action" value="edit_vieta">
                <input type="hidden" name="vieta_id" id="edit_vieta_id">
                
                <div class="form-group">
                    <label for="edit_vieta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="edit_vieta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_vieta_apraksts" class="form-label">Apraksts</label>
                    <textarea id="edit_vieta_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="edit_vieta_aktīvs" name="aktīvs"> Aktīvs
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editVietaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('editVietaForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<!-- Iekārtas pievienošanas modāls -->
<div id="addIekartaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pievienot jaunu iekārtu</h3>
            <button onclick="closeModal('addIekartaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addIekartaForm" method="POST">
                <input type="hidden" name="action" value="add_iekarta">
                
                <div class="form-group">
                    <label for="iekarta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="iekarta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="iekarta_vietas_id" class="form-label">Vieta</label>
                    <select id="iekarta_vietas_id" name="vietas_id" class="form-control">
                        <option value="">Izvēlieties vietu</option>
                        <?php foreach ($aktivas_vietas as $vieta): ?>
                            <option value="<?php echo $vieta['id']; ?>"><?php echo htmlspecialchars($vieta['nosaukums']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="iekarta_apraksts" class="form-label">Apraksts</label>
                    <textarea id="iekarta_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('addIekartaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('addIekartaForm').submit()" class="btn btn-success">Pievienot</button>
        </div>
    </div>
</div>

<!-- Iekārtas rediģēšanas modāls -->
<div id="editIekartaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt iekārtu</h3>
            <button onclick="closeModal('editIekartaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editIekartaForm" method="POST">
                <input type="hidden" name="action" value="edit_iekarta">
                <input type="hidden" name="iekarta_id" id="edit_iekarta_id">
                
                <div class="form-group">
                    <label for="edit_iekarta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="edit_iekarta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_iekarta_vietas_id" class="form-label">Vieta</label>
                    <select id="edit_iekarta_vietas_id" name="vietas_id" class="form-control">
                        <option value="">Izvēlieties vietu</option>
                        <?php foreach ($aktivas_vietas as $vieta): ?>
                            <option value="<?php echo $vieta['id']; ?>"><?php echo htmlspecialchars($vieta['nosaukums']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_iekarta_apraksts" class="form-label">Apraksts</label>
                    <textarea id="edit_iekarta_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="edit_iekarta_aktīvs" name="aktīvs"> Aktīvs
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editIekartaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('editIekartaForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<!-- Kategorijas pievienošanas modāls -->
<div id="addKategorijaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pievienot jaunu kategoriju</h3>
            <button onclick="closeModal('addKategorijaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addKategorijaForm" method="POST">
                <input type="hidden" name="action" value="add_kategorija">
                
                <div class="form-group">
                    <label for="kategorija_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="kategorija_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="kategorija_apraksts" class="form-label">Apraksts</label>
                    <textarea id="kategorija_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('addKategorijaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('addKategorijaForm').submit()" class="btn btn-success">Pievienot</button>
        </div>
    </div>
</div>

<!-- Kategorijas rediģēšanas modāls -->
<div id="editKategorijaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt kategoriju</h3>
            <button onclick="closeModal('editKategorijaModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editKategorijaForm" method="POST">
                <input type="hidden" name="action" value="edit_kategorija">
                <input type="hidden" name="kategorija_id" id="edit_kategorija_id">
                
                <div class="form-group">
                    <label for="edit_kategorija_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="edit_kategorija_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_kategorija_apraksts" class="form-label">Apraksts</label>
                    <textarea id="edit_kategorija_apraksts" name="apraksts" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="edit_kategorija_aktīvs" name="aktīvs"> Aktīvs
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editKategorijaModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('editKategorijaForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    // Pirmais tab pēc noklusējuma
    showTab('vietas');
});

// Ciļņu pārslēgšana
function showTab(tabName) {
    // Paslēpt visus ciļņu saturus
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Noņemt aktīvo klasi no visām pogām
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Parādīt izvēlēto ciļņu
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Pievienot aktīvo klasi pogai
    event.target.classList.add('active');
}

// Vietu funkcijas
function editVieta(vieta) {
    document.getElementById('edit_vieta_id').value = vieta.id;
    document.getElementById('edit_vieta_nosaukums').value = vieta.nosaukums;
    document.getElementById('edit_vieta_apraksts').value = vieta.apraksts || '';
    document.getElementById('edit_vieta_aktīvs').checked = vieta.aktīvs == 1;
    openModal('editVietaModal');
}

function deleteVieta(vietaId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_vieta';
    
    const vietaInput = document.createElement('input');
    vietaInput.type = 'hidden';
    vietaInput.name = 'vieta_id';
    vietaInput.value = vietaId;
    
    form.appendChild(actionInput);
    form.appendChild(vietaInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Iekārtu funkcijas
function editIekarta(iekarta) {
    document.getElementById('edit_iekarta_id').value = iekarta.id;
    document.getElementById('edit_iekarta_nosaukums').value = iekarta.nosaukums;
    document.getElementById('edit_iekarta_vietas_id').value = iekarta.vietas_id || '';
    document.getElementById('edit_iekarta_apraksts').value = iekarta.apraksts || '';
    document.getElementById('edit_iekarta_aktīvs').checked = iekarta.aktīvs == 1;
    openModal('editIekartaModal');
}

function deleteIekarta(iekartaId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_iekarta';
    
    const iekartaInput = document.createElement('input');
    iekartaInput.type = 'hidden';
    iekartaInput.name = 'iekarta_id';
    iekartaInput.value = iekartaId;
    
    form.appendChild(actionInput);
    form.appendChild(iekartaInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Kategoriju funkcijas
function editKategorija(kategorija) {
    document.getElementById('edit_kategorija_id').value = kategorija.id;
    document.getElementById('edit_kategorija_nosaukums').value = kategorija.nosaukums;
    document.getElementById('edit_kategorija_apraksts').value = kategorija.apraksts || '';
    document.getElementById('edit_kategorija_aktīvs').checked = kategorija.aktīvs == 1;
    openModal('editKategorijaModal');
}

function deleteKategorija(kategorijaId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_kategorija';
    
    const kategorijaInput = document.createElement('input');
    kategorijaInput.type = 'hidden';
    kategorijaInput.name = 'kategorija_id';
    kategorijaInput.value = kategorijaId;
    
    form.appendChild(actionInput);
    form.appendChild(kategorijaInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<style>
/* Ciļņu stili */
.settings-tabs {
    display: flex;
    border-bottom: 2px solid var(--gray-300);
    gap: var(--spacing-xs);
}

.tab-button {
    padding: var(--spacing-md) var(--spacing-lg);
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 500;
    color: var(--gray-600);
    transition: all 0.3s ease;
}

.tab-button:hover {
    background: var(--gray-100);
    color: var(--gray-800);
}

.tab-button.active {
    color: var(--secondary-color);
    border-bottom-color: var(--secondary-color);
    background: rgba(52, 152, 219, 0.05);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Statusu stili */
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

.btn-group {
    display: flex;
    gap: 2px;
}

.btn-group .btn {
    margin: 0;
    padding: 4px 8px;
    min-width: 32px;
}

/* Responsive dizains */
@media (max-width: 768px) {
    .settings-tabs {
        flex-wrap: wrap;
    }
    
    .tab-button {
        flex: 1;
        min-width: 0;
        padding: var(--spacing-sm) var(--spacing-md);
        font-size: var(--font-size-sm);
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
}

@media (max-width: 480px) {
    .table-responsive {
        font-size: var(--font-size-sm);
    }
    
    .btn-group {
        flex-direction: column;
        gap: 2px;
    }
    
    .btn-group .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>