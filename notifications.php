<?php
require_once 'config.php';

// Pārbaudīt pieslēgšanos
requireLogin();

$pageTitle = 'Paziņojumi';
$pageHeader = 'Paziņojumi';

$currentUser = getCurrentUser();
$errors = [];

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read' && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE pazinojumi 
                SET skatīts = 1 
                WHERE id = ? AND lietotaja_id = ?
            ");
            $stmt->execute([$notification_id, $currentUser['id']]);
        } catch (PDOException $e) {
            $errors[] = "Kļūda atzīmējot paziņojumu: " . $e->getMessage();
        }
    }
    
    if ($action === 'mark_all_read') {
        try {
            $stmt = $pdo->prepare("
                UPDATE pazinojumi 
                SET skatīts = 1 
                WHERE lietotaja_id = ? AND skatīts = 0
            ");
            $stmt->execute([$currentUser['id']]);
            setFlashMessage('success', 'Visi paziņojumi atzīmēti kā lasīti!');
        } catch (PDOException $e) {
            $errors[] = "Kļūda atzīmējot paziņojumus: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete_notification' && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        
        try {
            $stmt = $pdo->prepare("
                DELETE FROM pazinojumi 
                WHERE id = ? AND lietotaja_id = ?
            ");
            $stmt->execute([$notification_id, $currentUser['id']]);
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot paziņojumu: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete_all_read') {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM pazinojumi 
                WHERE lietotaja_id = ? AND skatīts = 1
            ");
            $stmt->execute([$currentUser['id']]);
            setFlashMessage('success', 'Visi lasītie paziņojumi dzēsti!');
        } catch (PDOException $e) {
            $errors[] = "Kļūda dzēšot paziņojumus: " . $e->getMessage();
        }
    }
}

// Filtrēšanas parametri
$filters = [
    'tips' => sanitizeInput($_GET['tips'] ?? ''),
    'skatīts' => sanitizeInput($_GET['skatīts'] ?? ''),
];

// Kārtošanas parametri
$sort = sanitizeInput($_GET['sort'] ?? 'izveidots');
$order = sanitizeInput($_GET['order'] ?? 'DESC');

if (!in_array($sort, ['izveidots', 'tips', 'skatīts'])) {
    $sort = 'izveidots';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Lapošana
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Būvēt vaicājumu
    $where_conditions = ["lietotaja_id = ?"];
    $params = [$currentUser['id']];
    
    if (!empty($filters['tips'])) {
        $where_conditions[] = "tips = ?";
        $params[] = $filters['tips'];
    }
    
    if ($filters['skatīts'] !== '') {
        $where_conditions[] = "skatīts = ?";
        $params[] = intval($filters['skatīts']);
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Galvenais vaicājums
    $sql = "
        SELECT * FROM pazinojumi 
        $where_clause
        ORDER BY $sort $order
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pazinojumi = $stmt->fetchAll();
    
    // Iegūt kopējo ierakstu skaitu
    $count_sql = "SELECT COUNT(*) FROM pazinojumi $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Iegūt statistiku
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā,
            SUM(CASE WHEN skatīts = 0 THEN 1 ELSE 0 END) as nelasīti,
            SUM(CASE WHEN skatīts = 1 THEN 1 ELSE 0 END) as lasīti
        FROM pazinojumi 
        WHERE lietotaja_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot paziņojumus: " . $e->getMessage();
    $pazinojumi = [];
    $stats = ['kopā' => 0, 'nelasīti' => 0, 'lasīti' => 0];
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
            <label for="tips" class="form-label">Tips</label>
            <select id="tips" name="tips" class="form-control">
                <option value="">Visi tipi</option>
                <option value="Jauns uzdevums" <?php echo $filters['tips'] === 'Jauns uzdevums' ? 'selected' : ''; ?>>Jauns uzdevums</option>
                <option value="Jauna problēma" <?php echo $filters['tips'] === 'Jauna problēma' ? 'selected' : ''; ?>>Jauna problēma</option>
                <option value="Statusa maiņa" <?php echo $filters['tips'] === 'Statusa maiņa' ? 'selected' : ''; ?>>Statusa maiņa</option>
                <option value="Sistēmas" <?php echo $filters['tips'] === 'Sistēmas' ? 'selected' : ''; ?>>Sistēmas</option>
            </select>
        </div>
        
        <div class="filter-col">
            <label for="skatīts" class="form-label">Statuss</label>
            <select id="skatīts" name="skatīts" class="form-control">
                <option value="">Visi</option>
                <option value="0" <?php echo $filters['skatīts'] === '0' ? 'selected' : ''; ?>>Nelasīti</option>
                <option value="1" <?php echo $filters['skatīts'] === '1' ? 'selected' : ''; ?>>Lasīti</option>
            </select>
        </div>
        
        <div class="filter-col" style="display: flex; gap: 0.5rem; align-items: end;">
            <button type="submit" class="btn btn-primary">Filtrēt</button>
            <button type="button" onclick="clearFilters()" class="btn btn-secondary">Notīrīt</button>
        </div>
    </form>
</div>

<!-- Statistika un darbības -->
<div class="notifications-header mb-3">
    <div class="stats-summary">
        <span class="stat-item">Kopā: <?php echo $stats['kopā']; ?></span>
        <span class="stat-item unread">Nelasīti: <?php echo $stats['nelasīti']; ?></span>
        <span class="stat-item read">Lasīti: <?php echo $stats['lasīti']; ?></span>
    </div>
    
    <div class="actions">
        <?php if ($stats['nelasīti'] > 0): ?>
            <button onclick="markAllRead()" class="btn btn-sm btn-success">Atzīmēt visus kā lasītus</button>
        <?php endif; ?>
        <?php if ($stats['lasīti'] > 0): ?>
            <button onclick="confirmAction('Vai tiešām vēlaties dzēst visus lasītos paziņojumus?', deleteAllRead)" class="btn btn-sm btn-danger">Dzēst lasītos</button>
        <?php endif; ?>
    </div>
</div>

<!-- Paziņojumu saraksts -->
<div class="notifications-list">
    <?php if (empty($pazinojumi)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h4>Nav paziņojumu</h4>
                <p>Jums pašlaik nav paziņojumu.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pazinojumi as $pazinojums): ?>
            <div class="notification-card <?php echo !$pazinojums['skatīts'] ? 'unread' : 'read'; ?>">
                <div class="notification-header">
                    <div class="notification-meta">
                        <span class="notification-type type-<?php echo strtolower(str_replace(' ', '-', $pazinojums['tips'])); ?>">
                            <?php echo getNotificationIcon($pazinojums['tips']); ?>
                            <?php echo htmlspecialchars($pazinojums['tips']); ?>
                        </span>
                        <span class="notification-time">
                            <?php echo formatDate($pazinojums['izveidots']); ?>
                        </span>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if (!$pazinojums['skatīts']): ?>
                            <button onclick="markAsRead(<?php echo $pazinojums['id']; ?>)" class="btn btn-sm btn-outline-success" title="Atzīmēt kā lasītu">✓</button>
                        <?php endif; ?>
                        <button onclick="deleteNotification(<?php echo $pazinojums['id']; ?>)" class="btn btn-sm btn-outline-danger" title="Dzēst">🗑</button>
                    </div>
                </div>
                
                <div class="notification-content">
                    <h5 class="notification-title"><?php echo htmlspecialchars($pazinojums['virsraksts']); ?></h5>
                    <p class="notification-message"><?php echo nl2br(htmlspecialchars($pazinojums['zinojums'])); ?></p>
                    
                    <?php if ($pazinojums['saistitas_tips'] && $pazinojums['saistitas_id']): ?>
                        <div class="notification-link">
                            <?php
                            $linkText = '';
                            $linkUrl = '';
                            
                            if ($pazinojums['saistitas_tips'] === 'Uzdevums') {
                                $linkText = 'Skatīt uzdevumu';
                                if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
                                    $linkUrl = 'tasks.php';
                                } elseif (hasRole(ROLE_MECHANIC)) {
                                    $linkUrl = 'my_tasks.php';
                                }
                            } elseif ($pazinojums['saistitas_tips'] === 'Problēma') {
                                $linkText = 'Skatīt problēmu';
                                if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
                                    $linkUrl = 'problems.php';
                                } elseif (hasRole(ROLE_OPERATOR)) {
                                    $linkUrl = 'my_problems.php';
                                }
                            }
                            ?>
                            
                            <?php if ($linkUrl): ?>
                                <a href="<?php echo $linkUrl; ?>" class="btn btn-sm btn-primary">
                                    <?php echo $linkText; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Lapošana -->
<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Iepriekšējā</a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Nākamā &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Atzīmēt kā lasītu
function markAsRead(notificationId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'mark_read';
    
    const notificationInput = document.createElement('input');
    notificationInput.type = 'hidden';
    notificationInput.name = 'notification_id';
    notificationInput.value = notificationId;
    
    form.appendChild(actionInput);
    form.appendChild(notificationInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Dzēst paziņojumu
function deleteNotification(notificationId) {
    if (confirm('Vai tiešām vēlaties dzēst šo paziņojumu?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_notification';
        
        const notificationInput = document.createElement('input');
        notificationInput.type = 'hidden';
        notificationInput.name = 'notification_id';
        notificationInput.value = notificationId;
        
        form.appendChild(actionInput);
        form.appendChild(notificationInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Atzīmēt visus kā lasītus
function markAllRead() {
    if (confirm('Vai tiešām vēlaties atzīmēt visus paziņojumus kā lasītus?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'mark_all_read';
        
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Dzēst visus lasītos
function deleteAllRead() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_all_read';
    
    form.appendChild(actionInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Filtru automātiska iesniegšana
document.querySelectorAll('#filterForm select').forEach(element => {
    element.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Auto-refresh paziņojumu skaitītāja
setInterval(function() {
    // Šis tiks realizēts ar AJAX
}, 30000);
</script>

<style>
/* Paziņojumu stili */
.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    background: var(--white);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
}

.stats-summary {
    display: flex;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.stat-item {
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius);
    font-weight: 500;
    font-size: var(--font-size-sm);
}

.stat-item.unread {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
}

.stat-item.read {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success-color);
}

.actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.notification-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    transition: all 0.3s ease;
    border-left: 4px solid var(--gray-400);
}

.notification-card.unread {
    border-left-color: var(--secondary-color);
    background: linear-gradient(135deg, var(--white) 0%, rgba(52, 152, 219, 0.02) 100%);
}

.notification-card.read {
    opacity: 0.8;
}

.notification-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--gray-100);
    border-bottom: 1px solid var(--gray-300);
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    flex-wrap: wrap;
}

.notification-type {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
}

.notification-type.type-jauns-uzdevums {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success-color);
}

.notification-type.type-jauna-problema {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
}

.notification-type.type-statusa-maina {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning-color);
}

.notification-type.type-sistemas {
    background: rgba(52, 152, 219, 0.1);
    color: var(--info-color);
}

.notification-time {
    color: var(--gray-600);
    font-size: var(--font-size-sm);
}

.notification-actions {
    display: flex;
    gap: var(--spacing-xs);
}

.notification-content {
    padding: var(--spacing-lg);
}

.notification-title {
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.notification-message {
    margin: 0 0 var(--spacing-md) 0;
    color: var(--gray-700);
    line-height: 1.5;
}

.notification-link {
    border-top: 1px solid var(--gray-300);
    padding-top: var(--spacing-md);
}

/* Responsive dizains */
@media (max-width: 768px) {
    .notifications-header {
        flex-direction: column;
        gap: var(--spacing-md);
        align-items: stretch;
    }
    
    .stats-summary {
        justify-content: center;
    }
    
    .actions {
        justify-content: center;
    }
    
    .notification-header {
        flex-direction: column;
        gap: var(--spacing-sm);
        align-items: stretch;
    }
    
    .notification-meta {
        justify-content: space-between;
    }
    
    .notification-actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .notification-content {
        padding: var(--spacing-md);
    }
    
    .notification-title {
        font-size: 1rem;
    }
    
    .stats-summary {
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .actions {
        flex-direction: column;
    }
}
</style>

<?php
// Palīgfunkcija paziņojumu ikonām
function getNotificationIcon($type) {
    switch ($type) {
        case 'Jauns uzdevums':
            return '📋';
        case 'Jauna problēma':
            return '⚠️';
        case 'Statusa maiņa':
            return '🔄';
        case 'Sistēmas':
            return 'ℹ️';
        default:
            return '📢';
    }
}

include 'includes/footer.php';
?>