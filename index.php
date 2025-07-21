<?php
require_once 'config.php';

$pageTitle = 'Sākums';
$pageHeader = 'Sveicināti AVOTI Task Management sistēmā';
$currentUser = getCurrentUser();

// Apstrādāt POST darbības (regulāro uzdevumu darbības mehāniķiem)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(ROLE_MECHANIC)) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai uzdevums pieder lietotājam
            $stmt = $pdo->prepare("SELECT statuss FROM uzdevumi WHERE id = ? AND piešķirts_id = ?");
            $stmt->execute([$task_id, $currentUser['id']]);
            $task = $stmt->fetch();
            
            if ($task && $task['statuss'] === 'Jauns') {
                // Mainīt statusu uz "Procesā"
                $stmt = $pdo->prepare("UPDATE uzdevumi SET statuss = 'Procesā', sakuma_laiks = NOW() WHERE id = ?");
                $stmt->execute([$task_id]);
                
                // Sākt darba laika uzskaiti
                $stmt = $pdo->prepare("
                    INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $task_id]);
                
                // Pievienot vēsturi
                $stmt = $pdo->prepare("
                    INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                    VALUES (?, 'Jauns', 'Procesā', 'Darbs sākts no sākumlapas', ?)
                ");
                $stmt->execute([$task_id, $currentUser['id']]);
                
                $pdo->commit();
                setFlashMessage('success', 'Uzdevuma darbs sākts!');
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Kļūda sākot darbu: ' . $e->getMessage());
        }
    }
    
    if ($action === 'complete_task' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        try {
            $pdo->beginTransaction();
            
            // Pārbaudīt vai uzdevums pieder lietotājam
            $stmt = $pdo->prepare("SELECT statuss, veids FROM uzdevumi WHERE id = ? AND piešķirts_id = ?");
            $stmt->execute([$task_id, $currentUser['id']]);
            $task = $stmt->fetch();
            
            if ($task && in_array($task['statuss'], ['Jauns', 'Procesā'])) {
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
                        atbildes_komentars = 'Pabeigts no sākumlapas',
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
                    "$uzdevuma_tips pabeigts no sākumlapas", 
                    $currentUser['id']
                ]);
                
                $pdo->commit();
                setFlashMessage('success', "$uzdevuma_tips pabeigts!");
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Kļūda pabeidzot uzdevumu: ' . $e->getMessage());
        }
    }
}

// Iegūt statistiku atkarībā no lietotāja lomas
$stats = [];

try {
    if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
        // Administratora un menedžera statistika
        
        // Kopējie uzdevumi (ikdienas)
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Ikdienas'");
        $stats['total_tasks'] = $stmt->fetchColumn();
        
        // Aktīvie uzdevumi (ikdienas)
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Ikdienas' AND statuss IN ('Jauns', 'Procesā')");
        $stats['active_tasks'] = $stmt->fetchColumn();
        
        // Pabeigto uzdevumu šomēnes (ikdienas)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE veids = 'Ikdienas' AND statuss = 'Pabeigts' 
            AND MONTH(beigu_laiks) = MONTH(NOW()) 
            AND YEAR(beigu_laiks) = YEAR(NOW())
        ");
        $stats['completed_this_month'] = $stmt->fetchColumn();
        
        // Kritiskās prioritātes uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE prioritate = 'Kritiska' AND statuss IN ('Jauns', 'Procesā')");
        $stats['critical_tasks'] = $stmt->fetchColumn();
        
        // Jaunas problēmas
        $stmt = $pdo->query("SELECT COUNT(*) FROM problemas WHERE statuss = 'Jauna'");
        $stats['new_problems'] = $stmt->fetchColumn();
        
        // Aktīvie mehāniķi
        $stmt = $pdo->query("SELECT COUNT(*) FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs'");
        $stats['active_mechanics'] = $stmt->fetchColumn();
        
        // Regulārie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM regularo_uzdevumu_sabloni WHERE aktīvs = 1");
        $stats['active_regular_templates'] = $stmt->fetchColumn();
        
        // Šodienas regulārie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND DATE(izveidots) = CURDATE()");
        $stats['todays_regular_tasks'] = $stmt->fetchColumn();
        
        // Jaunākie uzdevumi (ikdienas)
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
            WHERE u.veids = 'Ikdienas'
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_tasks = $stmt->fetchAll();
        
        // Jaunākie regulārie uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards,
                   r.periodicitate
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
            LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
            WHERE u.veids = 'Regulārais'
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_regular_tasks = $stmt->fetchAll();
        
        // Jaunākās problēmas
        $stmt = $pdo->prepare("
            SELECT p.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CONCAT(l.vards, ' ', l.uzvards) as zinotaja_vards
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            LEFT JOIN iekartas i ON p.iekartas_id = i.id
            LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
            WHERE p.statuss = 'Jauna'
            ORDER BY p.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_problems = $stmt->fetchAll();
        
    } elseif (hasRole(ROLE_MECHANIC)) {
        // Mehāniķa statistika
        
        // Mani ikdienas uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? AND veids = 'Ikdienas'");
        $stmt->execute([$currentUser['id']]);
        $stats['my_total_tasks'] = $stmt->fetchColumn();
        
        // Mani aktīvie ikdienas uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? AND veids = 'Ikdienas' AND statuss IN ('Jauns', 'Procesā')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_active_tasks'] = $stmt->fetchColumn();
        
        // Mani pabeigto ikdienas uzdevumi šomēnes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE piešķirts_id = ? AND veids = 'Ikdienas' AND statuss = 'Pabeigts' 
            AND MONTH(beigu_laiks) = MONTH(NOW()) 
            AND YEAR(beigu_laiks) = YEAR(NOW())
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_completed_this_month'] = $stmt->fetchColumn();
        
        // Mani regulārie uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? AND veids = 'Regulārais'");
        $stmt->execute([$currentUser['id']]);
        $stats['my_total_regular_tasks'] = $stmt->fetchColumn();
        
        // Mani aktīvie regulārie uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? AND veids = 'Regulārais' AND statuss IN ('Jauns', 'Procesā')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_active_regular_tasks'] = $stmt->fetchColumn();
        
        // Nokavētie uzdevumi (VISI - gan ikdienas, gan regulārie)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE piešķirts_id = ? 
            AND ((jabeidz_lidz IS NOT NULL AND jabeidz_lidz < NOW() AND statuss NOT IN ('Pabeigts', 'Atcelts'))
                 OR (statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), izveidots) > 3))
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_overdue_tasks'] = $stmt->fetchColumn();
        
        // Mani jaunākie ikdienas uzdevumi ar darba statusu
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
                   CASE 
                       WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 
                       WHEN u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 3 THEN 1
                       ELSE 0 
                   END as ir_nokavets
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            WHERE u.piešķirts_id = ? AND u.veids = 'Ikdienas'
            ORDER BY ir_nokavets DESC, u.prioritate DESC, u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $my_latest_tasks = $stmt->fetchAll();
        
        // Mani jaunākie regulārie uzdevumi ar darba statusu
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   r.periodicitate,
                   (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
                   CASE 
                       WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 
                       WHEN u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 1 THEN 1
                       ELSE 0 
                   END as ir_nokavets
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
            WHERE u.piešķirts_id = ? AND u.veids = 'Regulārais'
            ORDER BY ir_nokavets DESC, u.prioritate DESC, u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $my_latest_regular_tasks = $stmt->fetchAll();
        
    } elseif (hasRole(ROLE_OPERATOR)) {
        // Operatora statistika
        
        // Manas ziņotās problēmas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ?");
        $stmt->execute([$currentUser['id']]);
        $stats['my_total_problems'] = $stmt->fetchColumn();
        
        // Manas neatrisinātas problēmas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ? AND statuss IN ('Jauna', 'Apskatīta')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_pending_problems'] = $stmt->fetchColumn();
        
        // Manas atrisinātas problēmas šomēnes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM problemas 
            WHERE zinotajs_id = ? AND statuss = 'Pārvērsta uzdevumā' 
            AND MONTH(atjaunots) = MONTH(NOW()) 
            AND YEAR(atjaunots) = YEAR(NOW())
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_resolved_this_month'] = $stmt->fetchColumn();
        
        // Kritiskās problēmas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ? AND prioritate = 'Kritiska' AND statuss IN ('Jauna', 'Apskatīta')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_critical_problems'] = $stmt->fetchColumn();
        
        // Manas jaunākās problēmas
        $stmt = $pdo->prepare("
            SELECT p.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            LEFT JOIN iekartas i ON p.iekartas_id = i.id
            WHERE p.zinotajs_id = ?
            ORDER BY p.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $my_latest_problems = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Kļūda iegūstot statistiku: " . $e->getMessage());
}

include 'includes/header.php';
?>

<!-- Statistikas kartes -->
<div class="stats-grid">
    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_tasks'] ?? 0; ?></div>
            <div class="stat-label">Ikdienas uzdevumi</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active_tasks'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie uzdevumi</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['completed_this_month'] ?? 0; ?></div>
            <div class="stat-label">Pabeigti šomēnes</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--danger-color);">
            <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['critical_tasks'] ?? 0; ?></div>
            <div class="stat-label">Kritiskie uzdevumi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--warning-color);">
            <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['new_problems'] ?? 0; ?></div>
            <div class="stat-label">Jaunas problēmas</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success-color);">
            <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['active_mechanics'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie mehāniķi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--info-color);">
            <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['active_regular_templates'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie regulārie šabloni</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary-color);">
            <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['todays_regular_tasks'] ?? 0; ?></div>
            <div class="stat-label">Šodienas regulārie uzdevumi</div>
        </div>
        
    <?php elseif (hasRole(ROLE_MECHANIC)): ?>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['my_total_tasks'] ?? 0; ?></div>
            <div class="stat-label">Mani ikdienas uzdevumi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--warning-color);">
            <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['my_active_tasks'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie ikdienas</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success-color);">
            <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['my_completed_this_month'] ?? 0; ?></div>
            <div class="stat-label">Pabeigti šomēnes</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--info-color);">
            <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['my_total_regular_tasks'] ?? 0; ?></div>
            <div class="stat-label">Mani regulārie uzdevumi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--primary-color);">
            <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['my_active_regular_tasks'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie regulārie</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--danger-color);">
            <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['my_overdue_tasks'] ?? 0; ?></div>
            <div class="stat-label">Nokavētie uzdevumi</div>
        </div>
        
    <?php elseif (hasRole(ROLE_OPERATOR)): ?>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['my_total_problems'] ?? 0; ?></div>
            <div class="stat-label">Kopā ziņotās problēmas</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--warning-color);">
            <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['my_pending_problems'] ?? 0; ?></div>
            <div class="stat-label">Neatrisinātas problēmas</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success-color);">
            <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['my_resolved_this_month'] ?? 0; ?></div>
            <div class="stat-label">Atrisinātas šomēnes</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--danger-color);">
            <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['my_critical_problems'] ?? 0; ?></div>
            <div class="stat-label">Kritiskās problēmas</div>
        </div>
    <?php endif; ?>
</div>

<!-- Ātras darbības -->
<div class="card">
    <div class="card-header">
        <h3>Ātras darbības</h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="create_task.php" class="btn btn-primary">Izveidot uzdevumu</a>
                <a href="tasks.php" class="btn btn-secondary">Skatīt visus uzdevumus</a>
                <a href="regular_tasks.php" class="btn btn-info">Regulārie uzdevumi</a>
                <a href="problems.php" class="btn btn-warning">Skatīt problēmas</a>
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <a href="reports.php" class="btn btn-success">Atskaites</a>
                    <a href="users.php" class="btn btn-outline-primary">Pārvaldīt lietotājus</a>
                <?php endif; ?>
            <?php elseif (hasRole(ROLE_MECHANIC)): ?>
                <a href="my_tasks.php" class="btn btn-primary">Mani ikdienas uzdevumi</a>
                <a href="regular_tasks_mechanic.php" class="btn btn-info">Regulārie uzdevumi</a>
                <a href="completed_tasks.php" class="btn btn-success">Pabeigto uzdevumu vēsture</a>
            <?php elseif (hasRole(ROLE_OPERATOR)): ?>
                <a href="report_problem.php" class="btn btn-danger">Ziņot problēmu</a>
                <a href="my_problems.php" class="btn btn-secondary">Manas problēmas</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Jaunākie ieraksti -->
<div class="row">
    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <!-- Jaunākie ikdienas uzdevumi -->
        <?php if (!empty($latest_tasks)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Jaunākie ikdienas uzdevumi</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nosaukums</th>
                                        <th>Mehāniķis</th>
                                        <th>Prioritāte</th>
                                        <th>Statuss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_tasks as $task): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['mehaniķa_vards'] ?? ''); ?></td>
                                            <td>
                                                <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                                    <?php echo $task['prioritate']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                    <?php echo $task['statuss']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="tasks.php" class="btn btn-sm btn-primary">Skatīt visus uzdevumus</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Jaunākie regulārie uzdevumi -->
        <?php if (!empty($latest_regular_tasks)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Jaunākie regulārie uzdevumi</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nosaukums</th>
                                        <th>Mehāniķis</th>
                                        <th>Periodicitāte</th>
                                        <th>Statuss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_regular_tasks as $task): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['mehaniķa_vards'] ?? ''); ?></td>
                                            <td>
                                                <small class="text-muted"><?php echo $task['periodicitate']; ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                    <?php echo $task['statuss']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="regular_tasks.php" class="btn btn-sm btn-info">Skatīt regulāros uzdevumus</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Jaunākās problēmas -->
        <?php if (!empty($latest_problems)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Jaunākās problēmas</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nosaukums</th>
                                        <th>Ziņotājs</th>
                                        <th>Prioritāte</th>
                                        <th>Izveidots</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_problems as $problem): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($problem['nosaukums']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($problem['zinotaja_vards']); ?></td>
                                            <td>
                                                <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                                                    <?php echo $problem['prioritate']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo formatDate($problem['izveidots']); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="problems.php" class="btn btn-sm btn-warning">Skatīt visas problēmas</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (hasRole(ROLE_MECHANIC)): ?>
        <!-- Mehāniķa ikdienas uzdevumi -->
        <?php if (!empty($my_latest_tasks)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Mani jaunākie ikdienas uzdevumi</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nosaukums</th>
                                        <th>Vieta</th>
                                        <th>Prioritāte</th>
                                        <th>Statuss</th>
                                        <th>Termiņš</th>
                                        <th>Darbības</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_latest_tasks as $task): ?>
                                        <tr class="<?php echo $task['ir_nokavets'] ? 'table-danger' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                                <?php if ($task['ir_nokavets']): ?>
                                                    <span class="badge badge-danger">NOKAVĒTS</span>
                                                <?php endif; ?>
                                                <?php if ($task['aktīvs_darbs']): ?>
                                                    <span class="badge badge-warning">PROCESĀ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></td>
                                            <td>
                                                <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                                    <?php echo $task['prioritate']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                    <?php echo $task['statuss']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($task['jabeidz_lidz']): ?>
                                                    <small class="<?php echo $task['ir_nokavets'] ? 'text-danger' : ''; ?>">
                                                        <?php echo formatDate($task['jabeidz_lidz']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($task['statuss'] == 'Jauns'): ?>
                                                    <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Sākt</button>
                                                <?php elseif ($task['statuss'] == 'Procesā'): ?>
                                                    <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="my_tasks.php" class="btn btn-sm btn-primary">Skatīt visus ikdienas uzdevumus</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Mehāniķa regulārie uzdevumi -->
        <?php if (!empty($my_latest_regular_tasks)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Mani jaunākie regulārie uzdevumi</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nosaukums</th>
                                        <th>Vieta</th>
                                        <th>Periodicitāte</th>
                                        <th>Statuss</th>
                                        <th>Darbības</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_latest_regular_tasks as $task): ?>
                                        <tr class="<?php echo $task['ir_nokavets'] ? 'table-danger' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                                <?php if ($task['ir_nokavets']): ?>
                                                    <span class="badge badge-danger">NOKAVĒTS</span>
                                                <?php endif; ?>
                                                <?php if ($task['aktīvs_darbs']): ?>
                                                    <span class="badge badge-warning">PROCESĀ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></td>
                                            <td>
                                                <small class="text-muted"><?php echo $task['periodicitate']; ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                    <?php echo $task['statuss']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($task['statuss'] == 'Jauns'): ?>
                                                    <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Sākt</button>
                                                <?php elseif ($task['statuss'] == 'Procesā'): ?>
                                                    <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="regular_tasks_mechanic.php" class="btn btn-sm btn-info">Skatīt visus regulāros uzdevumus</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (hasRole(ROLE_OPERATOR) && !empty($my_latest_problems)): ?>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Manas jaunākās problēmas</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Vieta</th>
                                    <th>Prioritāte</th>
                                    <th>Statuss</th>
                                    <th>Izveidots</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_latest_problems as $problem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($problem['nosaukums']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? ''); ?></td>
                                        <td>
                                            <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                                                <?php echo $problem['prioritate']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $problem['statuss'])); ?>">
                                                <?php echo $problem['statuss']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($problem['izveidots']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="my_problems.php" class="btn btn-sm btn-secondary">Skatīt visas manas problēmas</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Uzdevuma darba sākšana
function startTaskWork(taskId) {
    if (confirm('Vai vēlaties sākt darbu pie šī uzdevuma?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'start_work';
        
        const taskInput = document.createElement('input');
        taskInput.type = 'hidden';
        taskInput.name = 'task_id';
        taskInput.value = taskId;
        
        form.appendChild(actionInput);
        form.appendChild(taskInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Uzdevuma darba pabeigšana
function completeTaskWork(taskId) {
    if (confirm('Vai vēlaties pabeigt šo uzdevumu?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'complete_task';
        
        const taskInput = document.createElement('input');
        taskInput.type = 'hidden';
        taskInput.name = 'task_id';
        taskInput.value = taskId;
        
        form.appendChild(actionInput);
        form.appendChild(taskInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Vecā changeTaskStatus funkcija - tiek atstāta saderības dēļ, bet nu tā nedara neko
function changeTaskStatus(taskId, newStatus) {
    console.log('Izmantojiet startTaskWork() vai completeTaskWork() funkcijas');
}
</script>

<style>
.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
}

.col-md-6 {
    flex: 1;
    min-width: 300px;
}

.col-md-12 {
    flex: 1;
    width: 100%;
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 11px;
    color: white;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-danger {
    background: var(--danger-color);
}

.badge-warning {
    background: var(--warning-color);
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>