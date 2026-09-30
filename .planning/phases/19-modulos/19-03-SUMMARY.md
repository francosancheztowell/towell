# 19-03 — Atadores: resumen

**Rama:** `claude/19-03-atadores` (base `claude/friendly-hopper-506bg9`, mergeada al día el 2026-09-30) · **Plan:** [19-03-PLAN.md](19-03-PLAN.md) · **Receta:** [19-00-RECETA.md](19-00-RECETA.md) · **Aprobación:** routine "Destrabar 19-03 Atadores" (owner vía la sesión integradora), con las dos preguntas del plan sin dato (ver §Decisiones).

**IDs:** MIG-ATA-01..04, PERF-08, SEC-07, UX-18 del módulo, hueco AuthZ de 20-03 (en modo auditar) y HANDOFF 16 C3. Anexo CAL 22-02 (código muerto de `OeeAtadoresFileService`). **SEC-06 no** (AuthZ sigue en auditar).

## Qué se hizo

| # | Entrega | Commit |
|---|---|---|
| 1 | N+1 de la siembra del checklist → `App\Services\Atadores\ChecklistAtado` (inserción por bloques < 2 100 parámetros) | `cefee422` |
| 2 | Catálogos sin `getMessage()` (`RespondeCatalogo`), validación 422 y 404 reales; Comentarios con llave por `Id` (rutas `/comentarios/id/{id}`, `.sql`, detección cacheada) | `a4c21e77` |
| 3 | SEC-07 en autorizar, Folio Paro, devoluciones, iniciar y reportes; `action=supervisor` audita `registrar,45`; `estatus` proyectado; `userCan` por idrol | `aba09eee` |
| 4 | Anexo CAL: `OeeAtadoresFileService` sin código muerto | `391230ad` |
| 5 | Arnés de pantallas 19-03 | `9fe782f0` |
| 6 | `calificar-atadores` + `proceso-km` a TS | `c6eb6123` |
| 7 | Tablero Programa Atadores a TS; refresco solo con pestaña visible | `64e8efb7` |
| 8 | Reportes (Programa, KM, OEE) a TS; test guardián de las 9 vistas | `2c42dfda` |
| 9 | Pint en tocados, ratchet fijado, evidencia | `e8308a17` |
| 10 | Correcciones de la revisión (`code-review`) | `02fb46a9` |

### MIG-ATA-01/02 — JS inline → TS

| Vista | JS inline antes | Después | Módulo |
|---|---:|---:|---|
| `calificar-atadores/index` | 1 620 | 0 | `resources/js/modulos/atadores/calificar/{index,devolucion,logica,tipos}.ts` |
| `programaAtadores/index` | 647 | 0 | `atadores/programa/{index,logica}.ts` |
| `reportes/atadores` (OEE) | 149 | 0 | `atadores/reportes/oee/{index,logica}.ts` |
| `calificar-atadores/_proceso-km` | 148 | 0 | `atadores/comun/proceso-km{,-logica}.ts` (un solo guardado para las dos pantallas) |
| `reportes/km` | 83 | 0 | `atadores/reportes/km/{index,grafica}.ts` (Chart.js en su bundle; ya no carga `charts.js`) |
| `reportes/programa` | 59 | 0 | `atadores/reportes/programa/index.ts` |
| `calificar-atadores/proceso-km` | 45 | 0 | `atadores/proceso-km/index.ts` |
| **Total** | **2 751** | **0** | 1 862 líneas TS (incluye `comun/` y `catalogos-atadores/guardado.ts`) |

- Config de la página por `data-pagina='@json($config)'` (receta §2), eventos por `data-accion` / `data-*` + `delegate()`, `fetch`+CSRF → `http`, Swal → `notify` (alertas, confirmaciones, toasts, loading).
- Formularios en SweetAlert2 → `x-ui.modal-base` + `x-ui.field` (Terminar, Califica Tejedor, Autoriza Supervisor, filtro de columna). Los modales de fechas de los 3 reportes usan el de 19-02 (`tejido/reportes/partials/rango-fechas` + `comun/rango.ts`), que ya es genérico.
- Código muerto quitado del JS: `abrir/cerrarProcesoKm` (no hay modal), `agregarNota` (sin llamador), `actualizarDisponibilidadDevolucion` (su panel está comentado en la vista: "Validación temporalmente pausada"; se conserva quitar los `max`). El endpoint `devoluciones/disponibilidad` sigue.
- **Puentes `window`:** `window.volverAlIndice` en los 3 reportes (`// PUENTE 19-03`: lo llama `app-core.js` en `#btn-back`). Ninguno más.
- **MIG-ATA-03 (duplicados):** Montado/Enhebrado tenían el guardado copiado en `calificar` y `proceso-km` → un módulo. Los tres modales de fechas → el parcial común.
- **MIG-ATA-04 (Livewire):** no, como dice `livewire-cuando-si-cuando-no.md` ("Atadores: no migrar ahora"); el tablero queda con JSON + `http`.
- Tests node: `tests/Js/atadores-{calificar,proceso-km,programa,reportes,catalogos-guardado}.test.mjs` (26 casos de lógica pura). Guardián PHP: `tests/Feature/Atadores/VistasAtadoresSinJsInlineTest.php`.

### Polling del tablero (números)

El "HTML completo cada 5 s aunque la pestaña esté oculta" era `d6bffcb` (22-09); `9cf2e50` (24-09) ya lo había pasado a JSON cada 15 s con `document.hidden`, sin medir. Medido en el arnés (9 filas, 60 s, reloj de Playwright, `.planning/phases/19-modulos/19-03-arnes`):

| Versión | Visible 60 s | Oculta 60 s | Al volver |
|---|---|---|---|
| `d6bffcb` (antes del prompt) | 13 peticiones · 1 345 110 B (HTML ~103 KB c/u, ~60 ms) | **14 · 1 448 580 B** | 0 |
| base `9cf2e50` | 4 · 1 072 B (~52 ms) | 0 | 0 (espera hasta 15 s) |
| **19-03** | 4 · 1 072 B (~47 ms) | **0** | **1 · 268 B** (refresca al volver) |

Con 150 filas (`ProgramaAtadoresEstatusTest`): la página pesa 627 368 B y el JSON 4 693 B; el antes de `d6bffcb` equivale a ~7,5 MB/min por pestaña abierta, visible u oculta. `GET programaatadores/estatus` ahora selecciona solo `id` + estatus (antes la consulta de 23 columnas del tablero) y sin `ORDER BY`: 4,1–5,4 ms → 2,1–2,3 ms en sqlite con 150 filas. Contrato por rol (supervisor, atador, tejedor, otro) × filtro (ninguno, todos, autorizados) en el test.

### PERF-08 — N+1 (consultas por request, sqlite, en caliente)

| Acción | Catálogo 2 máq + 3 act | 10 máq + 12 act |
|---|---|---|
| Iniciar atado | 17 → **11** | 51 → **11** |
| Autorizar (supervisor) | 15 → **12** | 32 → **12** |
| Abrir calificar con 11 actividades faltantes | — | 21 → **12** |

`AtadoresChecklistConsultasTest` falla si el número vuelve a crecer con el catálogo. La llave (`ChecklistAtado::llave`, minúsculas y sin espacios finales, como compara SQL Server) es la misma en la siembra, el controller y la vista.

### SEC-07

- 0 `getMessage()` hacia el usuario en `app/Http/Controllers/Atadores/**` (ratchet `getMessage() en response()->json` 192 → 177).
- Catálogos: la `ValidationException` ya no se atrapa como 500 (ahora 422 con errores), un registro que no existe es 404, un fallo es 500 con mensaje fijo y `trace_id` (`RespondeCatalogo` + `HandlesApiErrors`). `Rule::unique()->ignore()` reemplaza `'unique:…,'.$llave` (una coma en la llave rompía la regla).
- Autorizar supervisor: antes **200** con `ok:false` y el SQL en el mensaje; ahora 500 con `trace_id` (el TS muestra el mensaje del servidor).
- Iniciar atado (redirect): `report()` + "ref: <trace_id>" en el flash y en el log.
- Reportes OEE: sin `getMessage()` ni la ruta física del Excel; las reglas que el usuario corrige (`OeeAtadoresReglaException`: rango que cruza años ISO, archivo de otro año, falta la hoja DETALLE) vuelven como 422 con su mensaje.
- Catch vacío de `FechaRequerimiento` al autorizar → `report($e)` (ratchet `catch vacío` 28 → 27).

### AuthZ (modo auditar)

- `POST atadores/save` con `action=supervisor` pasa por `EnsureModulePermission` `registrar,45,auditar` (se reutiliza el middleware, no se toca): registra `authz_denegaria` y no bloquea. Test: sin `registrar` → 1 fila `registrar · 45 · POST …`; con `registrar` → 0; otras acciones → 0.
- `atadores/reportes-atadores/oee/despachar`: **sigue sin idrol** (el owner no lo tiene a mano) y en `SIN_PERMISO_DE_MODULO`.
- `ProgramaAtadoresListado::restringirAtador` y los botones Iniciar/Filtrar del tablero, por idrol 45 en vez del nombre.

### Comentarios con llave por Id (HANDOFF 16 C3)

- Rutas nuevas `…/comentarios/id` (POST) y `…/comentarios/id/{id}` (GET/PUT/DELETE, `whereNumber`, mismos permisos 151). Las `{nota1}` siguen tal cual.
- `AtaComentariosModel::tieneId()` (cache 1 h): con la columna, la pantalla usa `llave: Id`; sin ella, todo como hoy. Alta y edición devuelven la fila (`data`) y `catalogos-atadores/guardado.ts` la usa para pintar el Id real (sin tocar `catalog-base.ts`).
- `database/sql/atadores_comentarios_id.sql`: idempotente, 2008 R2, `GO` entre lotes, no agrega nada si ya hay otra columna IDENTITY.

### Anexo CAL 22-02 — `OeeAtadoresFileService`

- Los 10 métodos pedidos solo los llamaban a ellos mismos y a otros 47 métodos y 38 constantes privadas. phpstan los fue marcando al quitar los primeros y se borraron hasta que no quedó ninguno. Ninguno se invocaba dinámicamente. **3 097 → 1 293 líneas** (con Pint).
- Cobertura del archivo con sus tests (pcov compilado en la sesión): **543/1 728 = 31,4 % → 543/698 = 77,8 %**. Son las mismas 543 sentencias cubiertas: lo borrado nunca se ejecutaba. No se tocó lógica, así que no hacía falta subir cobertura antes.
- `phpstan-baseline.neon`: −13 entradas (10 `is unused` + 3 de `AtadoresController`) y un `count` de 2 a 1.

## UX-18 (768×1024, arnés)

Auditoría automática por pantalla (title, `<h1>`, texto < 12 px, botones de ícono sin label) + recorridos con Playwright.

| Pantalla | 1.1 title | 1.2 un h1 | 2.1 sin solo clic der. | 2.5 ≥12 px | 3.1 aria-label | 4.2 toasts notify | 4.3 419 vía http | Notas |
|---|---|---|---|---|---|---|---|---|
| Programa | ✅ | ✅ | ✅ long-press en encabezados | ✅ | ✅ (× con label) | ✅ | ✅ | Logo del navbar sin label (layout, HANDOFF) |
| Calificar Jacquard | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | Checkboxes del checklist 16 px (diseño previo, no se cambió) |
| Calificar Karl Mayer | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Montado/Enhebrado | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |
| Reportes (índice, Programa, KM, OEE) | ✅ | ✅ (`<h1>` del contenido → `<h2>`, 17-02 C2) | n/a | ✅ | ✅ | ✅ | ✅ | |
| Catálogo Comentarios | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | |

**Errores de consola:** 0 en las 12 pantallas y en los tres recorridos:
- calificar: checklist, merma con tope de 5 kg, observaciones, Folio Paro, devolución, terminar → calificar → autorizar con redirección; en Karl Mayer, asignarme, empleado repetido y devolución.
- programa: filtros, orden, filtro de columna con clic derecho y con long-press, e iniciar.
- reportes: modal, validación y consulta.

"Exportar a OEE" responde error en el arnés porque no existe el Excel (es el 500 esperado).

**Capturas:** `19-03-evidencia/antes-despues-*.png` (10 pantallas a 768×1024; calificar y programa también a 1280×800) y los modales nuevos. Mismo diseño salvo los modales (SweetAlert2 → `x-ui.modal-base`).

## Evidencia de checks (2026-09-30, sobre la base mergeada)

| Check | Resultado |
|---|---|
| `php artisan test` | 2 312 passed (23 948 assertions) antes de la revisión; módulo después: 73 passed |
| `vendor/bin/phpstan analyse --memory-limit=2G` | OK, sin errores |
| `npm run typecheck` / `npm run test:js` / `npm run build` | OK / 321 pass / OK |
| `npm run ratchet` | ok; fijado con `--update` (solo bajas): fetch 202→181, Swal.fire 485→408, onclick 239→218, innerHTML 329→321, X-CSRF 109→95, `<script>` inline 99→92, getMessage 192→177, catch vacío 28→27, duplicación 6,93→6,90 % |
| `vendor/bin/pint --test` (PHP cambiados) | pass |
| `composer quality` (+ `CAMBIADOS_BASE=origin/claude/friendly-hopper-506bg9` para phpmd de toda la rama) | pass; phpmd "sin violaciones nuevas" en 27 PHP |
| `code-review` (high) | 9 hallazgos: 5 corregidos en `02fb46a9`, los de diseño en archivos ajenos van a HANDOFF (A2, A3) y el de doble lectura en calificar se deja (camino raro, 1 consulta) |

`tests/Feature/Reporte03OeeFechaFinalizaTest` (Urdido) falla **solo corrido aislado**, igual en la base; en la suite completa pasa. HANDOFF A6.

## Decisiones

1. **Columna `Id` de `AtaComentarios`: desconocida** (owner). Se implementó el plan condicional: `.sql` + detección; hasta correrlo, nada cambia.
2. **idrol de "Reportes Atadores": desconocido** → `oee/despachar` sin gate.
3. **Refresco con JSON, no Livewire** (guía de arquitectura); el intervalo se detiene con la pestaña oculta en vez de solo saltarse.
4. Reutilizar el modal de fechas de 19-02 en lugar de copiarlo (duplicación y receta §5); se pide subirlo a componente común (HANDOFF A1).
5. `auditarPermisoSupervisor` llama al middleware en modo auditar desde la acción porque el JS postea todas las acciones a la misma URL. Cuando llegue SEC-06, esta llamada también se pasa a enforce (HANDOFF A4).

## Pendientes

- Correr `database/sql/atadores_comentarios_id.sql` (owner).
- idrol de "Reportes Atadores" para auditar `oee/despachar`.
- Checkboxes del checklist a 44 px: cambia el diseño; lo decide el owner.

## Cómo desplegar

1. `git pull` + `npm run build` (bundles nuevos `resources/js/modulos/atadores/**`; el glob de Vite los toma solo).
2. `php artisan optimize:clear && php artisan optimize` (rutas nuevas de Comentarios).
3. Opcional: correr `database/sql/atadores_comentarios_id.sql` en ProdTowel (SSMS/sqlcmd) y después `php artisan cache:clear` para que Comentarios pase a `Id` antes de 1 h.
4. Sin migraciones ni variables `.env` nuevas.
