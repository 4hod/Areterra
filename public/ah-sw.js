// Areterra Hub service worker. Authenticated records are deliberately never cached.
// Only the application shell is retained so a lost connection fails clearly and safely.

const STATIC_CACHE = 'areterra-static-v2';
const STATIC_FILES = ['/offline.html', '/icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_FILES)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(Promise.all([
        self.clients.claim(),
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('areterra-static-') && key !== STATIC_CACHE).map((key) => caches.delete(key)))),
    ]));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const safeStatic = ['script', 'style', 'font'].includes(request.destination)
        || (request.destination === 'image' && !url.pathname.startsWith('/media/'));
    if (!safeStatic) return;
    event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok) caches.open(STATIC_CACHE).then((cache) => cache.put(request, response.clone()));
        return response;
    })));
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
