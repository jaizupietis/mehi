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
        $nokluseta_vietas_id = intval($_POST['nokluseta_vietas_id'] ?? 0);
        $noklusetas_iekartas_id = intval($_POST['noklusetas_iekartas_id'] ?? 0);

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
                    (lietotajvards, parole, vards, uzvards, epasts, telefons, loma, nokluseta_vietas_id, noklusetas_iekartas_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $lietotajvards,
                    $hashed_password,
                    $vards,
                    $uzvards,
                    $epasts ?: null,
                    $telefons ?: null,
                    $loma,
                    $nokluseta_vietas_id ?: null,
                    $noklusetas_iekartas_id ?: null
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
        $nokluseta_vietas_id = intval($_POST['nokluseta_vietas_id'] ?? 0);
        $noklusetas_iekartas_id = intval($_POST['noklusetas_iekartas_id'] ?? 0);
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
                        SET vards = ?, uzvards = ?, epasts = ?, telefons = ?, loma = ?, statuss = ?, parole = ?, nokluseta_vietas_id = ?, noklusetas_iekartas_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $vards, $uzvards, $epasts ?: null, $telefons ?: null, 
                        $loma, $statuss, $hashed_password, $nokluseta_vietas_id ?: null, $noklusetas_iekartas_id ?: null, $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE lietotaji 
                        SET vards = ?, uzvards = ?, epasts = ?, telefons = ?, loma = ?, statuss = ?, nokluseta_vietas_id = ?, noklusetas_iekartas_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $vards, $uzvards, $epasts ?: null, $telefons ?: null, 
                        $loma, $statuss, $nokluseta_vietas_id ?: null, $noklusetas_iekartas_id ?: null, $user_id
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

    // CSV lietotāju imports
    if ($action === 'import_users' && isset($_FILES['csv_file'])) {
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
            $error_count = 0;
            $line_number = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $line_number++;

                // Izlaist galveni
                if ($line_number === 1 && (strtolower($data[0]) === 'vards' || strtolower($data[0]) === 'name')) {
                    continue;
                }

                if (empty(trim($data[0])) || empty(trim($data[1]))) {
                    continue;
                }

                $vards = sanitizeInput(trim($data[0]));
                $uzvards = sanitizeInput(trim($data[1]));
                $lietotajvards = sanitizeInput(trim($data[2] ?? ''));
                $parole = trim($data[3] ?? '');
                $epasts = sanitizeInput(trim($data[4] ?? ''));
                $telefons = sanitizeInput(trim($data[5] ?? ''));
                $loma = sanitizeInput(trim($data[6] ?? 'Operators'));
                $vietas_nosaukums = sanitizeInput(trim($data[7] ?? ''));
                $iekartas_nosaukums = sanitizeInput(trim($data[8] ?? ''));

                // Automātiski ģenerēt lietotājvārdu, ja nav norādīts
                if (empty($lietotajvards)) {
                    $lietotajvards = strtolower($vards . '.' . $uzvards);
                }

                // Automātiski ģenerēt paroli, ja nav norādīta
                if (empty($parole)) {
                    $parole = $lietotajvards . '123';
                }

                try {
                    // Pārbaudīt vai lietotājvārds jau eksistē
                    $stmt = $pdo->prepare("SELECT id FROM lietotaji WHERE lietotajvards = ?");
                    $stmt->execute([$lietotajvards]);
                    if ($stmt->fetch()) {
                        $error_count++;
                        continue;
                    }

                    // Atrast vietas ID
                    $vietas_id = null;
                    if (!empty($vietas_nosaukums)) {
                        $stmt = $pdo->prepare("SELECT id FROM vietas WHERE nosaukums = ?");
                        $stmt->execute([$vietas_nosaukums]);
                        $vieta = $stmt->fetch();
                        if ($vieta) {
                            $vietas_id = $vieta['id'];
                        }
                    }

                    // Atrast iekārtas ID
                    $iekartas_id = null;
                    if (!empty($iekartas_nosaukums)) {
                        $stmt = $pdo->prepare("SELECT id FROM iekartas WHERE nosaukums = ?");
                        $stmt->execute([$iekartas_nosaukums]);
                        $iekarta = $stmt->fetch();
                        if ($iekarta) {
                            $iekartas_id = $iekarta['id'];
                        }
                    }

                    $hashed_password = password_hash($parole, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO lietotaji 
                        (vards, uzvards, lietotajvards, parole, epasts, telefons, loma, nokluseta_vietas_id, noklusetas_iekartas_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $vards,
                        $uzvards,
                        $lietotajvards,
                        $hashed_password,
                        $epasts ?: null,
                        $telefons ?: null,
                        $loma,
                        $vietas_id,
                        $iekartas_id
                    ]);

                    $imported_count++;

                } catch (PDOException $e) {
                    $error_count++;
                }
            }

            fclose($handle);

            $message = "Importēti $imported_count lietotāji.";
            if ($error_count > 0) {
                $message .= " $error_count lietotāji netika importēti (dublēti lietotājvārdi vai citas kļūdas).";
            }
            setFlashMessage('success', $message);

        } catch (Exception $e) {
            $errors[] = "Importa kļūda: " . $e->getMessage();
        }
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
            $error_count = 0;
            $line_number = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $line_number++;

                // Izlaist galveni
                if ($line_number === 1 && (strtolower($data[0]) === 'nosaukums' || strtolower($data[0]) === 'name')) {
                    continue;
                }

                if (empty(trim($data[0])) || empty(trim($data[1]))) {
                    continue;
                }

                $nosaukums = sanitizeInput(trim($data[0]));
                $apraksts = sanitizeInput(trim($data[1] ?? ''));
                $vieta_nosaukums = sanitizeInput(trim($data[2] ?? ''));

                try {
                    // Atrast vietas ID
                    $vietas_id = null;
                    if (!empty($vieta_nosaukums)) {
                        $stmt = $pdo->prepare("SELECT id FROM vietas WHERE nosaukums = ?");
                        $stmt->execute([$vieta_nosaukums]);
                        $vieta = $stmt->fetch();
                        if ($vieta) {
                            $vietas_id = $vieta['id'];
                        } else {
                            // Ja vieta nav atrasta, izveidot jaunu
                            $stmt = $pdo->prepare("INSERT INTO vietas (nosaukums, apraksts) VALUES (?, ?)");
                            $stmt->execute([$vieta_nosaukums, 'Automātiski izveidota importējot']);
                            $vietas_id = $pdo->lastInsertId();
                        }
                    }

                    // Pārbaudīt vai iekārta jau eksistē
                    $stmt = $pdo->prepare("SELECT id FROM iekartas WHERE nosaukums = ?");
                    $stmt->execute([$nosaukums]);
                    if ($stmt->fetch()) {
                        $error_count++;
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO iekartas 
                        (nosaukums, apraksts, vietas_id)
                        VALUES (?, ?, ?)
                    ");

                    $stmt->execute([
                        $nosaukums,
                        $apraksts,
                        $vietas_id
                    ]);

                    $imported_count++;

                } catch (PDOException $e) {
                    $error_count++;
                }
            }

            fclose($handle);

            $message = "Importētas $imported_count iekārtas.";
            if ($error_count > 0) {
                $message .= " $error_count iekārtas netika importētas (dublēti nosaukumi vai citas kļūdas).";
            }
            setFlashMessage('success', $message);

        } catch (Exception $e) {
            $errors[] = "Importa kļūda: " . $e->getMessage();
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

    // Iegūt vietas un iekārtas, lai tās varētu izmantot dropdowns
    $stmt_vietas = $pdo->query("SELECT id, nosaukums FROM vietas ORDER BY nosaukums");
    $vietas = $stmt_vietas->fetchAll();

    $stmt_iekartas = $pdo->query("SELECT id, nosaukums FROM iekartas ORDER BY nosaukums");
    $iekartas = $stmt_iekartas->fetchAll();


} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot lietotājus: " . $e->getMessage();
    $lietotaji = [];
    $vietas = [];
    $iekartas = [];
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
        <button onclick="openModal('importUserModal')" class="btn btn-info">Importēt lietotājus (CSV)</button>
        <button onclick="openModal('importEquipmentModal')" class="btn btn-info">Importēt iekārtas (CSV)</button>
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

                <div class="row">
                    <div class="col-md-6">
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
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_nokluseta_vietas_id" class="form-label">Noklusētā vieta</label>
                            <select id="new_nokluseta_vietas_id" name="nokluseta_vietas_id" class="form-control">
                                <option value="0">Nav izvēlēta</option>
                                <?php foreach ($vietas as $vieta): ?>
                                    <option value="<?php echo $vieta['id']; ?>">
                                        <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new_noklusetas_iekartas_id" class="form-label">Noklusētā iekārta</label>
                            <select id="new_noklusetas_iekartas_id" name="noklusetas_iekartas_id" class="form-control">
                                <option value="0">Nav izvēlēta</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>">
                                        <?php echo htmlspecialchars($iekarta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
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

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_nokluseta_vietas_id" class="form-label">Noklusētā vieta</label>
                            <select id="edit_nokluseta_vietas_id" name="nokluseta_vietas_id" class="form-control">
                                <option value="0">Nav izvēlēta</option>
                                <?php foreach ($vietas as $vieta): ?>
                                    <option value="<?php echo $vieta['id']; ?>">
                                        <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_noklusetas_iekartas_id" class="form-label">Noklusētā iekārta</label>
                            <select id="edit_noklusetas_iekartas_id" name="noklusetas_iekartas_id" class="form-control">
                                <option value="0">Nav izvēlēta</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>">
                                        <?php echo htmlspecialchars($iekarta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
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

<!-- CSV lietotāju importēšanas modāls -->
<div id="importUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Importēt lietotājus no CSV</h3>
            <button onclick="closeModal('importUserModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="importUserForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_users">
                <div class="form-group">
                    <label for="csv_file_users" class="form-label">CSV fails</label>
                    <input type="file" id="csv_file_users" name="csv_file" accept=".csv" class="form-control" required>
                </div>
                <small>Fails jābūt CSV formātā ar kolonnām: Vārds, Uzvārds, Lietotājvārds (nav obligāti), Parole (nav obligāti), E-pasts, Telefons, Loma (noklusēti 'Operators'), Vieta, Iekārta.</small>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('importUserModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('importUserForm').submit()" class="btn btn-primary">Importēt</button>
        </div>
    </div>
</div>

<!-- CSV iekārtu importēšanas modāls -->
<div id="importEquipmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Importēt iekārtas no CSV</h3>
            <button onclick="closeModal('importEquipmentModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="importEquipmentForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_iekartas">
                <div class="form-group">
                    <label for="csv_file_equipment" class="form-label">CSV fails</label>
                    <input type="file" id="csv_file_equipment" name="csv_file" accept=".csv" class="form-control" required>
                </div>
                <small>Fails jābūt CSV formātā ar kolonnām: Nosaukums, Apraksts, Vieta (nav obligāti).</small>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('importEquipmentModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('importEquipmentForm').submit()" class="btn btn-primary">Importēt</button>
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
    document.getElementById('edit_nokluseta_vietas_id').value = user.nokluseta_vietas_id || '0';
    document.getElementById('edit_noklusetas_iekartas_id').value = user.noklusetas_iekartas_id || '0';
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

// Modālo logu atvēršana/aizvēršana
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Apstiprinājuma dialogs
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
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

/* Modālo logu stili */
.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1000; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
}

.modal-content {
    background-color: var(--white);
    margin: 5% auto; /* 5% from the top and centered */
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    width: 80%; /* Could be more or less, depending on screen size */
    max-width: 600px; /* Max width */
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.modal-title {
    margin: 0;
    font-size: 1.5em;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    color: var(--gray-700);
    background: none;
    border: none;
    cursor: pointer;
    line-height: 1;
}

.modal-close:hover,
.modal-close:focus {
    color: var(--gray-900);
    text-decoration: none;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid var(--border-color);
    padding-top: 10px;
    margin-top: 15px;
}

.filter-bar {
    background-color: var(--light-bg);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.filter-col {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 180px;
}

.filter-col label {
    margin-bottom: var(--spacing-sm);
    font-weight: 500;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    color: var(--text-color);
    background-color: var(--white);
    background-clip: padding-box;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25);
}

.btn {
    display: inline-block;
    font-weight: 400;
    color: var(--text-color);
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: 0.5rem 1rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: var(--border-radius);
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.btn-primary {
    color: var(--white);
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-color-darker);
    border-color: var(--primary-color-darker);
}

.btn-success {
    color: var(--white);
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.btn-success:hover {
    background-color: var(--success-color-darker);
    border-color: var(--success-color-darker);
}

.btn-info {
    color: var(--white);
    background-color: var(--info-color);
    border-color: var(--info-color);
}

.btn-info:hover {
    background-color: var(--info-color-darker);
    border-color: var(--info-color-darker);
}


.btn-warning {
    color: var(--white);
    background-color: var(--warning-color);
    border-color: var(--warning-color);
}

.btn-warning:hover {
    background-color: var(--warning-color-darker);
    border-color: var(--warning-color-darker);
}

.btn-danger {
    color: var(--white);
    background-color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: var(--danger-color-darker);
    border-color: var(--danger-color-darker);
}

.btn-secondary {
    color: var(--text-color);
    background-color: var(--gray-300);
    border-color: var(--gray-300);
}

.btn-secondary:hover {
    background-color: var(--gray-400);
    border-color: var(--gray-400);
}

.alert {
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: var(--border-radius);
}

.alert-danger {
    color: var(--danger-color-text);
    background-color: var(--danger-color-light);
    border-color: var(--danger-color-border);
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: var(--text-color);
    border-collapse: collapse;
}

.table th, .table td {
    padding: 0.75rem;
    vertical-align: top;
    border-top: 1px solid var(--border-color);
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--table-header-bg);
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    word-wrap: break-word;
    background-color: var(--white);
    background-clip: border-box;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
}

.card-body {
    flex: 1 1 auto;
    padding: 1.25rem;
}

.card-body.p-0 {
    padding: 0;
}

.text-center {
    text-align: center !important;
}

.text-muted {
    color: var(--gray-600) !important;
}

.justify-content-between {
    justify-content: space-between !important;
}

.align-items-center {
    align-items: center !important;
}

.mb-3 {
    margin-bottom: 1rem !important;
}

.d-flex {
    display: flex !important;
}

.flex-column {
    flex-direction: column !important;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: inline-block;
    margin-bottom: 0.5rem;
}

/* Papildu stili specifiskai lapai */

</style>

<?php include 'includes/footer.php'; ?>