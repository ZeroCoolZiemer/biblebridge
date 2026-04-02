/**
 * BibleBridge Standalone — Service Worker
 * ----------------------------------------
 * Caches the app shell (CSS, JS, fonts) and up to 50 chapter pages.
 * Oldest chapters are evicted when the cap is reached.
 */

const CACHE_VERSION = 'bb-v2';
const SHELL_CACHE  = 'bb-shell-' + CACHE_VERSION;
const PAGE_CACHE   = 'bb-pages-' + CACHE_VERSION;
const MAX_PAGES    = 50;

// App shell — cached on install
const SHELL_ASSETS = [
    'assets/reader.min.css',
    'assets/reader.min.js',
    'assets/fonts/fonts.css',
    'assets/fonts/inter-latin.woff2',
    'assets/fonts/lora-latin.woff2',
    'assets/fonts/lora-italic-latin.woff2',
    'offline.html'
];

// -----------------------------------------------------------
// Install — cache the app shell
// -----------------------------------------------------------
self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL_CACHE).then(function (cache) {
            return cache.addAll(SHELL_ASSETS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

// -----------------------------------------------------------
// Activate — clean up old caches
// -----------------------------------------------------------
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) {
                    return (k.startsWith('bb-shell-') || k.startsWith('bb-pages-')) &&
                           k !== SHELL_CACHE && k !== PAGE_CACHE;
                }).map(function (k) {
                    return caches.delete(k);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// -----------------------------------------------------------
// Fetch strategy
// -----------------------------------------------------------
self.addEventListener('fetch', function (event) {
    var url = new URL(event.request.url);

    // Only handle GET requests on same origin
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;

    var path = url.pathname;
    var base = self.registration.scope.replace(/\/$/, '');

    // Normalize path relative to base
    var rel = path;
    if (base && path.startsWith(base)) {
        rel = path.substring(base.length) || '/';
    }

    // Shell assets — cache-first
    if (isShellAsset(rel)) {
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                return cached || fetch(event.request).then(function (resp) {
                    if (resp.ok) {
                        var clone = resp.clone();
                        caches.open(SHELL_CACHE).then(function (c) { c.put(event.request, clone); });
                    }
                    return resp;
                });
            })
        );
        return;
    }

    // Chapter pages (/read/book/chapter) — cache-first (Bible text is immutable)
    if (isChapterPage(rel)) {
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                if (cached) return cached;
                return fetch(event.request).then(function (resp) {
                    if (resp.ok) {
                        var clone = resp.clone();
                        caches.open(PAGE_CACHE).then(function (c) {
                            c.put(event.request, clone);
                            trimCache(c, MAX_PAGES);
                        });
                    }
                    return resp;
                }).catch(function () {
                    return caches.match(base + '/offline.html') || caches.match('offline.html');
                });
            })
        );
        return;
    }

    // Homepage — network-first with cache fallback
    if (rel === '/' || rel === '' || rel === '/index.php') {
        event.respondWith(
            fetch(event.request).then(function (resp) {
                if (resp.ok) {
                    var clone = resp.clone();
                    caches.open(PAGE_CACHE).then(function (c) { c.put(event.request, clone); });
                }
                return resp;
            }).catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || caches.match(base + '/offline.html') || caches.match('offline.html');
                });
            })
        );
        return;
    }

    // Everything else — network only, offline fallback for navigations
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(function () {
                return caches.match(base + '/offline.html') || caches.match('offline.html');
            })
        );
    }
});

// -----------------------------------------------------------
// Helpers
// -----------------------------------------------------------
function isShellAsset(rel) {
    return rel.indexOf('/assets/') !== -1;
}

function isChapterPage(rel) {
    // Matches /read/book-name/123 with optional query string
    return /^\/read\/[a-z0-9-]+\/\d+/.test(rel);
}

/**
 * Trim a cache to maxEntries by deleting the oldest items first.
 */
function trimCache(cache, maxEntries) {
    cache.keys().then(function (keys) {
        if (keys.length > maxEntries) {
            cache.delete(keys[0]).then(function () {
                trimCache(cache, maxEntries);
            });
        }
    });
}
