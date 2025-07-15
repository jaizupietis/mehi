<?php
require_once 'config.php';

// Ja lietotājs jau ir pieslēdzies, novirzīt uz sākuma lapu
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lietotajvards = sanitizeInput($_POST['lietotajvards'] ?? '');
    $parole = $_POST['parole'] ?? '';
    
    if (empty($lietotajvards) || empty($parole)) {
        $error = 'Lūdzu ievadiet lietotājvārdu un paroli.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, lietotajvards, parole, vards, uzvards, loma, statuss 
                FROM lietotaji 
                WHERE lietotajvards = ? AND statuss = 'Aktīvs'
            ");
            $stmt->execute([$lietotajvards]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($parole, $user['parole'])) {
                // Veiksmīga pieslēgšanās
                $_SESSION['lietotaja_id'] = $user['id'];
                $_SESSION['lietotajvards'] = $user['lietotajvards'];
                $_SESSION['vards'] = $user['vards'];
                $_SESSION['uzvards'] = $user['uzvards'];
                $_SESSION['loma'] = $user['loma'];
                
                // Atjaunot pēdējās pieslēgšanās laiku
                $updateStmt = $pdo->prepare("UPDATE lietotaji SET pēdējā_pieslēgšanās = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Novirzīt uz sākuma lapu
                redirect('index.php');
            } else {
                $error = 'Nepareizs lietotājvārds vai parole.';
            }
        } catch (PDOException $e) {
            $error = 'Sistēmas kļūda. Lūdzu mēģiniet vēlāk.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getPageTitle('Pieslēgšanās'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: var(--spacing-md);
        }
        
        .login-card {
            background: var(--white);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .login-header h1 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
            font-size: var(--font-size-xl);
        }
        
        .login-header p {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: var(--secondary-color);
            border-radius: 50%;
            margin: 0 auto var(--spacing-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            font-weight: bold;
        }
        
        .login-form .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .login-form .form-control {
            padding: var(--spacing-md);
            font-size: var(--font-size-base);
        }
        
        .login-btn {
            width: 100%;
            padding: var(--spacing-md);
            font-size: var(--font-size-base);
            font-weight: 600;
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-lg);
            text-align: center;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .login-footer {
            text-align: center;
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--gray-300);
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: var(--spacing-lg);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="company-logo">A</div>
                <h1><?php echo SITE_NAME; ?></h1>
                <p><?php echo COMPANY_NAME; ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="lietotajvards" class="form-label">Lietotājvārds</label>
                    <input 
                        type="text" 
                        id="lietotajvards" 
                        name="lietotajvards" 
                        class="form-control" 
                        required 
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($_POST['lietotajvards'] ?? ''); ?>"
                        placeholder="Ievadiet lietotājvārdu"
                    >
                </div>
                
                <div class="form-group">
                    <label for="parole" class="form-label">Parole</label>
                    <input 
                        type="password" 
                        id="parole" 
                        name="parole" 
                        class="form-control" 
                        required 
                        autocomplete="current-password"
                        placeholder="Ievadiet paroli"
                    >
                </div>
                
                <button type="submit" class="btn btn-primary login-btn">
                    Pieslēgties
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. Visas tiesības aizsargātas.</p>
                <p><small>Versija 1.0</small></p>
            </div>
        </div>
    </div>
    
    <script>
        // Fokusēt uz lietotājvārda lauku
        document.getElementById('lietotajvards').focus();
        
        // Parādīt/paslēpt paroli funkcionalitāte (pievienot vēlāk)
        function togglePassword() {
            const passwordField = document.getElementById('parole');
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
        }
    </script>
</body>
</html>