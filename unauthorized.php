<?php
require_once 'config.php';

$pageTitle = 'Piekļuve liegta';
$pageHeader = 'Piekļuve liegta';

// Ja lietotājs nav pieslēdzies, novirzīt uz pieslēgšanās lapu
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentUser = getCurrentUser();

include 'includes/header.php';
?>

<div class="unauthorized-container">
    <div class="unauthorized-content">
        <div class="error-icon">
            <span class="error-code">403</span>
            <span class="error-symbol">🚫</span>
        </div>
        
        <h1>Piekļuve liegta</h1>
        
        <div class="error-message">
            <p>Diemžēl jums nav atļaujas piekļūt šai lapai.</p>
            <p>Jūsu pašreizējā loma: <strong><?php echo htmlspecialchars($currentUser['loma']); ?></strong></p>
        </div>
        
        <div class="error-details">
            <div class="details-card">
                <h3>Kas varētu būt noticis?</h3>
                <ul>
                    <li>Jūs mēģināt piekļūt lapai, kas nav pieejama jūsu lomai</li>
                    <li>Jūsu lietotāja loma ir mainīta, bet jūs vēl neesat atjauninājis sesiju</li>
                    <li>Šī lapa ir pieejama tikai noteiktām lietotāju grupām</li>
                    <li>URL adrese varētu būt nepareiza</li>
                </ul>
            </div>
            
            <div class="details-card">
                <h3>Ko jūs varat darīt?</h3>
                <ul>
                    <li>Atgriezieties uz sākuma lapu un izmantojiet navigācijas izvēlni</li>
                    <li>Pārbaudiet, vai jums ir pareizās atļaujas šai darbībai</li>
                    <li>Sazinieties ar sistēmas administratoru, ja uzskatāt, ka jums vajadzētu būt piekļuvei</li>
                    <li>Izrakstieties un pieslēdzieties no jauna, ja uzskatāt, ka loma ir mainīta</li>
                </ul>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">
                <span>🏠</span> Atgriezties uz sākuma lapu
            </a>
            
            <button onclick="goBack()" class="btn btn-secondary">
                <span>⬅️</span> Atgriezties atpakaļ
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
                <div class="contact-item">
                    <strong>👨‍💼 Sistēmas administrators:</strong>
                    <span>admin@avoti.lv</span>
                </div>
            </div>
        </div>
        
        <div class="user-info-section">
            <h4>Jūsu informācija</h4>
            <div class="user-details">
                <div class="user-detail-item">
                    <span class="label">Lietotājs:</span>
                    <span class="value"><?php echo htmlspecialchars($currentUser['vards'] . ' ' . $currentUser['uzvards']); ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="label">Lietotājvārds:</span>
                    <span class="value"><?php echo htmlspecialchars($currentUser['lietotajvards']); ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="label">Loma:</span>
                    <span class="value role-badge role-<?php echo strtolower(str_replace('ā', 'a', $currentUser['loma'])); ?>">
                        <?php echo htmlspecialchars($currentUser['loma']); ?>
                    </span>
                </div>
                <div class="user-detail-item">
                    <span class="label">Sesijas ID:</span>
                    <span class="value"><?php echo substr(session_id(), 0, 8) . '...'; ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="label">IP adrese:</span>
                    <span class="value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Nav zināma'; ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="label">Pārlūkprogramma:</span>
                    <span class="value"><?php echo substr($_SERVER['HTTP_USER_AGENT'] ?? 'Nav zināma', 0, 50) . '...'; ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="label">Laiks:</span>
                    <span class="value"><?php echo date('d.m.Y H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function goBack() {
    // Pārbaudīt vai ir vēsture
    if (window.history.length > 1) {
        window.history.back();
    } else {
        // Ja nav vēstures, doties uz sākuma lapu
        window.location.href = 'index.php';
    }
}

// Automātiski novirzīt pēc 30 sekundēm
setTimeout(function() {
    if (confirm('Vai vēlaties automātiski atgriezties uz sākuma lapu?')) {
        window.location.href = 'index.php';
    }
}, 30000);

// Tastatūras saīsnes
document.addEventListener('keydown', function(e) {
    // ESC - atgriezties atpakaļ
    if (e.key === 'Escape') {
        goBack();
    }
    
    // Alt+H - uz sākuma lapu
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = 'index.php';
    }
});
</script>

<style>
.unauthorized-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-lg);
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.unauthorized-content {
    max-width: 800px;
    width: 100%;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: var(--spacing-xl);
    text-align: center;
}

.error-icon {
    margin-bottom: var(--spacing-lg);
    position: relative;
}

.error-code {
    font-size: 4rem;
    font-weight: bold;
    color: var(--danger-color);
    display: block;
    margin-bottom: var(--spacing-sm);
}

.error-symbol {
    font-size: 3rem;
    display: block;
    margin-bottom: var(--spacing-lg);
}

.unauthorized-content h1 {
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
    border-left: 4px solid var(--secondary-color);
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

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-md) var(--spacing-lg);
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.help-section {
    background: var(--gray-100);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
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

.user-info-section {
    background: var(--gray-50);
    padding: var(--spacing-lg);
    border-radius: var(--border-radius);
    text-align: left;
}

.user-info-section h4 {
    text-align: center;
    color: var(--gray-800);
    margin-bottom: var(--spacing-md);
    font-size: 1.1rem;
}

.user-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-sm);
}

.user-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-xs) var(--spacing-sm);
    background: var(--white);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
}

.user-detail-item .label {
    font-weight: 500;
    color: var(--gray-600);
}

.user-detail-item .value {
    color: var(--gray-800);
    font-family: monospace;
}

.role-badge {
    padding: 2px 8px;
    border-radius: var(--border-radius);
    font-size: 11px;
    font-weight: 500;
    color: var(--white);
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

/* Responsive dizains */
@media (max-width: 768px) {
    .unauthorized-content {
        padding: var(--spacing-lg);
        margin: var(--spacing-md);
    }
    
    .error-code {
        font-size: 3rem;
    }
    
    .error-symbol {
        font-size: 2rem;
    }
    
    .unauthorized-content h1 {
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
        justify-content: center;
    }
    
    .contact-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }
    
    .user-details {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .unauthorized-container {
        padding: var(--spacing-md);
    }
    
    .unauthorized-content {
        padding: var(--spacing-md);
    }
    
    .details-card {
        padding: var(--spacing-md);
    }
    
    .help-section,
    .user-info-section {
        padding: var(--spacing-md);
    }
}

/* Animācijas */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.unauthorized-content {
    animation: fadeIn 0.5s ease-out;
}

.error-icon {
    animation: fadeIn 0.8s ease-out 0.2s both;
}

.error-message {
    animation: fadeIn 0.8s ease-out 0.4s both;
}

.error-details {
    animation: fadeIn 0.8s ease-out 0.6s both;
}

.action-buttons {
    animation: fadeIn 0.8s ease-out 0.8s both;
}
</style>

<?php include 'includes/footer.php'; ?>