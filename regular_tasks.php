<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pageTitle = 'Regulārie uzdevumi';
$pageHeader = 'Regulāro uzdevumu pārvaldība';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_template') {
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $vietas_id = intval($_POST['vietas_id'] ?? 0);
        $iekartas_id = intval($_POST['iekartas_id'] ?? 0);
        $kategorijas_id = intval($_POST['kategorijas_id'] ?? 0);
        $prioritate = sanitizeInput($_POST['prioritate'] ?? 'Vidēja');
        $paredzamais_ilgums = floatval($_POST['paredzamais_ilgums'] ?? 0);
        $periodicitate = sanitizeInput($_POST['periodicitate'] ?? '');
        $periodicitas_dienas = $_POST['periodicitas_dienas'] ?? [];
        $laiks = $_POST['laiks'] ?? '09:00';

        // Validācija
        if (empty($nosaukums) || empty($apraksts) || empty($periodicitate)) {
            $errors[] = "Nosaukums, apraksts un periodicitāte ir obligāti.";
        }

        if (!in_array($periodicitate, ['Katru dienu', 'Katru nedēļu', 'Reizi mēnesī', 'Reizi ceturksnī', 'Reizi gadā'])) {
            $errors[] = "Nederīga periodicitāte.";
        }

        // Validēt periodicitātes dienas
        $json_dienas = null;
        if ($periodicitate === 'Katru nedēļu' && !empty($periodicitas_dienas)) {
            // Nedēļas dienas (1-7)
            $valid_days = array_filter($periodicitas_dienas, function($day) {
                return is_numeric($day) && $day >= 1 && $day <= 7;
            });
            if (!empty($valid_days)) {
                $json_dienas = json_encode(array_values($valid_days));
            }
        } elseif ($periodicitate === 'Reizi mēnesī' && !empty($periodicitas_dienas)) {
            // Mēneša dienas (1-31)
            $valid_days = array_filter($periodicitas_dienas, function($day) {
                return is_numeric($day) && $day >= 1 && $day <= 31;
            });
            if (!empty($valid_days)) {
                $json_dienas = json_encode(array_values($valid_days));
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO regularo_uzdevumu_sabloni 
                    (nosaukums, apraksts, vietas_id, iekartas_id, kategorijas_id, prioritate, 
                     paredzamais_ilgums, periodicitate, periodicitas_dienas, laiks, izveidoja_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $nosaukums,
                    $apraksts,
                    $vietas_id ?: null,
                    $iekartas_id ?: null,
                    $kategorijas_id ?: null,
                    $prioritate,
                    $paredzamais_ilgums ?: null,
                    $periodicitate,
                    $json_dienas,
                    $laiks,
                    $currentUser['id']
                ]);

                setFlashMessage('success', 'Regulārais uzdevums veiksmīgi izveidots!');
                redirect('regular_tasks.php');

            } catch (PDOException $e) {
                $errors[] = "Kļūda izveidojot regulāro uzdevumu: " . $e->getMessage();
            }
        }
    }

    if ($action === 'update_template' && isset($_POST['template_id'])) {
        $template_id = intval($_POST['template_id']);
        $aktīvs = isset($_POST['aktīvs']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE regularo_uzdevumu_sabloni SET aktīvs = ? WHERE id = ?");
            $stmt->execute([$aktīvs, $template_id]);
            setFlashMessage('success', 'Regulārā uzdevuma statuss atjaunots!');
        } catch (PDOException $e) {
            $errors[] = "Kļūda atjaunojot statusu: " . $e->getMessage();
        }
    }

if ($action === 'delete_template' && isset($_POST['template_id'])) {
        $template_id = intval($_POST['template_id']);
        $force_delete = isset($_POST['force_delete']) ? true : false;

        try {
            $pdo->beginTransaction();

            // Pārbaudīt vai ir izveidoti uzdevumi no šī šablona
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE regulara_uzdevuma_id = ?");
            $stmt->execute([$template_id]);
            $usage_count = $stmt->fetchColumn();

            if ($usage_count > 0 && !$force_delete) {
                $errors[] = "Nevar dzēst šablonu, no kura ir izveidoti $usage_count uzdevumi. Izmantojiet 'Piespiedu dzēšana' vai deaktivizējiet šablonu.";
            } else {
                if ($force_delete && $usage_count > 0) {
                    // Dzēst visus saistītos uzdevumus un to datus
                    $stmt = $pdo->prepare("
                        DELETE FROM darba_laiks 
                        WHERE uzdevuma_id IN (SELECT id FROM uzdevumi WHERE regulara_uzdevuma_id = ?)
                    ");
                    $stmt->execute([$template_id]);

                    $stmt = $pdo->prepare("
                        DELETE FROM uzdevumu_vesture 
                        WHERE uzdevuma_id IN (SELECT id FROM uzdevumi WHERE regulara_uzdevuma_id = ?)
                    ");
                    $stmt->execute([$template_id]);

                    $stmt = $pdo->prepare("
                        DELETE FROM faili 
                        WHERE tips = 'Uzdevums' AND saistitas_id IN (SELECT id FROM uzdevumi WHERE regulara_uzdevuma_id = ?)
                    ");
                    $stmt->execute([$template_id]);

                    $stmt = $pdo->prepare("DELETE FROM uzdevumi WHERE regulara_uzdevuma_id = ?");
                    $stmt->execute([$template_id]);
                }

                $stmt = $pdo->prepare("DELETE FROM regularo_uzdevumu_sabloni WHERE id = ?");
                $stmt->execute([$template_id]);

                $pdo->commit();
                setFlashMessage('success', $force_delete && $usage_count > 0 ? 
                    "Regulārais uzdevums un visi saistītie uzdevumi ($usage_count gab.) dzēsti!" : 
                    'Regulārais uzdevums dzēsts!');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Kļūda dzēšot uzdevumu: " . $e->getMessage();
        }
    }

    if ($action === 'execute_now' && isset($_POST['template_id'])) {
        $template_id = intval($_POST['template_id']);

        try {
            // Iegūt šablona datus
            $stmt = $pdo->prepare("
                SELECT * FROM regularo_uzdevumu_sabloni 
                WHERE id = ? AND aktīvs = 1
            ");
            $stmt->execute([$template_id]);
            $template = $stmt->fetch();

            if ($template) {
                // Atrast brīvāko mehāniķi (normalizēt laiku)
                $template_time = substr($template['laiks'], 0, 5); // Noņemt sekundes ja ir
                $mechanic_data = findLeastBusyMechanic(date('Y-m-d'), $template_time);

                if ($mechanic_data) {
                    $mechanic_id = $mechanic_data['id'];
                    // Izveidot uzdevumu
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumi 
                        (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, 
                         prioritate, piešķirts_id, izveidoja_id, paredzamais_ilgums, regulara_uzdevuma_id)
                        VALUES (?, ?, 'Regulārais', ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $template['nosaukums'],
                        $template['apraksts'],
                        $template['vietas_id'],
                        $template['iekartas_id'],
                        $template['kategorijas_id'],
                        $template['prioritate'],
                        $mechanic_id,
                        $currentUser['id'],
                        $template['paredzamais_ilgums'],
                        $template_id
                    ]);

                    $task_id = $pdo->lastInsertId();

                    // Pievienot vēsturi
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture 
                        (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, NULL, 'Jauns', 'Regulārais uzdevums izveidots manuāli', ?)
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);

                    // Paziņot mehāniķim
                    createNotification(
                        $mechanic_id,
                        'Jauns regulārais uzdevums',
                        "Jums ir piešķirts regulārais uzdevums: {$template['nosaukums']}",
                        'Jauns uzdevums',
                        'Uzdevums',
                        $task_id
                    );

                    setFlashMessage('success', 'Regulārais uzdevums izveidots un piešķirts!');
                } else {
                    $errors[] = "Nav pieejamu mehāniķu uzdevuma piešķiršanai.";
                }
            } else {
                $errors[] = "Regulārais uzdevums nav atrasts vai nav aktīvs.";
            }
        } catch (PDOException $e) {
            $errors[] = "Kļūda izveidojot uzdevumu: " . $e->getMessage();
        }
    }

    if ($action === 'edit_template' && isset($_POST['template_id'])) {
        $template_id = intval($_POST['template_id']);
        $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
        $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
        $vietas_id = intval($_POST['vietas_id'] ?? 0);
        $iekartas_id = intval($_POST['iekartas_id'] ?? 0);
        $kategorijas_id = intval($_POST['kategorijas_id'] ?? 0);
        $prioritate = sanitizeInput($_POST['prioritate'] ?? 'Vidēja');
        $paredzamais_ilgums = floatval($_POST['paredzamais_ilgums'] ?? 0);
        $periodicitate = sanitizeInput($_POST['periodicitate'] ?? '');
        $periodicitas_dienas = $_POST['periodicitas_dienas'] ?? [];
        $laiks = $_POST['laiks'] ?? '09:00';
        $aktīvs = isset($_POST['aktīvs']) ? 1 : 0;

        // Validācija
        if (empty($nosaukums) || empty($apraksts) || empty($periodicitate)) {
            $errors[] = "Nosaukums, apraksts un periodicitāte ir obligāti.";
        }

        // Validēt periodicitātes dienas
        $json_dienas = null;
        if ($periodicitate === 'Katru nedēļu' && !empty($periodicitas_dienas)) {
            $valid_days = array_filter($periodicitas_dienas, function($day) {
                return is_numeric($day) && $day >= 1 && $day <= 7;
            });
            if (!empty($valid_days)) {
                $json_dienas = json_encode(array_values($valid_days));
            }
        } elseif ($periodicitate === 'Reizi mēnesī' && !empty($periodicitas_dienas)) {
            $valid_days = array_filter($periodicitas_dienas, function($day) {
                return is_numeric($day) && $day >= 1 && $day <= 31;
            });
            if (!empty($valid_days)) {
                $json_dienas = json_encode(array_values($valid_days));
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE regularo_uzdevumu_sabloni 
                    SET nosaukums = ?, apraksts = ?, vietas_id = ?, iekartas_id = ?, 
                        kategorijas_id = ?, prioritate = ?, paredzamais_ilgums = ?, 
                        periodicitate = ?, periodicitas_dienas = ?, laiks = ?, aktīvs = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $nosaukums,
                    $apraksts,
                    $vietas_id ?: null,
                    $iekartas_id ?: null,
                    $kategorijas_id ?: null,
                    $prioritate,
                    $paredzamais_ilgums ?: null,
                    $periodicitate,
                    $json_dienas,
                    $laiks,
                    $aktīvs,
                    $template_id
                ]);

                setFlashMessage('success', 'Regulārais uzdevums atjaunots!');
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot regulāro uzdevumu: " . $e->getMessage();
            }
        }
    }
}

// Funkcija brīvākā mehāniķa atrašanai (ņemot vērā darba grafiku un maiņu laikus)
function findLeastBusyMechanic($uzdevuma_datums = null, $uzdevuma_laiks = null) {
    global $pdo;

    if (!$uzdevuma_datums) {
        $uzdevuma_datums = date('Y-m-d');
    }

    if (!$uzdevuma_laiks) {
        $uzdevuma_laiks = date('H:i');
    }

    try {
        // Noteikt kura maiņa ir nepieciešama pēc laika
        $nepieciešamā_maiņa = null;
        if ($uzdevuma_laiks >= '07:00' && $uzdevuma_laiks <= '16:00') {
            $nepieciešamā_maiņa = 'R'; // Rīta maiņa (07:00 - 16:00)
        } elseif (($uzdevuma_laiks >= '16:01' && $uzdevuma_laiks <= '23:59') || 
                  ($uzdevuma_laiks >= '00:00' && $uzdevuma_laiks <= '01:00')) {
            $nepieciešamā_maiņa = 'V'; // Vakara maiņa (16:01 - 01:00)
        }

        // Vispirms meklēt mehāniķus ar atbilstošo darba grafiku
        if ($nepieciešamā_maiņa) {
            $stmt = $pdo->prepare("
                SELECT l.id, 
                       CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
                       COUNT(u.id) as aktīvo_uzdevumu_skaits,
                       SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 3 
                                WHEN u.prioritate = 'Augsta' THEN 2 
                                WHEN u.prioritate = 'Vidēja' THEN 1 
                                ELSE 0 END) as prioritātes_svars,
                       g.maina as darba_maina
                FROM lietotaji l
                LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
                INNER JOIN darba_grafiks g ON l.id = g.lietotaja_id AND g.datums = ?
                WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
                AND g.maina = ?
                GROUP BY l.id, g.maina
                ORDER BY aktīvo_uzdevumu_skaits ASC, prioritātes_svars ASC, l.id ASC
                LIMIT 1
            ");
            $stmt->execute([$uzdevuma_datums, $nepieciešamā_maiņa]);
            $result = $stmt->fetch();

            if ($result) {
                return $result;
            }
        }

        // Ja nav mehāniķu ar konkrētu maiņu, meklēt mehāniķus, kas strādā šajā datumā
        $stmt = $pdo->prepare("
            SELECT l.id, 
                   CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
                   COUNT(u.id) as aktīvo_uzdevumu_skaits,
                   SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 3 
                            WHEN u.prioritate = 'Augsta' THEN 2 
                            WHEN u.prioritate = 'Vidēja' THEN 1 
                            ELSE 0 END) as prioritātes_svars,
                   g.maina as darba_maina
            FROM lietotaji l
            LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
            INNER JOIN darba_grafiks g ON l.id = g.lietotaja_id AND g.datums = ?
            WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
            AND g.maina IN ('R', 'V')
            GROUP BY l.id, g.maina
            ORDER BY aktīvo_uzdevumu_skaits ASC, prioritātes_svars ASC, l.id ASC
            LIMIT 1
        ");
        $stmt->execute([$uzdevuma_datums]);
        $result = $stmt->fetch();

        if ($result) {
            return $result;
        }

        // Ja nav mehāniķu ar grafiku, iegūt jebkuru aktīvu mehāniķi
        $stmt = $pdo->query("
            SELECT l.id, 
                   CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
                   COUNT(u.id) as aktīvo_uzdevumu_skaits,
                   SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 3 
                            WHEN u.prioritate = 'Augsta' THEN 2 
                            WHEN u.prioritate = 'Vidēja' THEN 1 
                            ELSE 0 END) as prioritātes_svars
            FROM lietotaji l
            LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
            WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
            GROUP BY l.id
            ORDER BY aktīvo_uzdevumu_skaits ASC, prioritātes_svars ASC, l.id ASC
            LIMIT 1
        ");
        $result = $stmt->fetch();

        return $result;
    } catch (PDOException $e) {
        error_log("Kļūda meklējot brīvāko mehāniķi datumam $uzdevuma_datums: " . $e->getMessage());
        return null;
    }
}

// Iegūt datus
try {
    // Vietas
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();

    // Iekārtas
    $stmt = $pdo->query("SELECT id, nosaukums, vietas_id FROM iekartas WHERE aktīvs = 1 ORDER BY nosaukums");
    $iekartas = $stmt->fetchAll();

    // Kategorijas
    $stmt = $pdo->query("SELECT id, nosaukums FROM uzdevumu_kategorijas WHERE aktīvs = 1 ORDER BY nosaukums");
    $kategorijas = $stmt->fetchAll();

    // Regulārie uzdevumi
    $stmt = $pdo->query("
        SELECT r.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               k.nosaukums as kategorijas_nosaukums,
               CONCAT(l.vards, ' ', l.uzvards) as izveidoja_vards,
               (SELECT COUNT(*) FROM uzdevumi WHERE regulara_uzdevuma_id = r.id) as izveidoto_uzdevumu_skaits,
               (SELECT COUNT(*) FROM uzdevumi WHERE regulara_uzdevuma_id = r.id AND statuss = 'Pabeigts') as pabeigto_uzdevumu_skaits,
               (SELECT MAX(izveidots) FROM uzdevumi WHERE regulara_uzdevuma_id = r.id) as pēdējais_izveidots
        FROM regularo_uzdevumu_sabloni r
        LEFT JOIN vietas v ON r.vietas_id = v.id
        LEFT JOIN iekartas i ON r.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON r.kategorijas_id = k.id
        LEFT JOIN lietotaji l ON r.izveidoja_id = l.id
        ORDER BY r.aktīvs DESC, r.prioritate DESC, r.izveidots DESC
    ");
    $regular_tasks = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot datus: " . $e->getMessage();
    $vietas = $iekartas = $kategorijas = $regular_tasks = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Darbību josla -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <button onclick="openModal('createTemplateModal')" class="btn btn-success">Izveidot regulāro uzdevumu</button>
        <span class="text-muted">Kopā: <?php echo count($regular_tasks); ?> šabloni</span>
    </div>
    <div>
        <a href="cron_setup.php" class="btn btn-info">Automātiskās izpildes iestatījumi</a>
    </div>
</div>

<!-- Regulāro uzdevumu tabula -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Periodicitāte</th>
                        <th>Vieta/Iekārta</th>
                        <th>Prioritāte</th>
                        <th>Statuss</th>
                        <th>Statistika</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regular_tasks)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav izveidoti regulāri uzdevumi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regular_tasks as $task): ?>
                            <tr class="<?php echo !$task['aktīvs'] ? 'table-muted' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['apraksts'], 0, 100)) . (strlen($task['apraksts']) > 100 ? '...' : ''); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $task['periodicitate']; ?></strong>
                                        <?php if ($task['periodicitas_dienas']): ?>
                                            <br><small class="text-muted">
                                                <?php 
                                                $dienas = json_decode($task['periodicitas_dienas'], true);
                                                if ($task['periodicitate'] === 'Katru nedēļu' && $dienas) {
                                                    $nedēļas_dienas = ['', 'P', 'O', 'T', 'C', 'Pk', 'S', 'Sv'];
                                                    echo implode(', ', array_map(function($d) use ($nedēļas_dienas) { return $nedēļas_dienas[$d] ?? $d; }, $dienas));
                                                } elseif ($task['periodicitate'] === 'Reizi mēnesī' && $dienas) {
                                                    echo implode(', ', array_map(function($d) { return $d . '.'; }, $dienas));
                                                }
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                        <br><small class="text-muted">Laiks: <?php echo $task['laiks']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($task['vietas_nosaukums']): ?>
                                            <strong><?php echo htmlspecialchars($task['vietas_nosaukums']); ?></strong>
                                        <?php endif; ?>
                                        <?php if ($task['iekartas_nosaukums']): ?>
                                            <br><small><?php echo htmlspecialchars($task['iekartas_nosaukums']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($task['kategorijas_nosaukums']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($task['kategorijas_nosaukums']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                        <?php echo $task['prioritate']; ?>
                                    </span>
                                    <?php if ($task['paredzamais_ilgums']): ?>
                                        <br><small class="text-muted"><?php echo $task['paredzamais_ilgums']; ?>h</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $task['aktīvs'] ? 'status-aktīvs' : 'status-neaktīvs'; ?>">
                                        <?php echo $task['aktīvs'] ? 'Aktīvs' : 'Neaktīvs'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <small>Izveidoti: <?php echo $task['izveidoto_uzdevumu_skaits']; ?></small>
                                        <br><small>Pabeigti: <?php echo $task['pabeigto_uzdevumu_skaits']; ?></small>
                                        <?php if ($task['izveidoto_uzdevumu_skaits'] > 0): ?>
                                            <br><small>Efektivitāte: <?php echo number_format(($task['pabeigto_uzdevumu_skaits'] / $task['izveidoto_uzdevumu_skaits']) * 100, 1); ?>%</small>
                                        <?php endif; ?>
                                        <?php if ($task['pēdējais_izveidots']): ?>
                                            <br><small class="text-muted">Pēdējoreiz: <?php echo formatDate($task['pēdējais_izveidots']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="viewTemplate(<?php echo htmlspecialchars(json_encode($task)); ?>)" 
                                                class="btn btn-sm btn-info" title="Skatīt detaļas">👁</button>

                                        <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($task)); ?>)" 
                                                class="btn btn-sm btn-warning" title="Rediģēt">✏</button>

                                        <?php if ($task['aktīvs']): ?>
                                            <button onclick="executeNow(<?php echo $task['id']; ?>)" 
                                                    class="btn btn-sm btn-success" title="Izveidot uzdevumu tagad">▶</button>
                                        <?php endif; ?>

                                        <button onclick="toggleTemplate(<?php echo $task['id']; ?>, <?php echo $task['aktīvs'] ? 'false' : 'true'; ?>)" 
                                                class="btn btn-sm btn-secondary" title="<?php echo $task['aktīvs'] ? 'Deaktivizēt' : 'Aktivizēt'; ?>">
                                            <?php echo $task['aktīvs'] ? '⏸' : '▶'; ?>
                                        </button>

                                        <?php if ($task['izveidoto_uzdevumu_skaits'] == 0): ?>
                                            <button onclick="confirmAction('Vai tiešām vēlaties dzēst šo regulāro uzdevumu?', function() { deleteTemplate(<?php echo $task['id']; ?>); })" 
                                                    class="btn btn-sm btn-danger" title="Dzēst">🗑</button>
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

<!-- Šablona izveidošanas modāls -->
<div id="createTemplateModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Izveidot regulāro uzdevumu</h3>
            <button onclick="closeModal('createTemplateModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="createTemplateForm" method="POST">
                <input type="hidden" name="action" value="create_template">

                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="nosaukums" class="form-label">Uzdevuma nosaukums *</label>
                            <input type="text" id="nosaukums" name="nosaukums" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="prioritate" class="form-label">Prioritāte *</label>
                            <select id="prioritate" name="prioritate" class="form-control" required>
                                <option value="Zema">Zema</option>
                                <option value="Vidēja" selected>Vidēja</option>
                                <option value="Augsta">Augsta</option>
                                <option value="Kritiska">Kritiska</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="apraksts" class="form-label">Uzdevuma apraksts *</label>
                    <textarea id="apraksts" name="apraksts" class="form-control" rows="4" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="vietas_id" class="form-label">Vieta</label>
                            <select id="vietas_id" name="vietas_id" class="form-control" onchange="updateIekartas()">
                                <option value="">Izvēlieties vietu</option>
                                <?php foreach ($vietas as $vieta): ?>
                                    <option value="<?php echo $vieta['id']; ?>">
                                        <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="iekartas_id" class="form-label">Iekārta</label>
                            <select id="iekartas_id" name="iekartas_id" class="form-control">
                                <option value="">Izvēlieties iekārtu</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>" data-vieta="<?php echo $iekarta['vietas_id']; ?>">
                                        <?php echo htmlspecialchars($iekarta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="kategorijas_id" class="form-label">Kategorija</label>
                            <select id="kategorijas_id" name="kategorijas_id" class="form-control">
                                <option value="">Izvēlieties kategoriju</option>
                                <?php foreach ($kategorijas as $kategorija): ?>
                                    <option value="<?php echo $kategorija['id']; ?>">
                                        <?php echo htmlspecialchars($kategorija['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="paredzamais_ilgums" class="form-label">Paredzamais ilgums (h)</label>
                            <input type="number" id="paredzamais_ilgums" name="paredzamais_ilgums" class="form-control" step="0.5" min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="periodicitate" class="form-label">Periodicitāte *</label>
                            <select id="periodicitate" name="periodicitate" class="form-control" required onchange="updatePeriodicityOptions()">
                                <option value="">Izvēlieties periodicitāti</option>
                                <option value="Katru dienu">Katru dienu</option>
                                <option value="Katru nedēļu">Katru nedēļu</option>
                                <option value="Reizi mēnesī">Reizi mēnesī</option>
                                <option value="Reizi ceturksnī">Reizi ceturksnī</option>
                                <option value="Reizi gadā">Reizi gadā</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="laiks" class="form-label">Izveidošanas laiks</label>
                            <input type="time" id="laiks" name="laiks" class="form-control" value="09:00">
                        </div>
                    </div>
                </div>

                <!-- Nedēļas dienu izvēle -->
                <div id="weekDaysSection" class="form-group" style="display: none;">
                    <label class="form-label">Nedēļas dienas</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="1"> Pirmdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="2"> Otrdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="3"> Trešdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="4"> Ceturtdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="5"> Piektdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="6"> Sestdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="7"> Svētdiena</label>
                    </div>
                </div>

                <!-- Mēneša dienu izvēle -->
                <div id="monthDaysSection" class="form-group" style="display: none;">
                    <label class="form-label">Mēneša dienas</label>
                    <div class="checkbox-group">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <label><input type="checkbox" name="periodicitas_dienas[]" value="<?php echo $i; ?>"> <?php echo $i; ?>.</label>
                        <?php endfor; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('createTemplateModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('createTemplateForm').submit()" class="btn btn-success">Izveidot</button>
        </div>
    </div>
</div>

<!-- Šablona rediģēšanas modāls -->
<div id="editTemplateModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt regulāro uzdevumu</h3>
            <button onclick="closeModal('editTemplateModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editTemplateForm" method="POST">
                <input type="hidden" name="action" value="edit_template">
                <input type="hidden" name="template_id" id="edit_template_id">

                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="edit_nosaukums" class="form-label">Uzdevuma nosaukums *</label>
                            <input type="text" id="edit_nosaukums" name="nosaukums" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_prioritate" class="form-label">Prioritāte *</label>
                            <select id="edit_prioritate" name="prioritate" class="form-control" required>
                                <option value="Zema">Zema</option>
                                <option value="Vidēja">Vidēja</option>
                                <option value="Augsta">Augsta</option>
                                <option value="Kritiska">Kritiska</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_apraksts" class="form-label">Uzdevuma apraksts *</label>
                    <textarea id="edit_apraksts" name="apraksts" class="form-control" rows="4" required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_vietas_id" class="form-label">Vieta</label>
                            <select id="edit_vietas_id" name="vietas_id" class="form-control" onchange="updateEditIekartas()">
                                <option value="">Izvēlieties vietu</option>
                                <?php foreach ($vietas as $vieta): ?>
                                    <option value="<?php echo $vieta['id']; ?>">
                                        <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_iekartas_id" class="form-label">Iekārta</label>
                            <select id="edit_iekartas_id" name="iekartas_id" class="form-control">
                                <option value="">Izvēlieties iekārtu</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>" data-vieta="<?php echo $iekarta['vietas_id']; ?>">
                                        <?php echo htmlspecialchars($iekarta['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_kategorijas_id" class="form-label">Kategorija</label>
                            <select id="edit_kategorijas_id" name="kategorijas_id" class="form-control">
                                <option value="">Izvēlieties kategoriju</option>
                                <?php foreach ($kategorijas as $kategorija): ?>
                                    <option value="<?php echo $kategorija['id']; ?>">
                                        <?php echo htmlspecialchars($kategorija['nosaukums']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_paredzamais_ilgums" class="form-label">Paredzamais ilgums (h)</label>
                            <input type="number" id="edit_paredzamais_ilgums" name="paredzamais_ilgums" class="form-control" step="0.5" min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_periodicitate" class="form-label">Periodicitāte *</label>
                            <select id="edit_periodicitate" name="periodicitate" class="form-control" required onchange="updateEditPeriodicityOptions()">
                                <option value="">Izvēlieties periodicitāti</option>
                                <option value="Katru dienu">Katru dienu</option>
                                <option value="Katru nedēļu">Katru nedēļu</option>
                                <option value="Reizi mēnesī">Reizi mēnesī</option>
                                <option value="Reizi ceturksnī">Reizi ceturksnī</option>
                                <option value="Reizi gadā">Reizi gadā</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="edit_laiks" class="form-label">Izveidošanas laiks</label>
                            <input type="time" id="edit_laiks" name="laiks" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Nedēļas dienu izvēle -->
                <div id="editWeekDaysSection" class="form-group" style="display: none;">
                    <label class="form-label">Nedēļas dienas</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="1"> Pirmdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="2"> Otrdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="3"> Trešdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="4"> Ceturtdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="5"> Piektdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="6"> Sestdiena</label>
                        <label><input type="checkbox" name="periodicitas_dienas[]" value="7"> Svētdiena</label>
                    </div>
                </div>

                <!-- Mēneša dienu izvēle -->
                <div id="editMonthDaysSection" class="form-group" style="display: none;">
                    <label class="form-label">Mēneša dienas</label>
                    <div class="checkbox-group">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <label><input type="checkbox" name="periodicitas_dienas[]" value="<?php echo $i; ?>"> <?php echo $i; ?>.</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="edit_aktīvs" name="aktīvs"> Aktīvs
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('editTemplateModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('editTemplateForm').submit()" class="btn btn-primary">Saglabāt</button>
        </div>
    </div>
</div>

<!-- Šablona skatīšanas modāls -->
<div id="viewTemplateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Regulārā uzdevuma detaļas</h3>
            <button onclick="closeModal('viewTemplateModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="templateDetails">
            <!-- Saturs tiks ielādēts ar JavaScript -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('viewTemplateModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Iekartu filtrēšana pēc vietas
function updateIekartas() {
    const vietasSelect = document.getElementById('vietas_id');
    const iekartasSelect = document.getElementById('iekartas_id');
    const selectedVieta = vietasSelect.value;

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

    if (selectedVieta && iekartasSelect.value) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        const selectedIekartaVieta = selectedOption.getAttribute('data-vieta');
        if (selectedIekartaVieta !== selectedVieta) {
            iekartasSelect.value = '';
        }
    }
}

// Iekartu filtrēšana rediģēšanas modālā
function updateEditIekartas() {
    const vietasSelect = document.getElementById('edit_vietas_id');
    const iekartasSelect = document.getElementById('edit_iekartas_id');
    const selectedVieta = vietasSelect.value;

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

    if (selectedVieta && iekartasSelect.value) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        const selectedIekartaVieta = selectedOption.getAttribute('data-vieta');
        if (selectedIekartaVieta !== selectedVieta) {
            iekartasSelect.value = '';
        }
    }
}

// Periodicitātes opciju atjaunošana
function updatePeriodicityOptions() {
    const periodicitate = document.getElementById('periodicitate').value;
    const weekDaysSection = document.getElementById('weekDaysSection');
    const monthDaysSection = document.getElementById('monthDaysSection');

    // Paslēpt visas sekcijas
    weekDaysSection.style.display = 'none';
    monthDaysSection.style.display = 'none';

    // Notīrīt izvēles
    weekDaysSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    monthDaysSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

    // Parādīt attiecīgo sekciju
    if (periodicitate === 'Katru nedēļu') {
        weekDaysSection.style.display = 'block';
    } else if (periodicitate === 'Reizi mēnesī') {
        monthDaysSection.style.display = 'block';
    }
}

// Rediģēšanas periodicitātes opciju atjaunošana
function updateEditPeriodicityOptions() {
    const periodicitate = document.getElementById('edit_periodicitate').value;
    const weekDaysSection = document.getElementById('editWeekDaysSection');
    const monthDaysSection = document.getElementById('editMonthDaysSection');

    // Paslēpt visas sekcijas
    weekDaysSection.style.display = 'none';
    monthDaysSection.style.display = 'none';

    // Notīrīt izvēles
    weekDaysSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    monthDaysSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

    // Parādīt attiecīgo sekciju
    if (periodicitate === 'Katru nedēļu') {
        weekDaysSection.style.display = 'block';
    } else if (periodicitate === 'Reizi mēnesī') {
        monthDaysSection.style.display = 'block';
    }
}

// Šablona skatīšana
function viewTemplate(template) {
    const details = document.getElementById('templateDetails');

    let periodicityText = template.periodicitate;
    if (template.periodicitas_dienas) {
        const dienas = JSON.parse(template.periodicitas_dienas);
        if (template.periodicitate === 'Katru nedēļu') {
            const nedēļasDienas = ['', 'Pirmdiena', 'Otrdiena', 'Trešdiena', 'Ceturtdiena', 'Piektdiena', 'Sestdiena', 'Svētdiena'];
            periodicityText += ': ' + dienas.map(d => nedēļasDienas[d]).join(', ');
        } else if (template.periodicitate === 'Reizi mēnesī') {
            periodicityText += ': ' + dienas.map(d => d + '.').join(', ') + ' datums';
        }
    }

    details.innerHTML = `
        <div class="template-details">
            <div class="row">
                <div class="col-md-8">
                    <h4>${template.nosaukums}</h4>
                    <div class="template-description">
                        <strong>Apraksts:</strong>
                        <p>${template.apraksts.replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="template-meta">
                        <div><strong>Prioritāte:</strong> 
                            <span class="priority-badge priority-${template.prioritate.toLowerCase()}">${template.prioritate}</span>
                        </div>
                        <div><strong>Statuss:</strong> 
                            <span class="status-badge ${template.aktīvs ? 'status-aktīvs' : 'status-neaktīvs'}">
                                ${template.aktīvs ? 'Aktīvs' : 'Neaktīvs'}
                            </span>
                        </div>
                        ${template.paredzamais_ilgums ? '<div><strong>Paredzamais ilgums:</strong> ' + template.paredzamais_ilgums + 'h</div>' : ''}
                        ${template.vietas_nosaukums ? '<div><strong>Vieta:</strong> ' + template.vietas_nosaukums + '</div>' : ''}
                        ${template.iekartas_nosaukums ? '<div><strong>Iekārta:</strong> ' + template.iekartas_nosaukums + '</div>' : ''}
                        ${template.kategorijas_nosaukums ? '<div><strong>Kategorija:</strong> ' + template.kategorijas_nosaukums + '</div>' : ''}
                    </div>
                </div>
            </div>

            <div class="template-schedule">
                <h5>Izpildes grafiks</h5>
                <div><strong>Periodicitāte:</strong> ${periodicityText}</div>
                <div><strong>Izveidošanas laiks:</strong> ${template.laiks}</div>
            </div>

            <div class="template-stats">
                <h5>Statistika</h5>
                <div><strong>Izveidoti uzdevumi:</strong> ${template.izveidoto_uzdevumu_skaits}</div>
                <div><strong>Pabeigti uzdevumi:</strong> ${template.pabeigto_uzdevumu_skaits}</div>
                ${template.izveidoto_uzdevumu_skaits > 0 ? '<div><strong>Efektivitāte:</strong> ' + Math.round((template.pabeigto_uzdevumu_skaits / template.izveidoto_uzdevumu_skaits) * 100) + '%</div>' : ''}
                ${template.pēdējais_izveidots ? '<div><strong>Pēdējoreiz izveidots:</strong> ' + new Date(template.pēdējais_izveidots).toLocaleString('lv-LV') + '</div>' : ''}
                <div><strong>Izveidoja:</strong> ${template.izveidoja_vards}</div>
                <div><strong>Izveidots:</strong> ${new Date(template.izveidots).toLocaleString('lv-LV')}</div>
            </div>
        </div>
    `;

    openModal('viewTemplateModal');
}

// Šablona rediģēšana
function editTemplate(template) {
    document.getElementById('edit_template_id').value = template.id;
    document.getElementById('edit_nosaukums').value = template.nosaukums;
    document.getElementById('edit_apraksts').value = template.apraksts;
    document.getElementById('edit_vietas_id').value = template.vietas_id || '';
    document.getElementById('edit_iekartas_id').value = template.iekartas_id || '';
    document.getElementById('edit_kategorijas_id').value = template.kategorijas_id || '';
    document.getElementById('edit_prioritate').value = template.prioritate;
    document.getElementById('edit_paredzamais_ilgums').value = template.paredzamais_ilgums || '';
    document.getElementById('edit_periodicitate').value = template.periodicitate;
    document.getElementById('edit_laiks').value = template.laiks;
    document.getElementById('edit_aktīvs').checked = template.aktīvs == 1;

    // Atjaunot periodicitātes opcijas
    updateEditPeriodicityOptions();

    // Iestatīt periodicitātes dienas
    if (template.periodicitas_dienas) {
        const dienas = JSON.parse(template.periodicitas_dienas);
        const checkboxes = document.querySelectorAll('#editWeekDaysSection input[type="checkbox"], #editMonthDaysSection input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = dienas.includes(parseInt(cb.value));
        });
    }

    updateEditIekartas();
    openModal('editTemplateModal');
}

// Šablona statusa maiņa
function toggleTemplate(templateId, activate) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_template';

    const templateInput = document.createElement('input');
    templateInput.type = 'hidden';
    templateInput.name = 'template_id';
    templateInput.value = templateId;

    if (activate === 'true') {
        const activeInput = document.createElement('input');
        activeInput.type = 'hidden';
        activeInput.name = 'aktīvs';
        activeInput.value = '1';
        form.appendChild(activeInput);
    }

    form.appendChild(actionInput);
    form.appendChild(templateInput);

    document.body.appendChild(form);
    form.submit();
}

// Tūlītēja izpilde
function executeNow(templateId) {
    if (confirm('Vai vēlaties izveidot uzdevumu no šī šablona tagad?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'execute_now';

        const templateInput = document.createElement('input');
        templateInput.type = 'hidden';
        templateInput.name = 'template_id';
        templateInput.value = templateId;

        form.appendChild(actionInput);
        form.appendChild(templateInput);

        document.body.appendChild(form);
        form.submit();
    }
}

// Šablona dzēšana
function deleteTemplate(templateId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_template';

    const templateInput = document.createElement('input');
    templateInput.type = 'hidden';
    templateInput.name = 'template_id';
    templateInput.value = templateId;

    form.appendChild(actionInput);
    form.appendChild(templateInput);

    document.body.appendChild(form);
    form.submit();
}
</script>

<style>
.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-sm);
    cursor: pointer;
}

.template-details .row {
    display: flex;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.template-meta > div {
    margin-bottom: var(--spacing-sm);
}

.template-schedule,
.template-stats {
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--gray-300);
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

.col-md-4 {
    flex: 1;
    min-width: 200px;
}

.col-md-8 {
    flex: 2;
    min-width: 300px;
}

/* Modālo logu uzlabojumi */
.modal-content {
    max-height: 95vh;
    display: flex;
    flex-direction: column;
}

.modal-body {
    overflow-y: auto;
    max-height: calc(95vh - 120px);
    flex: 1;
}

.modal-footer {
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
    background: white;
    border-top: 1px solid var(--gray-300);
    z-index: 10;
}

#editTemplateModal .modal-content {
    max-width: 900px;
}

#createTemplateModal .modal-content {
    max-width: 900px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }

    .col-md-4,
    .col-md-8 {
        width: 100%;
        flex: none;
    }

    .checkbox-group {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .modal-content {
        max-height: 100vh;
        max-width: 100vw !important;
        margin: 0;
        border-radius: 0;
    }
    
    .modal-body {
        max-height: calc(100vh - 120px);
        padding: var(--spacing-md);
    }
}

@media (max-width: 480px) {
    .checkbox-group {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: var(--spacing-xs);
    }
    
    .checkbox-group label {
        font-size: 12px;
    }
    
    .modal-body {
        padding: var(--spacing-sm);
    }
}
</style>

<?php include 'includes/footer.php'; ?>