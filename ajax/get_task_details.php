<?php
require_once '../config.php';

// Pārbaudīt atļaujas
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$currentUser = getCurrentUser();
$task_id = intval($_GET['id'] ?? 0);

if ($task_id <= 0) {
    http_response_code(400);
    exit('Invalid task ID');
}

try {
    // Iegūt uzdevuma detaļas
    $stmt = $pdo->prepare("
        SELECT u.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               k.nosaukums as kategorijas_nosaukums,
               CASE 
                   WHEN u.piešķirts_id IS NOT NULL THEN CONCAT(l.vards, ' ', l.uzvards)
                   ELSE (
                       SELECT GROUP_CONCAT(CONCAT(lm.vards, ' ', lm.uzvards) SEPARATOR ', ')
                       FROM uzdevumu_piešķīrumi up
                       JOIN lietotaji lm ON up.mehāniķa_id = lm.id
                       WHERE up.uzdevuma_id = u.id AND up.statuss != 'Noņemts'
                   )
               END as mehaniķa_vards,
               CONCAT(e.vards, ' ', e.uzvards) as izveidoja_vards,
               p.nosaukums as problemas_nosaukums
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        LEFT JOIN problemas p ON u.problemas_id = p.id
        WHERE u.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        http_response_code(404);
        exit('Task not found');
    }
    
    // Pārbaudīt atļaujas - vai ir administrators/menedžeris vai piešķirts mehāniķis
    $hasAccess = hasRole([ROLE_ADMIN, ROLE_MANAGER]);
    
    // Ja ir mehāniķis, pārbaudīt vai uzdevums ir piešķirts viņam
    if (!$hasAccess && hasRole(ROLE_MECHANIC)) {
        if ($task['piešķirts_id'] == $currentUser['id']) {
            $hasAccess = true;
        } else {
            // Pārbaudīt vai ir piešķirts caur daudziem mehāniķiem
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM uzdevumu_piešķīrumi 
                WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
            ");
            $stmt->execute([$task_id, $currentUser['id']]);
            if ($stmt->fetchColumn() > 0) {
                $hasAccess = true;
            }
        }
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Iegūt uzdevuma failus
    $stmt = $pdo->prepare("
        SELECT originalais_nosaukums, saglabatais_nosaukums, faila_cels, faila_tips, faila_izmers
        FROM faili 
        WHERE tips = 'Uzdevums' AND saistitas_id = ?
        ORDER BY augšupielādēts DESC
    ");
    $stmt->execute([$task_id]);
    $files = $stmt->fetchAll();
    
    // Iegūt uzdevuma vēsturi
    $stmt = $pdo->prepare("
        SELECT uv.*, CONCAT(l.vards, ' ', l.uzvards) as mainītāja_vards
        FROM uzdevumu_vesture uv
        LEFT JOIN lietotaji l ON uv.mainīja_id = l.id
        WHERE uv.uzdevuma_id = ?
        ORDER BY uv.mainīts DESC
    ");
    $stmt->execute([$task_id]);
    $history = $stmt->fetchAll();
    
    // Iegūt darba laiku
    $stmt = $pdo->prepare("
        SELECT dl.*, CONCAT(l.vards, ' ', l.uzvards) as darbinieka_vards
        FROM darba_laiks dl
        LEFT JOIN lietotaji l ON dl.lietotaja_id = l.id
        WHERE dl.uzdevuma_id = ?
        ORDER BY dl.sakuma_laiks DESC
    ");
    $stmt->execute([$task_id]);
    $work_time = $stmt->fetchAll();
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
?>

<div class="task-details">
    <div class="row">
        <div class="col-md-8">
            <h4><?php echo htmlspecialchars($task['nosaukums']); ?></h4>
            
            <div class="task-meta mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Statuss:</strong> 
                        <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                            <?php echo $task['statuss']; ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Prioritāte:</strong> 
                        <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                            <?php echo $task['prioritate']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Veids:</strong> <?php echo htmlspecialchars($task['veids']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Kategorija:</strong> <?php echo htmlspecialchars($task['kategorijas_nosaukums'] ?? 'Nav norādīta'); ?>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Vieta:</strong> <?php echo htmlspecialchars($task['vietas_nosaukums'] ?? 'Nav norādīta'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Iekārta:</strong> <?php echo htmlspecialchars($task['iekartas_nosaukums'] ?? 'Nav norādīta'); ?>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Piešķirts:</strong> <?php echo htmlspecialchars($task['mehaniķa_vards'] ?? 'Nav piešķirts'); ?>
                        <?php if ($task['daudziem_mehāniķiem']): ?>
                            <br><small class="text-info">👥 Grupas darbs</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Izveidoja:</strong> <?php echo htmlspecialchars($task['izveidoja_vards']); ?>
                    </div>
                </div></div>
                
                <?php if ($task['daudziem_mehāniķiem']): ?>
                    <?php
                    // Iegūt detalizēto informāciju par piešķīrumiem
                    $stmt = $pdo->prepare("
                        SELECT up.*, CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards, up.statuss as piešķīruma_statuss
                        FROM uzdevumu_piešķīrumi up
                        JOIN lietotaji l ON up.mehāniķa_id = l.id
                        WHERE up.uzdevuma_id = ? AND up.statuss != 'Noņemts'
                        ORDER BY l.vards, l.uzvards
                    ");
                    $stmt->execute([$task_id]);
                    $assignments = $stmt->fetchAll();
                    ?>
                    
                    <?php if (!empty($assignments)): ?>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <strong>Iesaistītie mehāniķi:</strong>
                                <div class="mt-1">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <span class="badge <?php echo $assignment['piešķīruma_statuss'] === 'Pabeigts' ? 'badge-success' : ($assignment['piešķīruma_statuss'] === 'Sākts' ? 'badge-warning' : 'badge-secondary'); ?>" style="margin-right: 5px; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($assignment['mehaniķa_vards']); ?>
                                            <small>(<?php echo $assignment['piešķīruma_statuss']; ?>)</small>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="task-description mb-3">
                <strong>Apraksts:</strong>
                <div class="mt-2 p-3" style="background: var(--gray-100); border-radius: var(--border-radius);">
                    <?php echo nl2br(htmlspecialchars($task['apraksts'])); ?>
                </div>
            </div>
            
            <?php if ($task['problemas_nosaukums']): ?>
                <div class="alert alert-info">
                    <strong>Izveidots no problēmas:</strong> <?php echo htmlspecialchars($task['problemas_nosaukums']); ?>
                </div>
            <?php endif; ?>
            
        </div>
        
        <div class="col-md-4">
            <div class="task-timeline">
                <h5>Laika informācija</h5>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Izveidots:</strong></td>
                        <td><?php echo formatDate($task['izveidots']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sākuma datums:</strong></td>
                        <td><?php echo formatDate($task['sakuma_datums']); ?></td>
                    </tr>
                    <?php if ($task['jabeidz_lidz']): ?>
                    <tr>
                        <td><strong>Jābeidz līdz:</strong></td>
                        <td class="<?php echo strtotime($task['jabeidz_lidz']) < time() && $task['statuss'] != 'Pabeigts' ? 'text-danger' : ''; ?>">
                            <?php echo formatDate($task['jabeidz_lidz']); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($task['sakuma_laiks']): ?>
                    <tr>
                        <td><strong>Darbs sākts:</strong></td>
                        <td><?php echo formatDate($task['sakuma_laiks']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($task['beigu_laiks']): ?>
                    <tr>
                        <td><strong>Darbs pabeigts:</strong></td>
                        <td><?php echo formatDate($task['beigu_laiks']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($task['paredzamais_ilgums']): ?>
                    <tr>
                        <td><strong>Paredzamais ilgums:</strong></td>
                        <td><?php echo $task['paredzamais_ilgums']; ?> h</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($task['faktiskais_ilgums']): ?>
                    <tr>
                        <td><strong>Faktiskais ilgums:</strong></td>
                        <td><?php echo $task['faktiskais_ilgums']; ?> h</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($task['atbildes_komentars']): ?>
        <div class="mt-3">
            <h5>Mehāniķa komentārs</h5>
            <div class="p-3" style="background: var(--gray-100); border-radius: var(--border-radius);">
                <?php echo nl2br(htmlspecialchars($task['atbildes_komentars'])); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($files)): ?>
        <div class="mt-3">
            <h5>Pievienotie faili</h5>
            <div class="files-list">
                <?php foreach ($files as $file): ?>
                    <div class="file-item d-flex justify-content-between align-items-center p-2" style="border: 1px solid var(--gray-300); border-radius: var(--border-radius); margin-bottom: 5px;">
                        <div>
                            <strong><?php echo htmlspecialchars($file['originalais_nosaukums']); ?></strong>
                            <small class="text-muted">(<?php echo round($file['faila_izmers'] / 1024, 1); ?> KB)</small>
                        </div>
                        <div>
                            <a href="<?php echo htmlspecialchars($file['faila_cels']); ?>" target="_blank" class="btn btn-sm btn-primary">Lejupielādēt</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($work_time)): ?>
        <div class="mt-3">
            <h5>Darba laiks</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Darbinieks</th>
                            <th>Sākums</th>
                            <th>Beigas</th>
                            <th>Ilgums</th>
                            <th>Komentārs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($work_time as $wt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($wt['darbinieka_vards']); ?></td>
                                <td><?php echo formatDate($wt['sakuma_laiks']); ?></td>
                                <td><?php echo $wt['beigu_laiks'] ? formatDate($wt['beigu_laiks']) : 'Procesā'; ?></td>
                                <td><?php echo $wt['stundu_skaits'] ? $wt['stundu_skaits'] . ' h' : '-'; ?></td>
                                <td><?php echo htmlspecialchars($wt['komentars'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($history)): ?>
        <div class="mt-3">
            <h5>Uzdevuma vēsture</h5>
            <div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>
                                    <?php if ($h['iepriekšējais_statuss']): ?>
                                        Statuss mainīts no "<?php echo htmlspecialchars($h['iepriekšējais_statuss']); ?>" uz "<?php echo htmlspecialchars($h['jaunais_statuss']); ?>"
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($h['jaunais_statuss']); ?>
                                    <?php endif; ?>
                                </strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($h['mainītāja_vards']); ?> • <?php echo formatDate($h['mainīts']); ?>
                                </small>
                                <?php if ($h['komentars']): ?>
                                    <div class="mt-1">
                                        <em><?php echo htmlspecialchars($h['komentars']); ?></em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.task-details .row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.task-details .col-md-4 {
    flex: 1;
    min-width: 200px;
}

.task-details .col-md-6 {
    flex: 1;
    min-width: 250px;
}

.task-details .col-md-8 {
    flex: 2;
    min-width: 300px;
}

.task-details .col-md-12 {
    width: 100%;
}

.file-item {
    background: var(--white);
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    border-radius: 4px;
    color: white;
    font-weight: normal;
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-secondary {
    background-color: #6c757d;
}

@media (max-width: 768px) {
    .task-details .row {
        flex-direction: column;
    }
    
    .task-details .col-md-4,
    .task-details .col-md-6,
    .task-details .col-md-8,
    .task-details .col-md-12 {
        width: 100%;
        flex: none;
    }
}
</style>