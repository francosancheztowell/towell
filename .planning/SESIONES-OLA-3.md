# Sesiones de la Ola 3 — primera tanda

Abierta el 2026-09-25 por decisión del owner, con las Olas 0–2 completas en `main` (`42837fa1`). Base de todas: `claude/friendly-hopper-506bg9` (= `main` en ese momento). Tags `towell-refactor-2026`, `ola-3`. Plantilla en `PROTOCOLO-SESIONES.md` §7.

**Decisiones del owner que aplican a toda la ola (2026-09-25):**
- **Contraseñas: no se tocan** (ni reglas, ni validación, ni login). UX-10 queda fuera.
- **Codificación no es duplicado:** catálogos = `ReqModelosCodificados`, codificación = `CatCodificados`; se quedan las dos (19-06, más adelante).
- **AuthZ sigue en modo auditar:** sin datos de producción no se pasa a enforce (SEC-06 espera ~2 semanas de `/admin/accesos`).
- **Programa Tejido:** 05 (mutaciones) se adelanta a 03 (shell Livewire), porque 03 necesita telemetría/canary de producción; 05 no cambia la UI ni los contratos HTTP.

| Sesión | Rama | Contexto |
|---|---|---|
| 17-02 — UX global | `claude/17-02-ux-global` | `phases/17-ux/17-CONTEXT.md` (UX-01..18 salvo UX-10) |
| 19-01 — Urdido + Engomado | `claude/19-01-urdido-engomado` | `phases/19-modulos/19-CONTEXT.md` (receta + 19-01) |
| 19-03 — Atadores | `claude/19-03-atadores` | `phases/19-modulos/19-CONTEXT.md` (19-03) + `phases/20-arq-sec/20-03-MAPA-AUTHZ.md` |
| PT 05 — Mutaciones | `claude/pt-05-mutaciones` | `phases/05-mutations/05-mutations-PLAN.md` + PT-DUP-01..04 + PT-PERF-02 |

**Orden de merge:** 17-02 → 19-01 → 19-03 → PT 05 (o en el orden en que terminen; no comparten archivos).

## Propiedad de archivos en la Ola 3 (primera tanda)

Esta tabla **manda sobre** `PROTOCOLO-SESIONES.md` §5 durante la Ola 3.

| Sesión | Dueño de | Prohibido |
|---|---|---|
| 17-02 | `resources/views/layouts/**`, `components/layout-head.blade.php`, `components/layout-scripts.blade.php`, `components/layout-styles.blade.php`, `components/layout/global-loader.blade.php`, `components/navbar/navbar.blade.php`, `resources/views/errors/**`, `resources/views/produccionProceso.blade.php` (home: flash/estado vacío), `lang/**`, `config/app.php` (locale), `resources/js/utils/**`, `resources/js/componentes/**`, `resources/js/types/**`, `resources/css/app.css`, `resources/css/fontawesome-display.css`, textos/acentos de `resources/views/login.blade.php` y `components/auth/**` (sin tocar reglas de contraseña), `tests/Js/utils-*`, tests nuevos `tests/Feature/Ux/**` | Vistas y JS de módulos (HANDOFF), `AuthController`, validación de contraseñas, PT, `package.json`/`vite.config.js` salvo HANDOFF |
| 19-01 | `routes/modules/urdido.php`, `routes/modules/engomado.php`, `app/Http/Controllers/{Urdido,Engomado,UrdEngomado}/**` salvo `Urdido/ProgramaUrdido/**` y `Engomado/ProgramaEngomado/**`, `app/Services/{Urdido,Engomado}/**`, `app/Models/{Urdido,Engomado}/**`, `app/Livewire/Engomado/**`, `resources/views/modulos/{urdido,engomado}/**` salvo `programar-*`, `resources/views/catalogosurdido/**`, `resources/js/modulos/{urdido,engomado}/**` (nuevo), tests del módulo, `.planning/phases/19-modulos/19-00-RECETA.md` y `19-01-*` | Tableros Programar Urdido/Engomado (`app/Livewire/UrdEng/**`, `app/Services/Programas/**`, controllers `ProgramaUrdido`/`ProgramaEngomado`: van con 19-05), `tel-bpm` y demás Tejedores (19-04), utils/componentes/layouts (HANDOFF a 17-02), PT |
| 19-03 | `routes/modules/atadores.php`, `app/Http/Controllers/Atadores/**`, `app/Services/{Atadores,OeeAtadores}/**`, `app/Models/Atadores/**`, `resources/views/modulos/{atadores,catalogos-atadores}/**`, `resources/js/modulos/{atadores,catalogos-atadores}/**`, tests del módulo, `.planning/phases/19-modulos/19-03-*` | `19-00-RECETA.md` (la escribe 19-01; si no existe aún, sigue la receta de `19-CONTEXT.md`), utils/componentes/layouts (HANDOFF a 17-02), otros módulos, PT |
| PT 05 | Fila PT del protocolo + `app/Http/Requests/Planeacion/ProgramaTejido/**`, `app/Data/Planeacion/ProgramaTejido/**`, `resources/views/modulos/programa-tejido/modal/redbooth.blade.php` (vuelve a PT), `resources/views/components/programa-tejido/**` (HANDOFF PT B1) | Todo lo demás; utils/componentes (HANDOFF a 17-02) |

Compartidos: `scripts/ratchet-baseline.json` (solo bajar) y `phpstan-baseline.neon` (solo quitar entradas).

---

## 1. Fase 17-02 — UX global

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (propiedad y decisiones de esta ola) y CLAUDE.md.

Fase 17-02 — UX global. IDs: UX-01..09, UX-11..18 (UX-10 contraseñas: FUERA por decisión del owner, no las toques).
Contexto: .planning/phases/17-ux/17-CONTEXT.md. Componentes ya integrados (fase 16: x-ui.flash, modal <dialog>, loader único, tokens en @theme) y utils TS (http, notify, format, dom, combobox). Escribe primero .planning/phases/17-ux/17-02-PLAN.md y luego ejecútalo.
Rama: claude/17-02-ux-global (base claude/friendly-hopper-506bg9).

Alcance: flash visible en layout y home (UX-01); <title> por página (UX-02); un solo h1 (UX-03); pinch-zoom habilitado salvo pantallas andón (UX-04); user-select solo en el chrome, no en datos/folios (UX-05); helper global de acciones sin clic derecho para tablet (long-press + botón "⋮") en resources/js/utils o componentes, listo para que cada módulo lo adopte (UX-06: los módulos lo aplican en su 19-xx; PT en su track); texto mínimo 12 px en layout/componentes (UX-07); lang/es + APP_LOCALE=es y acentos (UX-08; en login solo textos/acentos, cero cambios de reglas); páginas 403/404/419/500/503 con CSS cargado y "volver" consistente (UX-09), con el código de referencia del evento de esa excepción en errors/500 (HANDOFF 20-02 #5); 419/401 unificado (UX-11); banner sin conexión con el evento towell:conexion (UX-12); duración de toasts única y toasts debajo del navbar (UX-13, HANDOFF 16 A4); aria-label en botones de ícono globales y foco visible (UX-14, UX-15); mojibake de ModulosController (UX-16, solo strings); meta towell-ruta con uri() cuando la ruta no tiene nombre (HANDOFF 18-01 #1); mostrarModalDiasLiberar del navbar a módulo (HANDOFF PT B2); loader de app-core.js → window.loader (HANDOFF 16 A3); y la checklist UX-18 por pantalla en .planning/phases/17-ux/17-02-CHECKLIST.md para que la usen las 19-xx.

ERES DUEÑO DE: lo listado para 17-02 en .planning/SESIONES-OLA-3.md.
PROHIBIDO: vistas y JS de módulos (anota en HANDOFF qué debe adoptar cada módulo), AuthController y cualquier regla de contraseña, Programa Tejido, package.json/vite.config.js (si hace falta, HANDOFF).

Acuerdos: docs y commits en español ("ux: <cambio>"); modo ponytail (reusa x-ui.*, notify, dom); mismo diseño salvo lo que corrige el hallazgo; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet no sube.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/pint --test en archivos tocados; skill code-review; capturas antes/después con la skill run a 1280×800 y 768×1024 (login, home, una pantalla de cada módulo grande, páginas de error).
Entregables: código + tests + 17-02-SUMMARY.md + 17-02-CHECKLIST.md (+ HANDOFF.md). Push a claude/17-02-ux-global. NO abras PR.
```

## 2. Fase 19-01 — Urdido + Engomado

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md y CLAUDE.md.

Fase 19-01 — Módulos Urdido + Engomado: JS inline → TS. IDs: MIG-URD-*, MIG-ENG-* (+ PERF-08..11, SEC-07 y UX-18 del módulo; SEC-06 NO: AuthZ sigue en auditar).
Contexto: .planning/phases/19-modulos/19-CONTEXT.md (receta y fila 19-01). Es el módulo con más JS inline (engomado 7 136 líneas en 12 vistas, urdido 3 448 en 11): captura-formula, modulo-produccion-engomado, urdido/produccion/_scripts, BPM Urdido/Engomado, BPM-Line, modal-calificar-julios vs -eng, reportes.
Escribe primero .planning/phases/19-modulos/19-00-RECETA.md (receta común para todas las 19-xx, a partir de 19-CONTEXT.md; si llega la checklist UX-18 de 17-02, enlázala) y .planning/phases/19-modulos/19-01-PLAN.md; divide en -p1 (Engomado) y -p2 (Urdido) con commits separados si pasa de ~1 500 líneas.
Rama: claude/19-01-urdido-engomado (base claude/friendly-hopper-506bg9).

Receta: <script> → resources/js/modulos/<mod>/<pantalla>/index.ts (Vite por glob, no toques vite.config.js); datos por <script type="application/json">; onclick → data-accion + delegate(); fetch → http; Swal/toasts → notify; escapeHtml/debounce → utils/format; modales/tablas/filtros → x-ui.*; puente window.fn solo si otro archivo lo llama (anótalo). Dedupe: BPM Urd/Eng y BPM-Line, calificar-julios Urd/Eng en una vista/JS parametrizados (receta-componentes.md). Del backend del módulo: N+1 y predicados no-sargables con número antes/después (PERF-08..11; índices solo como .sql revisado, 2008 R2), quitar catch → getMessage() hacia el usuario (SEC-07, usa HandlesApiErrors), huecos de 20-03-MAPA-AUTHZ.md del módulo (p. ej. actualizar-campo-orden sin validar) en modo AUDITAR. Mismo diseño: capturas antes/después en tablet.

ERES DUEÑO DE: lo listado para 19-01 en .planning/SESIONES-OLA-3.md.
PROHIBIDO: tableros Programar Urdido/Engomado (app/Livewire/UrdEng/**, app/Services/Programas/**, controllers ProgramaUrdido/ProgramaEngomado → 19-05), tel-bpm y Tejedores (19-04), utils/componentes/layouts (HANDOFF a 17-02), Programa Tejido, package.json, vite.config.js.

Acuerdos: docs y commits en español ("urdido: …", "engomado: …"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL 2008 R2 (hay test que vigila database/sql).
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet (-- --update solo para fijar bajas); vendor/bin/pint --test en archivos tocados; skill code-review; con la skill run abre cada pantalla migrada y verifica 0 errores de consola.
Entregables: código + tests (node para lógica pura + feature del backend) + 19-00-RECETA.md + 19-01-SUMMARY.md (+ HANDOFF.md). Push a claude/19-01-urdido-engomado. NO abras PR.
```

## 3. Fase 19-03 — Atadores

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md y CLAUDE.md.

Fase 19-03 — Módulo Atadores. IDs: MIG-ATA-* (+ PERF-08..11, SEC-07 y UX-18 del módulo; SEC-06 NO: AuthZ sigue en auditar).
Contexto: .planning/phases/19-modulos/19-CONTEXT.md (receta y fila 19-03; si ya existe 19-00-RECETA.md en la base, síguela; si no, sigue la receta de 19-CONTEXT y no la escribas tú: es de 19-01). Atadores tiene 2 744 líneas de JS inline en 7 vistas.
Escribe primero .planning/phases/19-modulos/19-03-PLAN.md y luego ejecútalo.
Rama: claude/19-03-atadores (base claude/friendly-hopper-506bg9).

Alcance: calificar-atadores y programa atadores a resources/js/modulos/atadores/** (misma receta); programa atadores hoy re-descarga el HTML completo cada 5 s aunque la pestaña esté oculta → Livewire con wire:poll.visible o JSON con http, midiendo antes/después (bytes por minuto y tiempo); N+1 con exists() por ítem y Model::all() ×4 en AtadoresController (número antes/después); SEC-07 del módulo; huecos de .planning/phases/20-arq-sec/20-03-MAPA-AUTHZ.md: POST atadores/save con action=supervisor debe auditar registrar,45 (modo AUDITAR, no enforce) y la ruta de reportes-atadores/oee/despachar cuando el owner mande su idrol; catálogo de Comentarios usa Nota1 (texto libre) como llave de ruta y un "/" da 404 → llave por Id (HANDOFF 16 C3), sin romper URLs existentes. Mismo diseño: capturas antes/después en tablet.

ERES DUEÑO DE: lo listado para 19-03 en .planning/SESIONES-OLA-3.md.
PROHIBIDO: 19-00-RECETA.md (de 19-01), utils/componentes/layouts (HANDOFF a 17-02), otros módulos, Programa Tejido, package.json, vite.config.js.

Acuerdos: docs y commits en español ("atadores: …"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL 2008 R2.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/pint --test en archivos tocados; skill code-review; con la skill run verifica cada pantalla migrada (0 errores de consola) y el polling con la pestaña oculta.
Entregables: código + tests + 19-03-SUMMARY.md (+ HANDOFF.md). Push a claude/19-03-atadores. NO abras PR.
```

## 4. PT 05 — Mutaciones

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, track Programa Tejido (PT). Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md, .planning/PROJECT.md (D-E: mismo diseño; 05 se adelanta a 03) y los SUMMARY de PT 01, 01.1, 02 y 04-perf.

Fase PT 05 — Mutaciones. IDs: PT-MUT-01, PT-DOM-01, PT-DOM-02, PT-ROL-01, PT-DUP-01..04, PT-PERF-02 (N+1 de ProgramaTejidoController::store()).
Plan base: .planning/phases/05-mutations/05-mutations-PLAN.md (y 05-REVIEW.md). Actualízalo a 05-mutations-PLAN.md v2 con: PT-DUP-01..04 de .planning/REQUIREMENTS.md, las capacidades Programa/Muestras (ProgramaTejidoSurface) y el requisito de liberar Muestras con "M" (02-MUESTRAS-LIBERAR.md) si cae en una mutación de este plan. Luego ejecútalo.
Rama: claude/pt-05-mutaciones (base claude/friendly-hopper-506bg9).

Reglas: FormRequest → DTO → Action transaccional por caso de uso; los endpoints y payloads legacy siguen compatibles (el snapshot de rutas y los tests de caracterización de PT-01 no cambian salvo lo que el plan justifique); el observer no se reemplaza entero; ningún fallo de derivados vuelve como éxito silencioso; shadow comparison de calculadores puros contra el comportamiento actual; PT-DUP: suppress/restore de observers, fallback de FechaFinal, chequeo "Ultimo" y scopes Salon/Telar con una sola implementación cada uno; N+1 de store() con número antes/después. Mismo diseño: sin cambios de UI (D-E). HANDOFF PT B1 (req-programa-tejido-line-table.blade.php) y B3 (redbooth.blade.php vuelve a PT: mover su <script> al bundle de resources/js/programa-tejido) si te da tiempo, en commits aparte.

ERES DUEÑO DE: lo listado para PT 05 en .planning/SESIONES-OLA-3.md.
PROHIBIDO: todo fuera de esa fila; utils/componentes (HANDOFF a 17-02); package.json, vite.config.js, bootstrap/**, config/database.php.

Acuerdos: docs y commits en español ("programa tejido: …"); modo ponytail; NUNCA saltar/desactivar tests; no ejecutar migraciones (SQL como .sql revisado, 2008 R2); no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet no sube; aquí no hay SQL Server: lo que requiera datos reales va como runbook para el owner.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; php artisan planeacion:programa-tejido-health en sqlite; vendor/bin/pint --test en archivos tocados; skills code-review y security-review.
Entregables: código + tests + 05-SUMMARY.md (+ HANDOFF.md). Push a claude/pt-05-mutaciones. NO abras PR.
```
