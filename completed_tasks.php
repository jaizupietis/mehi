<?php
// completed_tasks.php - labots filtru kods
session_start();
require_once 'config.php';

// Pārbaude vai lietotājs ir pieteicies un ir mehāniķis
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header('Location: login.php');
    exit;
}

// Filtru parametru iegūšana
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$completed_from = isset($_GET['completed_from']) ? $_GET['completed_from'] : '';
$completed_to = isset($_GET['completed_to']) ? $_GET['completed_to'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$task_type_filter = isset($_GET['task_type']) ? $_GET['task_type'] : '';

// SQL vaicājuma veidošana
$sql = "SELECT t.*, l.name as location_name,
               CASE 
                   WHEN t.type = 'daily' THEN 'Ikdienas'
                   WHEN t.type = 'regular' THEN 'Regulārais'
                   ELSE t.type 
               END as type_display,
               CASE 
                   WHEN t.priority = 'critical' THEN 'Kritiska'
                   WHEN t.priority = 'high' THEN 'Augsta'
                   WHEN t.priority = 'medium' THEN 'Vidēja'
                   WHEN t.priority = 'low' THEN 'Zema'
                   ELSE t.priority 
               END as priority_display
        FROM tasks t 
        LEFT JOIN locations l ON t.location_id = l.id 
        WHERE t.assigned_to = ? AND t.status = 'completed'";

$params = [$_SESSION['user_id']];
$param_types = "i";

// Pievienot meklēšanas filtru
if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

// Pievienot datuma filtrus
if (!empty($completed_from)) {
    $sql .= " AND DATE(t.completed_at) >= ?";
    $params[] = $completed_from;
    $param_types .= "s";
}

if (!empty($completed_to)) {
    $sql .= " AND DATE(t.completed_at) <= ?";
    $params[] = $completed_to;
    $param_types .= "s";
}

// Pievienot prioritātes filtru
if (!empty($priority_filter) && $priority_filter !== 'all') {
    $sql .= " AND t.priority = ?";
    $params[] = $priority_filter;
    $param_types .= "s";
}

// Pievienot uzdevuma veida filtru
if (!empty($task_type_filter) && $task_type_filter !== 'all') {
    $sql .= " AND t.type = ?";
    $params[] = $task_type_filter;
    $param_types .= "s";
}

$sql .= " ORDER BY t.completed_at DESC";

// Vaicājuma izpilde
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pabeiktie uzdevumi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Pabeiktie uzdevumi</h2>
        
        <!-- Filtru forma -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <!-- Meklēšana -->
                <div class="col-md-3">
                    <label class="form-label">Meklēt</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Meklēt uzdevumos...">
                </div>
                
                <!-- Pabeigts no -->
                <div class="col-md-2">
                    <label class="form-label">Pabeigts no</label>
                    <input type="date" class="form-control" name="completed_from" 
                           value="<?php echo htmlspecialchars($completed_from); ?>">
                </div>
                
                <!-- Pabeigts līdz -->
                <div class="col-md-2">
                    <label class="form-label">Pabeigts līdz</label>
                    <input type="date" class="form-control" name="completed_to" 
                           value="<?php echo htmlspecialchars($completed_to); ?>">
                </div>
                
                <!-- Prioritāte -->
                <div class="col-md-2">
                    <label class="form-label">Prioritāte</label>
                    <select name="priority" class="form-control">
                        <option value="">Visas prioritātes</option>
                        <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Kritiska</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>Augsta</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Vidēja</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Zema</option>
                    </select>
                </div>
                
                <!-- Uzdevuma veids -->
                <div class="col-md-2">
                    <label class="form-label">Uzdevuma veids</label>
                    <select name="task_type" class="form-control">
                        <option value="">Visi veidi</option>
                        <option value="daily" <?php echo $task_type_filter === 'daily' ? 'selected' : ''; ?>>Ikdienas</option>
                        <option value="regular" <?php echo $task_type_filter === 'regular' ? 'selected' : ''; ?>>Regulārais</option>
                    </select>
                </div>
                
                <!-- Pogas -->
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm">Filtrēt</button>
                        <a href="completed_tasks.php" class="btn btn-secondary btn-sm">Notīrīt</a>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Rezultātu skaits -->
        <div class="mb-3">
            <small class="text-muted">Atrasti: <?php echo $result->num_rows; ?> uzdevumi</small>
        </div>
        
        <!-- Uzdevumu tabula -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nosaukums</th>
                        <th>Veids</th>
                        <th>Prioritāte</th>
                        <th>Vieta</th>
                        <th>Pabeigts</th>
                        <th>Pavadītais laiks</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($task = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td><?php echo htmlspecialchars($task['type_display']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($task['priority']) {
                                            'critical' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info',
                                            'low' => 'secondary',
                                            default => 'secondary'
                                        }; 
                                    ?>">
                                        <?php echo htmlspecialchars($task['priority_display']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($task['location_name'] ?? 'Nav norādīta'); ?></td>
                                <td>
                                    <?php 
                                        if ($task['completed_at']) {
                                            echo date('d.m.Y H:i', strtotime($task['completed_at']));
                                        } else {
                                            echo 'Nav norādīts';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        if ($task['started_at'] && $task['completed_at']) {
                                            $start = new DateTime($task['started_at']);
                                            $end = new DateTime($task['completed_at']);
                                            $interval = $start->diff($end);
                                            
                                            $time_spent = '';
                                            if ($interval->d > 0) $time_spent .= $interval->d . 'd ';
                                            if ($interval->h > 0) $time_spent .= $interval->h . 'h ';
                                            if ($interval->i > 0) $time_spent .= $interval->i . 'min';
                                            
                                            echo $time_spent ?: '< 1min';
                                        } else {
                                            echo 'Nav aprēķināts';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">Skatīt</a>
                                    <?php if (!empty($task['report'])): ?>
                                        <a href="view_report.php?task_id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">Atskaite</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nav atrasti pabeigti uzdevumi ar norādītajiem kritērijiem</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginācijas vieta, ja nepieciešama -->
        <?php if ($result->num_rows > 50): ?>
        <nav aria-label="Lappušu navigācija">
            <ul class="pagination justify-content-center">
                <!-- Paginācijas kods, ja nepieciešams -->
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript filtru automātiskai darbībai -->
    <script>
        // Inicializācija kad lapa ielādējusies
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const searchInput = document.querySelector('input[name="meklēt"]');
            
            // Event listeners filtru elementiem - automātiska forma iesniegšana
            document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(element => {
                element.addEventListener('change', function() {
                    form.submit();
                });
            });
            
            // Meklēšanas lauka debounce (ar aizkavi 500ms)
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        form.submit();
                    }, 500);
                });
            }
            
            // Filtru poga
            const filterButton = form.querySelector('button[type="submit"]');
            if (filterButton) {
                filterButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    form.submit();
                });
            }
        });

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

        // Kārtošanas funkcija
        function sortBy(column, direction) {
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', direction);
            
            // Saglabāt esošos filtrus
            const form = document.getElementById('filterForm');
            if (form) {
                const formData = new FormData(form);
                for (let [key, value] of formData.entries()) {
                    if (value && key !== 'sort' && key !== 'order') {
                        url.searchParams.set(key, value);
                    }
                }
            }
            window.location = url;
        }

        // Filtru notīrīšana
        function clearFilters() {
            window.location.href = 'completed_tasks.php';
        }
    </script>
</body>
</html>