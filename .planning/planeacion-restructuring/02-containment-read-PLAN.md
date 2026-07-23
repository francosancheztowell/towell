---
phase: planeacion-restructuring
plan: "02"
wave: 1
depends_on: ["01"]
autonomous: false
requirements: [PT-CON-01, PT-DOM-02, PT-READ-01, PT-ROL-01]
files_modified:
  - app/Support/Planeacion/ProgramaTejidoSurface.php
  - app/Support/Planeacion/ProgramaTejidoContextResolver.php
  - app/Http/Middleware/ProgramaTejidoContext.php
  - app/Http/Requests/Planeacion/IndexProgramaTejidoRequest.php
  - app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadService.php
  - app/Http/Resources/Planeacion/ProgramaTejidoRowResource.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php
  - resources/views/modulos/programa-tejido/req-programa-tejido.blade.php
  - config/planeacion.php
must_haves:
  truths:
    - "El contexto de superficie llega explícitamente a controller, vista y manifest de rutas."
    - "Los datos de BD no se imprimen como HTML salvo renderer explícito y sanitizado."
    - "Error, vacío y sin coincidencias son estados distintos."
    - "Read v2 no produce side effects y puede volver a legacy con un flag."
  artifacts:
    - "ProgramaTejidoSurface + capability matrix."
    - "Request -> ReadService -> Resource para lectura v2."
    - "Feature flags de read/UI desactivados por defecto."
  key_links:
    - "ContextResolver -> tablas/rutas/capacidades/preferencias"
    - "IndexRequest -> ReadService -> Resource"
    - "Controller -> legacy Blade o v2 por flag autorizado"
---

<objective>
Contener los P0 visibles y crear una frontera de lectura side-effect free, sin alterar todavía las mutaciones, helpers de dominio ni endpoints existentes.
</objective>

<tasks>

<task type="auto">
<name>02.1 Introducir contexto/capacidades explícitas</name>
<files>app/Support/Planeacion/ProgramaTejidoSurface.php, app/Support/Planeacion/ProgramaTejidoContextResolver.php, app/Http/Middleware/ProgramaTejidoContext.php, config/planeacion.php</files>
<action>Crear un enum/value object que resuelva superficie, tablas, route manifest, namespace de preferencias y capacidades. Mantener el middleware/config legacy como adaptador; no cambiar de golpe todos los callers. Añadir flags independientes por superficie, desactivados por default.</action>
<verify>Tests prueban resolución de Programa/Muestras para cada familia de rutas y rechazan rutas ambiguas.</verify>
<done>Ningún código nuevo decide superficie por `str_replace` o por revisar URL ad hoc.</done>
</task>

<task type="auto">
<name>02.2 Corregir contención de presentación</name>
<files>app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php, resources/views/modulos/programa-tejido/req-programa-tejido.blade.php</files>
<action>Pasar explícitamente `$surface`/`$isMuestras`, renderizar capabilities y errores, distinguir empty/error, escapar valores por defecto y permitir HTML solo mediante renderers con allowlist/sanitización. Corregir UI/rutas no soportadas por Muestras y redirects fijos identificados, sin rediseñar aún la tabla.</action>
<verify>Feature tests con strings maliciosos, error simulado y ambas superficies; el HTML no ejecuta payload ni expone acciones inválidas.</verify>
<done>Se eliminan XSS y contexto roto sin cambiar el contrato normal de lectura.</done>
</task>

<task type="auto">
<name>02.3 Implementar read v2 versionado</name>
<files>app/Http/Requests/Planeacion/IndexProgramaTejidoRequest.php, app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadService.php, app/Http/Resources/Planeacion/ProgramaTejidoRowResource.php, routes/modules/planeacion.php</files>
<action>Agregar endpoint de datos v2 preservando rutas legacy. Validar paginación, filtros, orden y proyección de columnas. Usar allowlists por superficie, query builder/repository read-only y Resource estable. Incluir meta de columnas/capacidades y claves de concurrencia/versión si existen.</action>
<verify>Contract tests comparan una matriz de filas legacy/v2 y comprueban filtros, nulls, orden, paginación 50/100, autorización y query count.</verify>
<done>El endpoint devuelve solo las filas/columnas solicitadas y nunca activa observers/escrituras.</done>
</task>

<task type="auto">
<name>02.4 Shadow comparison y observabilidad</name>
<files>app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadComparison.php, config/logging.php</files>
<action>En usuarios canary y muestreo limitado, comparar semánticamente read legacy/v2 sin duplicar escrituras. Normalizar tipos/formatos antes de comparar; registrar IDs/campos divergentes, superficie, filtros y correlation id sin datos sensibles excesivos.</action>
<verify>Test fuerza una divergencia y comprueba el log estructurado; test de equivalencia no genera ruido.</verify>
<done>Read v2 puede medirse antes de convertirse en fuente de la UI.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>02.5 Aprobar contrato read v2</name>
<action>Revisar ejemplos reales de Programa y fixtures de Muestras, tiempos, filtros, campos calculados y diferencias shadow.</action>
<verify>Cero diferencias no explicadas y rendimiento igual o mejor que legacy para el volumen de prueba.</verify>
<done>El dueño autoriza que la UI v2 consuma read v2 en canary.</done>
</task>

</tasks>

<verification>
- Suite base + planes 01/02.
- `artisan route:list` conserva nombres/URIs legacy.
- Health check sin diferencias.
- Revisión de seguridad de todos los `{!! !!}` restantes en la pantalla.
- Flag apagado sirve exactamente la vista legacy.
</verification>

<success_criteria>
Existe una API de lectura explícita y reversible; la presentación ya no confunde error con vacío ni confía en contexto/rutas implícitas. Ninguna mutación operativa fue reescrita.
</success_criteria>

<rollback>
Desactivar `PLANEACION_PROGRAMA_READ_V2`/flag por superficie. El endpoint puede permanecer sin consumidores; no hay cambios de datos.
</rollback>

