
<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Iestatījumi';
$pageHeader = 'Sistēmas iestatījumi';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Noteikt aktīvo ciļni
$active_tab = $_GET['tab'] ?? $_POST['active_tab'] ?? 'vietas';

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
                $active_tab = 'vietas';
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
                $active_tab = 'vietas';
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
            $active_tab = 'vietas';
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot vietu: " . $e->getMessage();
        }
    }
    
    // Masveida vietu dzēšana
    if ($action === 'bulk_delete_vietas' && isset($_POST['selected_vietas'])) {
        $selected_ids = array_map('intval', $_POST['selected_vietas']);
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($selected_ids as $vieta_id) {
            try {
                // Pārbaudīt izmantošanu
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM uzdevumi WHERE vietas_id = ?) as uzdevumi,
                        (SELECT COUNT(*) FROM problemas WHERE vietas_id = ?) as problemas,
                        (SELECT COUNT(*) FROM iekartas WHERE vietas_id = ?) as iekartas
                ");
                $stmt->execute([$vieta_id, $vieta_id, $vieta_id]);
                $usage = $stmt->fetch();
                
                if ($usage['uzdevumi'] == 0 && $usage['problemas'] == 0 && $usage['iekartas'] == 0) {
                    $stmt = $pdo->prepare("DELETE FROM vietas WHERE id = ?");
                    $stmt->execute([$vieta_id]);
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            } catch (PDOException $e) {
                $error_count++;
            }
        }
        
        if ($deleted_count > 0) {
            setFlashMessage('success', "Dzēstas $deleted_count vietas.");
        }
        if ($error_count > 0) {
            $errors[] = "$error_count vietas nevarēja dzēst (tiek izmantotas citās vietās).";
        }
        $active_tab = 'vietas';
    }
    
    // CSV vietu imports
    if ($action === 'import_vietas' && isset($_FILES['csv_file'])) {
        try {
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Faila augšupielādes kļūda.');
            }
            
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception('Nevarēja atvērt failu.');
            }
            
            $imported_count = 0;
            $line_number = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $line_number++;
                
                // Izlaist galveni vai tukšas rindas
                if ($line_number === 1 && (strtolower($data[0]) === 'nosaukums' || strtolower($data[0]) === 'name')) {
                    continue;
                }
                
                if (empty(trim($data[0]))) {
                    continue;
                }
                
                $nosaukums = sanitizeInput(trim($data[0]));
                $apraksts = isset($data[1]) ? sanitizeInput(trim($data[1])) : '';
                
                // Pārbaudīt vai vieta jau eksistē
                $stmt = $pdo->prepare("SELECT id FROM vietas WHERE nosaukums = ?");
                $stmt->execute([$nosaukums]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO vietas (nosaukums, apraksts) VALUES (?, ?)");
                    $stmt->execute([$nosaukums, $apraksts]);
                    $imported_count++;
                }
            }
            
            fclose($handle);
            setFlashMessage('success', "Importētas $imported_count vietas.");
            $active_tab = 'vietas';
            
        } catch (Exception $e) {
            $errors[] = "Importa kļūda: " . $e->getMessage();
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
                $vietas_id = $vietas_id > 0 ? $vietas_id : null;
                
                $stmt = $pdo->prepare("INSERT INTO iekartas (nosaukums, apraksts, vietas_id) VALUES (?, ?, ?)");
                $stmt->execute([$nosaukums, $apraksts, $vietas_id]);
                
                setFlashMessage('success', 'Iekārta veiksmīgi pievienota!');
                $active_tab = 'iekartas';
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
                $vietas_id = $vietas_id > 0 ? $vietas_id : null;
                
                $stmt = $pdo->prepare("UPDATE iekartas SET nosaukums = ?, apraksts = ?, vietas_id = ?, aktīvs = ? WHERE id = ?");
                $stmt->execute([$nosaukums, $apraksts, $vietas_id, $aktīvs, $iekarta_id]);
                
                setFlashMessage('success', 'Iekārta veiksmīgi atjaunota!');
                $active_tab = 'iekartas';
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
            $active_tab = 'iekartas';
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot iekārtu: " . $e->getMessage();
        }
    }
    
    // Masveida iekārtu dzēšana
    if ($action === 'bulk_delete_iekartas' && isset($_POST['selected_iekartas'])) {
        $selected_ids = array_map('intval', $_POST['selected_iekartas']);
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($selected_ids as $iekarta_id) {
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM uzdevumi WHERE iekartas_id = ?) as uzdevumi,
                        (SELECT COUNT(*) FROM problemas WHERE iekartas_id = ?) as problemas
                ");
                $stmt->execute([$iekarta_id, $iekarta_id]);
                $usage = $stmt->fetch();
                
                if ($usage['uzdevumi'] == 0 && $usage['problemas'] == 0) {
                    $stmt = $pdo->prepare("DELETE FROM iekartas WHERE id = ?");
                    $stmt->execute([$iekarta_id]);
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            } catch (PDOException $e) {
                $error_count++;
            }
        }
        
        if ($deleted_count > 0) {
            setFlashMessage('success', "Dzēstas $deleted_count iekārtas.");
        }
        if ($error_count > 0) {
            $errors[] = "$error_count iekārtas nevarēja dzēst (tiek izmantotas).";
        }
        $active_tab = 'iekartas';
    }
    
    // CSV iekārtu imports
    if ($action === 'import_iekartas' && isset($_FILES['csv_file'])) {
        try {
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Faila augšupielādes kļūda.');
            }
            
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception('Nevarēja atvērt failu.');
            }
            
            $imported_count = 0;
            $line_number = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $line_number++;
                
                if ($line_number === 1 && (strtolower($data[0]) === 'nosaukums' || strtolower($data[0]) === 'name')) {
                    continue;
                }
                
                if (empty(trim($data[0]))) {
                    continue;
                }
                
                $nosaukums = sanitizeInput(trim($data[0]));
                $apraksts = isset($data[1]) ? sanitizeInput(trim($data[1])) : '';
                $vietas_nosaukums = isset($data[2]) ? sanitizeInput(trim($data[2])) : '';
                
                $vietas_id = null;
                if (!empty($vietas_nosaukums)) {
                    $stmt = $pdo->prepare("SELECT id FROM vietas WHERE nosaukums = ?");
                    $stmt->execute([$vietas_nosaukums]);
                    $vieta = $stmt->fetch();
                    if ($vieta) {
                        $vietas_id = $vieta['id'];
                    }
                }
                
                // Pārbaudīt vai iekārta jau eksistē
                $stmt = $pdo->prepare("SELECT id FROM iekartas WHERE nosaukums = ?");
                $stmt->execute([$nosaukums]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO iekartas (nosaukums, apraksts, vietas_id) VALUES (?, ?, ?)");
                    $stmt->execute([$nosaukums, $apraksts, $vietas_id]);
                    $imported_count++;
                }
            }
            
            fclose($handle);
            setFlashMessage('success', "Importētas $imported_count iekārtas.");
            $active_tab = 'iekartas';
            
        } catch (Exception $e) {
            $errors[] = "Importa kļūda: " . $e->getMessage();
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
                $active_tab = 'kategorijas';
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
                $active_tab = 'kategorijas';
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
            $active_tab = 'kategorijas';
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot kategoriju: " . $e->getMessage();
        }
    }
    
    // Masveida kategoriju dzēšana
    if ($action === 'bulk_delete_kategorijas' && isset($_POST['selected_kategorijas'])) {
        $selected_ids = array_map('intval', $_POST['selected_kategorijas']);
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($selected_ids as $kategorija_id) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE kategorijas_id = ?");
                $stmt->execute([$kategorija_id]);
                $usage = $stmt->fetchColumn();
                
                if ($usage == 0) {
                    $stmt = $pdo->prepare("DELETE FROM uzdevumu_kategorijas WHERE id = ?");
                    $stmt->execute([$kategorija_id]);
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            } catch (PDOException $e) {
                $error_count++;
            }
        }
        
        if ($deleted_count > 0) {
            setFlashMessage('success', "Dzēstas $deleted_count kategorijas.");
        }
        if ($error_count > 0) {
            $errors[] = "$error_count kategorijas nevarēja dzēst (tiek izmantotas).";
        }
        $active_tab = 'kategorijas';
    }
    
    // CSV kategoriju imports
    if ($action === 'import_kategorijas' && isset($_FILES['csv_file'])) {
        try {
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Faila augšupielādes kļūda.');
            }
            
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception('Nevarēja atvērt failu.');
            }
            
            $imported_count = 0;
            $line_number = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $line_number++;
                
                if ($line_number === 1 && (strtolower($data[0]) === 'nosaukums' || strtolower($data[0]) === 'name')) {
                    continue;
                }
                
                if (empty(trim($data[0]))) {
                    continue;
                }
                
                $nosaukums = sanitizeInput(trim($data[0]));
                $apraksts = isset($data[1]) ? sanitizeInput(trim($data[1])) : '';
                
                // Pārbaudīt vai kategorija jau eksistē
                $stmt = $pdo->prepare("SELECT id FROM uzdevumu_kategorijas WHERE nosaukums = ?");
                $stmt->execute([$nosaukums]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO uzdevumu_kategorijas (nosaukums, apraksts) VALUES (?, ?)");
                    $stmt->execute([$nosaukums, $apraksts]);
                    $imported_count++;
                }
            }
            
            fclose($handle);
            setFlashMessage('success', "Importētas $imported_count kategorijas.");
            $active_tab = 'kategorijas';
            
        } catch (Exception $e) {
            $errors[] = "Importa kļūda: " . $e->getMessage();
        }
    }
    
    // Pēc POST darbības, pāradresēt ar GET
    if (!empty($_POST)) {
        $redirect_url = "settings.php?tab=" . $active_tab;
        if (!empty($errors)) {
            $redirect_url .= "&error=1";
        }
        header("Location: $redirect_url");
        exit();
    }
}

// Iegūt datus
try {
    // Vietas
    $stmt = $pdo->query("
        SELECT v.*, 
               (SELECT COUNT(*) FROM iekartas WHERE vietas_id = v.id AND aktīvs = 1) as iekartu_skaits,
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
    <button class="tab-button <?php echo $active_tab === 'vietas' ? 'active' : ''; ?>" onclick="showTab('vietas')">Vietas</button>
    <button class="tab-button <?php echo $active_tab === 'iekartas' ? 'active' : ''; ?>" onclick="showTab('iekartas')">Iekārtas</button>
    <button class="tab-button <?php echo $active_tab === 'kategorijas' ? 'active' : ''; ?>" onclick="showTab('kategorijas')">Kategorijas</button>
</div>

<!-- Vietu pārvaldība -->
<div id="vietas-tab" class="tab-content <?php echo $active_tab === 'vietas' ? 'active' : ''; ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Vietu pārvaldība</h3>
        <div class="btn-group">
            <button onclick="openModal('addVietaModal')" class="btn btn-success">Pievienot vietu</button>
            <button onclick="openModal('importVietasModal')" class="btn btn-info">Importēt CSV</button>
            <button onclick="bulkDeleteVietas()" class="btn btn-danger">Dzēst izvēlētās</button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllVietas" onchange="toggleSelectAll('vietas')"></th>
                            <th onclick="sortTable('vietas', 'nosaukums')" class="sortable-header">
                                Nosaukums <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('vietas', 'apraksts')" class="sortable-header">
                                Apraksts <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('vietas', 'aktīvs')" class="sortable-header">
                                Statuss <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('vietas', 'iekartu_skaits')" class="sortable-header">
                                Statistika <span class="sort-icon">↕</span>
                            </th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vietas as $vieta): ?>
                            <tr class="<?php echo !$vieta['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <?php if ($vieta['iekartu_skaits'] == 0 && $vieta['uzdevumu_skaits'] == 0 && $vieta['problemu_skaits'] == 0): ?>
                                        <input type="checkbox" class="vieta-checkbox" value="<?php echo $vieta['id']; ?>">
                                    <?php endif; ?>
                                </td>
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
<div id="iekartas-tab" class="tab-content <?php echo $active_tab === 'iekartas' ? 'active' : ''; ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Iekārtu pārvaldība</h3>
        <div class="btn-group">
            <button onclick="openModal('addIekartaModal')" class="btn btn-success">Pievienot iekārtu</button>
            <button onclick="openModal('importIekartasModal')" class="btn btn-info">Importēt CSV</button>
            <button onclick="bulkDeleteIekartas()" class="btn btn-danger">Dzēst izvēlētās</button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllIekartas" onchange="toggleSelectAll('iekartas')"></th>
                            <th onclick="sortTable('iekartas', 'nosaukums')" class="sortable-header">
                                Nosaukums <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('iekartas', 'vietas_nosaukums')" class="sortable-header">
                                Vieta <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('iekartas', 'apraksts')" class="sortable-header">
                                Apraksts <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('iekartas', 'aktīvs')" class="sortable-header">
                                Statuss <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('iekartas', 'uzdevumu_skaits')" class="sortable-header">
                                Statistika <span class="sort-icon">↕</span>
                            </th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($iekartas as $iekarta): ?>
                            <tr class="<?php echo !$iekarta['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <?php if ($iekarta['uzdevumu_skaits'] == 0 && $iekarta['problemu_skaits'] == 0): ?>
                                        <input type="checkbox" class="iekarta-checkbox" value="<?php echo $iekarta['id']; ?>">
                                    <?php endif; ?>
                                </td>
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
<div id="kategorijas-tab" class="tab-content <?php echo $active_tab === 'kategorijas' ? 'active' : ''; ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Uzdevumu kategoriju pārvaldība</h3>
        <div class="btn-group">
            <button onclick="openModal('addKategorijaModal')" class="btn btn-success">Pievienot kategoriju</button>
            <button onclick="openModal('importKategorijasModal')" class="btn btn-info">Importēt CSV</button>
            <button onclick="bulkDeleteKategorijas()" class="btn btn-danger">Dzēst izvēlētās</button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllKategorijas" onchange="toggleSelectAll('kategorijas')"></th>
                            <th onclick="sortTable('kategorijas', 'nosaukums')" class="sortable-header">
                                Nosaukums <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('kategorijas', 'apraksts')" class="sortable-header">
                                Apraksts <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('kategorijas', 'aktīvs')" class="sortable-header">
                                Statuss <span class="sort-icon">↕</span>
                            </th>
                            <th onclick="sortTable('kategorijas', 'uzdevumu_skaits')" class="sortable-header">
                                Uzdevumu skaits <span class="sort-icon">↕</span>
                            </th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategorijas as $kategorija): ?>
                            <tr class="<?php echo !$kategorija['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <?php if ($kategorija['uzdevumu_skaits'] == 0): ?>
                                        <input type="checkbox" class="kategorija-checkbox" value="<?php echo $kategorija['id']; ?>">
                                    <?php endif; ?>
                                </td>
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

<!-- CSV importa modālie logi -->

<!-- Vietu imports -->
<div id="importVietasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Importēt vietas no CSV</h3>
            <button onclick="closeModal('importVietasModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>CSV fails jāsatur kolonnas: <code>nosaukums,apraksts</code></p>
            <p>Piemērs:</p>
            <pre>nosaukums,apraksts
Biroja ēka,"Galvenā biroja ēka"
Noliktava,"Preču noliktava"</pre>
            
            <form id="importVietasForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_vietas">
                <input type="hidden" name="active_tab" value="vietas">
                
                <div class="form-group">
                    <label for="vietas_csv_file" class="form-label">CSV fails</label>
                    <input type="file" id="vietas_csv_file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('importVietasModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('importVietasForm').submit()" class="btn btn-primary">Importēt</button>
        </div>
    </div>
</div>

<!-- Iekārtu imports -->
<div id="importIekartasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Importēt iekārtas no CSV</h3>
            <button onclick="closeModal('importIekartasModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>CSV fails jāsatur kolonnas: <code>nosaukums,apraksts,vieta</code></p>
            <p>Piemērs:</p>
            <pre>nosaukums,apraksts,vieta
Printeri,"Biroja printeri","Biroja ēka"
Serveri,"Datu serveri","Noliktava"</pre>
            
            <form id="importIekartasForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_iekartas">
                <input type="hidden" name="active_tab" value="iekartas">
                
                <div class="form-group">
                    <label for="iekartas_csv_file" class="form-label">CSV fails</label>
                    <input type="file" id="iekartas_csv_file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('importIekartasModal')" class="btn btn-secondary">Atceli</button>
            <button onclick="document.getElementById('importIekartasForm').submit()" class="btn btn-primary">Importēt</button>
        </div>
    </div>
</div>

<!-- Kategoriju imports -->
<div id="importKategorijasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Importēt kategorijas no CSV</h3>
            <button onclick="closeModal('importKategorijasModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>CSV fails jāsatur kolonnas: <code>nosaukums,apraksts</code></p>
            <p>Piemērs:</p>
            <pre>nosaukums,apraksts
Apkope,"Regulārā apkope"
Remonts,"Ārkārtas remonts"</pre>
            
            <form id="importKategorijasForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_kategorijas">
                <input type="hidden" name="active_tab" value="kategorijas">
                
                <div class="form-group">
                    <label for="kategorijas_csv_file" class="form-label">CSV fails</label>
                    <input type="file" id="kategorijas_csv_file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('importKategorijasModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('importKategorijasForm').submit()" class="btn btn-primary">Importēt</button>
        </div>
    </div>
</div>

<!-- Esošie modālie logi (pievienošana un rediģēšana) -->

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
                <input type="hidden" name="active_tab" value="vietas">
                
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
                <input type="hidden" name="active_tab" value="vietas">
                
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
                <input type="hidden" name="active_tab" value="iekartas">
                
                <div class="form-group">
                    <label for="iekarta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="iekarta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="iekarta_vietas_id" class="form-label">Vieta</label>
                    <select id="iekarta_vietas_id" name="vietas_id" class="form-control">
                        <option value="">Nav norādīta</option>
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
                <input type="hidden" name="active_tab" value="iekartas">
                
                <div class="form-group">
                    <label for="edit_iekarta_nosaukums" class="form-label">Nosaukums *</label>
                    <input type="text" id="edit_iekarta_nosaukums" name="nosaukums" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_iekarta_vietas_id" class="form-label">Vieta</label>
                    <select id="edit_iekarta_vietas_id" name="vietas_id" class="form-control">
                        <option value="">Nav norādīta</option>
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
                <input type="hidden" name="active_tab" value="kategorijas">
                
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
                <input type="hidden" name="active_tab" value="kategorijas">
                
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
// Globālie kārtošanas mainīgie
let currentSortField = {};
let currentSortDirection = {};

// Tabelu kārtošanas funkcija
function sortTable(tableType, field) {
    const table = document.querySelector(`#${tableType}-tab table tbody`);
    const rows = Array.from(table.querySelectorAll('tr'));
    
    // Noteikt kārtošanas virzienu
    if (currentSortField[tableType] === field) {
        currentSortDirection[tableType] = currentSortDirection[tableType] === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortDirection[tableType] = 'asc';
    }
    currentSortField[tableType] = field;
    
    // Atjaunot vizuālās ikoniņas
    document.querySelectorAll(`#${tableType}-tab .sort-icon`).forEach(icon => {
        icon.textContent = '↕';
    });
    
    const activeHeader = document.querySelector(`#${tableType}-tab th[onclick="sortTable('${tableType}', '${field}')"] .sort-icon`);
    if (activeHeader) {
        activeHeader.textContent = currentSortDirection[tableType] === 'asc' ? '↑' : '↓';
    }
    
    // Kārtot rindas
    rows.sort((a, b) => {
        let aVal, bVal;
        
        // Iegūt vērtības atkarībā no lauka
        switch (field) {
            case 'nosaukums':
                aVal = a.cells[1].textContent.trim();
                bVal = b.cells[1].textContent.trim();
                break;
            case 'apraksts':
                aVal = a.cells[2].textContent.trim();
                bVal = b.cells[2].textContent.trim();
                break;
            case 'aktīvs':
                aVal = a.cells[3].textContent.trim();
                bVal = b.cells[3].textContent.trim();
                break;
            case 'vietas_nosaukums':
                aVal = a.cells[2].textContent.trim();
                bVal = b.cells[2].textContent.trim();
                break;
            case 'iekartu_skaits':
            case 'uzdevumu_skaits':
                // Iegūt skaitļus no statistikas
                const aStats = a.cells[4].textContent;
                const bStats = b.cells[4].textContent;
                aVal = parseInt(aStats.match(/\d+/)?.[0] || '0');
                bVal = parseInt(bStats.match(/\d+/)?.[0] || '0');
                break;
            default:
                aVal = a.cells[1].textContent.trim();
                bVal = b.cells[1].textContent.trim();
        }
        
        // Salīdzināšana
        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return currentSortDirection[tableType] === 'asc' ? aVal - bVal : bVal - aVal;
        } else {
            const comparison = aVal.localeCompare(bVal, 'lv', { sensitivity: 'base' });
            return currentSortDirection[tableType] === 'asc' ? comparison : -comparison;
        }
    });
    
    // Atjaunot tabulu
    rows.forEach(row => table.appendChild(row));
}

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
    
    // Atjaunot URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

// Masveida izvēle
function toggleSelectAll(type) {
    const checkboxes = document.querySelectorAll('.' + type.slice(0, -1) + '-checkbox');
    const selectAll = document.getElementById('selectAll' + type.charAt(0).toUpperCase() + type.slice(1));
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// Masveida dzēšana
function bulkDeleteVietas() {
    const selected = Array.from(document.querySelectorAll('.vieta-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Lūdzu, izvēlieties vismaz vienu vietu.');
        return;
    }
    
    if (confirm(`Vai tiešām vēlaties dzēst ${selected.length} izvēlētās vietas?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete_vietas';
        form.appendChild(actionInput);
        
        const tabInput = document.createElement('input');
        tabInput.type = 'hidden';
        tabInput.name = 'active_tab';
        tabInput.value = 'vietas';
        form.appendChild(tabInput);
        
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_vietas[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

function bulkDeleteIekartas() {
    const selected = Array.from(document.querySelectorAll('.iekarta-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Lūdzu, izvēlieties vismaz vienu iekārtu.');
        return;
    }
    
    if (confirm(`Vai tiešām vēlaties dzēst ${selected.length} izvēlētās iekārtas?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete_iekartas';
        form.appendChild(actionInput);
        
        const tabInput = document.createElement('input');
        tabInput.type = 'hidden';
        tabInput.name = 'active_tab';
        tabInput.value = 'iekartas';
        form.appendChild(tabInput);
        
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_iekartas[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

function bulkDeleteKategorijas() {
    const selected = Array.from(document.querySelectorAll('.kategorija-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Lūdzu, izvēlieties vismaz vienu kategoriju.');
        return;
    }
    
    if (confirm(`Vai tiešām vēlaties dzēst ${selected.length} izvēlētās kategorijas?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete_kategorijas';
        form.appendChild(actionInput);
        
        const tabInput = document.createElement('input');
        tabInput.type = 'hidden';
        tabInput.name = 'active_tab';
        tabInput.value = 'kategorijas';
        form.appendChild(tabInput);
        
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_kategorijas[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
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
    
    const tabInput = document.createElement('input');
    tabInput.type = 'hidden';
    tabInput.name = 'active_tab';
    tabInput.value = 'vietas';
    
    form.appendChild(actionInput);
    form.appendChild(vietaInput);
    form.appendChild(tabInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Iekārtu funkcijas
function editIekarta(iekarta) {
    document.getElementById('edit_iekarta_id').value = iekarta.id;
    document.getElementById('edit_iekarta_nosaukums').value = iekarta.nosaukums;
    
    // Iestatīt izvēlēto vietu
    const select = document.getElementById('edit_iekarta_vietas_id');
    select.value = iekarta.vietas_id || '';
    
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
    
    const tabInput = document.createElement('input');
    tabInput.type = 'hidden';
    tabInput.name = 'active_tab';
    tabInput.value = 'iekartas';
    
    form.appendChild(actionInput);
    form.appendChild(iekartaInput);
    form.appendChild(tabInput);
    
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
    
    const tabInput = document.createElement('input');
    tabInput.type = 'hidden';
    tabInput.name = 'active_tab';
    tabInput.value = 'kategorijas';
    
    form.appendChild(actionInput);
    form.appendChild(kategorijaInput);
    form.appendChild(tabInput);
    
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
    gap: 4px;
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
    
    .btn-group {
        flex-wrap: wrap;
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

/* Kārtojamās galvenes */
.sortable-header {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: background-color 0.2s ease;
}

.sortable-header:hover {
    background-color: var(--gray-100);
}

.sort-icon {
    font-size: 12px;
    color: var(--gray-500);
    margin-left: 5px;
}

.sortable-header:hover .sort-icon {
    color: var(--primary-color);
}

/* Select styling */
select {
    min-height: 38px;
    padding: var(--spacing-xs);
}

/* CSV importa stili */
pre {
    background: var(--gray-100);
    padding: var(--spacing-sm);
    border-radius: 4px;
    font-size: var(--font-size-sm);
    overflow-x: auto;
}

code {
    background: var(--gray-100);
    padding: 2px 4px;
    border-radius: 2px;
    font-size: var(--font-size-sm);
}
</style>

<?php include 'includes/footer.php'; ?>
