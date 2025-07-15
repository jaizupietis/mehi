<?php
require_once 'config.php';

// Iestatīt 404 statusu
http_response_code(404);

$pageTitle = 'Lapa nav atrasta';
$pageHeader = 'Lapa nav atrasta';

// Ja lietotājs nav pieslēdzies, parādīt vienkāršāku versiju
$isLoggedIn = isLoggedIn();
$currentUser = $isLoggedIn ? getCurrentUser() : null;

// Mēģināt noteikt, ko lietotājs meklēja
$requestedUrl = $_SERVER['REQUEST_URI'] ?? '';
$suggestions = [];

// Dot ieteikumus balstoties uz URL
if (strpos($requestedUrl, 'task') !== false) {
    $suggestions[] = ['url' => 'tasks.php', 'title' => 'Uzdevumi', 'description' => 'Visi sistēmas uzdevumi'];
    $suggestions[] = ['url' => 'my_tasks.php', 'title' => 'Mani uzdevumi', 'description' => 'Jūsu piešķirtie uzdevumi'];
    $suggestions[] = ['url' => 'create_task.php', 'title' => 'Izveidot uzdevumu', 'description' => 'Jauna uzdevuma izveidošana'];
} elseif (strpos($requestedUrl, 'problem') !== false) {
    $suggestions[] = ['url' => 'problems.php', 'title' => 'Problēmas', 'description' => 'Visas sistēmas problēmas'];
    $suggestions[] = ['url' => 'report_problem.php', 'title' => 'Ziņot problēmu', 'description' => 'Jauna problēma'];
    $suggestions[] = ['url' => 'my_problems.php', 'title' => 'Manas problēmas', 'description' => 'Jūsu ziņotās problēmas'];
} elseif (strpos($requestedUrl, 'user') !== false) {
    $suggestions[] = ['url' => 'users.php', 'title' => 'Lietotāji', 'description' => 'Lietotāju pārvaldība'];
    $suggestions[] = ['url' => 'profile.php', 'title' => 'Profils', 'description' => 'Jūsu profila iestatījumi'];
} elseif (strpos($requestedUrl, 'report') !== false) {
    $suggestions[] = ['url' => 'reports.php', 'title' => 'Atskaites', 'description' => 'Sistēmas atskaites un statistika'];
}

// Ja nav konkrētu ieteikumu, dot vispārīgos
if (empty($suggestions)) {
    $suggestions = [
        ['url' => 'index.php', 'title' => 'Sākums', 'description' => 'Sistēmas sākuma lapa'],
        ['url' => 'tasks.php', 'title' => 'Uzdevumi', 'description' => 'Visi sistēmas uzdevumi'],
        ['url' => 'problems.php', 'title' => 'Problēmas', 'description' => 'Visas sistēmas problēmas'],
        ['url' => 'notifications.php', 'title' => 'Paziņojumi', 'description' => 'Jūsu paziņojumi']
    ];
}

// Ja nav pieslēdzies, parādīt tikai pieslēgšanās
if (!$isLoggedIn) {
    include 'includes/header.php';
} else {
    include 'includes/header.php';
}
?>

<div class="error-404-container">
    <div class="error-404-content">
        <div class="error-illustration">
            <div class="error-code">404</div>
            <div class="error-icon">🔍</div>
            <div class="error-message-graphic">Lapa nav atrasta</div>
        </div>
        
        <div class="error-main-content">
            <h1>Ups! Lapa nav atrasta</h1>
            
            <div class="error-description">
                <p>Lapa, kuru meklējāt, neeksistē vai ir pārvietota.</p>
                <?php if (!empty($requestedUrl)): ?>
                    <p class="requested-url">
                        <strong>Meklētā adrese:</strong> 
                        <code><?php echo htmlspecialchars($requestedUrl); ?></code>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if (!$isLoggedIn): ?>
                <!-- Nepieslēgtiem lietotājiem -->
                <div class="login-suggestion">
                    <h3>Vai jūs meklējāt sistēmu?</h3>
                    <p>Lai piekļūtu AVOTI Task Management sistēmai, jums jāpieslēdzas.</p>
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <span>🔐</span> Pieslēgties sistēmai
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Pieslēgtiem lietotājiem -->
                <div class="suggestions-section">
                    <h3>Iespējams, jūs meklējāt:</h3>
                    <div class="suggestions-grid">
                        <?php foreach ($suggestions as $suggestion): ?>
                            <a href="<?php echo $suggestion['url']; ?>" class="suggestion-card">
                                <h4><?php echo htmlspecialchars($suggestion['title']); ?></h4>
                                <p><?php echo htmlspecialchars($suggestion['description']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Ātras darbības</h3>
                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary">
                            <span>🏠</span> Sākums
                        </a>
                        
                        <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                            <a href="create_task.php" class="btn btn-success">
                                <span>➕</span> Izveidot uzdevumu
                            </a>
                            <a href="problems.php" class="btn btn-warning">
                                <span>⚠️</span> Problēmas
                            </a>
                        <?php elseif (hasRole(ROLE_MECHANIC)): ?>
                            <a href="my_tasks.php" class="btn btn-primary">
                                <span>🔧</span> Mani uzdevumi
                            </a>
                        <?php elseif (hasRole(ROLE_OPERATOR)): ?>
                            <a href="report_problem.php" class="btn btn-danger">
                                <span>📋</span> Ziņot problēmu
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="goBack()" class="btn btn-secondary">
                            <span>⬅️</span> Atgriezties
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="search-section">
                <h3>Vai meklēt kaut ko konkrētu?</h3>
                <div class="search-options">
                    <?php if ($isLoggedIn): ?>
                        <div class="search-grid">
                            <div class="search-category">
                                <h4>📋 Uzdevumi</h4>
                                <ul>
                                    <li><a href="tasks.php">Visi uzdevumi</a></li>
                                    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                                        <li><a href="create_task.php">Izveidot uzdevumu</a></li>
                                    <?php endif; ?>
                                    <?php if (hasRole(ROLE_MECHANIC)): ?>
                                        <li><a href="my_tasks.php">Mani uzdevumi</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <div class="search-category">
                                <h4>⚠️ Problēmas</h4>
                                <ul>
                                    <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                                        <li><a href="problems.php">Visas problēmas</a></li>
                                    <?php endif; ?>
                                    <?php if (hasRole(ROLE_OPERATOR)): ?>
                                        <li><a href="report_problem.php">Ziņot problēmu</a></li>
                                        <li><a href="my_problems.php">Manas problēmas</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <div class="search-category">
                                <h4>👤 Profils</h4>
                                <ul>
                                    <li><a href="profile.php">Mans profils</a></li>
                                    <li><a href="notifications.php">Paziņojumi</a></li>
                                    <?php if (hasRole(ROLE_ADMIN)): ?>
                                        <li><a href="users.php">Lietotāji</a></li>
                                        <li><a href="reports.php">Atskaites</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="help-section">
                <h3>Nepieciešama palīdzība?</h3>
                <div class="help-grid">
                    <div class="help-item">
                        <strong>📞 Tālrunis</strong>
                        <span>+371 1234-5678</span>
                    </div>
                    <div class="help-item">
                        <strong>📧 E-pasts</strong>
                        <span>support@avoti.lv</span>
                    </div>
                    <div class="help-item">
                        <strong>🕒 Darba laiks</strong>
                        <span>P-Pk 8:00-17:00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="error-footer">
            <div class="error-details">
                <details>
                    <summary>Tehniskā informācija</summary>
                    <div class="tech-info">
                        <div class="tech-item">
                            <span class="label">Kļūdas kods:</span>
                            <span class="value">404 - Not Found</span>
                        </div>
                        <div class="tech-item">
                            <span class="label">Meklētā adrese:</span>
                            <span class="value"><?php echo htmlspecialchars($requestedUrl); ?></span>
                        </div>
                        <div class="tech-item">
                            <span class="label">Servera laiks:</span>
                            <span class="value"><?php echo date('d.m.Y H:i:s'); ?></span>
                        </div>
                        <?php if ($isLoggedIn): ?>
                            <div class="tech-item">
                                <span class="label">Lietotājs:</span>
                                <span class="value"><?php echo htmlspecialchars($currentUser['lietotajvards']); ?></span>
                            </div>
                            <div class="tech-item">
                                <span class="label">Loma:</span>
                                <span class="value"><?php echo htmlspecialchars($currentUser['loma']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="tech-item">
                            <span class="label">IP adrese:</span>
                            <span class="value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Nav zināma'; ?></span>
                        </div>
                        <div class="tech-item">
                            <span class="label">Pārlūks:</span>
                            <span class="value"><?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? 'Nav zināms', 0, 60) . '...'); ?></span>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

<script>
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '<?php echo $isLoggedIn ? "index.php" : "login.php"; ?>';
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        goBack();
    }
    
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = '<?php echo $isLoggedIn ? "index.php" : "login.php"; ?>';
    }
});

// Auto redirect after 60 seconds for non-logged users
<?php if (!$isLoggedIn): ?>
setTimeout(function() {
    if (confirm('Vai vēlaties doties uz pieslēgšanās lapu?')) {
        window.location.href = 'login.php';
    }
}, 60000);
<?php endif; ?>
</script>

<style>
.error-404-container {
    min-height: calc(100vh - 200px);
    padding: var(--spacing-lg);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.error-404-content {
    max-width: 1000px;
    margin: 0 auto;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.error-illustration {
    background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
    color: var(--white);
    text-align: center;
    padding: var(--spacing-xl);
}

.error-code {
    font-size: 5rem;
    font-weight: bold;
    margin-bottom: var(--spacing-sm);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.error-icon {
    font-size: 3rem;
    margin-bottom: var(--spacing-md);
}

.error-message-graphic {
    font-size: 1.5rem;
    font-weight: 500;
}

.error-main-content {
    padding: var(--spacing-xl);
}

.error-main-content h1 {
    text-align: center;
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    font-size: 2rem;
}

.error-description {
    text-align: center;
    margin-bottom: var(--spacing-xl);
    color: var(--gray-700);
    font-size: 1.1rem;
    line-height: 1.6;
}

.requested-url {
    background: var(--gray-100);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin-top: var(--spacing-md);
    font-family: monospace;
    word-break: break-all;
}

.login-suggestion {
    text-align: center;
    background: var(--gray-100);
    padding: var(--spacing-xl);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
}

.login-suggestion h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.suggestions-section {
    margin-bottom: var(--spacing-xl);
}

.suggestions-section h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.suggestions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.suggestion-card {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--gray-800);
    transition: all 0.3s ease;
    border-left: 4px solid var(--secondary-color);
}

.suggestion-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    background: var(--gray-200);
}

.suggestion-card h4 {
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--secondary-color);
}

.suggestion-card p {
    margin: 0;
    color: var(--gray-600);
    font-size: var(--font-size-sm);
}

.quick-actions {
    text-align: center;
    margin-bottom: var(--spacing-xl);
}

.quick-actions h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
}

.action-buttons {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
    flex-wrap: wrap;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-md) var(--spacing-lg);
}

.search-section {
    margin-bottom: var(--spacing-xl);
}

.search-section h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    text-align: center;
}

.search-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
}

.search-category {
    background: var(--gray-50);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
}

.search-category h4 {
    margin: 0 0 var(--spacing-md) 0;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.search-category ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.search-category li {
    margin-bottom: var(--spacing-xs);
}

.search-category a {
    color: var(--secondary-color);
    text-decoration: none;
    font-size: var(--font-size-sm);
}

.search-category a:hover {
    text-decoration: underline;
}

.help-section {
    text-align: center;
    background: var(--gray-50);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
}

.help-section h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
}

.help-item {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.help-item strong {
    color: var(--gray-700);
    font-size: var(--font-size-sm);
}

.help-item span {
    color: var(--secondary-color);
    font-weight: 500;
}

.error-footer {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-top: 1px solid var(--gray-300);
}

.error-details details {
    max-width: 600px;
    margin: 0 auto;
}

.error-details summary {
    cursor: pointer;
    font-weight: 500;
    color: var(--gray-700);
    text-align: center;
    padding: var(--spacing-sm);
}

.tech-info {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: var(--spacing-lg);
    margin-top: var(--spacing-md);
    border: 1px solid var(--gray-300);
}

.tech-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-xs) 0;
    border-bottom: 1px solid var(--gray-200);
    font-size: var(--font-size-sm);
}

.tech-item:last-child {
    border-bottom: none;
}

.tech-item .label {
    font-weight: 500;
    color: var(--gray-600);
}

.tech-item .value {
    color: var(--gray-800);
    font-family: monospace;
    word-break: break-all;
}

/* Responsive */
@media (max-width: 768px) {
    .error-404-container {
        padding: var(--spacing-md);
    }
    
    .error-main-content {
        padding: var(--spacing-lg);
    }
    
    .error-code {
        font-size: 3rem;
    }
    
    .suggestions-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .action-buttons .btn {
        width: 100%;
        max-width: 300px;
    }
    
    .search-grid {
        grid-template-columns: 1fr;
    }
    
    .help-grid {
        grid-template-columns: 1fr;
    }
    
    .tech-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }
}
</style>

<?php include 'includes/footer.php'; ?>