# HANDOFF — Fase 15-01 (utils TS)

Cambios que 15-01 necesita en archivos de otro dueño. Ninguno bloquea el merge.

| # | Archivo (dueño) | Cambio | Por qué |
|---|---|---|---|
| 1 | `CLAUDE.md` (BASE / integrador) | En "HTTP & notificaciones": rutas `utils/http.ts` y `utils/notifications.ts`; firma real `http.get/delete(url, config?)` y `http.post/put/patch(url, data?, config?)` (el body de un DELETE va en `config.data`); los toasts son nativos (no Toastr); `utils/format.ts` (`escapeHtml`, `debounce`, `formatNumber`, `formatDate`, `formatDateTime`) y `utils/dom.ts` (`qs`, `qsa`, `delegate`, `onReady`); 419/401 avisan y recargan solos. | CLAUDE.md apunta a archivos borrados y documenta `http.get(url, data?)`/`http.delete(url, data?)`, que ignoraría el segundo argumento como datos. |
| 2 | `resources/views/modulos/cargar-catalogos.blade.php:92`, `resources/views/catalagos/catalogoCodificacion.blade.php:657` (vistas, ADOP / 19-xx) | Comentario: `resources/js/utils/notifications.js → toastr` pasa a `resources/js/utils/notifications.ts → toasts nativos`. | Solo el comentario quedó obsoleto; el código funciona igual. |
| 3 | Fase 12 (`resources/js/monitoreo/**`) | Nada que cambiar: `towell:http-error` se emite en **`window`** con `detail { status, url, method }`; `url` sin query ni hash, `method` en mayúsculas, `status 0` = error de red; las peticiones canceladas no se emiten. Solo lo emite `window.http`: los `fetch` crudos siguen sin emitirlo hasta que se migren. | Contrato 11 §4. |
| 4 | Tejido (19-xx) | `resources/js/tejido/inventario-telas.ts`, `actualizarRegistroConNuevaFecha`: usar `(errorActualizar as HttpError).message` en vez de `.response?.data?.message`. | Bug previo: el mensaje del servidor nunca se muestra. 15-01 solo tipó sin cambiar comportamiento. |
