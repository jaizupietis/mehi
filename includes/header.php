<?php
// Debug informācija Android aplikācijām
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_android = (strpos($user_agent, 'CapacitorWebView') !== false || 
              strpos($user_agent, 'AVOTI TMS') !== false ||
              strpos($user_agent, 'Android') !== false);

if ($is_android) {
    error_log("Header check - Android detected. Session ID: " . session_id());
    error_log("Header check - Session data exists: " . (isset($_SESSION['lietotaja_id']) ? 'YES' : 'NO'));
    if (isset($_SESSION['lietotaja_id'])) {
        error_log("Header check - User ID in session: " . $_SESSION['lietotaja_id']);
    }
}

if (!isLoggedIn()) {
    if ($is_android) {
        error_log("Header check - User not logged in, redirecting to login.php");
    }
    redirect('login.php');
}

$currentUser = getCurrentUser();

if ($is_android && !$currentUser) {
    error_log("Header check - getCurrentUser() returned null for Android user");
}

$unreadNotifications = getUnreadNotificationCount($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle ? $pageTitle . ' - AVOTI TMS' : 'AVOTI TMS'; ?></title>

    <!-- PWA Meta Tags -->
    <meta name="application-name" content="AVOTI TMS">
    <meta name="apple-mobile-web-app-title" content="AVOTI TMS">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2c3e50">
    <meta name="msapplication-navbutton-color" content="#2c3e50">
    <meta name="msapplication-starturl" content="/mehi/">

    <!-- Prevent zoom on input focus -->
    <meta name="format-detection" content="telephone=no">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicons and PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/icon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/images/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/images/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/images/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/images/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/images/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/images/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/images/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="57x57" href="assets/images/icon-72x72.png">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- App JavaScript -->
    <script src="assets/js/app.js" defer></script>

    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="description" content="AVOTI uzdevumu pārvaldības sistēma">
    <meta name="robots" content="noindex, nofollow">

	 <script>
        // Service Worker reģistrācija ar pareizu ceļu
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/mehi/assets/js/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');

                        // Pēc veiksmīgas reģistrācijas, iestatīt push notifications
                        setupPushNotifications(registration);
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }

        // Push notification setup
        function setupPushNotifications(registration) {
            if (!('PushManager' in window)) {
                console.log('Push messaging is not supported');
                return;
            }

            // Pārbaudīt vai jau ir subscription
            registration.pushManager.getSubscription()
                .then(function(subscription) {
                    if (subscription) {
                        console.log('Already subscribed to push notifications');
                        return;
                    }

                    // Pieprasīt paziņojumu atļaujas
                    return Notification.requestPermission()
                        .then(function(permission) {
                            if (permission === 'granted') {
                                return subscribeToPush(registration);
                            }
                            console.log('Notification permission denied');
                        });
                })
                .catch(function(error) {
                    console.log('Error setting up push notifications:', error);
                });
        }

        function subscribeToPush(registration) {
            // Vienkārša VAPID key (publiskā)
            const applicationServerKey = urlBase64ToUint8Array(
                'BEl62iUYgUivxIkv69yViEuiBIa40HI80Y4K_7_Bt_O0WQZB2MklLH3MkZGBCJW1vhDLCHGEIwWjGMnqFtgDJgU'
            );

            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            })
            .then(function(subscription) {
                console.log('Push subscription successful:', subscription);

                // Nosūtīt subscription uz serveri
                return sendSubscriptionToServer(subscription);
            })
            .catch(function(error) {
                console.log('Failed to subscribe to push notifications:', error);
            });
        }

        function sendSubscriptionToServer(subscription) {
            return fetch('/mehi/ajax/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    subscription: subscription
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                console.log('Subscription saved to server:', data);
            })
            .catch(function(error) {
                console.log('Error saving subscription to server:', error);
            });
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    </script>
    <script>
        // Pieprasīt paziņojumu atļaujas
        if ('Notification' in window && Notification.permission === 'default') {
            // Pieprasīt atļaujas pēc lietotāja pieteikšanās
            setTimeout(() => {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                });
            }, 3000);
        }
    </script>
    <!-- Paziņojumu manuālā pārbaude -->
<script>
// Atjaunot paziņojumu skaitu periodiski un reālajā laikā
function updateNotificationCount() {
    fetch('/mehi/ajax/get_notification_count.php', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-cache'
    })
    .then(response => response.json())
    .then(data => {
        if (data.count !== undefined) {
            updateNotificationBadge(data.count);
        }
    })
    .catch(error => {
        console.error('Error updating notification count:', error);
    });
}

function updateNotificationBadge(count) {
    const notificationLink = document.querySelector('a[href="notifications.php"]');
    const badge = document.querySelector('.notification-badge .badge');
    const notificationBadgeContainer = document.querySelector('.notification-badge');

    if (notificationLink) {
        // Atjaunot tekstu navigācijas linkā
        const linkText = notificationLink.textContent.replace(/\(\d+\)/, '');
        if (count > 0) {
            notificationLink.textContent = linkText.trim() + ` (${count})`;
        } else {
            notificationLink.textContent = linkText.trim();
        }
    }

    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline';
            if (notificationBadgeContainer) {
                notificationBadgeContainer.style.display = 'inline-block';
            }
        } else {
            badge.style.display = 'none';
            if (notificationBadgeContainer) {
                notificationBadgeContainer.style.display = 'none';
            }
        }
    }
}

// Atjaunot paziņojumu skaitu periodiski (retāk - katras 30 sekundes)
function startNotificationPolling() {
    updateNotificationCount();
    setInterval(updateNotificationCount, 30000); // 30 sekundes
}

// Sākt polling kad lapa ielādējas
document.addEventListener('DOMContentLoaded', function() {
    startNotificationPolling();
});
</script>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
					<div class="header-logo">
						<img src="assets/images/logo_bez_bg.png" alt="AVOTI Logo" style="width: 100%; height: 100%; object-fit: contain;">
					</div>
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
                   <li><a href="regular_tasks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'regular_tasks.php' ? 'class="active"' : ''; ?>>Regulārie uzdevumi</a>
                    <li><a href="work_schedule.php" <?php echo basename($_SERVER['PHP_SELF']) == 'work_schedule.php' ? 'class="active"' : ''; ?>>Darba grafiks</a></li>
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

                <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                    <li><a href="reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'class="active"' : ''; ?>>Atskaites</a></li>
                <?php endif; ?>

                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <li><a href="users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'class="active"' : ''; ?>>Lietotāji</a></li>
                    <li><a href="settings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'class="active"' : ''; ?>>Iestatījumi</a></li>
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