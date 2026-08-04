---
status: resolved
trigger: "Investiga solamente, sin modificar archivos de producción ni pruebas, el bug de Crudo donde al seleccionar un día anterior y abrir el modal de un telar, el detalle parece consultar información del día de hoy."
created: 2026-08-04T12:01:35-06:00
updated: 2026-08-04T16:25:00-06:00
---

## Current Focus

hypothesis: CONFIRMADA. Los síntomas de periodo son el mismo defecto de contrato: Dashboard sí consulta la fecha elegida, pero MachineDetail no recibe cambios de fecha/rango/turno y consulta con su copia anterior. weavingOrder sí cruza repository->service->Blade cuando ORDENTEJIDO existe; el faltante restante del origen se atiende concurrentemente con backfill por PRODID fuera de estos archivos.
test: Implementar sincronización PHP mediante payload completo de crudo-filtros-cambiados y probar dos transiciones (hoy->ayer y ayer->hoy), rango invertido/normalizado y turno, registrando argumentos reales enviados a provider->detail.
expecting: La siguiente apertura del modal debe llamar detail con exactamente el periodo/turno recién seleccionados; el test existente debe seguir viendo weavingOrder.
next_action: Aplicar el cambio mínimo en Dashboard.php y MachineDetail.php y añadir pruebas de regresión focalizadas preservando cambios ajenos.

## Symptoms

expected: El modal debe consultar capturas/defectos exactamente con el mismo fecha/modo/rango/turno seleccionado en Dashboard.
actual: Dashboard muestra un día anterior, pero al hacer clic en un telar el modal parece mostrar datos de hoy.
errors: No hay error visible.
reproduction: En /Crudo elegir una fecha anterior en vista Día y luego abrir cualquier telar.
started: Detectado después de corregir el cierre del modal al cambiar filtros; puede ser un contrato previo no cubierto.

## Additional Symptoms

- timestamp: 2026-08-04T12:08:00-06:00
  expected: Al seleccionar hoy, Dashboard debe mostrar los registros existentes en TWCRUDOTABLE; la columna Orden debe mostrar ORDENTEJIDO.
  actual: Hoy aparece sin datos aunque existen registros SQL; Orden se muestra vacía aunque ORDENTEJIDO tiene valor.
  reproduction: En /Crudo seleccionar el día actual y revisar el tablero/columna Orden.
  source: Confirmación directa del usuario al reanudar la sesión; el repositorio recibió cambios posteriores al diagnóstico inicial.

## Eliminated

- hypothesis: allowRebuild=false hace que la primera carga de hoy devuelva vacío aun existiendo datos en origen.
  evidence: CachedCrudoDashboardProvider::get solo sirve stale sin reconstruir cuando ya hay un array cacheado; en cache miss continúa al lock y rebuild (líneas 42-66). La prueba existente también documenta la reconstrucción forzada, aunque falta una de cache miss explícita.
  timestamp: 2026-08-04T12:15:00-06:00

- hypothesis: La consulta del repositorio excluye las filas reales de hoy por TRANSDATE o DATAAREAID.
  evidence: Sonda real encontró 90 filas entre 2026-08-04 00:00 y 2026-08-05 00:00, todas con DATAAREAID=pro; headersForRange devolvió 90 y aggregateHeadersForRange 36 telares.
  timestamp: 2026-08-04T12:19:00-06:00

- hypothesis: El driver SQL Server cambia el case de ORDENTEJIDO y por eso $header->ORDENTEJIDO no existe.
  evidence: Tanto la consulta directa como headersForRange devolvieron la propiedad exacta ORDENTEJIDO en array_keys(get_object_vars($row)).
  timestamp: 2026-08-04T12:19:00-06:00

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

- timestamp: 2026-08-04T12:08:00-06:00
  checked: Síntomas informados al reanudar.
  found: Además del periodo del modal, hoy no produce datos pese a filas en TWCRUDOTABLE y weavingOrder aparece vacío pese a ORDENTEJIDO poblado.
  implication: La corrección debe cubrir dos contratos adicionales y confirmar si la desincronización previa sigue vigente en el código actual.

- timestamp: 2026-08-04T12:11:00-06:00
  checked: Estado actual y diff de los archivos autorizados después de cambios posteriores.
  found: Dashboard.php y MachineDetail.php no tienen diff local; CrudoLivewireTest.php sí contiene un cambio ajeno de CSS que debe preservarse. En el código actual Dashboard sigue despachando crudo-filtros-cambiados sin payload y MachineDetail::open sigue aceptando solo telar+machine; el detalle ahora es computed, pero rangeFrom/rangeTo/turno aún salen del estado independiente del modal.
  implication: La causa previa sigue vigente pese al refactor del detalle; cualquier cambio de pruebas debe evitar pisar el bloque CSS ajeno.

- timestamp: 2026-08-04T12:11:00-06:00
  checked: Entrada inicial del Dashboard al proveedor.
  found: allowSynchronousRebuildOnNextRender inicia false, no se activa en mount, y render pasa ese false a get; los filtros tampoco lo activan.
  implication: La semántica de cache miss/stale con allowRebuild=false es candidata específica para explicar por qué hoy queda vacío.

- timestamp: 2026-08-04T12:15:00-06:00
  checked: CachedCrudoDashboardProvider::get completo.
  found: Un snapshot fresco se sirve; un snapshot stale con allowRebuild=false se sirve y agenda rebuild; si no hay snapshot se adquiere lock y se construye sincrónicamente. No fabrica un snapshot vacío por allowRebuild=false.
  implication: El flag no explica por sí solo la ausencia de filas de hoy; se elimina H1 y se debe observar el origen real o un snapshot previamente cacheado.

- timestamp: 2026-08-04T12:15:00-06:00
  checked: SqlServerCrudoReadRepository y CrudoDashboardService.
  found: Las consultas usan intervalo semiabierto [00:00 del from, 00:00 del día posterior a to), filtran DATAAREAID=config(crudo.data_area_id), seleccionan ORDENTEJIDO y el mapper asigna weavingOrder=trim(header->ORDENTEJIDO). Las pruebas SQLite/fake verifican fecha y valor 36541.
  implication: El contrato nominal es correcto; falta comprobar las propiedades/valores producidos por el driver SQL Server y si la caché contiene un payload anterior al campo weavingOrder.

- timestamp: 2026-08-04T12:19:00-06:00
  checked: Sonda read-only contra sqlsrv_ti/dbo.TWCRUDOTABLE para 2026-08-04.
  found: Consulta directa=90 filas, repository headers=90, aggregate=36 telares. El snapshot cacheado del Dashboard tiene 39 máquinas y suma captureCount=90. Las propiedades reales incluyen ORDENTEJIDO con el case esperado. Las cinco filas más recientes y la primera fila ordenada por telar tenían ORDENTEJIDO vacío.
  implication: El backend y caché del Dashboard sí contienen la producción de hoy; la falta visible no se debe a no leer TWCRUDOTABLE. Para Orden debe trazarse una fila explícitamente no vacía, no asumir que cualquier captura de hoy tiene el campo.

- timestamp: 2026-08-04T12:23:00-06:00
  checked: Sonda read-only enfocada en ORDENTEJIDO no vacío y render end-to-end.
  found: De 90 capturas de hoy, 8 tenían ORDENTEJIDO poblado antes del backfill concurrente. La captura RECID 5637970725/telar 402 llevaba M3204; CrudoDashboardService produjo weavingOrder=M3204 con la clave esperada y el fallback cacheado también conservó M3204. La prueba de MachineDetail ya afirma que el Blade muestra el valor 36541.
  implication: No hay pérdida de weavingOrder en MachineDetail ni en el render. El vacío mayoritario era dato de origen (82/90), no el alias. La sonda temporal fue eliminada.

- timestamp: 2026-08-04T12:23:00-06:00
  checked: Coordinación con cambio concurrente del repositorio.
  found: Otro agente implementa backfill por PRODID en lote y reporta recuperación de 70 de las 82 capturas cuyo ORDENTEJIDO estaba vacío; este agente no tocará repository.
  implication: La responsabilidad local queda acotada a sincronizar filtros Dashboard->MachineDetail y verificar el render ya cubierto.

## Resolution

root_cause: Dashboard y MachineDetail duplicaban fecha/fechaInicio/fechaFin/modo/turno. Dashboard cambiaba y consultaba correctamente su periodo, pero crudo-filtros-cambiados no transmitía los valores; MachineDetail conservaba su copia previa. Además, 82 de 90 capturas de hoy tenían ORDENTEJIDO vacío en origen aunque 70 podían resolverse desde una captura previa del mismo PRODID.
fix: Dashboard transmite el contexto completo al cambiar filtros y MachineDetail lo normaliza antes de cerrar. SqlServerCrudoReadRepository recupera en una consulta por lote el ORDENTEJIDO más reciente del mismo PRODID sin sobrescribir valores actuales.
verification: 71 pruebas Unit/Crudo y 418 aserciones aprobadas; Pint aprobó los seis archivos PHP modificados; Blade se recompiló; la consulta real de hoy recuperó 36514 para el telar 213.
files_changed: [app/Livewire/Crudo/Dashboard.php, app/Livewire/Crudo/MachineDetail.php, app/Repositories/Crudo/SqlServerCrudoReadRepository.php, tests/Unit/Crudo/CrudoLivewireTest.php, tests/Unit/Crudo/CrudoMachineDetailTest.php, tests/Unit/Crudo/SqlServerCrudoReadRepositoryTest.php]
