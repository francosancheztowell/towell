---
phase: planeacion-restructuring
plan: "06"
wave: 5
depends_on: ["05"]
autonomous: false
requirements: [PT-MUT-01, PT-OPS-01, PT-DOM-01, PT-DOM-02, PT-ROL-01]
files_modified:
  - app/Actions/Planeacion/ProgramaTejido/Sequence/*
  - app/Actions/Planeacion/ProgramaTejido/SharedOrders/*
  - app/Actions/Planeacion/ProgramaTejido/Balance/*
  - app/Actions/Planeacion/ProgramaTejido/Release/*
  - app/Actions/Planeacion/ProgramaTejido/Finalize/*
  - app/Services/Planeacion/ProgramaTejido/Integrations/*
  - app/Http/Controllers/Planeacion/ProgramaTejido/*
  - app/Http/Controllers/Planeacion/Utileria/*
must_haves:
  truths:
    - "Cada boundary operativo se implementa y despliega en un slice independiente."
    - "Orden/estado/fecha/líneas/grupo/CatCodificados permanecen atómicos según contrato."
    - "Muestras conserva su lifecycle propio; compartir cálculos no implica compartir orquestación."
    - "AX/TI, UNC y Redbooth no se ejecutan en validaciones locales destructivas."
  artifacts:
    - "Actions de secuencia, grupos y balance con locks/idempotencia definidos."
    - "Planes secundarios independientes para liberar, finalizar, codificación, imports/exports e integraciones."
    - "Gates/flags por subfamilia."
  key_links:
    - "Sequence action -> DateHelpers/line regeneration adapter"
    - "Shared order action -> OrdCompartidaHelper adapter"
    - "Boundary action -> CatCodificados/integration port"
---

<objective>
Migrar las operaciones de alto riesgo solo después de estabilizar lectura, UX y mutaciones simples. Este plan es un marco de ejecución: cada subfase requiere PR, suite, UAT y aprobación propios.
</objective>

<tasks>

<task type="auto">
<name>06A Secuencia y movimientos</name>
<files>app/Actions/Planeacion/ProgramaTejido/Sequence/*, ProgramaTejidoOperacionesController.php, app/Services/Planeacion/ProgramaTejido/draganddroptejido.php, app/Http/Controllers/Planeacion/Utileria/MoverOrdenesController.php</files>
<action>Extraer mover posición, drag/drop, cambio de telar y Mover Órdenes. Definir locks y orden estable `(Posicion, NoTelarId/Id)`. Recalcular origen/destino, EnProceso, Ultimo, fechas y líneas dentro del boundary probado. Conservar filas sin NoProduccion y no tocar FechaFinaliza.</action>
<verify>Concurrency/rollback tests, fixtures con dos telares, filas sin orden y health check completo.</verify>
<done>Flag `sequence_v2` pasa un ciclo operativo y vuelve a legacy sin reparación de datos.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>06A Gate de secuencia</name>
<action>Validar Gantt/preview, colas origen/destino, orden activa y filas sin orden con usuarios de Planeación.</action>
<verify>Cero diferencia en posición/EnProceso/fechas/líneas y rollback probado.</verify>
<done>Autoriza empezar grupos.</done>
</task>

<task type="auto">
<name>06B Órdenes compartidas</name>
<files>app/Actions/Planeacion/ProgramaTejido/SharedOrders/*, app/Helpers/OrdCompartidaHelper.php, servicios Duplicar/Dividir/Vincular</files>
<action>Separar duplicate, split, link, unlink y liderazgo. Mantener adaptador `OrdCompartidaHelper`; preservar identidad, saldos, TotalRollos/TotalPzas y orden. Probar fallas a mitad de operación.</action>
<verify>Property/invariant tests para grupos de varios tamaños, líder eliminado/movido y rollback.</verify>
<done>Flag `groups_v2` estable sin huérfanos ni saldos divergentes.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>06B Gate de grupos</name>
<action>UAT duplicar/dividir/vincular/desvincular y confirmar datos downstream.</action>
<verify>Resultados coinciden con contratos caracterizados y health check.</verify>
<done>Autoriza balanceo.</done>
</task>

<task type="auto">
<name>06C Balanceo</name>
<files>app/Actions/Planeacion/ProgramaTejido/Balance/*, app/Services/Planeacion/ProgramaTejido/BalancearTejido.php, ProgramaTejidoBalanceoController.php</files>
<action>Extraer preview y apply; la suma/cierre frente al objetivo se concentra en el último registro del grupo según orden definido. Mantener separación manual/automático y fechas. No mezclar cambios visuales del modal con algoritmo.</action>
<verify>Golden tests de escenarios reales, suma exacta, negativos/límites, preview=apply e invariantes.</verify>
<done>Flag `balance_v2` aprobado y resultado matemático/temporal idéntico.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>06C Gate de balanceo</name>
<action>UAT con grupos reales anonimizados y preview Gantt/fechas.</action>
<verify>Cero diferencia no aprobada y rollback probado.</verify>
<done>Autoriza planear boundaries externos.</done>
</task>

<task type="checkpoint:decision" gate="blocking">
<name>06D Priorizar boundaries operativos</name>
<action>Crear un plan secundario por: Liberar; Finalizar; Codificación/Revivir; Desarrolladores Programa; Desarrolladores Muestras; Imports/command; export UNC; AX/TI; Redbooth. Ordenar por valor/riesgo. No ejecutar todos por defecto.</action>
<verify>Cada plan declara tablas, transacción, API externa, compensación, stubs, UAT y owner.</verify>
<done>El dueño elige una familia; las demás permanecen legacy y documentadas.</done>
</task>

<task type="auto">
<name>06E Ejecutar una boundary autorizada</name>
<files>Solo los archivos declarados por el plan secundario aprobado</files>
<action>Usar ports/adapters para CatCodificados, ReqModelosCodificados, AX/TI, UNC o Redbooth. Mantener respuestas/rutas. Para Muestras aplicar lifecycle/capabilities propias, no copiar orquestación de Programa.</action>
<verify>Stubs/ambiente controlado, contract tests, fallas parciales, idempotencia/compensación y UAT del owner.</verify>
<done>Solo la boundary autorizada queda en v2; las demás siguen intactas.</done>
</task>

</tasks>

<verification>
- Ejecutar gates por subfase, nunca un único “test final”.
- Health check antes/después y snapshots de tablas afectadas.
- Verificar locks/concurrencia y rollback de transacción.
- Integraciones externas solo con stubs o ambiente autorizado.
- Feature flag independiente y apagado probado por boundary.
</verification>

<success_criteria>
Las operaciones de alto riesgo migradas tienen boundaries explícitos y reversibles. Ningún refactor visual altera indirectamente liberar, finalizar, secuencia, grupos o integraciones.
</success_criteria>

<rollback>
Apagar el flag de la subfamilia. Si una operación externa no es transaccional, ejecutar la compensación definida por su plan secundario y detener el rollout; nunca confiar en rollback DB para revertir efectos externos.</rollback>

