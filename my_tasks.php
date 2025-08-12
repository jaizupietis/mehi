<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MECHANIC);

$pageTitle = 'Mani uzdevumi';
$pageHeader = 'Mani uzdevumi';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

/**
 * Apstrādā uzdevuma pabeigšanu, ja tam ir piešķirti vairāki mehāniķi.
 *
 * @param int $taskId Uzdevuma ID.
 * @param int $mechanicId Pašreizējā mehāniķa ID.
 * @return bool Vai atjaunināšana bija veiksmīga.
 */
function completeMultiMechanicTask(int $taskId, int $mechanicId): bool
{
    global $pdo; // Piekļuve globālajam PDO savienojumam

    try {
        $pdo->beginTransaction();

        // Pabeigt darba laika uzskaiti šim mehāniķim
        $stmt = $pdo->prepare("
            UPDATE darba_laiks 
            SET beigu_laiks = NOW(), 
                stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
            WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
        ");
        $stmt->execute([$taskId, $mechanicId]);

        // Atjaunot piešķīruma statusu uz 'Pabeigts' šim mehāniķim
        $stmt = $pdo->prepare("
            UPDATE uzdevumu_piešķīrumi 
            SET statuss = 'Pabeigts', pabeigts = NOW() 
            WHERE uzdevuma_id = ? AND mehāniķa_id = ?
        ");
        $stmt->execute([$taskId, $mechanicId]);

        // Pārbaudīt, vai visi mehāniķi ir pabeiguši uzdevumu
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM uzdevumu_piešķīrumi 
            WHERE uzdevuma_id = ? AND statuss != 'Pabeigts' AND statuss != 'Noņemts'
        ");
        $stmt->execute([$taskId]);
        $activeAssignments = $stmt->fetchColumn();

        if ($activeAssignments == 0) {
            // Visi uzdevumi ir pabeigti, atjaunot galveno uzdevumu
            $stmt = $pdo->prepare("
                UPDATE uzdevumi 
                SET statuss = 'Pabeigts', 
                    beigu_laiks = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$taskId]);

            // Pievienot vēsturi par uzdevuma galveno pabeigšanu
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumu_vesture 
                (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                VALUES (?, 'Procesā', 'Pabeigts', 'Visi mehāniķi pabeiguši uzdevumu', ?)
            ");
            $stmt->execute([$taskId, $mechanicId]); // Šeit varētu būt nepieciešams ID, kurš ir galvenais
        }

        // Paziņot menedžerim/administratoram par darba pabeigšanu
        $taskStmt = $pdo->prepare("SELECT u.nosaukums, u.veids FROM uzdevumi u WHERE u.id = ?");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch();
        $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';

        // Iegūt mehāniķa vārdu
        $mechanicStmt = $pdo->prepare("SELECT CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE id = ?");
        $mechanicStmt->execute([$mechanicId]);
        $mechanicName = $mechanicStmt->fetchColumn();

        $managerStmt = $pdo->prepare("
            SELECT l.id 
            FROM lietotaji l 
            WHERE l.loma IN ('Administrators', 'Menedžeris') AND l.statuss = 'Aktīvs'
        ");
        $managerStmt->execute();
        $managers = $managerStmt->fetchAll();

        foreach ($managers as $manager) {
            createNotification(
                $manager['id'],
                "$uzdevuma_tips pabeigts",
                "Mehāniķis $mechanicName ir pabeidzis savu daļu uzdevumā: {$task['nosaukums']}",
                'Statusa maiņa',
                'Uzdevums',
                $taskId
            );
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Kļūda completeMultiMechanicTask: " . $e->getMessage());
        return false;
    }
}


// Function removed - using the one in config.php instead to avoid redeclaration


// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);

        try {
            $pdo->beginTransaction();

            // Pārbaudīt vai uzdevums pieder lietotājam un nav sākts
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs
                FROM uzdevumi u 
                WHERE u.id = ? AND (u.piešķirts_id = ? OR EXISTS (
                    SELECT 1 FROM uzdevumu_piešķīrumi up 
                    WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
                ))
            ");
            $stmt->execute([$currentUser['id'], $task_id, $currentUser['id'], $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                $errors[] = 'Uzdevums nav atrasts vai jums nav tiesību to sākt.';
            } elseif ($task['aktīvs_darbs'] > 0) {
                $errors[] = 'Jūs jau strādājat pie šī uzdevuma!';
            } elseif (!in_array($task['statuss'], ['Jauns', 'Procesā'])) {
                $errors[] = 'Var sākt tikai jaunus vai procesā esošus uzdevumus.';
            } else {
                // Ja uzdevums ir vairākiem mehāniķiem, atjaunināt piešķīrumu statusu
                if ($task['daudziem_mehāniķiem']) {
                    $stmt = $pdo->prepare("
                        UPDATE uzdevumu_piešķīrumi 
                        SET statuss = 'Sākts', sākts = NOW() 
                        WHERE uzdevuma_id = ? AND mehāniķa_id = ?
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                }

                // Ja uzdevums ir jauns, mainīt uz "Procesā"
                if ($task['statuss'] === 'Jauns') {
                    $stmt = $pdo->prepare("UPDATE uzdevumi SET statuss = 'Procesā', sakuma_laiks = NOW() WHERE id = ?");
                    $stmt->execute([$task_id]);

                    // Pievienot vēsturi
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, 'Jauns', 'Procesā', 'Darbs sākts', ?)
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                }

                // Sākt darba laika uzskaiti
                $stmt = $pdo->prepare("
                    INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $task_id]);

                // Ja šis ir kritisks uzdevums, noņemt citiem mehāniķiem
                if ($task['prioritate'] === 'Kritiska' && $task['problemas_id']) {
                    removeCriticalTaskFromOtherMechanics($task_id, $currentUser['id']);
                }

                $success = 'Uzdevuma darbs sākts!';
            }

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda sākot darbu: ' . $e->getMessage();
        }
    }

    if ($action === 'pause_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);

        try {
            // Pabeigt pašreizējo darba laika ierakstu
            $stmt = $pdo->prepare("
                UPDATE darba_laiks 
                SET beigu_laiks = NOW(), 
                    stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
                WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
            ");
            $stmt->execute([$task_id, $currentUser['id']]);

            setFlashMessage('success', 'Darbs pauzēts!');

        } catch (PDOException $e) {
            $errors[] = 'Kļūda pauzējot darbu: ' . $e->getMessage();
        }
    }

    if ($action === 'resume_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);

        try {
            // Sākt jaunu darba laika ierakstu
            $stmt = $pdo->prepare("
                INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$currentUser['id'], $task_id]);

            setFlashMessage('success', 'Darbs atsākts!');

        } catch (PDOException $e) {
            $errors[] = 'Kļūda atsākot darbu: ' . $e->getMessage();
        }
    }

    if ($action === 'complete_task' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);

        try {
            // Iegūt uzdevuma informāciju
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs
                FROM uzdevumi u 
                WHERE u.id = ? AND (u.piešķirts_id = ? OR EXISTS (
                    SELECT 1 FROM uzdevumu_piešķīrumi up 
                    WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
                ))
            ");
            $stmt->execute([$currentUser['id'], $task_id, $currentUser['id'], $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                $errors[] = 'Uzdevums nav atrasts vai jums nav tiesību to pabeigt.';
            } elseif (!in_array($task['statuss'], ['Jauns', 'Procesā'])) {
                $errors[] = 'Var pabeigt tikai jaunus vai procesā esošus uzdevumus.';
            } else {
                // Izmantot vairāku mehāniķu pabeigšanas funkciju
                if ($task['daudziem_mehāniķiem']) {
                    // Pārbaudīt vai mehāniķis ir sācis darbu pie šī uzdevuma
                    $stmt = $pdo->prepare("
                        SELECT statuss FROM uzdevumu_piešķīrumi 
                        WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                    $assignment_status = $stmt->fetchColumn();

                    if (!$assignment_status) {
                        $errors[] = 'Jums nav piešķīruma šim uzdevumam.';
                    } elseif ($assignment_status === 'Pabeigts') {
                        $errors[] = 'Jūs jau esat pabeidzis savu uzdevuma daļu.';
                    } else {
                        if (completeMultiMechanicTask($task_id, $currentUser['id'])) {
                            $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';
                            $success = "Jūsu uzdevuma daļa veiksmīgi pabeigta!";
                        } else {
                            $errors[] = 'Kļūda pabeidzot vairāku mehāniķu uzdevumu.';
                        }
                    }
                } else {
                    // Parastā loģika vienam mehāniķim
                    $pdo->beginTransaction();

                    // Pabeigt darba laika uzskaiti
                    $stmt = $pdo->prepare("
                        UPDATE darba_laiks 
                        SET beigu_laiks = NOW(), 
                            stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
                        WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);

                    // Aprēķināt kopējo darba laiku
                    $stmt = $pdo->prepare("
                        SELECT SUM(stundu_skaits) as kopejais_laiks 
                        FROM darba_laiks 
                        WHERE uzdevuma_id = ? AND lietotaja_id = ?
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                    $total_time = $stmt->fetchColumn() ?: 0;

                    // Atjaunot uzdevuma statusu
                    $stmt = $pdo->prepare("
                        UPDATE uzdevumi 
                        SET statuss = 'Pabeigts', 
                            beigu_laiks = NOW(),
                            faktiskais_ilgums = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$total_time, $task_id]);

                    // Pievienot vēsturi
                    $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture 
                        (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, ?, 'Pabeigts', ?, ?)
                    ");
                    $stmt->execute([
                        $task_id, 
                        $task['statuss'], 
                        "$uzdevuma_tips pabeigts", 
                        $currentUser['id']
                    ]);

                    $pdo->commit();
                    $success = "$uzdevuma_tips veiksmīgi pabeigts!";
                }
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Kļūda pabeidzot uzdevumu: ' . $e->getMessage();
        }
    }

    // Redirect to prevent form resubmission
    header('Location: my_tasks.php');
    exit();
}

try {
    // Pārbaudīt vai rādīt tikai šodienas uzdevumus
    $show_today_only = isset($_GET['today']) && $_GET['today'] == '1';
    $date_filter = $show_today_only ? "AND DATE(u.izveidots) = CURDATE()" : "";

    // Ielādēt uzdevumus (visus vai tikai šodienas)
    $sql = "
        SELECT u.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               k.nosaukums as kategorijas_nosaukums,
               r.periodicitate,
               (SELECT COUNT(*) FROM faili WHERE tips = 'Uzdevums' AND saistitas_id = u.id) as failu_skaits,
               (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
               CASE 
                   WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 
                   WHEN u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 3 THEN 1
                   ELSE 0 
               END as ir_nokavets,
               (SELECT COUNT(*) FROM uzdevumi WHERE prioritate = 'Kritiska' AND statuss != 'Pabeigts') as kopējās_kritiskās_problēmas,
               u.daudziem_mehāniķiem,
               (SELECT COUNT(*) FROM uzdevumu_piešķīrumi WHERE uzdevuma_id = u.id) as piešķīrumu_skaits,
               (SELECT COUNT(*) FROM uzdevumu_piešķīrumi WHERE uzdevuma_id = u.id AND statuss IN ('Sākts', 'Pabeigts')) as aktīvo_piešķīrumu_skaits,
               (SELECT GROUP_CONCAT(CONCAT(l.vards, ' ', l.uzvards) SEPARATOR ', ') 
                FROM uzdevumu_piešķīrumi up 
                JOIN lietotaji l ON up.mehāniķa_id = l.id 
                WHERE up.uzdevuma_id = u.id AND up.statuss != 'Noņemts') as visi_piešķirtie,
               CASE 
                   WHEN u.prioritate = 'Kritiska' AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1
                   ELSE 0
               END as ir_kritisks_aktīvs,
               CASE 
                   WHEN u.daudziem_mehāniķiem = 1 THEN (
                       SELECT up.statuss FROM uzdevumu_piešķīrumi up 
                       WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
                   )
                   ELSE u.statuss
               END as mans_statuss,
               (SELECT up.statuss FROM uzdevumu_piešķīrumi up 
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts') as mans_piešķīruma_statuss
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        WHERE (u.piešķirts_id = ? OR (u.daudziem_mehāniķiem = 1 AND EXISTS(
            SELECT 1 FROM uzdevumu_piešķīrumi WHERE uzdevuma_id = u.id AND mehāniķa_id = ? AND statuss != 'Noņemts'
        ))) AND u.statuss != 'Nodalīts' $date_filter
        ORDER BY 
            ir_kritisks_aktīvs DESC,
            ir_nokavets DESC,
            u.izveidots DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
    $uzdevumi = $stmt->fetchAll();

    // Iegūt kopējo kritisko problēmu skaitu visiem operatoriem
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE prioritate = 'Kritiska' AND statuss != 'Pabeigts'");
    $stmt->execute();
    $kopējās_kritiskās_problēmas = $stmt->fetchColumn();


    // Statistika pa veidiem - tikai šodienas uzdevumi
    $stmt = $pdo->prepare("
        SELECT 
            u.veids,
            COUNT(*) as kopā,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti,
            SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi,
            SUM(CASE WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavēti
        FROM uzdevumi u
        WHERE (u.piešķirts_id = ? OR EXISTS (
            SELECT 1 FROM uzdevumu_piešķīrumi up 
            WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
        )) AND u.statuss != 'Nodalīts' AND DATE(u.izveidots) = CURDATE()
        GROUP BY u.veids
    ");
    $stmt->execute([$currentUser['id'], $currentUser['id']]);
    $statistika_pa_veidiem = [];
    while ($row = $stmt->fetch()) {
        $statistika_pa_veidiem[$row['veids']] = $row;
    }

} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot uzdevumus: " . $e->getMessage();
    $uzdevumi = [];
    $statistika_pa_veidiem = [];
    $kopējās_kritiskās_problēmas = 0;
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Statistikas kartes pa uzdevumu veidiem - šodienas uzdevumi -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-number"><?php echo ($statistika_pa_veidiem['Ikdienas']['kopā'] ?? 0); ?></div>
        <div class="stat-label">Šodienas ikdienas uzdevumi</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo ($statistika_pa_veidiem['Regulārais']['kopā'] ?? 0); ?></div>
        <div class="stat-label">Šodienas regulārie uzdevumi</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);">
            <?php 
            $total_active = ($statistika_pa_veidiem['Ikdienas']['aktīvi'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['aktīvi'] ?? 0);
            echo $total_active;
            ?>
        </div>
        <div class="stat-label">Šodienas aktīvie</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);">
            <?php 
            $total_completed = ($statistika_pa_veidiem['Ikdienas']['pabeigti'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['pabeigti'] ?? 0);
            echo $total_completed;
            ?>
        </div>
        <div class="stat-label">Šodienas pabeigti</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);">
            <?php 
            $total_overdue = ($statistika_pa_veidiem['Ikdienas']['nokavēti'] ?? 0) + ($statistika_pa_veidiem['Regulārais']['nokavēti'] ?? 0);
            echo $total_overdue;
            ?>
        </div>
        <div class="stat-label">Šodienas nokavētie</div>
    </div>

</div>

<!-- Navigācijas saites un filtri -->
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php 
            $show_today_only = isset($_GET['today']) && $_GET['today'] == '1';
            ?>
            <?php if ($show_today_only): ?>
                <a href="my_tasks.php" class="btn btn-primary">Visi mani uzdevumi</a>
                <span class="btn btn-outline-primary disabled">Šodienas uzdevumi</span>
            <?php else: ?>
                <span class="btn btn-primary disabled">Visi mani uzdevumi</span>
                <a href="my_tasks.php?today=1" class="btn btn-outline-primary">Šodienas uzdevumi</a>
            <?php endif; ?>
            <a href="completed_tasks.php" class="btn btn-outline-success">Pabeigto uzdevumu vēsture</a>
        </div>
    </div>
</div>

<!-- Uzdevumu saraksts -->
<div class="tasks-grid">
    <?php if (empty($uzdevumi)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h4>Nav uzdevumu</h4>
                <p>Jums pašlaik nav piešķirts neviens uzdevums.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($uzdevumi as $uzdevums): ?>
            <div class="task-card <?php echo strtolower($uzdevums['prioritate']); ?> <?php echo strtolower(str_replace(' ', '-', $uzdevums['statuss'])); ?> <?php echo $uzdevums['ir_nokavets'] ? 'overdue' : ''; ?> <?php echo $uzdevums['veids'] === 'Regulārais' ? 'regular-task' : ''; ?>">
                <div class="task-header">
                    <div class="task-title">
                        <h4>
                            <?php echo htmlspecialchars($uzdevums['nosaukums']); ?>
                            <?php if ($uzdevums['veids'] === 'Regulārais'): ?>
                                <span class="task-type-badge">Regulārais</span>
                            <?php endif; ?>
                        </h4>
                        <div class="task-badges">
                            <span class="priority-badge <?php echo getPriorityClass($uzdevums['prioritate']); ?>">
                                <?php echo $uzdevums['prioritate']; ?>
                            </span>
                            <span class="status-badge <?php echo getStatusClass($uzdevums['statuss']); ?>">
                                <?php echo $uzdevums['statuss']; ?>
                            </span>
                            <?php if ($uzdevums['failu_skaits'] > 0): ?>
                                <span class="file-badge" title="Pievienoti faili">📎 <?php echo $uzdevums['failu_skaits']; ?></span>
                            <?php endif; ?>
                            <?php if ($uzdevums['daudziem_mehāniķiem']): ?>
                                <span class="multi-mechanic-badge" title="Piešķirts vairākiem mehāniķiem">👥 <?php echo $uzdevums['piešķīrumu_skaits']; ?></span>
                            <?php endif; ?>
                            <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                <span class="working-badge" title="Darbs procesā">⏰ Darbs procesā</span>
                            <?php endif; ?>
                            <?php if ($uzdevums['ir_nokavets']): ?>
                                <span class="overdue-badge" title="Nokavēts">⚠️ NOKAVĒTS</span>
                            <?php endif; ?>
                            <?php if ($uzdevums['periodicitate']): ?>
                                <span class="periodicity-badge" title="Periodicitāte"><?php echo $uzdevums['periodicitate']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="task-body">
                    <div class="task-meta">
                        <?php if ($uzdevums['vietas_nosaukums']): ?>
                            <div><strong>Vieta:</strong> <?php echo htmlspecialchars($uzdevums['vietas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['iekartas_nosaukums']): ?>
                            <div><strong>Iekārta:</strong> <?php echo htmlspecialchars($uzdevums['iekartas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['kategorijas_nosaukums']): ?>
                            <div><strong>Kategorija:</strong> <?php echo htmlspecialchars($uzdevums['kategorijas_nosaukums']); ?></div>
                        <?php endif; ?>
                        <?php if ($uzdevums['jabeidz_lidz']): ?>
                            <div><strong>Termiņš:</strong> 
                                <span class="<?php echo $uzdevums['ir_nokavets'] ? 'text-danger' : ''; ?>">
                                    <?php echo formatDate($uzdevums['jabeidz_lidz']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($uzdevums['paredzamais_ilgums']): ?>
                            <div><strong>Paredzamais ilgums:</strong> <?php echo $uzdevums['paredzamais_ilgums']; ?> h</div>
                        <?php endif; ?>
                        <?php if ($uzdevums['daudziem_mehāniķiem']): ?>
                            <div><strong>Piešķirts:</strong> <?php echo htmlspecialchars($uzdevums['visi_piešķirtie']); ?></div>
                            <div><strong>Aktīvi strādā:</strong> <?php echo $uzdevums['aktīvo_piešķīrumu_skaits']; ?> no <?php echo $uzdevums['piešķīrumu_skaits']; ?></div>
                            <?php
                            // Parādīt pašreizējā mehāniķa statusu
                            $stmt = $pdo->prepare("
                                SELECT statuss FROM uzdevumu_piešķīrumi 
                                WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                            ");
                            $stmt->execute([$uzdevums['id'], $currentUser['id']]);
                            $my_assignment_status = $stmt->fetchColumn();
                            ?>
                            <?php if ($my_assignment_status): ?>
                                <div><strong>Mans statuss:</strong> 
                                    <span class="badge <?php echo $my_assignment_status === 'Pabeigts' ? 'badge-success' : ($my_assignment_status === 'Sākts' ? 'badge-warning' : 'badge-secondary'); ?>">
                                        <?php echo $my_assignment_status; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="task-description">
                        <?php echo htmlspecialchars(substr($uzdevums['apraksts'], 0, 200)) . (strlen($uzdevums['apraksts']) > 200 ? '...' : ''); ?>
                    </div>
                </div>

                <div class="task-footer">
                    <div class="task-actions">
                        <button onclick="viewTask(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-info">Skatīt detaļas</button>

                        <?php if ($uzdevums['statuss'] === 'Jauns'): ?>
                            <button onclick="startWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-success">Sākt darbu</button>
                        <?php elseif ($uzdevums['statuss'] === 'Procesā'): ?>
                            <?php
                            // Pārbaudīt vai mehāniķis var pabeigt uzdevumu
                            $can_complete = true;
                            $my_assignment_status = null;
                            if ($uzdevums['daudziem_mehāniķiem']) {
                                // Pārbaudīt vai mehāniķis ir sācis darbu
                                $stmt = $pdo->prepare("
                                    SELECT statuss FROM uzdevumu_piešķīrumi 
                                    WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                                ");
                                $stmt->execute([$uzdevums['id'], $currentUser['id']]);
                                $my_assignment_status = $stmt->fetchColumn();

                                if (!$my_assignment_status || $my_assignment_status === 'Pabeigts') {
                                    $can_complete = false;
                                }
                            }
                            ?>

                            <?php if ($can_complete && (!$uzdevums['daudziem_mehāniķiem'] || $my_assignment_status !== 'Pabeigts')): ?>
                                <?php if ($uzdevums['aktīvs_darbs'] > 0): ?>
                                    <button onclick="pauseWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-warning">Pauzēt</button>
                                <?php else: ?>
                                    <button onclick="resumeWork(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-warning">Turpināt</button>
                                <?php endif; ?>

                                <button onclick="completeTask(<?php echo $uzdevums['id']; ?>)" class="btn btn-sm btn-success">
                                    <?php echo $uzdevums['daudziem_mehāniķiem'] ? 'Pabeigt savu daļu' : 'Pabeigt'; ?>
                                </button>
                            <?php else: ?>
                                <?php if ($uzdevums['daudziem_mehāniķiem'] && $my_assignment_status === 'Pabeigts'): ?>
                                    <span class="btn btn-sm btn-secondary disabled" title="Jūs jau esat pabeidzis savu daļu">✓ Pabeigts</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="task-time">
                        <small>Izveidots: <?php echo formatDate($uzdevums['izveidots']); ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modālie logi -->

<!-- Uzdevuma pabeigšanas modāls -->
<div id="completeTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pabeigt uzdevumu</h3>
            <button onclick="closeModal('completeTaskModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="completeTaskForm" method="POST">
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="completeTaskId">

                <div class="form-group">
                    <label for="faktiskais_ilgums" class="form-label">Faktiskais izpildes laiks (stundas)</label>
                    <input type="number" id="faktiskais_ilgums" name="faktiskais_ilgums" class="form-control" step="0.1" min="0">
                    <small class="form-text text-muted">Atstājiet tukšu, lai automātiski aprēķinātu no darba laika</small>
                </div>

                <div class="form-group">
                    <label for="komentars" class="form-label">Komentārs par paveikto darbu</label>
                    <textarea id="komentars" name="komentars" class="form-control" rows="4" placeholder="Aprakstiet paveikto darbu, izmantotie materiāli, problēmas, u.c."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('completeTaskModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('completeTaskForm').submit()" class="btn btn-success">Pabeigt uzdevumu</button>
        </div>
    </div>
</div>

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

<script>
// Darba sākšana
function startWork(taskId) {
    if (confirm('Vai vēlaties sākt darbu pie šī uzdevuma?')) {
        submitAction('start_work', taskId);
    }
}

// Darba pauzēšana
function pauseWork(taskId) {
    if (confirm('Vai vēlaties pauzēt darbu pie šī uzdevuma?')) {
        submitAction('pause_work', taskId);
    }
}

// Darba atsākšana
function resumeWork(taskId) {
    if (confirm('Vai vēlaties atsākt darbu pie šī uzdevuma?')) {
        submitAction('resume_work', taskId);
    }
}

// Uzdevuma pabeigšana
function completeTask(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('faktiskais_ilgums').value = '';
    document.getElementById('komentars').value = '';
    openModal('completeTaskModal');
}

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

// Palīgfunkcija POST darbībām
function submitAction(action, taskId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;

    const taskInput = document.createElement('input');
    taskInput.type = 'hidden';
    taskInput.name = 'task_id';
    taskInput.value = taskId;

    form.appendChild(actionInput);
    form.appendChild(taskInput);

    document.body.appendChild(form);
    form.submit();
}

// Helper functions for modal management (assuming these exist in includes/header.php or similar)
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Add a class to body to prevent scrolling
        document.body.classList.add('modal-open');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // Remove the class from body to allow scrolling
        document.body.classList.remove('modal-open');
    }
}
</script>

<style>
/* Uzdevumu režģa izkārtojums */
.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--spacing-lg);
    margin-top: var(--spacing-lg);
}

@media (max-width: 768px) {
    .tasks-grid {
        grid-template-columns: 1fr;
    }
}

/* Uzdevuma kartes */
.task-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: all 0.3s ease;
    border-left: 4px solid var(--gray-400);
    position: relative;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.task-card.kritiska {
    border-left-color: var(--priority-critical);
}

.task-card.augsta {
    border-left-color: var(--priority-high);
}

.task-card.vidēja {
    border-left-color: var(--priority-medium);
}

.task-card.zema {
    border-left-color: var(--priority-low);
}

.task-card.procesā {
    background: linear-gradient(135deg, var(--white) 0%, rgba(243, 156, 18, 0.05) 100%);
}

.task-card.pabeigts {
    opacity: 0.8;
    background: linear-gradient(135deg, var(--white) 0%, rgba(39, 174, 96, 0.05) 100%);
}

.task-card.overdue {
    background: linear-gradient(135deg, var(--white) 0%, rgba(231, 76, 60, 0.05) 100%);
    border-left-color: var(--danger-color) !important;
}

.task-card.regular-task::before {
    content: 'R';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    background: var(--info-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.task-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--gray-300);
}

.task-title h4 {
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.task-badges {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.task-type-badge {
    background: var(--info-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 8px;
}

.overdue-badge {
    background: var(--danger-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    animation: pulse 1.5s infinite;
}

.periodicity-badge {
    background: var(--secondary-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.task-body {
    padding: var(--spacing-md);
}

.task-meta {
    margin-bottom: var(--spacing-md);
    font-size: var(--font-size-sm);
}

.task-meta div {
    margin-bottom: var(--spacing-xs);
    color: var(--gray-600);
}

.task-description {
    color: var(--gray-700);
    line-height: 1.5;
    margin-bottom: var(--spacing-md);
}

.task-footer {
    padding: var(--spacing-md);
    border-top: 1px solid var(--gray-300);
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.task-actions {
    display: flex;
    gap: var(--spacing-xs);
    flex-wrap: wrap;
}

.task-time {
    color: var(--gray-500);
    font-size: var(--font-size-sm);
}

/* Papildu iezīmes */
.file-badge {
    background: var(--info-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.working-badge {
    background: var(--warning-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    animation: pulse 1.5s infinite;
}

.multi-mechanic-badge {
    background: var(--primary-color);
    color: var(--white);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

/* Modālais logs */
.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1050; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px); /* For Safari */
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto; /* 15% from the top and centered */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Could be more or less, depending on screen size */
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    margin-bottom: 15px;
}

.modal-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    color: #aaa;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding-bottom: 20px;
}

.modal-body .form-group {
    margin-bottom: 15px;
}

.modal-body .form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.modal-body .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
}

.modal-body textarea.form-control {
    resize: vertical;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid #eee;
    margin-top: 15px;
    gap: 10px;
}

.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color,background-color,border-color,box-shadow 0.15s ease-in-out;
}

.btn-secondary {
    color: #6c757d;
    background-color: #e9ecef;
    border-color: #e9ecef;
}

.btn-success {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}

.btn-info {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-warning {
    color: #212529;
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-outline-success {
    color: #28a745;
    background-color: transparent;
    border-color: #28a745;
}

.btn:hover {
    opacity: 0.85;
}


/* Small screen adjustments for modal */
@media (max-width: 768px) {
    .modal-content {
        margin: 10% auto;
        width: 90%;
    }
}

@media (max-width: 480px) {
    .task-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .task-actions {
        justify-content: center;
    }

    .task-time {
        text-align: center;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<?php include 'includes/footer.php'; ?>