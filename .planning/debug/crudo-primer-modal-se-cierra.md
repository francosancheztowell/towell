---
status: awaiting_human_verify
trigger: "En /Crudo, al abrir por primera vez una card de telar, el modal casi siempre se cierra solo mientras terminan de cargar detalle, Flog o auditorías. Aperturas posteriores suelen permanecer abiertas."
created: 2026-08-05T10:48:07-06:00
updated: 2026-08-05T10:55:47-06:00
---

## Current Focus

hypothesis: Confirmada y corregida con comparacion normalizada del contexto entrante.
test: Verificacion humana en la primera apertura real de `/Crudo` con sesion autenticada y fuentes de datos reales.
expecting: El modal permanece abierto durante la carga de detalle, Flog y auditorias; solo cierra por X, backdrop, Escape o un cambio real de filtros.
next_action: Esperar confirmacion del usuario sobre la primera apertura real.

## Symptoms

expected: El primer modal debe permanecer abierto desde el clic hasta que el usuario cierre con X, backdrop o Escape; las cargas internas no deben cerrarlo.
actual: Durante la primera apertura, mientras carga todo, el modal se cierra sin intención del usuario; ocurre casi siempre solo la primera vez.
errors: No se reporta error visible.
reproduction: Cargar /Crudo, hacer clic por primera vez en una card de telar y esperar a que terminen el detalle, Flog y auditorías.
started: Reportado después de las optimizaciones recientes de carga/historial del modal.

## Eliminated

- hypothesis: `MachineFlogSummary::load()` emite un evento global que cierra al padre.
  evidence: El componente hijo completo no contiene `dispatch`, listeners de cierre ni mutacion del padre; solo asigna `loaded` y `summary`.
  timestamp: 2026-08-05T10:50:46-06:00

- hypothesis: El `MutationObserver` de auditorias cierra directamente el modal.
  evidence: Sus callbacks solo abortan fetch desconectados, ocultan el indicador pendiente e hidratan catalogo/historial; no llaman click, close ni dispatch de filtros.
  timestamp: 2026-08-05T10:50:46-06:00

## Evidence

- timestamp: 2026-08-05T10:48:47-06:00
  checked: Nombres de archivo que contengan Crudo, MachineDetail, MachineFlog o Dashboard.
  found: La busqueda directa no devolvio coincidencias y corto el comando encadenado antes de buscar contenido.
  implication: Debe localizarse el modulo por ruta o contenido; este resultado no demuestra ausencia de los simbolos.

- timestamp: 2026-08-05T10:49:17-06:00
  checked: Ruta `/Crudo`, clases Livewire, frontend y pruebas existentes.
  found: La ruta carga `resources/js/crudo/dashboard.ts`; existen `app/Livewire/Crudo/MachineDetail.php`, `MachineFlogSummary.php`, vistas bajo `resources/views/livewire/crudo` y pruebas JS/PHP especificas.
  implication: El flujo completo es localizable y puede cubrirse con una regresion determinista sin depender de SQL Server.

- timestamp: 2026-08-05T10:49:17-06:00
  checked: Estado Git inicial.
  found: Solo aparece sin seguimiento el archivo de sesion debug recien creado.
  implication: No hay cambios ajenos visibles que interfieran con esta investigacion.

- timestamp: 2026-08-05T10:50:08-06:00
  checked: `dashboard.ts`, `MachineDetail`, `MachineFlogSummary`, `Dashboard` y sus vistas.
  found: La apertura es `window.dispatchEvent('open-crudo-detail')`; el detalle ejecuta `wire:init=loadDetail` y el Flog anidado otro `wire:init=load`. `MachineDetail::close()` solo se llama por X/backdrop/Escape, `crudo-filtros-cambiados` o guardado de auditoria. Los MutationObserver no llaman close; hidratan historiales/catalogos y ocultan el indicador pendiente.
  implication: La investigacion debe observar/desambiguar un evento global de filtros frente a un remount del componente; la sola carga de MutationObserver no contiene un cierre directo.

- timestamp: 2026-08-05T10:50:46-06:00
  checked: Componente hijo `MachineFlogSummary` completo y listener de filtros de `MachineDetail`.
  found: El hijo solo ejecuta `wire:init=load` y no emite ningun evento global. `closeForFilterChange()` aplica valores recibidos y llama `close()` sin comprobar si el contexto cambio.
  implication: El Flog puede coincidir temporalmente con el cierre pero no lo dispara; el handler global de filtros es el emisor no humano capaz de cerrar durante la primera carga.

- timestamp: 2026-08-05T10:52:19-06:00
  checked: Regresion `test_initial_filter_context_sync_does_not_close_the_just_opened_detail` antes del fix.
  found: Falla de forma determinista: despues de abrir telar 201 y emitir el mismo contexto, `selectedTelar` es `null` en vez de `201`.
  implication: Confirma el mecanismo de cierre involuntario y proporciona una prueba automatica roja previa al cambio.

- timestamp: 2026-08-05T10:52:19-06:00
  checked: Historial Git de `closeForFilterChange`.
  found: El cierre global fue agregado el 2026-08-04 y el payload completo se incorporo despues el mismo dia, coherente con que el reporte aparezca tras las optimizaciones recientes.
  implication: El cambio temporal respalda la regresion: la sincronizacion inicial ahora tiene una ruta explicita que antes no podia cerrar el modal.

- timestamp: 2026-08-05T10:53:09-06:00
  checked: Nueva regresion despues del guard de equivalencia.
  found: Pasa con 4 aserciones; el telar 201 y el modal siguen presentes tras recibir el mismo contexto inicial.
  implication: El cambio aborda directamente el mecanismo reproducido.

- timestamp: 2026-08-05T10:53:34-06:00
  checked: `CrudoMachineDetailTest.php` completo despues del fix.
  found: 19 pruebas y 185 aserciones pasan, incluidos cierres intencionales, cambio de filtros legado, refresco, Flog y auditorias.
  implication: El guard no altera los contratos existentes del componente y la correccion se mantiene en el ciclo completo del modal.

- timestamp: 2026-08-05T10:54:06-06:00
  checked: Suite PHP completa `tests/Unit/Crudo` y contrato de ruta.
  found: 78 pruebas y 481 aserciones pasan.
  implication: Servicios, cache, dashboard, detalle, Flog, historial, rendimiento, repositorio y ruta permanecen compatibles.

- timestamp: 2026-08-05T10:54:06-06:00
  checked: Ejecucion de pruebas JS desde WSL.
  found: No se ejecutaron porque `node` no esta en el PATH de bash (exit 127).
  implication: Es un limite del shell, no un fallo de codigo; se reintentara con Node de Windows.

- timestamp: 2026-08-05T10:54:28-06:00
  checked: Cuatro archivos de pruebas JS de Crudo con Node 22.20.0 de Windows.
  found: 14 pruebas pasan, incluidas activacion intencional, reaperturas, abortos de historial y estado de formularios.
  implication: El flujo frontend adyacente y los observadores conservan sus contratos.

- timestamp: 2026-08-05T10:55:10-06:00
  checked: Laravel Pint sobre los dos archivos PHP modificados.
  found: Pasa para 2 archivos sin reportar correcciones pendientes.
  implication: El cambio cumple el formato del repositorio.

- timestamp: 2026-08-05T10:55:10-06:00
  checked: `npm run build` con Vite 6.4.3.
  found: 108 modulos transformados y build de produccion completado correctamente.
  implication: La aplicacion y el bundle Crudo siguen compilando tras el cambio.

- timestamp: 2026-08-05T10:55:47-06:00
  checked: Diff, `git diff --check` y estado Git final.
  found: Solo estan modificados `MachineDetail.php`, su prueba de regresion y el archivo debug; no hay errores de whitespace ni artefactos versionados del build.
  implication: El arreglo es minimo, focalizado y no pisa trabajo ajeno.

- timestamp: 2026-08-05T10:55:47-06:00
  checked: Regresion final de sincronizacion equivalente y cierre por filtros despues de Pint/build.
  found: 2 pruebas y 11 aserciones pasan.
  implication: El codigo final preserva ambos lados del contrato: no cerrar en sync inicial y si cerrar ante invalidacion real/legada.

## Resolution

root_cause: `MachineDetail::closeForFilterChange()` llama `close()` para cualquier `crudo-filtros-cambiados`, incluso cuando el payload solo resincroniza el mismo periodo y turno durante la primera hidratacion; esto borra `selectedTelar` mientras cargan detalle/Flog/auditorias.
fix: `closeForFilterChange()` normaliza primero el contexto recibido, actualiza el contexto local y evita `close()` cuando el payload es una resincronizacion equivalente; cambios reales y eventos sin payload conservan el cierre.
verification: Regresion roja antes del fix y verde despues; 19 pruebas/185 aserciones del modal, 78/481 del modulo PHP, 14 JS, Pint (2 archivos), Vite build (108 modulos), diff-check y regresion final (2/11) pasan. Pendiente confirmacion humana en `/Crudo` autenticado.
files_changed: [app/Livewire/Crudo/MachineDetail.php, tests/Unit/Crudo/CrudoMachineDetailTest.php]
