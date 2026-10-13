# Reporte OEE Urdido — decisiones (grill 2026-10-13)

Nueva sección en `/urdido/reportesurdido` (`ReportesUrdidoController::index()` → `$reportes`).
No reutiliza "Produccion OEE-URD-ENG" (`reporte03Oee`), que solo lista producción.

## Consulta y entrega
- Filtro: select de **mes, múltiple** (todos los julios del/los mes(es)).
- **Siempre solo finalizados** (`UrdProduccionUrdido.Finalizar = 1`), sin casilla.
- Casillas de tipo de paro (los 4 marcados por default): Tiempo Muerto, Mecánico, Eléctrico, Calidad.
- Pantalla + Excel con la misma estructura.

## Estructura
Mes → **Máquina** (plegable) → fila por día → **un renglón por julio**, con total por máquina.
- Sin nivel de semana en pantalla ni Excel (owner, 13-oct: "no me importa el salón ni la semana, las máquinas").
- La semana ISO (lunes–domingo, **partida por mes**) sigue solo por dentro, para prorratear el paro.
- Máquinas: Mc Coy 1/2/3 y KM1 (normalizar 'Karl Mayer' → KM1, como `CuotasUrdidoService::maquina()`).

## Columnas (por julio)
| Columna | Fuente |
|---|---|
| Orden / Número de lote | `UrdProgramaUrdido.Folio` |
| Número de L.MAT | `BomId` |
| Configuración | `Fibra` |
| Tamaño | `InventSizeId` |
| Localidad | `MaquinaId` |
| No. julio, Fecha, Hora ini/fin | `UrdProduccionUrdido` |
| Cantidad (kg) | `KgNeto` |
| Mts | `Metros1+2+3` |
| Minutos prod | `CuotasUrdidoService::duracion(HoraInicial, HoraFinal)` |
| Min paros | prorrateo (ver abajo) |
| Defecto / Penalización (pts) | `ClaveDefecto` → `CatDefectosUrdEng`, `Penalizacion` |
| Turno / Oficial | los 3 operadores en una celda: "1 · Nombre / 2 · Nombre" |
| Código artículo, Color, Almacén | fijos: JULIO-URDIDO, 1000, A-JUL/URD |

Omitida por ahora: **Producción** (no. de orden AX, ej. 04925427_087) — no existe en Towell.

## Paros (dbo.ManFallasParos, Depto='Urdido')
Medido jun–sep 2026: paros cerrados días tarde (KM1 4→17 jun, 18,447 min) mientras la máquina urdía;
30–40 % de los minutos de paro caen dentro de julios; en casi todo mes/máquina paros > producción.
32 paros siguen abiertos (se ignoran).
- **El julio manda**: por máquina, unir paros (`Tramos::unir`) y **restar las ventanas de julios**;
  solo cuentan minutos de paro fuera de julios.
- **Prorrateo**: los minutos de paro de cada máquina+semana (semana partida por mes) se reparten entre
  sus julios **proporcional a sus minutos de producción**. Máquina-semana sin julios: paro solo en el subtotal.

## Alcance mostrado (owner, 13-oct-2026)
Por ahora el reporte es solo para analizar: pantalla y Excel muestran **solo datos** (julios, cantidad,
minutos de producción y de paro), **ningún porcentaje**: "que ellos lo hagan". Disponibilidad, Calidad,
Rendimiento y OEE siguen en `OeeUrdidoService::indicadores()` (cubierto por test) pero no se muestran
("asumir la calidad no sería bueno"). Defecto y penalización siguen como columnas de dato crudo.

## Fórmulas
- Disponibilidad = T.prod ÷ (T.prod + Paros)  — provisional, sin tiempo total.
  Mostrar nota: incluye noches/días sin programa hasta tener el tiempo total.
- Rendimiento = **100 % fijo** (no hay velocidad estándar de urdido).
- Calidad = kg de julios **sin defecto** ÷ kg totales (cualquier defecto descuenta el julio;
  sin calificar = bueno). La penalización es una boleta de puntos (5S/seguridad), no %; va informativa.
- OEE = D × R × C.

## Pendientes del owner
- **Tiempo total** disponible (los 480 / turno) → Disponibilidad = (Total − Paros) ÷ Total y columna
  "Tiempo sin registrar" prorrateada.
- Velocidad estándar m/min por máquina → Rendimiento real.
- ¿Umbral de penalización para Calidad (p. ej. ≥ 25 pts)?
- Columna Producción (AX).
