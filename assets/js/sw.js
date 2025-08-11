
// Service Worker for AVOTI TMS PWA - Enhanced Push Notifications
const CACHE_NAME = 'avoti-tms-v1.0.1';
const urlsToCache = [
  '/mehi/',
  '/mehi/assets/css/style.css',
  '/mehi/assets/js/app.js',
  '/mehi/assets/images/icon-192x192.png',
  '/mehi/assets/images/icon-512x512.png',
  '/mehi/index.php',
  '/mehi/my_tasks.php',
  '/mehi/problems.php',
  '/mehi/notifications.php'
];

// Instalēšanas notikums
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache atvērts');
        return cache.addAll(urlsToCache);
      })
      .catch(function(error) {
        console.log('Kļūda kešošanā:', error);
      })
  );
});

// Fetch notikums
self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          return response;
        }
        return fetch(event.request);
      }
    )
  );
});

// Aktivizēšanas notikums
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Push notification handling - uzlabota versija Android Chrome
self.addEventListener('push', function(event) {
  console.log('Push notification received:', event);
  
  let notificationData = {
    title: 'AVOTI TMS',
    body: 'Jums ir jauns paziņojums',
    icon: '/mehi/assets/images/icon-192x192.png',
    badge: '/mehi/assets/images/icon-72x72.png',
    data: {
      url: '/mehi/notifications.php',
      timestamp: Date.now()
    },
    tag: 'avoti-notification-' + Date.now(),
    requireInteraction: false,
    vibrate: [200, 100, 200],
    actions: [
      {
        action: 'open',
        title: 'Atvērt',
        icon: '/mehi/assets/images/icon-72x72.png'
      },
      {
        action: 'dismiss',
        title: 'Aizvērt'
      }
    ]
  };

  // Ja push notikumam ir dati, izmantot tos
  if (event.data) {
    try {
      const pushData = event.data.json();
      console.log('Push data received:', pushData);
      notificationData = { ...notificationData, ...pushData };
    } catch (e) {
      console.log('Error parsing push data, using text:', e);
      notificationData.body = event.data.text() || notificationData.body;
    }
  }
  
  console.log('Showing notification with data:', notificationData);
  
  const promiseChain = self.registration.showNotification(
    notificationData.title,
    {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      data: notificationData.data,
      tag: notificationData.tag,
      requireInteraction: notificationData.requireInteraction,
      vibrate: notificationData.vibrate,
      actions: notificationData.actions,
      silent: false,
      timestamp: Date.now()
    }
  );
  
  event.waitUntil(promiseChain);
});

// Notification click handling
self.addEventListener('notificationclick', function(event) {
  console.log('Notification clicked:', event.notification.tag);
  
  event.notification.close();
  
  if (event.action === 'dismiss') {
    return;
  }
  
  const urlToOpen = event.notification.data?.url || '/mehi/';
  
  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    }).then(function(clientList) {
      // Meklēt vai jau ir atvērts AVOTI TMS
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url.includes('/mehi/') && 'focus' in client) {
          client.navigate(urlToOpen);
          return client.focus();
        }
      }
      
      // Ja nav atvērts, atvērt jaunu logu
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// Background sync (ja vajadzīgs)
self.addEventListener('sync', function(event) {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

function doBackgroundSync() {
  return fetch('/mehi/ajax/get_notification_count.php')
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      console.log('Background sync completed');
    })
    .catch(function(error) {
      console.log('Background sync failed:', error);
    });
}
