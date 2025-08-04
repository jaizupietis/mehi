<?php
require_once '../config.php';

// Pārbaudīt atļaujas
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$currentUser = getCurrentUser();
$problem_id = intval($_GET['problem_id'] ?? 0);

if ($problem_id <= 0) {
    http_response_code(400);
    exit('Invalid problem ID');
}

try {
    // Pārbaudīt vai lietotājs var skatīt šo problēmu
    $stmt = $pdo->prepare("SELECT zinotajs_id FROM problemas WHERE id = ?");
    $stmt->execute([$problem_id]);
    $problem = $stmt->fetch();
    
    if (!$problem) {
        http_response_code(404);
        exit('Problem not found');
    }
    
    // Pārbaudīt atļaujas
    if (!hasRole([ROLE_ADMIN, ROLE_MANAGER]) && 
        $problem['zinotajs_id'] != $currentUser['id']) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Iegūt saistītos uzdevumus
    $stmt = $pdo->prepare("
        SELECT u.id, u.nosaukums, u.statuss, u.prioritate, u.izveidots, u.jabeidz_lidz,
               u.sakuma_laiks, u.beigu_laiks, u.faktiskais_ilgums,
               CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards,
               CONCAT(e.vards, ' ', e.uzvards) as izveidoja_vards,
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums
        FROM uzdevumi u
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        WHERE u.problemas_id = ?
        ORDER BY u.izveidots DESC
    ");
    $stmt->execute([$problem_id]);
    $tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
?>

<div class="related-tasks">
    <?php if (empty($tasks)): ?>
        <div class="alert alert-info">
            <p>Šai problēmai vēl nav izveidoti uzdevumi.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uzdevums</th>
                        <th>Mehāniķis</th>
                        <th>Prioritāte</th>
                        <th>Statuss</th>
                        <th>Progress</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?>
                                        <?php if ($task['iekartas_nosaukums']): ?>
                                            - <?php echo htmlspecialchars($task['iekartas_nosaukums']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($task['mehaniķa_vards']); ?>
                                <br>
                                <small class="text-muted">Izveidoja: <?php echo htmlspecialchars($task['izveidoja_vards']); ?></small>
                            </td>
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
                                <div class="task-timeline">
                                    <small>
                                        <strong>Izveidots:</strong> <?php echo formatDate($task['izveidots']); ?>
                                    </small>
                                    
                                    <?php if ($task['jabeidz_lidz']): ?>
                                        <br><small>
                                            <strong>Termiņš:</strong> 
                                            <span class="<?php echo strtotime($task['jabeidz_lidz']) < time() && $task['statuss'] != 'Pabeigts' ? 'text-danger' : ''; ?>">
                                                <?php echo formatDate($task['jabeidz_lidz']); ?>
                                            </span>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['sakuma_laiks']): ?>
                                        <br><small>
                                            <strong>Sākts:</strong> <?php echo formatDate($task['sakuma_laiks']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['beigu_laiks']): ?>
                                        <br><small>
                                            <strong>Pabeigts:</strong> <?php echo formatDate($task['beigu_laiks']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['faktiskais_ilgums']): ?>
                                        <br><small>
                                            <strong>Ilgums:</strong> <?php echo $task['faktiskais_ilgums']; ?> h
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <button onclick="viewTaskFromModal(<?php echo $task['id']; ?>)" class="btn btn-sm btn-info">
                                    Skatīt detaļas
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <div class="alert alert-info">
                <strong>Kopā izveidoti uzdevumi:</strong> <?php echo count($tasks); ?>
                <br>
                <strong>Pabeigti:</strong> <?php echo count(array_filter($tasks, function($t) { return $t['statuss'] === 'Pabeigts'; })); ?>
                <br>
                <strong>Aktīvi:</strong> <?php echo count(array_filter($tasks, function($t) { return in_array($t['statuss'], ['Jauns', 'Procesā']); })); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Uzdevuma skatīšana no modāla loga
function viewTaskFromModal(taskId) {
    // Aizvērt pašreizējo modālo logu
    closeModal('relatedTasksModal');
    
    // Atvērt uzdevuma detaļu modālo logu
    setTimeout(() => {
        if (typeof viewTask === 'function') {
            viewTask(taskId);
        } else {
            // Ja funkcija nav pieejama, pāriet uz uzdevumu lapu
            window.open('tasks.php?task_id=' + taskId, '_blank');
        }
    }, 300);
}
</script>

<style>
.related-tasks .task-timeline {
    font-size: var(--font-size-sm);
    line-height: 1.4;
}

.related-tasks .task-timeline small {
    display: block;
    margin-bottom: 2px;
}

.related-tasks .priority-badge,
.related-tasks .status-badge {
    font-size: 11px;
    padding: 2px 6px;
}

.related-tasks .table td {
    vertical-align: top;
    padding: var(--spacing-sm);
}

.related-tasks .table th {
    background: var(--gray-200);
    font-weight: 600;
    padding: var(--spacing-sm);
}

@media (max-width: 768px) {
    .related-tasks .table {
        font-size: var(--font-size-sm);
    }
    
    .related-tasks .table td,
    .related-tasks .table th {
        padding: var(--spacing-xs);
    }
}
</style>