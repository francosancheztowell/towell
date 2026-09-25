# 15-02 — Librerías y Vite · SUMMARY

**Rama:** `claude/15-02-librerias` (base `claude/friendly-hopper-506bg9`, `c0fab09`) · **IDs:** FE-07..12 · **Fecha:** 2026-09-25
**Plan:** `15-02-PLAN.md` (aprobado por el owner). Sin PR: este archivo sirve de cuerpo.

## Resultado principal

El JS inicial de **todas las páginas del layout baja 50 KB gzip (−53 %)**: de 95.4 a 45.3 KB. Ya no hay chunk `vendor`: jQuery, Select2 y Toastr desaparecen, y Tom Select, html2canvas y pdf.js solo se descargan en la página o acción que los usa.

| Página (JS inicial = entradas + imports estáticos) | Antes KB / gzip | Después KB / gzip | Δ gzip | CSS gzip antes → después |
|---|---:|---:|---:|---:|
| Layout (las 129 páginas) | 296.1 / 95.4 | 141.3 / 45.3 | **−50.1** | 40.4 → 37.2 |
| Programa Tejido | 566.0 / 159.0 | 411.1 / 108.9 | −50.1 | 40.4 → 37.2 |
| Trazabilidad (incluye Tom Select) | 321.6 / 103.1 | 213.5 / 69.5 | −33.6 | 45.1 → 44.5 |
| L.Mat lista (incluye Tom Select) | 356.0 / 112.0 | 247.5 / 78.2 | −33.8 | 40.4 → 39.8 |
| Codificación (incluye Tom Select) | 399.4 / 123.1 | 291.0 / 89.5 | −33.6 | 40.4 → 39.8 |
| Inventario de telas | 340.2 / 105.5 | 185.3 / 55.4 | −50.1 | 41.9 → 38.7 |
| Reservar/programar | 347.5 / 109.3 | 192.6 / 59.2 | −50.1 | 40.4 → 37.2 |
| Crudo | 317.8 / 102.7 | 163.0 / 52.6 | −50.1 | 52.7 → 49.5 |
| Reportes con charts | 498.7 / 164.8 | 343.9 / 114.7 | −50.1 | 40.4 → 37.2 |

Contra `10-BASELINE.md`: el chunk `vendor` (278.8 KB / 87.7 gzip) y `_vendor.css` (22.0 / 4.7) ya no existen. Bajo demanda: `combobox` 47 KB / 16.6 gzip (+ su CSS), `html2canvas-pro` 265 KB / 69 gzip (solo al descargar imagen en mecánicos), `pdf` 541 KB / 165 gzip + worker 1.27 MB (solo al descargar imagen en cortes de eficiencia).

Método: `npm run build` en la base (worktree en `c0fab09`) y en la rama; por página se suman la entrada, `app.js`, `app-core.js`, `app.css` y sus imports estáticos según `public/build/manifest.json`, gzip nivel 9 (mismo método que 10-BASELINE §a).

## Qué se hizo

| ID | Entrega |
|---|---|
| FE-07 | `resources/js/utils/combobox.ts` sobre **Tom Select 2.6**: `combobox(select, { placeholder, permitirVacio, multiple, remoto: { url, params() }, textos })`, `comboboxDe`, `refrescarCombobox` y `destruirCombobox`. Llamarlo dos veces sobre el mismo select devuelve la misma instancia. Funciona como Select2: la lista se monta en `<body>` (así no la tapan encabezados sticky ni la recortan los modales), la búsqueda va dentro de la lista (plugin `dropdown_input`) y hay botón para limpiar. En modo remoto, cada respuesta reemplaza a la anterior, una respuesta atrasada se descarta y la lista se vuelve a consultar al abrir. El `<select>` nativo sigue siendo la fuente de verdad (`select.value` y eventos `change` nativos). Su CSS se importa desde el wrapper (`tom-select.default.css` + `combobox.css`, con el tema que antes tenían Trazabilidad y L.Mat) y no desde app.css. La lógica pura está en `combobox-opciones.ts` y tiene tests. Para Blade inline, `window.combobox()` descarga el módulo al primer uso. Las listas que quedan sin dueño al cerrar un Swal se destruyen solas. **Migrado:** `lmat-lista/index.js` (5 filtros), `catcodificacion/lmat-modal.js` (artículo y color dentro del popup de Swal), `trazabilidad/filter-selects.ts` (3 filtros, Flog remoto; se destruyen antes del dispatch a Livewire y se recrean tras `trazabilidad-filtros-actualizados`), `trazabilidad/scroll-manager.ts` y el bloque select2/jQuery de `programa-tejido/modal/redbooth.blade.php` (excepción aprobada: solo ese bloque y sus 3 líneas de CSS). |
| FE-08 | 20 llamadas `toastr.*` en 5 vistas fuera de PT → `notify.*`: reenconado-cabezuela, urdido/produccion/_scripts, engomado calificar-julios (x2), modulo-produccion-engomado. `app-core.js` pierde `initToastr`. `window.toastr` queda como **adaptador a notify** para PT (balancear, repaso) hasta la fase 21 (HANDOFF #5). Ratchet `toastr.`: 25 → **3** (solo `balancear`). |
| FE-09 | `npm uninstall jquery select2 toastr`. Salen el shim de jQuery 4, los CSS de select2 y toastr en `app.js`, la reasignación duplicada de `window.Swal` en `app.js` y los tipos `JQueryBridge`, `Select2Bridge`, `$` y `jQuery`. Antes se revisó con grep que cada `$(`/`$$(` restante tiene su `$` local (mecánicos OT, alineación, catalogoCodificacion, reservar-programar, PT index.js, catcodificacion) y que no queda `$.fn`, `$.ajax` ni `jQuery(`. |
| FE-10 | `vite.config.js` ya no tiene `manualChunks`. `input` = las entradas actuales + `resources/js/modulos/**/index.ts` por glob (`readdirSync` recursivo, Node ≥ 20) dentro de un `Set`: si 16-componentes dejó su línea ancla, la entrada no se duplica. Probado con un `modulos/prueba-glob/sub/index.ts` temporal, que apareció en el manifest. |
| FE-11 | `resources/js/utils/librerias.ts`: `html2canvas()` y `pdfjs()` con `import()` dinámico, cargados una sola vez y con reintento si falla la red; expuestos como `window.librerias`. pdf.js usa `pdfjs-dist` 6 en su build **legacy** (hay tablets viejas) y el worker se compila como `.js` (`?worker&url`), porque un `.mjs` sin MIME en Apache/IIS rompería el worker. Se quitaron los `<script>` de cdnjs de `mecanicos/reportes/{estado-maquina,ot-diarias}` y `cortes-eficiencia/{cortes-eficiencia,visualizar-cortes-eficiencia}`; `cargarPdfJs()` ahora devuelve `window.librerias.pdfjs()`. |
| FE-12 | `bg-opacity-*` en 12 vistas fuera de PT y de catalogos-atadores: `bg-X bg-opacity-N` → `bg-X/N`, y `bg-black/40 bg-opacity-60` → `bg-black/40` (lo que se ve hoy). Ratchet: 28 → **9** (los 9 son del piloto de 16). `fontawesome-display.css` declara la familia `'Font Awesome 7 Free'`; con `'…6 Free'` el `font-display: swap` no aplicaba a ningún icono. `public/js/catalog-core.js` ya no pisa `window.showToast` y su `CatalogCore.showToast` delega en el global. |

### Decisiones
- **`html2canvas-pro` en vez de `html2canvas`.** La 1.4.1 (la misma versión del CDN) truena con los colores `oklch()` de Tailwind v4 ("Attempting to parse an unsupported color function oklch"). La hoja de estado-máquina usa `text-gray-900`, que en v4 es oklch, así que **la descarga de imagen ya fallaba en producción**. El fork tiene la misma API.
- **Lista en `<body>` y búsqueda dentro de la lista, siempre.** La primera versión dejaba la lista dentro del wrapper y la tapaba el encabezado de la tabla de L.Mat. Con el input en el control, las celdas angostas del modal L.Mat saltaban de línea. Hacerlo como Select2 evita ambas cosas sin opciones extra.
- `maxOptions: null` (mostrar todas las opciones, como Select2). Si el catálogo de artículos AX crece mucho y se nota lento en tablets, bajarlo a ~200 (se sigue buscando sobre todas).
- `window.toastr` como adaptador y no borrado: PT tiene `balancear.blade.php` en su sesión (orden de merge: PT 04-perf antes que 15-02).
- `tsconfig`: sin cambios. Se agregó `resources/js/types/vite-client.d.ts` (`/// <reference types="vite/client" />`) para los imports de CSS y `?worker&url`.

## Archivos
- **Nuevos:** `resources/js/utils/{combobox.ts,combobox.css,combobox-opciones.ts,librerias.ts}`, `resources/js/types/vite-client.d.ts`, `tests/Js/combobox-opciones.test.mjs` (9), `tests/Js/utils-librerias.test.mjs` (2), `15-02-PLAN.md`, evidencias `15-02-evidencia-*.png`.
- **Modificados (propios):** `package.json/lock` (+tom-select, +html2canvas-pro, +pdfjs-dist; −jquery, −select2, −toastr), `vite.config.js`, `resources/js/{bootstrap.js,app.js,app-core.js}`, `resources/js/types/global.d.ts`, `resources/js/lmat-lista/index.js`, `resources/js/catcodificacion/lmat-modal.js`, `resources/js/trazabilidad/{filter-selects,scroll-manager}.ts`, `resources/css/fontawesome-display.css`, `public/js/catalog-core.js`, `scripts/ratchet-baseline.json` (solo bajas).
- **Vistas, solo las líneas autorizadas:** select2 en `catalagos/lmat-lista` (bloque CSS) y `programa-tejido/modal/redbooth` (bloque JS y 3 líneas CSS); toastr en las 5 vistas de FE-08 más 2 comentarios obsoletos (`cargar-catalogos`, `catalogoCodificacion`: pedido #2 de 15-01); CDN en las 4 vistas de FE-11; `bg-opacity-*` en 12 vistas.
- Tamaño: 45 archivos, +871 / −453 sin `package-lock.json`.

## Evidencia
```
npm run typecheck            ✓ sin errores
npm run test:js              # pass 106  # fail 0   (11 nuevos: combobox-opciones, utils-librerias)
npm run build                ✓ (sin chunk vendor)
npm run ratchet              ratchet ok; toastr. 25→3, bg-opacity- 28→9, onclick= 413→412, innerHTML = 394→393 (fijado con --update)
php artisan test             1365 passed (19811 assertions)
vendor/bin/phpstan analyse   [OK] No errors
```

**Navegador (skill `run`).** App real con `php -S` y un router en el scratchpad que apunta todas las conexiones a un sqlite en archivo con el esquema `dbo` adjunto, inyecta un usuario con todos los permisos y crea las tablas y columnas que falten a partir del log. Chromium headless con Playwright, 1280×800. Se corrió lo **mismo antes** (worktree en `c0fab09` con su propio build) **y después**:

| Pantalla | Qué se probó | Antes | Después |
|---|---|---|---|
| L.Mat lista | filtro Orden: escribir "502" + Enter | 1 fila visible | 1 fila visible |
| Modal L.Mat (Swal) | combos de artículo y color dentro del popup | 6 combos, abre | 6 combos, abre sobre el popup (`15-02-evidencia-lmat-modal.png`); al cerrar el Swal se destruyen sus 6 listas |
| Trazabilidad | Flog remoto al abrir; Artículo = A200 → Livewire | 6 flogs; A200 y el combo se recrea | 6 flogs (`…-trazabilidad.png`); "1005" → solo FL-1005; A200 y el combo se recrea |
| Redbooth en Programa Tejido | tareas remotas (endpoint simulado); elegir; reabrir en otra fila | 501/502, valor 501 | igual (`…-redbooth.png` vs `…-redbooth-antes.png`); en la otra fila el valor queda vacío y la lista se vuelve a consultar |
| Cortes de eficiencia | `window.librerias.pdfjs()` renderiza un PDF de dompdf | (CDN) | 1 página 612×792 dibujada, worker `.js` propio, 0 peticiones fuera del host |
| Reporte de mecánicos | `window.librerias.html2canvas()` sobre la página | (CDN) | canvas 1280×800 (con html2canvas 1.4.1 fallaba por oklch) |
| Reenconado (toastr migrado) | "Editar" sin selección → `notify.info` | toast de Toastr | toast nativo (`…-toast-reenconado.png`) |

**Errores de consola:** ninguno nuevo. Los únicos que salen (folio en cortes de eficiencia, `/programa-tejido/columnas`, catálogos de julios en producción Urdido/Engomado) aparecen **igual antes y después**: son tablas o `INFORMATION_SCHEMA` que el sqlite del arnés no tiene. `jQuery` ya no existe en `window`; `toastr`, `combobox`, `librerias` y `showToast` sí.

**Code-review (skill, high), 2 pasadas sobre `c0fab09..HEAD`:**
- **Corregido.** `else alert(...)` huérfano en `modal-calificar-julios{,-eng}` después de quitar `if (typeof toastr…)`. Era un SyntaxError que tumbaba todo el script del parcial. Se confirmó con `node --check` sobre los `<script>` extraídos de la vista: la versión con el error falla y la corregida pasa, igual que las otras 4 vistas con toastr migrado. `abrirModalCalificarJuliosEng` existe en `/engomado/reimpresion-engomado`.
- **Corregido.** Redbooth: `clear(true)` no limpiaba el `<select>` nativo, así que "Guardar" en otra fila mandaba la tarea anterior. También se corrigió que la lista remota no se volvía a consultar al reabrir, que se acumulaban resultados de búsquedas viejas, y que el blur de la búsqueda en `scroll-manager` no la encontraba (vive en `.ts-dropdown`). Ahora las listas huérfanas se limpian con un `MutationObserver` sobre los hijos de `<body>`.
- **No aplican.** `maxOptions` (ver Decisiones); CSS muerto en `trazabilidad/index.css` y el adaptador toastr (HANDOFF #5 y #6). La primera pasada tomó una base local desfasada y reportó hallazgos de archivos de otras fases (monitoreo, PT); no son de este diff.

## Pendientes / deuda
- HANDOFF 15-02 #5–#9: PT migra toastr y después se borra el adaptador; CSS select2 muerto en Trazabilidad; `<br>` en calendarios; CLAUDE.md.
- No hay un test que valide la sintaxis del JS inline de las vistas Blade. El bug de calificar-julios solo lo detectó el code-review. Un test node que extraiga los `<script>` de `resources/views/**` (sustituyendo `{{ }}`/`@json`) y corra `node --check` lo habría detectado (propuesta para BASE / fase 21).
- Visual: el combo de Redbooth ahora usa el tema de Trazabilidad y L.Mat (34 px, bordes redondeados) en vez del gris por defecto de Select2. El modal no cambió.

## Despliegue
Solo frontend: **`npm ci && npm run build`** (hay dependencias nuevas), luego `php artisan view:clear` (cambiaron vistas). Sin migraciones, sin `.env`, sin `optimize`. Rollback: revertir la rama y repetir `npm ci && npm run build`.
