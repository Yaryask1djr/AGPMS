const CACHE_NAME = 'ikhe-pass-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/assets/css/style.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});
