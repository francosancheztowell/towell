# Fase 12 — Monitoreo: captura cliente (TS)

**Track:** MON-B · **Ola:** 1 · **IDs:** MON-15..20 · **Rama:** `claude/12-mon-cliente`
**Depende de:** contrato `../11-mon-servidor/11-CONTRACT.md` (puede arrancar antes de que 11 esté mergeada; tests con fetch mockeado).
**Antes de ejecutar:** escribir `12-01-PLAN.md` con este contexto (formato de `../01-guardrails/01-guardrails-PLAN.md`).

## Objetivo
Script cliente liviano que reporta latido, vistas con tiempos de carga, errores del navegador y nombre de dispositivo, sin afectar la experiencia ni el rendimiento.

## Alcance
- `resources/js/monitoreo/telemetria.ts` (entrada importada desde `resources/js/app.js` con **una línea**; arranca solo si existe `<meta name="towell-telemetria" content="1">`).
- **Latido** (MON-15): cada `intervalo` que devuelva el servidor (60 s visible / 300 s oculta); `visibilitychange`; inactividad = segundos desde el último `pointerdown/keydown/touchstart/scroll`; respuesta `cerrar:true` → `location.reload()`.
- **Vistas** (MON-16): tras `load` → `POST /telemetria/vista` con `crypto.randomUUID()` (fallback `getRandomValues`: la LAN puede no servir HTTPS), Navigation Timing L2 (`responseStart`, `domContentLoadedEventEnd`, `loadEventEnd`, `transferSize`) + `serverTiming` (`app`, `db`, `q`); `pagehide` → `navigator.sendBeacon('/telemetria/vista/{uuid}/fin', FormData{_token, visibleMs})`; navegación suave con `document` `livewire:navigated` (tipo `suave`).
- **Errores** (MON-17): `window` `error` (ignorar `Script error.`, orígenes `chrome-extension:`/`moz-extension:`, `ResizeObserver loop`), `unhandledrejection`, evento `towell:http-error` (lo emitirá `utils/http` en fase 15; escucharlo ya), `Livewire.hook('request', ({ fail }) => …)` siguiendo `resources/js/crudo/dashboard.ts:271`. Dedupe por mensaje+fuente por página, máximo 10 por página.
- **Conexión** (MON-18): si el latido falla por red, emitir `towell:conexion {online:false}`; al recuperar, `{online:true}`; también `online`/`offline` del navegador.
- **Nombre de dispositivo** (MON-19): `resources/views/components/navbar/sections/user-modal.blade.php` hoy guarda el nombre en `localStorage` bajo `device_name_<hash>` (clave inestable). Enviar a `POST /telemetria/dispositivo/nombre` al guardar y migrar una vez el valor existente; mostrar el nombre que venga del servidor.
- **Tests** (MON-20): `tests/Js/monitoreo-*.test.mjs` con `node --test`, fetch/sendBeacon mockeados. Presupuesto ≤ 5 KB gzip.

## Restricciones
- **No** parchear `window.fetch` (ya lo hacen `programa-tejido/index.js:47` y `crudo/dashboard.ts:212`).
- TS solo con sintaxis borrable (sin `enum`, `namespace`, parameter properties): los tests node importan `.ts` directo.
- No tocar `resources/js/utils/**` (FE): si hace falta un helper, copiar 3 líneas locales y dejar TODO para ADOP.
- Propiedad: `resources/js/monitoreo/**`, `tests/Js/monitoreo-*`, `user-modal.blade.php`, 1 línea en `app.js`.

## Criterios de éxito
- En una sesión real: se crean filas en `SYSMonVista` con tiempos, el dispositivo aparece en línea, un `throw` en consola crea fila en `SYSMonError`.
- `npm run typecheck && npm run test:js && npm run build` verdes; bundle ≤ 5 KB gz.
- Con el meta ausente (kill switch) no se hace ninguna request.

## Skills
`code-review`, `run` (verificar en navegador con Playwright/Chromium).
