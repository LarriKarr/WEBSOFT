const CACHE_NAME = 'my-portfolio-apps-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Игнорируем внешние API и стримы
  if (event.request.url.includes('api') || event.request.url.includes('stream') || event.request.url.includes('.m3u8')) {
    return;
  }
  
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      // Возвращаем из кеша или подгружаем из сети и обновляем кеш
      const fetchPromise = fetch(event.request).then((networkResponse) => {
        return caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, networkResponse.clone());
          return networkResponse;
        });
      }).catch(() => cachedResponse);

      return cachedResponse || fetchPromise;
    })
  );
});
          
