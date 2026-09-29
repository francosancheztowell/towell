# 19-01 — Urdido + Engomado: JS inline → TS · SUMMARY

**Rama:** `claude/19-01-urdido-engomado` (base `claude/friendly-hopper-506bg9` @ `ef60a878`) · **Fecha:** 2026-09-26 · **PR:** no abierto (lo pide el owner).
**IDs:** MIG-URD-01..04, MIG-ENG-01..04, PERF-08..11 (del módulo), SEC-07 (del módulo), UX-18 (checklist provisional §7 de la receta). SEC-06 **no**: AuthZ sigue en modo auditar.
**Receta común:** [`19-00-RECETA.md`](19-00-RECETA.md) · **Plan:** [`19-01-PLAN.md`](19-01-PLAN.md) · **HANDOFF:** [`HANDOFF.md`](HANDOFF.md) · **Arnés:** [`19-01-arnes/`](19-01-arnes/README.md) · **Capturas:** [`19-01-evidencia/`](19-01-evidencia/)

## Resultado

Las 41 vistas de `modulos/urdido`, `modulos/engomado` y `catalogosurdido` (salvo `programar-*`, que es de 19-05) quedan **sin `<script>` inline, sin `on*=` y sin `csrf_token()` en el fuente**. Un test guardián (`VistasSinJsInlineTest`, 41 casos) lo vigila. El JS vive en `resources/js/modulos/{urdido,engomado}/**` (49 archivos TS, tipado estricto, sin `@ts-nocheck`).

| | Antes | Después |
|---|---|---|
| Líneas de JS inline en el módulo | ~11 100 (25 vistas) | 0 |
| Vistas Blade (líneas) | — | −12 036 netas (1 697 + / 13 733 −) |
| TS nuevo | — | 7 660 líneas (con `logica.ts` puros probados en node) |

**Ratchet (fijado con `--update`, solo bajas):**

| Métrica | Antes | Después |
|---|---|---|
| `<script>` inline en blade | 161 | **118** |
| `fetch(` | 297 | **247** |
| `Swal.fire` | 800 | **659** |
| `onclick=` | 372 | **266** |
| `innerHTML =` | 400 | **351** |
| `X-CSRF-TOKEN` | 190 | **151** |
| `getMessage()` en `response()->json` | 270 | **229** |

## Pantallas (commit → qué)

Los commits van por pantalla (o par deduplicado). El diff total pasa de 1 500 líneas, pero casi todo es **movimiento** Blade → TS. La división -p1/-p2 queda por rangos de commits:

- **p1 Engomado (y pares compartidos):** `25e4a6d3` … `aa8c2287`, salvo `7e17e36c`.
- **p2 Urdido:** `7e17e36c` (producción) y las partes Urdido de los commits compartidos.

| Commit | Pantalla(s) | Notas |
|---|---|---|
| `25e4a6d3` | Calificar julios (Urd + Eng) | 1 vista `urdido/comun/calificar-julios` + 1 bundle; **puente** `window.abrirModalCalificarJuliosEng` para `urd-eng/edicion-ordenes.ts` (HANDOFF U1) |
| `c6fa1de6` | 6 reportes de rango, resumen Urd/Eng, panel de control, popup reimpresión | Swal con fechas → `x-ui.modal-base`; Chart.js dentro del bundle |
| `7133876b` | Catálogos julios (Urd/Eng), máquinas, ubicaciones, núcleos | `CatalogBase` no encaja (pide `GET show`); helpers en `urdido/comun/catalogo.ts` |
| `bb3c58ee` | BPM, BPM-Line y actividades BPM (3 pares → 3 vistas + 3 bundles) | variante por mapa PHP; flashes por `x-ui.flash` |
| `7e17e36c` | Producción Urdido (`_scripts`, 2 264 líneas) | `ProduccionUrdidoScriptsBladeTest` pasa al contrato `data-*` sin perder intención |
| `fdc9f3d6` | Captura de fórmula (2 834 líneas) | UX-06: pulsación larga = clic derecho en filtros de columna |
| `aa8c2287` | Producción Engomado (2 563 líneas) | filas del modal de oficiales desde `<template>` |
| `6a020a70`, `01580d4f`, `8bf69ca9` | SEC-07 en `ProduccionTrait`, guardián, ratchet, correcciones del code-review | — |

**Puentes `window` que quedan (para ADOP):** solo `window.abrirModalCalificarJuliosEng` (`// PUENTE 19-01`), porque lo llama `resources/js/urd-eng/edicion-ordenes.ts` (19-05).

## Bugs encontrados y corregidos (además de mover el código)

- **Producción (Urd y Eng):**
  - La propagación de oficiales no mandaba `metros` y el trait los exige: daba 422 y las filas de abajo quedaban **sin oficial**.
  - El modal no preseleccionaba el turno guardado.
  - En Urdido, los selects de julio podían volver a un julio viejo.
  - En Engomado:
    - La canoa no se revertía al fallar.
    - Borrar un oficial no refrescaba la fila.
    - La clave de empleado de la formulación siempre quedaba vacía.
    - Sólidos y Kg. Bruto se guardaban dos veces.
    - El catálogo de julios se pedía en cada cambio.
  - *Code-review:* el anti doble envío de Kg. Bruto/Sólidos bloqueaba el reintento tras un fallo. Se corrigió y se probó abortando el primer POST en el arnés.
- **Captura de fórmula:**
  - **XSS**: `ItemName` iba sin escapar en `innerHTML`.
  - "Nueva" heredaba el operador de la formulación anterior.
  - El tooltip de calidad salía doble escapado.
  - El folio no se codificaba en las URLs.
  - Se quitó código muerto (`#editModal` y `#componentesModal` nunca se mostraban).
- **BPM:**
  - Editar de Engomado dejaba la fecha vacía y **cruzaba Entrega/Recibe**.
  - En actividades de Urdido, cada botón disparaba dos veces (doble confirmación).
  - `data-actividad` salía doble escapado.
- **Reportes:** la fecha por defecto usaba el día UTC; después de las 18:00 en CDMX precargaba mañana.
- **Catálogos:**
  - `update` de un registro inexistente daba 500 (ahora 404).
  - El error del index nunca se mostraba.
  - En Ubicaciones, "Editar" aparecía habilitado sin selección.
- **Calificar julios:** sin folio daba 500 (ahora 422).
- **Blade `@json([...literal...])`:** la directiva parte su argumento en las comas y pierde el escape. Ahora siempre se pasa una variable (receta §2), y hay tests con `'` y `<` en los datos.

## Backend del módulo

**SEC-07:** 0 `getMessage()` hacia el usuario en controllers/trait del módulo. JSON usa `HandlesApiErrors` (`message` + `trace_id`). Los redirects hacen `report()` y dan un mensaje genérico con referencia.
- Sitios corregidos:
  - CalificarJulios: 4
  - Catálogos: 13
  - BPM: 6
  - Fórmula: 12 (algunos también filtraban el stack trace)
  - Producción Eng: 5 (uno devolvía SQL de una `QueryException`)
  - Producción Urd: 3
  - `ProduccionTrait`: 9
- Quedan a propósito los `RuntimeException` propios de BPM-Line (mensajes escritos por el código).
- **Contrato:** los 500 ahora traen `message` en vez de `error`. En catálogos, el 422 trae `message` como texto y los errores en `errors`. Todos los consumidores son las vistas migradas, que leen ambos (`mensajeError`).

**PERF-08..11:** todas las cifras salen de tests con `contarQueries()` en sqlite, comparando el mismo escenario contra el código anterior.

| Dónde | Antes → después |
|---|---|
| BPM-Line `index` primera visita (12 actividades) | Urd 19 → 8, Eng 20 → 8 queries |
| BPM-Line visitas siguientes | 9/8 → 7 |
| Fórmula `store`/`update` (5 componentes) | 10 → 6 |
| Fórmula con 250 componentes | 250 → 2 INSERT |
| Producción Eng: crear 20 renglones | 22 → 3 |
| Producción Urd: sobrantes por Hilos (3 grupos) | 8 → 6 |
| Producción Urd: realinear Hilos (5 filas) | 9 → 6 |

- INSERT en bloques de ≤ 2 099 parámetros (límite de SQL Server).
- `LTRIM(RTRIM(Folio)) = ''` → `Folio IS NULL OR Folio = ''`: es equivalente en SQL Server por el padding ANSI y ahora es sargable. sqlite no reproduce el caso de solo espacios y el test no finge que sí.
- Sin índices nuevos.
- `LIKE '%q%'` en catálogos chicos se queda (no hay número que lo justifique).

**AuthZ (20-03, modo auditar):**
- `actualizar-campo-orden` ya valida `campo` contra una lista blanca (`merma_con_goma`, `merma_sin_goma`) y `valor` como `nullable|numeric|min:0` (422).
- La ruta sigue en `module.permission:modificar,43,auditar`, sin enforce, y hay un test que lo verifica.

## Evidencia

- **PHP:**
  - `php artisan test` (sqlite): **1 774 passed**, 0 fallos.
  - `tests/Feature/UrdEng/**` nuevos: calificar-julios, BPM ×3, catálogos ×2, reportes, fórmula, producción ×2, trait SEC-07 y guardián.
- **JS:** `npm run typecheck`: 0 errores. `npm run test:js`: **219 pass** (nuevos: `tests/Js/urdeng-*.test.mjs`). `npm run build`: ok.
- **Análisis estático:** `vendor/bin/phpstan analyse --memory-limit=2G`: **0 errores** (sin tocar el baseline). `vendor/bin/pint --test` en los PHP tocados: pass.
- **Ratchet:** `npm run ratchet`: ok (tabla de arriba).
- **Code-review** (skill, `high`): 1 hallazgo real (reintento de Kg. Bruto), corregido en `8bf69ca9`.
- **Navegador** (skill `run` con el arnés `19-01-arnes`):
  - 29 pantallas a 768×1024, antes y después, **todas 200 y 0 errores de consola**.
  - Cada unidad ejercitó además sus acciones principales:
    - crear, editar y borrar en catálogos y actividades;
    - filtros y checklist de BPM;
    - modales de rango y charts en reportes;
    - en producción: julio → neto, oficiales y propagación, "Listo" y finalizar hasta `Status=Finalizado`;
    - en fórmula: crear, editar, ver, componentes, filtros, calidad y eliminar;
    - calificar julios en sus dos variantes.
  - Comparativas lado a lado en `19-01-evidencia/`: tablas y producción idénticas.
  - Cambios visuales intencionales:
    - Los formularios que eran SweetAlert (rango de reportes, filtros y formularios de catálogos, filtro de columna y calidad de fórmula) ahora son `x-ui.modal-base`.
    - En BPM se unificaron detalles entre variantes: la X de cerrar en Crear de Urdido, el overlay `/50` y el texto blanco de "Terminado" en Engomado.

## Decisiones

- **Datos en `data-*`, no islas JSON:** el ratchet cuenta toda `<script type="application/json">`. Corregido en la receta §0.
- **Guardianes sobre el fuente Blade:** `x-ui.modal-base` emite un `onclick` en el HTML (HANDOFF U4).
- **`ProduccionTrait` (en `app/Traits`):** se tocó solo para SEC-07. Lo usan **únicamente** los dos controllers de producción del módulo, así que es backend del módulo. Ahora usa `HandlesApiErrors` directamente.
- **Producción Urdido y Engomado no comparten `logica.ts`** (desviación del plan):
  - Los scripts ya divergían (~62 % de líneas distintas).
  - Tras reescribirlos, las APIs puras solo comparten 3 helpers triviales.
  - Unificar ahora sería reescribir código recién probado. Queda para cuando converjan las reglas (ADOP).
- **Código muerto quitado:**
  - En fórmula, los modales nunca visibles.
  - En BPM, `fillAutoriza`, `#selectAll` y el `#deleteModal` sin uso.
  - En producción Eng, el modal de formulación se conserva, pero nada lo abre: `abrirModalFormulacion` no tiene llamadores. Queda como `data-accion` inactiva.

## Pendientes y decisiones para el owner

1. **Fórmula (bug previo, sin cambiar):** al editar y guardar, `fecha` y `Hora` se sobrescriben con el momento actual, porque el formulario siempre manda los inputs de hoy. ¿Se conservan los originales al editar?
2. **`ProduccionTrait::guardarOficial` exige `metros > 0`:** eso rompía la propagación. Ya se compensa del lado del cliente; ¿debe relajarse la regla?
3. **`index()` de producción rellena el Oficial 1 con el usuario actual en filas sin hora al recargar.** Deshace la propagación al recargar; es comportamiento del servidor y no se cambió.
4. **Checklist UX-18 de 17-02:** aún no existe. Enlazarla al llegar (HANDOFF U2).
5. **Arnés:** guardar un BPM nuevo falla porque `FolioHelper` consulta `INFORMATION_SCHEMA`, que sqlite no tiene. Lo cubre el feature test.

## Cómo desplegar

Sin migraciones, `.sql` ni variables `.env` nuevas.
- `npm run build` (bundles nuevos por glob en `resources/js/modulos/**/index.ts`).
- `php artisan optimize:clear` (o `view:clear`), porque cambian muchas vistas.
