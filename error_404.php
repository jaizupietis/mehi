<?php
// 404.php - Lapa nav atrasta
http_response_code(404);

$pageTitle = '404 - Lapa nav atrasta';
$pageHeader = 'Lapa nav atrasta';

// Pārbaudīt vai lietotājs ir pieslēdzies
if (file_exists('config.php')) {
    require_once 'config.php';
    
    if (isLoggedIn()) {
        include 'includes/header.php';
        ?>
        <div class="error-page">
            <div class="error-content">
                <h1 class="error-code">404</h1>
                <h2 class="error-message">Lapa nav atrasta</h2>
                <p class="error-description">
                    Atvainojamies, bet meklētā lapa neeksistē vai ir pārvietota.
                </p>
                <div class="error-actions">
                    <a href="index.php" class="btn btn-primary">Atgriezties sākumā</a>
                    <a href="javascript:history.back()" class="btn btn-secondary">Atpakaļ</a>
                </div>
            </div>
        </div>
        <?php
        include 'includes/footer.php';
    } else {
        // Ja nav pieslēdzies, parādīt vienkāršu kļūdas lapu
        showSimpleErrorPage('404', 'Lapa nav atrasta', 'Meklētā lapa neeksistē vai ir pārvietota.');
    }
} else {
    showSimpleErrorPage('404', 'Lapa nav atrasta', 'Meklētā lapa neeksistē vai ir pārvietota.');
}

function showSimpleErrorPage($code, $title, $description) {
    ?>
    <!DOCTYPE html>
    <html lang="lv">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $code; ?> - <?php echo $title; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #333;
            }
            
            .error-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                padding: 3rem;
                text-align: center;
                max-width: 500px;
                width: 90%;
            }
            
            .error-code {
                font-size: 6rem;
                font-weight: bold;
                color: #e74c3c;
                margin-bottom: 1rem;
            }
            
            .error-title {
                font-size: 2rem;
                color: #2c3e50;
                margin-bottom: 1rem;
            }
            
            .error-description {
                color: #7f8c8d;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .error-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn {
                padding: 0.8rem 1.5rem;
                border: none;
                border-radius: 5px;
                text-decoration: none;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-primary {
                background: #3498db;
                color: white;
            }
            
            .btn-primary:hover {
                background: #2980b9;
            }
            
            .btn-secondary {
                background: #95a5a6;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #7f8c8d;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1 class="error-code"><?php echo $code; ?></h1>
            <h2 class="error-title"><?php echo $title; ?></h2>
            <p class="error-description"><?php echo $description; ?></p>
            <div class="error-actions">
                <a href="/" class="btn btn-primary">Sākums</a>
                <a href="javascript:history.back()" class="btn btn-secondary">Atpakaļ</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>

<?php if (isLoggedIn()): ?>
<style>
.error-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    text-align: center;
}

.error-content {
    max-width: 600px;
    padding: 2rem;
}

.error-code {
    font-size: 8rem;
    font-weight: bold;
    color: var(--danger-color);
    margin-bottom: 1rem;
}

.error-message {
    font-size: 2.5rem;
    color: var(--gray-800);
    margin-bottom: 1rem;
}

.error-description {
    color: var(--gray-600);
    font-size: 1.1rem;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .error-code {
        font-size: 5rem;
    }
    
    .error-message {
        font-size: 1.8rem;
    }
    
    .error-actions {
        flex-direction: column;
    }
}
</style>
<?php endif; ?>