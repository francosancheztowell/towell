---
status: resolved
trigger: "Investiga y corrige dos regresiones relacionadas en Crudo: al cambiar el periodo/filtros se abre automáticamente el modal de detalle, y Auditorías de hoy muestra una auditoría aunque no corresponda al telar ni al día actual."
created: 2026-08-04T11:27:51-06:00
updated: 2026-08-04T11:45:00-06:00
---

## Current Focus

hypothesis: Confirmada y corregida: el modal tenía estado retenido/click sin intención, y el historial conservaba cache/respuestas sin identidad de telar y fecha.
test: Completado con suite Crudo, pruebas JS, TypeScript, Vite, PHP, Blade, Pint y diff check.
expecting: Cumplido; cero fallos en todas las validaciones ejecutadas.
next_action: Ninguna; sesión resuelta.

## Symptoms

expected: Cambiar periodo/fecha/modo/turno solo refresca el dashboard y nunca abre un detalle. El historial debe contener exclusivamente auditorías de la fecha de hoy y del NoTelarId del modal abierto.
actual: Al cambiar el periodo se abre un modal automáticamente. El historial conserva/muestra una auditoría creada para otro telar o día.
errors: No se reporta error visible.
reproduction: Abrir /Crudo, cambiar periodo; luego abrir distintos telares y observar Auditorías de hoy.
started: Detectado después de mover el historial fuera del disclosure y cargarlo aun con el formulario colapsado.

## Eliminated

- hypothesis: CrudoAuditService devuelve auditorías de otros telares o días por una consulta SQL demasiado amplia.
  evidence: todayForMachine aplica where NoTelarId exacto y Fecha >= inicio de hoy, Fecha < inicio de mañana en app.timezone. En BD real solo existe Id=1, Fecha 2026-08-04 09:08, NoTelarId=204.
  timestamp: 2026-08-04T11:34:18-06:00

- hypothesis: Un temporizador o el overlay pendiente despacha open-crudo-detail por sí solo.
  evidence: El único dispatch de open-crudo-detail está dentro del listener document click cuando target.closest(data-crudo-machine) produce una tarjeta; el timer únicamente oculta el overlay.
  timestamp: 2026-08-04T11:34:18-06:00

## Evidence

- timestamp: 2026-08-04T11:30:04-06:00
  checked: Búsqueda inicial de apertura de modal e historial.
  found: open-crudo-detail se despacha desde dashboard.ts y se consume en MachineDetail; además existe resources/js/crudo/pending-detail.ts. El historial usa una URL por telar en el Blade y cachea estado en data-audit-history-state dentro de dashboard.ts.
  implication: Ambas regresiones pueden originarse en estado de navegador que sobrevive un morph, por lo que hay que verificar tanto el emisor pendiente como el wire:ignore del formulario.

- timestamp: 2026-08-04T11:30:04-06:00
  checked: Estado del worktree acotado a Crudo.
  found: dashboard.ts, machine-detail.blade.php, CrudoAuditHistoryResourceTest y CrudoMachineDetailTest ya contienen cambios ajenos/no confirmados; el trabajo debe preservar esos hunks y modificar solo la causa demostrada.
  implication: Se requiere revisar diff y contenido actual antes de editar para no revertir trabajo concurrente.

- timestamp: 2026-08-04T11:34:18-06:00
  checked: Emisor frontend de open-crudo-detail.
  found: El listener delegado acepta cualquier click cuyo target final esté dentro de data-crudo-machine; no conserva ni compara el telar del pointerdown. Los filtros Livewire cambian el layout por morph antes de que termine el gesto.
  implication: Si el click final cae sobre una tarjeta después del morph, se abre aunque el gesto se originó en un filtro. Se necesita una compuerta de intención que preserve activación por teclado.

- timestamp: 2026-08-04T11:34:18-06:00
  checked: Carga del historial y límites wire:ignore.
  found: loadAuditHistory retorna al ver auditHistoryState loading/loaded sin comprobar para qué URL fue ese estado; tampoco verifica que la URL siga vigente al resolver fetch. La sección de historial es wire:ignore y el formulario no tiene wire:key por telar.
  implication: El contenido/estado del telar anterior puede sobrevivir al morph y una respuesta vieja puede renderizar sobre el formulario del telar nuevo.

- timestamp: 2026-08-04T11:37:06-06:00
  checked: Pruebas de regresión antes del fix.
  found: Node falla por ausencia de isIntentionalMachineActivation y audit-history-state.ts; CrudoLivewireTest no observa crudo-filtros-cambiados; CrudoMachineDetailTest reporta EventHandlerDoesNotExist; el contrato Blade falla por ausencia de wire:key por telar.
  implication: Los cuatro huecos están reproducidos de forma determinista antes de modificar producción.

- timestamp: 2026-08-04T11:40:15-06:00
  checked: Las mismas pruebas después del fix.
  found: 6 tests Node pasan; el cierre de filtros pasa con 5 assertions; el reset de MachineDetail pasa con 7 assertions; el contrato de historial keyed pasa con 19 assertions.
  implication: El cambio corrige directamente los mecanismos reproducidos y conserva activación por teclado.

- timestamp: 2026-08-04T11:42:02-06:00
  checked: Primera validación completa.
  found: Suite Crudo pasa 62 tests/351 assertions, Node pasa 6 tests, PHP lint y Pint pasan. tsc falla porque TypeScript 7 tipa HTMLElement.hidden como string|boolean y dataset como string|undefined, mientras los helpers aceptaban boolean y un union cerrado.
  implication: Es un defecto de tipos localizado en código nuevo; la lógica funcional no falló y no requiere cambiar el diseño.

- timestamp: 2026-08-04T11:45:00-06:00
  checked: Validación final después del ajuste de tipos.
  found: tsc --noEmit pasa, 6 tests JS pasan, npm run build termina correctamente (106 módulos, 32.20 s), Blade clear/cache pasa y git diff --check no reporta errores. La suite Crudo ya había pasado 62 tests/351 assertions, con PHP lint y Pint aprobados.
  implication: La corrección está cubierta de extremo a extremo dentro del entorno automatizable; no quedó proceso colgado ni error de compilación.

## Resolution

root_cause: Dos mecanismos frontend. (1) MachineDetail conserva selectedTelar al cambiar filtros y el click delegado puede abrir una tarjeta sin demostrar que el gesto comenzó allí. (2) El historial wire:ignore cachea solo loading/loaded, no la URL; además una respuesta en vuelo puede pintar tras cambiar de telar y no se valida telar/fecha antes de renderizar.
fix: Dashboard despacha crudo-filtros-cambiados en sus cinco filtros y MachineDetail cierra/resetear; dashboard.ts exige pointer/click del mismo telar salvo activación por teclado. El formulario tiene wire:key por telar; el historial asocia estado a URL, ignora respuestas obsoletas y filtra cada item por NoTelarId y meta.fecha de hoy.
verification: Suite Crudo 62 tests/351 assertions; Node 6 tests; tsc --noEmit; npm run build; PHP lint; Blade view:clear/view:cache; Pint --test y git diff --check, todos aprobados.
files_changed: [app/Livewire/Crudo/Dashboard.php, app/Livewire/Crudo/MachineDetail.php, resources/views/livewire/crudo/machine-detail.blade.php, resources/js/crudo/dashboard.ts, resources/js/crudo/pending-detail.ts, resources/js/crudo/audit-history-state.ts, tests/Unit/Crudo/CrudoLivewireTest.php, tests/Unit/Crudo/CrudoMachineDetailTest.php, tests/Unit/Crudo/CrudoAuditHistoryResourceTest.php, tests/Js/crudo-pending-detail.test.mjs, tests/Js/crudo-audit-history-state.test.mjs]
