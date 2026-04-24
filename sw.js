/* Repo Delivery — Service Worker
   Maneja push notifications y clicks. Sin cache offline por ahora. */

const APP_NAME = 'Repo Delivery';
// URLs relativas al SW (/<app>/sw.js) → funcionan tanto en subcarpeta como en subdominio
const ICON     = new URL('favicon/android-icon-192x192.png', self.location).toString();
const BADGE    = new URL('favicon/android-icon-96x96.png',  self.location).toString();
const APP_URL  = new URL('./', self.location).toString();

// Forzar activación rápida en actualizaciones
self.addEventListener('install',  (e) => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

// ─── Recepción de push ─────────────────────────────────────────────
self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (_) {
    data = { title: APP_NAME, body: event.data ? event.data.text() : '' };
  }

  const title = data.title || APP_NAME;
  const options = {
    body:        data.body || '',
    icon:        data.icon || ICON,
    badge:       BADGE,
    tag:         data.data && data.data.tag ? data.data.tag : undefined,
    renotify:    !!(data.data && data.data.renotify),
    requireInteraction: !!(data.data && data.data.requireInteraction),
    data:        data.data || {},
    vibrate:     [200, 100, 200],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// ─── Click en la notificación ──────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const rawUrl    = event.notification.data && event.notification.data.url;
  const targetUrl = rawUrl ? new URL(rawUrl, self.location).toString() : APP_URL;

  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      // Si ya hay una pestaña de la app abierta (dentro del scope del SW), enfocarla
      if (c.url.startsWith(APP_URL) && 'focus' in c) {
        await c.focus();
        if ('navigate' in c) { try { await c.navigate(targetUrl); } catch (_) {} }
        return;
      }
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
  })());
});

// Si el navegador invalida la suscripción (rara vez), reintentar re-suscripción silenciosa
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      c.postMessage({ type: 'pushsubscriptionchange' });
    }
  })());
});
