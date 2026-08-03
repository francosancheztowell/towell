---
status: resolved
trigger: "Investiga y corrige el cierre automático del desplegable de auditoría en el modal Crudo del repo /mnt/c/xampp/htdocs/Towell."
created: 2026-08-03T12:59:25-06:00
updated: 2026-08-03T13:08:32-06:00
---

## Current Focus

hypothesis: Confirmada y corregida: Livewire conserva auditExpanded durante crudo-refrescado y el frontend solo hidrata el catálogo al hacerse visible.
test: Completado con regresión Livewire, suite Crudo, lint PHP, Blade cache, TypeScript y Pint focalizado.
expecting: Cumplido; cero fallos en las validaciones ejecutadas.
next_action: Ninguna; sesión resuelta.

## Symptoms

expected: El bloque de auditoría debe permanecer abierto hasta que el usuario pulse Ocultar auditoría o cierre el modal.
actual: Se cierra solo aproximadamente 10 segundos después de abrirlo.
errors: No se reportan errores visibles.
reproduction: Abrir /Crudo, abrir detalle de un telar, pulsar Agregar auditoría y esperar el siguiente refresco automático.
started: Empezó tras implementar el desplegable de auditoría inicialmente oculto.

## Eliminated

- hypothesis: Un temporizador JavaScript cierra explícitamente la auditoría.
  evidence: dashboard.ts solo programa updateRelativeTimes cada 5 segundos; toggleAuditContent se ejecuta únicamente desde el click del botón y no existe cierre temporizado.
  timestamp: 2026-08-03T13:01:09-06:00

- hypothesis: El poll cierra el modal completo o limpia selectedTelar.
  evidence: Dashboard::refreshDashboard despacha crudo-refrescado y MachineDetail::refreshDetail solo llama loadDetail cuando selectedTelar existe; open/close son las únicas acciones que cambian selectedTelar.
  timestamp: 2026-08-03T13:01:09-06:00

## Evidence

- timestamp: 2026-08-03T13:00:20-06:00
  checked: Búsqueda inicial por textos del desplegable y mecanismos de refresco.
  found: El toggle está en resources/js/crudo/dashboard.ts y el marcado en resources/views/livewire/crudo/machine-detail.blade.php; existen pruebas focalizadas CrudoMachineDetailTest y CrudoLivewireTest.
  implication: La investigación puede acotarse al modal Crudo y sus pruebas, como requiere el encargo.

- timestamp: 2026-08-03T13:01:09-06:00
  checked: Flujo completo del refresco y render del modal.
  found: dashboard.blade.php ejecuta wire:poll.visible con config crudo.poll_seconds; Dashboard::refreshDashboard despacha crudo-refrescado; MachineDetail::refreshDetail recarga el detalle y re-renderiza. El Blade siempre emite aria-expanded=false, texto Agregar auditoría, contenido hidden y Guardar auditoría hidden.
  implication: Cada refresco vuelve a imponer el estado colapsado del HTML del servidor.

- timestamp: 2026-08-03T13:01:09-06:00
  checked: Estado y acciones en MachineDetail y dashboard.ts.
  found: MachineDetail no tiene propiedad para la apertura; dashboard.ts modifica hidden, aria-expanded, etiqueta y botón Guardar solo en el DOM al hacer click.
  implication: La causa es la falta de una fuente de verdad persistente en el componente Livewire, no la consulta SQL ni el catálogo de defectos.

- timestamp: 2026-08-03T13:02:35-06:00
  checked: Prueba de regresión test_audit_disclosure_stays_open_across_refresh_until_hidden_or_modal_closed antes del fix.
  found: Falla de forma determinista con MethodNotFoundException porque MachineDetail no expone toggleAudit; el componente no puede representar el estado que el navegador abrió.
  implication: La prueba reproduce el hueco de contrato que permite al refresh imponer nuevamente el estado colapsado.

- timestamp: 2026-08-03T13:03:28-06:00
  checked: La misma prueba de regresión después del cambio.
  found: Pasa con 10 assertions; auditExpanded sigue true tras crudo-refrescado y vuelve a false al ocultar o cerrar.
  implication: El fix aborda directamente la causa y cubre el ciclo exacto reportado.

- timestamp: 2026-08-03T13:05:06-06:00
  checked: CrudoMachineDetailTest completo después de retirar el toggle DOM duplicado.
  found: 8 tests y 77 assertions pasan. dashboard.ts observa cambios de hidden y ya no modifica apertura, etiqueta ni acción por su cuenta.
  implication: El contrato anterior del modal se conserva y Livewire queda como fuente única del estado durante los refresh.

- timestamp: 2026-08-03T13:08:32-06:00
  checked: Validación focalizada final.
  found: tests/Unit/Crudo pasa con 37 tests y 198 assertions; PHP -l pasa en componente y prueba; npx tsc --noEmit pasa; Pint --test pasa en 2 archivos; view:clear y view:cache pasan; git diff --check no reporta errores.
  implication: El fix está cubierto por regresión y no introduce fallos detectables en el módulo Crudo, Blade, PHP ni TypeScript.

## Resolution

root_cause: dashboard.ts abre el disclosure modificando solo el DOM, pero cada crudo-refrescado hace que MachineDetail cargue detalle y re-renderice un Blade que siempre declara aria-expanded=false y hidden. Al no existir estado Livewire de apertura, el morph cierra el bloque.
fix: Se añadió auditExpanded y toggleAudit a MachineDetail, se resetea al abrir/cerrar el modal y el Blade deriva aria-expanded, texto, contenido y acción Guardar de esa propiedad.
verification: Regresión aislada aprobada (1 test, 10 assertions); CrudoMachineDetailTest completo aprobado (8 tests, 77 assertions); suite tests/Unit/Crudo aprobada (37 tests, 198 assertions); PHP lint, Blade view cache, npx tsc --noEmit, Pint --test y git diff --check aprobados.
files_changed: [app/Livewire/Crudo/MachineDetail.php, resources/views/livewire/crudo/machine-detail.blade.php, resources/js/crudo/dashboard.ts, tests/Unit/Crudo/CrudoMachineDetailTest.php]
