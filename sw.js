// Zvyšte verzi při změně tohoto souboru nebo předkešovaných souborů.
const CACHE_NAME = 'cmms-v2';
const PRECACHE = [
    'assets/css/style.css',
    'assets/js/app.js',
    'assets/icon-192.png'
];

// Kešují se jen statické soubory. Stránky aplikace (index.php) a nahrané soubory
// (?page=file, assets/uploads) obsahují soukromá data a nikdy se do cache nedávají.
function isCacheable(url) {
    if (url.origin === self.location.origin) {
        return /\/assets\/(css|js)\//.test(url.pathname) || /\/assets\/(icon-\d+|favicon.*)\.(png|ico)$/.test(url.pathname);
    }
    return url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// Síť má přednost (uživatel vždy dostane aktuální verzi), cache slouží jako záloha při offline.
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (!isCacheable(url)) return; // vše ostatní jde přímo na síť

    event.respondWith(
        fetch(req)
            .then((res) => {
                if (res && (res.ok || res.type === 'opaque')) {
                    const copy = res.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req))
    );
});
