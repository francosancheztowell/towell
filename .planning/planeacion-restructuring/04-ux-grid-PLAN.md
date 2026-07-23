---
phase: planeacion-restructuring
plan: "04"
wave: 3
depends_on: ["03"]
autonomous: false
requirements: [PT-UI-02, PT-READ-01, PT-ROL-01]
files_modified:
  - resources/js/programa-tejido/grid/*
  - resources/js/programa-tejido/features/columns/*
  - resources/js/programa-tejido/features/filters/*
  - resources/js/programa-tejido/features/selection/*
  - resources/js/programa-tejido/features/inline-edit/*
  - resources/views/components/programa-tejido/*.blade.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ColumnasProgramaTejidoController.php
must_haves:
  truths:
    - "La tabla inicial no renderiza 92 columnas ni todas las filas."
    - "Las 460 preferencias existentes se conservan."
    - "Programa y Muestras no contaminan preferencias entre sí."
    - "Toda acción de contexto tiene alternativa visible y accesible."
  artifacts:
    - "Grid paginado con selección, filtros, columnas y estados explícitos."
    - "Presets de 12-16 columnas para usuarios sin preferencias."
    - "Migración/adaptador no destructivo de preferencias."
  key_links:
    - "grid query -> read v2 pagination/projection"
    - "column preference namespace -> surface + user"
    - "row/selection actions -> capability manifest"
---

<objective>
Entregar la mejora UX principal sobre la arquitectura v2: tabla rápida, comprensible, accesible y configurable sin romper las preferencias o mutaciones existentes.
</objective>

<tasks>

<task type="auto">
<name>04.1 Caracterizar y adaptar preferencias</name>
<files>app/Http/Controllers/Planeacion/ProgramaTejido/ColumnasProgramaTejidoController.php, resources/js/programa-tejido/features/columns/*</files>
<action>Resolver la semántica real de `Estado` y localStorage/servidor. Añadir namespace de superficie de manera compatible. Para usuarios con preferencias existentes, conservar orden/visibilidad; para usuarios nuevos, aplicar preset aprobado. No truncar las 460 filas actuales.</action>
<verify>Fixtures cubren usuario existente, nuevo, Programa y Muestras; round-trip no invierte visible/oculta.</verify>
<done>Preferencias sobreviven al opt-in/opt-out y no cruzan superficies.</done>
</task>

<task type="checkpoint:decision" gate="blocking">
<name>04.2 Aprobar presets semánticos</name>
<action>Presentar Operación, Planeación, Materiales y Comercial con 12–16 columnas iniciales, grupos y columnas sticky. Validar con usuarios del proceso.</action>
<verify>Cada preset cubre las tareas diarias sin volver a las 92 columnas por default.</verify>
<done>Lista/orden/default aprobados antes de habilitar globalmente.</done>
</task>

<task type="auto">
<name>04.3 Grid paginado y proyectado</name>
<files>resources/js/programa-tejido/grid/*, resources/js/programa-tejido/store.js</files>
<action>Implementar paginación 50/100, sort/filter server-side, proyección según columnas y abort de requests obsoletos. Definir selección solo página vs conjunto filtrado de forma explícita; no asumir que checkboxes DOM son el estado.</action>
<verify>Tests de carreras de requests, cambio de página, selección y filtros; medición de celdas/HTML/request.</verify>
<done>La carga inicial deja de crear miles de celdas y los resultados no saltan por respuestas fuera de orden.</done>
</task>

<task type="auto">
<name>04.4 UX de filtros, acciones y estados</name>
<files>resources/js/programa-tejido/features/filters/*, resources/js/programa-tejido/features/selection/*, resources/views/components/programa-tejido/*.blade.php</files>
<action>Agrupar toolbar por intención; chips de filtros activos/reset; action bar con selección; menú visible `…` por fila; clic derecho solo como atajo. Añadir loading skeleton, error con reintento, vacío real y sin coincidencias.</action>
<verify>Browser UAT con mouse/teclado, lector semántico básico y fallas HTTP inyectadas.</verify>
<done>Las acciones críticas son descubribles y cada estado explica causa/siguiente acción.</done>
</task>

<task type="auto">
<name>04.5 Inline edit adapter</name>
<files>resources/js/programa-tejido/features/inline-edit/*</files>
<action>Conectar inicialmente al endpoint legacy mediante `api.js`. Mostrar pendiente/guardando/guardado/error/conflicto; rollback visual al valor confirmado. No mover fórmulas al browser ni asumir éxito antes de respuesta.</action>
<verify>Tests de success, validation 422, conflicto, timeout/retry y respuesta con derivados actualizados.</verify>
<done>La edición tiene feedback y consistencia sin cambiar aún el backend de la mutación.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>04.6 UAT operativo</name>
<action>Usuarios canary realizan búsqueda, filtros, columnas, selección, edición y recuperación de errores en Programa/Muestras.</action>
<verify>Métricas mejores que legacy y cero diferencias en health check/preferencias.</verify>
<done>Grid v2 queda aprobado antes de extraer mutaciones.</done>
</task>

</tasks>

<verification>
- Suite frontend y Feature tests de preferencias/read.
- `npm run build` con budget de chunk documentado.
- Health check antes/después de edición controlada.
- Browser UAT y prueba de flag rollback.
- Verificar que legacy conserva las 460 preferencias sin reinterpretación.</verification>

<success_criteria>
La pantalla ofrece una UX notablemente más rápida y legible con compatibilidad de datos/preferencias, y las mutaciones de dominio siguen usando contratos legacy conocidos.</success_criteria>

<rollback>
Apagar UI v2. Las preferencias nuevas deben estar versionadas/namespaced para que legacy ignore lo que no comprenda y conserve sus datos.</rollback>

