# 19-08 — Módulo Mantenimiento · SUMMARY

**Rama:** `claude/19-08-mantenimiento` (base `claude/friendly-hopper-506bg9`, mergeada de nuevo el 2026-09-30 con CAL, 19-02 y PT 03) · **Fecha:** 2026-09-30 · **PR:** no abierto (lo pide el owner).
**IDs:** MIG-MAN-01..04, PERF-08..11, SEC-07 y UX-18 del módulo. SEC-06 **no**: AuthZ sigue en auditar.
**Plan:** [`19-08-PLAN.md`](19-08-PLAN.md) (aprobado con sus 3 propuestas por el trigger "Destrabar 19-08", 2026-09-30) · **HANDOFF:** [`HANDOFF.md`](HANDOFF.md) § De 19-08 · **Arnés:** [`19-08-arnes/`](19-08-arnes/) · **Capturas:** [`19-08-evidencia/`](19-08-evidencia/)

## Resultado

Las 9 vistas de `modulos/mantenimiento/**` y `livewire/mantenimiento/**` quedan **sin `<script>` inline, sin `on*=`, sin `csrf_token()`, sin texto < 12 px y sin `<h1>` propio** en el fuente. Lo vigila `tests/Feature/Mantenimiento/VistasSinJsInlineTest.php` (9 casos).

| Pantalla | JS inline antes | Después | Cómo |
|---|---|---|---|
| Nuevo paro (piso, tablet) | ~665 líneas | 0 | `resources/js/modulos/mantenimiento/nuevo-paro/{index,logica}.ts` |
| Finalizar paro | ~310 | 0 | `finalizar-paro/{index,logica}.ts` |
| Solicitudes (reporte-fallos-paros) | ~470 | 0 | `solicitudes/{index,logica}.ts`, filas desde `<template>` |
| Reportes (índice) | ~65 | 0 | `reportes/index.ts` (compartido) |
| Reporte Fallas y Paros | ~50 | 0 | `reportes/index.ts` |
| Operadores de mantenimiento | ~540 (4 `<script>`) | 0 | **Livewire** `CatalogoOperadores` (`ConTabla` + `x-tabla`) |
| Catálogo de fallas | 0 (ya Livewire) | 0 | solo a11y |

Diff (sin la evidencia): 35 archivos, +2 765 / −2 597.

**Ratchet (fijado con `--update`, solo bajas):**

| Métrica | Antes | Después |
|---|---|---|
| `<script>` inline en blade | 99 | **90** |
| `fetch(` | 202 | **188** |
| `Swal.fire` | 485 | **463** |
| `onclick=` | 239 | **230** |
| `innerHTML =` | 329 | **290** |
| `X-CSRF-TOKEN` | 109 | **104** |
| `getMessage()` en `response()->json` | 192 | **179** |
| duplicación % | 6.93 | **6.92** |

## Commits

| Commit | Qué |
|---|---|
| `3317940` | 14 tests de caracterización del API de paros **antes** de moverlo (el controller tenía 17.8 % de cobertura) · SEC-07 (10 catch → `apiErrorResponse`) · BUG-025 |
| `a933382` | PERF (área del usuario autenticado) · `maquinas()` 401 → 422 |
| `8087766` | Nuevo paro, finalizar paro y Solicitudes a TS |
| `8ee7639` | Reportes a TS (`<h1>` → `<h2>`, HANDOFF 17-02 C2) |
| `a4e7f41` | Catálogo de operadores a Livewire; fuera las rutas POST/PUT/DELETE |
| `5b68fd2` | Guardián de JS inline, ratchet, arnés |
| `6e713b7` | Carrera de la cascada de nuevo paro (hallazgo de code-review) + `@vite` duplicado |

## Decisiones

- **BUG-025:** `departamentos()` ya no distingue al usuario 6; todos reciben todos los departamentos (nada se cierra). El área propia sigue preseleccionada. Test `test_departamentos_no_distingue_al_usuario_6`.
- **Operadores por idrol 53** (el mismo que ya exigían sus rutas `module.permission:*,53`) en `mount()` y en cada acción. Antes el GET revisaba por nombre (`'Mantenimiento'`): si ese nombre resolviera a otro idrol, cambia quién entra. Ver HANDOFF M3.
- **Un solo camino de escritura:** sin rutas `mantenimiento.operadores-mantenimiento.{store,update,destroy}` ni métodos en el controller (test `test_ya_no_hay_rutas_de_escritura_de_operadores`). Sin llamadores en `resources/`, `public/js`, `app/` ni `tests/`.
- **Paros sin gate** (excepción del owner): los guardianes `EscrituraFueraDePlaneacionTest` / `RutasDestructivasPermisoTest` no cambian.
- **AuthZ:** 20-03-MAPA-AUTHZ no tiene huecos del módulo aparte de esa excepción. Nada nuevo en enforce.
- **Nuevo paro no es Livewire** (la guía dice "no migrar ahora" para paros): TS con la receta.
- **Avisos que se cierran solos:** el éxito de alta (6 s) y de cierre (2 s) se cerraba solo con `Swal({timer})`; se conserva con `Promise.race(notify.alert, 6 s)`, porque en piso nadie toca "OK".
- **Telegram / `ParoTelegramNotifier`:** sin tocar.

## Bugs encontrados y corregidos

| # | Dónde | Bug | Arreglo |
|---|---|---|---|
| 1 | Solicitudes | La fecha se pintaba con `new Date('…T00:00:00Z').toLocaleDateString()`: en un navegador de México (UTC-6) salía **el día anterior** | `fechaCorta()` toma el prefijo `aaaa-mm-dd` (test node) |
| 2 | Finalizar paro | A 768 px (tablet) la **5.ª estrella quedaba fuera de la pantalla**: en piso no se podía calificar con 5 | `gap-8 md:gap-12` → `gap-4 md:gap-2 lg:gap-12` (escritorio igual). El flujo del arnés comprueba `estrella5Visible: true` |
| 3 | Nuevo paro | Respuestas rezagadas de la cascada: con red lenta, depto A → B podía dejar **las máquinas de A con B elegido**, y `store()` no revisa que la máquina sea del depto (heredado del script inline; lo encontró code-review) | Contador por combo + invalidar al cambiar depto/máquina. `19-08-arnes/carrera.mjs` lo reproduce con una respuesta lenta |
| 4 | Nuevo paro | Usuario de Tejido sin número de empleado: `maquinas()` respondía 401, que con `window.http` muestra "sesión expirada" y recarga | 422 con mensaje (test) |
| 5 | Finalizar paro | "Atendió" se prellenaba antes de cargar los operadores y se perdía | Se reaplica al terminar de cargar |
| 6 | Reportes | La fecha propuesta salía de `toISOString()` (UTC): de noche proponía mañana | `relojLocal()` |

## PERF-08..11 (sqlite, `DB::getQueryLog()`)

| Endpoint | Antes | Después |
|---|---|---|
| `GET mantenimiento/nuevo-paro` | 1 consulta (`SYSUsuario` otra vez) | **0** |
| `GET api/mantenimiento/paros` (usuario sin área) | 3 (`SYSUsuario` ×2 + paros) | **1** |
| `GET api/mantenimiento/paros` (área Engomado) | 1 | 1 |
| `GET api/mantenimiento/paros` (Tejedores) | 2 (telares + paros) | 2 |

El usuario autenticado (`Usuario`) ya es la fila de `dbo.SYSUsuario`: volver a leer `area` sobraba. Lo fija `test_el_area_no_vuelve_a_consultar_sysusuario`. **Sin N+1** en el módulo. El predicado `UPPER(LTRIM(RTRIM(Depto))) <> 'TEJEDORES'` (alcance=todos de Tejedores) no es sargable, pero un `<>` dentro de un `OR` tampoco usaría un índice, así que no se cambió (no hay número que ganar). Sin índices nuevos.

## Checklist UX-18 (17-02-CHECKLIST)

| Pantalla | 1.1 title | 1.2 un h1 | 2.1 sin clic derecho | 2.5 ≥12 px | 3.1 aria-label | 4.2 toasts | 4.3 419 | Notas |
|---|---|---|---|---|---|---|---|---|
| Nuevo paro | ✅ | ✅ | n/a | ✅ | ✅ | ✅ notify | ✅ http | "Maquina" → "Máquina"; Cancelar es `<a>` |
| Finalizar paro | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | 5.ª estrella visible en tablet; "Atendio" → "Atendió" |
| Solicitudes | ✅ | ✅ | n/a | ✅ | ✅ (× con label) | ✅ | ✅ | labels `for` en filtros; "Area/Maquina" con acento |
| Reportes índice | ✅ | ✅ (antes 2) | n/a | ✅ | ✅ | ✅ | n/a | C2 |
| Fallas y Paros | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | n/a | modal con `role="dialog"`, Esc cierra |
| Operadores | ✅ | ✅ | n/a | ✅ | ✅ | ✅ (Livewire `aviso`) | ✅ Livewire | controles ≥ 44 px en el modal |
| Catálogo de fallas | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | `aria-label` en Limpiar |

C1 (clic derecho) y C7 (andón) no aplican al módulo (grep). C4/C5 quedan cubiertos por `notify`/`http`.

## Evidencia

- Arnés `19-08-arnes/` (reusa el de 19-01 + `INFORMATION_SCHEMA` para los folios): `reporte-antes.json` / `reporte-despues.json` → 7 pantallas × 2 tamaños, **status 200, 1 `<h1>` y 0 errores de consola** (antes: `reportes-index` con 2 `<h1>`).
- `antes-despues-*.png` (768×1024) y `flujo-tablet.png`: alta con folio PF00042 → Solicitudes → Terminar → finalizar (turno autollenado, 5 estrellas) → ya no está entre los activos (`flujo.json`, 0 errores).
- `php artisan test`: 2 317 passed antes del code-review; se repite al final (abajo). Módulo: `tests/Feature/Mantenimiento/*` 32 tests, `tests/Js/mantenimiento.test.mjs` 12.
- `vendor/bin/phpstan analyse`: sin errores. PHPMD (`scripts/calidad.mjs phpmd`): sin violaciones nuevas. Pint: pass en todos los PHP cambiados. `npm run typecheck`, `test:js`, `build`, `ratchet`: ok.

## Pendientes / fuera de alcance

- `app.css` no escanea `*.ts` con `@source`: las clases que solo aparecen en TS no se generan (HANDOFF M1). En este módulo las clases alternadas viven en el Blade.
- Navbar a 768 px: el título largo ("Reporte de Fallos y Paros", "Operadores de Mantenimiento") tapa botones (ya pasaba; = U5/T6).
- `CalificacionParoService` (Mecánicos, 19-07) no se tocó.

## Cómo desplegar

Sin migraciones, sin `.sql`, sin variables `.env`. `npm run build` y `php artisan optimize:clear` (cambian rutas: desaparecen 3 de operadores).
