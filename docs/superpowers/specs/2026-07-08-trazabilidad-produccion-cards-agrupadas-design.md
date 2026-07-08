# Diseño: cards agrupadas de Producción en Trazabilidad

## Objetivo

Mejorar la sección **Producción > Crudo** para evitar que una misma orden ocupe varias tarjetas cuando registra producción en muchos telares. La pantalla debe conservar el formato de cards, reducir el ruido visual y facilitar la consulta del desglose por telar.

## Estructura aprobada

- Se mostrará una sola card por orden.
- Cada card mostrará siempre:
  - número de orden;
  - mes y estado;
  - cantidad programada;
  - piezas producidas consolidadas;
  - peso total consolidado.
  - piezas estándar por día;
  - kilogramos estándar por día;
  - porcentaje y barra de avance.
- Cuando la orden tenga producción en varios telares:
  - la card mostrará la cantidad de telares;
  - mostrará siempre una matriz horizontal, sin dropdown;
  - cada telar será una columna;
  - Piezas y Peso serán las filas;
  - si no caben todos los telares, la matriz tendrá scroll horizontal interno.
- Una orden de un solo telar permanecerá como card compacta.

## Detalle desplegable

La matriz no mostrará la columna Origen. El encabezado contendrá los telares y las filas mostrarán piezas y peso producidos. El color ámbar de la card indicará producción distribuida.

## Distribución responsive

- Escritorio amplio: cuadrícula de cuatro columnas para cards cerradas.
- Escritorio intermedio: cuadrícula de tres columnas.
- Una card abierta con varios telares ocupará dos columnas cuando exista espacio.
- Tableta: cuadrícula de dos columnas.
- Móvil: una columna; la card abierta conservará el detalle dentro de su ancho.

## Interacción y filtros

- No habrá interacción de expansión para los telares.
- Los filtros existentes de estado continuarán operando por orden, no por cada registro de telar.
- La sección **Rollos Teñido** no cambia.

## Datos

`TrazabilidadProduccionService` seguirá resolviendo el telar canónico y los registros externos. La salida de Crudo se reorganizará en grupos por orden y añadirá totales consolidados, sin modificar las reglas actuales para:

- prioridad de `ReqProgramaTejido` frente a `CatCodificados`;
- producción del telar canónico;
- producción proveniente de `TrazaProduccion`;
- filtros superiores, incluido que Color solo afecta Rollos Teñido.

## Verificación

- Una orden con uno, dos y cuatro telares genera una sola card en cada caso.
- Los totales de la card equivalen a la suma de su desglose.
- El botón abre y cierra únicamente su card.
- Los filtros Todos, Activo y Terminado mantienen conteos por orden.
- La cuadrícula no desborda en escritorio, tableta ni móvil.
- La pestaña Rollos Teñido conserva su comportamiento actual.
- Las vistas Blade compilan y las pruebas relacionadas con Trazabilidad pasan.
