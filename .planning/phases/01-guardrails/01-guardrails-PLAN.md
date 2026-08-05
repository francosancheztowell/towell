---
phase: planeacion-restructuring
plan: "01"
wave: 0
depends_on: []
autonomous: false
requirements: [PT-CON-01, PT-CON-02, PT-DOM-01, PT-DOM-02, PT-ROL-01]
files_modified:
  - tests/Feature/Planeacion/ProgramaTejidoRouteSurfaceTest.php
  - tests/Feature/Planeacion/ProgramaTejidoSchemaCapabilityTest.php
  - tests/Feature/Planeacion/ProgramaTejidoIsolationTest.php
  - tests/Feature/Planeacion/ProgramaTejidoInvariantTest.php
  - tests/Unit/Planeacion/ProgramaTejidoFormulaCharacterizationTest.php
  - app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php
  - config/planeacion.php
must_haves:
  truths:
    - "Toda ruta compartida o exclusiva de Programa/Muestras está inventariada con su action y contrato."
    - "Las diferencias físicas de schema se expresan como capacidades, no como supuestos."
    - "Las invariantes pueden verificarse automáticamente antes y después de una mutación."
    - "Ninguna migración pendiente se ejecuta durante esta fase."
  artifacts:
    - "Suite de caracterización de rutas, schema, aislamiento, observer y fórmulas."
    - "Comando read-only de salud para posición, EnProceso, líneas, grupos y CatCodificados."
    - "Decisión aprobada para las seis columnas y once longitudes divergentes de Muestras."
  key_links:
    - "routes/modules/planeacion.php -> snapshots de rutas"
    - "sys.columns/sys.indexes/sys.foreign_keys -> capability matrix"
    - "ReqProgramaTejidoObserver -> tests de efectos derivados"
---

<objective>
Congelar el comportamiento real de Programa Tejido y sus superficies vinculadas antes de extraer o mover código. Esta fase convierte el conocimiento implícito en contratos verificables y produce la decisión de schema/capacidades para Muestras.
</objective>

<execution_context>
Usar `laravel-specialist` para tests, factories/fixtures y command read-only. Usar `/mnt/c/xampp/php/php.exe`; SQL Server es la fuente física y no debe inferirse solo de migrations. No ejecutar escrituras en live para “probar” escenarios.
</execution_context>

<tasks>

<task type="auto">
<name>01.1 Snapshot completo de superficies HTTP</name>
<files>routes/modules/planeacion.php, routes/web.php, tests/Feature/Planeacion/ProgramaTejidoRouteSurfaceTest.php</files>
<action>Generar una matriz versionada de método, URI, nombre, middleware, controller/action, superficie y capacidad. Incluir rutas auxiliares fuera del prefijo principal, GET+POST y rutas exclusivas como Redbooth. Comparar Programa/Muestras por contrato, no solo por método.</action>
<verify>`artisan route:list --path=planeacion --json` y el test detectan alta, baja o cambio no aprobado de cualquier ruta inventariada.</verify>
<done>Las 195 rutas de Planeación tienen dueño/clasificación y cada diferencia Programa/Muestras es intencional o queda marcada como gap.</done>
</task>

<task type="auto">
<name>01.2 Matriz física de schema y migraciones</name>
<files>tests/Feature/Planeacion/ProgramaTejidoSchemaCapabilityTest.php, config/planeacion.php</files>
<action>Consultar de forma read-only `sys.columns`, `sys.indexes` y `sys.foreign_keys` para ambas cabeceras/líneas. Registrar tipos, longitudes, nullability, defaults, índices y FK; contrastar con migrations pendientes. No crear índices ni columnas. Expresar las diferencias como una matriz de capacidad por superficie.</action>
<verify>El test falla si una operación declara un campo que no existe o excede la longitud física de su superficie.</verify>
<done>Quedan documentadas las 6 columnas faltantes, 11 longitudes menores, índices/FK y todas las divergencias nuevas encontradas.</done>
</task>

<task type="checkpoint:decision" gate="blocking">
<name>01.3 Decisión Programa/Muestras</name>
<action>Presentar dos alternativas: (A) migración aditiva de paridad física con preflight/backfill, o (B) capacidades exclusivas y bloqueo explícito de operaciones incompatibles. Separar Redbooth, marbetes, producción, descarga y finalización; no exigir una sola decisión para todo.</action>
<verify>La decisión incluye impacto, rollback, datos de Muestras, owners y criterio de aceptación.</verify>
<done>El dueño aprueba por capacidad qué se alinea y qué permanece exclusivo.</done>
</task>

<task type="auto">
<name>01.4 Fixtures e aislamiento de superficies</name>
<files>tests/Feature/Planeacion/ProgramaTejidoIsolationTest.php, tests/Fixtures/Planeacion/ProgramaTejido</files>
<action>Crear fixtures mínimos representativos para Programa y Muestras en DB de prueba. Cubrir nulls, texto al límite, grupo compartido, calendario, orden activa y fila sin orden. Probar que leer/mutar una superficie no cambia la otra ni sus líneas.</action>
<verify>Tests de aislamiento pasan en transacciones y consultan ambas tablas antes/después.</verify>
<done>Muestras deja de ser un camino sin datos en la suite; escrituras cruzadas son detectables.</done>
</task>

<task type="auto">
<name>01.5 Caracterizar observer, fórmulas e invariantes</name>
<files>tests/Feature/Planeacion/ProgramaTejidoInvariantTest.php, tests/Unit/Planeacion/ProgramaTejidoFormulaCharacterizationTest.php, app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php</files>
<action>Congelar resultados actuales para posición, EnProceso, Ultimo, fechas, líneas, OrdCompartida, CatCodificados y fórmulas. Inyectar errores controlados para evidenciar catches silenciosos. El command de salud debe ser read-only, aceptar superficie y producir salida estructurada/exit code.</action>
<verify>Baseline de 69/853/0/0 se mantiene live en modo read-only; la suite reproduce los casos límite en DB de prueba.</verify>
<done>Existe un gate automático ejecutable antes/después de cada fase y se conocen los fallos silenciosos que la fase 02 debe contener.</done>
</task>

</tasks>

<verification>
- Ejecutar la suite base de 24 tests/150 assertions y toda la nueva suite Planeación.
- Ejecutar el health check read-only para Programa y Muestras.
- Revisar que `git diff -- database/migrations` esté vacío salvo una decisión aprobada posterior al checkpoint.
- Confirmar que no se modificó código funcional ni configuración live.
</verification>

<success_criteria>
No queda ninguna refactorización propuesta basada en “Programa y Muestras son iguales”. Las rutas, schema, derivados, consumidores e invariantes relevantes tienen evidencia automatizada y la decisión de capacidades está aprobada.
</success_criteria>

<rollback>
Esta fase añade pruebas/diagnóstico. Si el command genera carga excesiva, se deshabilita y se conservan las consultas como tests de integración; no hay cambios de datos que revertir.
</rollback>

