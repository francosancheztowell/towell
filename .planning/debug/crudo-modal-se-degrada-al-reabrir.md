---
status: awaiting_human_verify
trigger: "En /Crudo, abrir y cerrar varios modales de telares progresivamente vuelve lenta o trabada la aplicación. Debe permanecer fluida aunque se abran/cerren muchos detalles y el modal exclusivo de auditoría."
created: 2026-08-04T12:43:19-06:00
updated: 2026-08-04T13:12:00-06:00
---

## Current Focus

hypothesis: El fix está verificado automáticamente y elimina ambas vías de acumulación sin cambiar el contrato del modal exclusivo.
test: Validación manual en /Crudo abriendo/cerrando al menos 15 detalles distintos y alternando varias veces al modal de auditoría.
expecting: Un solo modal visible, respuestas estables, sin congelamiento ni crecimiento perceptible del tiempo de apertura.
next_action: Esperar confirmación humana del flujo real antes de archivar la sesión.

## Symptoms

expected: Abrir/cerrar repetidamente detalles de distintos telares y alternar a auditoría mantiene tiempo de respuesta y recursos estables; solo un modal abierto.
actual: Después de abrir varios modales la aplicación empieza a trabarse.
errors: No hay error visible reportado.
reproduction: En /Crudo abrir un telar, cerrar, abrir otro y repetir varias veces; considerar también abrir el modal de auditoría y cerrar.
started: Reportado justo después de cambiar Agregar auditoría de disclosure a modal exclusivo. Había historial previo de congelamiento del modal.

## Eliminated

## Evidence

- timestamp: 2026-08-04T12:43:19-06:00
  checked: Lectura completa de MachineDetail, su Blade, dashboard.ts, helpers JS, CSS y pruebas obligatorias.
  found: dashboard.ts mantiene un único MutationObserver sobre document.body con childList/subtree y, en cada mutación con nodos agregados, ejecuta syncPendingDetail, hydrateQualityDefectEditors e hydrateAuditHistories mediante consultas globales al documento.
  implication: El observer no se duplica dentro de una sola evaluación del módulo, pero su alcance global y el trabajo por callback son candidatos directos al costo progresivo durante morphs frecuentes de Livewire.

- timestamp: 2026-08-04T12:48:30-06:00
  checked: Estado y diff local de los archivos Crudo.
  found: El workspace ya contiene cambios amplios solicitados por el usuario, incluidos el modal exclusivo, fixes de periodo/puntero y pruebas; dashboard.ts ya tenía la arquitectura de observers/listeners y el cambio exclusivo no añadió un segundo observer.
  implication: No se debe atribuir la degradación solo a duplicación del observer; hay que probar el mecanismo y preservar todas las ediciones concurrentes.

- timestamp: 2026-08-04T12:55:00-06:00
  checked: Integración Livewire y ciclo de recursos del frontend.
  found: MachineDetail está anidado una sola vez en Dashboard; el dashboard sí hace polling, pero el componente hijo es una isla persistente. Cada detalle renderiza un historial que dispara fetch; loadAuditHistory no usa AbortController ni comprueba form.isConnected antes de renderizar. El callback del observer sí corre en remociones, pero no cancela requests. Además pointerdown agrega is-closing y el CSS lo convierte en display:none antes de que wire:click close complete.
  implication: Aperturas/cierres rápidas pueden dejar varios fetches reteniendo formularios desconectados y habilitar clics sobre telares mientras el cierre anterior sigue pendiente; ambas condiciones sí explican crecimiento de solicitudes y bloqueo del pool de conexión.

- timestamp: 2026-08-04T13:01:00-06:00
  checked: Pruebas de regresión ejecutadas contra el código previo al fix.
  found: Las dos pruebas estructurales nuevas fallaron exactamente porque fetch carece de signal/cancelación y .crudo-modal-backdrop.is-closing usa display:none. La prueba JS del coordinador aún no pudo correr porque node no está en PATH de WSL.
  implication: Se reprodujeron de forma determinista los dos contratos ausentes; procede el fix enfocado.

- timestamp: 2026-08-04T13:05:00-06:00
  checked: Regresiones estructurales después del fix.
  found: CrudoPerformanceStructureTest pasa 4 pruebas y 18 assertions; confirma signal/cancelación y que is-closing ya no usa display:none.
  implication: Los dos contratos que fallaban antes del cambio quedaron satisfechos.

- timestamp: 2026-08-04T13:08:30-06:00
  checked: Pruebas JS y PHP focalizadas.
  found: Pasan 10/10 pruebas JS, incluida simulación de 50 reaperturas con una sola solicitud activa; pasan 20 pruebas PHP con 177 assertions para detalle, historial y estructura.
  implication: La cancelación es latest-only, un completion obsoleto no limpia la solicitud vigente y el contrato Livewire del modal sigue intacto.

- timestamp: 2026-08-04T13:12:00-06:00
  checked: Validación final completa.
  found: Vite compiló 107 módulos; las 64 pruebas Unit/Crudo pasan con 363 assertions; las 10 pruebas JS pasan; git diff --check no reporta errores. Se confirmó que solo existe una instancia de cada observer/timer en la evaluación del módulo, por lo que duplicación de listeners/observers queda descartada como causa acumulativa dentro del flujo.
  implication: La implementación está estable bajo simulación y regresión; resta observar el rendimiento en el navegador/entorno real.

## Resolution

root_cause: Cada apertura iniciaba un GET de historial no abortable que retenía el formulario aun desconectado; al mismo tiempo el pointerdown de cierre ocultaba el backdrop antes de la respuesta Livewire, permitiendo nuevas aperturas y acumulación de requests/DOM retenido.
fix: Coordinador AbortController latest-only para GET de historial, cancelación al desconectar el formulario y en beforeunload, guardas contra DOM desconectado, y backdrop persistente con cursor de espera durante el cierre Livewire.
verification: 10 pruebas JS; 64 pruebas Unit/Crudo con 363 assertions; npm run build exitoso (107 módulos); git diff --check limpio. Pendiente confirmación humana en navegador.
files_changed: [resources/js/crudo/audit-history-request.ts, resources/js/crudo/dashboard.ts, resources/css/crudo/dashboard.css, tests/Js/crudo-audit-history-request.test.mjs, tests/Unit/Crudo/CrudoPerformanceStructureTest.php]
