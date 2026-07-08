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
- Cuando la orden tenga producción en varios telares:
  - la card mostrará la cantidad de telares;
  - mostrará una lista breve de sus números de telar;
  - la acción **Ver telares** aparecerá en la esquina inferior izquierda;
  - el detalle estará cerrado inicialmente;
  - al abrirse, el desglose aparecerá dentro de la misma card.
- Una orden de un solo telar permanecerá como card compacta y no mostrará un control de expansión innecesario.

## Detalle desplegable

El detalle incluirá una fila por telar con:

- número de telar;
- origen: programa o trazabilidad;
- piezas producidas;
- peso producido.

El telar canónico del programa y los telares encontrados únicamente en trazabilidad seguirán diferenciándose visualmente. El color ámbar indicará producción distribuida, sin convertir cada telar en una card independiente.

## Distribución responsive

- Escritorio: cuadrícula de tres columnas para cards cerradas.
- Una card abierta con varios telares ocupará dos columnas cuando exista espacio.
- Tableta: cuadrícula de dos columnas.
- Móvil: una columna; la card abierta conservará el detalle dentro de su ancho.

## Interacción y filtros

- **Ver telares** expandirá solo la card seleccionada y cambiará a **Ocultar telares**.
- El control será un botón accesible con `aria-expanded` y relación explícita con el panel.
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
