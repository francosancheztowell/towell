# Spec: Reporte Órdenes de Trabajo Diarias

## Objetivo

En `/mecanicos/reportes/ot-diarias` el usuario elige una fecha de inicio (en la práctica un lunes), consulta la matriz semanal de intervenciones por mecánico (lunes a domingo más totales y %) y la descarga en Excel, PDF o imagen, con aviso a Telegram al descargar. El flujo, permisos y exports siguen el mismo principio que **Reporte Estado de Máquina**.

## Fuera de alcance

- Cambiar captura o estatus de Órdenes de Trabajo.
- Persistir OT Trama, Cumplidas Trama u Ocupación % en base de datos.
- Recortar la semana al mes (eso es propio de Estado de Máquina).
- Contar por folio: el grano es el renglón.

## Consulta

- Al entrar, solo hay selector de fecha y botón Consultar. No hay matriz ni botones de descarga.
- Consultar está deshabilitado hasta que haya fecha.
- La fecha de inicio es el día 1 del rango. El reporte cubre **7 días consecutivos** desde esa fecha (`America/Mexico_City`).
- El input de fecha se prellena con el **lunes de la semana actual**, pero el usuario puede elegir otro día: el rango sigue siendo “a partir de esa fecha”, no se recorta al lunes.
- Título de bloque: `SEMANA {n}` donde `n` es la semana ISO de la fecha de inicio (ej. 6 jul 2026 → semana 28), y cada día se etiqueta `lunes, 6 de julio de 2026` (locale `es`).

## Quién aparece

- Una fila por usuario de `SYSUsuario` con `UPPER(TRIM(area)) = 'MANTENIMIENTO'`, ordenados por nombre. Es el mismo catálogo que la captura de OT.
- Si en el rango hay renglones de un `CveOperador` que ya no está en ese catálogo, esa clave también sale como fila extra (al final), para no perder histórico.
- Nombre mostrado: `nombre` del catálogo; si la fila es huérfana, `NomOperador` del renglón.

## Conteo (grano = renglón)

Un renglón de `MecOrdenTrabajoLine` cuenta para un mecánico y un día cuando `Fecha` del renglón es ese día y `CveOperador` coincide. Dos renglones el mismo día, mismo folio, mismo mecánico = **2**.

Estatus se lee de la **cabecera** (`MecOrdenTrabajoTable.Estatus`). En UI, `Terminado` se muestra como Finalizado.

| Columna | Qué cuenta |
|---|---|
| **Realizadas** | Renglones del día cuyo folio está `Terminado`, `Calificado` **o** `Autorizado`. |
| **Firmadas** | Renglones del día cuyo folio está `Autorizado`. |
| **Ocupación min.** | Suma de `TotalMinutos` de **todos** los renglones de ese mecánico ese día (incluye `Activo`). `NULL` cuenta como 0. |
| **Ninguna de las dos primeras** | Folio `Activo` o `Cancelado` no entra en Realizadas ni Firmadas. |

Decisión (aprobada de momento): Realizadas **incluye** Autorizado. Si no, una semana ya firmada quedaría Realizadas = 0 y rompería el Excel de referencia, donde ambas columnas coinciden cuando todo está autorizado. En una semana a medias, Realizadas ≥ Firmadas.

## Totales y fórmulas (por fila)

Constantes: jornada = **480** minutos; semana = **7** días; capacidad = `480 * 7` = 3360.

Inputs (no se guardan; viajan al Excel/PDF/imagen de esa consulta, igual que Prioridad en Estado de Máquina):

- **OT Trama** y **Cumplidas Trama** (grupo De Trama). Vacío = 0.
- **Ocupación %**. Vacío = 0. No tiene fórmula.

Calculados:

| Campo | Fórmula |
|---|---|
| Total semana Realizadas | Suma de Realizadas de los 7 días |
| Total semana Firmadas | Suma de Firmadas de los 7 días |
| Total Realizadas | Total semana Realizadas + OT Trama |
| Total Cumplidas | Total semana Firmadas + Cumplidas Trama |
| Min. Semana | Suma de Ocupación min. de los 7 días |
| % | `(Min. Semana / 3360) * 100` |
| % Cumplimiento | Si Total Realizadas = 0 → 0. Si no: `(Total Cumplidas / Total Realizadas) * 100` |
| % OT Final | `(Ocupación % + % Cumplimiento) / 2` |

`% Cumplimiento` usa Cumplidas / Realizadas (no al revés): en el Excel de referencia 16.90 / 17.0 = 99.4.

## Pie de tabla

- Suma de **OT Trama** y de **Cumplidas Trama** de todos los mecánicos.
- Promedio aritmético de **% OT Final** de las filas visibles.

## Formato visual

Reproducir el Excel de referencia (colores aproximados, texto blanco en encabezados oscuros, celdas de datos blancas, números centrados):

| Bloque | Color de encabezado |
|---|---|
| SEMANA / MECANICOS / Realizadas-Firmadas-Ocupación de cada día | Verde oscuro `#375623` |
| Nombre del día (`lunes, 6 de julio de 2026`) | Oro `#FFC000` |
| TOTAL SEMANA EN TURNO | Oro `#FFC000` |
| DE TRAMA | Teal `#4BACC6` |
| GENERAL | Oro `#FFC000` |
| MIN. SEMANA y % | Magenta `#C659B4` |
| OCUPACIÓN %, % CUMPLIMIENTO, % OT FINAL | Salmón `#F8CBAD` |

Formatos numéricos:

- Realizadas / Firmadas / De Trama / General: 1 decimal (`1.0`, `15.0`).
- Ocupación min. y Min. Semana: 1 decimal. Min. Semana con miles: `3,780.0` (Excel `#,##0.0`).
- % capacidad: 2 decimales (`112.50`).
- Ocupación %, % Cumplimiento, % OT Final: 1 decimal (`99.4`).

Día sin renglones: `0.0`, no celda vacía.

## Descarga y Telegram

- Excel, PDF e imagen solo después de Consultar.
- PDF apaisado (A3, como Estado de Máquina) para que quepan los 7 días + totales.
- Imagen: html2canvas de la hoja consultada.
- Al descargar cualquiera de los tres se envía el archivo a suscriptores `SYSMensajes.ReporteMecanico`. Si Telegram falla o no hay destinatarios, la descarga sigue.
- Los valores de los tres inputs viajan en el POST de export, igual que `prioridades` en Estado de Máquina.

## Permiso

Fail closed: `userCan('acceso', $nombre)` → 403 si no hay acceso. `$nombre` se resuelve en este orden: (1) `SYSRoles.Nombre` cuya `Ruta` es `/mecanicos/reportes/ot-diarias`; (2) si no hay fila, el literal `OT Diarias` (comentario de rutas `1103-1`). No dejar el endpoint del placeholder sin chequeo.

## Arquitectura

Mismo patrón que Estado de Máquina. El controlador no arma la matriz.

| Unidad | Responsabilidad |
|---|---|
| `ReporteOtDiariasService` | Rango de 7 días, catálogo de mecánicos, agregación de renglones, fórmulas puras (sin inputs de trama/ocupación, o aplicándolos al final). Testeable sin HTTP. |
| `MecReportesController` | `otDiarias`, excel, pdf, telegram-imagen. Autorización y orquestación. |
| `ReporteOtDiariasExport` | Hoja Excel con merges, colores y formatos. |
| Vista Blade `reportes/ot-diarias` | Filtro + matriz + html2canvas. |
| Vista PDF `pdf/mecanicos/ot-diarias` | Misma matriz para Dompdf. |
| Telegram | Reutilizar destino `ReporteMecanico`. Generalizar el notifier existente si el cambio es pequeño; si no, notifier hermano con el mismo contrato. |

Consultar es GET con `fecha`. Exportar es POST con `fecha` + inputs por mecánico (`cve`).

## Verificación

- Lunes 6 jul 2026 → días 6–12 jul 2026, etiqueta semana 28.
- Dos renglones el mismo día, mismo folio, mismo mecánico, folio `Terminado` → Realizadas 2, Firmadas 0.
- Mismos renglones con folio `Autorizado` → Realizadas 2, Firmadas 2.
- Folio `Activo` con 90 minutos → Realizadas 0, Firmadas 0, Ocupación 90.
- Folio `Cancelado` → no suma Realizadas ni Firmadas; sí suma minutos si el renglón los tiene.
- OT Trama 2.0 sobre 15 Realizadas de turno → Total Realizadas 17.0.
- Min. Semana 3780 → % = 112.50.
- Total Cumplidas 16.9 y Total Realizadas 17 → % Cumplimiento = 99.4.
- Ocupación % 100 y % Cumplimiento 99.4 → % OT Final = 99.7.
- Sin Consultar no hay descarga.
- Telegram caído: el Excel igual se descarga.
- Las pruebas unitarias del service cubren rango, conteo por renglón y fórmulas (incluye división por cero).
