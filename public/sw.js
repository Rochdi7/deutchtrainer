// Minimal service worker: makes the app installable and keeps the app shell
// (fonts, built assets, icons) available from cache. Pages are always fetched
// from the network so quiz data is never stale.
const CACHE = 'deutschtrainer-static-v3';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

// Daily study reminder sent by `php artisan reminders:send`.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data?.text() };
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'DeutschTrainer', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: 'study-reminder',
            data: { url: data.url || '/kurs/A1' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((w) => w.url.startsWith(self.location.origin));
            if (open) return open.focus().then((w) => w.navigate(url));
            return self.clients.openWindow(url);
        }),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const isStatic =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/images/') ||
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com';

    if (!isStatic) return; // network only for pages & API calls

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request);
            if (cached) return cached;
            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        }),
    );
});
