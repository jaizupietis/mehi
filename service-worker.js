/**
 * Service Worker for AVOTI Task Management PWA
 * Handles push notifications, caching, and offline functionality
 */

const CACHE_NAME = 'avoti-tms-v1.0.0';
const urlsToCache = [
    '/',
    '/index.php',
    '/login.php',
    '/assets/css/style.css',
    '/assets/js/push-notifications.js',
    '/manifest.json',
    '/assets/images/icon-192x192.png'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Service Worker: Opened cache');
                return cache.addAll(urlsToCache.map(url => new Request(url, {credentials: 'same-origin'})));
            })
            .catch((error) => {
                console.error('Service Worker: Cache failed:', error);
            })
    );
    
    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});

// Activate event - cleanup old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Service Worker: Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    
    // Claim all clients immediately
    return self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }
    
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Cache hit - return response
                if (response) {
                    return response;
                }
                
                return fetch(event.request).then((response) => {
                    // Check if we received a valid response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone the response
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    
                    return response;
                });
            })
            .catch(() => {
                // Network failed, show offline page for navigation requests
                if (event.request.mode === 'navigate') {
                    return new Response(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>AVOTI TMS - Bezsaiste</title>
                            <meta charset="utf-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <style>
                                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                                .offline-message { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                                .icon { font-size: 64px; margin-bottom: 20px; }
                                h1 { color: #2c3e50; }
                                p { color: #666; }
                                .retry-btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
                            </style>
                        </head>
                        <body>
                            <div class="offline-message">
                                <div class="icon">📱</div>
                                <h1>AVOTI TMS</h1>
                                <p>Aplikācija nav pieejama bezsaistē</p>
                                <p>Pārbaudiet interneta savienojumu un mēģiniet vēlreiz</p>
                                <button class="retry-btn" onclick="location.reload()">Mēģināt vēlreiz</button>
                            </div>
                        </body>
                        </html>
                    `, {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({
                            'Content-Type': 'text/html; charset=utf-8'
                        })
                    });
                }
            })
    );
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('Service Worker: Push received');
    
    let notificationData = {};
    
    if (event.data) {
        try {
            notificationData = event.data.json();
        } catch (error) {
            console.error('Service Worker: Invalid push data:', error);
            notificationData = {
                title: 'AVOTI TMS',
                body: event.data.text() || 'Jauns paziņojums',
                icon: '/assets/images/icon-192x192.png',
                badge: '/assets/images/icon-192x192.png'
            };
        }
    } else {
        notificationData = {
            title: 'AVOTI TMS',
            body: 'Jauns paziņojums',
            icon: '/assets/images/icon-192x192.png',
            badge: '/assets/images/icon-192x192.png'
        };
    }
    
    const options = {
        body: notificationData.body,
        icon: notificationData.icon || '/assets/images/icon-192x192.png',
        badge: notificationData.badge || '/assets/images/icon-192x192.png',
        image: notificationData.image,
        data: notificationData.data || {},
        tag: notificationData.tag || 'avoti-notification',
        renotify: true,
        requireInteraction: notificationData.requireInteraction || false,
        silent: false,
        vibrate: [200, 100, 200],
        actions: notificationData.actions || [
            {
                action: 'open',
                title: 'Atvērt',
                icon: '/assets/images/icon-192x192.png'
            },
            {
                action: 'dismiss',
                title: 'Aizvērt',
                icon: '/assets/images/icon-192x192.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, options)
            .then(() => {
                console.log('Service Worker: Notification shown');
                
                // Send analytics or confirmation back to server
                if (notificationData.data && notificationData.data.trackingId) {
                    fetch('/ajax/track_notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            trackingId: notificationData.data.trackingId,
                            action: 'delivered'
                        })
                    }).catch(error => console.error('Tracking failed:', error));
                }
            })
            .catch((error) => {
                console.error('Service Worker: Notification failed:', error);
            })
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('Service Worker: Notification clicked');
    
    event.notification.close();
    
    const action = event.action;
    const notificationData = event.notification.data;
    
    if (action === 'dismiss') {
        return;
    }
    
    // Default action or 'open' action
    let urlToOpen = '/';
    
    // Determine URL based on notification data
    if (notificationData.url) {
        urlToOpen = notificationData.url;
    } else if (notificationData.type === 'task' && notificationData.taskId) {
        urlToOpen = `/my_tasks.php?task_id=${notificationData.taskId}`;
    } else if (notificationData.type === 'problem' && notificationData.problemId) {
        urlToOpen = `/problems.php?problem_id=${notificationData.problemId}`;
    }
    
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then((clientList) => {
            // Check if there's already a window/tab open with the target URL
            for (const client of clientList) {
                if (client.url.includes(urlToOpen.split('?')[0]) && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // If no existing window, open a new one
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        }).then(() => {
            // Track notification click
            if (notificationData.trackingId) {
                return fetch('/ajax/track_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        trackingId: notificationData.trackingId,
                        action: 'clicked'
                    })
                });
            }
        }).catch((error) => {
            console.error('Service Worker: Error handling notification click:', error);
        })
    );
});

// Background sync event (for offline actions)
self.addEventListener('sync', (event) => {
    console.log('Service Worker: Background sync triggered');
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

// Background sync function
async function doBackgroundSync() {
    try {
        console.log('Service Worker: Performing background sync');
        // Add any offline sync logic here
    } catch (error) {
        console.error('Background sync error:', error);
    }
}

// Message event - handle messages from main thread
self.addEventListener('message', (event) => {
    console.log('Service Worker: Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

// Error event
self.addEventListener('error', (event) => {
    console.error('Service Worker: Error occurred:', event.error);
});

// Unhandled promise rejection
self.addEventListener('unhandledrejection', (event) => {
    console.error('Service Worker: Unhandled promise rejection:', event.reason);
    event.preventDefault();
});