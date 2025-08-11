// AVOTI TMS App JavaScript
import { Capacitor } from '@capacitor/core';
import { StatusBar, Style } from '@capacitor/status-bar';
import { SplashScreen } from '@capacitor/splash-screen';
import { PushNotifications } from '@capacitor/push-notifications';
import { LocalNotifications } from '@capacitor/local-notifications';
import { Device } from '@capacitor/device';
import { Network } from '@capacitor/network';

document.addEventListener('DOMContentLoaded', function() {
    // Inicializēt Capacitor, ja darbojamies kā natīva app
    if (Capacitor.isNativePlatform()) {
        initNativeApp();
    }

    // PWA instalācijas funkcionalitāte
    initPWA();

    // Offline/Online status
    updateOnlineStatus();
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    // Mobilā navigācija
    initMobileNavigation();

    // Reāllaika paziņojumu sistēma
    initRealTimeNotifications();
});

// Natīvās aplikācijas inicializācija
async function initNativeApp() {
    try {
        // Konfigurēt status bar
        await StatusBar.setStyle({ style: Style.Light });
        await StatusBar.setBackgroundColor({ color: '#2c3e50' });
        
        // Paslēpt splash screen
        await SplashScreen.hide();
        
        // Iegūt ierīces informāciju
        const deviceInfo = await Device.getInfo();
        console.log('Device info:', deviceInfo);
        
        // Inicializēt push paziņojumus natīvai aplikācijai
        await initNativePushNotifications();
        
        // Network status monitoring
        Network.addListener('networkStatusChange', status => {
            console.log('Network status changed', status);
            updateOnlineStatus(status.connected);
        });
        
        // Back button handling for Android
        if (deviceInfo.platform === 'android') {
            document.addEventListener('ionBackButton', (ev) => {
                ev.detail.register(-1, () => {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        // Close app
                        (navigator as any).app?.exitApp();
                    }
                });
            });
        }
        
    } catch (error) {
        console.error('Error initializing native app:', error);
    }
}

// Natīvo push paziņojumu inicializācija
async function initNativePushNotifications() {
    try {
        // Request permissions
        const permission = await PushNotifications.requestPermissions();
        
        if (permission.receive === 'granted') {
            // Register for push notifications
            await PushNotifications.register();
            
            // Listen for registration token
            PushNotifications.addListener('registration', token => {
                console.log('Push registration success, token: ', token.value);
                // Send token to server
                savePushToken(token.value);
            });
            
            // Listen for push notifications
            PushNotifications.addListener('pushNotificationReceived', notification => {
                console.log('Push notification received: ', notification);
                
                // Show local notification if app is in foreground
                LocalNotifications.schedule({
                    notifications: [
                        {
                            title: notification.title || 'AVOTI TMS',
                            body: notification.body || 'Jums ir jauns paziņojums',
                            id: Date.now(),
                            schedule: { at: new Date(Date.now() + 1000) },
                            sound: 'default',
                            attachments: notification.data?.attachments,
                            actionTypeId: 'OPEN_NOTIFICATION',
                            extra: notification.data
                        }
                    ]
                });
            });
            
            // Handle notification tap
            PushNotifications.addListener('pushNotificationActionPerformed', notification => {
                console.log('Push notification action performed: ', notification.actionId, notification.inputValue);
                
                // Handle notification tap - navigate to relevant page
                if (notification.notification.data?.url) {
                    window.location.href = notification.notification.data.url;
                }
            });
        }
    } catch (error) {
        console.error('Error setting up native push notifications:', error);
    }
}

// Saglabāt push token serverī
async function savePushToken(token) {
    try {
        await fetch('/mehi/ajax/save_push_subscription.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                platform: 'android_native'
            })
        });
    } catch (error) {
        console.error('Error saving push token:', error);
    }
}

// Datuma formatēšanas funkcijas
function formatDateLV(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
}

function formatDateTimeLV(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

// PWA un notifikāciju funkcionalitāte
function initPWA() {
    // Reģistrēt Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/mehi/assets/js/sw.js')
            .then(function(registration) {
                console.log('Service Worker reģistrēts:', registration.scope);
            })
            .catch(function(error) {
                console.log('Service Worker reģistrācijas kļūda:', error);
            });
    }

    // PWA instalācijas prompt
    let deferredPrompt;

    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        console.log('PWA jau ir instalēta');
        document.body.classList.add('pwa-mode');
    }

    window.addEventListener('beforeinstallprompt', function(e) {
        console.log('beforeinstallprompt notikums saņemts');
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });

    // Manual check for PWA installability
    if ('getInstalledRelatedApps' in navigator) {
        navigator.getInstalledRelatedApps().then(relatedApps => {
            if (relatedApps.length === 0) {
                // Not installed, show manual install option
                setTimeout(showManualInstallInstructions, 3000);
            }
        });
    } else {
        // Fallback for browsers that don't support automatic detection
        setTimeout(showManualInstallInstructions, 3000);
    }

    // Instalācijas poga
    function showInstallButton() {
        // Pārbaudīt vai jau ir rādīts
        if (localStorage.getItem('installButtonShown') === 'true') {
            return;
        }

        const installBtn = document.createElement('button');
        installBtn.textContent = '📱 Instalēt aplikāciju';
        installBtn.className = 'btn btn-primary install-btn';
        installBtn.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 25px;
            padding: 10px 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;

        installBtn.addEventListener('click', function() {
            installBtn.style.display = 'none';
            localStorage.setItem('installButtonShown', 'true');
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(choiceResult) {
                if (choiceResult.outcome === 'accepted') {
                    console.log('Lietotājs instalēja PWA');
                }
                deferredPrompt = null;
            });
        });

        document.body.appendChild(installBtn);

        // Atzīmēt kā rādītu
        localStorage.setItem('installButtonShown', 'true');

        // Paslēpt pēc 10 sekundēm ja netiek klikšķināts
        setTimeout(function() {
            if (installBtn && installBtn.parentNode) {
                installBtn.style.display = 'none';
            }
        }, 10000);
    }

    // Manual installation instructions
    function showManualInstallInstructions() {
        if (window.matchMedia('(display-mode: standalone)').matches) {
            return; // Already installed
        }

        // Pārbaudīt vai jau ir rādīts
        if (localStorage.getItem('installInstructionsShown') === 'true') {
            return;
        }

        const isAndroid = /Android/i.test(navigator.userAgent);
        const isChrome = /Chrome/i.test(navigator.userAgent);

        if (isAndroid && isChrome) {
            const instructionDiv = document.createElement('div');
            instructionDiv.innerHTML = `
                <div style="position: fixed; bottom: 60px; left: 20px; right: 20px; 
                           background: rgba(0,0,0,0.9); color: white; padding: 15px; 
                           border-radius: 10px; z-index: 1002; font-size: 14px;">
                    <strong>📱 Instalēt kā aplikāciju:</strong><br>
                    1. Nospiediet izvēlni (⋮) Chrome<br>
                    2. Izvēlieties "Pievienot sākuma ekrānam"<br>
                    3. Apstipriniet instalāciju<br>
                    <button onclick="this.parentElement.remove(); localStorage.setItem('installInstructionsShown', 'true');" style="float: right; margin-top: 5px; background: #007cba; color: white; border: none; padding: 5px 10px; border-radius: 3px;">Aizvērt</button>
                </div>
            `;
            document.body.appendChild(instructionDiv);

            // Atzīmēt kā rādītu
            localStorage.setItem('installInstructionsShown', 'true');

            // Auto hide after 15 seconds
            setTimeout(() => {
                if (instructionDiv.parentNode) {
                    instructionDiv.remove();
                }
            }, 15000);
        }
    }
}

// Online/Offline status
function updateOnlineStatus() {
    const statusIndicator = document.querySelector('.connection-status') || createStatusIndicator();

    if (navigator.onLine) {
        statusIndicator.textContent = '🟢 Pieslēgts';
        statusIndicator.className = 'connection-status online';
    } else {
        statusIndicator.textContent = '🔴 Bezsaistē';
        statusIndicator.className = 'connection-status offline';
    }
}

function createStatusIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'connection-status';
    indicator.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        z-index: 1001;
        background: rgba(255,255,255,0.9);
        border: 1px solid #ccc;
    `;
    document.body.appendChild(indicator);
    return indicator;
}

// Mobilā navigācija
function initMobileNavigation() {
    // Hamburger izvēlne mobilajām ierīcēm
    const nav = document.querySelector('nav .nav-menu');
    if (nav && window.innerWidth <= 768) {
        const hamburger = document.createElement('button');
        hamburger.innerHTML = '☰';
        hamburger.className = 'mobile-menu-toggle';
        hamburger.style.cssText = `
            display: block;
            background: none;
            border: none;
            font-size: 24px;
            padding: 10px;
            cursor: pointer;
        `;

        hamburger.addEventListener('click', function() {
            nav.style.display = nav.style.display === 'none' ? 'flex' : 'none';
        });

        nav.parentNode.insertBefore(hamburger, nav);
        nav.style.display = 'none';
    }
}

// Swipe funkcionalitāte mobilajām ierīcēm
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    if (touchEndX < touchStartX - 50) {
        // Swipe left - varētu atvērt izvēlni
        console.log('Swipe left');
    }
    if (touchEndX > touchStartX + 50) {
        // Swipe right - varētu aizvērt izvēlni
        console.log('Swipe right');
    }
}

// Form validation enhancement for mobile
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                field.style.borderColor = '#e74c3c';
                isValid = false;
            } else {
                field.style.borderColor = '';
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Lūdzu aizpildiet visus obligātos laukus!');
        }
    });
});

// Vienkārša push paziņojumu sistēma
let notificationPollingInterval = null;
let lastNotificationCheck = localStorage.getItem('lastNotificationCheck') || '0';

function initRealTimeNotifications() {
    // Pieprasīt push paziņojumu atļaujas
    requestNotificationPermission();
    
    // Sākt vienkāršu polling (ik 30 sekundes)
    startSimplePushNotifications();
}

// SSE funkcijas noņemtas - izmantojam vienkāršu polling

function showToastNotification(notification) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <div class="toast-header">
            <strong>${escapeHtml(notification.virsraksts)}</strong>
            <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
        <div class="toast-body">
            ${escapeHtml(notification.zinojums)}
        </div>
    `;

    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-left: 4px solid #3498db;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        padding: 0;
        max-width: 350px;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;

    document.body.appendChild(toast);

    // Auto remove after 8 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }, 8000);
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function requestNotificationPermission() {
    if ('Notification' in window) {
        if (Notification.permission === 'default') {
            // Pieprasīt atļaujas pēc 3 sekundēm
            setTimeout(() => {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                    if (permission === 'granted') {
                        showToastNotification({
                            virsraksts: 'Paziņojumi ieslēgti',
                            zinojums: 'Jūs saņemsiet Chrome paziņojumus par jauniem uzdevumiem un problēmām'
                        });
                    }
                });
            }, 3000);
        }
    }
}

function startSimplePushNotifications() {
    // Pārbaudīt paziņojumus biežāk - ik 15 sekundes
    if (notificationPollingInterval) {
        clearInterval(notificationPollingInterval);
    }
    
    notificationPollingInterval = setInterval(checkForNewNotifications, 15000);
    
    // Pārbaudīt uzreiz
    setTimeout(checkForNewNotifications, 1000);
    
    // Pārbaudīt arī kad logs atgūst fokusu
    window.addEventListener('focus', function() {
        setTimeout(checkForNewNotifications, 500);
    });
}

function checkForNewNotifications() {
    fetch('/mehi/ajax/simple_push_notification.php', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-cache'
    })
    .then(response => response.json())
    .then(data => {
        if (data.hasNew && data.notifications) {
            data.notifications.forEach(notification => {
                showChromeNotification(notification);
            });
            
            // Saglabāt laiku
            localStorage.setItem('lastNotificationCheck', Date.now().toString());
        }
        
        // Uzreiz atjaunot badge pēc jauniem paziņojumiem
        if (data.hasNew && data.notifications) {
            // Nekavējoties atjaunot badge skaitu
            fetch('/mehi/ajax/get_notification_count.php', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate'
                }
            })
            .then(response => response.json())
            .then(countData => {
                if (countData.count !== undefined) {
                    updateNotificationBadge(countData.count);
                    updateNavigationNotificationCount(countData.count);
                }
            })
            .catch(err => console.error('Badge update error:', err));
            
            // Un vēl vienu reizi pēc īsa brīža, lai pārliecinātos
            setTimeout(() => {
                fetch('/mehi/ajax/get_notification_count.php', {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-cache',
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    }
                })
                .then(response => response.json())
                .then(countData => {
                    if (countData.count !== undefined) {
                        updateNotificationBadge(countData.count);
                        updateNavigationNotificationCount(countData.count);
                    }
                })
                .catch(err => console.error('Badge delayed update error:', err));
            }, 1000);
        }
    })
    .catch(error => {
        console.error('Notification check error:', error);
        
        // Fallback uz paziņojumu skaitītāja atjaunināšanu
        fetch('/mehi/ajax/get_notification_count.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                updateNotificationBadge(data.count);
                updateNavigationNotificationCount(data.count);
            }
        })
        .catch(err => console.error('Fallback notification count error:', err));
    });
}

function updateNavigationNotificationCount(count) {
    const notificationLink = document.querySelector('a[href="notifications.php"]');
    if (notificationLink) {
        const linkText = notificationLink.textContent.replace(/\s*\(\d+\)/, '');
        if (count > 0) {
            notificationLink.textContent = linkText.trim() + ` (${count})`;
        } else {
            notificationLink.textContent = linkText.trim();
        }
    }
}

function showChromeNotification(notificationData) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            tag: notificationData.tag,
            requireInteraction: notificationData.requireInteraction,
            vibrate: notificationData.vibrate,
            data: notificationData.data
        });

        notification.onclick = function() {
            window.focus();
            if (notificationData.data && notificationData.data.url) {
                window.location.href = notificationData.data.url;
            }
            notification.close();
        };

        // Aizvērt pēc 8 sekundēm
        setTimeout(() => {
            notification.close();
        }, 8000);
    }
    
    // Arī parādīt toast paziņojumu lapā
    showToastNotification({
        virsraksts: notificationData.title,
        zinojums: notificationData.body
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Pievienot CSS animācijas
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .toast-notification {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        overflow: hidden;
    }

    .toast-header {
        background: #f8f9fa;
        padding: 8px 12px;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }

    .toast-body {
        padding: 12px;
        font-size: 13px;
        color: #333;
    }

    .toast-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #999;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .toast-close:hover {
        color: #333;
    }

    @media (max-width: 768px) {
        .toast-notification {
            right: 10px !important;
            left: 10px !important;
            max-width: calc(100vw - 20px) !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .toast-body {
            font-size: 12px !important;
            line-height: 1.4;
            word-break: break-word;
        }
        
        .toast-header {
            font-size: 13px !important;
        }
    }
    
    @media (max-width: 480px) {
        .toast-notification {
            right: 5px !important;
            left: 5px !important;
            max-width: calc(100vw - 10px) !important;
        }
        
        .toast-body {
            font-size: 11px !important;
            padding: 8px !important;
        }
        
        .toast-header {
            font-size: 12px !important;
            padding: 6px 8px !important;
        }
    }
`;
document.head.appendChild(style);