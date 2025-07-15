<?php
require_once 'config.php';

$pageTitle = 'Sākums';
$pageHeader = 'Sveicināti AVOTI Task Management sistēmā';
$currentUser = getCurrentUser();

// Iegūt statistiku atkarībā no lietotāja lomas
$stats = [];

try {
    if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
        // Administratora un menedžera statistika
        
        // Kopējie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi");
        $stats['total_tasks'] = $stmt->fetchColumn();
        
        // Aktīvie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE statuss IN ('Jauns', 'Procesā')");
        $stats['active_tasks'] = $stmt->fetchColumn();
        
        // Pabeigto uzdevumu šomēnes
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE statuss = 'Pabeigts' 
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
        
        // Jaunākie uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_tasks = $stmt->fetchAll();
        
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
        
        // Mani uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ?");
        $stmt->execute([$currentUser['id']]);
        $stats['my_total_tasks'] = $stmt->fetchColumn();
        
        // Mani aktīvie uzdevumi
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzdevumi WHERE piešķirts_id = ? AND statuss IN ('Jauns', 'Procesā')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_active_tasks'] = $stmt->fetchColumn();
        
        // Mani pabeigto uzdevumi šomēnes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE piešķirts_id = ? AND statuss = 'Pabeigts' 
            AND MONTH(beigu_laiks) = MONTH(NOW()) 
            AND YEAR(beigu_laiks) = YEAR(NOW())
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_completed_this_month'] = $stmt->fetchColumn();
        
        // Nokavētie uzdevumi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi 
            WHERE piešķirts_id = ? AND statuss IN ('Jauns', 'Procesā') 
            AND jabeidz_lidz < NOW()
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_overdue_tasks'] = $stmt->fetchColumn();
        
        // Mani jaunākie uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            WHERE u.piešķirts_id = ?
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $my_latest_tasks = $stmt->fetchAll();
        
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
            <div class="stat-label">Kopā uzdevumi</div>
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
        
    <?php elseif (hasRole(ROLE_MECHANIC)): ?>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['my_total_tasks'] ?? 0; ?></div>
            <div class="stat-label">Kopā mani uzdevumi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--warning-color);">
            <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['my_active_tasks'] ?? 0; ?></div>
            <div class="stat-label">Aktīvie uzdevumi</div>
        </div>
        
        <div class="stat-card" style="border-left-color: var(--success-color);">
            <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['my_completed_this_month'] ?? 0; ?></div>
            <div class="stat-label">Pabeigti šomēnes</div>
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
                <a href="problems.php" class="btn btn-warning">Skatīt problēmas</a>
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <a href="reports.php" class="btn btn-info">Atskaites</a>
                    <a href="users.php" class="btn btn-success">Pārvaldīt lietotājus</a>
                <?php endif; ?>
            <?php elseif (hasRole(ROLE_MECHANIC)): ?>
                <a href="my_tasks.php" class="btn btn-primary">Mani uzdevumi</a>
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
    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER]) && !empty($latest_tasks)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Jaunākie uzdevumi</h3>
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
    
    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER]) && !empty($latest_problems)): ?>
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
    
    <?php if (hasRole(ROLE_MECHANIC) && !empty($my_latest_tasks)): ?>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Mani jaunākie uzdevumi</h3>
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
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
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
                                                <small class="<?php echo strtotime($task['jabeidz_lidz']) < time() && $task['statuss'] != 'Pabeigts' ? 'text-danger' : ''; ?>">
                                                    <?php echo formatDate($task['jabeidz_lidz']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($task['statuss'] == 'Jauns'): ?>
                                                <button onclick="changeTaskStatus(<?php echo $task['id']; ?>, 'Procesā')" class="btn btn-sm btn-warning">Sākt</button>
                                            <?php elseif ($task['statuss'] == 'Procesā'): ?>
                                                <button onclick="changeTaskStatus(<?php echo $task['id']; ?>, 'Pabeigts')" class="btn btn-sm btn-success">Pabeigt</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="my_tasks.php" class="btn btn-sm btn-primary">Skatīt visus manus uzdevumus</a>
                </div>
            </div>
        </div>
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

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>