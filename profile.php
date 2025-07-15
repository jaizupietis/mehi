<?php
require_once 'config.php';

// Pārbaudīt pieslēgšanos
requireLogin();

$pageTitle = 'Profils';
$pageHeader = 'Mans profils';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Apstrādāt POST darbības
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $vards = sanitizeInput($_POST['vards'] ?? '');
        $uzvards = sanitizeInput($_POST['uzvards'] ?? '');
        $epasts = sanitizeInput($_POST['epasts'] ?? '');
        $telefons = sanitizeInput($_POST['telefons'] ?? '');
        
        // Validācija
        if (empty($vards) || empty($uzvards)) {
            $errors[] = "Vārds un uzvārds ir obligāti.";
        }
        
        if (!empty($epasts) && !filter_var($epasts, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Nederīgs e-pasta formāts.";
        }
        
        // Atjaunot profilu
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE lietotaji 
                    SET vards = ?, uzvards = ?, epasts = ?, telefons = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $vards, 
                    $uzvards, 
                    $epasts ?: null, 
                    $telefons ?: null, 
                    $currentUser['id']
                ]);
                
                // Atjaunot sesijas datus
                $_SESSION['vards'] = $vards;
                $_SESSION['uzvards'] = $uzvards;
                
                setFlashMessage('success', 'Profils veiksmīgi atjaunots!');
                redirect('profile.php');
                
            } catch (PDOException $e) {
                $errors[] = "Kļūda atjaunojot profilu: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'change_password') {
        $pašreizējā_parole = $_POST['pašreizējā_parole'] ?? '';
        $jaunā_parole = $_POST['jaunā_parole'] ?? '';
        $apstiprināt_paroli = $_POST['apstiprināt_paroli'] ?? '';
        
        // Validācija
        if (empty($pašreizējā_parole) || empty($jaunā_parole) || empty($apstiprināt_paroli)) {
            $errors[] = "Visi paroles lauki ir obligāti.";
        }
        
        if (strlen($jaunā_parole) < 6) {
            $errors[] = "Jaunā parole jābūt vismaz 6 rakstzīmes gara.";
        }
        
        if ($jaunā_parole !== $apstiprināt_paroli) {
            $errors[] = "Jaunās paroles nesakrīt.";
        }
        
        // Pārbaudīt pašreizējo paroli
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT parole FROM lietotaji WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                $stored_password = $stmt->fetchColumn();
                
                if (!password_verify($pašreizējā_parole, $stored_password)) {
                    $errors[] = "Pašreizējā parole ir nepareiza.";
                }
            } catch (PDOException $e) {
                $errors[] = "Kļūda pārbaudot paroli.";
            }
        }
        
        // Mainīt paroli
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($jaunā_parole, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE lietotaji SET parole = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $currentUser['id']]);
                
                setFlashMessage('success', 'Parole veiksmīgi nomainīta!');
                redirect('profile.php');
                
            } catch (PDOException $e) {
                $errors[] = "Kļūda mainot paroli: " . $e->getMessage();
            }
        }
    }
}

// Iegūt lietotāja statistiku
try {
    $user_stats = [];
    
    if ($currentUser['loma'] === 'Mehāniķis') {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as kopā_uzdevumi,
                SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
                SUM(CASE WHEN statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_uzdevumi,
                SUM(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums ELSE 0 END) as kopējais_darba_laiks,
                AVG(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums END) as vidējais_ilgums
            FROM uzdevumi 
            WHERE piešķirts_id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $user_stats = $stmt->fetch();
        
        // Jaunākie uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.id, u.nosaukums, u.statuss, u.prioritate, u.izveidots, u.jabeidz_lidz,
                   v.nosaukums as vietas_nosaukums
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            WHERE u.piešķirts_id = ?
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $recent_tasks = $stmt->fetchAll();
        
    } elseif ($currentUser['loma'] === 'Operators') {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as kopā_problēmas,
                SUM(CASE WHEN statuss = 'Jauna' THEN 1 ELSE 0 END) as jaunas_problēmas,
                SUM(CASE WHEN statuss = 'Apskatīta' THEN 1 ELSE 0 END) as apskatītas_problēmas,
                SUM(CASE WHEN statuss = 'Pārvērsta uzdevumā' THEN 1 ELSE 0 END) as pārvērstas_problēmas
            FROM problemas 
            WHERE zinotajs_id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $user_stats = $stmt->fetch();
        
        // Jaunākās problēmas
        $stmt = $pdo->prepare("
            SELECT p.id, p.nosaukums, p.statuss, p.prioritate, p.izveidots,
                   v.nosaukums as vietas_nosaukums
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            WHERE p.zinotajs_id = ?
            ORDER BY p.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $recent_problems = $stmt->fetchAll();
        
    } elseif (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM uzdevumi WHERE izveidoja_id = ?) as izveidoti_uzdevumi,
                (SELECT COUNT(*) FROM uzdevumi) as kopā_uzdevumi,
                (SELECT COUNT(*) FROM problemas) as kopā_problēmas,
                (SELECT COUNT(*) FROM lietotaji WHERE statuss = 'Aktīvs') as aktīvi_lietotāji
        ");
        $stmt->execute([$currentUser['id']]);
        $user_stats = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    error_log("Kļūda iegūstot lietotāja statistiku: " . $e->getMessage());
    $user_stats = [];
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="row">
    <!-- Profila informācija -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h4>Profila informācija</h4>
            </div>
            <div class="card-body">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php echo strtoupper(substr($currentUser['vards'], 0, 1) . substr($currentUser['uzvards'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <h3><?php echo htmlspecialchars($currentUser['vards'] . ' ' . $currentUser['uzvards']); ?></h3>
                        <p class="role-badge role-<?php echo strtolower(str_replace('ā', 'a', $currentUser['loma'])); ?>">
                            <?php echo $currentUser['loma']; ?>
                        </p>
                        
                        <div class="profile-meta">
                            <div class="meta-item">
                                <strong>Lietotājvārds:</strong> <?php echo htmlspecialchars($currentUser['lietotajvards']); ?>
                            </div>
                            
                            <?php if ($currentUser['epasts']): ?>
                                <div class="meta-item">
                                    <strong>E-pasts:</strong> <?php echo htmlspecialchars($currentUser['epasts']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($currentUser['telefons']): ?>
                                <div class="meta-item">
                                    <strong>Telefons:</strong> <?php echo htmlspecialchars($currentUser['telefons']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="meta-item">
                                <strong>Reģistrēts:</strong> <?php echo formatDate($currentUser['izveidots']); ?>
                            </div>
                            
                            <?php if ($currentUser['pēdējā_pieslēgšanās']): ?>
                                <div class="meta-item">
                                    <strong>Pēdējoreiz:</strong> <?php echo formatDate($currentUser['pēdējā_pieslēgšanās']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lietotāja statistika -->
        <?php if (!empty($user_stats)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Mana statistika</h4>
                </div>
                <div class="card-body">
                    <div class="stats-list">
                        <?php if ($currentUser['loma'] === 'Mehāniķis'): ?>
                            <div class="stat-item">
                                <span class="stat-label">Kopā uzdevumi:</span>
                                <span class="stat-value"><?php echo $user_stats['kopā_uzdevumi']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Pabeigti uzdevumi:</span>
                                <span class="stat-value text-success"><?php echo $user_stats['pabeigti_uzdevumi']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Aktīvie uzdevumi:</span>
                                <span class="stat-value text-warning"><?php echo $user_stats['aktīvi_uzdevumi']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Kopējais darba laiks:</span>
                                <span class="stat-value"><?php echo number_format($user_stats['kopējais_darba_laiks'], 1); ?>h</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Vidējais ilgums:</span>
                                <span class="stat-value"><?php echo number_format($user_stats['vidējais_ilgums'] ?? 0, 1); ?>h</span>
                            </div>
                            
                        <?php elseif ($currentUser['loma'] === 'Operators'): ?>
                            <div class="stat-item">
                                <span class="stat-label">Kopā problēmas:</span>
                                <span class="stat-value"><?php echo $user_stats['kopā_problēmas']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Jaunas problēmas:</span>
                                <span class="stat-value text-info"><?php echo $user_stats['jaunas_problēmas']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Apskatītas problēmas:</span>
                                <span class="stat-value text-warning"><?php echo $user_stats['apskatītas_problēmas']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Pārvērstas uzdevumos:</span>
                                <span class="stat-value text-success"><?php echo $user_stats['pārvērstas_problēmas']; ?></span>
                            </div>
                            
                        <?php elseif (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                            <div class="stat-item">
                                <span class="stat-label">Izveidoti uzdevumi:</span>
                                <span class="stat-value"><?php echo $user_stats['izveidoti_uzdevumi']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Sistēmā uzdevumi:</span>
                                <span class="stat-value"><?php echo $user_stats['kopā_uzdevumi']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Sistēmā problēmas:</span>
                                <span class="stat-value"><?php echo $user_stats['kopā_problēmas']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Aktīvi lietotāji:</span>
                                <span class="stat-value"><?php echo $user_stats['aktīvi_lietotāji']; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Profila rediģēšana -->
    <div class="col-md-8">
        <!-- Profila atjaunošana -->
        <div class="card">
            <div class="card-header">
                <h4>Rediģēt profilu</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vards" class="form-label">Vārds *</label>
                                <input type="text" id="vards" name="vards" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['vards']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="uzvards" class="form-label">Uzvārds *</label>
                                <input type="text" id="uzvards" name="uzvards" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['uzvards']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="epasts" class="form-label">E-pasts</label>
                                <input type="email" id="epasts" name="epasts" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['epasts'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="telefons" class="form-label">Telefons</label>
                                <input type="text" id="telefons" name="telefons" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['telefons'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Saglabāt izmaiņas</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Paroles maiņa -->
        <div class="card mt-4">
            <div class="card-header">
                <h4>Mainīt paroli</h4>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="pašreizējā_parole" class="form-label">Pašreizējā parole *</label>
                        <input type="password" id="pašreizējā_parole" name="pašreizējā_parole" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jaunā_parole" class="form-label">Jaunā parole *</label>
                                <input type="password" id="jaunā_parole" name="jaunā_parole" class="form-control" 
                                       minlength="6" required>
                                <small class="form-text text-muted">Minimums 6 rakstzīmes</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="apstiprināt_paroli" class="form-label">Apstiprināt paroli *</label>
                                <input type="password" id="apstiprināt_paroli" name="apstiprināt_paroli" class="form-control" 
                                       minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-warning">Mainīt paroli</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Jaunākie uzdevumi/problēmas -->
        <?php if ($currentUser['loma'] === 'Mehāniķis' && !empty($recent_tasks)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Jaunākie uzdevumi</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Uzdevums</th>
                                    <th>Prioritāte</th>
                                    <th>Statuss</th>
                                    <th>Termiņš</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></small>
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
                                            <?php if ($task['jabeidz_lidz']): ?>
                                                <small class="<?php echo strtotime($task['jabeidz_lidz']) < time() && $task['statuss'] != 'Pabeigts' ? 'text-danger' : ''; ?>">
                                                    <?php echo formatDate($task['jabeidz_lidz']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="my_tasks.php" class="btn btn-sm btn-primary">Skatīt visus uzdevumus</a>
                </div>
            </div>
            
        <?php elseif ($currentUser['loma'] === 'Operators' && !empty($recent_problems)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h4>Jaunākās problēmas</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Problēma</th>
                                    <th>Prioritāte</th>
                                    <th>Statuss</th>
                                    <th>Izveidots</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_problems as $problem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($problem['nosaukums']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                                                <?php echo $problem['prioritate']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', 'ā'], ['-', 'a'], $problem['statuss'])); ?>">
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
                    <a href="my_problems.php" class="btn btn-sm btn-secondary">Skatīt visas problēmas</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Paroles validācija
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('jaunā_parole').value;
    const confirmPassword = document.getElementById('apstiprināt_paroli').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Jaunās paroles nesakrīt!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Jaunā parole jābūt vismaz 6 rakstzīmes gara!');
        return false;
    }
});

// Paroles stipruma pārbaude
document.getElementById('jaunā_parole').addEventListener('input', function() {
    const password = this.value;
    const strength = getPasswordStrength(password);
    
    // Parādīt stipruma indikatoru (var pievienot vēlāk)
});

function getPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return strength;
}
</script>

<style>
.profile-info {
    text-align: center;
}

.profile-avatar {
    margin-bottom: var(--spacing-lg);
}

.avatar-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto;
    box-shadow: var(--shadow-lg);
}

.profile-details h3 {
    margin-bottom: var(--spacing-sm);
    color: var(--gray-800);
}

.role-badge {
    display: inline-block;
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--white);
    margin-bottom: var(--spacing-lg);
}

.role-administrators {
    background: var(--danger-color);
}

.role-menedzris {
    background: var(--warning-color);
}

.role-operators {
    background: var(--info-color);
}

.role-mehanikis {
    background: var(--success-color);
}

.profile-meta {
    text-align: left;
    margin-top: var(--spacing-lg);
}

.meta-item {
    margin-bottom: var(--spacing-sm);
    padding-bottom: var(--spacing-sm);
    border-bottom: 1px solid var(--gray-200);
}

.meta-item:last-child {
    border-bottom: none;
}

.stats-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm) 0;
    border-bottom: 1px solid var(--gray-200);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    font-weight: 500;
    color: var(--gray-700);
}

.stat-value {
    font-weight: bold;
    color: var(--gray-800);
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.col-md-4 {
    flex: 0 0 300px;
    min-width: 250px;
}

.col-md-6 {
    flex: 1;
    min-width: 250px;
}

.col-md-8 {
    flex: 1;
    min-width: 400px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .col-md-4,
    .col-md-6,
    .col-md-8 {
        width: 100%;
        flex: none;
    }
    
    .avatar-circle {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .profile-details h3 {
        font-size: 1.2rem;
    }
    
    .meta-item {
        font-size: var(--font-size-sm);
    }
    
    .stat-item {
        font-size: var(--font-size-sm);
    }
}
</style>

<?php include 'includes/footer.php'; ?>