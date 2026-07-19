// Areterra Hub service worker.
// Served from the site root so its scope covers the whole app.
// Phase 2 adds push notification handling; for now it only enables installability.

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    if (!event.data) return;
    const payload = event.data.json();
    event.waitUntil(
        self.registration.showNotification(payload.title ?? 'Areterra Hub', {
            body: payload.body ?? '',
            icon: '/icon.svg',
            data: { url: payload.url ?? '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(self.clients.openWindow(event.notification.data?.url ?? '/'));
});
