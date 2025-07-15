<?php
// 500 kļūdas lapa - minimālas atkarības, jo servera kļūda var būt config.php
http_response_code(500);

// Mēģināt ielādēt konfigurāciju, bet turpināt, ja nav iespējams
$config_loaded = false;
$isLoggedIn = false;
$currentUser = null;

try {
    if (file_exists('config.php')) {
        require_once 'config.php';
        $config_loaded = true;
        $isLoggedIn = isLoggedIn();
        $currentUser = $isLoggedIn ? getCurrentUser() : null;
    }
} catch (Exception $e) {
    // Konfigurācija nav ielādējama, turpinām bez tās
    $config_loaded = false;
    error_log("500 page: Config loading failed - " . $e->getMessage());
}

$pageTitle = 'Servera kļūda';
$pageHeader = 'Iekšējā servera kļūda';

// Iegūt kļūdas informāciju
$error_time = date('Y-m-d H:i:s');
$request_uri = $_SERVER['REQUEST_URI'] ?? 'Nav zināms';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Nav zināms';
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'Nav zināma';
$server_name = $_SERVER['SERVER_NAME'] ?? 'Nav zināms';

// Ģenerēt unikālu kļūdas ID
$error_id = 'ERR-' . date('Ymd-His') . '-' . substr(uniqid(), -6);

// Logot kļūdu (ja iespējams)
$error_message = "500 Error - ID: {$error_id}, URI: {$request_uri}, IP: {$remote_addr}, Time: {$error_time}";
if (function_exists('error_log')) {
    error_log($error_message);
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AVOTI Task Management</title>
    
    <!-- Inline CSS lai nodrošinātu, ka styles darbojas pat, ja CSS fails nav pieejams -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --success-color: #27ae60;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 3rem;
            --border-radius: 6px;
            --border-radius-lg: 12px;
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            line-height: 1.6;
            color: var(--gray-800);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }
        
        .error-500-container {
            max-width: 800px;
            width: 100%;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-header {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: var(--white);
            text-align: center;
            padding: var(--spacing-xl);
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: bold;
            margin-bottom: var(--spacing-sm);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .error-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 500;
        }
        
        .error-content {
            padding: var(--spacing-xl);
        }
        
        .error-content h1 {
            text-align: center;
            color: var(--gray-800);
            margin-bottom: var(--spacing-lg);
            font-size: 1.8rem;
        }
        
        .error-description {
            background: var(--gray-100);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-xl);
            border-left: 4px solid var(--danger-color);
        }
        
        .error-description h3 {
            color: var(--gray-800);
            margin-bottom: var(--spacing-md);
        }
        
        .error-description p {
            color: var(--gray-700);
            margin-bottom: var(--spacing-sm);
            line-height: 1.6;
        }
        
        .error-id {
            background: var(--gray-200);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius);
            font-family: monospace;
            font-weight: bold;
            color: var(--danger-color);
            text-align: center;
            margin: var(--spacing-md) 0;
        }
        
        .what-happened {
            margin-bottom: var(--spacing-xl);
        }
        
        .what-happened h3 {
            color: var(--gray-800);
            margin-bottom: var(--spacing-md);
        }
        
        .what-happened ul {
            color: var(--gray-700);
            margin-left: var(--spacing-lg);
        }
        
        .what-happened li {
            margin-bottom: var(--spacing-sm);
        }
        
        .what-to-do {
            background: var(--gray-100);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--warning-color);
            margin-bottom: var(--spacing-xl);
        }
        
        .what-to-do h3 {
            color: var(--gray-800);
            margin-bottom: var(--spacing-md);
        }
        
        .what-to-do ol {
            color: var(--gray-700);
            margin-left: var(--spacing-lg);
        }
        
        .what-to-do li {
            margin-bottom: var(--spacing-sm);
        }
        
        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: var(--spacing-xl);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--gray-600);
            color: var(--white);
        }
        
        .btn-secondary:hover {
            background: var(--gray-700);
        }
        
        .btn-success {
            background: var(--success-color);
            color: var(--white);
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .help-section {
            background: var(--gray-100);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-lg);
        }
        
        .help-section h3 {
            color: var(--gray-800);
            margin-bottom: var(--spacing-md);
            text-align: center;
        }
        
        .help-contacts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        
        .contact-item {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: var(--border-radius);
            border-left: 3px solid var(--secondary-color);
        }
        
        .contact-item strong {
            display: block;
            color: var(--gray-800);
            margin-bottom: var(--spacing-sm);
        }
        
        .contact-item span {
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .technical-details {
            background: var(--gray-50);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .technical-details summary {
            background: var(--gray-200);
            padding: var(--spacing-md);
            cursor: pointer;
            font-weight: 500;
            color: var(--gray-800);
            border: none;
            outline: none;
        }
        
        .technical-details summary:hover {
            background: var(--gray-300);
        }
        
        .tech-info {
            padding: var(--spacing-lg);
            background: var(--white);
        }
        
        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }
        
        .tech-item {
            display: flex;
            justify-content: space-between;
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.9rem;
        }
        
        .tech-item:last-child {
            border-bottom: none;
        }
        
        .tech-label {
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .tech-value {
            color: var(--gray-800);
            font-family: monospace;
            word-break: break-all;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            body {
                padding: var(--spacing-sm);
            }
            
            .error-content {
                padding: var(--spacing-lg);
            }
            
            .error-code {
                font-size: 3rem;
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
            
            .help-contacts {
                grid-template-columns: 1fr;
            }
            
            .tech-grid {
                grid-template-columns: 1fr;
            }
            
            .tech-item {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="error-500-container">
        <div class="error-header">
            <div class="error-code">500</div>
            <div class="error-icon">⚠️</div>
            <div class="error-title">Iekšējā servera kļūda</div>
        </div>
        
        <div class="error-content">
            <h1>Ups! Serverī radās neparedzēta kļūda</h1>
            
            <div class="error-description">
                <h3>Kas notika?</h3>
                <p>Serverī radās iekšēja kļūda, kas neļauj apstrādāt jūsu pieprasījumu. Mūsu tehniskie speciālisti ir informēti par šo problēmu.</p>
                
                <div class="error-id">
                    Kļūdas ID: <?php echo htmlspecialchars($error_id); ?>
                </div>
                
                <p><strong>Norādiet šo ID, sazinoties ar tehnisko atbalstu!</strong></p>
            </div>
            
            <div class="what-happened">
                <h3>Iespējamie cēloņi:</h3>
                <ul>
                    <li>Īslaicīga servera pārslodze</li>
                    <li>Datubāzes pieslēgšanās problēmas</li>
                    <li>Programmēšanas kļūda aplikācijā</li>
                    <li>Servera konfigurācijas problēma</li>
                    <li>Nepietiekama servera atmiņa</li>
                </ul>
            </div>
            
            <div class="what-to-do">
                <h3>Ko jūs varat darīt:</h3>
                <ol>
                    <li><strong>Uzgaidiet dažas minūtes</strong> un mēģiniet vēlreiz</li>
                    <li><strong>Atjaunojiet lapu</strong> (Ctrl+F5 vai Cmd+R)</li>
                    <li><strong>Notīriet pārlūka kešu</strong> un mēģiniet no jauna</li>
                    <li><strong>Mēģiniet izmantot citu pārlūkprogrammu</strong></li>
                    <li><strong>Sazinieties ar tehnisko atbalstu</strong> ar kļūdas ID</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <button onclick="location.reload()" class="btn btn-primary">
                    <span>🔄</span> Atjaunot lapu
                </button>
                
                <button onclick="goBack()" class="btn btn-secondary">
                    <span>⬅️</span> Atgriezties atpakaļ
                </button>
                
                <?php if ($config_loaded): ?>
                    <a href="<?php echo $isLoggedIn ? 'index.php' : 'login.php'; ?>" class="btn btn-success">
                        <span>🏠</span> <?php echo $isLoggedIn ? 'Uz sākumu' : 'Pieslēgties'; ?>
                    </a>
                <?php else: ?>
                    <a href="/mehi/" class="btn btn-success">
                        <span>🏠</span> Uz sākumu
                    </a>
                <?php endif; ?>
                
                <a href="mailto:support@avoti.lv?subject=Servera kļūda - <?php echo urlencode($error_id); ?>&body=Kļūdas ID: <?php echo urlencode($error_id); ?>%0AAdrese: <?php echo urlencode($request_uri); ?>%0ALaiks: <?php echo urlencode($error_time); ?>" class="btn btn-secondary">
                    <span>📧</span> Rakstīt atbalstam
                </a>
            </div>
            
            <div class="help-section">
                <h3>Nepieciešama palīdzība?</h3>
                <div class="help-contacts">
                    <div class="contact-item">
                        <strong>📞 Tehniskais atbalsts</strong>
                        <span>+371 1234-5678</span>
                    </div>
                    <div class="contact-item">
                        <strong>📧 E-pasts</strong>
                        <span>support@avoti.lv</span>
                    </div>
                    <div class="contact-item">
                        <strong>🕒 Darba laiks</strong>
                        <span>P-Pk 8:00-17:00</span>
                    </div>
                    <div class="contact-item">
                        <strong>⏰ Ārkārtas atbalsts</strong>
                        <span>+371 2345-6789</span>
                    </div>
                </div>
            </div>
            
            <details class="technical-details">
                <summary>📋 Tehniskā informācija (administratoriem)</summary>
                <div class="tech-info">
                    <div class="tech-grid">
                        <div>
                            <div class="tech-item">
                                <span class="tech-label">Kļūdas ID:</span>
                                <span class="tech-value"><?php echo htmlspecialchars($error_id); ?></span>
                            </div>
                            <div class="tech-item">
                                <span class="tech-label">Laiks:</span>
                                <span class="tech-value"><?php echo htmlspecialchars($error_time); ?></span>
                            </div>
                            <div class="tech-item">
                                <span class="tech-label">Adrese:</span>
                                <span class="tech-value"><?php echo htmlspecialchars($request_uri); ?></span>
                            </div>
                            <div class="tech-item">
                                <span class="tech-label">Serveris:</span>
                                <span class="tech-value"><?php echo htmlspecialchars($server_name); ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <div class="tech-item">
                                <span class="tech-label">IP adrese:</span>
                                <span class="tech-value"><?php echo htmlspecialchars($remote_addr); ?></span>
                            </div>
                            <?php if ($isLoggedIn && $currentUser): ?>
                                <div class="tech-item">
                                    <span class="tech-label">Lietotājs:</span>
                                    <span class="tech-value"><?php echo htmlspecialchars($currentUser['lietotajvards']); ?></span>
                                </div>
                                <div class="tech-item">
                                    <span class="tech-label">Loma:</span>
                                    <span class="tech-value"><?php echo htmlspecialchars($currentUser['loma']); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="tech-item">
                                    <span class="tech-label">Lietotājs:</span>
                                    <span class="tech-value">Nav pieslēdzies</span>
                                </div>
                            <?php endif; ?>
                            <div class="tech-item">
                                <span class="tech-label">Sesijas ID:</span>
                                <span class="tech-value"><?php echo session_id() ? substr(session_id(), 0, 8) . '...' : 'Nav aktīva'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tech-item" style="margin-top: 1rem;">
                        <span class="tech-label">Pārlūkprogramma:</span>
                        <span class="tech-value"><?php echo htmlspecialchars(substr($user_agent, 0, 100) . (strlen($user_agent) > 100 ? '...' : '')); ?></span>
                    </div>
                    
                    <div class="tech-item">
                        <span class="tech-label">Config ielādēts:</span>
                        <span class="tech-value"><?php echo $config_loaded ? 'Jā' : 'Nē'; ?></span>
                    </div>
                    
                    <div class="tech-item">
                        <span class="tech-label">PHP versija:</span>
                        <span class="tech-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div class="tech-item">
                        <span class="tech-label">Atmiņas limits:</span>
                        <span class="tech-value"><?php echo ini_get('memory_limit'); ?></span>
                    </div>
                </div>
            </details>
        </div>
    </div>
    
    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '<?php echo $config_loaded && $isLoggedIn ? "/mehi/index.php" : "/mehi/"; ?>';
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.metaKey && e.key === 'r')) {
                // Let browser handle refresh
                return;
            }
            
            if (e.key === 'Escape') {
                goBack();
            }
            
            if (e.altKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = '<?php echo $config_loaded && $isLoggedIn ? "/mehi/index.php" : "/mehi/"; ?>';
            }
        });
        
        // Auto-retry mechanism (wait 30 seconds, then offer to retry)
        setTimeout(function() {
            if (confirm('Vai vēlaties automātiski mēģināt ielādēt lapu no jauna?')) {
                location.reload();
            }
        }, 30000);
        
        // Log error on client side (if console available)
        if (typeof console !== 'undefined' && console.error) {
            console.error('500 Server Error - ID: <?php echo $error_id; ?>', {
                timestamp: '<?php echo $error_time; ?>',
                uri: '<?php echo addslashes($request_uri); ?>',
                userAgent: '<?php echo addslashes(substr($user_agent, 0, 100)); ?>'
            });
        }
    </script>
</body>
</html>