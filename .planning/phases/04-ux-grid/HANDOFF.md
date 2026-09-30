# HANDOFF — PT 04-perf (rama `claude/pt-04-perf`)

## A. Ya hecho fuera de la propiedad PT (el integrador debe aceptarlo o rehacerlo)

| Archivo (dueño) | Cambio | Por qué |
|---|---|---|
| `tests/Feature/ProgramaTejidoJsSyntaxTest.php` (fuera del glob `tests/Feature/Planeacion/**`) | 1 aserción + su comentario: `'window.PT_BOOT'` → `'<script type="application/json" id="pt-boot">'` | El test fija el contrato de `scripts/main.blade.php`; el corte 5 cambió `PT_BOOT` de código a JSON. Sin el cambio la suite queda en rojo. El resto del test (tamaño < 20 KB, bundle de Vite) no se tocó. |

## B. Pedidos

| # | Archivo (dueño) | Cambio pedido | Por qué |
|---|---|---|---|
| 1 | `resources/views/components/programa-tejido/req-programa-tejido-line-table.blade.php` (no está en la fila PT) | Pasar a la fila PT del protocolo, o que su dueño mueva su `<script>` (19 KB) a `resources/js/programa-tejido/` | Es el mayor bloque inline que queda en la grilla; solo lo usa Programa Tejido |
| 2 | `resources/views/components/navbar/navbar.blade.php` (UX-global) | Mover `mostrarModalDiasLiberar` (2 KB inline) a un módulo | Mismo motivo; PT solo podía tocar 1 línea de ese archivo |
| 3 | `modulos/programa-tejido/modal/redbooth.blade.php` (15-02 en esta ola) | Al terminar 15-02, devolverlo a PT para mover su `<script>` (18 KB) al bundle | Último bloque inline de PT en la página de Programa |

---

# HANDOFF — PT-TS 1 (rama `claude/pt-ts-1`)

## A. Ya hecho fuera de la fila PT-TS 1 (aprobado en el plan o mínimo para no dejar la suite roja)

| Archivo (dueño) | Cambio | Por qué |
|---|---|---|
| `resources/views/layouts/app.blade.php` (UX-global / TS-base: líneas `<script>` del layout) | Se quitan las 3 líneas `@if … <script src="js/programa-tejido-menu.js"> … @endif` | El archivo era código muerto (busca `#layoutBtnAddMenu`, que no existe; su "alta de pronósticos" apunta a una ruta inexistente) y se borró. Aprobado en el plan. **Al mergear con TS-base:** si su rama también tocó esa zona, quedarse sin esas 3 líneas |
| `resources/js/app-core.js` (TS-base) | Se quita `import "./programa-tejido/modal-cache-bootstrap.js";` | El módulo solo ponía `window.__PT_DEBUG = false`, que `index.js` ya hace antes de leerlo; se borró. **Al mergear con TS-base (`app-core.ts`):** no volver a importar ese archivo |
| `tests/Unit/TrazabilidadStructureTest.php` (Trazabilidad) | Ruta `js/modulos/redbooth/modal.js` → `modal.ts` (1 línea) | El modal de Redbooth pasó a TS; la aserción del contenido no cambia |
| `tests/Js/redbooth-boot.test.mjs` (lo convertiría TS-base) | → `redbooth-boot.test.ts` | Redbooth es de esta fila; **TS-base: no volver a convertirlo** |
| `docs/documentacion-modulos/02-planeacion-programa-tejido.md` | 1 línea sobre `modal-cache-bootstrap.js` | El archivo ya no existe |
| `modulos/programa-tejido/modal/{marbetes,repaso,act-calendarios,redbooth}.blade.php` (PT) | Sin `onclick` en los botones de guardar/crear; Redbooth con `data-redbooth-boot` en vez de `<script type="application/json">` | Son los Blade de los modales que migró esta fila |

## B. Pedidos

| # | Para | Cambio pedido | Por qué |
|---|---|---|---|
| 1 | **PT-TS 2** (`programa-tejido/index.js`) | Que el parche de `window.fetch` use `rutaSuperficie()` de `resources/js/programa-tejido/rutas.ts` en vez de su propio `rewriteUrl` | Hoy son dos copias de la misma regla (Programa → Muestras); las une un test de paridad (`programa-tejido-rutas.test.ts`) |
| 2 | **PT-TS 2** | Publicar `window.agregarRegistroSinRecargar` (o llamarla desde un evento) | `modales/repaso.ts` la busca para insertar el repaso sin recargar, pero vive en el scope de `index.js` y no se publica desde 04-perf: hoy el repaso se crea y solo avisa (igual que antes de PT-TS 1) |
| 3 | **PT-TS 2** | Publicar `formatearValorCelda` y las columnas (o un evento `pt:fila-actualizada`) | `balancear.ts` → `actualizarRegistrosBalanceo` pinta las celdas con el formateador de respaldo porque el de la grilla no es global (igual que antes) |
| 4 | **TS-base** | Si `tests/Js/utils-fake-dom.mjs` pasa a `.ts`, cambiar el import de `tests/Js/programa-tejido-acciones.test.ts` y quitar su `@ts-expect-error` | El test importa el DOM falso por su nombre actual |
| 5 | **Owner** | Decidir el hallazgo H1 del SUMMARY (Liberar en Muestras usa las rutas de Programa) | Cambia a qué tabla escribe Liberar desde Muestras; no se tocó |
| 6 | **PT 05.1** | Partir `LiberarOrdenesController::index()`, `liberar()` y `guardarMarbetes()` | PT-TS 1 solo tocó sus `catch` (SEC-07); quedan con `@SuppressWarnings` y ese destino |
| 7 | **FE / ADOP** | Un solo helper de "mensaje de error de la respuesta" en `resources/js/utils/` | Hoy hay variantes por módulo (`urdido/comun/pagina.ts` `mensajeError`, `programa-tejido/respuesta.ts`, los `logica.ts` de Liberar y Redbooth); los del bundle de la grilla no pueden importar `utils/http.ts` en el test del bundle (arrastra sweetalert2) |
| 8 | **Dueño de `docs/documentacion-modulos/14-frontend-js.md`** | Quitar las menciones a `programa-tejido-menu.js` y `modal-cache-bootstrap.js` | Ya no existen |
