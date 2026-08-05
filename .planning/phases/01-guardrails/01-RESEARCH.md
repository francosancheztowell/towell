# Investigación y mapa de impacto

## Resumen ejecutivo

La pantalla no es grande solamente por el Blade principal. Su carga inicial compone cerca de 17,000 líneas entre Blade, JavaScript y CSS. El problema central es la ausencia de fronteras: datos, renderizado, estado, rutas, fórmulas, operaciones y compatibilidad Programa/Muestras están acoplados mediante HTML, globals y configuración mutable.

El refactor visual solo es seguro después de congelar contratos. Hoy una escritura puede activar observer, regenerar líneas, actualizar CatCodificados, reordenar telar o recalcular fechas. Además, Programa y Muestras comparten código, pero sus esquemas no son equivalentes.

## Línea base live verificada

| Medición | Resultado |
|---|---:|
| Rutas bajo `planeacion` | 195 |
| `ReqProgramaTejido` | 69 filas |
| `ReqProgramaTejidoLine` | 853 filas |
| `MuestrasPrograma` | 0 filas |
| `MuestrasProgramaLine` | 0 filas |
| `CatCodificados` | 11,861 filas |
| Posiciones duplicadas | 0 |
| Telares con más de un `EnProceso` | 0 |
| Posiciones nulas | 0 |
| Líneas huérfanas | 0 |
| Programas sin líneas | 0 |

Esta línea base es saludable y debe convertirse en un gate repetible. El hecho de que Muestras esté vacío impide declarar paridad funcional: se necesitan fixtures representativos antes de modificar el código compartido.

## Tamaño y deuda frontend

| Artefacto | Tamaño aproximado |
|---|---:|
| `req-programa-tejido.blade.php` | 339 líneas |
| `scripts/main.blade.php` | 3,895 líneas / 161 KB |
| modal duplicar/dividir | 3,381 líneas / 153 KB |
| helper compartido de modal | 1,629 líneas |
| balancear | 1,731 líneas |
| dependencias iniciales antes de filas/layout | ~16,987 líneas / ~711 KB |
| columnas de tabla | 92 |
| celdas con las 69 filas actuales | 6,348 |
| asignaciones `window.*` detectadas | 264 |
| búsquedas DOM | 574 |
| listeners | 137 |
| asignaciones `innerHTML` | 79 |
| estilos inline desde JS | 157 |
| reglas `!important` | 63 |

### Hallazgos principales de UI/código

- El controller recupera el conjunto completo y selecciona cerca de cien campos, sin paginación.
- Las 92 columnas se renderizan en servidor y parten visibles.
- Estado duplicado entre DOM, `PTStore`, índices de filtros y variables globales.
- `window.fetch` se parchea globalmente para transformar URLs de Programa en Muestras.
- Rutas, formateadores, filtros, caches y CSS están duplicados o dispersos.
- Acciones críticas dependen de clic derecho/context menu; tienen baja descubribilidad y accesibilidad.
- Carga/error/vacío no son estados diferenciados. Una excepción puede aparentar “sin datos”.
- El modal de calendario tiene loaders paralelos y arranca con todas las filas seleccionadas.
- Existen assets públicos y Vite con responsabilidades superpuestas.

## Riesgos por prioridad

### P0: bloquear antes de refactorizar

1. **Deriva de esquema Programa/Muestras.** `ReqProgramaTejido` tiene 141 columnas y `MuestrasPrograma` 135.
2. **Campos ausentes en Muestras:** `NoMarbete`, `RollosProgramados`, `ProdId`, `ProduccionMarbetes`, `IdRedbooth`, `NombreRedbooth`.
3. **Longitudes menores en Muestras:** CalendarioId, FlogsId, NombreProyecto, CustName, AplicacionId, Observaciones, ColorTrama, Prioridad, CombinaTram, BomId y BomName.
4. **Recalculado silenciosamente parcial.** El observer intenta escribir campos ausentes y captura `Throwable`; una cabecera puede guardarse aunque derivados queden incompletos.
5. **Salida HTML insegura.** La vista imprime con `{!! !!}` un formateador cuyo fallback puede ser el valor crudo de BD.
6. **Contexto Muestras implícito.** La selección de tabla depende de URL/config global y el flag calculado por el controller no llega consistentemente al Blade.
7. **Operaciones incompatibles visibles.** Redbooth no tiene la misma superficie en Muestras; liberar/redirects y descarga tienen rutas o destinos de Programa.
8. **Migraciones vs live.** Programa live ya tiene índice único y FK aunque sus migraciones aparecen pendientes; Muestras carece de índices equivalentes. `migrate` no es un paso seguro hasta reconciliar.

### P1: resolver durante read/UI seam

- Carga completa y render de 92 columnas.
- Preferencias sin separación explícita por superficie y semántica de `Estado` a caracterizar.
- Las 460 preferencias existentes no se pueden descartar.
- Paridad de rutas actual cubre solo una parte y principalmente estructura.
- Mutaciones que suprimen observers y reproducen manualmente solo parte de sus efectos.
- Fórmulas/formateadores repetidos entre PHP, JS, observer, liberar e imports.
- `ReqProgramaTejidoLine.Aplicacion` presenta deriva de tipo/uso que se debe documentar antes de tocar casts.
- Catálogos que recalculan Programa sin política clara para Muestras.
- Consumidores con tabla hardcodeada que pueden ser intencionalmente exclusivos de producción.

### P2: limpiar después de estabilizar

- CSS público/Vite duplicado y selectores globales.
- Botones solo con iconos, grupos sin semántica, foco/teclado débiles.
- Métodos sin ruta aparente y JS/assets posiblemente muertos.
- Duplicación de registro de rutas Programa/Muestras.

## Mapa de lectura y escritura

### Núcleo y efectos implícitos

- `ReqProgramaTejido::getTable()` resuelve tabla desde configuración mutable.
- `Muestras` hereda de `ReqProgramaTejido`, pero usa tabla física distinta.
- `ProgramaTejidoContext` cambia tablas por URL y está agregado globalmente al stack web.
- `ReqProgramaTejidoObserver` recalcula fórmulas, sincroniza CatCodificados y regenera líneas diarias.
- La regeneración elimina y vuelve a insertar líneas dentro de una transacción, pero algunos errores se registran sin invalidar la escritura principal.
- Varios servicios suprimen globalmente el dispatcher de eventos y luego intentan reproducir efectos manualmente.

### Mutadores de riesgo alto

| Familia | Efectos que deben caracterizarse |
|---|---|
| Edición/update | cabecera, fórmulas, líneas y CatCodificados |
| Drag/drop/cambio de telar | posición, `EnProceso`, fechas, línea y cola origen/destino |
| Mover órdenes | dos colas, fechas, líneas, CatCodificados y filas sin orden |
| Duplicar/dividir/vincular | identidad, líder `OrdCompartida`, saldos, posiciones y líneas |
| Balancear | distribución, último registro del grupo, preview y fechas |
| Liberar | validaciones, fórmulas, CatCodificados, ReqModelosCodificados y AX/TI |
| Finalizar | saldo, CatCodificados, eliminación/promoción, siguiente orden y líneas |
| Desarrolladores/Muestras | ciclo de vida distinto; procesar Muestras puede eliminar la fila procesada |
| Imports/command | escrituras directas, observers suprimidos y tablas hardcodeadas |
| Revivir | recreación desde CatCodificados, estado, posición y fórmulas |
| Redbooth | campos exclusivos y sincronización con CatCodificados |

### Consumidores de lectura a proteger

- Alineación combina programa activo, CatCodificados y paros de mantenimiento.
- Mantenimiento consume telar/orden/estado.
- Cortes de eficiencia consume velocidad y eficiencia activa.
- Inventario, Saldos, Notificar Montados, reportes y exportaciones consumen nombres y semántica actuales.
- Trazabilidad usa ReqProgramaTejido como fuente canónica de telar/orden en Crudo.
- Tejedores/Desarrolladores resuelven y mutan contexto operativo.

## Fronteras recomendadas

### Contexto explícito

Crear un value object/enum `ProgramaTejidoSurface` (`programa`, `muestras`) y un `ProgramaTejidoContextResolver` que declaren:

- tabla de cabecera y líneas;
- route manifest;
- namespace de preferencias;
- capacidades: Redbooth, liberar, descargar, campos de marbetes, ciclo de finalización;
- policy de CatCodificados y de derivados.

El middleware legacy puede poblar este contexto mientras los call sites migran. No se sustituye de golpe la configuración global.

### Read seam

`IndexProgramaTejidoRequest -> ProgramaTejidoReadService -> ProgramaTejidoRowResource`.

Responsabilidades:

- validar superficie, paginación, filtros, orden y columnas solicitadas;
- proyectar solo campos necesarios;
- devolver contrato versionado y metadata de columnas/capacidades;
- no activar observers ni introducir escrituras;
- comparar opcionalmente resultado legacy/v2 en modo shadow.

### UI v2

- Blade shell con componentes semánticos.
- Entry dedicado Vite en `resources/js/programa-tejido/index.js`.
- `api.js`, `routes.js`, `store.js`, `columns.js`, `formatters.js`.
- Features cargadas dinámicamente: detalles, calendarios, repaso, duplicar/dividir, balanceo y Redbooth.
- Una única fuente de estado; renderizadores explícitos y salida segura.
- Las mutaciones siguen llamando endpoints legacy durante la primera adopción.

### Mutaciones por caso de uso

No crear un nuevo servicio monolítico. Extraer servicios verticales:

- `UpdateProgramaTejidoField`;
- `ReprogramarProgramaTejido`;
- `ChangeProgramaTejidoCalendar`;
- `MoveProgramaTejidoWithinLoom` / `MoveBetweenLooms`;
- `DuplicateSharedOrder`, `SplitSharedOrder`, `LinkSharedOrder`;
- familias independientes para liberar, finalizar, balancear e integraciones.

Los helpers actuales permanecen como facades/adapters hasta migrar todos sus consumidores.

## Propuesta UX

- Preset inicial de 12–16 columnas esenciales para usuarios sin preferencias guardadas.
- Presets: Operación, Planeación, Materiales y Comercial.
- Preservar las 460 preferencias existentes; no imponer defaults a quien ya configuró la tabla.
- Identidad y acciones sticky; columnas agrupadas por Orden, Telar, Fechas, Producción, Materiales, Balanceo e Integraciones.
- Paginación de servidor 50/100 y selección con alcance visible y explícito.
- Toolbar por intención y action bar solo cuando haya selección.
- Menú `…` visible por fila; clic derecho puede mantenerse como atajo, no como único acceso.
- Búsqueda global, chips de filtros activos y reset claro.
- Estados independientes: cargando, error, vacío real y sin coincidencias.
- Estado de edición: pendiente, guardando, guardado, conflicto/error con reintento.
- Teclado, foco visible, `aria-label`, focus trap y restauración de foco en modales.
- Imports dinámicos para features pesadas y medición del tamaño de chunks.

## Alternativas descartadas inicialmente

- **React/TanStack inmediato:** cambia demasiadas capas a la vez y no resuelve los contratos implícitos de backend.
- **Unificar rutas primero:** puede ocultar diferencias de capacidades antes de caracterizarlas.
- **Mover toda lógica del observer primero:** riesgo alto de omitir efectos que hoy dependen del orden de eventos.
- **Alinear schema ejecutando migraciones pendientes:** el historial no coincide con live y puede causar errores o falsa seguridad.
- **Hacer genéricos todos los consumidores:** algunos deben leer exclusivamente el programa productivo, no Muestras.

