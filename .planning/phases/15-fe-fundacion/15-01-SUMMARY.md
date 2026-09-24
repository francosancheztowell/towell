# 15-01 — Utils frontend en TypeScript · SUMMARY

**Rama:** `claude/15-01-utils-ts` (base `claude/friendly-hopper-506bg9`) · **IDs:** FE-01..06 · **Fecha:** 2026-09-24
**Plan:** `15-01-PLAN.md`. Sin PR (lo abre el owner cuando quiera; este archivo sirve de cuerpo).

## Qué se hizo

| ID | Entrega |
|---|---|
| FE-01 | `resources/js/utils/http.ts`: misma API que `http.js` (`get/post/put/patch/delete(url, config)`, `upload`, `csrfToken`, default + named). Siempre `Accept: application/json`, `X-Requested-With` y CSRF fresco de la meta. Lanza `HttpError` (`status`, `data`, `errors`, `original`). En todo fallo emite `towell:http-error` en `window` con `detail { status, url, method }` (contrato 11 §4; url sin query ni hash, método en mayúsculas, `status 0` = red). Las cancelaciones (AbortController) no se reportan. Sesión expirada (419 CSRF **o 401** del middleware `auth`): un solo toast por página y recarga a los 2.5 s. |
| FE-02 | `resources/js/utils/notifications.ts`: misma API `notify.*`. Toasts **nativos**: contenedor único `role="status" aria-live="polite"`, pila máx. 4 (sale el más viejo), 2.5 / 3 / 5 / 6 s, cierre con `aria-label`, pausa con puntero/foco, `textContent` (sin XSS), estilos inyectados una vez con `prefers-reduced-motion`, z-index sobre los overlays existentes. `alert/validation/confirm/loading/close` siguen con SweetAlert2. `showToast(message, type)` compatible (tipo desconocido → info). Ya no usa Toastr. |
| FE-03 | `resources/js/utils/format.ts`: `escapeHtml` (sin DOM), `debounce` (+`cancel`), `formatNumber`, `formatDate`, `formatDateTime` es-MX. Fechas y fechas-hora sin zona (formato SQL Server) = hora de pared, sin corrimiento; con zona = instante en America/Mexico_City. Formateadores `Intl` en caché. |
| FE-04 | `resources/js/utils/dom.ts`: `qs`, `qsa` (array), `delegate` (devuelve el unsubscribe), `onReady`. |
| FE-05 | `resources/js/types/global.d.ts`: `axios`, `http`, `notify`, `showToast`, `Swal`, `toastr`, `$`, `jQuery`, `Livewire` como `var` globales (sirven `window.http` y `http` a secas). |
| FE-06 | `tsconfig.json`: `erasableSyntaxOnly`, `verbatimModuleSyntax`, `allowImportingTsExtensions` (los tests de node importan `.ts`); sin `exclude`. `tejido/inventario-telas.ts` tipado y bajo strict. |

`bootstrap.js` sigue exponiendo exactamente las mismas globals (`axios`, `$`, `jQuery`, `Swal`, `toastr`, `http`, `notify`, `showToast`, Select2 registrado). El puente Livewire `aviso` ahora pasa por `showToast` (solo tipos de toast). Se borraron `utils/http.js` y `utils/notifications.js` (nadie más los importaba). **Ningún call-site de vista cambió.**

## Archivos
- Nuevos: `resources/js/utils/{http,notifications,format,dom}.ts`, `resources/js/types/global.d.ts`, `tests/Js/utils-{http,notifications,format,dom}.test.mjs`, `tests/Js/utils-fake-dom.mjs` (DOM mínimo: no hay jsdom), `15-01-evidencia-toasts.png`.
- Modificados: `resources/js/bootstrap.js` (imports `.ts` + puente), `tsconfig.json`, `resources/js/tejido/inventario-telas.ts` (solo tipos), `scripts/ratchet-baseline.json` (`toastr.` 29 → 25).
- `resources/js/trazabilidad/*.ts` — **autorizado por el owner en esta sesión, solo sintaxis/tipos**: 16 parameter properties → campos explícitos (ningún inicializador de campo usa `this`, así que el orden no cambia) y se quitó su `declare global` de http/notify/Swal/Livewire/jQuery (queda solo `abrirModalRedboothProgramaTejido`).

## Evidencia
- `npm run typecheck` → sin errores (todo `resources/js/**/*.ts`, sin exclude).
- `npm run test:js` → 64/64 (27 nuevos en `utils-*`). `utils-format` pasa también con `TZ=Asia/Tokyo`.
- `npm run build` → ok. `npm run ratchet` → ok, `toastr.` baja 29 → 25 (fijado con `--update`).
- `php artisan test` → 1254 passed (19262 assertions).
- `inventario-telas.ts`: la salida de esbuild antes/después solo difiere en `String(x)` explícitos donde el navegador ya coercionaba igual (`setAttribute`, `textContent`, `URLSearchParams.append`, `sessionStorage.setItem`, `parseInt`; siempre con valores no nulos) y en llamar `window.cerrarModalTelaReservada()` en vez de la misma global sin prefijo.
- Navegador (skill `run`, Chromium headless contra `php -S` con sqlite en archivo y usuario inyectado como en los tests): `/planeacion/calendarios`, `/configuracion/departamentos`, `/configuracion/secuencia-de-folios` → 200, las 10 globals presentes (incl. `$.fn.select2`), **0 errores de consola** salvo el 404 provocado a propósito. Con un `notify.confirm` abierto se dispararon 5 toasts: quedan 4 visibles, por encima del modal y sin cerrarlo; el error se muestra como texto (`<b>sin html</b>`); `confirm` devolvió `true`; un `http.get` a una ruta inexistente emitió `towell:http-error {status:404, url:"/no-existe-ruta", method:"GET"}`. Captura: `15-01-evidencia-toasts.png`.
- `code-review` (high): 10 hallazgos; corregidos 1, 2, 3, 4, 6 y 8 (commit "correcciones del code-review"); el resto abajo.

## Decisiones
- axios se mantiene (ponytail); retirarlo no es de 15-01.
- `http.delete(url, config)` como en `http.js` (el body va en `config.data`). CLAUDE.md documenta mal la firma → HANDOFF.
- 401 se trata como sesión expirada igual que 419: con `Accept: application/json` el middleware `auth` responde 401 en vez de redirigir.
- El aviso de sesión es un toast nativo con recarga temporizada, no un modal de Swal: los `catch` de las vistas hacen `Swal.close()`/abren otro modal y eso lo cerraba al instante.
- Globals como `var` en `global.d.ts` para tipar también el uso sin `window.` (Swal, http, notify en Blade/TS).
- Globals propias de inventario-telas en un `declare global` local (`interface Window`), no en `types/`.

## Pendientes / deuda conocida
- `inventario-telas.ts` usa `Crudo = any` / `Registro = Record<string, any>` para los datos del servidor (formas abiertas, nombres de campo variables). Compila en strict, pero esos valores no se revisan; estrecharlos a `unknown` implica tocar lógica → ADOP / 19-xx de Tejido.
- `inventario-telas.ts` (`actualizarRegistroConNuevaFecha`): lee `err.response?.data?.message`, que `HttpError` no tiene (es `.message`/`.data`); siempre cae al mensaje genérico. Bug previo; no se tocó por la regla "solo tipos". Arreglo de una línea para quien sea dueño de Tejido.
- `utils/dom.ts#qs` duplica `trazabilidad/dom.ts#queryElement`: trazabilidad puede migrar cuando la toque su dueño.
- Tamaño: ~1 900 líneas cambiadas en total (código ≈ 1 450 sin el tipado de inventario-telas). Si el integrador quiere partir: `-p1` = commits hasta "correcciones del code-review" menos `tipar tejido/inventario-telas.ts`; `-p2` = ese commit (independiente).

## Despliegue
Solo frontend: `npm ci && npm run build`. Sin migraciones, sin `.env`, sin `optimize`.
