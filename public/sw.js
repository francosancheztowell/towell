const CACHE = "pwa-v4";
const ASSETS = [
  "/", "/offline",
  "/manifest.webmanifest",
  "/js/app-pwa.js",
  "/images/fotosTowell/TOWELLIN.png"
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
  const url = new URL(req.url);

  // NO interceptar peticiones que no son GET (POST, PUT, DELETE, etc.)
  // Estas requieren CSRF tokens y deben ir directo al servidor
  if (req.method !== "GET") {
    return; // Dejar pasar sin interceptar
  }

  // NO cachear peticiones que requieren autenticación o tienen headers especiales
  if (req.headers.get('Authorization') ||
      req.headers.get('X-CSRF-TOKEN') ||
      req.headers.get('X-Requested-With')) {
    return; // Dejar pasar sin interceptar
  }

  // NO cachear rutas de autenticación o que modifican estado
  const noCachePaths = [
    '/login', '/logout', '/register', '/auth',
    '/guardar', '/eliminar', '/editar', '/crear',
    '/api/auth', '/sanctum'
  ];
  if (noCachePaths.some(path => url.pathname.includes(path))) {
    return; // Dejar pasar sin interceptar
  }

  // API calls: Network-first strategy (solo GET)
  if (url.pathname.includes("/api/") ||
      url.pathname.includes("/planeacion/") ||
      url.pathname.includes("/buscar")) {
    e.respondWith(
      fetch(req).then(res => {
        // Solo cachear si es exitosa y no requiere autenticación
        if (res.ok && res.status !== 419 && res.status !== 401 && res.status !== 403) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(req, clone));
        }
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
        // Solo cachear assets estáticos exitosos
        if (res.ok && res.status !== 419) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(req, clone));
        }
        return res;
      }).catch(() => caches.match("/offline"))
    )
  );
});










