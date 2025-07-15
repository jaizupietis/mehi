<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$pageTitle = 'Atskaites';
$pageHeader = 'Sistēmas atskaites un statistika';

$currentUser = getCurrentUser();
$errors = [];

// Datuma filtri
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Mēneša sākums
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Šodiena
$selected_mechanic = intval($_GET['mechanic_id'] ?? 0);
$selected_location = intval($_GET['location_id'] ?? 0);

try {
    // Iegūt mehāniķu sarakstu
    $stmt = $pdo->query("SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' ORDER BY vards, uzvards");
    $mehaniki = $stmt->fetchAll();
    
    // Iegūt vietu sarakstu
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();
    
    // Būvēt filtru nosacījumus
    $date_filter = "DATE(izveidots) BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
    
    $mechanic_filter = $selected_mechanic > 0 ? "AND piešķirts_id = ?" : "";
    $mechanic_params = $selected_mechanic > 0 ? [$selected_mechanic] : [];
    
    $location_filter = $selected_location > 0 ? "AND vietas_id = ?" : "";
    $location_params = $selected_location > 0 ? [$selected_location] : [];
    
    $all_params = array_merge($date_params, $mechanic_params, $location_params);
    
    // 1. Vispārīgā statistika
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_uzdevumi,
            SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
            SUM(CASE WHEN statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_uzdevumi,
            SUM(CASE WHEN statuss = 'Atcelts' THEN 1 ELSE 0 END) as atcelti_uzdevumi,
            SUM(CASE WHEN prioritate = 'Kritiska' THEN 1 ELSE 0 END) as kritiski_uzdevumi,
            AVG(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums END) as vidējais_ilgums,
            SUM(CASE WHEN jabeidz_lidz < NOW() AND statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavētie
        FROM uzdevumi 
        WHERE $date_filter $mechanic_filter $location_filter
    ");
    $stmt->execute($all_params);
    $vispārīgā_statistika = $stmt->fetch();
    
    // 2. Problēmu statistika
    $problem_date_params = $date_params;
    if ($selected_location > 0) {
        $problem_date_params[] = $selected_location;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_problēmas,
            SUM(CASE WHEN statuss = 'Jauna' THEN 1 ELSE 0 END) as jaunas_problēmas,
            SUM(CASE WHEN statuss = 'Apskatīta' THEN 1 ELSE 0 END) as apskatītas_problēmas,
            SUM(CASE WHEN statuss = 'Pārvērsta uzdevumā' THEN 1 ELSE 0 END) as pārvērstas_problēmas,
            SUM(CASE WHEN prioritate = 'Kritiska' THEN 1 ELSE 0 END) as kritiskās_problēmas
        FROM problemas 
        WHERE $date_filter " . ($selected_location > 0 ? "AND vietas_id = ?" : "")
    );
    $stmt->execute($problem_date_params);
    $problēmu_statistika = $stmt->fetch();
    
    // 3. Mehāniķu produktivitāte
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            CONCAT(l.vards, ' ', l.uzvards) as mehaniķis,
            COUNT(u.id) as uzdevumu_skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
            AVG(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums END) as vidējais_ilgums,
            SUM(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums ELSE 0 END) as kopējais_darba_laiks,
            SUM(CASE WHEN u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavētie
        FROM lietotaji l
        LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND $date_filter $location_filter
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
        " . ($selected_mechanic > 0 ? "AND l.id = ?" : "") . "
        GROUP BY l.id, l.vards, l.uzvards
        ORDER BY pabeigto_skaits DESC, uzdevumu_skaits DESC
    ");
    
    $productivity_params = array_merge($date_params, $location_params);
    if ($selected_mechanic > 0) {
        $productivity_params[] = $selected_mechanic;
    }
    $stmt->execute($productivity_params);
    $mehāniķu_produktivitāte = $stmt->fetchAll();
    
    // 4. Vietu statistika
    $stmt = $pdo->prepare("
        SELECT 
            v.nosaukums as vieta,
            COUNT(u.id) as uzdevumu_skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
            COUNT(p.id) as problēmu_skaits,
            SUM(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums ELSE 0 END) as kopējais_darba_laiks
        FROM vietas v
        LEFT JOIN uzdevumi u ON v.id = u.vietas_id AND $date_filter $mechanic_filter
        LEFT JOIN problemas p ON v.id = p.vietas_id AND DATE(p.izveidots) BETWEEN ? AND ?
        WHERE v.aktīvs = 1
        " . ($selected_location > 0 ? "AND v.id = ?" : "") . "
        GROUP BY v.id, v.nosaukums
        ORDER BY uzdevumu_skaits DESC
    ");
    
    $location_params_full = array_merge($all_params, $date_params);
    if ($selected_location > 0) {
        $location_params_full[] = $selected_location;
    }
    $stmt->execute($location_params_full);
    $vietu_statistika = $stmt->fetchAll();
    
    // 5. Prioritāšu sadalījums
    $stmt = $pdo->prepare("
        SELECT 
            prioritate,
            COUNT(*) as skaits,
            SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti,
            AVG(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums END) as vidējais_ilgums
        FROM uzdevumi 
        WHERE $date_filter $mechanic_filter $location_filter
        GROUP BY prioritate
        ORDER BY FIELD(prioritate, 'Kritiska', 'Augsta', 'Vidēja', 'Zema')
    ");
    $stmt->execute($all_params);
    $prioritāšu_sadalījums = $stmt->fetchAll();
    
    // 6. Uzdevumu kategoriju statistika
    $stmt = $pdo->prepare("
        SELECT 
            k.nosaukums as kategorija,
            COUNT(u.id) as uzdevumu_skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
            AVG(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums END) as vidējais_ilgums
        FROM uzdevumu_kategorijas k
        LEFT JOIN uzdevumi u ON k.id = u.kategorijas_id AND $date_filter $mechanic_filter $location_filter
        WHERE k.aktīvs = 1
        GROUP BY k.id, k.nosaukums
        HAVING uzdevumu_skaits > 0
        ORDER BY uzdevumu_skaits DESC
    ");
    $stmt->execute($all_params);
    $kategoriju_statistika = $stmt->fetchAll();
    
    // 7. Laika analīze (pa dienām)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(izveidots) as datums,
            COUNT(*) as izveidoti_uzdevumi,
            SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
            COUNT(DISTINCT piešķirts_id) as aktīvi_mehāniķi
        FROM uzdevumi 
        WHERE $date_filter $mechanic_filter $location_filter
        GROUP BY DATE(izveidots)
        ORDER BY datums DESC
        LIMIT 30
    ");
    $stmt->execute($all_params);
    $dienas_statistika = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot atskaites: " . $e->getMessage();
    // Inicializēt tukšus masīvus kļūdas gadījumā
    $vispārīgā_statistika = ['kopā_uzdevumi' => 0, 'pabeigti_uzdevumi' => 0, 'aktīvi_uzdevumi' => 0, 'atcelti_uzdevumi' => 0, 'kritiski_uzdevumi' => 0, 'vidējais_ilgums' => 0, 'nokavētie' => 0];
    $problēmu_statistika = ['kopā_problēmas' => 0, 'jaunas_problēmas' => 0, 'apskatītas_problēmas' => 0, 'pārvērstas_problēmas' => 0, 'kritiskās_problēmas' => 0];
    $mehāniķu_produktivitāte = $vietu_statistika = $prioritāšu_sadalījums = $kategoriju_statistika = $dienas_statistika = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Filtru forma -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Atskaišu filtri</h4>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="date_from" class="form-label">Datums no</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="date_to" class="form-label">Datums līdz</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="mechanic_id" class="form-label">Mehāniķis</label>
                        <select id="mechanic_id" name="mechanic_id" class="form-control">
                            <option value="">Visi mehāniķi</option>
                            <?php foreach ($mehaniki as $mehaniķis): ?>
                                <option value="<?php echo $mehaniķis['id']; ?>" <?php echo $selected_mechanic == $mehaniķis['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mehaniķis['pilns_vards']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="location_id" class="form-label">Vieta</label>
                        <select id="location_id" name="location_id" class="form-control">
                            <option value="">Visas vietas</option>
                            <?php foreach ($vietas as $vieta): ?>
                                <option value="<?php echo $vieta['id']; ?>" <?php echo $selected_location == $vieta['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Atjaunot atskaites</button>
                <a href="reports.php" class="btn btn-secondary">Notīrīt filtrus</a>
                <button type="button" onclick="exportReport()" class="btn btn-success">Eksportēt Excel</button>
            </div>
        </form>
    </div>
</div>

<!-- Vispārīgā statistika -->
<div class="row mb-4">
    <div class="col-md-12">
        <h3>Vispārīgā statistika</h3>
    </div>
</div>

<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-number"><?php echo $vispārīgā_statistika['kopā_uzdevumi']; ?></div>
        <div class="stat-label">Kopā uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);"><?php echo $vispārīgā_statistika['pabeigti_uzdevumi']; ?></div>
        <div class="stat-label">Pabeigti uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo $vispārīgā_statistika['aktīvi_uzdevumi']; ?></div>
        <div class="stat-label">Aktīvie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);"><?php echo $vispārīgā_statistika['nokavētie']; ?></div>
        <div class="stat-label">Nokavētie uzdevumi</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo number_format($vispārīgā_statistika['vidējais_ilgums'] ?? 0, 1); ?>h</div>
        <div class="stat-label">Vidējais ilgums</div>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--secondary-color);">
        <div class="stat-number" style="color: var(--secondary-color);"><?php echo $problēmu_statistika['kopā_problēmas']; ?></div>
        <div class="stat-label">Kopā problēmas</div>
    </div>
</div>

<!-- Mehāniķu produktivitāte -->
<div class="card mb-4">
    <div class="card-header">
        <h4>Mehāniķu produktivitāte</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mehāniķis</th>
                        <th>Kopā uzdevumi</th>
                        <th>Pabeigti</th>
                        <th>Efektivitāte</th>
                        <th>Vidējais ilgums</th>
                        <th>Kopējais darba laiks</th>
                        <th>Nokavētie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mehāniķu_produktivitāte as $mehaniķis): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($mehaniķis['mehaniķis']); ?></strong></td>
                            <td><?php echo $mehaniķis['uzdevumu_skaits']; ?></td>
                            <td><?php echo $mehaniķis['pabeigto_skaits']; ?></td>
                            <td>
                                <?php 
                                $efektivitāte = $mehaniķis['uzdevumu_skaits'] > 0 ? ($mehaniķis['pabeigto_skaits'] / $mehaniķis['uzdevumu_skaits']) * 100 : 0;
                                echo number_format($efektivitāte, 1) . '%';
                                ?>
                            </td>
                            <td><?php echo number_format($mehaniķis['vidējais_ilgums'] ?? 0, 1); ?>h</td>
                            <td><?php echo number_format($mehaniķis['kopējais_darba_laiks'], 1); ?>h</td>
                            <td>
                                <?php if ($mehaniķis['nokavētie'] > 0): ?>
                                    <span class="text-danger"><?php echo $mehaniķis['nokavētie']; ?></span>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vietu un prioritāšu statistika -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Vietu statistika</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Vieta</th>
                                <th>Uzdevumi</th>
                                <th>Pabeigti</th>
                                <th>Problēmas</th>
                                <th>Darba laiks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vietu_statistika as $vieta): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vieta['vieta']); ?></td>
                                    <td><?php echo $vieta['uzdevumu_skaits']; ?></td>
                                    <td><?php echo $vieta['pabeigto_skaits']; ?></td>
                                    <td><?php echo $vieta['problēmu_skaits']; ?></td>
                                    <td><?php echo number_format($vieta['kopējais_darba_laiks'], 1); ?>h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Prioritāšu sadalījums</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Prioritāte</th>
                                <th>Skaits</th>
                                <th>Pabeigti</th>
                                <th>Efektivitāte</th>
                                <th>Vid. ilgums</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prioritāšu_sadalījums as $prioritāte): ?>
                                <tr>
                                    <td>
                                        <span class="priority-badge <?php echo getPriorityClass($prioritāte['prioritate']); ?>">
                                            <?php echo $prioritāte['prioritate']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $prioritāte['skaits']; ?></td>
                                    <td><?php echo $prioritāte['pabeigti']; ?></td>
                                    <td>
                                        <?php 
                                        $efektivitāte = $prioritāte['skaits'] > 0 ? ($prioritāte['pabeigti'] / $prioritāte['skaits']) * 100 : 0;
                                        echo number_format($efektivitāte, 1) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo number_format($prioritāte['vidējais_ilgums'] ?? 0, 1); ?>h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kategoriju statistika -->
<?php if (!empty($kategoriju_statistika)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h4>Uzdevumu kategoriju statistika</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kategorija</th>
                        <th>Uzdevumu skaits</th>
                        <th>Pabeigti</th>
                        <th>Efektivitāte</th>
                        <th>Vidējais ilgums</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kategoriju_statistika as $kategorija): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kategorija['kategorija']); ?></td>
                            <td><?php echo $kategorija['uzdevumu_skaits']; ?></td>
                            <td><?php echo $kategorija['pabeigto_skaits']; ?></td>
                            <td>
                                <?php 
                                $efektivitāte = $kategorija['uzdevumu_skaits'] > 0 ? ($kategorija['pabeigto_skaits'] / $kategorija['uzdevumu_skaits']) * 100 : 0;
                                echo number_format($efektivitāte, 1) . '%';
                                ?>
                            </td>
                            <td><?php echo number_format($kategorija['vidējais_ilgums'] ?? 0, 1); ?>h</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dienas statistika -->
<?php if (!empty($dienas_statistika)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h4>Darbība pa dienām (pēdējās 30 dienas)</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Datums</th>
                        <th>Izveidoti uzdevumi</th>
                        <th>Pabeigti uzdevumi</th>
                        <th>Aktīvi mehāniķi</th>
                        <th>Produktivitāte</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dienas_statistika as $diena): ?>
                        <tr>
                            <td><?php echo formatDate($diena['datums'], 'd.m.Y'); ?></td>
                            <td><?php echo $diena['izveidoti_uzdevumi']; ?></td>
                            <td><?php echo $diena['pabeigti_uzdevumi']; ?></td>
                            <td><?php echo $diena['aktīvi_mehāniķi']; ?></td>
                            <td>
                                <?php if ($diena['aktīvi_mehāniķi'] > 0): ?>
                                    <?php echo number_format($diena['pabeigti_uzdevumi'] / $diena['aktīvi_mehāniķi'], 1); ?> uzd./meh.
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Eksportēšanas funkcionalitāte (vienkāršota versija)
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'excel');
    
    // Izveidot slēpto formu eksportēšanai
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_report.php';
    form.style.display = 'none';
    
    // Pievienot filtru parametrus
    for (const [key, value] of params) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Datuma validācija
document.addEventListener('DOMContentLoaded', function() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    dateFrom.addEventListener('change', function() {
        if (dateTo.value && this.value > dateTo.value) {
            alert('Sākuma datums nevar būt lielāks par beigu datumu');
            this.value = dateTo.value;
        }
    });
    
    dateTo.addEventListener('change', function() {
        if (dateFrom.value && this.value < dateFrom.value) {
            alert('Beigu datums nevar būt mazāks par sākuma datumu');
            this.value = dateFrom.value;
        }
    });
});
</script>

<style>
.filter-form .row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.filter-form .col-md-3 {
    flex: 1;
    min-width: 200px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
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
    .filter-form .row {
        flex-direction: column;
    }
    
    .filter-form .col-md-3 {
        width: 100%;
        flex: none;
    }
    
    .row {
        flex-direction: column;
    }
    
    .col-md-6 {
        width: 100%;
        flex: none;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}

/* Tabulu responsīvais dizains */
@media (max-width: 480px) {
    .table {
        font-size: var(--font-size-sm);
    }
    
    .table th,
    .table td {
        padding: var(--spacing-xs);
    }
}
</style>

<?php include 'includes/footer.php'; ?>