# 19-00 — Receta común de la fase 19 (JS inline → TS)

**Autor:** sesión 19-01 (2026-09-26). **Aplica a:** todas las 19-xx. Deriva de `19-CONTEXT.md` y la corrige donde el código manda (ver §0).
**Checklist UX-18:** `../17-ux/17-02-CHECKLIST.md` (la escribe 17-02; si aún no está en tu base, usa §7 de esta receta y enlázala en tu SUMMARY cuando llegue).

---

## 0. Correcciones a 19-CONTEXT (medidas, no opiniones)

| 19-CONTEXT dice | Realidad | Qué hacer |
|---|---|---|
| Datos por `<script type="application/json">` | `scripts/ratchet.mjs` cuenta **todo** `<script>` sin `src` en Blade, JSON incluido: la isla no baja la métrica | Datos en **atributos** `data-*='@json(...)'` sobre un nodo raíz (como el piloto `catalogos-atadores` y `receta-componentes.md` §8) |
| Mover el `<script>` a TS baja el ratchet | `fetch(`, `Swal.fire`, `innerHTML =` y `X-CSRF-TOKEN` se cuentan también en `resources/js/**/*.ts` | Al mover, **reescribir**: `http`, `notify`, `textContent`/`replaceChildren` o plantillas escapadas |
| `delegate()` ya adoptado | Nadie lo usaba antes de 19-01 | Esta receta fija el patrón (§3) |

## 1. Estructura

```
resources/js/modulos/<mod>/<pantalla>/index.ts   ← entrada Vite (glob; NO tocar vite.config.js)
resources/js/modulos/<mod>/<pantalla>/logica.ts  ← funciones puras (sin DOM) → tests node
resources/js/modulos/<mod>/comun/**              ← lo compartido por varias pantallas del módulo
tests/Js/<mod>-<pantalla>.test.mjs               ← node --test sobre logica.ts
```

- `index.ts` solo **cablea**: lee la config, instala `delegate()`, llama a la lógica. Si pasa de ~400 líneas, parte en archivos por responsabilidad (`tabla.ts`, `modal-x.ts`), todos importados desde `index.ts`.
- La vista lo carga al final:
  ```blade
  @push('scripts')
      @vite('resources/js/modulos/<mod>/<pantalla>/index.ts')
  @endpush
  ```
  (Si el layout de la vista no tiene `@stack('scripts')`, el `@vite` va al final del `@section('content')`.)
- Imports relativos con extensión `.ts`; `import type` para tipos (tsconfig `verbatimModuleSyntax`, `erasableSyntaxOnly`: sin `enum` ni parameter properties).
- Pantalla gigante (> 1 500 líneas): se permite un paso intermedio `legacy.ts` con `// @ts-nocheck` **dentro del mismo commit** solo si al final del plan queda tipada; nunca se mergea `@ts-nocheck`.

## 2. Datos del servidor

```blade
@php
    $configPagina = [
        'rutas' => ['guardar' => route('x.guardar'), 'julios' => route('x.julios')],
        'ordenId' => $orden?->Id,
        'puedeEditar' => $canEdit,
    ];
@endphp
<div id="pagina-produccion" data-pagina='@json($configPagina)'>
```

> **Siempre una variable dentro de `@json(...)`.** La directiva parte su argumento en las comas (`CompilesJson::compileJson` hace `explode(',')`): un array literal con varias claves pierde los flags `JSON_HEX_*` (o no compila) y un apóstrofo en los datos rompe el atributo `data-*='…'`. Con una variable, `@json` escapa `'`, `"`, `<`, `>` y `&`.

```ts
import { leerDatos } from '../comun/pagina.ts';
const cfg = leerDatos<ConfigPagina>(document.getElementById('pagina-produccion'));
```

- **Rutas resueltas en PHP** con `route()`; nunca `'/modulo/' + id` a mano. Para rutas con parámetro, pasa la plantilla con un marcador: `route('x.show', ['id' => '__ID__'])` y en TS `url.replace('__ID__', encodeURIComponent(id))`.
- Nada de `{{ csrf_token() }}` en JS: `http` lo manda solo.
- Valores por fila (id, folio, estado) en `data-*` de la fila, no en closures de Blade.

## 3. Eventos

```blade
<button type="button" data-accion="finalizar">Finalizar</button>
<input data-accion-cambio="kg-bruto" data-id="{{ $r->Id }}">
```

```ts
import { delegate } from '../../../utils/dom.ts';
const acciones: Record<string, (el: HTMLElement, ev: Event) => void> = { finalizar, ... };
delegate(raiz, 'click', '[data-accion]', (ev, el) => acciones[el.dataset.accion ?? '']?.(el, ev));
delegate(raiz, 'change', '[data-accion-cambio]', ...);
```

- `onclick=`/`onchange=` → `data-accion` / `data-accion-cambio`. Un solo `delegate` por tipo de evento en la raíz (sirve también para filas agregadas por JS).
- Cerrar modal al tocar el fondo: `x-ui.modal-base :close-on-backdrop="true"`, no `onclick="if(event.target===this)…"`.

## 4. HTTP, avisos, formato

| Antes | Después |
|---|---|
| `fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':…}, body: JSON.stringify(d)}).then(r => r.json())` | `await http.post(url, d)` (lanza `HttpError`; 422 → `notify.validation(err.errors)`) |
| `Swal.fire({toast:true,…})`, `showToast()`, `mostrarToast()` locales | `notify.success/error/warning/info(msg)` |
| `Swal.fire({icon:'error', title, text})` | `notify.alert(text, title, 'error')` |
| `Swal.fire({showCancelButton:true…}).then(r => r.isConfirmed…)` | `if (await notify.confirm({title, text, confirmText}))` |
| `Swal.fire({html:'<input…>', preConfirm})` (formulario) | `x-ui.modal-base` + `x-ui.field` en el Blade; validación en `logica.ts` |
| `Swal.showLoading()` / overlays propios | `notify.loading()` / `notify.close()` o `window.loader` |
| `escapeHtml`, `debounce`, `formatNumber` locales | `utils/format.ts` |
| `el.innerHTML = \`…${dato}…\`` | `textContent`, `replaceChildren(...)`, o `<template>` del Blade clonado; si no hay más remedio, plantilla con `escapeHtml` en **cada** dato |

Mensajes de error al usuario: `err instanceof HttpError ? (err.data?.message ?? genérico) : genérico`. El backend ya no manda `getMessage()` (§6), así que el mensaje del servidor es seguro de mostrar.

## 5. Puentes y pares

- **Puente `window.fn`** solo si **otro archivo** lo llama (otro bundle, Livewire, `public/js`). Se marca `// PUENTE <fase>: <quién lo llama>` y se lista en el SUMMARY para ADOP. Si solo lo llamaba un `onclick` de la misma vista, no es puente: es `data-accion`.
- **Pares casi idénticos** (Urdido/Engomado, Tejido/…): una vista `comun/x.blade.php` + un módulo `comun/x/…ts` parametrizados por `variante`; las vistas originales quedan como `@include('…comun.x', ['variante' => '…'])` para que los controllers no cambien. Diferencias de columnas/etiquetas en un mapa PHP/TS por variante, nunca `@if` repartidos.

## 6. Backend del módulo (en la misma sesión)

- **SEC-07:** ningún `$e->getMessage()` hacia el usuario. JSON → `use HandlesApiErrors;` + `apiErrorResponse($e, 'Log…', 'Mensaje para el usuario')` (incluye `trace_id`); errores de negocio → `apiClientErrorResponse($msg, 422)`. Redirects → `report($e)` + `->with('error', 'Mensaje genérico (ref: '.$this->traceIdDeError($e).')')`. Mensajes que el código escribe (p. ej. `RuntimeException` propia) pueden seguir.
- **PERF-08..11:** cada cambio con número **antes/después** en un test: `DB::enableQueryLog()` + `count(DB::getQueryLog())` sobre sqlite. Índices: solo `.sql` revisado para 2008 R2 en `database/sql/`, sin ejecutar.
- **N+1 típicos:** `create()` en loop → `Model::insert($filas)` en bloques de `floor(2100 / columnas)` filas (límite de parámetros de SQL Server); `where(...)->first()` en loop → un `whereIn` y `keyBy`.
- **SQL 2008 R2:** sin `OFFSET/FETCH`, `STRING_AGG`, `TRY_CONVERT`, `IIF`, `CONCAT`, `FORMAT`, `THROW`, `PERCENTILE_CONT`. Hay un test que vigila `database/sql`.
- **AuthZ:** sigue en modo **auditar** (`module.permission:<accion>,<idrol>,auditar`) hasta SEC-06. Los huecos del módulo en `20-03-MAPA-AUTHZ.md` se cierran con validación de entrada y ruta en auditar, no con enforce.

## 7. Checklist por pantalla (mínimo hasta que llegue UX-18)

- [ ] 0 `<script>` inline en el HTML renderizado (salvo lo que el layout agrega) y 0 `onclick=` → test guardián por vista.
- [ ] 0 errores de consola al abrir y en la acción principal (skill `run`).
- [ ] Captura antes/después a **768×1024** (tablet) y 1280×800; mismo diseño salvo el bug que se corrige.
- [ ] Botones de ícono con `aria-label`; texto ≥ 12 px (`text-caption`); objetivos táctiles ≥ 44 px en controles nuevos.
- [ ] Acciones solo por clic derecho → también accesibles en tablet (helper UX-06 de 17-02 cuando exista).
- [ ] Ratchet baja; `--update` solo para fijar la baja.

## 8. Arnés para ver pantallas sin SQL Server

`.planning/phases/19-modulos/19-01-arnes/` (de 19-01, reutilizable): crea un sqlite en archivo con **una tabla por modelo** (`$fillable` + `$casts`, espejo `dbo.`), un usuario con todos los permisos, datos semilla del módulo, sirve la app con `php -S` y toma capturas con Playwright + Chromium de `/opt/pw-browsers`. Ver su `README.md`. Para el "antes" de una pantalla ya migrada: `git worktree add ../antes <base>` + `npm run build` dentro y apunta el arnés a ese árbol.

## 9. Commits y entregables

- Un commit por pantalla (o par deduplicado): `<mod>: <pantalla> a TS (…)`. Backend del módulo en commits aparte (`<mod>: SEC-07 …`, `<mod>: N+1 …`).
- `<NN-XX>-SUMMARY.md`: pantallas migradas (líneas inline antes → después), puentes `window`, bugs encontrados, números PERF, ratchet antes → después, capturas, HANDOFF.
