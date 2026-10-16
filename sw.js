const CACHE_NAME = 'agpms-static-v3';
const STATIC_PATHS = ['assets/css/style.css','assets/css/bootstrap.min.css','assets/js/dark-mode.js','assets/js/offline-scanner.js'];
const ALLOWED = new Set(STATIC_PATHS.map(path => new URL(path, self.registration.scope).href));
self.addEventListener('install', event => {
 event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll([...ALLOWED])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
 event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('agpms-') && key !== CACHE_NAME).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
 if (event.request.method !== 'GET' || !ALLOWED.has(event.request.url)) return;
 event.respondWith(caches.open(CACHE_NAME).then(async cache => (await cache.match(event.request)) || fetch(event.request)));
});
