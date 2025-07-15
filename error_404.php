<?php
require_once 'config.php';

// Iestatīt HTTP statusu
http_response_code(404);

$pageTitle = 'Lapa nav atrasta';
$pageHeader = 'Lapa nav atrasta';

// Ja lietotājs nav pieslēdzies, parādīt vienkāršu 404 lapu
if (!isLoggedIn()) {
    ?>
    <!DOCTYPE html>
    <html lang="lv">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo getPageTitle($pageTitle); ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="error-page">
            <div class="error-content">
                <h1>404</h1>
                <h2>Lapa nav atrasta</h2>
                <p>Diemžēl meklētā lapa nav atrasta.</p>
                <a href="login.php" class="btn btn-primary">Pieslēgties</a>
            </div>
        </div>
        
        <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            text-align: center;
            color: white;
        }
        .error-content h1 {
            font-size: 8rem;
            margin-bottom: 1rem;
        }
        .error-content h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .error-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        </style>
    </body>
    </html>
    <?php
    exit();
}

$currentUser = getCurrentUser();
$requestedUrl = $_SERVER['REQUEST_URI'] ?? '';

include 'includes/header.php';
?>

<div class="error-container">
    <div class="error-content">
        <div class="error-icon">
            <span class="error-code">404</span>
            <span class="error-symbol">📄</span>
        </div>
        
        <h1>Lapa nav atrasta</h1>
        
        <div class="error-message">
            <p>Diemžēl meklētā lapa nav atrasta sistēmā.</p>
            <p>Meklētais URL: <code><?php echo htmlspecialchars($requestedUrl); ?></code></p>
        </div>
        
        <div class="error-details">
            <div class="details-card">
                <h3>Kas varētu būt noticis?</h3>
                <ul>
                    <li>Lapa ir dzēsta vai pārvietota</li>
                    <li>URL adrese ir nepareizi ierakstīta</li>
                    <li>Saite ir novecojusi</li>
                    <li>Jums nav atļaujas piekļūt šai lapai</li>
                </ul>
            </div>
            
            <div class="details-card">
                <h3>Ko jūs varat darīt?</h3>
                <ul>
                    <li>Pārbaudīt URL adresi uz kļūdām</li>
                    <li>Izmantot navigācijas izvēlni</li>
                    <li>Atgriezties uz sākuma lapu</li>
                    <li>Sazināties ar tehnikas atbalstu</li>
                </ul>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">
                <span>🏠</span> Sākuma lapa
            </a>
            
            <button onclick="goBack()" class="btn btn-secondary">
                <span>⬅️</span> Atpakaļ
            </button>
            
            <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="tasks.php" class="btn btn-info">
                    <span>📋</span> Uzdevumi
                </a>
            <?php elseif (hasRole(ROLE_MECHANIC)): ?>
                <a href="my_tasks.php" class="btn btn-success">
                    <span>🔧</span> Mani uzdevumi
                </a>
            <?php elseif (hasRole(ROLE_OPERATOR)): ?>
                <a href="report_problem.php" class="btn btn-warning">
                    <span>⚠️</span> Ziņot problēmu
                </a>
            <?php endif; ?>
        </div>
        
        <div class="search-section">
            <h3>Meklēt sistēmā</h3>
            <div class="search-form">
                <form method="GET" action="search.php">
                    <div class="search-input-group">
                        <input type="text" name="q" placeholder="Meklēt uzdevumos, problēmās..." class="form-control">
                        <button type="submit" class="btn btn-primary">Meklēt</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="quick-links">
            <h3>Ātras saites</h3>
            <div class="links-grid">
                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                    <a href="tasks.php" class="quick-link">
                        <span class="link-icon">📋</span>
                        <span class="link-text">Uzdevumi</span>
                    </a>
                    <a href="problems.php" class="quick-link">
                        <span class="link-icon">⚠️</span>
                        <span class="link-text">Problēmas</span>
                    </a>
                    <a href="create_task.php" class="quick-link">
                        <span class="link-icon">➕</span>
                        <span class="link-text">Jauns uzdevums</span>
                    </a>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                        <a href="reports.php" class="quick-link">
                            <span class="link-icon">📊</span>
                            <span class="link-text">Atskaites</span>
                        </a>
                        <a href="users.php" class="quick-link">
                            <span class="link-icon">👥</span>
                            <span class="link-text">Lietotāji</span>
                        </a>
                        <a href="settings.php" class="quick-link">
                            <span class="link-icon">⚙️</span>
                            <span class="link-text">Iestatījumi</span>
                        </a>
                    <?php endif; ?>
                <?php elseif (hasRole(ROLE_MECHANIC)): ?>
                    <a href="my_tasks.php" class="quick-link">
                        <span class="link-icon">🔧</span>
                        <span class="link-text">Mani uzdevumi</span>
                    </a>
                    <a href="completed_tasks.php" class="quick-link">
                        <span class="link-icon">✅</span>
                        <span class="link-text">Pabeigto uzdevumu vēsture</span>
                    </a>
                <?php elseif (hasRole(ROLE_OPERATOR)): ?>
                    <a href="report_problem.php" class="quick-link">
                        <span class="link-icon">⚠️</span>
                        <span class="link-text">Ziņot problēmu</span>
                    </a>
                    <a href="my_problems.php" class="quick-link">
                        <span class="link-icon">📝</span>
                        <span class="link-text">Manas problēmas</span>
                    </a>
                <?php endif; ?>
                
                <a href="notifications.php" class="quick-link">
                    <span class="link-icon">🔔</span>
                    <span class="link-text">Paziņojumi</span>
                </a>
                <a href="profile.php" class="quick-link">
                    <span class="link-icon">👤</span>
                    <span class="link-text">Profils</span>
                </a>
            </div>
        </div>
        
        <div class="help-section">
            <h3>Nepieciešama palīdzība?</h3>
            <div class="help-contacts">
                <div class="contact-item">
                    <strong>📞 Tehniskā atbalsta tālrunis:</strong>
                    <span>+371 1234-5678</span>
                </div>
                <div class="contact-item">
                    <strong>📧 E-pasts:</strong>
                    <span>support@avoti.lv</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

// Automātiski fokusēt uz meklēšanas lauku
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.focus();
    }
});

// Tastatūras saīsnes
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        goBack();
    }
    
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = 'index.php';
    }
});
</script>

<style>
.error-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-lg);
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.error-content {
    max-width: 900px;
    width: 100%;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--spacing-xl);
    text-align: center;
}

.error-icon {
    margin-bottom: var(--spacing-lg);
}

.error-code {
    font-size: 4rem;
    font-weight: bold;
    color: var(--warning-color);
    display: block;
    margin-bottom: var(--spacing-sm);
}

.error-symbol {
    font-size: 3rem;
    display: block;
    margin-bottom: var(--spacing-lg);
}

.error-content h1 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-lg);
    font-size: 2rem;
}

.error-message {
    margin-bottom: var(--spacing-xl);
    color: var(--gray-700);
    font-size: 1.1rem;
    line-height: 1.6;
}

.error-message code {
    background: var(--gray-100);
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    color: var(--danger-color);
}

.error-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
    text-align: left;
}

.details-card {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--warning-color);
}

.details-card h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
    font-size: 1.2rem;
}

.details-card ul {
    margin: 0;
    padding-left: var(--spacing-lg);
}

.details-card li {
    margin-bottom: var(--spacing-sm);
    color: var(--gray-700);
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: var(--spacing-xl);
}

.search-section {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
}

.search-section h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.search-input-group {
    display: flex;
    gap: var(--spacing-sm);
    max-width: 400px;
    margin: 0 auto;
}

.search-input-group input {
    flex: 1;
}

.quick-links {
    margin-bottom: var(--spacing-xl);
}

.quick-links h3 {
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--spacing-md);
}

.quick-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--gray-100);
    border-radius: var(--border-radius);
    text-decoration: none;
    color: var(--gray-700);
    transition: all 0.3s ease;
}

.quick-link:hover {
    background: var(--secondary-color);
    color: var(--white);
    transform: translateY(-2px);
}

.link-icon {
    font-size: 2rem;
    margin-bottom: var(--spacing-sm);
}

.link-text {
    font-size: var(--font-size-sm);
    text-align: center;
    font-weight: 500;
}

.help-section {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    text-align: left;
}

.help-section h3 {
    text-align: center;
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
}

.help-contacts {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.contact-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm);
    background: var(--white);
    border-radius: var(--border-radius);
    border-left: 3px solid var(--info-color);
}

/* Responsive dizains */
@media (max-width: 768px) {
    .error-content {
        padding: var(--spacing-lg);
        margin: var(--spacing-md);
    }
    
    .error-code {
        font-size: 3rem;
    }
    
    .error-symbol {
        font-size: 2rem;
    }
    
    .error-content h1 {
        font-size: 1.5rem;
    }
    
    .error-details {
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
    
    .search-input-group {
        flex-direction: column;
    }
    
    .links-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .contact-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }
}
</style>

<?php include 'includes/footer.php'; ?>