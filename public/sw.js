const CACHE_NAME = 'leafit-cache-v1';
const STATIC_CACHE = 'leafit-static-v1';
const API_CACHE = 'leafit-api-v1';
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/public/frontend/retro/js/main.js',
    '/public/frontend/retro/js/custom.js',
    '/public/frontend/retro/js/scripts.js',
    '/public/frontend/retro/js/stisla.js',
    '/public/frontend/retro/js/page/index.js',
    '/public/frontend/retro/js/page/index-0.js',
    '/public/frontend/retro/vendor/bootstrap/js/bootstrap.bundle.js',
    '/public/frontend/retro/vendor/aos/aos.js',
    '/public/frontend/retro/vendor/lottie/lottie.js',
    '/public/frontend/retro/vendor/swiper/swiper-bundle.min.js',
    '/public/frontend/retro/vendor/jQuery/jquery-min.js',
    '/public/frontend/retro/img/hero-image.svg',
    '/public/frontend/retro/img/tts.png',
    '/public/frontend/retro/img/espeech-app-download.png',
    '/public/frontend/retro/img/app-store.png',
    '/public/frontend/retro/img/google-play-store.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS)),
            caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)),
        ])
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME && key !== STATIC_CACHE && key !== API_CACHE)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    if (url.pathname.startsWith('/api/') || url.pathname.includes('api/')) {
        event.respondWith(networkFirst(event.request));
    } else {
        event.respondWith(cacheFirst(event.request));
    }
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) return cachedResponse;

    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (err) {
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
        }
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(API_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (err) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) return cachedResponse;
        return new Response(JSON.stringify({ error: true, message: 'You are offline' }), {
            headers: { 'Content-Type': 'application/json' },
            status: 503,
        });
    }
}
