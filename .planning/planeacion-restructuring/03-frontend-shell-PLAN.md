---
phase: planeacion-restructuring
plan: "03"
wave: 2
depends_on: ["02"]
autonomous: false
requirements: [PT-UI-01, PT-ROL-01]
files_modified:
  - resources/views/modulos/programa-tejido/v2/index.blade.php
  - resources/views/components/programa-tejido/*.blade.php
  - resources/js/programa-tejido/index.js
  - resources/js/programa-tejido/api.js
  - resources/js/programa-tejido/routes.js
  - resources/js/programa-tejido/store.js
  - resources/js/programa-tejido/columns.js
  - resources/js/programa-tejido/formatters.js
  - resources/css/programa-tejido/index.css
  - vite.config.js
must_haves:
  truths:
    - "UI v2 convive con legacy y el flag apagado no carga sus assets."
    - "No se parchea window.fetch ni se construyen rutas reemplazando texto."
    - "La UI v2 tiene una sola fuente de estado."
    - "Features pesadas no forman parte del chunk inicial."
  artifacts:
    - "Blade shell y componentes de layout/estado/toolbar/grid."
    - "Entry Vite dedicado y módulos base testeables."
    - "Route manifest generado desde el contexto del servidor."
  key_links:
    - "Blade data/config -> routes.js"
    - "api.js -> read v2 y mutaciones legacy"
    - "store.js -> render y features"
---

<objective>
Construir la carcasa v2 del módulo y su arquitectura frontend sin migrar todavía las operaciones pesadas. Debe poder activarse por usuario y volver inmediatamente al Blade legacy.
</objective>

<tasks>

<task type="auto">
<name>03.1 Definir contrato de bootstrap y route manifest</name>
<files>resources/views/modulos/programa-tejido/v2/index.blade.php, resources/js/programa-tejido/routes.js, resources/js/programa-tejido/api.js</files>
<action>El servidor entrega surface, capabilities, endpoints nombrados, CSRF, usuario y defaults en JSON seguro. `api.js` centraliza fetch, errores, abort/cancel, CSRF y parseo. Prohibir globals y reemplazos Programa→Muestras.</action>
<verify>Unit/contract tests recorren todas las capabilities y prueban que cada request usa el endpoint manifest correcto.</verify>
<done>Muestras puede abrir sin monkey patch y una feature no soportada no tiene URL ni control visible.</done>
</task>

<task type="auto">
<name>03.2 Crear shell Blade accesible</name>
<files>resources/views/modulos/programa-tejido/v2/index.blade.php, resources/views/components/programa-tejido/*.blade.php</files>
<action>Separar encabezado, toolbar, filtros, estado, grid container, selection bar y modal host en componentes presentacionales. Blade no contiene loops de 92 columnas ni lógica de operaciones.</action>
<verify>Blade render test y UAT de landmarks, labels, foco inicial y estados sin JavaScript.</verify>
<done>La vista principal describe composición, no implementación de comportamiento.</done>
</task>

<task type="auto">
<name>03.3 Entry Vite y store único</name>
<files>resources/js/programa-tejido/index.js, resources/js/programa-tejido/store.js, resources/js/programa-tejido/columns.js, resources/js/programa-tejido/formatters.js, vite.config.js</files>
<action>Crear estado normalizado para query, página, columnas, filas, selección, edición y estado de red. Usar reducer/actions explícitas o store pequeño sin framework nuevo. Renderizadores de texto escapan; badge/acción son renderers explícitos.</action>
<verify>`node --test` cubre transiciones de store, selección, normalización y formateadores; `npm run build` genera entry separado.</verify>
<done>No hay estado duplicado en DOM/índices/globals dentro de v2.</done>
</task>

<task type="auto">
<name>03.4 CSS aislado y carga dinámica</name>
<files>resources/css/programa-tejido/index.css, resources/js/programa-tejido/index.js, resources/js/programa-tejido/features/*</files>
<action>Usar namespace/componentes y tokens ya existentes; evitar selectores globales y `!important`. Definir imports dinámicos vacíos/adapters para features pesadas, manteniendo mutaciones legacy.</action>
<verify>Build report demuestra que duplicar/dividir/balancear/calendario no están en el chunk inicial; revisión visual no altera layout legacy.</verify>
<done>Los estilos v2 no contaminan otras vistas ni legacy.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>03.5 Canary shell</name>
<action>Activar v2 solo para usuario autorizado y probar Programa/Muestras, error/empty/loading, reload, back/forward y apagado del flag.</action>
<verify>Rollback por flag funciona sin limpiar cache del browser ni revertir BD.</verify>
<done>Shell autorizada para recibir la migración de grid/UX.</done>
</task>

</tasks>

<verification>
- `npm run build` y tests frontend puros.
- CSP/escaping del JSON de bootstrap.
- Browser UAT en ambas superficies.
- Flag apagado: sin requests ni assets v2.
- Health check de dominio sin cambios.
</verification>

<success_criteria>
La nueva arquitectura frontend existe y es reversible, pero todavía no reemplaza operaciones críticas ni introduce un framework de tabla nuevo.
</success_criteria>

<rollback>
Apagar `PLANEACION_PROGRAMA_UI_V2`; legacy permanece intacto. Los assets v2 no escriben datos por sí mismos.
</rollback>

