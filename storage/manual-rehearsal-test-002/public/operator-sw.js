/**
 * operator-sw.js — Service Worker for the /u/ operator workspace (Phase 9)
 *
 * Strategy:
 *   - Static assets (CSS, JS, fonts, images): cache-first with background update.
 *   - HTML pages (/u/*): network-first; fall back to cache when offline.
 *   - /u/kpi-feed: network-only (never serve stale KPI data offline).
 *   - Anything else: network-first.
 *
 * Offline UX: When a page request fails and no cache entry exists, the SW
 * returns the offline fallback page (/assets/operator-offline.html).
 *
 * Cache names are versioned; old caches are cleaned up on activate.
 */
'use strict';

var CACHE_VERSION = 'op-sw-v1';
var CACHE_STATIC  = CACHE_VERSION + '-static';
var CACHE_PAGES   = CACHE_VERSION + '-pages';
var OFFLINE_URL   = '/assets/operator-offline.html';

// Static assets to pre-cache on install
var PRECACHE_ASSETS = [
    '/assets/operator-surface.css',
    '/assets/apps/shell/styles/shell.css',
    '/assets/apps/shell/styles/shell-tokens.css',
    '/assets/apps/shell/styles/shell-layout.css',
    '/assets/apps/shell/styles/shell-surfaces.css',
    '/assets/apps/shell/styles/shell-navigation.css',
    '/assets/apps/shell/styles/shell-forms.css',
    '/assets/apps/shell/styles/shell-components.css',
    '/assets/apps/shell/styles/shell-operator.css',
    '/operator-sw.js',
    OFFLINE_URL,
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_STATIC).then(function (cache) {
            return cache.addAll(PRECACHE_ASSETS.filter(function (url) {
                return url !== '/operator-sw.js';
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    var CURRENT_CACHES = [CACHE_STATIC, CACHE_PAGES];
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return CURRENT_CACHES.indexOf(key) === -1;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    var url = new URL(req.url);

    if (url.origin !== self.location.origin) return;
    if (url.pathname === '/u/kpi-feed') return;
    if (req.method !== 'GET') return;

    var isStaticAsset = /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?.*)?$/.test(url.pathname);
    var isOperatorPage = url.pathname.startsWith('/u/') || url.pathname === '/u';

    if (isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_STATIC).then(function (cache) {
                return cache.match(req).then(function (cached) {
                    var networkFetch = fetch(req).then(function (resp) {
                        if (resp && resp.ok) {
                            cache.put(req, resp.clone());
                        }
                        return resp;
                    }).catch(function () { return null; });

                    return cached || networkFetch;
                });
            })
        );
    } else if (isOperatorPage) {
        event.respondWith(
            fetch(req).then(function (resp) {
                if (resp && resp.ok) {
                    var respClone = resp.clone();
                    caches.open(CACHE_PAGES).then(function (cache) {
                        cache.put(req, respClone);
                    });
                }
                return resp;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(OFFLINE_URL);
                });
            })
        );
    }
});
