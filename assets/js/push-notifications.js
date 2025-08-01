/**
 * Push Notifications Client-side Handler
 * AVOTI Task Management System
 */

class PushNotificationManager {
    constructor() {
        this.swRegistration = null;
        this.isSubscribed = false;
        this.applicationServerPublicKey = null;
        
        // Initialize when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    async init() {
        console.log('PushNotificationManager: Initializing...');
        
        // Check for service worker support
        if (!('serviceWorker' in navigator)) {
            console.warn('Service Worker not supported');
            return;
        }
        
        // Check for push notifications support
        if (!('PushManager' in window)) {
            console.warn('Push messaging not supported');
            return;
        }
        
        try {
            // Register service worker
            await this.registerServiceWorker();
            
            // Get application server public key
            await this.getApplicationServerKey();
            
            // Initialize UI
            this.initializeUI();
            
            // Check current subscription status
            await this.updateSubscriptionStatus();
            
            console.log('PushNotificationManager: Initialized successfully');
        } catch (error) {
            console.error('PushNotificationManager: Initialization failed:', error);
        }
    }
    
    async registerServiceWorker() {
        try {
            console.log('Registering service worker...');
            
            this.swRegistration = await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/'
            });
            
            console.log('Service Worker registered:', this.swRegistration);
            
            // Handle service worker updates
            this.swRegistration.addEventListener('updatefound', () => {
                console.log('Service Worker update found');
                
                const newWorker = this.swRegistration.installing;
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed') {
                            if (navigator.serviceWorker.controller) {
                                // New update available
                                this.showUpdateAvailable();
                            }
                        }
                    });
                }
            });
            
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            throw error;
        }
    }
    
    async getApplicationServerKey() {
        try {
            const response = await fetch('/ajax/get_vapid_key.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.publicKey) {
                this.applicationServerPublicKey = data.publicKey;
                console.log('Got VAPID public key');
            } else {
                throw new Error(data.error || 'Failed to get VAPID key');
            }
        } catch (error) {
            console.error('Failed to get application server key:', error);
            throw error;
        }
    }
    
    initializeUI() {
        // Create notification settings UI if it doesn't exist
        this.createNotificationUI();
        
        // Bind event listeners
        this.bindEventListeners();
        
        // Show install prompt if available
        this.handleInstallPrompt();
    }
    
    createNotificationUI() {
        // Check if UI already exists
        if (document.getElementById('push-notification-controls')) {
            return;
        }
        
        // Create notification controls in user info area
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            const controlsHTML = `
                <div id="push-notification-controls" class="notification-controls" style="display: none;">
                    <div class="notification-status" id="notification-status">
                        <span class="status-text">Ielādē paziņojumu statusu...</span>
                    </div>
                    <div class="notification-buttons">
                        <button id="enable-notifications" class="btn btn-sm btn-primary" style="display: none;">
                            🔔 Iespējot paziņojumus
                        </button>
                        <button id="disable-notifications" class="btn btn-sm btn-secondary" style="display: none;">
                            🔕 Atspējot paziņojumus
                        </button>
                        <button id="test-notification" class="btn btn-sm btn-info" style="display: none;">
                            🧪 Testēt
                        </button>
                    </div>
                </div>
            `;
            
            userInfo.insertAdjacentHTML('afterend', controlsHTML);
        }
    }
    
    bindEventListeners() {
        // Enable notifications button
        const enableBtn = document.getElementById('enable-notifications');
        if (enableBtn) {
            enableBtn.addEventListener('click', () => this.subscribeUser());
        }
        
        // Disable notifications button
        const disableBtn = document.getElementById('disable-notifications');
        if (disableBtn) {
            disableBtn.addEventListener('click', () => this.unsubscribeUser());
        }
        
        // Test notification button
        const testBtn = document.getElementById('test-notification');
        if (testBtn) {
            testBtn.addEventListener('click', () => this.sendTestNotification());
        }
    }
    
    async updateSubscriptionStatus() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;
            
            this.updateUI();
            
            if (subscription) {
                console.log('User is subscribed to push notifications');
                this.updateSubscriptionOnServer(subscription);
            } else {
                console.log('User is not subscribed to push notifications');
            }
        } catch (error) {
            console.error('Error checking subscription status:', error);
        }
    }
    
    updateUI() {
        const controlsElement = document.getElementById('push-notification-controls');
        const statusElement = document.getElementById('notification-status');
        const enableBtn = document.getElementById('enable-notifications');
        const disableBtn = document.getElementById('disable-notifications');
        const testBtn = document.getElementById('test-notification');
        
        if (!statusElement) return;
        
        // Show controls
        if (controlsElement) {
            controlsElement.style.display = 'block';
        }
        
        if (this.isSubscribed) {
            statusElement.innerHTML = '<span class="status-text status-enabled">📱 Paziņojumi iespējoti</span>';
            enableBtn && (enableBtn.style.display = 'none');
            disableBtn && (disableBtn.style.display = 'inline-block');
            testBtn && (testBtn.style.display = 'inline-block');
        } else {
            statusElement.innerHTML = '<span class="status-text status-disabled">🔕 Paziņojumi atspējoti</span>';
            enableBtn && (enableBtn.style.display = 'inline-block');
            disableBtn && (disableBtn.style.display = 'none');
            testBtn && (testBtn.style.display = 'none');
        }
    }
    
    async subscribeUser() {
        try {
            console.log('Subscribing user to push notifications...');
            
            // Request notification permission
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                alert('Paziņojumu atļauja nav piešķirta');
                return;
            }
            
            // Convert VAPID key
            const applicationServerKey = this.urlB64ToUint8Array(this.applicationServerPublicKey);
            
            // Subscribe to push notifications
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });
            
            console.log('User subscribed:', subscription);
            
            // Update subscription on server
            await this.updateSubscriptionOnServer(subscription);
            
            this.isSubscribed = true;
            this.updateUI();
            
            this.showNotificationStatus('Paziņojumi veiksmīgi iespējoti!', 'success');
            
        } catch (error) {
            console.error('Failed to subscribe user:', error);
            this.showNotificationStatus('Kļūda iespējojot paziņojumus: ' + error.message, 'error');
        }
    }
    
    async unsubscribeUser() {
        try {
            console.log('Unsubscribing user from push notifications...');
            
            const subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (subscription) {
                // Unsubscribe from push service
                await subscription.unsubscribe();
                
                // Remove subscription from server
                await this.removeSubscriptionFromServer(subscription);
                
                console.log('User unsubscribed');
            }
            
            this.isSubscribed = false;
            this.updateUI();
            
            this.showNotificationStatus('Paziņojumi atspējoti', 'info');
            
        } catch (error) {
            console.error('Failed to unsubscribe user:', error);
            this.showNotificationStatus('Kļūda atspējojot paziņojumus: ' + error.message, 'error');
        }
    }
    
    async updateSubscriptionOnServer(subscription) {
        try {
            const response = await fetch('/ajax/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    subscription: subscription,
                    action: 'subscribe'
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Server error');
            }
            
            console.log('Subscription updated on server');
            
        } catch (error) {
            console.error('Failed to update subscription on server:', error);
            throw error;
        }
    }
    
    async removeSubscriptionFromServer(subscription) {
        try {
            const response = await fetch('/ajax/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    subscription: subscription,
                    action: 'unsubscribe'
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('Server error removing subscription:', data.error);
            }
            
        } catch (error) {
            console.error('Failed to remove subscription from server:', error);
        }
    }
    
    async sendTestNotification() {
        try {
            const response = await fetch('/ajax/send_test_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    test: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotificationStatus('Testa paziņojums nosūtīts!', 'success');
            } else {
                throw new Error(data.error || 'Failed to send test notification');
            }
            
        } catch (error) {
            console.error('Failed to send test notification:', error);
            this.showNotificationStatus('Kļūda nosūtot testa paziņojumu: ' + error.message, 'error');
        }
    }
    
    showNotificationStatus(message, type = 'info') {
        // Show notification in a simple way
        if (type === 'success') {
            console.log('✅ ' + message);
        } else if (type === 'error') {
            console.error('❌ ' + message);
        } else {
            console.info('ℹ️ ' + message);
        }
        
        // Show browser notification if supported
        if (Notification.permission === 'granted') {
            new Notification('AVOTI TMS', {
                body: message,
                icon: '/assets/images/icon-192x192.png'
            });
        }
    }
    
    handleInstallPrompt() {
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('Install prompt available');
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button
            this.showInstallButton(deferredPrompt);
        });
        
        window.addEventListener('appinstalled', (evt) => {
            console.log('App installed');
            this.hideInstallButton();
        });
    }
    
    showInstallButton(deferredPrompt) {
        // Create install button if it doesn't exist
        let installBtn = document.getElementById('pwa-install-btn');
        
        if (!installBtn) {
            installBtn = document.createElement('button');
            installBtn.id = 'pwa-install-btn';
            installBtn.className = 'btn btn-success pwa-install-prompt';
            installBtn.innerHTML = '📱 Instalēt aplikāciju';
            
            document.body.appendChild(installBtn);
        }
        
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to install prompt: ${outcome}`);
                deferredPrompt = null;
                this.hideInstallButton();
            }
        });
        
        installBtn.style.display = 'block';
    }
    
    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'none';
        }
    }
    
    // Helper function to convert VAPID key
    urlB64ToUint8Array(base64String) {
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
}

// Initialize Push Notification Manager
document.addEventListener('DOMContentLoaded', function() {
    window.pushNotificationManager = new PushNotificationManager();
});