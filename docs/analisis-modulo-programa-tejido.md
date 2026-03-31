# Análisis del Módulo ProgramaTejido

**Fecha:** 2026-03-04

---

## 1. Listado de archivos PHP

### Controladores (raíz)

| Archivo | Líneas | Responsabilidades |
|---------|--------|-------------------|
| `ProgramaTejidoController.php` | ~2047 | Controlador principal: index, CRUD, creación de registros, integración con funciones (editar, actualizar, eliminar, duplicar, dividir, balancear, vincular, drag-and-drop). Orquesta todas las operaciones del programa de tejido. |
| `LiberarOrdenesController.php` | ~1306 | Liberación de órdenes: index, liberar, obtenerBomYNombre, obtenerTipoHilo, guardarCamposEditables, obtenerCodigoDibujo, obtenerOpcionesHilos. Gestión de catálogos codificados y actualización de ReqModelosCodificados. |
| `OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php` | ~1683 | Orden de cambio para felpa: gestión de órdenes de cambio, propagación a CatCodificados. |
| `DescargarProgramaController.php` | ~385 | Exportar programa de tejido (Excel/CSV): descargar, mapeo de columnas, formateo de valores. |
| `ReqProgramaTejidoLineController.php` | ~112 | CRUD para líneas del programa (ReqProgramaTejidoLine): index, store, show, update, destroy. |
| `ColumnasProgramaTejidoController.php` | ~121 | Configuración de columnas visibles del programa: index, getColumnasVisibles, store. |
| `RepasoController.php` | ~168 | Creación de registros de repaso: createrepaso, derivar salón, obtener registro anterior, aplicar fórmulas. |
| `ReimprimirOrdenesController.php` | ~74 | Reimpresión de órdenes. |

### Funciones (`funciones/`)

| Archivo | Líneas | Responsabilidades |
|---------|--------|-------------------|
| `BalancearTejido.php` | ~1009 | Balanceo de fechas para OrdCompartida: previewFechas, actualizarPedidos, getRegistrosPorOrdCompartida, recalcularRegistroPorProduccion, balancearAutomatico. Calcula HorasProd, fechas finales desde calendario real, cascade de fechas. |
| `DividirTejido.php` | ~1179 | Dividir registro en múltiples telares: dividir (crear copias con mismo OrdCompartida), redistribuirGrupoExistente, calcularTotalesDividir. Aplica Std, fórmulas, recalcula fechas. |
| `DuplicarTejido.php` | ~868 | Duplicar registros a nuevos telares: duplicar (con opción de vincular OrdCompartida existente), obtenerNuevoOrdCompartidaDisponible, aplicarDatosModeloCodificado. |
| `UpdateTejido.php` | ~878 | Actualización de registro existente: actualizar, truncarStringsAntesDeGuardar, recalcular fórmulas, actualizarAplicacionEnLineas. |
| `VincularTejido.php` | ~581 | Vincular registros existentes bajo un OrdCompartida: vincularRegistrosExistentes, obtenerNuevoOrdCompartidaDisponible, actualizarOrdPrincipalPorOrdCompartida. |
| `eliminartejido.php` | ~490 | Eliminación: eliminar, eliminarEnProceso, moverEnLugarDeEliminar, eliminarConOrdCompartida, recalcularFechasYFormulas. |
| `DragAndDropTejido.php` | ~249 | Reordenar registros por posición: mover, moverAposicion, obtenerRegistrosBloqueadosPorTelar, reordenar colección. |
| `EditTejido.php` | ~27 | Mostrar formulario de edición: editar (findOrFail + vista). |

### Helpers (`helper/`)

| Archivo | Líneas | Responsabilidades |
|---------|--------|-------------------|
| `TejidoHelpers.php` | ~475 | Helpers compartidos: calcularHorasProd, calcularFormulasEficiencia, snapInicioAlCalendario, aplicarStdDesdeCatalogos, obtenerModeloParams, sanitizeNumber, construirMaquinaConSalon, esRepaso, resolverDiasEntrega, buscarStdVelocidad/Eficiencia. |
| `DateHelpers.php` | ~511 | Recalcular fechas en secuencia: recalcularFechasSecuencia, cascadeFechas, snapInicioAlCalendario, calcularFechaFinalDesdeInicio. |
| `UpdateHelpers.php` | ~275 | Aplicar actualizaciones inline: applyInlineFieldUpdates, applyCantidad, applyCalculados, aplicarCamposFormulario, aplicarFallbackModeloCodificado. Usa StringTruncator. |
| `UtilityHelpers.php` | ~263 | getTableColumns, extractResumen, resolveTipoPedidoFromFlog, resolverAliases, marcarCambioHiloAnterior. |
| `QueryHelpers.php` | ~110 | getStdValue, pluckDistinctNonEmpty, findModeloDestino, resolverStdSegunTelar, getEficienciaVelocidadStd. |

---

## 2. Funciones/métodos duplicados o muy similares

### Entre BalancearTejido, DuplicarTejido, DividirTejido, UpdateTejido

| Función | Ubicaciones | Comentario |
|---------|-------------|------------|
| **calcularHorasProd** | BalancearTejido (L569), DuplicarTejido (L668), DividirTejido (L1240), UpdateTejido (L672) | Los cuatro definen una versión privada que obtiene params (getModeloParams/obtenerTotalModelo), sanitiza cantidad y delega a `TejidoHelpers::calcularHorasProd`. **UpdateTejido** usa `obtenerTotalModelo` + valores del registro directamente (no getModeloParams). |
| **calcularFormulasEficiencia** | BalancearTejido (L669), DuplicarTejido (L737 pública), DividirTejido (L708), UpdateTejido (L949) | Balancear, Duplicar, Dividir: cada uno llama `getModeloParams` + `TejidoHelpers::calcularFormulasEficiencia` con parámetros ligeramente distintos (includeEntregaCte, etc.). **UpdateTejido** delega a `DuplicarTejido::calcularFormulasEficiencia`. |
| **getModeloParams** | BalancearTejido (L687), DuplicarTejido (L687), DividirTejido (L1262), UpdateTejido (no tiene; usa getModeloCodificado + obtenerTotalModelo) | Balancear, Duplicar, Dividir: lógica muy similar (cache por tamanoClave, fallback a NoTiras/Luchaje/Rep del programa). **DividirTejido** usa `obtenerModeloCodificadoPorSalon` (con salón). **UpdateTejido** tiene `getModeloCodificado` y `obtenerTotalModelo` con lógica distinta. |
| **aplicarStdDesdeCatalogos** | DuplicarTejido (L732), DividirTejido (L1308), BalancearTejido (no la tiene explícita) | Ambos delegan a `TejidoHelpers::aplicarStdDesdeCatalogos`. Balancear no la usa. |
| **snapInicioAlCalendario** | BalancearTejido (L591), UpdateTejido (L667), DateHelpers (L357), TejidoHelpers (L389) | Balancear usa cache (`getCalendarioLines`) y delega a TejidoHelpers. Update y DateHelpers delegan directamente a TejidoHelpers. |
| **sanitizeNumber** | BalancearTejido (L821), DuplicarTejido (L761), DividirTejido (L1299), UpdateTejido (L956) | Todos delegan a `TejidoHelpers::sanitizeNumber` o implementan lo mismo. |
| **construirMaquina** | DuplicarTejido (L756), DividirTejido (L1232) | Duplicar usa `TejidoHelpers::construirMaquinaConBase`, Dividir usa `TejidoHelpers::construirMaquinaConSalon`. |

### Otras duplicaciones

| Función | Ubicaciones | Comentario |
|---------|-------------|------------|
| **obtenerNuevoOrdCompartidaDisponible** | DuplicarTejido (L972), VincularTejido (L576) | Implementación idéntica: max(OrdCompartida)+1, verificar que no exista. |
| **truncamiento de strings** | UpdateTejido (truncarStringsAntesDeGuardar, L634), UpdateHelpers + DuplicarTejido + DividirTejido (StringTruncator) | UpdateTejido usa diccionario de límites y mb_substr. Resto usa `App\Helpers\StringTruncator::truncate`. |

---

## 3. Patrones repetidos

### Snap al calendario

- **TejidoHelpers::snapInicioAlCalendario**: implementación canónica.
- **BalancearTejido**: wrapper con cache (`getCalendarioLines`) → TejidoHelpers.
- **DateHelpers**: wrapper sin cache → TejidoHelpers.
- **UpdateTejido**: wrapper → TejidoHelpers.
- **RepasoController**: llama TejidoHelpers directamente.

### Recalcular fechas

- **DateHelpers::recalcularFechasSecuencia**: lógica principal (snap, calcularFechaFinalDesdeInicio, fórmulas).
- **BalancearTejido::actualizarPedidos**: cascada en telar tras aplicar cambios del balanceo (snap + fechas).
- **DateHelpers::cascadeFechas**: cascade tras actualización.
- **EliminarTejido::recalcularFechasYFormulas**: tras eliminar, llama DateHelpers.

### Obtener OrdCompartida

- **DuplicarTejido, VincularTejido**: `obtenerNuevoOrdCompartidaDisponible()` duplicada.
- **DividirTejido**: inline `max(OrdCompartida)+1` sin verificación de colisión.
- **BalancearTejido::getRegistrosPorOrdCompartida**: consulta por OrdCompartida.
- **eliminartejido**: `eliminarConOrdCompartida` cuando el registro tiene OrdCompartida.

### Truncamiento de strings

- **StringTruncator** (global): usado en UpdateHelpers, DuplicarTejido, DividirTejido, ProgramaTejidoController.
- **UpdateTejido::truncarStringsAntesDeGuardar**: diccionario propio con mb_substr, no usa StringTruncator.

---

## 4. Estructura de dependencias entre clases

```
ProgramaTejidoController
├── EditTejido
├── UpdateTejido
│   └── DuplicarTejido::calcularFormulasEficiencia
│   └── TejidoHelpers (sanitizeNumber, snapInicioAlCalendario)
│   └── UtilityHelpers::extractResumen
├── EliminarTejido
│   └── DateHelpers
│   └── TejidoHelpers
├── DragAndDropTejido
├── DuplicarTejido
│   └── TejidoHelpers (calcularHorasProd, calcularFormulasEficiencia, aplicarStdDesdeCatalogos, construirMaquinaConBase, sanitizeNumber)
│   └── StringTruncator
├── DividirTejido
│   └── TejidoHelpers (calcularHorasProd, calcularFormulasEficiencia, aplicarStdDesdeCatalogos, construirMaquinaConSalon, sanitizeNumber)
│   └── VincularTejido::actualizarOrdPrincipalPorOrdCompartida
│   └── StringTruncator
├── BalancearTejido
│   └── TejidoHelpers (calcularHorasProd, calcularFormulasEficiencia, snapInicioAlCalendario, sanitizeNumber)
├── VincularTejido
│   └── (sin helpers de fórmulas, solo lógica OrdCompartida)
├── UpdateHelpers
├── DateHelpers
│   └── TejidoHelpers::esRepaso, calcularFormulasEficiencia
├── QueryHelpers
├── UtilityHelpers
└── TejidoHelpers

RepasoController
├── TejidoHelpers::snapInicioAlCalendario
└── DuplicarTejido::calcularFormulasEficiencia

LiberarOrdenesController
└── (modelos propios)

DescargarProgramaController
└── (modelos/helpers propios)

ColumnasProgramaTejidoController
ReqProgramaTejidoLineController
ReimprimirOrdenesController
OrdenDeCambioFelpaController
```

### Resumen de dependencias

| Clase | Usada por | Usa |
|-------|-----------|-----|
| **TejidoHelpers** | BalancearTejido, DuplicarTejido, DividirTejido, UpdateTejido, DateHelpers, EliminarTejido, RepasoController | ReqModelosCodificados, ReqVelocidadStd, ReqEficienciaStd |
| **DateHelpers** | UpdateTejido (indirecto), EliminarTejido, UpdateHelpers (setSafeDate) | TejidoHelpers |
| **UpdateHelpers** | ProgramaTejidoController, UpdateTejido | DateHelpers, StringTruncator |
| **DuplicarTejido** | UpdateTejido (calcularFormulasEficiencia), RepasoController, DividirTejido (actualizarOrdPrincipalPorOrdCompartida vía VincularTejido) | TejidoHelpers |
| **VincularTejido** | DividirTejido (actualizarOrdPrincipalPorOrdCompartida) | (lógica propia) |
| **BalancearTejido** | ProgramaTejidoController | TejidoHelpers |
| **DividirTejido** | ProgramaTejidoController | TejidoHelpers, VincularTejido |
| **EliminarTejido** | ProgramaTejidoController | DateHelpers, TejidoHelpers |

---

## 5. Recomendaciones de refactorización

1. **Centralizar `obtenerNuevoOrdCompartidaDisponible`** en `TejidoHelpers` o un helper `OrdCompartidaHelper`, y usarla desde DuplicarTejido y VincularTejido.

2. **Unificar `calcularHorasProd` y `getModeloParams`**: que Balancear, Duplicar, Dividir y Update usen `TejidoHelpers::obtenerModeloParams` + `TejidoHelpers::calcularHorasProd`. UpdateTejido hoy usa una variante distinta con `obtenerTotalModelo`; conviene alinearla.

3. **Unificar truncamiento**: que `UpdateTejido::truncarStringsAntesDeGuardar` use `StringTruncator` en lugar de un diccionario propio, para mantener un solo punto de verdad.

4. **Reducir duplicación en `calcularFormulasEficiencia`**: que Balancear, Duplicar y Dividir llamen siempre a `TejidoHelpers::calcularFormulasEficiencia` con los mismos flags, o a `DuplicarTejido::calcularFormulasEficiencia` si se mantiene como punto único (como ya hace UpdateTejido).

5. **Snap al calendario**: mantener `TejidoHelpers` como implementación canónica; Balancear puede seguir usando su cache para performance sin duplicar lógica.

---

*Análisis generado el 2026-03-04*
