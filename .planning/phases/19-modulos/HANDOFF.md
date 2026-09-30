# HANDOFF — fase 19 (módulos)

Pedidos de cambios en archivos que no son de la sesión que los detecta. Cada fila: archivo, cambio, por qué, quién lo pidió.

## De 19-01 (Urdido + Engomado)

| # | Para | Archivo | Cambio | Por qué |
|---|---|---|---|---|
| U1 | 19-05 | `resources/js/urd-eng/edicion-ordenes.ts` | Importar el modal de calificar julios desde `resources/js/modulos/urdido/comun/calificar-julios/` en vez de llamar `window.abrirModalCalificarJuliosEng` | Hoy queda un puente `window` (marcado `// PUENTE 19-01`) solo por este llamador; al importarlo se quita el puente |
| U2 | 17-02 | `.planning/phases/17-ux/17-02-CHECKLIST.md` | Cuando exista, enlazarlo desde `19-00-RECETA.md` (cabecera) — o avisar a la siguiente 19-xx que lo haga | La receta usa una checklist mínima provisional (§7) |
| U3 | 17-02 | helper UX-06 (long-press / botón "⋮") | Al publicarse, adoptarlo en los filtros por menú contextual de `engomado/captura-formula` | Hoy esos filtros solo se abren con clic derecho (inalcanzable en iPad) |
| U4 | 17-02 / DS | `resources/views/components/ui/modal-base.blade.php` | Cambiar el `onclick="{{ $closeJs }}"` del botón × por un atributo de datos que lea `componentes/dialog.ts` | Cada vista que adopta `x-ui.modal-base` hereda un `onclick=` en el HTML renderizado; los tests guardianes de 19-xx tienen que revisar el fuente Blade en vez del HTML |
| U5 | 17-02 | navbar del layout (`components/navbar/navbar.blade.php`) | A 768 px el `<h1>` del título tapa el botón "Crear" en Catálogo de Ubicaciones y de Máquinas: el clic real cae en el título (ya pasaba antes de 19-01) | Tablet es el objetivo de planta |
| U6 | DS / 19-xx | `resources/views/components/buttons/catalog-actions.blade.php` | Pasar Filtrar/Restablecer de `onclick` + `<script>` inline a `data-accion`; en 19-01 Julios/Máquinas dejaron de usarlo por eso (copian el markup) | Ratchet y receta |
| U7 | 19-05 | `ProgramarUrdidoController::reimpresionVentanaImprimir` | Sin `orden_id` responde 400 con un `<script>alert(…)</script>` inline | Receta §4 |
| U8 | FE / 17-02 | `resources/js/charts.js` | Su comentario dice "4 vistas": las de Urdido/Engomado ya no lo cargan (Chart.js va en sus bundles); lo siguen usando `tejido/reportes/rpm-semanal` y `atadores/reportes/km` | Comentario desactualizado |
| U9 | 20 / owner | `app/Helpers/FolioHelper.php` | `obtenerSiguienteFolio` consulta `INFORMATION_SCHEMA.COLUMNS`: el arnés sqlite no puede crear BPM nuevos. Opcional: capacidad por driver | Solo afecta pruebas locales |

## De 19-02 (Tejido)

| # | Para | Archivo | Cambio | Por qué |
|---|---|---|---|---|
| T1 | DS / 16 | `resources/views/components/buttons/inventory-sequence-actions.blade.php` | Borrarlo | Solo lo usaban las 4 secuencias de Tejido; la vista común `tejido/secuencia/comun.blade.php` pone sus botones con `data-accion` (el componente emitía `onclick`) |
| T2 | FE | `resources/js/modulos/tejido/comun/pagina.ts` y `resources/js/modulos/urdido/comun/pagina.ts` | Unificar en `resources/js/utils/` (`leerDatos`, `mensajeError`, `ErrorApi`, `exigirExito`) | Dos copias casi iguales en dos módulos |
| T3 | 19-04 / DS | `resources/views/components/telares/telar-requerimiento.blade.php` | 11 `onclick` → `data-accion`; luego quitar los puentes `// PUENTE 19-02` de `resources/js/tejido/inventario-telas.ts` (`abrirModalSeleccion`, `confirmarSeleccion`, `mostrarCalendarioParaActualizar`, `loadRequerimientos`…). Tiene ~24 `innerHTML` | 19-02 solo podía tocar su texto < 12 px |
| T4 | FE (Ola 4) | `vite.config.js` | Si `resources/js/tejido/inventario-telas.ts` pasa a `modulos/`, quitar su entrada fija | Vite congelado en Ola 3 |
| T5 | 17-02 | `resources/css/tejido/inventario-telas.css` | `--inv-text-xs`: mínimo `0.75rem` (hoy `clamp(0.65rem…)` ≈ 10 px) | UX-07 |
| T6 | 17-02 | navbar (`components/navbar/navbar.blade.php`) | Igual que U5: a 768 px el `<h1>` "Cortes de Eficiencia" / "Producción Reenconado Cabezuela" se parte en 3 líneas y tapa "Crear"/"Filtros" | Tablet |
| T7 | owner | `routes/modules/tejido.php` | `finalizar`/`reabrir` de marcas (`modificar,177`), `finalizar` de cortes (`modificar,105`) y `DELETE produccion-reenconado/{folio}` (`eliminar,27`) están en **enforce** (sin `,auditar`), a diferencia de sus hermanas. 19-02 no los cambió (regla: nunca enforce nuevo, pero tampoco quitarlo sin decisión) | Consistencia AuthZ antes de SEC-06 |
| T8 | 20 | `.planning/phases/20-arq-sec/20-03-MAPA-AUTHZ.md` | Hueco `PUT modulo-cortes-de-eficiencia/{id}`: ya responde 410 (sigue en auditar). Hueco `POST produccion/reenconado-cabezuela`: misma acción y validación que la ruta nueva (test en `ReenconadoTest`) | Cerrar filas del mapa |
| T9 | 19-01 / arnés | `.planning/phases/19-modulos/19-01-arnes/boot.php` (y el de 19-02) | Adjuntar un `INFORMATION_SCHEMA` con `COLUMNS` para que `nextFolio()` funcione (igual que U9) | Alta de reenconado da 500 solo en el arnés |

**Estado de U1 y U7 (cerrados por 19-05):** U1 hecho (`abrirCalificarJulios` importado, puente quitado); U7 hecho (422 sin `<script>`).

## De 19-05 (Programa Urdido-Engomado)

| # | Para | Archivo | Cambio | Por qué |
|---|---|---|---|---|
| R1 | FE / ADOP (cuando se descongele `vite.config.js`) | `vite.config.js` + `resources/js/programa-urd-eng/reservar-programar.ts` | Quitar la entrada fija `resources/js/programa-urd-eng/reservar-programar.ts` y borrar el stub | El código se movió a `resources/js/modulos/programa-urd-eng/reservar-programar/index.ts` (entrada por glob); el stub vacío solo existe para que el build no falle |
| R2 | owner / 20 | `routes/modules/urdido.php` (`programar-urdido/{intercambiar-prioridad, actualizar-prioridades, guardar-observaciones, marcar-incorrecto, actualizar-status}` → `modificar`; `actualizar-calidad` → `registrar`) y `routes/modules/engomado.php` (`programar-engomado/{intercambiar-prioridad, guardar-observaciones, actualizar-prioridades, actualizar-status}` → `modificar`) | Con el idrol de la consulta de `20-03-MAPA-AUTHZ.md`: `->middleware('module.permission:<acción>,<idrol>,auditar')` y quitar la ruta de `SIN_PERMISO_DE_MODULO` en el guardián | Los archivos de rutas son de 19-01; el idrol aún no llega. `actualizar-prioridades` de Engomado ya valida la entrada (19-05) |
| R3 | 19-05 siguiente / DS | `resources/css/urd-eng/program-board.css` | Subir a `.75rem` los `font-size` < 12 px (líneas ~155 `.7rem`, 178 `.6rem`, 192 `.72rem`, 215/238/293 `.66rem`, 482 `.65rem`, 529 `.7rem`) | UX-18 2.5; el CSS no estaba en la fila de 19-05 |
| R4 | 19-01 / quien toque el arnés | `.planning/phases/19-modulos/19-01-arnes/README.md` y `19-00-RECETA.md` §8 | Para el "antes", no symlinkear `vendor`: composer sigue el symlink y carga `App\` del árbol de trabajo. Copia con hardlinks (`cp -al`), copias reales de `vendor/composer` y `vendor/autoload.php` y `composer dump-autoload --no-scripts` (ver `19-05-arnes/README.md`) | El "antes" corría con los controllers nuevos |
| R5 | PT / FE | `tests/Js/agruparTelares.test.cjs` | Borrarlo o apuntarlo a `resources/js/modulos/programa-urd-eng/creacion-ordenes/logica.ts` | Prueba una copia vieja de `agruparTelares` del `public/js/…/creacion-ordenes.js` borrado; la versión vigente la cubre `programa-urd-eng-creacion-ordenes.test.mjs` |
| R6 | 20 / owner | `app/Http/Controllers/PDFController.php` (~línea 163, `generarPDFUrdidoEngomado`) | `'Error al generar PDF: '.$e->getMessage()` → `HandlesApiErrors` | SEC-07; lo usa la descarga de edición de órdenes, pero el controller no es del módulo |
| R7 | Tests (dueño de `tests/Unit/Programas/`) | `tests/Unit/Programas/ProgramBoardStructureTest.php` | Aceptar `from './sortable-board.ts'` (hoy busca el import sin extensión) | La receta pide imports con `.ts`; `program-board.ts` conserva el import sin extensión solo por ese test |
| R8 | CAL-03 | lista de excepciones deliberadas de `22-CONTEXT.md` | Agregar `InventarioTelaresService::parseDateFlexible` (usa `rescue()`, sin `report()`) y el fallback de `ProgramaPrioridadService::loadRecordsWithOptionalPriority` | Intentar formatos / columna opcional es flujo normal; con el poll de 15 s del tablero, `report()` saturaría el monitoreo |

## De 19-08 (Mantenimiento)

| # | Para | Archivo | Cambio | Por qué |
|---|---|---|---|---|
| M1 | 17-02 / FE | `resources/css/app.css` | Agregar `@source '../**/*.ts';` (hoy solo `../**/*.js` y Blade) | Una clase de Tailwind que solo aparece en un `.ts` de `resources/js/modulos/**` no se genera. 19-08 dejó las clases alternadas en `data-clase-*` del `<template>` para no depender de esto |
| M2 | 17-02 | navbar (`components/navbar/navbar.blade.php`) | Igual que U5/T6: a 768 px "Reporte de Fallos y Paros" y "Operadores de Mantenimiento" se parten y tapan los botones del navbar | Tablet (el alta de paro se usa en piso) |
| M3 | owner | `SYSRoles` | Confirmar que "Mantenimiento" (el nombre que revisaba el GET de operadores) es el idrol **53**. El catálogo de operadores ahora revisa `acceso/crear/modificar/eliminar` por 53 | Siempre por idrol (`RutasDestructivasPermisoTest`) |
| M4 | owner | `MantenimientoParosController::store()` | Si se quiere cerrar del todo la carrera de la cascada del lado servidor: validar que `maquina` pertenezca a `depto` (hoy no se valida; el front ya descarta respuestas rezagadas) | Defensa en profundidad; cambia reglas del alta abierta, decisión del owner |
