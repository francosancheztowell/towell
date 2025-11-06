const CACHE = "pwa-v1";
const ASSETS = [
  "/", "/offline",
  "/manifest.webmanifest", "/images/fotosTowell/TOWELLIN.png"
];

self.addEventListener("install", (e) => {
  e.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE);
      // Resolver URLs relativas contra el scope del SW y omitir fallos
      const urls = ASSETS.map(u => new URL(u, self.location).toString());
      const results = await Promise.allSettled(
        urls.map(u => fetch(u, { cache: "reload" }))
      );
      await Promise.all(
        results.map((r, i) => {
          const url = urls[i];
          if (r.status === "fulfilled" && r.value && r.value.ok) {
            return cache.put(url, r.value.clone());
          }
          // Recurso no disponible: omitir sin romper la instalación
          return Promise.resolve();
        })
      );
      await self.skipWaiting();
    })()
  );
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;

  // API calls: Network-first strategy
  if (req.url.includes("/api/") || req.url.includes("/planeacion/") || req.url.includes("/buscar")) {
    e.respondWith(
      fetch(req).then(res => {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(req, clone));
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Static assets: Cache-first strategy
  e.respondWith(
    caches.match(req).then(cached =>
      cached ||
      fetch(req).then(res => {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(req, clone));
        return res;
      }).catch(() => caches.match("/offline"))
    )
  );
});










