<?php
// Iestatīt HTTP statusu
http_response_code(500);

// Nekad nerādīt PHP kļūdas uz šīs lapas
ini_set('display_errors', 0);
error_reporting(0);

// Loģēt kļūdu
error_log("500 error accessed at " . date('Y-m-d H:i:s') . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$pageTitle = 'Servera kļūda';
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AVOTI Task Management</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --success-color: #27ae60;
            --info-color: #3498db;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 3rem;
            --border-radius: 6px;
            --border-radius-lg: 12px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
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
            padding: var(--spacing-lg);
        }

        .error-container {
            max-width: 600px;
            width: 100%;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-xl);
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

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

        .error-icon {
            margin-bottom: var(--spacing-lg);
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

        .error-container h1 {
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
            background: var(--gray-100);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--danger-color);
            margin-bottom: var(--spacing-xl);
            text-align: left;
        }

        .error-details h3 {
            color: var(--gray-800);
            margin-bottom: var(--spacing-md);
            font-size: 1.2rem;
        }

        .error-details ul {
            margin: 0;
            padding-left: var(--spacing-lg);
        }

        .error-details li {
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

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-md) var(--spacing-lg);
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
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
            background: var(--gray-500);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-1px);
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
            border-left: 3px solid var(--danger-color);
        }

        .status-info {
            background: var(--gray-50);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-lg);
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .status-info strong {
            color: var(--gray-800);
        }

        /* Responsive dizains */
        @media (max-width: 768px) {
            .error-container {
                padding: var(--spacing-lg);
                margin: var(--spacing-md);
            }
            
            .error-code {
                font-size: 3rem;
            }
            
            .error-symbol {
                font-size: 2rem;
            }
            
            .error-container h1 {
                font-size: 1.5rem;
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
        }

        @media (max-width: 480px) {
            body {
                padding: var(--spacing-md);
            }
            
            .error-container {
                padding: var(--spacing-md);
            }
            
            .error-details,
            .help-section {
                padding: var(--spacing-md);
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <span class="error-code">500</span>
            <span class="error-symbol">⚠️</span>
        </div>
        
        <h1>Servera kļūda</h1>
        
        <div class="error-message">
            <p>Atvainojamies, bet radusies servera kļūda, kas neļauj apstrādāt jūsu pieprasījumu.</p>
            <p>Mūsu tehniskā komanda ir informēta par problēmu un strādā pie tās novēršanas.</p>
        </div>
        
        <div class="status-info">
            <strong>Incidenta ID:</strong> <?php echo uniqid('ERR-'); ?><br>
            <strong>Laiks:</strong> <?php echo date('d.m.Y H:i:s'); ?><br>
            <strong>IP adrese:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Nav zināma'; ?>
        </div>
        
        <div class="error-details">
            <h3>Ko jūs varat darīt?</h3>
            <ul>
                <li>Uzgaidiet dažas minūtes un mēģiniet vēlreiz</li>
                <li>Pārbaudiet, vai URL adrese ir pareiza</li>
                <li>Atgriezieties uz iepriekšējo lapu</li>
                <li>Sazināties ar tehnikas atbalstu, ja problēma atkārtojas</li>
                <li>Restartējiet pārlūkprogrammu</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <span>⬅️</span> Atpakaļ
            </a>
            
            <a href="/" class="btn btn-primary">
                <span>🏠</span> Sākuma lapa
            </a>
            
            <button onclick="location.reload()" class="btn btn-danger">
                <span>🔄</span> Mēģināt vēlreiz
            </button>
        </div>
        
        <div class="help-section">
            <h3>Nepieciešama palīdzība?</h3>
            <div class="help-contacts">
                <div class="contact-item">
                    <strong>📞 Neatliekamā palīdzība:</strong>
                    <span>+371 1234-5678</span>
                </div>
                <div class="contact-item">
                    <strong>📧 Tehniskais atbalsts:</strong>
                    <span>support@avoti.lv</span>
                </div>
                <div class="contact-item">
                    <strong>👨‍💼 Sistēmas administrators:</strong>
                    <span>admin@avoti.lv</span>
                </div>
                <div class="contact-item">
                    <strong>🌐 Uzņēmuma mājaslapa:</strong>
                    <span>www.avoti.lv</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Automātiski atjaunot lapu pēc 30 sekundēm
        setTimeout(function() {
            if (confirm('Vai vēlaties automātiski atjaunot lapu?')) {
                location.reload();
            }
        }, 30000);

        // Tastatūras saīsnes
        document.addEventListener('keydown', function(e) {
            // ESC - atgriezties atpakaļ
            if (e.key === 'Escape') {
                history.back();
            }
            
            // F5 vai Ctrl+R - atjaunot
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                location.reload();
            }
            
            // Alt+H - uz sākuma lapu
            if (e.altKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = '/';
            }
        });

        // Loģēt kļūdu uz konsoli (izstrādē)
        console.error('500 Internal Server Error occurred at:', new Date().toISOString());
        console.info('If you are a developer, check the server logs for more details.');
    </script>
</body>
</html>