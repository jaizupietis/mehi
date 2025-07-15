<?php
require_once '../config.php';

// Pārbaudīt atļaujas
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$currentUser = getCurrentUser();
$problem_id = intval($_GET['id'] ?? 0);

if ($problem_id <= 0) {
    http_response_code(400);
    exit('Invalid problem ID');
}

try {
    // Iegūt problēmas detaļas
    $stmt = $pdo->prepare("
        SELECT p.*, 
               v.nosaukums as vietas_nosaukums,
               i.nosaukums as iekartas_nosaukums,
               CONCAT(l.vards, ' ', l.uzvards) as zinotaja_vards,
               CONCAT(a.vards, ' ', a.uzvards) as apstradasija_vards
        FROM problemas p
        LEFT JOIN vietas v ON p.vietas_id = v.id
        LEFT JOIN iekartas i ON p.iekartas_id = i.id
        LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
        LEFT JOIN lietotaji a ON p.apstradasija_id = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$problem_id]);
    $problem = $stmt->fetch();
    
    if (!$problem) {
        http_response_code(404);
        exit('Problem not found');
    }
    
    // Pārbaudīt atļaujas
    if (!hasRole([ROLE_ADMIN, ROLE_MANAGER]) && 
        !($problem['zinotajs_id'] == $currentUser['id'] && hasRole(ROLE_OPERATOR))) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // Iegūt problēmas failus
    $stmt = $pdo->prepare("
        SELECT originalais_nosaukums, saglabatais_nosaukums, faila_cels, faila_tips, faila_izmers
        FROM faili 
        WHERE tips = 'Problēma' AND saistitas_id = ?
        ORDER BY augšupielādēts DESC
    ");
    $stmt->execute([$problem_id]);
    $files = $stmt->fetchAll();
    
    // Iegūt saistītos uzdevumus
    $stmt = $pdo->prepare("
        SELECT u.id, u.nosaukums, u.statuss, u.prioritate,
               CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards
        FROM uzdevumi u
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        WHERE u.problemas_id = ?
        ORDER BY u.izveidots DESC
    ");
    $stmt->execute([$problem_id]);
    $related_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error');
}
?>

<div class="problem-details">
    <div class="row">
        <div class="col-md-8">
            <h4><?php echo htmlspecialchars($problem['nosaukums']); ?></h4>
            
            <div class="problem-meta mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Statuss:</strong> 
                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $problem['statuss'])); ?>">
                            <?php echo $problem['statuss']; ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Prioritāte:</strong> 
                        <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                            <?php echo $problem['prioritate']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Sarežģītības pakāpe:</strong> <?php echo htmlspecialchars($problem['sarezgitibas_pakape']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Aptuvenais ilgums:</strong> 
                        <?php echo $problem['aptuvenais_ilgums'] ? $problem['aptuvenais_ilgums'] . ' h' : 'Nav norādīts'; ?>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Vieta:</strong> <?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? 'Nav norādīta'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Iekārta:</strong> <?php echo htmlspecialchars($problem['iekartas_nosaukums'] ?? 'Nav norādīta'); ?>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Ziņotājs:</strong> <?php echo htmlspecialchars($problem['zinotaja_vards']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Apstrādāja:</strong> <?php echo htmlspecialchars($problem['apstradasija_vards'] ?? 'Nav apstrādāts'); ?>
                    </div>
                </div>
            </div>
            
            <div class="problem-description mb-3">
                <strong>Problēmas apraksts:</strong>
                <div class="mt-2 p-3" style="background: var(--gray-100); border-radius: var(--border-radius);">
                    <?php echo nl2br(htmlspecialchars($problem['apraksts'])); ?>
                </div>
            </div>
            
        </div>
        
        <div class="col-md-4">
            <div class="problem-timeline">
                <h5>Laika informācija</h5>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Ziņots:</strong></td>
                        <td><?php echo formatDate($problem['izveidots']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Atjaunots:</strong></td>
                        <td><?php echo formatDate($problem['atjaunots']); ?></td>
                    </tr>
                </table>
                
                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER]) && in_array($problem['statuss'], ['Jauna', 'Apskatīta'])): ?>
                    <div class="mt-3">
                        <h5>Darbības</h5>
                        <div class="d-flex flex-column gap-2">
                            <a href="create_task.php?from_problem=<?php echo $problem['id']; ?>" class="btn btn-success btn-sm">
                                Izveidot uzdevumu
                            </a>
                            <button onclick="editProblemStatus(<?php echo $problem['id']; ?>, '<?php echo $problem['statuss']; ?>')" class="btn btn-warning btn-sm">
                                Mainīt statusu
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($files)): ?>
        <div class="mt-3">
            <h5>Pievienotie attēli</h5>
            <div class="files-grid">
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <?php 
                        $extension = strtolower(pathinfo($file['originalais_nosaukums'], PATHINFO_EXTENSION));
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        
                        <?php if ($isImage): ?>
                            <div class="image-preview">
                                <img src="<?php echo htmlspecialchars($file['faila_cels']); ?>" 
                                     alt="<?php echo htmlspecialchars($file['originalais_nosaukums']); ?>"
                                     onclick="openImageModal('<?php echo htmlspecialchars($file['faila_cels']); ?>', '<?php echo htmlspecialchars($file['originalais_nosaukums']); ?>')">
                            </div>
                        <?php else: ?>
                            <div class="file-icon">
                                📄
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-info">
                            <strong><?php echo htmlspecialchars($file['originalais_nosaukums']); ?></strong>
                            <small class="text-muted d-block"><?php echo round($file['faila_izmers'] / 1024, 1); ?> KB</small>
                            <a href="<?php echo htmlspecialchars($file['faila_cels']); ?>" target="_blank" class="btn btn-sm btn-primary mt-1">
                                Lejupielādēt
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($related_tasks)): ?>
        <div class="mt-3">
            <h5>Saistītie uzdevumi</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Uzdevums</th>
                            <th>Mehāniķis</th>
                            <th>Prioritāte</th>
                            <th>Statuss</th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($related_tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['nosaukums']); ?></td>
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
                                <td>
                                    <button onclick="viewTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-info">Skatīt</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Attēla skatīšanas modāls -->
<div id="imageModal" class="modal" style="z-index: 2001;">
    <div class="modal-content" style="max-width: 90%; max-height: 90%;">
        <div class="modal-header">
            <h3 class="modal-title" id="imageModalTitle">Attēls</h3>
            <button onclick="closeModal('imageModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body text-center">
            <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('imageModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Attēla modāla atvēršana
function openImageModal(imageSrc, imageTitle) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModalTitle').textContent = imageTitle;
    openModal('imageModal');
}

// Uzdevuma skatīšana (ja funkcija nav definēta)
if (typeof viewTask !== 'function') {
    function viewTask(taskId) {
        // Atvērt jaunu logu vai pāriet uz uzdevuma lapu
        window.open('tasks.php?task_id=' + taskId, '_blank');
    }
}
</script>

<style>
.problem-details .row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.problem-details .col-md-4 {
    flex: 1;
    min-width: 200px;
}

.problem-details .col-md-6 {
    flex: 1;
    min-width: 250px;
}

.problem-details .col-md-8 {
    flex: 2;
    min-width: 300px;
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-top: var(--spacing-md);
}

.file-item {
    background: var(--white);
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    padding: var(--spacing-sm);
    text-align: center;
}

.image-preview {
    margin-bottom: var(--spacing-sm);
}

.image-preview img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.image-preview img:hover {
    transform: scale(1.05);
}

.file-icon {
    font-size: 3rem;
    margin-bottom: var(--spacing-sm);
    color: var(--gray-500);
}

.file-info {
    text-align: center;
}

.gap-2 {
    gap: 0.5rem;
}

/* Status badge stili */
.status-jauna {
    background: var(--info-color);
    color: var(--white);
}

.status-apskatīta {
    background: var(--warning-color);
    color: var(--white);
}

.status-pārvērsta-uzdevumā {
    background: var(--success-color);
    color: var(--white);
}

.status-atcelta {
    background: var(--gray-500);
    color: var(--white);
}

@media (max-width: 768px) {
    .problem-details .row {
        flex-direction: column;
    }
    
    .problem-details .col-md-4,
    .problem-details .col-md-6,
    .problem-details .col-md-8 {
        width: 100%;
        flex: none;
    }
    
    .files-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}

@media (max-width: 480px) {
    .files-grid {
        grid-template-columns: 1fr;
    }
}
</style>