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

---

# Ola 3 — segunda tanda

Abierta el 2026-09-29 con 17-02, 19-01 y PT 05 integradas en la rama (`f55e5663`, con `main` del 29 incluido). Base: `claude/friendly-hopper-506bg9`. Tags `towell-refactor-2026`, `ola-3`, `tanda-2`. 19-03 sigue abierta (espera al owner) y conserva su fila de arriba.

| Sesión | Rama | Contexto |
|---|---|---|
| 19-02 — Tejido | `claude/19-02-tejido` | `phases/19-modulos/19-00-RECETA.md` + `19-CONTEXT.md` (19-02) + `20-03-MAPA-AUTHZ.md` |
| 19-05 — Programa Urd-Eng | `claude/19-05-programa-urd-eng` | receta + `19-CONTEXT.md` (19-05) + HANDOFF 19-01 U1/U7 |
| 19-08 — Mantenimiento | `claude/19-08-mantenimiento` | receta + `19-CONTEXT.md` (19-08) + `docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md` |
| PT 03 — Shell Livewire | `claude/pt-03-shell-livewire` | `phases/03-frontend-shell/03-shell-livewire-PLAN.md` + `04-PERF-MEDIDO.md` + `05-SUMMARY.md` |

**Orden de merge:** en el orden en que terminen (no comparten archivos). El owner commitea seguido en `main` sobre Cortes, Paros y Crudo: cada sesión hace `git merge origin/main` antes de su último push.

## Propiedad de archivos (segunda tanda)

| Sesión | Dueño de | Prohibido |
|---|---|---|
| 19-02 | `routes/modules/tejido.php`, `app/Http/Controllers/Tejido/**` (incl. `CortesEficiencia`, `InventarioTelas`, `InventarioTrama`, `MarcasFinales`, `ProduccionReenconado`, `Reportes`), `app/Services/Tejido/**`, `app/Models/Tejido/**`, `app/Livewire/InventarioTrama/**`, `resources/views/modulos/{tejido,cortes-eficiencia,inventario-trama,marcas-finales}/**`, `resources/views/modulos/produccion-reenconado-cabezuela.blade.php`, `resources/js/tejido/**`, `resources/js/modulos/tejido/**` (nuevo), tests del módulo, `.planning/phases/19-modulos/19-02-*` | Crudo, Trazabilidad, Tejedores/BPM (19-04), `components/telares/**` salvo texto < 12 px, utils/componentes/layouts (HANDOFF), PT |
| 19-05 | `routes/modules/programa-urd-eng.php`, `app/Http/Controllers/ProgramaUrdEng/**`, `app/Http/Controllers/Urdido/ProgramaUrdido/**`, `app/Http/Controllers/Engomado/ProgramaEngomado/**`, `app/Livewire/UrdEng/**`, `app/Services/{Programas,ProgramaUrdEng}/**`, `resources/views/modulos/programa_urd_eng/**`, `resources/views/modulos/urdido/{programar-urdido-livewire,editar-orden-programada}.blade.php`, `resources/views/modulos/engomado/{programar-engomado-livewire,editar-orden-engomado}.blade.php`, `resources/views/livewire/urd-eng/**`, `resources/js/{urd-eng,programa-urd-eng}/**`, `resources/js/modulos/programa-urd-eng/**` (nuevo), `public/js/modulos/programa_urd_eng/**`, `config/program-board.php`, tests del módulo, `.planning/phases/19-modulos/19-05-*` | Resto de Urdido/Engomado (de 19-01, ya integrado: solo quitar el puente U1 en `resources/js/modulos/urdido/comun/calificar-julios/` si hace falta), utils/componentes/layouts, PT |
| 19-08 | `routes/modules/mantenimiento.php`, `app/Http/Controllers/Mantenimiento/**`, `app/Livewire/Mantenimiento/**`, `app/Services/Mantenimiento/**` salvo `ParoTelegramNotifier` (solo lectura), `app/Models/Mantenimiento/**`, `resources/views/modulos/mantenimiento/**`, `resources/views/livewire/mantenimiento/**`, `resources/js/modulos/mantenimiento/**` (nuevo), tests del módulo, `.planning/phases/19-modulos/19-08-*` | Mecánicos (19-07), Telegram/cola, utils/componentes/layouts, PT |
| PT 03 | Fila PT del protocolo + `app/Livewire/Planeacion/**`, `resources/views/livewire/planeacion/**`, `app/Support/Planeacion/**`, `resources/js/modulos/programa-tejido-v2/**` y `resources/css/planeacion/**` (nuevos), `components/navbar/sections/programa-tejido.blade.php` (HANDOFF 17-02 B1), `resources/views/modulos/programa-tejido/modal/redbooth.blade.php` + `resources/js/modulos/redbooth/**` (nuevo; HANDOFF PT-05 B4) | `vite.config.js` (congelado: entradas nuevas bajo `resources/js/modulos/**/index.ts`, que el glob ya toma), utils/componentes, módulos 19-xx |

## 5. Fase 19-02 — Tejido

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (segunda tanda: propiedad y decisiones), CLAUDE.md, .planning/phases/19-modulos/19-00-RECETA.md (receta obligatoria, escrita por 19-01) y .planning/phases/17-ux/17-02-CHECKLIST.md (checklist UX-18 por pantalla).

Fase 19-02 — Módulo Tejido: JS inline → TS. IDs: MIG-TEJ-01..04 (+ PERF-08..11, SEC-07 y UX-18 del módulo; SEC-06 NO: AuthZ sigue en auditar).
Contexto: fila 19-02 de .planning/phases/19-modulos/19-CONTEXT.md; mira 19-01-SUMMARY.md como ejemplo de entrega. Escribe primero .planning/phases/19-modulos/19-02-PLAN.md; divide en -p1/-p2 si pasa de ~1 500 líneas sin contar movimientos.
Rama: claude/19-02-tejido (base claude/friendly-hopper-506bg9).

Alcance: las 4 vistas tejido/secuencia/* → 1 vista + 1 bundle parametrizados; cortes-eficiencia (pdf.js ya es npm vía window.librerias.pdfjs()) y el N+1 de CortesEficienciaController::obtenerDatosVisualizacionPorFecha (3 consultas por fecha del rango; mitad pendiente de PT-PERF-02, HANDOFF PT-05 B3) con número antes/después; inventario de telas (bug de HANDOFF 15-01: lee err.response?.data?.message, con window.http es err.data?.message), inventario de trama, marcas finales, reportes de Tejido y reenconado; huecos de .planning/phases/20-arq-sec/20-03-MAPA-AUTHZ.md del módulo en modo AUDITAR (nunca enforce); SEC-07 del módulo (HandlesApiErrors); HANDOFF 17-02 C1–C8 que caigan en Tejido (clic derecho de tejido/reportes/saldos-2026 → accionesTactiles, <h1> propios → <h2>, texto < 12 px, toasts locales → notify). Test guardián de JS inline por vista como VistasSinJsInlineTest de 19-01. Mismo diseño: capturas antes/después a 768×1024 y 1280×800 (puedes reusar el arnés .planning/phases/19-modulos/19-01-arnes/).

ERES DUEÑO DE: la fila 19-02 de la segunda tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: Crudo, Trazabilidad, Tejedores/BPM (19-04), utils/componentes/layouts (HANDOFF), Programa Tejido, package.json, vite.config.js.

Acuerdos: docs y commits en español ("tejido: …", "cortes: …"); modo ponytail (reusa http, notify, format, dom, combobox, x-ui.*, accionesTactiles); ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con SQL Server 2008 R2 (hay test que vigila database/sql); el owner commitea en main sobre Cortes: haz git merge origin/main antes del último push y resuelve.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet (-- --update solo para fijar bajas); vendor/bin/pint --test en TODOS los PHP que cambies (incluidos scripts de arnés: el CI los revisa); skill code-review; con la skill run abre cada pantalla migrada y verifica 0 errores de consola.
Entregables: código + tests + 19-02-SUMMARY.md (+ HANDOFF.md). Push a claude/19-02-tejido. NO abras PR.
```

## 6. Fase 19-05 — Programa Urdido-Engomado

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (segunda tanda), CLAUDE.md, .planning/phases/19-modulos/19-00-RECETA.md y .planning/phases/17-ux/17-02-CHECKLIST.md.

Fase 19-05 — Programa Urdido-Engomado: JS inline y public/js → TS. IDs: MIG-PUE-01..04 (+ PERF-08..11, SEC-07 y UX-18 del módulo; SEC-06 NO).
Contexto: fila 19-05 de 19-CONTEXT.md; 19-01-SUMMARY.md y la sección "De 19-01" de .planning/phases/19-modulos/HANDOFF.md (U1, U7). Escribe primero .planning/phases/19-modulos/19-05-PLAN.md.
Rama: claude/19-05-programa-urd-eng (base claude/friendly-hopper-506bg9).

Alcance: programacion-requerimientos (1 358 líneas inline), reservar-programar, creacion-ordenes y public/js/modulos/programa_urd_eng/creacion-ordenes.js (1 460 líneas, fuera de Vite: BUG-033) a resources/js/modulos/programa-urd-eng/**; karl-mayer (crear-karl-mayer); tableros Livewire Programar Urdido/Engomado y edición de órdenes (app/Livewire/UrdEng/**: solo JS inline y adopción de utils, sin rediseño); HANDOFF 19-01 U1 (importar el modal de calificar julios de resources/js/modulos/urdido/comun/calificar-julios/ y quitar el puente window.abrirModalCalificarJuliosEng) y U7 (ProgramarUrdidoController::reimpresionVentanaImprimir responde 400 con <script>alert inline); clic derecho de resources/js/programa-urd-eng/reservar-programar.ts → accionesTactiles (HANDOFF 17-02 C1); N+1/consultas de InventarioReservasService y demás services del módulo con número antes/después (SEC-01 ya auditó el SQL interpolado: 0 interpolaciones de input; el ratchet vigila las 8 internas y no deben subir); SEC-07; huecos de 20-03-MAPA-AUTHZ del módulo en modo AUDITAR. Mismo diseño: capturas antes/después en tablet.

ERES DUEÑO DE: la fila 19-05 de la segunda tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: el resto de Urdido/Engomado (de 19-01), utils/componentes/layouts (HANDOFF), Programa Tejido, package.json, vite.config.js.

Acuerdos: docs y commits en español ("programa urd-eng: …"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL 2008 R2; git merge origin/main antes del último push.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/pint --test en TODOS los PHP que cambies; skill code-review; con la skill run verifica cada pantalla migrada (0 errores de consola) y un flujo completo de reservar → programar → crear orden.
Entregables: código + tests + 19-05-SUMMARY.md (+ HANDOFF.md). Push a claude/19-05-programa-urd-eng. NO abras PR.
```

## 7. Fase 19-08 — Mantenimiento

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (segunda tanda), CLAUDE.md, .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md y docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md.

Fase 19-08 — Módulo Mantenimiento. IDs: MIG-MAN-01..04 (+ PERF-08..11, SEC-07 y UX-18 del módulo; SEC-06 NO).
Contexto: fila 19-08 de 19-CONTEXT.md; BUG-025 en docs/cerebro-towell/Auditoria/inventario-bugs.md. Escribe primero .planning/phases/19-modulos/19-08-PLAN.md.
Rama: claude/19-08-mantenimiento (base claude/friendly-hopper-506bg9).

Alcance: catálogos de Mantenimiento (fallas, operadores) a Livewire con app/Livewire/Concerns/ConTabla + x-tabla (la guía dice sí; CatalogoFallas ya existe como referencia); nuevo paro, finalizar paro y reportes (fallas/paros) con JS inline → resources/js/modulos/mantenimiento/**; BUG-025: quitar el userId === 6 hardcodeado sin cerrar nada (el alta de paros sigue abierta a todos por decisión del owner); SEC-07 del módulo; huecos de 20-03-MAPA-AUTHZ del módulo en modo AUDITAR (paros quedan sin gate, excepción del owner); HANDOFF 17-02 C2 (reportes-mantenimiento-index: <h1> → <h2>) y demás C1–C8 que caigan aquí. El aviso de paro por Telegram ya va por la cola (ParoTelegramNotifier, solo lectura): no lo toques. Mismo diseño: capturas antes/después en tablet (el alta de paro se usa en piso).

ERES DUEÑO DE: la fila 19-08 de la segunda tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: Mecánicos (19-07), Telegram/cola, utils/componentes/layouts (HANDOFF), Programa Tejido, package.json, vite.config.js.

Acuerdos: docs y commits en español ("mantenimiento: …"); modo ponytail; ninguna optimización sin número antes/después; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL 2008 R2; el owner commitea en main sobre MantenimientoParosController: git merge origin/main antes del último push.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; vendor/bin/pint --test en TODOS los PHP que cambies; skill code-review; con la skill run verifica cada pantalla (0 errores de consola) y el flujo alta → finalizar paro en tablet.
Entregables: código + tests + 19-08-SUMMARY.md (+ HANDOFF.md). Push a claude/19-08-mantenimiento. NO abras PR.
```

## 8. PT 03 — Shell Livewire (mismo diseño)

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, track Programa Tejido (PT). Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (segunda tanda), .planning/PROJECT.md (D-E: PT sí migra a Livewire, sin cambiar el diseño, y solo se da por buena si mejora números), docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md y los SUMMARY de PT 02, 04-perf y 05.

Fase PT 03 — Shell Livewire. IDs: PT-UI-01, PT-ROL-01.
Plan base: .planning/phases/03-frontend-shell/03-shell-livewire-PLAN.md (+ 03-RESEARCH.md, 03-REVIEW.md). Actualízalo a v2 con lo que cambió desde que se escribió: PT 04-perf (HTML −16 % gzip, 0 <script> inline propios), PT 05 (mutaciones v2 detrás de flag, observers/scopes unificados), 17-02 (títulos, zoom, accionesTactiles, sesion.ts), y la regla de Ola 3 de no tocar vite.config.js: la entrada va en resources/js/modulos/programa-tejido-v2/index.ts (glob). Luego ejecútalo.
Rama: claude/pt-03-shell-livewire (base claude/friendly-hopper-506bg9).

Reglas: el shell v2 va detrás de canary por allowlist (como el plan) y con el canary apagado la respuesta es byte a byte la legacy (cero assets v2); Muestras resuelve su superficie explícita; el dataset nunca es propiedad pública de Livewire; mismo diseño (capturas lado a lado 1280×800 y 768×1024). GATE: mide TTFB, KB de HTML (gzip) y tiempo de interacción de v2 contra legacy con el método de .planning/phases/04-ux-grid/04-PERF-MEDIDO.md; si v2 no mejora, se documenta con números, queda apagado y no se sigue con 04-ux sobre Livewire. HANDOFF 17-02 B1 (onclick="mostrarModalDiasLiberar()" del navbar de PT → data-accion + import de resources/js/componentes/dias-liberar.ts) y B2 (menús contextuales de PT y liberar-ordenes → accionesTactiles + botonAcciones). HANDOFF PT-05 B4 (Redbooth): mover el <script> de modal/redbooth.blade.php a resources/js/modulos/redbooth/index.ts cargado desde el propio modal con @vite, para que PT, Trazabilidad y CatCodificación lo sigan teniendo; commits aparte.

ERES DUEÑO DE: la fila PT 03 de la segunda tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: vite.config.js, package.json, utils/componentes (HANDOFF), módulos 19-xx, bootstrap/**, config/database.php.

Acuerdos: docs y commits en español ("programa tejido: …"); modo ponytail; NUNCA saltar/desactivar tests; no ejecutar migraciones (SQL como .sql revisado, 2008 R2); no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet no sube; aquí no hay SQL Server: lo que requiera datos reales va como runbook para el owner (canary en Laragon); git merge origin/main antes del último push.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; php artisan planeacion:programa-tejido-health en sqlite; vendor/bin/pint --test en TODOS los PHP que cambies; skills code-review y security-review.
Entregables: código + tests + 03-SUMMARY.md con la tabla de números v2 vs legacy (+ HANDOFF.md). Push a claude/pt-03-shell-livewire. NO abras PR.
```

---

# Ola 3 — Track CAL (fase 22)

Abierta el 2026-09-29 a pedido del owner (propuesta de calidad propia, analizada en `phases/22-calidad/22-CONTEXT.md`). Base: `claude/friendly-hopper-506bg9`. Tags `towell-refactor-2026`, `ola-3`, `cal`. No comparte archivos con 19-03, 19-05, 19-08 ni PT.

| Sesión | Rama | Contexto |
|---|---|---|
| CAL — 22-01 gates, 22-02 (libre), 22-04, 22-05 | `claude/22-cal-gates` | `phases/22-calidad/22-CONTEXT.md` |

**Propiedad:** fila "CAL (22)" de `PROTOCOLO-SESIONES.md` §5, más `package.json/lock` (solo la devDependency `jscpd`), los exports `Bpm{Urdido,Engomado}Export` y `ReporteResumenSemanal{Urdido,Engomado}Export` con sus llamadores (solo `use`/`new`), y las constantes `KaizenExport::MESES` y `ReporteOtDiariasExport::COL_NOMBRE`.

## 9. CAL — Gates y primera deuda

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, track CAL (calidad). Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Track CAL": propiedad), CLAUDE.md y .planning/phases/22-calidad/22-CONTEXT.md (hallazgos del owner verificados por el integrador y el alcance ajustado). Si el owner sube su documento de análisis a .planning/phases/22-calidad/, úsalo también.

Fase 22 — Calidad, primera tanda. IDs: CAL-01, CAL-02 (parte libre), CAL-04, CAL-05.
Principio: nada nuevo empeora (gates sobre archivos cambiados o métricas del ratchet, SIN baselines globales nuevos) y la deuda vieja se paga archivo por archivo, con tests primero, por su dueño. Escribe primero .planning/phases/22-calidad/22-01-PLAN.md (cubre las cuatro partes) y luego ejecútalo en commits separados, en este orden; si pasa de ~1 500 líneas sin contar movimientos, parte en -p1/-p2.
Rama: claude/22-cal-gates (base claude/friendly-hopper-506bg9).

22-01 Gates: (a) scripts/ratchet.mjs: métrica "catch vacío" en app/ (hoy 29; las excepciones deliberadas del CONTEXT cuentan igual, solo que nadie las baja) y métrica "duplicación %" con jscpd (devDependency; formatos php, ts, js, blade; techo = lo medido en la rama; excluir vendor, node_modules, public/build, tests/fixtures). (b) phpmd/phpmd en require-dev + phpmd.xml con reglas unusedcode y codesize (complejidad ciclomática, NPath, largo de método/clase con umbrales documentados en el PLAN), aplicado SOLO a los PHP cambiados en el CI, reusando la lógica de archivos cambiados del paso de Pint en .github/workflows/frontend-checks.yml; verifica que corre limpio en PHP 8.4. (c) composer audit en el CI: bloquea solo si el push/PR cambia composer.lock; si no, solo aviso. (d) script "composer quality" (pint --test en cambiados + phpstan + phpmd en cambiados + npm run ratchet) documentado en CLAUDE.md §Commands y §CI. PHP Insights NO entra como gate.
22-02 Código muerto (solo lo que no tiene dueño activo): los 2 métodos privados sin uso de app/Imports/ReqModelosCodificadosImport.php (convertToInt, isValidTotalValue) y las constantes sin uso KaizenExport::MESES y ReporteOtDiariasExport::COL_NOMBRE; luego quita del phpstan-baseline.neon solo las entradas que dejan de aplicar (nunca agregar). NO toques OeeAtadoresFileService (19-03), BalancearTejido (PT) ni TelBpmController (19-04).
22-04 Tests de hotspots: tests de ReqModelosCodificadosImport (import en cola ShouldQueue: filas válidas, vacías, totales, errores; con la cola en sync o Queue::fake) y convierte su catch vacío en report() si es del flujo principal. Cobertura por archivo con pcov medida EN ESTA RAMA (no en main: allí faltan ~550 tests de la Ola 3) → tabla en 22-01-SUMMARY.md con los 20 archivos más riesgosos (líneas × complejidad) con menos cobertura, para priorizar siguientes tandas. No se agrega cobertura al CI.
22-05 Duplicación: BpmUrdidoExport/BpmEngomadoExport (282 líneas, difieren 6) y ReporteResumenSemanalUrdidoExport/ReporteResumenSemanalEngomadoExport (214, difieren 6) → una clase parametrizada por par (o una base + variante), mismos nombres públicos para sus llamadores; test que genere el Excel antes y después y compare celda a celda (valores, estilos relevantes, anchos). Mide jscpd antes/después.

ERES DUEÑO DE: la fila "CAL (22)" de .planning/PROTOCOLO-SESIONES.md y de la sección Track CAL de SESIONES-OLA-3.md: composer.json/lock (solo require-dev), package.json/lock (solo la devDependency jscpd), .github/workflows/frontend-checks.yml (pasos de calidad), scripts/ratchet.mjs + scripts/ratchet-baseline.json, phpmd.xml, phpstan-baseline.neon (solo quitar), app/Imports/ReqModelosCodificadosImport.php, las 4 clases de export de arriba y sus llamadores (solo el use/new), las 2 constantes citadas, tests nuevos, CLAUDE.md (§Commands y §CI), .planning/phases/22-calidad/**.
PROHIBIDO: archivos de 19-03 (Atadores/OeeAtadores), 19-05 (Programa Urd-Eng), 19-08 (Mantenimiento), Programa Tejido, bootstrap/**, vite.config.js, config/database.php, reglas de contraseña.

Acuerdos: docs y commits en español ("calidad: …"); modo ponytail (reusa ratchet, baseline de phpstan y la lógica de archivos cambiados; no agregues baselines globales); NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet no sube; SQL 2008 R2 si hubiera; git merge origin/main antes del último push y vuelve a correr todo.
Antes de push: hook SessionStart; php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; composer quality; vendor/bin/pint --test en TODOS los PHP que cambies; skill code-review. Comprueba que el CI nuevo pasa en tu rama (el workflow corre en push a claude/**) y que un PHP cambiado con un método privado sin usar sí lo haría fallar (pruébalo en un commit local que no subas).
Entregables: código + tests + 22-01-PLAN.md + 22-01-SUMMARY.md (con la tabla de cobertura y números antes/después) (+ HANDOFF.md). Push a claude/22-cal-gates. NO abras PR.
```

## 10. CAL — Dependencias con advisories (22-02s)

Abierta el 2026-09-30 por el hallazgo de seguridad de 22-01 (2 críticos en `phpoffice/phpspreadsheet`). Rama `claude/22-cal-deps`. Dueña de `composer.json` (solo `config.platform` y lo que el update exija sin subir mayores), `composer.lock`, `package-lock.json`; no toca lógica de módulos con dueño activo.

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12 + Livewire 4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, track CAL. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (Track CAL), CLAUDE.md, .planning/phases/22-calidad/22-CONTEXT.md y 22-01-SUMMARY.md (§Hallazgos y HANDOFF).

Fase 22 — CAL-deps: dependencias con advisories de seguridad. Escribe primero .planning/phases/22-calidad/22-02s-PLAN.md y luego ejecútalo.
Rama: claude/22-cal-deps (base claude/friendly-hopper-506bg9).

Situación (composer audit sobre composer.lock, 2026-09-30): 56 advisories en 16 paquetes; 2 críticos en phpoffice/phpspreadsheet 1.30.2 (uno es SSRF/RCE en IOFactory::load con nombre de archivo del usuario; la app importa Excel subidos con maatwebsite/excel), altos en laravel/framework v12.53.0 (arreglo en 12.60), maatwebsite/excel 3.1.67 (arreglo en 3.1.70), guzzlehttp/guzzle 7.10.0, league/commonmark 2.8.2, symfony/* y otros. npm audit: 1 alto (nanoid).

Alcance:
1. Versión de PHP de producción: nadie la dejó escrita. Averíguala con lo que haya en el repo (docs/cerebro-towell/Runbooks, .env.example, requisitos del lock actual: el máximo "require php" de los paquetes instalados hoy es un piso que producción ya cumple). Fija config.platform.php en composer.json a ese piso para que el lock nuevo nunca exija más de lo que hay en Laragon, y documéntalo; si hay duda real, deja el piso más bajo que satisfaga el lock actual y anótalo como pregunta para el owner en el SUMMARY (el runbook de despliegue debe decir cómo confirmarlo: php -v en Laragon).
2. composer update -W SOLO de los paquetes con advisories (y lo que arrastren), dentro de las restricciones actuales de composer.json: sin subir mayores (laravel/framework ^12, maatwebsite/excel ^3.1, phpspreadsheet dentro de lo que permita maatwebsite 3.1). Si un advisory no tiene arreglo sin subir mayor, no lo fuerces: documéntalo con su riesgo real para Towell.
3. npm audit fix SIN --force; si algo pide mayor, documéntalo.
4. Verificación extra de Excel: los snapshot celda a celda de CAL (tests/Unit/Calidad/*), los tests de imports/exports existentes y, con el arnés de .planning/phases/19-modulos/19-01-arnes o un test, un import real de .xlsx pequeño y un export descargado; lista en el SUMMARY qué pantallas de producción conviene probar a mano tras desplegar (imports de Programa Tejido, Codificación, Calendarios; exports de reportes).
5. composer audit y npm audit antes/después en el SUMMARY (conteo por severidad) + notas de despliegue (composer install --no-dev -o, npm ci && npm run build, optimize) y rollback (volver al composer.lock anterior).

ERES DUEÑO DE: composer.json (solo config.platform y restricciones que el update exija, sin subir mayores), composer.lock, package-lock.json (y package.json solo si npm audit fix lo exige sin mayores), docs de .planning/phases/22-calidad/22-02s-*, notas en docs/cerebro-towell/Runbooks/deploy.md (solo la sección de dependencias). Si un update rompe código de un módulo con dueño activo (19-03 Atadores, 19-05 Programa Urd-Eng, 19-08 Mantenimiento), NO lo arregles tú: HANDOFF; si rompe código sin dueño activo, arréglalo mínimo con test.
PROHIBIDO: subir versiones mayores, tocar lógica de módulos con dueño activo, bootstrap/**, vite.config.js, config/database.php, reglas de contraseña.

Acuerdos: docs y commits en español ("calidad: …"); NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet no sube; git merge origin/main antes del último push y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G (si un update mueve el baseline, solo se permite que BAJE; si sube por firmas nuevas de un paquete, explícalo y consúltalo en el SUMMARY); npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; composer audit; skill security-review.
Entregables: composer.lock/package-lock.json actualizados + tests si hicieron falta + 22-02s-PLAN.md + 22-02s-SUMMARY.md (antes/después, versiones, riesgos restantes, despliegue y rollback) (+ HANDOFF.md). Push a claude/22-cal-deps. NO abras PR.
```

---

# Ola 3 — tercera tanda

Abierta el 2026-09-30 con la Ola 3 subida a `main`. Decisiones del owner que aplican a toda la tanda: **solo TypeScript** (también tests, scripts y la configuración de Vite) y **ORM primero** (ver PROJECT y CLAUDE.md). Además se toman en cuenta la auditoría del owner (`phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md`) y la estructura destino del backend (`phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md`). Base: `claude/friendly-hopper-506bg9`. Tags `towell-refactor-2026`, `ola-3`, `tanda-3`.

| Sesión | Rama | Dueño de | Prohibido |
|---|---|---|---|
| TS-base | `claude/22-ts-base` | `resources/js/{app,bootstrap,app-core,charts}.*`, `public/js/app-pwa.js`, `public/sw.js`, `resources/js/pwa/**` (nuevo), `vite.config.*`, `package.json/lock`, `tsconfig.json`, `scripts/ratchet.*`, `scripts/calidad.*`, `scripts/ratchet-baseline.json`, `composer.json` (solo `scripts`), `.github/workflows/frontend-checks.yml`, `resources/css/app.css` (solo `@source`), los tests JS de áreas que no son de esta tanda, líneas `<script>`/`@vite` de layouts y login, `CLAUDE.md` (Commands/CI/Frontend) | Vistas, JS y backend de módulos |
| 19-06a Codificación | `claude/19-06a-codificacion` | `catalagos/catalogoCodificacion`, `catalagos/codificacion-form`, `catcodificacion/**`, `resources/js/{catcodificacion,lmat-lista}/**`, `resources/js/modulos/codificacion/**`, `CodificacionController`, `CatCodificacionController`, `CatLMatController`, `ReqModelosCodificadosImport`, modelos de codificación, `app/Integrations/Ax/**` (BOM/L.Mat), tests del módulo y `tests/Js/{catcodificacion,lmat}-*` | 19-06b, PT (salvo el llamado al repositorio de L.Mat), utils/layouts, Vite, package.json |
| 19-06b Catálogos de Planeación (**terminada 2026-09-30**, `19-06b-SUMMARY.md`) | `claude/19-06b-catalogos-planeacion` | `catalagos/**` salvo Codificación, `public/js/catalogs/**`, `public/js/catalog-core.js`, `resources/js/catalogos/**`, `resources/js/modulos/catalogos-planeacion/**`, `components/buttons/catalog-actions.blade.php`, controllers de `Planeacion/CatalogoPlaneacion/**` salvo `ModelosCodificados`, imports de catálogos, tests del módulo y `tests/Js/catalogos-*` | Codificación (19-06a), PT, utils/layouts, Vite, package.json |
| 19-04 Tejedores | `claude/19-04-tejedores` | `routes/modules/tejedores.php`, `app/Http/Controllers/Tejedores/**`, `app/Services/Tejedores/**`, `app/Livewire/{Desarrolladores,Tejedores}/**`, vistas `tel-telares-operador`, `bpm-tejedores`, `tejedores`, `tel-actividades-bpm`, `desarrolladores`, `notificar-montado-julios`, `components/telares/**`, los puentes de `resources/js/tejido/inventario-telas.ts`, `resources/js/modulos/tejedores/**`, tests del módulo | Urdido/Engomado/Tejido (salvo lo listado), PT, utils/layouts, Vite, package.json |
| PT-TS 1 | `claude/pt-ts-1` | Vistas de PT (`liberar-ordenes`, `planeacion/utileria/**`, `planeacion/alineacion/**`), `resources/js/programa-tejido/**` salvo el cuerpo de `index.js`, `public/js/programa-tejido-menu.js`, `resources/js/modulos/{redbooth,programa-tejido}/**`, `tests/Js/programa-tejido-*` | Backend de PT (salvo lo mínimo), módulos 19-xx, utils/layouts, Vite, package.json |

Integración: en el orden en que terminen. Todas hacen `git merge origin/main` antes del último push: Jonny sigue trabajando en `main` (Ventas y Mecánicos, que por eso quedan fuera de esta tanda). **Siguiente tanda:** 20-05 Estructura Urd/Eng (pares Reportes/Programar/BpmLine/ModuloProduccion + `ProduccionTrait` + enum de status de programa), 20-04 AuthZ (Livewire `#[Locked]` y `authorize`, `addPersistentMiddleware`, Gates sobre `userCan`, `updatePermiso` con lista blanca, paros con lock, headers), PT-TS 2 (`index.js`), 19-09, 19-07, 19-10, 22-06, PT 05.1, 22-08 (SQL Server en el CI y phpat) y 22-09 (ide-helper y enums de status).

## 11. TS-base (solo TypeScript)

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12.69 + Livewire 4.4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, Ola 3 tercera tanda. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Tercera tanda": propiedad), CLAUDE.md (reglas "Solo TypeScript" y "ORM primero"), .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md, .planning/phases/22-calidad/22-CONTEXT.md y .planning/phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md.

Fase TS-base — cimiento de "solo TypeScript". IDs: FE-TS-01..04.
Escribe primero .planning/phases/15-fe-fundacion/15-03-TS-PLAN.md y luego ejecútalo en commits separados.
Rama: claude/22-ts-base (base claude/friendly-hopper-506bg9).

Alcance:
1. Gate: scripts/ratchet.* agrega métricas "líneas JS en resources/js+public/js" y "líneas de <script> inline en Blade" (incluidas islas no JSON) que no pueden subir, y el CI falla si aparece un archivo .js/.mjs/.cjs nuevo en resources/js, public/js, tests o scripts (lista de permitidos solo para lo que aún no migra, que se achica).
2. Núcleo a TS: resources/js/app.js, bootstrap.js, app-core.js, charts.js → .ts (y sus @vite en layouts/components de layout); PWA: public/js/app-pwa.js y public/sw.js → TS en resources/js/pwa/ compilados por Vite/esbuild a la MISMA URL pública (el service worker debe seguir en /sw.js con su scope; verifica registro y actualización en el navegador).
3. Herramientas a TS: vite.config.js → vite.config.ts (conserva entrada() y el glob de modulos/**/index.ts); scripts/ratchet.mjs y scripts/calidad.mjs → .ts (Node ≥ 22.18 ejecuta TS por type stripping; fija "engines" en package.json, ajusta composer.json scripts y el workflow; documenta en CLAUDE.md que Laragon necesita Node ≥ 22.18 o deja un fallback con tsx si hace falta); tsconfig incluye tests y scripts.
4. Tests JS a TS: convierte tests/Js/*.mjs/*.cjs de utils, componentes, monitoreo, urdeng/urdido/engomado, tejido, atadores, mantenimiento, programa-urd-eng, crudo, ventas, trazabilidad y demás que NO sean de las sesiones de esta tanda (catcodificacion/lmat → 19-06a; catalogos-* → 19-06b; tejedores/desarrolladores/telares → 19-04; programa-tejido-* → PT-TS 1: esas las convierte su dueño). tests/Js/agruparTelares.test.cjs prueba una copia vieja: bórralo si ya lo cubre programa-urd-eng-creacion-ordenes (HANDOFF 19-05 R5).
5. Gates de estructura del backend (20-04-ESTRUCTURA-BACKEND.md) en el ratchet: "archivos de controllers con DB::" (hoy 44) y "->validate([ en controllers" (hoy 68), que no pueden subir.
6. HANDOFF 19-08 M1: app.css con @source para *.ts (clases que solo aparecen en TS hoy no se generan).

Reglas de esta tanda (decisiones del owner, 2026-09-30):
- SOLO TYPESCRIPT: nada de .js nuevo ni <script> inline. Todo JS que toques termina en .ts (sin paso intermedio .js). Tests JS nuevos o migrados como tests/Js/<area>-*.test.ts (npm run test:js ya los corre). Vite: las entradas fijas se resuelven .ts primero (función entrada() de vite.config.js), así que al renombrar un .js a .ts solo cambias el @vite(...) de la vista, no el config.
- ORM PRIMERO: Eloquent (modelos, relaciones, scopes, casts) para todo acceso a datos del módulo; Query Builder con bindings solo sin modelo; AX (sqlsrv_ti) detrás de app/Integrations/Ax/<Agregado>Repository.php con DTOs readonly (créalo solo para lo que tu módulo use; si otra sesión ya creó uno, reúsalo); SQL crudo solo documentado y parametrizado; WHERE sargables (no envolver columnas con LTRIM/RTRIM/UPPER/CAST si no hay prueba de que hace falta: la collation es CI); nada de paginate()/simplePaginate()/skip()/offset() (PaginacionCompat). Cada cambio de consulta con test y número de consultas antes/después.
- CÓDIGO LARGO: en tu módulo, todo método > 100 líneas o con complejidad ≥ 10 que toques se parte (servicios/Actions/DTOs), con tests de caracterización ANTES de moverlo. PHPMD (composer quality) rechaza violaciones nuevas.
- ESTRUCTURA (.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md): controllers delgados (FormRequest → Action/Service → respuesta; meta ≤ 300 líneas por controller y métodos ≤ 50), nada de DB:: en controllers, validación en FormRequest (no $request->validate([...]) en línea), status del módulo como backed enum en app/Enums/<Mod>/ con cast en el modelo (mismo string que la BD; reusa uno compartido si ya existe), DTOs en app/Data/<Mod>/, lógica compartida entre pares (Urdido/Engomado, Programa/Muestras) en una sola implementación parametrizada.
- AuthZ solo en modo auditar (sin enforce); SEC-07 (sin getMessage() al usuario; catch vacíos → report($e) salvo las excepciones del CONTEXT); checklist UX-18; mismo diseño (capturas antes/después 768×1024 y 1280×800, puedes reusar los arneses de .planning/phases/19-modulos/19-0x-arnes); contraseñas y login no se tocan.

ERES DUEÑO DE: la fila TS-base de la tercera tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: vistas y JS de módulos (salvo las líneas <script>/@vite del layout y de login para app/app-core/PWA), backend de módulos, bootstrap/**, config/database.php.

Acuerdos: docs y commits en español; modo ponytail (reusa http, notify, format, dom, combobox, acciones táctiles, x-ui.*, PaginacionCompat, HandlesApiErrors); ninguna optimización sin número; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con 2008 R2; antes del último push: git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main, y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; vendor/bin/pint --test en TODOS los PHP que cambies (incluidos arneses); skill code-review; con la skill run abre cada pantalla migrada (0 errores de consola) y su flujo principal.
Entregables: código + tests + 15-03-TS-PLAN.md + 15-03-TS-SUMMARY.md (+ HANDOFF.md). Push a claude/22-ts-base. NO abras PR.
```

## 12. 19-06a Codificación

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12.69 + Livewire 4.4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, Ola 3 tercera tanda. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Tercera tanda": propiedad), CLAUDE.md (reglas "Solo TypeScript" y "ORM primero"), .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md, .planning/phases/22-calidad/22-CONTEXT.md y .planning/phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md.

Fase 19-06a — Codificación (modelos codificados, CatCodificados y L.Mat): JS → TS, ORM y código largo. IDs: MIG-COD-01..04 (+ PERF-08..11, SEC-07, UX-18 y ORM del módulo).
Contexto: fila 19-06 de 19-CONTEXT.md (dos pantallas que se quedan: catálogos = ReqModelosCodificados y codificación = CatCodificados); BUG-007 (L.Mat distinto entre CatCodificacionController::queryLmatDesdeTi y LiberarBomCrudoResolver) y BUG-012 en docs/cerebro-towell/Auditoria/inventario-bugs.md. Escribe primero .planning/phases/19-modulos/19-06a-PLAN.md; divide en -p1/-p2.
Rama: claude/19-06a-codificacion (base claude/friendly-hopper-506bg9).

Alcance: vistas catalagos/catalogoCodificacion (2 323 líneas inline), catalagos/codificacion-form (664), catcodificacion/** (497) y resources/js/catcodificacion/*.js (index 1 953, lmat-modal 2 542) + resources/js/lmat-lista/index.js → TS en resources/js/modulos/codificacion/** (o el nombre que el plan justifique); clic derecho → accionesTactiles (HANDOFF 17-02 C1). Backend: CodificacionController (1 692 líneas, 51 % cobertura), CatCodificacionController, CatLMatController (guardarLmat 381 líneas; 10 consultas directas a AX), ReqModelosCodificadosImport::collection (291) → partir con tests primero; AX detrás de app/Integrations/Ax (BOM/L.Mat) con UNA consulta de L.Mat compartida por CatCodificación y Liberar + test de contrato que compare ambas salidas (cierra BUG-007; si Liberar es de PT, deja el resolver de Liberar llamando al repositorio sin cambiar su comportamiento y HANDOFF). El owner cambió estas pantallas en main (25–27 sep): merge de main temprano y al final.

Reglas de esta tanda (decisiones del owner, 2026-09-30):
- SOLO TYPESCRIPT: nada de .js nuevo ni <script> inline. Todo JS que toques termina en .ts (sin paso intermedio .js). Tests JS nuevos o migrados como tests/Js/<area>-*.test.ts (npm run test:js ya los corre). Vite: las entradas fijas se resuelven .ts primero (función entrada() de vite.config.js), así que al renombrar un .js a .ts solo cambias el @vite(...) de la vista, no el config.
- ORM PRIMERO: Eloquent (modelos, relaciones, scopes, casts) para todo acceso a datos del módulo; Query Builder con bindings solo sin modelo; AX (sqlsrv_ti) detrás de app/Integrations/Ax/<Agregado>Repository.php con DTOs readonly (créalo solo para lo que tu módulo use; si otra sesión ya creó uno, reúsalo); SQL crudo solo documentado y parametrizado; WHERE sargables (no envolver columnas con LTRIM/RTRIM/UPPER/CAST si no hay prueba de que hace falta: la collation es CI); nada de paginate()/simplePaginate()/skip()/offset() (PaginacionCompat). Cada cambio de consulta con test y número de consultas antes/después.
- CÓDIGO LARGO: en tu módulo, todo método > 100 líneas o con complejidad ≥ 10 que toques se parte (servicios/Actions/DTOs), con tests de caracterización ANTES de moverlo. PHPMD (composer quality) rechaza violaciones nuevas.
- ESTRUCTURA (.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md): controllers delgados (FormRequest → Action/Service → respuesta; meta ≤ 300 líneas por controller y métodos ≤ 50), nada de DB:: en controllers, validación en FormRequest (no $request->validate([...]) en línea), status del módulo como backed enum en app/Enums/<Mod>/ con cast en el modelo (mismo string que la BD; reusa uno compartido si ya existe), DTOs en app/Data/<Mod>/, lógica compartida entre pares (Urdido/Engomado, Programa/Muestras) en una sola implementación parametrizada.
- AuthZ solo en modo auditar (sin enforce); SEC-07 (sin getMessage() al usuario; catch vacíos → report($e) salvo las excepciones del CONTEXT); checklist UX-18; mismo diseño (capturas antes/después 768×1024 y 1280×800, puedes reusar los arneses de .planning/phases/19-modulos/19-0x-arnes); contraseñas y login no se tocan.

ERES DUEÑO DE: la fila 19-06a de la tercera tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: catálogos de Planeación que no son Codificación (19-06b), Programa Tejido salvo el llamado al repositorio de L.Mat, utils/componentes/layouts (HANDOFF), vite.config.*, package.json.

Acuerdos: docs y commits en español; modo ponytail (reusa http, notify, format, dom, combobox, acciones táctiles, x-ui.*, PaginacionCompat, HandlesApiErrors); ninguna optimización sin número; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con 2008 R2; antes del último push: git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main, y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; vendor/bin/pint --test en TODOS los PHP que cambies (incluidos arneses); skill code-review; con la skill run abre cada pantalla migrada (0 errores de consola) y su flujo principal.
Entregables: código + tests + 19-06a-PLAN.md + 19-06a-SUMMARY.md (+ HANDOFF.md). Push a claude/19-06a-codificacion. NO abras PR.
```

## 13. 19-06b Catálogos de Planeación

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12.69 + Livewire 4.4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, Ola 3 tercera tanda. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Tercera tanda": propiedad), CLAUDE.md (reglas "Solo TypeScript" y "ORM primero"), .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md, .planning/phases/22-calidad/22-CONTEXT.md y .planning/phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md.

Fase 19-06b — Catálogos de Planeación: JS → TS, ORM y código largo. IDs: MIG-CAT-01..04 (+ PERF, SEC-07, UX-18 y ORM del módulo).
Escribe primero .planning/phases/19-modulos/19-06b-PLAN.md; divide en -p1/-p2 si hace falta.
Rama: claude/19-06b-catalogos-planeacion (base claude/friendly-hopper-506bg9).

Alcance: vistas catalagos/** salvo Codificación (catalagoEficiencia 644, catalagoVelocidad 603 —hoy duplicadas entre sí, dedupe—, calendarios/index 452 + modal-calendario 613 + modal-eliminar-rango 181, pesos-rollos 389, catalagoTelares, aplicaciones, matriz-calibres, matriz-hilos, y el resto que el plan liste) y su JS en public/js/catalogs/*.js + public/js/catalog-core.js (fuera de Vite hoy) → TS en resources/js/modulos/catalogos-planeacion/** y resources/js/catalogos/** (ya existe catalog-base.ts del piloto de atadores: reúsalo); components/buttons/catalog-actions.blade.php (300 líneas inline, HANDOFF 19-01 U6) sin onclick. Backend: CalendarioController (1 301 líneas; recalcularProgramasPorCalendario 212, 5.5 % de cobertura: tests primero), controllers de Eficiencia/Velocidad/Telares/Aplicaciones/Matrices a Eloquent y sin getMessage(); los imports de catálogos (Calendarios, Velocidades, Eficiencias, Telares, Aplicaciones) con tests del import real. Cuidado: recalcular programas por calendario escribe en ReqProgramaTejido (usar suppressObservers/restoreObservers del modelo, HANDOFF PT-05 B1).

Reglas de esta tanda (decisiones del owner, 2026-09-30):
- SOLO TYPESCRIPT: nada de .js nuevo ni <script> inline. Todo JS que toques termina en .ts (sin paso intermedio .js). Tests JS nuevos o migrados como tests/Js/<area>-*.test.ts (npm run test:js ya los corre). Vite: las entradas fijas se resuelven .ts primero (función entrada() de vite.config.js), así que al renombrar un .js a .ts solo cambias el @vite(...) de la vista, no el config.
- ORM PRIMERO: Eloquent (modelos, relaciones, scopes, casts) para todo acceso a datos del módulo; Query Builder con bindings solo sin modelo; AX (sqlsrv_ti) detrás de app/Integrations/Ax/<Agregado>Repository.php con DTOs readonly (créalo solo para lo que tu módulo use; si otra sesión ya creó uno, reúsalo); SQL crudo solo documentado y parametrizado; WHERE sargables (no envolver columnas con LTRIM/RTRIM/UPPER/CAST si no hay prueba de que hace falta: la collation es CI); nada de paginate()/simplePaginate()/skip()/offset() (PaginacionCompat). Cada cambio de consulta con test y número de consultas antes/después.
- CÓDIGO LARGO: en tu módulo, todo método > 100 líneas o con complejidad ≥ 10 que toques se parte (servicios/Actions/DTOs), con tests de caracterización ANTES de moverlo. PHPMD (composer quality) rechaza violaciones nuevas.
- ESTRUCTURA (.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md): controllers delgados (FormRequest → Action/Service → respuesta; meta ≤ 300 líneas por controller y métodos ≤ 50), nada de DB:: en controllers, validación en FormRequest (no $request->validate([...]) en línea), status del módulo como backed enum en app/Enums/<Mod>/ con cast en el modelo (mismo string que la BD; reusa uno compartido si ya existe), DTOs en app/Data/<Mod>/, lógica compartida entre pares (Urdido/Engomado, Programa/Muestras) en una sola implementación parametrizada.
- AuthZ solo en modo auditar (sin enforce); SEC-07 (sin getMessage() al usuario; catch vacíos → report($e) salvo las excepciones del CONTEXT); checklist UX-18; mismo diseño (capturas antes/después 768×1024 y 1280×800, puedes reusar los arneses de .planning/phases/19-modulos/19-0x-arnes); contraseñas y login no se tocan.

ERES DUEÑO DE: la fila 19-06b de la tercera tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: Codificación/CatCodificados/L.Mat (19-06a), Programa Tejido (salvo llamar al modelo como hoy), utils/componentes/layouts salvo catalog-actions, vite.config.*, package.json.

Acuerdos: docs y commits en español; modo ponytail (reusa http, notify, format, dom, combobox, acciones táctiles, x-ui.*, PaginacionCompat, HandlesApiErrors); ninguna optimización sin número; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con 2008 R2; antes del último push: git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main, y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; vendor/bin/pint --test en TODOS los PHP que cambies (incluidos arneses); skill code-review; con la skill run abre cada pantalla migrada (0 errores de consola) y su flujo principal.
Entregables: código + tests + 19-06b-PLAN.md + 19-06b-SUMMARY.md (+ HANDOFF.md). Push a claude/19-06b-catalogos-planeacion. NO abras PR.
```

## 14. 19-04 Tejedores

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12.69 + Livewire 4.4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, Ola 3 tercera tanda. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Tercera tanda": propiedad), CLAUDE.md (reglas "Solo TypeScript" y "ORM primero"), .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md, .planning/phases/22-calidad/22-CONTEXT.md y .planning/phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md.

Fase 19-04 — Tejedores / Desarrolladores / BPM Tejedores: JS → TS, ORM y código largo. IDs: MIG-TEJE-01..04 (+ PERF, SEC-07, UX-18 y ORM del módulo).
Contexto: fila 19-04 de 19-CONTEXT.md; HANDOFF 10-base (quitar tel-bpm/log-debug), 19-02 T3 (11 onclick de components/telares/telar-requerimiento.blade.php y los puentes // PUENTE 19-02 de resources/js/tejido/inventario-telas.ts), PT-05 B1 (MovimientoDesarrolladorService: suppress/restore de observers y scopes salon/telar). Escribe primero .planning/phases/19-modulos/19-04-PLAN.md.
Rama: claude/19-04-tejedores (base claude/friendly-hopper-506bg9).

Alcance: vistas tel-telares-operador (831), bpm-tejedores/tel-bpm (477) y tel-bpm-line (217), tejedores/notificar-mont-rollos (367) y demás de tejedores, tel-actividades-bpm (155), desarrolladores (58), notificar-montado-julios (107) → TS en resources/js/modulos/tejedores/**; BPM de Tejedores con el mismo patrón compartido que 19-01 dejó para Urdido/Engomado si encaja. Backend: InventarioTelaresController (0 % de cobertura; verificarEstado 288, updateFecha 189: tests primero), Livewire/Desarrolladores/Captura (1 164 líneas), MovimientoDesarrolladorService (N+1 ×14) y ProcesarDesarrolladorService::store (200) a Eloquent y partidos; ProcesarDesarrolladorService ↔ ProcesarMuestrasDesarrolladorService (257 líneas duplicadas) en un solo servicio con superficie Programa/Muestras; rutas de notificar (atadodejulio/notificar, cortadoderollo/notificar e insertar) en modo auditar.

Reglas de esta tanda (decisiones del owner, 2026-09-30):
- SOLO TYPESCRIPT: nada de .js nuevo ni <script> inline. Todo JS que toques termina en .ts (sin paso intermedio .js). Tests JS nuevos o migrados como tests/Js/<area>-*.test.ts (npm run test:js ya los corre). Vite: las entradas fijas se resuelven .ts primero (función entrada() de vite.config.js), así que al renombrar un .js a .ts solo cambias el @vite(...) de la vista, no el config.
- ORM PRIMERO: Eloquent (modelos, relaciones, scopes, casts) para todo acceso a datos del módulo; Query Builder con bindings solo sin modelo; AX (sqlsrv_ti) detrás de app/Integrations/Ax/<Agregado>Repository.php con DTOs readonly (créalo solo para lo que tu módulo use; si otra sesión ya creó uno, reúsalo); SQL crudo solo documentado y parametrizado; WHERE sargables (no envolver columnas con LTRIM/RTRIM/UPPER/CAST si no hay prueba de que hace falta: la collation es CI); nada de paginate()/simplePaginate()/skip()/offset() (PaginacionCompat). Cada cambio de consulta con test y número de consultas antes/después.
- CÓDIGO LARGO: en tu módulo, todo método > 100 líneas o con complejidad ≥ 10 que toques se parte (servicios/Actions/DTOs), con tests de caracterización ANTES de moverlo. PHPMD (composer quality) rechaza violaciones nuevas.
- ESTRUCTURA (.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md): controllers delgados (FormRequest → Action/Service → respuesta; meta ≤ 300 líneas por controller y métodos ≤ 50), nada de DB:: en controllers, validación en FormRequest (no $request->validate([...]) en línea), status del módulo como backed enum en app/Enums/<Mod>/ con cast en el modelo (mismo string que la BD; reusa uno compartido si ya existe), DTOs en app/Data/<Mod>/, lógica compartida entre pares (Urdido/Engomado, Programa/Muestras) en una sola implementación parametrizada.
- AuthZ solo en modo auditar (sin enforce); SEC-07 (sin getMessage() al usuario; catch vacíos → report($e) salvo las excepciones del CONTEXT); checklist UX-18; mismo diseño (capturas antes/después 768×1024 y 1280×800, puedes reusar los arneses de .planning/phases/19-modulos/19-0x-arnes); contraseñas y login no se tocan.

ERES DUEÑO DE: la fila 19-04 de la tercera tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: Urdido/Engomado (19-01, ya integrado: solo reusar), Tejido (19-02) salvo el componente telar-requerimiento y los puentes de inventario-telas.ts, Programa Tejido, utils/layouts, vite.config.*, package.json.

Acuerdos: docs y commits en español; modo ponytail (reusa http, notify, format, dom, combobox, acciones táctiles, x-ui.*, PaginacionCompat, HandlesApiErrors); ninguna optimización sin número; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con 2008 R2; antes del último push: git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main, y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; vendor/bin/pint --test en TODOS los PHP que cambies (incluidos arneses); skill code-review; con la skill run abre cada pantalla migrada (0 errores de consola) y su flujo principal.
Entregables: código + tests + 19-04-PLAN.md + 19-04-SUMMARY.md (+ HANDOFF.md). Push a claude/19-04-tejedores. NO abras PR.
```

## 15. PT-TS 1 (Programa Tejido a TS)

```
(Esta sesión arranca en modo plan: lee el protocolo y el contexto, presenta tu plan para aprobación del owner y, una vez aprobado, ejecútalo completo.)

Proyecto Towell (Laravel 12.69 + Livewire 4.4 + Vite/TS; producción Windows/Laragon, SQL Server 2008 R2). Refactor integral 2026, Ola 3 tercera tanda. Lee primero .planning/PROTOCOLO-SESIONES.md, .planning/SESIONES-OLA-3.md (sección "Tercera tanda": propiedad), CLAUDE.md (reglas "Solo TypeScript" y "ORM primero"), .planning/phases/19-modulos/19-00-RECETA.md, .planning/phases/17-ux/17-02-CHECKLIST.md, .planning/phases/22-calidad/22-CONTEXT.md y .planning/phases/22-calidad/AUDITORIA-OWNER-2026-09-29.md.

Fase PT-TS 1 — Programa Tejido: JS → TS (primera parte), mismo diseño. IDs: PT-TS-01 (+ SEC-07 y UX-18 de las pantallas tocadas).
Contexto: PT 03 no pasó el gate de Livewire (PROJECT 2026-09-29): PT sigue en Blade/TS. Lee los SUMMARY de PT 04-perf, 05 y 03. Escribe primero .planning/phases/04-ux-grid/PT-TS-1-PLAN.md.
Rama: claude/pt-ts-1 (base claude/friendly-hopper-506bg9).

Alcance: liberar-ordenes (1 902 líneas inline), planeacion/utileria/mover-ordenes (610) y finalizar-ordenes (287), planeacion/alineacion/_script (421), resources/js/programa-tejido/{balancear,lineas,recalcular-fechas,modal-cache-bootstrap}.js, modales/*.js, public/js/programa-tejido-menu.js y resources/js/modulos/redbooth/modal.js → TS. programa-tejido/index.js (13 233 líneas) NO entra aquí (va en PT-TS 2): solo lo tocas para importar lo que migres. Tests del bundle de PT (tests/Js/programa-tejido-*) a .test.ts. Liberar, Mover y Finalizar tienen tests PHP de caracterización: no deben cambiar. FinalizarOrdenesController (finalizarOrdenes 233 líneas) solo si hace falta para el front; su partición va en PT 05.1.

Reglas de esta tanda (decisiones del owner, 2026-09-30):
- SOLO TYPESCRIPT: nada de .js nuevo ni <script> inline. Todo JS que toques termina en .ts (sin paso intermedio .js). Tests JS nuevos o migrados como tests/Js/<area>-*.test.ts (npm run test:js ya los corre). Vite: las entradas fijas se resuelven .ts primero (función entrada() de vite.config.js), así que al renombrar un .js a .ts solo cambias el @vite(...) de la vista, no el config.
- ORM PRIMERO: Eloquent (modelos, relaciones, scopes, casts) para todo acceso a datos del módulo; Query Builder con bindings solo sin modelo; AX (sqlsrv_ti) detrás de app/Integrations/Ax/<Agregado>Repository.php con DTOs readonly (créalo solo para lo que tu módulo use; si otra sesión ya creó uno, reúsalo); SQL crudo solo documentado y parametrizado; WHERE sargables (no envolver columnas con LTRIM/RTRIM/UPPER/CAST si no hay prueba de que hace falta: la collation es CI); nada de paginate()/simplePaginate()/skip()/offset() (PaginacionCompat). Cada cambio de consulta con test y número de consultas antes/después.
- CÓDIGO LARGO: en tu módulo, todo método > 100 líneas o con complejidad ≥ 10 que toques se parte (servicios/Actions/DTOs), con tests de caracterización ANTES de moverlo. PHPMD (composer quality) rechaza violaciones nuevas.
- ESTRUCTURA (.planning/phases/20-arq-sec/20-04-ESTRUCTURA-BACKEND.md): controllers delgados (FormRequest → Action/Service → respuesta; meta ≤ 300 líneas por controller y métodos ≤ 50), nada de DB:: en controllers, validación en FormRequest (no $request->validate([...]) en línea), status del módulo como backed enum en app/Enums/<Mod>/ con cast en el modelo (mismo string que la BD; reusa uno compartido si ya existe), DTOs en app/Data/<Mod>/, lógica compartida entre pares (Urdido/Engomado, Programa/Muestras) en una sola implementación parametrizada.
- AuthZ solo en modo auditar (sin enforce); SEC-07 (sin getMessage() al usuario; catch vacíos → report($e) salvo las excepciones del CONTEXT); checklist UX-18; mismo diseño (capturas antes/después 768×1024 y 1280×800, puedes reusar los arneses de .planning/phases/19-modulos/19-0x-arnes); contraseñas y login no se tocan.

ERES DUEÑO DE: la fila PT-TS 1 de la tercera tanda en .planning/SESIONES-OLA-3.md.
PROHIBIDO: backend de PT salvo lo mínimo para el front, index.js salvo imports, módulos 19-xx, utils/layouts, vite.config.*, package.json.

Acuerdos: docs y commits en español; modo ponytail (reusa http, notify, format, dom, combobox, acciones táctiles, x-ui.*, PaginacionCompat, HandlesApiErrors); ninguna optimización sin número; NUNCA saltar/desactivar tests; no editar ROADMAP/STATE/REQUIREMENTS/PROJECT; el ratchet debe BAJAR; SQL compatible con 2008 R2; antes del último push: git fetch origin && git merge origin/claude/friendly-hopper-506bg9 && git merge origin/main, y vuelve a correr todo.
Antes de push: hook SessionStart (o CLAUDE_CODE_REMOTE=true bash scripts/session-start.sh); php artisan test; vendor/bin/phpstan analyse --memory-limit=2G; npm run typecheck && npm run test:js && npm run build; npm run ratchet; COMPOSER_ALLOW_SUPERUSER=1 composer quality; vendor/bin/pint --test en TODOS los PHP que cambies (incluidos arneses); skill code-review; con la skill run abre cada pantalla migrada (0 errores de consola) y su flujo principal.
Entregables: código + tests + PT-TS-1-PLAN.md + PT-TS-1-SUMMARY.md (+ HANDOFF.md). Push a claude/pt-ts-1. NO abras PR.
```
