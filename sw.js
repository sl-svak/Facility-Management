// Zvyšte verzi při změně tohoto souboru nebo předkešovaných souborů.
const CACHE_NAME = 'cmms-v6';
const PRECACHE = [
    'assets/css/style.css',
    'assets/js/app.js',
    'assets/icon-192.png'
];

// Kešují se jen statické soubory z vlastního serveru. Stránky aplikace (index.php) a nahrané
// soubory (?page=file, assets/uploads) obsahují soukromá data a nikdy se do cache nedávají.
// Požadavky na cizí servery (např. Google Fonts) worker vůbec nezachytává: jeho fetch() by
// podléhal CSP direktivě connect-src a byl by zablokován. Fonty tedy načítá přímo stránka.
function isCacheable(url) {
    if (url.origin !== self.location.origin) return false;
    return /\/assets\/(css|js)\//.test(url.pathname) || /\/assets\/(icon-\d+|favicon.*)\.(png|ico)$/.test(url.pathname);
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
                if (res && res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req))
    );
});
