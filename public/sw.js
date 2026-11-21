// Minimal service worker: makes the site installable. It does not cache
// pages or API calls, so earnings and balances are always live.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
