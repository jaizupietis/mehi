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

	// Regulāro uzdevumu šabloni
    $stmt = $pdo->query("SELECT id, nosaukums FROM regularo_uzdevumu_sabloni WHERE aktīvs = 1 ORDER BY nosaukums");
    $regularie_sabloni = $stmt->fetchAll();

    // Brīvāko mehāniķu saraksts
    $stmt = $pdo->query("
        SELECT l.id, 
               CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
               COUNT(u.id) as aktīvo_uzdevumu_skaits
        FROM lietotaji l
        LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
        GROUP BY l.id
        ORDER BY aktīvo_uzdevumu_skaits ASC, l.vards, l.uzvards
    ");
    $mehaniki_ar_statistiku = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot datus: " . $e->getMessage();
}

// Apstrādāt formas iesniegšanu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pārbaudīt CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals(generateCSRFToken(), $_POST['csrf_token'])) {
        $errors[] = "Nederīgs drošības token. Lūdzu, mēģiniet vēlreiz.";
    }
    $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
    $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
    $veids = 'Ikdienas'; // Visi uzdevumi ir ikdienas, problēmu risināšana
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

    $assignment_type = sanitizeInput($_POST['assignment_type'] ?? 'single');
    $piešķirts_ids = $_POST['piešķirts_ids'] ?? [];

    if ($assignment_type === 'single') {
        if ($piešķirts_id == 0) {
            $errors[] = "Jāizvēlas mehāniķis, kuram piešķirt uzdevumu.";
        }
    } else {
        if (empty($piešķirts_ids) || !is_array($piešķirts_ids)) {
            $errors[] = "Jāizvēlas vismaz viens mehāniķis, kam piešķirt uzdevumu.";
        } else {
            $piešķirts_ids = array_map('intval', array_filter($piešķirts_ids));
            if (empty($piešķirts_ids)) {
                $errors[] = "Jāizvēlas vismaz viens mehāniķis, kam piešķirt uzdevumu.";
            }
        }
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
            $is_multi_assignment = ($assignment_type === 'multiple');
            $primary_mechanic_id = $is_multi_assignment ? $piešķirts_ids[0] : $piešķirts_id;

            $stmt = $pdo->prepare("
                INSERT INTO uzdevumi 
                (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, prioritate, 
                 piešķirts_id, izveidoja_id, jabeidz_lidz, paredzamais_ilgums, problemas_id, daudziem_mehāniķiem)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $nosaukums,
                $apraksts,
                $veids,
                $vietas_id ?: null,
                $iekartas_id ?: null,
                $kategorijas_id ?: null,
                $prioritate,
                $primary_mechanic_id,
                $currentUser['id'],
                $jabeidz_lidz ?: null,
                $paredzamais_ilgums ?: null,
                $problemas_id ?: null,
                $is_multi_assignment ? 1 : 0
            ]);

            $uzdevuma_id = $pdo->lastInsertId();

            // Ja ir vairāku mehāniķu piešķīrums, izveidot piešķīrumus
            if ($is_multi_assignment) {
                $stmt = $pdo->prepare("
                    INSERT INTO uzdevumu_piešķīrumi (uzdevuma_id, mehāniķa_id, statuss) 
                    VALUES (?, ?, 'Piešķirts')
                ");

                $successful_assignments = [];
                foreach ($piešķirts_ids as $mechanic_id) {
                    if ($stmt->execute([$uzdevuma_id, $mechanic_id])) {
                        $successful_assignments[] = $mechanic_id;
                    }
                }

                // Paziņot visiem veiksmīgi piešķirtajiem mehāniķiem
                foreach ($successful_assignments as $mechanic_id) {
                    try {
                        // Iegūt mehāniķa vārdu paziņojumam
                        $mechanicStmt = $pdo->prepare("SELECT CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE id = ?");
                        $mechanicStmt->execute([$mechanic_id]);
                        $mechanicName = $mechanicStmt->fetchColumn();

                        $notification_result = createNotification(
                        $mechanic_id,
                            'Jauns uzdevums piešķirts (grupas darbs)',
                            "Jums ir piešķirts jauns uzdevums kopā ar citiem mehāniķiem: $nosaukums",
                            'Jauns uzdevums',
                            'Uzdevums',
                            $uzdevuma_id
                        );

                        error_log("Paziņojums mehāniķim $mechanicName ($mechanic_id) izveidots: " . ($notification_result ? 'jā' : 'nē'));

                        // Telegram paziņojums
                        sendTaskTelegramNotification($mechanic_id, $nosaukums, $uzdevuma_id, 'new_task');
                    } catch (Exception $e) {
                        error_log("Kļūda izveidojot paziņojumu mehāniķim $mechanic_id: " . $e->getMessage());
                    }
                }

                error_log("Vairāku mehāniķu uzdevums izveidots. Kopā mehāniķu: " . count($successful_assignments) . ". IDs: " . implode(', ', $successful_assignments));
            } else {
                // Izveidot paziņojumu vienam mehāniķim
                createNotification(
                    $piešķirts_id,
                    'Jauns uzdevums piešķirts',
                    "Jums ir piešķirts jauns uzdevums: $nosaukums",
                    'Jauns uzdevums',
                    'Uzdevums',
                    $uzdevuma_id
                );

                // Telegram paziņojums mehāniķim
                sendTaskTelegramNotification($piešķirts_id, $nosaukums, $uzdevuma_id, 'new_task');
            }

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
        <small class="text-muted">Visi uzdevumi tiek izveidoti kā ikdienas problēmu risināšanas uzdevumi</small>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="taskForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <?php if ($problem_data): ?>
                <input type="hidden" name="problemas_id" value="<?php echo $problem_data['id']; ?>">
            <?php endif; ?>

            <!-- Uzdevuma nosaukums - pilns platums -->
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
                    placeholder="Ievadiet uzdevuma nosaukumu..."
                >
            </div>

            <!-- Uzdevuma apraksts -->
            <div class="form-group">
                <label for="apraksts" class="form-label">Uzdevuma apraksts *</label>
                <textarea 
                    id="apraksts" 
                    name="apraksts" 
                    class="form-control" 
                    rows="4" 
                    required
                    placeholder="Detalizēti aprakstiet uzdevumu..."
                ><?php echo htmlspecialchars($problem_data['apraksts'] ?? $_POST['apraksts'] ?? ''); ?></textarea>
            </div>

            <!-- Vieta un iekārta -->
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
                        <div class="searchable-select-container">
                            <input 
                                type="text" 
                                id="iekartas_search" 
                                class="form-control searchable-input" 
                                placeholder="Meklēt iekārtu vai izvēlieties no saraksta..."
                                autocomplete="off"
                            >
                            <select id="iekartas_id" name="iekartas_id" class="form-control searchable-select">
                                <option value="">Izvēlieties iekārtu</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>" 
                                        data-vieta="<?php echo $iekarta['vietas_id']; ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower(trim($iekarta['nosaukums']))); ?>"
                                        <?php echo ($problem_data && $problem_data['iekartas_id'] == $iekarta['id']) || ($_POST['iekartas_id'] ?? '') == $iekarta['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(trim($iekarta['nosaukums'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategorija un mehāniķis -->
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
                        <label class="form-label">Piešķirt mehāniķim/mehāniķiem *</label>

                        <!-- Izvēle starp vienu vai vairākiem mehāniķiem -->
                        <div class="assignment-type-selector mb-2">
                            <label class="radio-label">
                                <input type="radio" name="assignment_type" value="single" checked onchange="toggleAssignmentType()">
                                Piešķirt vienam mehāniķim
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="assignment_type" value="multiple" onchange="toggleAssignmentType()">
                                Piešķirt vairākiem mehāniķiem
                            </label>
                        </div>

                        <!-- Viena mehāniķa izvēle -->
                        <div id="single-assignment">
                            <select id="piešķirts_id" name="piešķirts_id" class="form-control">
                                <option value="">Izvēlieties mehāniķi</option>
                                <?php foreach ($mehaniki as $mehaniķis): ?>
                                    <option value="<?php echo $mehaniķis['id']; ?>" 
                                        <?php echo ($_POST['piešķirts_id'] ?? '') == $mehaniķis['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mehaniķis['pilns_vards']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Vairāku mehāniķu izvēle -->
                        <div id="multiple-assignment" style="display: none;">
                            <div class="mechanic-checkboxes">
                                <?php foreach ($mehaniki as $mehaniķis): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="piešķirts_ids[]" value="<?php echo $mehaniķis['id']; ?>"
                                            <?php echo (isset($_POST['piešķirts_ids']) && in_array($mehaniķis['id'], $_POST['piešķirts_ids'])) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($mehaniķis['pilns_vards']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prioritāte, termiņš un ilgums -->
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
                            placeholder="1.5"
                        >
                    </div>
                </div>
            </div>

            <!-- Failu augšupielāde -->
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

            <!-- Darbības pogas -->
            <div class="form-group">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Izveidot uzdevumu
                        </button>
                        <a href="tasks.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Atcelt
                        </a>
                    </div>
                    <?php if ($problem_data): ?>
                        <a href="problems.php" class="btn btn-info">
                            <i class="fas fa-arrow-left"></i> Atgriezties pie problēmām
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Inicializācija kad lapa ielādējusies
document.addEventListener('DOMContentLoaded', function() {
    updateIekartas();
    initSearchableSelect();
    toggleAssignmentType();

    // Uzstādīt noklusēto iekārtas nosaukumu meklēšanas laukā
    const iekartasSelect = document.getElementById('iekartas_id');
    const searchInput = document.getElementById('iekartas_search');
    if (iekartasSelect.value && searchInput) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        if (selectedOption.value) {
            searchInput.value = selectedOption.textContent.trim();
        }
    }

    // Form validation
    const taskForm = document.getElementById('taskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            const nosaukums = document.getElementById('nosaukums').value.trim();
            const apraksts = document.getElementById('apraksts').value.trim();
            const assignmentTypeElement = document.querySelector('input[name="assignment_type"]:checked');

            if (!assignmentTypeElement) {
                e.preventDefault();
                alert('Izvēlieties piešķīruma veidu.');
                return;
            }

            const assignmentType = assignmentTypeElement.value;
            let assignmentValid = false;

            if (assignmentType === 'single') {
                const piešķirts = document.getElementById('piešķirts_id').value;
                assignmentValid = piešķirts !== '';
            } else {
                const selectedMechanics = document.querySelectorAll('input[name="piešķirts_ids[]"]:checked');
                assignmentValid = selectedMechanics.length > 0;
            }

            if (!nosaukums || !apraksts || !assignmentValid) {
                e.preventDefault();
                let message = 'Lūdzu aizpildiet visus obligātos laukus.';
                if (!assignmentValid) {
                    if (assignmentType === 'single') {
                        message = 'Lūdzu izvēlieties mehāniķi, kuram piešķirt uzdevumu.';
                    } else {
                        message = 'Lūdzu izvēlieties vismaz vienu mehāniķi, kam piešķirt uzdevumu.';
                    }
                }
                alert(message);
                return false;
            }

            // Pārbaudīt failu izmēru
            const fileInput = document.getElementById('faili');
            if (fileInput && fileInput.files.length > 0) {
                for (let i = 0; i < fileInput.files.length; i++) {
                    if (fileInput.files[i].size > <?php echo MAX_FILE_SIZE; ?>) {
                        e.preventDefault();
                        alert('Fails "' + fileInput.files[i].name + '" ir pārāk liels. Maksimālais izmērs: <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB');
                        return false;
                    }
                }
            }

            return true;
        });
    }
});

// Iekartu filtrēšana pēc vietas
function updateIekartas() {
    const vietasSelect = document.getElementById('vietas_id');
    const iekartasSelect = document.getElementById('iekartas_id');
    const iekartasSearch = document.getElementById('iekartas_search');
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
            iekartasSearch.value = '';
        }
    }

    // Atjaunot meklēšanas filtrāciju
    filterIekartas();
}

// Iekārtu meklēšanas funkcionalitāte
function filterIekartas() {
    const searchInput = document.getElementById('iekartas_search');
    const iekartasSelect = document.getElementById('iekartas_id');
    const vietasSelect = document.getElementById('vietas_id');
    const searchText = searchInput.value.toLowerCase();
    const selectedVieta = vietasSelect.value;

    let hasVisibleOptions = false;

    Array.from(iekartasSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }

        const iekartaName = option.getAttribute('data-name') || '';
        const iekartaVieta = option.getAttribute('data-vieta');

        // Pārbaudīt vai atbilst vietas filtram
        const vietaMatch = !selectedVieta || iekartaVieta === selectedVieta;

        // Pārbaudīt vai atbilst meklēšanas tekstam
        const searchMatch = !searchText || iekartaName.includes(searchText);

        if (vietaMatch && searchMatch) {
            option.style.display = 'block';
            hasVisibleOptions = true;
        } else {
            option.style.display = 'none';
        }
    });

    // Ja nav redzamu opciju, rādīt ziņojumu
    if (!hasVisibleOptions && searchText) {
        // Pievienot pagaidu opciju ar ziņojumu
        const noResultsOption = iekartasSelect.querySelector('.no-results');
        if (!noResultsOption) {
            const option = document.createElement('option');
            option.className = 'no-results';
            option.disabled = true;
            option.textContent = 'Nav atrasta iekārta ar šādu nosaukumu';
            iekartasSelect.appendChild(option);
        }
        iekartasSelect.querySelector('.no-results').style.display = 'block';
    } else {
        // Noņemt "nav rezultātu" opciju
        const noResultsOption = iekartasSelect.querySelector('.no-results');
        if (noResultsOption) {
            noResultsOption.style.display = 'none';
        }
    }
}

// Inicializēt iekārtu meklēšanu
function initSearchableSelect() {
    const searchInput = document.getElementById('iekartas_search');
    const iekartasSelect = document.getElementById('iekartas_id');

    if (!searchInput || !iekartasSelect) return;

    // Meklēšanas ievades notikums
    searchInput.addEventListener('input', function() {
        filterIekartas();

        // Ja ir tikai viena redzama opcija (bez tukšās), automātiski izvēlēties to
        const visibleOptions = Array.from(iekartasSelect.options).filter(option => 
            option.style.display !== 'none' && option.value !== ''
        );

        if (visibleOptions.length === 1) {
            iekartasSelect.value = visibleOptions[0].value;
        }
    });

    // Kad izvēlas no select, atjaunot meklēšanas lauku
    iekartasSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            searchInput.value = selectedOption.textContent.trim();
        } else {
            searchInput.value = '';
        }
    });

    // Kad fokusē meklēšanas lauku, parādīt visas opcijas
    searchInput.addEventListener('focus', function() {
        iekartasSelect.classList.add('show');
        iekartasSelect.size = Math.min(8, iekartasSelect.options.length);
        filterIekartas(); // Atjaunot filtrāciju
    });

    // Kad zaudē fokusu no meklēšanas lauka, paslēpt opcijas (ar aizkavi)
    searchInput.addEventListener('blur', function(e) {
        setTimeout(() => {
            // Pārbaudīt vai fokuss nav pārslēgts uz select elementu
            if (document.activeElement !== iekartasSelect) {
                iekartasSelect.classList.remove('show');
                iekartasSelect.size = 1;
            }
        }, 150);
    });

    // Kad fokusē select, neslēpt to
    iekartasSelect.addEventListener('focus', function() {
        iekartasSelect.classList.add('show');
    });

    // Kad zaudē fokusu no select, paslēpt opcijas
    iekartasSelect.addEventListener('blur', function() {
        setTimeout(() => {
            if (document.activeElement !== searchInput) {
                iekartasSelect.classList.remove('show');
                iekartasSelect.size = 1;
            }
        }, 150);
    });

    // Peles klikšķis uz select opcijas
    iekartasSelect.addEventListener('click', function(e) {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            searchInput.value = selectedOption.textContent.trim();
            iekartasSelect.classList.remove('show');
            iekartasSelect.size = 1;
        }
    });

    // Klaviatūras navigācija
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            iekartasSelect.focus();
            if (iekartasSelect.selectedIndex < iekartasSelect.options.length - 1) {
                iekartasSelect.selectedIndex++;
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const visibleOptions = Array.from(iekartasSelect.options).filter(option => 
                option.style.display !== 'none' && option.value !== ''
            );
            if (visibleOptions.length === 1) {
                iekartasSelect.value = visibleOptions[0].value;
                searchInput.value = visibleOptions[0].textContent.trim();
            }
        }
    });
}

// Pārslēgšana starp vienu un vairākiem mehāniķiem
function toggleAssignmentType() {
    const assignmentType = document.querySelector('input[name="assignment_type"]:checked').value;
    const singleAssignment = document.getElementById('single-assignment');
    const multipleAssignment = document.getElementById('multiple-assignment');
    const singleSelect = document.getElementById('piešķirts_id');

    if (assignmentType === 'single') {
        singleAssignment.style.display = 'block';
        multipleAssignment.style.display = 'none';
        singleSelect.required = true;

        // Noņemt required no checkboxiem
        document.querySelectorAll('input[name="piešķirts_ids[]"]').forEach(cb => {
            cb.required = false;
        });
    } else {
        singleAssignment.style.display = 'none';
        multipleAssignment.style.display = 'block';
        singleSelect.required = false;
        singleSelect.value = '';

        // Nav nepieciešams required checkboxiem, validācija notiek serverī
    }
}
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

.card-header small {
    display: block;
    margin-top: 5px;
    font-style: italic;
}

.btn i {
    margin-right: 5px;
}

/* Multi-mechanic assignment styles */
.assignment-type-selector {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.radio-label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    font-weight: normal;
}

.radio-label input[type="radio"] {
    margin: 0;
}

.mechanic-checkboxes {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #f9f9f9;
}

.checkbox-label {
    display: block;
    padding: 5px 0;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 8px;
}

.checkbox-label:hover {
    background: rgba(0, 123, 255, 0.1);
    padding-left: 5px;
    border-radius: 3px;
}

/* Meklējamā select stila */
.searchable-select-container {
    position: relative;
}

.searchable-input {
    position: relative;
    z-index: 2;
}

.searchable-select {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    display: none;
    cursor: pointer;
}

.searchable-select.show {
    display: block;
}

.searchable-input:focus + .searchable-select {
    display: block;
}

.searchable-select option {
    padding: 8px 12px;
}

.searchable-select option {
    padding: 8px 12px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.searchable-select option:hover {
    background-color: #f5f5f5;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }

    .col-md-4,
    .col-md-6 {
        width: 100%;
    }

    .d-flex {
        flex-direction: column;
        gap: 10px;
    }

    .d-flex > div {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .searchable-select {
        max-height: 150px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>