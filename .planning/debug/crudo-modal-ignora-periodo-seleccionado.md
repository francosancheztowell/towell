---
status: diagnosed
trigger: "Investiga solamente, sin modificar archivos de producción ni pruebas, el bug de Crudo donde al seleccionar un día anterior y abrir el modal de un telar, el detalle parece consultar información del día de hoy."
created: 2026-08-04T12:01:35-06:00
updated: 2026-08-04T12:05:00-06:00
---

## Current Focus

hypothesis: CONFIRMADA. El contrato de apertura no transmite el periodo; MachineDetail consulta con su estado independiente inicializado a hoy.
test: Sonda aislada completada con reloj fijo y proveedor espía; se comparó el payload real contra una fecha anterior asignada explícitamente al modal.
expecting: Confirmado: contrato real -> 2026-08-04; fecha explícita en MachineDetail -> 2026-08-03.
next_action: Entregar diagnóstico y dirección de corrección; no implementar por modo find_root_cause_only.

## Symptoms

expected: El modal debe consultar capturas/defectos exactamente con el mismo fecha/modo/rango/turno seleccionado en Dashboard.
actual: Dashboard muestra un día anterior, pero al hacer clic en un telar el modal parece mostrar datos de hoy.
errors: No hay error visible.
reproduction: En /Crudo elegir una fecha anterior en vista Día y luego abrir cualquier telar.
started: Detectado después de corregir el cierre del modal al cambiar filtros; puede ser un contrato previo no cubierto.

## Eliminated

- hypothesis: CrudoDashboardProvider::detail o su caché sustituyen el periodo solicitado por la fecha actual.
  evidence: La sonda espía registró exactamente 2026-08-03 cuando esa fecha se asignó a MachineDetail; el proveedor recibe el valor calculado por el componente y no es quien lo reemplaza.
  timestamp: 2026-08-04T12:05:00-06:00

- hypothesis: rangeFrom/rangeTo de MachineDetail ignoran la propiedad fecha en modo día.
  evidence: Con el mismo componente y mismo flujo open(), cambiar únicamente MachineDetail::fecha de 2026-08-04 a 2026-08-03 cambió ambos argumentos from/to a 2026-08-03.
  timestamp: 2026-08-04T12:05:00-06:00

## Evidence

- timestamp: 2026-08-04T12:03:35-06:00
  checked: Estado de filtros en Dashboard y binding de la vista.
  found: Dashboard declara y normaliza fecha/fechaInicio/fechaFin/modo/turno en app/Livewire/Crudo/Dashboard.php:21-96; los controles de resources/views/livewire/crudo/dashboard.blade.php:23-60 están enlazados exclusivamente a esas propiedades del componente padre.
  implication: La selección de día anterior sí actualiza el estado que alimenta el resumen del Dashboard.

- timestamp: 2026-08-04T12:03:35-06:00
  checked: Evento emitido al cambiar filtros.
  found: Dashboard::filtersChanged() en app/Livewire/Crudo/Dashboard.php:153-157 despacha crudo-filtros-cambiados sin fecha, rango, modo ni turno; MachineDetail::closeForFilterChange() en app/Livewire/Crudo/MachineDetail.php:105-109 solo cierra el modal.
  implication: El evento de filtro no sincroniza el periodo del modal.

- timestamp: 2026-08-04T12:03:35-06:00
  checked: Contrato de apertura desde TypeScript hasta MachineDetail.
  found: resources/js/crudo/dashboard.ts:933-940 despacha open-crudo-detail con detail={telar,machine}; MachineDetail::open(string $telar, array $machine) en app/Livewire/Crudo/MachineDetail.php:84-93 tampoco acepta filtros.
  implication: No existe una ruta por la que el periodo visible del Dashboard llegue al modal al abrirlo.

- timestamp: 2026-08-04T12:03:35-06:00
  checked: Construcción de argumentos para la consulta de detalle.
  found: MachineDetail posee otra copia de fecha/fechaInicio/fechaFin/modo/turno (líneas 21-34), la inicializa por separado en mount (75-81), y loadDetail pasa sus propios rangeFrom/rangeTo/turno al proveedor (189-201). En modo día rangeFrom/rangeTo usan su propia fecha (238-264).
  implication: Si la copia del modal no fue sincronizada, la fecha vacía de mount se normaliza a hoy y esa es la fecha enviada al proveedor.

- timestamp: 2026-08-04T12:05:00-06:00
  checked: Sonda PHP temporal de MachineDetail con reloj fijo en 2026-08-04 y fake de CrudoDashboardProvider que registra argumentos.
  found: Tras mount y open('201', ['programa' => null]) con el contrato vigente, fecha del modal=2026-08-04 y detail recibió from=2026-08-04, to=2026-08-04, shift=todos. En una segunda instancia, asignar solo fecha=2026-08-03 antes de open produjo from=2026-08-03 y to=2026-08-03.
  implication: El bug se reproduce en el límite MachineDetail->provider y queda aislado a la falta de propagación/sincronización del contexto temporal; la sonda fue eliminada después de ejecutarse.

## Resolution

root_cause: Dashboard y MachineDetail son componentes Livewire separados que duplican fecha/fechaInicio/fechaFin/modo/turno. Los filtros visibles solo modifican Dashboard. crudo-filtros-cambiados no lleva valores y solo cierra el modal; open-crudo-detail tampoco lleva el periodo, únicamente telar+machine. Por eso MachineDetail conserva el valor normalizado en su propio mount (hoy) y usa ese estado al llamar provider->detail.
fix: No aplicado por modo de diagnóstico. Dirección sugerida: establecer una sola fuente de verdad del periodo o transmitir explícitamente fecha/fechaInicio/fechaFin/modo/turno al contrato de apertura y asignarlos/normalizarlos en MachineDetail antes de loadDetail.
verification: Diagnóstico confirmado con traza estática completa y sonda aislada. Debe añadirse posteriormente una regresión que capture los argumentos de detail para día anterior, rango y turno.
files_changed: []
