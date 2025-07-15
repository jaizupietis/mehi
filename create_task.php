<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pageTitle = 'Izveidot uzdevumu';
$pageHeader = 'Jauna uzdevuma izveidošana';

$errors = [];
$success = false;
$currentUser = getCurrentUser();

// Iegūt nepieciešamos datus formām
try {
    // Vietas
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    // Iekārtas
    $stmt = $pdo->query("SELECT id, nosaukums, vietas_id FROM iekartas WHERE aktīvs = 1 ORDER BY nosaukums");
    $iekartas = $stmt->fetchAll();
    
    // Mehāniķi
    $stmt = $pdo->query("SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' ORDER BY vards, uzvards");
    $mehaniki = $stmt->fetchAll();
    
    // Uzdevumu kategorijas
    $stmt = $pdo->query("SELECT id, nosaukums FROM uzdevumu_kategorijas WHERE aktīvs = 1 ORDER BY nosaukums");
    $kategorijas = $stmt->fetchAll();
    
    // Regulāro uzdevumu šabloni
    $stmt = $pdo->query("SELECT id, nosaukums FROM regularo_uzdevumu_sabloni WHERE aktīvs = 1 ORDER BY nosaukums");
    $regularie_sabloni = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot datus: " . $e->getMessage();
}

// Apstrādāt formas iesniegšanu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
    $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
    $veids = sanitizeInput($_POST['veids'] ?? '');
    $vietas_id = intval($_POST['vietas_id'] ?? 0);
    $iekartas_id = intval($_POST['iekartas_id'] ?? 0);
    $kategorijas_id = intval($_POST['kategorijas_id'] ?? 0);
    $piešķirts_id = intval($_POST['piešķirts_id'] ?? 0);
    $prioritate = sanitizeInput($_POST['prioritate'] ?? 'Vidēja');
    $jabeidz_lidz = $_POST['jabeidz_lidz'] ?? '';
    $paredzamais_ilgums = floatval($_POST['paredzamais_ilgums'] ?? 0);
    $problemas_id = intval($_POST['problemas_id'] ?? 0);
    
    // Validācija
    if (empty($nosaukums)) {
        $errors[] = "Uzdevuma nosaukums ir obligāts.";
    }
    
    if (empty($apraksts)) {
        $errors[] = "Uzdevuma apraksts ir obligāts.";
    }
    
    if (!in_array($veids, ['Ikdienas', 'Regulārais'])) {
        $errors[] = "Nederīgs uzdevuma veids.";
    }
    
    if ($piešķirts_id == 0) {
        $errors[] = "Jāizvēlas mehāniķis, kuram piešķirt uzdevumu.";
    }
    
    if (!in_array($prioritate, ['Zema', 'Vidēja', 'Augsta', 'Kritiska'])) {
        $errors[] = "Nederīga prioritāte.";
    }
    
    if (!empty($jabeidz_lidz)) {
        $jabeidz_lidz_timestamp = strtotime($jabeidz_lidz);
        if ($jabeidz_lidz_timestamp === false || $jabeidz_lidz_timestamp <= time()) {
            $errors[] = "Termiņa datums jābūt nākotnē.";
        }
    }
    
    // Ja nav kļūdu, izveidot uzdevumu
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Izveidot uzdevumu
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumi 
                (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, prioritate, 
                 piešķirts_id, izveidoja_id, jabeidz_lidz, paredzamais_ilgums, problemas_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $nosaukums,
                $apraksts,
                $veids,
                $vietas_id ?: null,
                $iekartas_id ?: null,
                $kategorijas_id ?: null,
                $prioritate,
                $piešķirts_id,
                $currentUser['id'],
                $jabeidz_lidz ?: null,
                $paredzamais_ilgums ?: null,
                $problemas_id ?: null
            ]);
            
            $uzdevuma_id = $pdo->lastInsertId();
            
            // Apstrādāt failu augšupielādi
            if (!empty($_FILES['faili']['name'][0])) {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['faili']['name']); $i++) {
                    if ($_FILES['faili']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['faili']['name'][$i],
                            'type' => $_FILES['faili']['type'][$i],
                            'tmp_name' => $_FILES['faili']['tmp_name'][$i],
                            'error' => $_FILES['faili']['error'][$i],
                            'size' => $_FILES['faili']['size'][$i]
                        ];
                        
                        try {
                            $fileInfo = uploadFile($file);
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO faili 
                                (originalais_nosaukums, saglabatais_nosaukums, faila_cels, faila_tips, faila_izmers, tips, saistitas_id, augšupielādēja_id)
                                VALUES (?, ?, ?, ?, ?, 'Uzdevums', ?, ?)
                            ");
                            
                            $stmt->execute([
                                $fileInfo['originalais_nosaukums'],
                                $fileInfo['saglabatais_nosaukums'],
                                $fileInfo['faila_cels'],
                                $fileInfo['faila_tips'],
                                $fileInfo['faila_izmers'],
                                $uzdevuma_id,
                                $currentUser['id']
                            ]);
                        } catch (Exception $e) {
                            error_log("Faila augšupielādes kļūda: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Izveidot paziņojumu mehāniķim
            $stmt = $pdo->prepare("SELECT CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE id = ?");
            $stmt->execute([$piešķirts_id]);
            $mehaniķis = $stmt->fetch();
            
            createNotification(
                $piešķirts_id,
                'Jauns uzdevums piešķirts',
                "Jums ir piešķirts jauns uzdevums: $nosaukums",
                'Jauns uzdevums',
                'Uzdevums',
                $uzdevuma_id
            );
            
            // Ja uzdevums izveidots no problēmas, atjaunot problēmas statusu
            if ($problemas_id > 0) {
                $stmt = $pdo->prepare("UPDATE problemas SET statuss = 'Pārvērsta uzdevumā', apstradasija_id = ? WHERE id = ?");
                $stmt->execute([$currentUser['id'], $problemas_id]);
            }
            
            // Pievienot uzdevumu vēsturi
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                VALUES (?, NULL, 'Jauns', 'Uzdevums izveidots', ?)
            ");
            $stmt->execute([$uzdevuma_id, $currentUser['id']]);
            
            $pdo->commit();
            
            setFlashMessage('success', 'Uzdevums veiksmīgi izveidots!');
            redirect('tasks.php');
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Kļūda izveidojot uzdevumu: " . $e->getMessage();
            error_log("Uzdevuma izveidošanas kļūda: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Sistēmas kļūda: " . $e->getMessage();
        }
    }
}

// Ja tiek izveidots uzdevums no problēmas
$problem_data = null;
if (isset($_GET['from_problem']) && is_numeric($_GET['from_problem'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            LEFT JOIN iekartas i ON p.iekartas_id = i.id
            WHERE p.id = ? AND p.statuss IN ('Jauna', 'Apskatīta')
        ");
        $stmt->execute([$_GET['from_problem']]);
        $problem_data = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Kļūda iegūstot problēmas datus: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($problem_data): ?>
    <div class="alert alert-info">
        <strong>Izveidojiet uzdevumu no problēmas:</strong> <?php echo htmlspecialchars($problem_data['nosaukums']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Uzdevuma informācija</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <?php if ($problem_data): ?>
                <input type="hidden" name="problemas_id" value="<?php echo $problem_data['id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nosaukums" class="form-label">Uzdevuma nosaukums *</label>
                        <input 
                            type="text" 
                            id="nosaukums" 
                            name="nosaukums" 
                            class="form-control" 
                            required 
                            maxlength="200"
                            value="<?php echo htmlspecialchars($problem_data['nosaukums'] ?? $_POST['nosaukums'] ?? ''); ?>"
                        >
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="veids" class="form-label">Uzdevuma veids *</label>
                        <select id="veids" name="veids" class="form-control" required>
                            <option value="">Izvēlieties veidu</option>
                            <option value="Ikdienas" <?php echo ($problem_data || ($_POST['veids'] ?? '') === 'Ikdienas') ? 'selected' : ''; ?>>Ikdienas, problēmu risināšana</option>
                            <option value="Regulārais" <?php echo (($_POST['veids'] ?? '') === 'Regulārais') ? 'selected' : ''; ?>>Regulārais</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="apraksts" class="form-label">Uzdevuma apraksts *</label>
                <textarea 
                    id="apraksts" 
                    name="apraksts" 
                    class="form-control" 
                    rows="4" 
                    required
                ><?php echo htmlspecialchars($problem_data['apraksts'] ?? $_POST['apraksts'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="vietas_id" class="form-label">Vieta</label>
                        <select id="vietas_id" name="vietas_id" class="form-control" onchange="updateIekartas()">
                            <option value="">Izvēlieties vietu</option>
                            <?php foreach ($vietas as $vieta): ?>
                                <option value="<?php echo $vieta['id']; ?>" 
                                    <?php echo ($problem_data && $problem_data['vietas_id'] == $vieta['id']) || ($_POST['vietas_id'] ?? '') == $vieta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="iekartas_id" class="form-label">Iekārta</label>
                        <select id="iekartas_id" name="iekartas_id" class="form-control">
                            <option value="">Izvēlieties iekārtu</option>
                            <?php foreach ($iekartas as $iekarta): ?>
                                <option value="<?php echo $iekarta['id']; ?>" 
                                    data-vieta="<?php echo $iekarta['vietas_id']; ?>"
                                    <?php echo ($problem_data && $problem_data['iekartas_id'] == $iekarta['id']) || ($_POST['iekartas_id'] ?? '') == $iekarta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($iekarta['nosaukums']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="kategorijas_id" class="form-label">Kategorija</label>
                        <select id="kategorijas_id" name="kategorijas_id" class="form-control">
                            <option value="">Izvēlieties kategoriju</option>
                            <?php foreach ($kategorijas as $kategorija): ?>
                                <option value="<?php echo $kategorija['id']; ?>" 
                                    <?php echo ($_POST['kategorijas_id'] ?? '') == $kategorija['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kategorija['nosaukums']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="piešķirts_id" class="form-label">Piešķirt mehāniķim *</label>
                        <select id="piešķirts_id" name="piešķirts_id" class="form-control" required>
                            <option value="">Izvēlieties mehāniķi</option>
                            <?php foreach ($mehaniki as $mehaniķis): ?>
                                <option value="<?php echo $mehaniķis['id']; ?>" 
                                    <?php echo ($_POST['piešķirts_id'] ?? '') == $mehaniķis['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mehaniķis['pilns_vards']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="prioritate" class="form-label">Prioritāte *</label>
                        <select id="prioritate" name="prioritate" class="form-control" required>
                            <option value="Zema" <?php echo ($problem_data && $problem_data['prioritate'] === 'Zema') || ($_POST['prioritate'] ?? 'Vidēja') === 'Zema' ? 'selected' : ''; ?>>Zema</option>
                            <option value="Vidēja" <?php echo ($problem_data && $problem_data['prioritate'] === 'Vidēja') || ($_POST['prioritate'] ?? 'Vidēja') === 'Vidēja' ? 'selected' : ''; ?>>Vidēja</option>
                            <option value="Augsta" <?php echo ($problem_data && $problem_data['prioritate'] === 'Augsta') || ($_POST['prioritate'] ?? '') === 'Augsta' ? 'selected' : ''; ?>>Augsta</option>
                            <option value="Kritiska" <?php echo ($problem_data && $problem_data['prioritate'] === 'Kritiska') || ($_POST['prioritate'] ?? '') === 'Kritiska' ? 'selected' : ''; ?>>Kritiska</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="jabeidz_lidz" class="form-label">Jābeidz līdz</label>
                        <input 
                            type="datetime-local" 
                            id="jabeidz_lidz" 
                            name="jabeidz_lidz" 
                            class="form-control"
                            value="<?php echo $_POST['jabeidz_lidz'] ?? ''; ?>"
                            min="<?php echo date('Y-m-d\TH:i'); ?>"
                        >
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="paredzamais_ilgums" class="form-label">Paredzamā izpilde (stundas)</label>
                        <input 
                            type="number" 
                            id="paredzamais_ilgums" 
                            name="paredzamais_ilgums" 
                            class="form-control" 
                            step="0.5" 
                            min="0" 
                            max="1000"
                            value="<?php echo $problem_data['aptuvenais_ilgums'] ?? $_POST['paredzamais_ilgums'] ?? ''; ?>"
                        >
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="faili" class="form-label">Pievienot failus (attēli, PDF)</label>
                <input 
                    type="file" 
                    id="faili" 
                    name="faili[]" 
                    class="form-control" 
                    multiple 
                    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx"
                >
                <small class="form-text text-muted">
                    Atļautie failu tipi: JPG, PNG, GIF, PDF, DOC, DOCX. Maksimālais izmērs: <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB
                </small>
            </div>
            
            <div class="form-group">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary">Izveidot uzdevumu</button>
                        <a href="tasks.php" class="btn btn-secondary">Atcelt</a>
                    </div>
                    <?php if ($problem_data): ?>
                        <a href="problems.php" class="btn btn-info">Atgriezties pie problēmām</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Iekartu filtrēšana pēc vietas
function updateIekartas() {
    const vietasSelect = document.getElementById('vietas_id');
    const iekartasSelect = document.getElementById('iekartas_id');
    const selectedVieta = vietasSelect.value;
    
    // Rādīt visas opcijas
    Array.from(iekartasSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        
        const iekartaVieta = option.getAttribute('data-vieta');
        if (!selectedVieta || iekartaVieta === selectedVieta) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
    
    // Atiestatīt izvēli, ja pašreizējā iekārta nepieder izvēlētajai vietai
    if (selectedVieta && iekartasSelect.value) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        const selectedIekartaVieta = selectedOption.getAttribute('data-vieta');
        if (selectedIekartaVieta !== selectedVieta) {
            iekartasSelect.value = '';
        }
    }
}

// Inicializēt iekartu filtrēšanu
document.addEventListener('DOMContentLoaded', function() {
    updateIekartas();
});

// Formas validācija
document.getElementById('taskForm').addEventListener('submit', function(e) {
    const nosaukums = document.getElementById('nosaukums').value.trim();
    const apraksts = document.getElementById('apraksts').value.trim();
    const piešķirts = document.getElementById('piešķirts_id').value;
    
    if (!nosaukums || !apraksts || !piešķirts) {
        e.preventDefault();
        alert('Lūdzu aizpildiet visus obligātos laukus.');
        return;
    }
    
    // Pārbaudīt failu izmēru
    const fileInput = document.getElementById('faili');
    if (fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) {
            if (fileInput.files[i].size > <?php echo MAX_FILE_SIZE; ?>) {
                e.preventDefault();
                alert('Fails "' + fileInput.files[i].name + '" ir pārāk liels. Maksimālais izmērs: <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB');
                return;
            }
        }
    }
});
</script>

<style>
.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.col-md-4 {
    flex: 1;
    min-width: 200px;
}

.col-md-6 {
    flex: 1;
    min-width: 250px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-4,
    .col-md-6 {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>