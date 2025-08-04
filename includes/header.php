<?php
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentUser = getCurrentUser();
$unreadNotifications = getUnreadNotificationCount($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? getPageTitle($pageTitle) : getPageTitle(); ?></title>

	 <!-- PWA Meta tags -->
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="AVOTI TMS">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Favicons and PWA Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" href="assets/images/icon-192x192.png">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Push Notifications JavaScript -->
    <script src="assets/js/push-notifications.js" defer></script>

    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="description" content="AVOTI uzdevumu pārvaldības sistēma">
    <meta name="robots" content="noindex, nofollow">

	 <script>
        // Service Worker reģistrācija
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    AVOTI TMS
                </a>
                
                <div class="user-info">
                    <?php if ($unreadNotifications > 0): ?>
                        <div class="notification-badge" onclick="window.location.href='notifications.php'">
                            <span>🔔</span>
                            <span class="badge"><?php echo $unreadNotifications; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <span class="username">
                        <?php echo htmlspecialchars($currentUser['vards'] . ' ' . $currentUser['uzvards']); ?>
                    </span>
                    
                    <span class="role">
                        <?php echo htmlspecialchars($currentUser['loma']); ?>
                    </span>
                    
                    <a href="profile.php" class="btn btn-secondary btn-sm">Profils</a>
                    <a href="logout.php" class="btn btn-danger btn-sm">Iziet</a>
                </div>
            </div>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul class="nav-menu">
                <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Sākums</a></li>
                
                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                    <li><a href="tasks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'class="active"' : ''; ?>>Uzdevumi</a></li>
                    <li><a href="problems.php" <?php echo basename($_SERVER['PHP_SELF']) == 'problems.php' ? 'class="active"' : ''; ?>>Problēmas</a></li>
                    <li><a href="create_task.php" <?php echo basename($_SERVER['PHP_SELF']) == 'create_task.php' ? 'class="active"' : ''; ?>>Izveidot uzdevumu</a></li>
                    <li><a href="regular_tasks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'regular_tasks.php' ? 'class="active"' : ''; ?>>Regulārie uzdevumi</a></li>
                <?php endif; ?>
                
                <?php if (hasRole(ROLE_OPERATOR)): ?>
                    <li><a href="report_problem.php" <?php echo basename($_SERVER['PHP_SELF']) == 'report_problem.php' ? 'class="active"' : ''; ?>>Ziņot problēmu</a></li>
                    <li><a href="my_problems.php" <?php echo basename($_SERVER['PHP_SELF']) == 'my_problems.php' ? 'class="active"' : ''; ?>>Manas problēmas</a></li>
                <?php endif; ?>
                
                <?php if (hasRole(ROLE_MECHANIC)): ?>
                    <li><a href="my_tasks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'my_tasks.php' ? 'class="active"' : ''; ?>>Mani uzdevumi</a></li>
                    <li><a href="regular_tasks_mechanic.php" <?php echo basename($_SERVER['PHP_SELF']) == 'regular_tasks_mechanic.php' ? 'class="active"' : ''; ?>>Regulārie uzdevumi</a></li>
                    <li><a href="completed_tasks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'completed_tasks.php' ? 'class="active"' : ''; ?>>Pabeigto uzdevumu vēsture</a></li>
                <?php endif; ?>
                
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <li><a href="users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'class="active"' : ''; ?>>Lietotāji</a></li>
                    <li><a href="settings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'class="active"' : ''; ?>>Iestatījumi</a></li>
                    <li><a href="reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'class="active"' : ''; ?>>Atskaites</a></li>
                <?php endif; ?>
                
                <li><a href="notifications.php" <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'class="active"' : ''; ?>>
                    Paziņojumi <?php echo $unreadNotifications > 0 ? "($unreadNotifications)" : ''; ?>
                </a></li>
            </ul>
        </div>
    </nav>
    
    <main>
        <div class="container">
            <?php
            // Parādīt flash ziņojumus
            $flashMessages = getFlashMessages();
            foreach ($flashMessages as $message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo htmlspecialchars($message['message']); ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (isset($pageHeader)): ?>
                <div class="page-header mb-4">
                    <h1><?php echo htmlspecialchars($pageHeader); ?></h1>
                    <?php if (isset($pageDescription)): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($pageDescription); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>