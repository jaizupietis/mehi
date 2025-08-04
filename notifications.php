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
                            unset($linkOnclick); // Notīrīt iepriekšējo vērtību
                            
                            if ($pazinojums['saistitas_tips'] === 'Uzdevums') {
                                $linkText = 'Skatīt uzdevumu';
                                if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
                                    $linkUrl = 'tasks.php?task_id=' . $pazinojums['saistitas_id'];
                                } elseif (hasRole(ROLE_MECHANIC)) {
                                    $linkUrl = '#';
                                    $linkOnclick = "viewTaskFromNotification(" . $pazinojums['saistitas_id'] . ", " . $pazinojums['id'] . ")";
                                }
                            } elseif ($pazinojums['saistitas_tips'] === 'Problēma') {
                                $linkText = 'Skatīt problēmu';
                                if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
                                    $linkUrl = 'problems.php?problem_id=' . $pazinojums['saistitas_id'];
                                } elseif (hasRole(ROLE_OPERATOR)) {
                                    $linkUrl = 'my_problems.php?problem_id=' . $pazinojums['saistitas_id'];
                                }
                            }
                            ?>
                            
                            <?php if ($linkUrl): ?>
                                <?php if (isset($linkOnclick)): ?>
                                    <button onclick="viewTaskFromNotification(<?php echo $pazinojums['saistitas_id']; ?>, <?php echo $pazinojums['id']; ?>)" class="btn btn-sm btn-primary">
                                        <?php echo $linkText; ?>
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo $linkUrl; ?>" onclick="markAsReadBeforeRedirect(<?php echo $pazinojums['id']; ?>)" class="btn btn-sm btn-primary">
                                        <?php echo $linkText; ?>
                                    </a>
                                <?php endif; ?>
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

<!-- Uzdevuma detaļu modāls -->
<div id="taskDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Uzdevuma detaļas</h3>
            <button onclick="closeModal('taskDetailsModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="taskDetailsContent">
            <!-- Saturs tiks ielādēts ar AJAX -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('taskDetailsModal')" class="btn btn-secondary">Aizvērt</button>
            <button id="goToTaskBtn" onclick="goToMyTasks()" class="btn btn-primary" style="display: none;">Iet uz Mani uzdevumi</button>
        </div>
    </div>
</div>

<script>
// Atvērt modālu
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Novērst fona skrollēšanu
        
        // Fokuss uz modālu (accessibility)
        modal.setAttribute('tabindex', '-1');
        modal.focus();
        
        // ESC taustiņa apstrāde
        document.addEventListener('keydown', handleModalEscape);
        
        // Klikšķis uz fona, lai aizvērtu
        modal.addEventListener('click', handleModalBackdropClick);
    }
}

// Aizvērt modālu
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        
        // Animācijas beigās paslēpt modālu
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Atjaunot fona skrollēšanu
            
            // Notīrīt event listeners
            document.removeEventListener('keydown', handleModalEscape);
            modal.removeEventListener('click', handleModalBackdropClick);
        }, 300);
    }
}

// ESC taustiņa apstrāde
function handleModalEscape(event) {
    if (event.key === 'Escape') {
        // Atrast atvērto modālu
        const openModal = document.querySelector('.modal.show');
        if (openModal) {
            closeModal(openModal.id);
        }
    }
}

// Klikšķis uz fona
function handleModalBackdropClick(event) {
    if (event.target === event.currentTarget) {
        closeModal(event.target.id);
    }
}

// Skatīt uzdevumu no paziņojuma (mehāniķiem) un automātiski atzīmēt kā lasītu
function viewTaskFromNotification(taskId, notificationId) {
    // Uzreiz atzīmēt paziņojumu kā lasītu
    if (notificationId) {
        markAsReadSilently(notificationId);
    }
    
    // Rādīt loading ziņojumu
    document.getElementById('taskDetailsContent').innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 18px; margin-bottom: 10px;">Ielādē uzdevuma detaļas...</div>
            <div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    
    // Atvērt modālu uzreiz ar loading
    openModal('taskDetailsModal');
    
    // Ielādēt uzdevuma detaļas
    fetch(`ajax/get_task_details.php?id=${taskId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('taskDetailsContent').innerHTML = html;
            document.getElementById('goToTaskBtn').style.display = 'inline-block';
            
            // Ja modāls nav redzams, atvērt to
            const modal = document.getElementById('taskDetailsModal');
            if (!modal.classList.contains('show')) {
                openModal('taskDetailsModal');
            }
        })
        .catch(error => {
            console.error('Kļūda:', error);
            document.getElementById('taskDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                    <h4>Kļūda ielādējot uzdevuma detaļas</h4>
                    <p>Lūdzu, mēģiniet vēlreiz vai sazinieties ar administratoru.</p>
                    <button onclick="viewTaskFromNotification(${taskId}, ${notificationId})" class="btn btn-primary" style="margin-top: 15px;">
                        Mēģināt vēlreiz
                    </button>
                </div>
            `;
        });
}

// Atzīmēt paziņojumu kā lasītu bez lapas pārlādēšanas
function markAsReadSilently(notificationId) {
    fetch('notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&notification_id=${notificationId}`
    })
    .then(response => {
        if (response.ok) {
            // Atjaunot UI - paziņojuma kartīti padarīt par lasītu
            updateNotificationVisually(notificationId, true);
            // Atjaunot statistiku
            updateNotificationStats(-1); // -1 nelasītais
        }
    })
    .catch(error => {
        console.error('Kļūda atzīmējot paziņojumu:', error);
    });
}

// Atzīmēt kā lasītu pirms redirect (administratoriem/operatoriem)
function markAsReadBeforeRedirect(notificationId) {
    // Atzīmēt kā lasītu
    markAsReadSilently(notificationId);
    
    // Īsa pauze, lai POST pieprasījums pagūtu izpildīties
    setTimeout(() => {
        // Redirect tiks veikts automātiski, jo onclick notiek uz <a> elementa
    }, 100);
}

// Atjaunot paziņojuma vizuālo stāvokli
function updateNotificationVisually(notificationId, isRead) {
    // Atrast paziņojuma kartīti
    const notificationCards = document.querySelectorAll('.notification-card');
    
    notificationCards.forEach(card => {
        // Pārbaudīt vai šī ir pareizā kartīte (pēc pogu onclick atribūtiem)
        const readButton = card.querySelector(`button[onclick*="markAsRead(${notificationId})"]`);
        const taskButton = card.querySelector(`button[onclick*="${notificationId}"]`);
        
        if (readButton || taskButton) {
            if (isRead) {
                // Pievienot īsu animāciju
                card.style.transition = 'all 0.5s ease';
                
                // Padarīt par lasītu
                card.classList.remove('unread');
                card.classList.add('read');
                
                // Noņemt "Atzīmēt kā lasītu" pogu
                if (readButton) {
                    readButton.style.opacity = '0';
                    setTimeout(() => {
                        readButton.remove();
                    }, 300);
                }
                
                // Parādīt īsu success feedback
                showMiniToast('✓ Paziņojums atzīmēts kā lasīts', 'success');
                
            } else {
                // Padarīt par nelasītu
                card.classList.remove('read');
                card.classList.add('unread');
            }
        }
    });
}

// Parādīt īsu toast paziņojumu
function showMiniToast(message, type = 'info') {
    // Izveidot toast elementu
    const toast = document.createElement('div');
    toast.className = `mini-toast mini-toast-${type}`;
    toast.textContent = message;
    
    // Pielāgot stilu atkarībā no ekrāna izmēra
    const isMobile = window.innerWidth <= 480;
    
    // Pievienot stilīgo
    toast.style.cssText = `
        position: fixed;
        ${isMobile ? 'top: 10px; left: 10px; right: 10px;' : 'top: 20px; right: 20px;'}
        background: ${type === 'success' ? '#27ae60' : '#3498db'};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: ${isMobile ? 'translateY(-100%)' : 'translateX(100%)'};
        transition: transform 0.3s ease;
        text-align: center;
    `;
    
    // Pievienot DOM
    document.body.appendChild(toast);
    
    // Animācija: iebraukt
    setTimeout(() => {
        toast.style.transform = isMobile ? 'translateY(0)' : 'translateX(0)';
    }, 10);
    
    // Animācija: izbraukt un dzēst
    setTimeout(() => {
        toast.style.transform = isMobile ? 'translateY(-100%)' : 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 2500);
}

// Atjaunot statistikas skaitļus
function updateNotificationStats(unreadChange) {
    const unreadStat = document.querySelector('.stat-item.unread');
    const readStat = document.querySelector('.stat-item.read');
    
    if (unreadStat && readStat) {
        // Iegūt pašreizējos skaitļus
        const unreadText = unreadStat.textContent;
        const readText = readStat.textContent;
        
        const currentUnread = parseInt(unreadText.match(/\d+/)[0]);
        const currentRead = parseInt(readText.match(/\d+/)[0]);
        
        // Atjaunot skaitļus
        const newUnread = Math.max(0, currentUnread + unreadChange);
        const newRead = currentRead - unreadChange;
        
        unreadStat.textContent = `Nelasīti: ${newUnread}`;
        readStat.textContent = `Lasīti: ${newRead}`;
        
        // Atjaunot pogu redzamību
        const markAllButton = document.querySelector('button[onclick="markAllRead()"]');
        const deleteReadButton = document.querySelector('button[onclick*="deleteAllRead"]');
        
        if (markAllButton) {
            markAllButton.style.display = newUnread === 0 ? 'none' : 'inline-block';
        }
        
        if (deleteReadButton) {
            deleteReadButton.style.display = newRead === 0 ? 'none' : 'inline-block';
        }
        
        // Ja nav ne nelasītu, ne lasītu, paslēpt visu actions bloku
        const actionsDiv = document.querySelector('.actions');
        if (actionsDiv && newUnread === 0 && newRead === 0) {
            actionsDiv.style.display = 'none';
        } else if (actionsDiv) {
            actionsDiv.style.display = 'flex';
        }
    }
}

// Iet uz "Mani uzdevumi" lapu
function goToMyTasks() {
    window.location.href = 'my_tasks.php';
}

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

// Palīgfunkcija apstiprinājuma dialogiem
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Filtru notīrīšana
function clearFilters() {
    window.location.href = 'notifications.php';
}

// Inicializēt modālu sistēmu, kad lapa ielādējas
document.addEventListener('DOMContentLoaded', function() {
    // Pievienot filtru automātisko iesniegšanu
    document.querySelectorAll('#filterForm select').forEach(element => {
        element.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    // Pārbaudīt vai eksistē nepieciešamie modāli
    const taskModal = document.getElementById('taskDetailsModal');
    
    if (!taskModal) {
        console.warn('Uzdevuma modāls nav atrasts - pārbaudiet HTML struktūru');
    }
    
    // Responsīvs modāla izmēru pielāgojums
    adjustModalSize();
});

// Responsīvs modāla izmēru pielāgojums
function adjustModalSize() {
    const modals = document.querySelectorAll('.modal-content');
    modals.forEach(modal => {
        const windowHeight = window.innerHeight;
        const windowWidth = window.innerWidth;
        
        // Mobilajām ierīcēm
        if (windowWidth <= 768) {
            modal.style.maxHeight = '95vh';
            modal.style.width = '95%';
        } else {
            modal.style.maxHeight = '90vh';
            modal.style.width = '90%';
        }
    });
}

// Pielāgot modāla izmērus, kad mainās loga izmērs
window.addEventListener('resize', adjustModalSize);

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
    transition: all 0.5s ease;
    border-left: 4px solid var(--gray-400);
}

.notification-card.unread {
    border-left-color: var(--secondary-color);
    background: linear-gradient(135deg, var(--white) 0%, rgba(52, 152, 219, 0.02) 100%);
    transform: scale(1.00);
}

.notification-card.read {
    opacity: 0.8;
    transform: scale(0.99);
    border-left-color: var(--success-color);
    background: linear-gradient(135deg, var(--white) 0%, rgba(39, 174, 96, 0.02) 100%);
}

.notification-card:hover {
    transform: translateY(-1px) scale(1.005);
    box-shadow: var(--shadow-lg);
}

.notification-card.read:hover {
    transform: translateY(-1px) scale(1.005);
    opacity: 0.9;
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

/* Uzlabotie modāla stili */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow-y: auto;
    padding: 20px 0;
}

.modal-content {
    background-color: var(--white);
    margin: auto;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    position: relative;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: var(--spacing-lg);
    background: var(--gray-100);
    border-bottom: 1px solid var(--gray-300);
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-body {
    padding: var(--spacing-lg);
    overflow-y: auto;
    flex-grow: 1;
    max-height: calc(90vh - 120px);
}

.modal-footer {
    padding: var(--spacing-lg);
    background: var(--gray-100);
    border-top: 1px solid var(--gray-300);
    border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-sm);
    flex-shrink: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    font-weight: bold;
    color: var(--gray-600);
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: var(--gray-300);
    color: var(--gray-800);
}

.modal-title {
    margin: 0;
    color: var(--gray-800);
    font-size: 1.25rem;
}

/* Animācijas */
.modal.show {
    display: block;
    animation: modalFadeIn 0.3s ease;
}

.modal.show .modal-content {
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes modalSlideIn {
    from { 
        transform: translateY(-50px);
        opacity: 0;
    }
    to { 
        transform: translateY(0);
        opacity: 1;
    }
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
    
    /* Modāla pielāgojumi */
    .modal {
        padding: 10px 0;
    }
    
    .modal-content {
        width: 95%;
        max-height: 95vh;
        margin: 10px auto;
    }
    
    .modal-header {
        padding: var(--spacing-md);
    }
    
    .modal-body {
        padding: var(--spacing-md);
        max-height: calc(95vh - 100px);
    }
    
    .modal-footer {
        padding: var(--spacing-md);
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    
    .modal-footer .btn {
        width: 100%;
    }
    
    .modal-title {
        font-size: 1.1rem;
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
    
    /* Modāla pielāgojumi mazām ierīcēm */
    .modal {
        padding: 5px 0;
    }
    
    .modal-content {
        width: 98%;
        max-height: 98vh;
        margin: 5px auto;
    }
    
    .modal-header {
        padding: 12px;
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .modal-body {
        padding: 12px;
        max-height: calc(98vh - 80px);
    }
    
    .modal-footer {
        padding: 12px;
    }
    
    /* Toast pielāgojumi mazām ierīcēm */
    .mini-toast {
        right: 10px !important;
        left: 10px !important;
        top: 10px !important;
        transform: translateY(-100%) !important;
        transition: transform 0.3s ease !important;
    }
    
    .mini-toast.show {
        transform: translateY(0) !important;
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