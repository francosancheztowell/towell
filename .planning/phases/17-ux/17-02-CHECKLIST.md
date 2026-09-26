# UX-18 — Checklist por pantalla (para las sesiones 19-xx)

Fase 17-02 dejó resuelto lo global (layout, navbar, errores, utils). Esta lista es lo que **cada
pantalla** debe cumplir al migrarse en su 19-xx. Copiarla al SUMMARY del módulo con una fila por
pantalla y marcar ✅ / ❌ / n/a. Capturas antes/después a 1280×800 y 768×1024.

Referencias: `docs/cerebro-towell/Arquitectura/receta-componentes.md` (componentes),
`.planning/phases/19-modulos/19-00-RECETA.md` (migración JS), `17-02-SUMMARY.md` (qué hace ya el layout).

## 1. Estructura

| # | Punto | Cómo se verifica | Qué hacer si falla |
|---|---|---|---|
| 1.1 | **`<title>` propio** | La pestaña dice "<Pantalla> · Towell", no "Producción Towell". | El layout lo toma de `@section('page-title')`; si la vista no lo tiene, agregar `@section('title', 'Nombre')`. |
| 1.2 | **Un solo `<h1>`** | `document.querySelectorAll('h1').length === 1` | El título va en `@section('page-title')` (con `<x-layout.page-title>` o texto). Los encabezados dentro del contenido son `<h2>`/`<h3>`. |
| 1.3 | **Andón** | Solo pantallas fijas en TV/tablet todo el turno. | `@section('viewport-fijo', '1')` bloquea el pinch-zoom. Ninguna otra pantalla lo lleva. |

## 2. Tablet (iPad / Android)

| # | Punto | Cómo se verifica | Qué hacer si falla |
|---|---|---|---|
| 2.1 | **Sin acciones solo por clic derecho** | Todo lo que abre `contextmenu` se puede abrir con el dedo. | `accionesTactiles(root, selector, abrirMenu)` + `botonAcciones(fila, abrirMenu)` (`resources/js/utils/acciones-tactiles.ts`; inline: `window.accionesTactiles.enlazar/boton`). Reemplaza el listener `contextmenu` (el helper también atiende el clic derecho). La zona lleva la clase `towell-acciones-zona`. |
| 2.2 | **Folios y datos se copian** | Mantener el dedo / arrastrar sobre un folio lo selecciona. | No poner `select-none`/`user-select:none` en tablas, celdas ni inputs; solo en controles. |
| 2.3 | **Pinch-zoom** | Se puede ampliar con dos dedos. | No redefinir `<meta name="viewport">` en la vista. |
| 2.4 | **Controles ≥ 44 px** | Botones de fila y de modal. | `min-h-touch` / `size-touch` o `x-ui.button`. |
| 2.5 | **Texto ≥ 12 px** | Nada con `text-[9px]`, `text-[10px]`, `text-[11px]`, `font-size < 12px`. | `text-caption` o `text-xs`. |

## 3. Accesibilidad

| # | Punto | Cómo se verifica | Qué hacer si falla |
|---|---|---|---|
| 3.1 | **`aria-label` en botones de ícono** | Todo `<button>`/`<a>` sin texto visible (×, ✎, 🗑, ⋮, flechas). | `aria-label="Cerrar"`, `"Editar folio X"`… y `aria-hidden="true"` en el `<i>`. |
| 3.2 | **Foco visible** | Tab recorre la pantalla y se ve dónde está el foco. | El anillo global (`:focus-visible` en app.css) ya aplica; no poner `outline:none` sin un `focus-visible:ring-*` a cambio. |
| 3.3 | **Modales** | Esc cierra, el foco entra y vuelve. | `x-ui.modal-base`. |
| 3.4 | **Estados anunciados** | Carga y resultados. | `window.loader.show()/hide()`, `x-ui.skeleton`, `notify.*` (aria-live). |

## 4. Mensajes y errores

| # | Punto | Cómo se verifica | Qué hacer si falla |
|---|---|---|---|
| 4.1 | **Flash de servidor** | `redirect()->with('error', …)` se ve. | Nada: el layout monta `x-ui.flash`. No duplicar con un Swal propio. |
| 4.2 | **Toasts con la duración única** | 5 s, debajo del navbar. | `notify.success/error/warning/info`; quitar `Swal.fire({ toast: true, timer: … })` y `showToast` locales (33 archivos, ver HANDOFF). |
| 4.3 | **Sesión expirada (419/401)** | Con la sesión vencida, una acción muestra "Tu sesión expiró…" y recarga. | Usar `window.http` (ya lo maneja); Livewire ya lo maneja. Un `fetch` crudo no. |
| 4.4 | **Sin conexión** | Con la red caída aparece el banner amarillo. | Nada (global). No mostrar alertas propias de "sin red". |
| 4.5 | **Errores sin detalle interno** | Ningún `getMessage()` al usuario (SEC-07). | `HandlesApiErrors`. |
| 4.6 | **Español con acentos** | "sesión", "conexión", "contraseña", "número", "módulo"… | Corregir el texto; validaciones de Laravel ya salen en español (`lang/es`). |

## 5. Estados de la pantalla

| # | Punto | Qué hacer |
|---|---|---|
| 5.1 | **Vacío** | `x-empty.empty-state` o `x-ui.table-empty` con qué hacer a continuación. |
| 5.2 | **Cargando** | `x-ui.skeleton` / `window.loader`, nunca un overlay propio. |
| 5.3 | **Error de carga** | Mensaje + "Reintentar", sin tabla vacía muda. |
| 5.4 | **Permisos** | Botones Crear/Editar/Eliminar solo si `userCan()`. |

## 6. Evidencia mínima por pantalla

- Captura 1280×800 y 768×1024, antes y después.
- `console --errors` sin errores nuevos.
- Fila en el SUMMARY: `pantalla | 1.1 | 1.2 | 2.1 | 2.5 | 3.1 | 4.2 | 4.3 | notas`.
