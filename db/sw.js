/* Tabib Pharmacy — service worker (offline app shell).
 *
 * Strategy:
 *  - App's own HTML/JS/CSS: network-first (fresh when online, cache offline).
 *    This keeps updates instant while still working offline.
 *  - Firebase SDK scripts (gstatic): cache-first (stable, versioned URLs).
 *  - Firestore/Auth/Google APIs: BYPASS — the Firestore SDK manages its own
 *    online/offline cache and write queue; we must never intercept those.
 *
 * Bump CACHE_VERSION to force clients onto fresh assets.
 */
const CACHE_VERSION = "tabib-rx-v15";

// Same-origin shell to precache so the portal opens offline after install.
const SHELL = [
  "./",
  "./index.html",
  "./app.html",
  "./manifest.webmanifest",
  "./assets/css/tailwind.css",
  "./assets/js/firebase.js",
  "./assets/js/session.js",
  "./assets/js/repo.js",
  "./assets/js/router.js",
  "./assets/js/ui.js",
  "./assets/js/jalali.js",
  "./assets/js/i18n.js",
  "./assets/js/backup.js",
  "./assets/js/autobackup.js",
  "./assets/i18n/en.json",
  "./assets/i18n/fa.json",
  "./assets/i18n/ps.json",
  "./assets/js/pages/dashboard.js",
  "./assets/js/pages/drugs.js",
  "./assets/js/pages/inventory.js",
  "./assets/js/pages/sales.js",
  "./assets/js/pages/prescriptions.js",
  "./assets/js/pages/patients.js",
  "./assets/js/pages/suppliers.js",
  "./assets/js/pages/purchases.js",
  "./assets/js/pages/expenses.js",
  "./assets/js/pages/reports.js",
  "./assets/js/pages/settings.js",
  "./assets/js/pages/_scaffold.js",
];

// Firebase SDK modules (cross-origin, best-effort precache).
const SDK = [
  "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js",
  "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js",
  "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js",
  "https://www.gstatic.com/firebasejs/10.14.1/firebase-functions.js",
];

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_VERSION);
    await cache.addAll(SHELL);                       // must succeed
    await Promise.allSettled(SDK.map((u) => cache.add(u))); // best-effort
    self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

function isFirebaseData(url) {
  return /(^|\.)(googleapis\.com|firebaseio\.com|firebaseinstallations\.|identitytoolkit\.|securetoken\.)/.test(url.hostname)
    || url.hostname.endsWith("google.com")
    || url.hostname.endsWith("gstatic.com") && url.pathname.includes("/firebase/"); // not the SDK
}

async function cacheFirst(req) {
  const cache = await caches.open(CACHE_VERSION);
  const hit = await cache.match(req);
  if (hit) return hit;
  const res = await fetch(req);
  if (res && (res.ok || res.type === "opaque")) cache.put(req, res.clone());
  return res;
}

async function networkFirst(req) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const res = await fetch(req);
    if (res && res.ok) cache.put(req, res.clone());
    return res;
  } catch (e) {
    const hit = await cache.match(req);
    if (hit) return hit;
    // Offline navigation fallback → the app shell.
    if (req.mode === "navigate") return (await cache.match("./app.html")) || (await cache.match("./index.html"));
    throw e;
  }
}

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return; // never touch writes/POSTs

  const url = new URL(req.url);

  // Firestore / Auth / Google APIs → bypass entirely (SDK owns offline).
  if (isFirebaseData(url)) return;

  // Firebase SDK scripts from gstatic → cache-first.
  if (url.hostname === "www.gstatic.com" && url.pathname.includes("/firebasejs/")) {
    event.respondWith(cacheFirst(req));
    return;
  }

  // Same-origin app assets → network-first with cache fallback.
  if (url.origin === self.location.origin) {
    event.respondWith(networkFirst(req));
  }
});
