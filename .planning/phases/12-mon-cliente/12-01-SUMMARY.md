# 12-01 — Monitoreo: captura cliente (TS) · SUMMARY

**Rama:** `claude/12-mon-cliente` (base `claude/friendly-hopper-506bg9`) · **IDs:** MON-15, MON-16, MON-17, MON-18, MON-19, MON-20 y el enlace "Admin" de MON-21.
**Plan:** `12-01-PLAN.md` (aprobado por el owner el 2026-09-24).

## Qué se hizo

Cliente de telemetría en TypeScript de 3.1 KB gzip, importado con una línea desde `resources/js/app.js`. Solo arranca si existe `<meta name="towell-telemetria" content="1">`.

| ID | Qué hace |
|---|---|
| MON-15 latido | Envía `POST /telemetria/latido {vista, ruta, visible, inactivoSeg, version, pantalla}` al terminar la carga. Después lo repite cada `intervalo` que devuelva el servidor (acotado a 15–900 s) y también en cada `visibilitychange`. La inactividad cuenta desde el último `pointerdown/keydown/touchstart/scroll`. Si llega `cerrar:true`, o un 401 o 419, hace `location.reload()`. El `hidden` que sigue a un `pagehide` no manda latido. |
| MON-16 vistas | Tras el `load` envía `POST /telemetria/vista` con Navigation Timing L2 (`ttfb/dom/carga/kb`) y Server-Timing (`app/db/q`). El UUID sale de `crypto.randomUUID()`, con respaldo en `getRandomValues` y luego en `Math.random`. En `pagehide` cierra la vista con `sendBeacon('/telemetria/vista/{uuid}/fin', FormData{_token, visibleMs})`, contando solo el tiempo visible. Un `livewire:navigated` cierra la vista y abre otra de tipo `suave`, y la vuelta desde bfcache también abre una vista nueva. |
| MON-17 errores | Escucha `window` `error` (ignora `Script error.`, las extensiones y `ResizeObserver loop`) y `unhandledrejection` (solo excepciones de JS, no rechazos HTTP). También escucha `towell:http-error` y `Livewire.hook('request', ({ fail }))`, que se instala de inmediato o en `livewire:init`. De HTTP y Livewire solo reporta status 0 con el navegador en línea y los ≥ 500; nunca reporta URLs de `/telemetria/*`. Dedupe por mensaje+fuente y máximo 10 por página. |
| MON-18 conexión | Emite `towell:conexion {online}` en `window` solo cuando cambia el estado. Un fallo de red del latido lo pone en `false` y el siguiente latido OK en `true`. También reacciona a `online`/`offline` del navegador, y `online` fuerza un latido. |
| MON-19 nombre | El modal pinta el `Nombre` de `SYSMonDispositivo`: una lectura por el índice único `Uuid`, dentro de `Monitoreo::seguro`. Al guardar envía `POST /telemetria/dispositivo/nombre` (≤ 80 caracteres). El valor viejo `device_name_<hash>` de localStorage se migra, y la llave se borra cuando el render ya trae el nombre del servidor. El `<script>` inline se movió a `resources/js/monitoreo/dispositivo.ts` (incluida la IP por WebRTC) y se vuelve a enlazar en `livewire:navigated`. Con el monitoreo apagado, el nombre solo se muestra (sin lápiz, sin editor, sin requests). |
| MON-20 tests | 31 tests `node --test` con axios, sendBeacon y DOM falsos. Un test empaqueta el cliente con esbuild y exige ≤ 5 KB gzip. |
| MON-21 (enlace) | Enlace "Admin" (`route('admin.index')`) en el modal, dentro de `@can('admin')`. |

## Decisiones

- **Transporte: axios directo, no `utils/http` ni `fetch`.** axios ya está en el bundle y lleva `X-CSRF-TOKEN` desde `bootstrap.js`, así que no se agregan `fetch(` ni `X-CSRF-TOKEN` (el ratchet no sube). Además, un fallo de telemetría no dispara `towell:http-error` ni la UI de 419 de la fase 15. Queda un `TODO(ADOP)` en `telemetria.ts`.
- **El núcleo (`cliente.ts`) no importa nada y recibe `post` inyectado.** Así los tests node importan el `.ts` directo. Solo usa sintaxis borrable.
- **Errores HTTP:** los 4xx (422, 419, 403) son flujo normal y no se reportan. Los 5xx también los captura el servidor (`CapturarRespuesta5xx`); el cliente aporta la URL de la llamada.
- **419 en el latido recarga.** Con la sesión vencida, el CSRF responde 419 antes que `auth`. Así una pestaña muerta termina en el login.

## Archivos

- Nuevos: `resources/js/monitoreo/cliente.ts`, `resources/js/monitoreo/dispositivo.ts`, `resources/js/monitoreo/telemetria.ts`, `tests/Js/monitoreo-cliente.test.mjs`, `tests/Js/monitoreo-dispositivo.test.mjs`, `tests/Js/monitoreo-presupuesto.test.mjs`, `.planning/phases/12-mon-cliente/{12-01-PLAN,12-01-SUMMARY,HANDOFF}.md`.
- Modificados: `resources/js/app.js` (1 línea), `resources/views/components/navbar/sections/user-modal.blade.php`, `scripts/ratchet-baseline.json` (`<script> inline en blade` 170 → 169).

## Evidencia

```
npm run typecheck                 ✓ sin errores
npm run test:js                   # pass 68  # fail 0   (31 de monitoreo)
presupuesto                       # monitoreo: 3149 B gzip (≤ 5120)
npm run build                     ✓ built
npm run ratchet                   ratchet ok (10 metricas, ninguna subio); <script> inline 170 → 169 fijado
php artisan test                  Tests: 1254 passed (19262 assertions)
vendor/bin/phpstan analyse        [OK] No errors
pint                              sin archivos PHP tocados
code-review (medium)              3 hallazgos, los 3 corregidos: re-enlace del modal tras wire:navigate, 419 recarga, borrado de la llave vieja
```

**Navegador (Chromium con Playwright, app real con `php -S` y sqlite local; `sqlsrv` apuntado a sqlite solo en scratch):**
- Sin sesión, en `/login`: 0 requests a `/telemetria`.
- Login con un usuario de área Sistemas → `/admin`: `POST /telemetria/vista` con `nav:{ttfb:104,dom:229,carga:230,kb:58}` y `st:{app:67,db:0,q:1}`, y luego `POST /telemetria/latido`.
- `SYSMonVista`: fila `admin.index`, `carga`, `TtfbMs 104 · DomMs 229 · CargaMs 230 · Kb 58 · ServidorMs 67 · ConsultasN 1`; al navegar, `Fin` y `VisibleMs 3875` por beacon.
- `SYSMonDispositivo`: `VersionFront`, `Pantalla 1280x800`, `UltimaRuta admin.index`, `Visible 1`, y `Nombre "Andón Crudo 1"` después de guardar desde el modal. Tras recargar, el nombre viene del servidor (`data-nombre`).
- `throw` desde la consola → `SYSMonError` con `Origen js`, `Clase Error`, `Mensaje "Uncaught Error: prueba MON-17 desde consola"`, más su evento con `VersionFront`.
- `MONITOREO_ENABLED=false` → sin meta, sin lápiz, 0 requests a `/telemetria` (con navegación y `throw` incluidos).
- Render del modal: área Sistemas → enlace Admin 1; área Tejido → 0. Ningún `<script>` inline.

## Pendientes / límites conocidos

- HANDOFF §1: los errores de cliente quedan con `Ruta = telemetria.error` en el servidor.
- Con `wire:navigate`, el `ruta` de la vista `suave` sale del meta `towell-ruta`. Si Livewire no actualiza ese meta del `<head>`, repite la ruta anterior; el `url` (pathname) sí es el nuevo.
- La vista de una pestaña cerrada de golpe (crash, kill) queda sin `Fin`; es el comportamiento esperado de `sendBeacon`.

## Despliegue

Sin migraciones ni `.env` nuevos. `npm run build`, y luego `php artisan view:clear` (cambió el Blade del modal). El kill switch sigue siendo `MONITOREO_ENABLED=false` (fase 11).
