// ponytail: SW mínimo para instalabilidad PWA. Solo cachea la página offline.
// NO cachea HTML, JS, CSS ni respuestas de la app: siempre red. Así Chrome pasa
// su "offline capability check" sin reintroducir el riesgo de login/CSRF obsoleto
// que motivó el recorte del SW anterior (commit 9c6e79f8).
const CACHE = "pwa-offline-v1";
const BASE_URL = self.registration?.scope || self.location.href;
const OFFLINE_URL = new URL("offline", BASE_URL).toString();

self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      try {
        const cache = await caches.open(CACHE);
        const res = await fetch(OFFLINE_URL, { cache: "reload" });
        if (res && res.ok) {
          await cache.put(OFFLINE_URL, res.clone());
        }
      } catch (e) {
        // Sin página offline el SW sigue siendo válido; no romper la instalación.
      }
      await self.skipWaiting();
    })()
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
      await self.clients.claim();
    })()
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;

  // Solo navegaciones GET. Todo lo demás (POST/login, assets, XHR) va directo
  // a la red sin que el SW lo toque.
  if (req.method !== "GET" || req.mode !== "navigate") {
    return;
  }

  event.respondWith(
    fetch(req).catch(async () => {
      const cached = await caches.match(OFFLINE_URL);
      return (
        cached ||
        new Response("Sin conexión", {
          status: 503,
          headers: { "Content-Type": "text/plain; charset=utf-8" },
        })
      );
    })
  );
});

self.addEventListener("message", (event) => {
  const data = event.data || {};
  if (data.action === "skipWaiting") {
    event.waitUntil(self.skipWaiting());
  }
});
