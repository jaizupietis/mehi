<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pageTitle = 'Darba maiņu grafiks';
$pageHeader = 'Mehāniķu darba maiņu pārvaldība';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_schedule') {
        $schedule_data = $_POST['schedule'] ?? [];
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';

        if (empty($date_from) || empty($date_to)) {
            $errors[] = "Datuma periods ir obligāts.";
        } else {
            try {
                $pdo->beginTransaction();

                // Dzēst esošos ierakstus periodā
                $stmt = $pdo->prepare("DELETE FROM darba_grafiks WHERE datums BETWEEN ? AND ?");
                $stmt->execute([$date_from, $date_to]);

                // Saglabāt jauno grafiku
                $saved_count = 0;
                foreach ($schedule_data as $date => $mechanics) {
                    foreach ($mechanics as $mechanic_id => $shifts) {
                        foreach ($shifts as $shift) {
                            if (!empty($shift)) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO darba_grafiks (lietotaja_id, datums, maina, izveidoja_id)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([$mechanic_id, $date, $shift, $currentUser['id']]);
                                $saved_count++;
                            }
                        }
                    }
                }

                $pdo->commit();
                setFlashMessage('success', "Darba grafiks saglabāts! Ieraksti: $saved_count");

            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Kļūda saglabājot grafiku: " . $e->getMessage();
            }
        }
    }

    if ($action === 'copy_week') {
        $source_date = $_POST['source_date'] ?? '';
        $target_date = $_POST['target_date'] ?? '';

        if (empty($source_date) || empty($target_date)) {
            $errors[] = "Abas nedēļas datumi ir obligāti.";
        } else {
            try {
                // Aprēķināt nedēļas diapazonu
                $source_monday = date('Y-m-d', strtotime('monday this week', strtotime($source_date)));
                $source_sunday = date('Y-m-d', strtotime('sunday this week', strtotime($source_date)));
                $target_monday = date('Y-m-d', strtotime('monday this week', strtotime($target_date)));

                // Iegūt avota nedēļas grafiku
                $stmt = $pdo->prepare("
                    SELECT lietotaja_id, DAYOFWEEK(datums) as day_of_week, maina 
                    FROM darba_grafiks 
                    WHERE datums BETWEEN ? AND ?
                ");
                $stmt->execute([$source_monday, $source_sunday]);
                $source_schedule = $stmt->fetchAll();

                if (empty($source_schedule)) {
                    $errors[] = "Avota nedēļā nav atrasts darba grafiks.";
                } else {
                    $pdo->beginTransaction();

                    $copied_count = 0;
                    foreach ($source_schedule as $entry) {
                        // Aprēķināt mērķa datumu
                        $day_offset = $entry['day_of_week'] - 2; // MySQL DAYOFWEEK: 1=Sunday, 2=Monday
                        if ($day_offset < 0) $day_offset = 6; // Sunday becomes 6

                        $target_date_full = date('Y-m-d', strtotime($target_monday . " +$day_offset days"));

                        // Pārbaudīt vai jau eksistē
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM darba_grafiks 
                            WHERE lietotaja_id = ? AND datums = ? AND maina = ?
                        ");
                        $stmt->execute([$entry['lietotaja_id'], $target_date_full, $entry['maina']]);

                        if ($stmt->fetchColumn() == 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO darba_grafiks (lietotaja_id, datums, maina, izveidoja_id)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$entry['lietotaja_id'], $target_date_full, $entry['maina'], $currentUser['id']]);
                            $copied_count++;
                        }
                    }

                    $pdo->commit();
                    setFlashMessage('success', "Nedēļas grafiks nokopēts! Ieraksti: $copied_count");
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Kļūda kopējot grafiku: " . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_period') {
        $delete_from = $_POST['delete_from'] ?? '';
        $delete_to = $_POST['delete_to'] ?? '';

        if (empty($delete_from) || empty($delete_to)) {
            $errors[] = "Dzēšanas periods ir obligāts.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM darba_grafiks WHERE datums BETWEEN ? AND ?");
                $stmt->execute([$delete_from, $delete_to]);
                $deleted_count = $stmt->rowCount();

                setFlashMessage('success', "Dzēsti $deleted_count ieraksti no perioda.");

            } catch (PDOException $e) {
                $errors[] = "Kļūda dzēšot grafiku: " . $e->getMessage();
            }
        }
    }
}

// Iegūt mehāniķus
try {
    $stmt = $pdo->query("
        SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
        FROM lietotaji 
        WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' 
        ORDER BY vards, uzvards
    ");
    $mechanics = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot mehāniķus: " . $e->getMessage();
    $mechanics = [];
}

// Iegūt pašreizējo nedēļu
$current_monday = date('Y-m-d', strtotime('monday this week'));
$display_date = $_GET['date'] ?? $current_monday;
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($display_date)));

// Iegūt nedēļas grafiku
$schedule = [];
try {
    $stmt = $pdo->prepare("
        SELECT g.*, CONCAT(l.vards, ' ', l.uzvards) as mehaniķis,
               DAYNAME(g.datums) as dienas_nosaukums
        FROM darba_grafiks g
        JOIN lietotaji l ON g.lietotaja_id = l.id
        WHERE g.datums BETWEEN ? AND ?
        ORDER BY g.datums, l.vards, l.uzvards, g.maina
    ");
    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
    $stmt->execute([$week_start, $week_end]);
    $schedule_data = $stmt->fetchAll();

    // Organizēt datus pēc datuma un mehāniķa
    foreach ($schedule_data as $entry) {
        $schedule[$entry['datums']][$entry['lietotaja_id']][] = $entry['maina'];
    }
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot grafiku: " . $e->getMessage();
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Navigācijas josla -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="?date=<?php echo date('Y-m-d', strtotime($week_start . ' -7 days')); ?>" class="btn btn-outline-primary">
            ← Iepriekšējā nedēļa
        </a>
        <span class="mx-3">
            <strong>
                <?php 
                echo date('d.m.Y', strtotime($week_start)) . ' - ' . 
                     date('d.m.Y', strtotime($week_start . ' +6 days')); 
                ?>
            </strong>
        </span>
        <a href="?date=<?php echo date('Y-m-d', strtotime($week_start . ' +7 days')); ?>" class="btn btn-outline-primary">
            Nākamā nedēļa →
        </a>
    </div>
    <div>
        <button onclick="openModal('bulkActionsModal')" class="btn btn-secondary">Masveida darbības</button>
        <button onclick="openModal('scheduleModal')" class="btn btn-success">Rediģēt grafiku</button>
    </div>
</div>

<!-- Maiņu apzīmējumi -->
<div class="alert alert-info">
    <h5>Maiņu apzīmējumi:</h5>
    <div class="d-flex gap-4">
        <span><strong>R:</strong> Rīta maiņa (07:00 - 16:00)</span>
        <span><strong>V:</strong> Vakara maiņa (16:00 - 01:00)</span>
        <span><strong>B:</strong> Brīvdiena</span>
    </div>
</div>

<!-- Nedēļas grafiks -->
<div class="card">
    <div class="card-header">
        <h4>Darba maiņu grafiks</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table schedule-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Mehāniķis</th>
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <?php 
                            $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
                            $day_name = ['Pirmdiena', 'Otrdiena', 'Trešdiena', 'Ceturtdiena', 'Piektdiena', 'Sestdiena', 'Svētdiena'][$i];
                            $is_weekend = $i >= 5;
                            ?>
                            <th class="text-center <?php echo $is_weekend ? 'weekend-header' : ''; ?>">
                                <div><?php echo $day_name; ?></div>
                                <small><?php echo date('d.m.Y', strtotime($current_date)); ?></small>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mechanics)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Nav aktīvu mehāniķu</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mechanics as $mechanic): ?>
                            <tr>
                                <td class="mechanic-name">
                                    <strong><?php echo htmlspecialchars($mechanic['pilns_vards']); ?></strong>
                                </td>
                                <?php for ($i = 0; $i < 7; $i++): ?>
                                    <?php 
                                    $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
                                    $shifts = $schedule[$current_date][$mechanic['id']] ?? [];
                                    $is_weekend = $i >= 5;
                                    ?>
                                    <td class="text-center schedule-cell <?php echo $is_weekend ? 'weekend-cell' : ''; ?>">
                                        <?php if (empty($shifts)): ?>
                                            <span class="no-shift">-</span>
                                        <?php else: ?>
                                            <?php foreach ($shifts as $shift): ?>
                                                <span class="shift-badge shift-<?php echo strtolower($shift); ?>">
                                                    <?php echo $shift; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Statistika -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Nedēļas statistika</h5>
            </div>
            <div class="card-body">
                <?php
                $stats = ['R' => 0, 'V' => 0, 'B' => 0, 'total_mechanics' => count($mechanics)];
                foreach ($schedule as $date => $day_schedule) {
                    foreach ($day_schedule as $mechanic_id => $shifts) {
                        foreach ($shifts as $shift) {
                            if (isset($stats[$shift])) {
                                $stats[$shift]++;
                            }
                        }
                    }
                }
                ?>
                <div class="stats-row">
                    <span>Rīta maiņas (R): <strong><?php echo $stats['R']; ?></strong></span>
                    <span>Vakara maiņas (V): <strong><?php echo $stats['V']; ?></strong></span>
                    <span>Brīvdienas (B): <strong><?php echo $stats['B']; ?></strong></span>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        Kopā mehāniķu: <?php echo $stats['total_mechanics']; ?> | 
                        Kopā maiņu: <?php echo $stats['R'] + $stats['V']; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Ātrās darbības</h5>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?date=<?php echo $current_monday; ?>" class="btn btn-outline-primary btn-sm">
                        Šodienas nedēļas
                    </a>
                    <button onclick="printSchedule()" class="btn btn-outline-secondary btn-sm">
                        Drukāt grafiku
                    </button>
                    <button onclick="exportSchedule()" class="btn btn-outline-info btn-sm">
                        Eksportēt CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafika rediģēšanas modāls -->
<div id="scheduleModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="modal-header">
            <h3 class="modal-title">Rediģēt darba grafiku</h3>
            <button onclick="closeModal('scheduleModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="scheduleForm">
                <input type="hidden" name="action" value="save_schedule">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="date_from" class="form-label">Datums no</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?php echo $week_start; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="date_to" class="form-label">Datums līdz</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?php echo date('Y-m-d', strtotime($week_start . ' +6 days')); ?>" required>
                    </div>
                </div>

                <div class="schedule-editor">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mehāniķis</th>
                                <?php for ($i = 0; $i < 7; $i++): ?>
                                    <?php 
                                    $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
                                    $day_name = ['P', 'O', 'T', 'C', 'Pk', 'S', 'Sv'][$i];
                                    ?>
                                    <th class="text-center">
                                        <?php echo $day_name; ?>
                                        <br><small><?php echo date('d.m.Y', strtotime($current_date)); ?></small>
                                    </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($mechanic['pilns_vards']); ?></strong></td>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <?php 
                                        $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
                                        $current_shifts = $schedule[$current_date][$mechanic['id']] ?? [];
                                        ?>
                                        <td class="text-center">
                                            <div class="shift-selector">
                                                <label>
                                                    <input type="checkbox" 
                                                           name="schedule[<?php echo $current_date; ?>][<?php echo $mechanic['id']; ?>][]" 
                                                           value="R" 
                                                           <?php echo in_array('R', $current_shifts) ? 'checked' : ''; ?>>
                                                    R
                                                </label>
                                                <label>
                                                    <input type="checkbox" 
                                                           name="schedule[<?php echo $current_date; ?>][<?php echo $mechanic['id']; ?>][]" 
                                                           value="V" 
                                                           <?php echo in_array('V', $current_shifts) ? 'checked' : ''; ?>>
                                                    V
                                                </label>
                                                <label>
                                                    <input type="checkbox" 
                                                           name="schedule[<?php echo $current_date; ?>][<?php echo $mechanic['id']; ?>][]" 
                                                           value="B" 
                                                           <?php echo in_array('B', $current_shifts) ? 'checked' : ''; ?>>
                                                    B
                                                </label>
                                            </div>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('scheduleModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="document.getElementById('scheduleForm').submit()" class="btn btn-success">Saglabāt grafiku</button>
        </div>
    </div>
</div>

<!-- Masveida darbību modāls -->
<div id="bulkActionsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Masveida darbības</h3>
            <button onclick="closeModal('bulkActionsModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bulk-action-section">
                <h5>Kopēt nedēļas grafiku</h5>
                <form method="POST" id="copyWeekForm">
                    <input type="hidden" name="action" value="copy_week">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="source_date" class="form-label">No nedēļas (jebkurš datums)</label>
                            <input type="date" id="source_date" name="source_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="target_date" class="form-label">Uz nedēļu (jebkurš datums)</label>
                            <input type="date" id="target_date" name="target_date" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-2">Kopēt grafiku</button>
                </form>
            </div>

            <hr>

            <div class="bulk-action-section">
                <h5>Dzēst perioda grafiku</h5>
                <form method="POST" id="deletePeriodForm" onsubmit="return confirm('Vai tiešām vēlaties dzēst grafiku šajā periodā?');">
                    <input type="hidden" name="action" value="delete_period">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="delete_from" class="form-label">No datuma</label>
                            <input type="date" id="delete_from" name="delete_from" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="delete_to" class="form-label">Līdz datumam</label>
                            <input type="date" id="delete_to" name="delete_to" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger mt-2">Dzēst grafiku</button>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('bulkActionsModal')" class="btn btn-secondary">Aizvērt</button>
        </div>
    </div>
</div>

<script>
// Ātrās darbības
function printSchedule() {
    window.print();
}

function exportSchedule() {
    const startDate = '<?php echo $week_start; ?>';
    window.location.href = `export_schedule.php?start_date=${startDate}&type=csv`;
}

// Maiņu validācija
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.shift-selector input[type="checkbox"]');

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const parent = this.closest('.shift-selector');
            const rCheckbox = parent.querySelector('input[value="R"]');
            const vCheckbox = parent.querySelector('input[value="V"]');
            const bCheckbox = parent.querySelector('input[value="B"]');

            // Ja izvēlas brīvdienu, noņemt citas maiņas
            if (this.value === 'B' && this.checked) {
                rCheckbox.checked = false;
                vCheckbox.checked = false;
            }

            // Ja izvēlas maiņu, noņemt brīvdienu
            if ((this.value === 'R' || this.value === 'V') && this.checked) {
                bCheckbox.checked = false;
            }
        });
    });
});
</script>

<style>
.schedule-table th,
.schedule-table td {
    padding: 8px;
    border: 1px solid var(--gray-300);
    vertical-align: middle;
}

.weekend-header {
    background-color: #f8f9fa;
    color: #6c757d;
}

.weekend-cell {
    background-color: #fafafa;
}

.mechanic-name {
    background-color: var(--gray-100);
    font-weight: bold;
}

.schedule-cell {
    min-height: 50px;
    position: relative;
}

.shift-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    margin: 1px;
}

.shift-r {
    background-color: #28a745;
    color: white;
}

.shift-v {
    background-color: #fd7e14;
    color: white;
}

.shift-b {
    background-color: #6c757d;
    color: white;
}

.no-shift {
    color: var(--gray-400);
    font-size: 18px;
}

.shift-selector {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.shift-selector label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    padding: 2px;
    font-size: 12px;
}

.shift-selector input[type="checkbox"] {
    margin: 0;
}

.stats-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.bulk-action-section {
    margin-bottom: 20px;
}

.bulk-action-section h5 {
    margin-bottom: 15px;
    color: var(--primary-color);
}

@media (max-width: 768px) {
    .schedule-table {
        font-size: 12px;
    }

    .schedule-table th,
    .schedule-table td {
        padding: 4px;
    }

    .shift-badge {
        font-size: 10px;
        padding: 1px 3px;
    }
}

@media print {
    .btn,
    .modal,
    .alert {
        display: none !important;
    }

    .schedule-table {
        width: 100%;
        border-collapse: collapse;
    }

    .schedule-table th,
    .schedule-table td {
        border: 1px solid #000;
        padding: 5px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>