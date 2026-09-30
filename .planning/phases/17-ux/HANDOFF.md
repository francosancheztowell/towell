# HANDOFF — fase 17-02 (UX global) → otros dueños

Rama `claude/17-02-ux-global`. Nada de esto bloquea el merge de 17-02: lo global ya funciona; cada
punto es lo que otro dueño debe adoptar o corregir en sus archivos. Checklist por pantalla:
`17-02-CHECKLIST.md`.

## A. Integrador / BASE

| # | Archivo | Cambio | Por qué |
|---|---|---|---|
| A1 | `.env` de **producción** (Laragon) | `APP_LOCALE=es`. **Ya hecho en `.env.example`** (1 línea, BASE: el prompt de 17-02 lo pide; el integrador decide si lo acepta). | `config/app.php` ya tiene `es` como default, pero la `.env` manda. Sin esto las validaciones siguen en inglés en producción. |
| A2 | `bootstrap/app.php` (sin dueño en la Ola 3) | Acentos en los dos textos de `TokenMismatchException`: `'La sesión expiró. Inicia sesión nuevamente.'` y `'Tu sesión expiró. Inicia sesión nuevamente.'`. | UX-08. Es el flash que ve el usuario en el login tras un 419 de formulario. Solo texto. |
| A3 | `CLAUDE.md` (Frontend) | Agregar: `window.accionesTactiles` (UX-06), banner `towell:conexion` (UX-12), `utils/sesion.ts` (419/401 de http **y** Livewire), toasts de duración única (5 s) debajo del navbar, `@section('title')` / `@section('viewport-fijo')`, `lang/es`. | Que las 19-xx lo encuentren sin leer este SUMMARY. |

## B. PT (Programa Tejido / Muestras)

| # | Archivo | Cambio | Por qué |
|---|---|---|---|
| B1 | `components/navbar/sections/programa-tejido.blade.php` | Cuando migre el navbar de PT: `onclick="mostrarModalDiasLiberar()"` → `data-accion` + `delegate()` e importar `mostrarModalDiasLiberar` de `resources/js/componentes/dias-liberar.ts`. Hoy funciona por el puente `window.mostrarModalDiasLiberar`. | HANDOFF PT B2 cerrado del lado del navbar (el `<script>` inline salió); el onclick queda en el archivo de PT. |
| B2 | `resources/js/programa-tejido/index.js`, `modulos/programa-tejido/liberar-ordenes/index.blade.php` | Menús contextuales → `accionesTactiles()` + `botonAcciones()` (UX-06). | Inalcanzables en iPad. |

## C. Sesiones 19-xx (por módulo)

Todas: aplicar `17-02-CHECKLIST.md` a cada pantalla migrada.

| # | Módulo (sesión) | Archivos | Pedido |
|---|---|---|---|
| C1 | UX-06 clic derecho | `planeacion/alineacion/_script.blade.php`, `catalagos/catalogoCodificacion.blade.php`, `catcodificacion/partials/redbooth.blade.php`, `resources/js/catcodificacion/index.js` (Planeación); `modulos/atadores/programaAtadores/index.blade.php` (19-03); `modulos/engomado/captura-formula/index.blade.php` (19-01); `modulos/mecanicos/ordenes-trabajo/index.blade.php`; `modulos/configuracion/basededatos.blade.php`; `modulos/tejido/reportes/saldos-2026.blade.php`; `resources/js/programa-urd-eng/reservar-programar.ts` (19-05) | Cambiar el `addEventListener('contextmenu', …)` por `accionesTactiles(root, selector, abrir)` y añadir `botonAcciones()` en la fila; clase `towell-acciones-zona` en el contenedor. |
| C2 | UX-03 `<h1>` propio dentro del contenido (con el `<h1>` del navbar quedan dos) | `catalagos/matriz-calibres`, `livewire/mecanicos/verifica-maquina/show`, `modulos/atadores/reportes/{index,atadores,km,programa}`, `modulos/desarrolladores/reportes/{index,programa}`, `modulos/engomado/reportes-engomado-index`, `modulos/mantenimiento/reportes-mantenimiento-index`, `modulos/mecanicos/reportes/{estado-maquina,ot-diarias}`, `modulos/tejedores/reportes/{index,programa}`, `modulos/tejido/reportes/{index,inv-telas,promedio-paros-eficiencia,reporte-marcas-finales,rpm-semanal}`, `modulos/urdido/{reportes-panel-control,reportes-urdido-index}` | El título va en `@section('page-title')`; los del contenido pasan a `<h2>`. (Las vistas PDF y `usuarios/qr` son documentos sueltos: no aplica.) |
| C3 | UX-07 texto < 12 px | 40 vistas con `text-[9–11px]` (`grep -rlE "text-\[(8|9|10|11)px\]" resources/views`) + `components/telares/telar-requerimiento.blade.php` (`md:text-[10px]`, Tejido) | `text-caption`/`text-xs`. |
| C4 | UX-13 toasts locales | 33 archivos con `Swal.fire({ toast: true, timer: … })` (`grep -rlE "toast:\s*true" resources public/js`), más los `showToast` locales con otra firma (engomado/urdido `(icon, title)`, cortes-eficiencia `(options)`) | `notify.success/error/warning/info`: una sola duración (5 s) y debajo del navbar. |
| C5 | UX-11 419 en `fetch` crudos | Todos los `fetch(` que quedan (ratchet: 297) | Pasar a `window.http`: el 419/401 ya da el aviso único y recarga. Un `fetch` no lo hace. |
| C6 | UX-02 títulos | Pantallas sin `page-title` ni `title` (el layout cae al nombre del módulo de SYSRoles o a "Producción Towell") | `@section('title', '…')` cuando el módulo no es un buen título (detalle, captura, reporte). |
| C7 | UX-04 andón | Crudo (`crudo.index`) ya está en la lista del layout. Si otra pantalla es andón (TV fija), `@section('viewport-fijo', '1')`. | Solo andón bloquea el pinch-zoom. |
| C8 | UX-14 "×" sin label | Atadores, Departamentos y los modales a mano que quedan (HANDOFF 16 C2) | `aria-label="Cerrar"` o migrar a `x-ui.modal-base` (ya lo trae). |

## D. MON-B (información)

`components/navbar/sections/user-modal.blade.php`: se cambiaron 3 `text-[10px]` por `text-xs` y se
agregó `aria-label` al lápiz de "Editar nombre" (UX-07/UX-14). Sin cambios de lógica ni de ids.
