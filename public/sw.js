// Tele Cafe POS service worker: makes the POS installable and shows a friendly page when there is no signal.
// It never stores orders, bills or any signed-in page; those always come fresh from the server.
const CACHE = 'telepos-v1';
const SHELL = ['/offline.html', '/icons/icon-192.png', '/manifest.webmanifest'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET') return;
  if (r.mode === 'navigate') { e.respondWith(fetch(r).catch(() => caches.match('/offline.html'))); return; }
  const u = new URL(r.url);
  if (u.origin === location.origin && SHELL.includes(u.pathname)) { e.respondWith(caches.match(r).then(m => m || fetch(r))); }
});
