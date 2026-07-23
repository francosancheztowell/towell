---
phase: planeacion-restructuring
plan: "07"
wave: 6
depends_on: ["06"]
autonomous: false
requirements: [PT-CON-02, PT-UI-01, PT-ROL-01]
files_modified:
  - resources/views/modulos/programa-tejido/scripts/*
  - resources/views/modulos/programa-tejido/modal/*
  - public/js/programa-tejido-*.js
  - public/css/programa-tejido/*
  - routes/modules/planeacion.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/*
must_haves:
  truths:
    - "Legacy solo se retira con cero uso observado y ciclo operativo estable."
    - "Toda eliminación tiene evidencia de no uso y rollback/recovery."
    - "La consolidación de rutas conserva método, URI, nombre, middleware y action."
    - "No quedan patches globales ni assets duplicados cargados por la UI v2."
  artifacts:
    - "Matriz de adopción/telemetría y aprobación de retiro."
    - "Route registrar probado, si realmente reduce duplicación sin ocultar capacidades."
    - "Inventario de código eliminado y mecanismo de recuperación vía git."
  key_links:
    - "telemetría -> decisión de retiro"
    - "route snapshot -> route registrar"
    - "asset manifest -> eliminación legacy"
---

<objective>
Retirar duplicación y código legacy únicamente después de demostrar adopción y paridad. La limpieza no es condición para obtener el beneficio UX y no debe anticiparse.
</objective>

<tasks>

<task type="auto">
<name>07.1 Medir adopción y uso residual</name>
<files>logging/telemetry scope acordado, .planning/planeacion-restructuring/ADOPTION.md</files>
<action>Registrar versión UI/read/mutación, superficie, endpoint y feature usada sin capturar datos sensibles innecesarios. Medir usuarios legacy/v2, errores, rollbacks y endpoints/métodos aparentemente muertos durante el ciclo acordado.</action>
<verify>Dashboard/reporte distingue ausencia de llamadas de ausencia de instrumentación.</verify>
<done>Existe evidencia suficiente para cada candidato a retiro.</done>
</task>

<task type="checkpoint:decision" gate="blocking">
<name>07.2 Aprobar retiro legacy</name>
<action>Presentar lista exacta de Blade, scripts, CSS, rutas y métodos; uso observado; consumidores; recuperación y ventana de rollout.</action>
<verify>Owner aprueba elemento por elemento; “parece muerto” no es evidencia.</verify>
<done>Solo los elementos aprobados pasan a eliminación.</done>
</task>

<task type="auto">
<name>07.3 Retirar patches/globals/assets legacy</name>
<files>resources/views/modulos/programa-tejido/scripts/*, modal/*, public/js/programa-tejido-*.js, public/css/programa-tejido/*</files>
<action>Eliminar en lotes pequeños el patch `window.fetch`, globals, rebindings temporizados y assets duplicados que ya no cargue ninguna vista. No editar build generado; reconstruir con Vite.</action>
<verify>`rg` de símbolos/paths, asset manifest, build, smoke/UAT y route health. Comparar tamaño final.</verify>
<done>UI v2 no carga legacy y otros módulos no pierden dependencias compartidas.</done>
</task>

<task type="auto">
<name>07.4 Consolidar registro de rutas solo si conserva claridad</name>
<files>routes/modules/planeacion.php, tests/Feature/Planeacion/ProgramaTejidoRouteSurfaceTest.php</files>
<action>Introducir registrar/helper parametrizado por surface y capabilities. Mantener exactamente los contratos snapshots. Dejar rutas exclusivas explícitas cuando el helper reduzca legibilidad.</action>
<verify>Snapshot completo idéntico y route cache/list funciona; no se crean endpoints Muestras inexistentes.</verify>
<done>La duplicación disminuye sin ocultar diferencias de dominio.</done>
</task>

<task type="auto">
<name>07.5 Eliminar métodos muertos aprobados y cerrar documentación</name>
<files>Controllers/Services identificados, ROADMAP.md, RUNBOOK.md</files>
<action>Eliminar solo métodos sin ruta/caller/telemetría aprobados. Documentar arquitectura final, flags restantes, runbook de health check, rollback y riesgos diferidos.</action>
<verify>Static search, suite completa, route list, build, browser UAT y revisión de consumers.</verify>
<done>No quedan flags/adapters sin owner o fecha de retiro; riesgos diferidos son explícitos.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>07.6 Cierre de programa</name>
<action>UAT end-to-end con Planeación y owners downstream; revisar métricas, errores e invariantes durante el ciclo acordado.</action>
<verify>Criterios globales del ROADMAP y rollback/recovery documentados.</verify>
<done>La reestructuración se declara completa y legacy aprobado queda retirado.</done>
</task>

</tasks>

<verification>
- Suite Laravel completa relevante y frontend.
- `artisan route:list --path=planeacion --json` contra snapshot.
- `npm run build` y asset inventory.
- Health check Programa/Muestras y consumidores downstream.
- UAT end-to-end y revisión de telemetría post-deploy.
</verification>

<success_criteria>
El código eliminado estaba demostrablemente sin uso, la superficie pública se conserva, la UI nueva es la ruta estable y el sistema mantiene un runbook operable.</success_criteria>

<rollback>
Reactivar flags legacy dentro de la ventana acordada. Las eliminaciones se hacen en commits pequeños recuperables; si una ruta/caller reaparece, restaurar solo ese lote y registrar el gap de instrumentación.</rollback>

