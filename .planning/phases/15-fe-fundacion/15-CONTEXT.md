# Fase 15 — Fundación frontend

**Track:** FE · **Ola:** 15-01 en Ola 1, 15-02 en Ola 2 · **IDs:** FE-01..12 · **Ramas:** `claude/15-01-utils-ts`, `claude/15-02-librerias`
**Antes de ejecutar:** escribir `15-01-PLAN.md` / `15-02-PLAN.md`.

## Objetivo
Una sola capa TS para HTTP, notificaciones, formato y DOM; fuera jQuery, Select2 (RC de 2021) y Toastr (2018); Vite sin chunk vendor global; regresiones de Tailwind v4 corregidas.

## Estado actual (verificado 2026-09-24)
- `resources/js/utils/http.js` (78 líneas) y `notifications.js` (101) — poco usados: 211 `fetch` crudos en 58 blades vs 34 `http.*`; `X-CSRF-TOKEN` manual 147×; solo 89 mandan Accept JSON; 5 manejan 419.
- `showToast` con ~7 implementaciones (`utils/notifications.js`, `catcodificacion/index.js:166`, `public/js/catalog-core.js:263`, `public/js/catalogs/CatalogBase.js:203`, locales en cortes-eficiencia, programar-urdido/engomado). `escapeHtml` ×13, `debounce` ×5, `getCsrfToken` ×4.
- jQuery solo lo necesitan Select2 y Toastr. Select2 en 5 archivos: `resources/views/**/modal/redbooth.blade.php` (PT), `resources/js/lmat-lista/index.js`, `resources/js/catcodificacion/lmat-modal.js`, `resources/js/trazabilidad/filter-selects.ts`, `resources/js/trazabilidad/scroll-manager.ts`. Shim jQuery4/Select2 de ~45 líneas en `bootstrap.js`. Toastr: 23 llamadas en 6 blades.
- `vite.config.js` `manualChunks` mete jquery+swal+select2+toastr+axios en `vendor` cargado en las 129 páginas del layout.
- CDN: html2canvas (`mecanicos/reportes/estado-maquina.blade.php:172`, `ot-diarias.blade.php:74`), pdf.js 2.16 inyectado (`cortes-eficiencia.blade.php:976`, `visualizar-cortes-eficiencia.blade.php:292`).
- Tailwind v4: `bg-opacity-*` 32× en 17 archivos (overlays negros sólidos), keyframes `spin` del global-loader pisan `animate-spin`, `fontawesome-display.css` usa `'Font Awesome 6 Free'` con FA 7, `.fa-spin` redefinido en layout.
- `tsconfig` estricto; tests JS importan `.ts` con type-stripping de Node.

## 15-01 (Ola 1) — utils TS
- FE-01 `utils/http.ts`: API compatible con `http.js` (get/post/put/patch/delete/upload), `Accept: application/json` siempre, CSRF automático, 419 único (mensaje + recarga a login), dispara `towell:http-error {status,url,method}` (contrato `../11-mon-servidor/11-CONTRACT.md` §4).
- FE-02 `utils/notifications.ts`: API compatible con `notify.*`; toasts **nativos** (contenedor `aria-live="polite"`, pila máx 4, duraciones éxito 2.5 s / info 3 s / aviso 5 s / error 6 s); `confirm`/`loading`/`validation` siguen con SweetAlert2 (el toast de Swal comparte singleton y cierra el modal abierto). `window.showToast` apunta aquí.
- FE-03 `utils/format.ts`: `escapeHtml`, `debounce`, `formatNumber`, `formatDate` (es-MX, America/Mexico_City). FE-04 `utils/dom.ts`: `qs`, `qsa`, `delegate`, `onReady`.
- FE-05 `types/global.d.ts`: `window.http`, `notify`, `Swal`, `Livewire`, `showToast`.
- FE-06 tsconfig `erasableSyntaxOnly` + `verbatimModuleSyntax`; tipar `resources/js/tejido/inventario-telas.ts` y quitar su exclude.
- Mantener `bootstrap.js` exponiendo lo mismo en `window` (no romper nada). Tests `tests/Js/utils-*.test.mjs`.

## 15-02 (Ola 2) — librerías y Vite
- FE-07 **Tom Select** + wrapper `utils/combobox.ts` (remote load, templates, multiple; en Livewire con `wire:ignore`). Migrar los 4 usos no-PT; PT migra `redbooth` y `balancear` en su track antes del merge.
- FE-08 toastr → `notify` (23 llamadas); adaptador temporal `window.toastr` → `notify` hasta ADOP.
- FE-09 quitar `jquery`, `select2`, `toastr` y el shim (grep: 0 `$.fn`, `jQuery(`, `$.ajax`).
- FE-10 `vite.config.js`: sin `manualChunks` vendor; inputs por glob `resources/js/modulos/**/index.ts` para que la Ola 3 no toque Vite.
- FE-11 html2canvas y `pdfjs-dist` por npm con `import()` dinámico.
- FE-12 regresiones Tailwind v4 (`bg-opacity-*` → `bg-black/50`…), keyframes del loader, familia FA7, `catalog-core.js` sin pisar `showToast`.

## Criterios de éxito
- JS inicial por página −X KB vs `../10-base/10-BASELINE.md` (medido); ratchet de `toastr.` en 0.
- Ninguna pantalla con errores JS nuevos en `/admin/errores` durante 7 días tras el deploy.

## Skills
`simplify`, `code-review`, `run`.
