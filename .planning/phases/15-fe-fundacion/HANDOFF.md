# HANDOFF — Fase 15-01 (utils TS)

Cambios que 15-01 necesita en archivos de otro dueño. Ninguno bloquea el merge.

| # | Archivo (dueño) | Cambio | Por qué |
|---|---|---|---|
| 1 | `CLAUDE.md` (BASE / integrador) | En "HTTP & notificaciones": rutas `utils/http.ts` y `utils/notifications.ts`; firma real `http.get/delete(url, config?)` y `http.post/put/patch(url, data?, config?)` (el body de un DELETE va en `config.data`); los toasts son nativos (no Toastr); `utils/format.ts` (`escapeHtml`, `debounce`, `formatNumber`, `formatDate`, `formatDateTime`) y `utils/dom.ts` (`qs`, `qsa`, `delegate`, `onReady`); 419/401 avisan y recargan solos. | CLAUDE.md apunta a archivos borrados y documenta `http.get(url, data?)`/`http.delete(url, data?)`, que ignoraría el segundo argumento como datos. |
| 2 | `resources/views/modulos/cargar-catalogos.blade.php:92`, `resources/views/catalagos/catalogoCodificacion.blade.php:657` (vistas, ADOP / 19-xx) | Comentario: `resources/js/utils/notifications.js → toastr` pasa a `resources/js/utils/notifications.ts → toasts nativos`. | Solo el comentario quedó obsoleto; el código funciona igual. |
| 3 | Fase 12 (`resources/js/monitoreo/**`) | Nada que cambiar: `towell:http-error` se emite en **`window`** con `detail { status, url, method }`; `url` sin query ni hash, `method` en mayúsculas, `status 0` = error de red; las peticiones canceladas no se emiten. Solo lo emite `window.http`: los `fetch` crudos siguen sin emitirlo hasta que se migren. | Contrato 11 §4. |
| 4 | Tejido (19-xx) | `resources/js/tejido/inventario-telas.ts`, `actualizarRegistroConNuevaFecha`: usar `(errorActualizar as HttpError).message` en vez de `.response?.data?.message`. | Bug previo: el mensaje del servidor nunca se muestra. 15-01 solo tipó sin cambiar comportamiento. |

---

# HANDOFF — Fase 15-02 (librerías y Vite)

Cambios que 15-02 necesita en archivos de otro dueño. Ninguno bloquea el merge.

| # | Archivo (dueño) | Cambio | Por qué |
|---|---|---|---|
| 5 | `resources/views/modulos/programa-tejido/balancear.blade.php` (3 llamadas) y `modal/repaso.blade.php` (respaldo `toastr[fn]`) (PT, sesión `claude/pt-04-perf`) | `toastr.error/warning(msg)` → `notify.error/warning(msg)`; en repaso, quitar la rama `typeof toastr`. | Toastr ya no existe: `bootstrap.js` deja `window.toastr` como adaptador a `notify` (ignora título y opciones). Cuando PT migre, quien sea dueño de FE borra el adaptador y el tipo `ToastrClient` de `types/global.d.ts` (ratchet `toastr.` → 0). |
| 6 | `resources/css/trazabilidad/index.css` líneas 10–75 (Trazabilidad, 19-xx) | Borrar el bloque `/* === Estilo de los selects (select2) … */` (`#form-filtros .select2-*`, `.traza-select2-dd`). | CSS muerto: Select2 ya no existe y el tema del combobox (`resources/js/utils/combobox.css`) da el mismo aspecto. En `lmat-lista.blade.php` ya se borró en esta fase. |
| 7 | `resources/js/trazabilidad/types.ts:61` (Trazabilidad, 19-xx) | Comentario: quitar "jQuery" de la lista de globals tipadas. | Ya no hay jQuery. |
| 8 | `resources/views/catalagos/calendarios/index.blade.php:467` (Planeación, 19-xx) | `'Restablecido<br>Todos los filtros…'` → `'Restablecido: todos los filtros…'`. | Desde 15-01 `showToast` es el toast nativo y escapa HTML: el `<br>` se ve literal. (`catalog-core.js` tampoco pisa ya `window.showToast`; en la práctica ya no lo hacía porque `app.js` es módulo y corre después.) |
| 9 | `CLAUDE.md` (integrador) | Sección Frontend: "jQuery v4 + Select2 … Toastr" → "Tom Select vía `resources/js/utils/combobox.ts` (`combobox()`; en Blade inline `await window.combobox(select, op)`), SweetAlert2 (modales), Chart.js, SortableJS, Font Awesome. Sin jQuery/Select2/Toastr." Agregar: `window.librerias.html2canvas()` / `.pdfjs()` (carga diferida, `utils/librerias.ts`); cada `resources/js/modulos/<mod>/index.ts` es entrada de Vite por glob (no tocar `vite.config.js`). | CLAUDE.md describe librerías que ya no están. |

El pedido #2 de 15-01 (comentarios obsoletos en `cargar-catalogos` y `catalogoCodificacion`) quedó resuelto aquí: eran líneas de toastr.
