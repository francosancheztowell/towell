# Auditoría de responsividad: Planeación, Ventas y Costos

Fecha de inicio: 10 octubre 2026; evidencia añadida el 11 octubre. Se inspeccionó código y después capturas originales y mediciones JSON del navegador coordinado por el agente principal. No se alteró aplicación ni datos. La primera sección distingue riesgos estáticos; al final se documentan hallazgos runtime medidos y sus límites. Se leyó AGENTS.md.

## Cobertura e inventario

Se revisaron `routes/modules/planeacion.php`, `ventas.php`, `costos.php`; resolución de vistas en controladores de Planeación; plantillas principales y componentes/JS/CSS relacionados. Las rutas JSON, altas, cambios y exportaciones forman parte del flujo de sus pantallas; no se contabilizan como pantallas adicionales. Los aliases/redirects del catálogo reutilizan vistas.

| Submódulo / rutas principales | Vistas y soporte inspeccionados | Fortalezas | Riesgos estáticos / pendiente |
|---|---|---|---|
| Catálogos Telares `/planeacion/catalogos/telares`, Eficiencia `/eficiencia`, Velocidad `/velocidad`, Aplicaciones `/aplicaciones` y aliases `/planeacion/*` | `catalagos/catalagoTelares`, `comun/estandar`, `aplicaciones`, `comun/modales`; JS `modulos/catalogos-planeacion` | Formularios 1→2 columnas, controles táctiles, filas enfocables | Altura fija en Telares; verificar anchura de datos largos y acceso a acciones del navbar |
| Calendarios `/planeacion/catalogos/calendarios` y `/planeacion/calendarios` | `catalagos/calendarios/index`, cinco modales; JS de calendario | Cabeceras sticky, selección por teclado, tabla del modal con scroll horizontal | Dos tablas verticales, segunda de 600px; rango de fechas con dos inputs de ancho fijo |
| Matriz hilos `/planeacion/catalogos/matriz-hilos`, Matriz calibres `/matrizcalibres`, Pesos por rollos `/pesos-rollos` | `catalagos/matriz-hilos`, `matriz-calibres`, `pesos-rollos`; JS respectivos | Scroll interno; botones 44px explícitos en calibres | Calibres exige tabla 900px; scroll deliberado, no defecto de página probado |
| Modelos codificados `/planeacion/catalogos/codificacion-modelos`, `/create`, `/{id}/edit` | `catalogoCodificacion`, `codificacion-form`, modal duplicar/importar | Formulario 1→2→4→6 columnas; listado con scroll en dos ejes | Grid identificador pasa a cinco columnas ya en 768px; probar contenido largo. Tablas de captura requieren desplazamiento |
| Codificación `/planeacion/codificacion`, alias `/modulo-codificación`; L.Mat y Peso muestra | `catcodificacion/index`; JS `catcodificacion/index.js`, `lmat-modal.js` | Listado scroll táctil, modal L.Mat limitado a viewport, formulario superior adaptativo | Tabla de edición L.Mat de 1040px y necesidad de saltar entre componentes; menú contextual en encabezados |
| Lista de materiales `/planeacion/catalogos/lista-de-materiales` | `catalagos/lmat-lista`; JS `lmat-lista/index.js` | Filtros 2→5 columnas, tabla en scroll | Cinco filtros ya a 640px; verificar legibilidad a 768px |
| Alineación `/planeacion/alineacion` | `planeacion/alineacion/index`, `_styles`; JS `modulos/programa-tejido/alineacion` | Scroll en dos ejes y cabeceras | Densidad de tabla y menú contextual; medir identificación de fila y filtros en móvil |
| Programa tejido `/planeacion/programa-tejido`, Muestras `/planeacion/muestras` | `req-programa-tejido`, v2/Livewire board, `partials/grilla`, complementos; JS `programa-tejido` | Scroll de tabla, columnas ocultables; variantes comparten superficie | Muchas columnas mínimas 80px; usar tabla no equivale a captura responsiva |
| Liberar órdenes `/planeacion/programa-tejido/liberar-ordenes`, equivalente Muestras | `liberar-ordenes/index`; JS liberación y columnas | Scroll explícito en ambos ejes y columnas editables con ancho legible | Anchos 170–300px por campo; comprobar acceso constante a orden/errores/confirmación |
| Balancear `/planeacion/programa-tejido/balancear`, equivalente Muestras | `balancear.blade.php`, JS `programa-tejido/balancear.ts` | Modal 98/96/94vw; Gantt ajusta altura por viewport; scroll táctil | Tabla 620px; Gantt móvil de texto 9px y filas 32px |
| Auditoría `/planeacion/programa-tejido/auditoria`, detalle líneas `/planeacion/req-programa-tejido-line`, `/planeacion/muestras-line` | Auditoría index; componente `req-programa-tejido-line-table` | Tabla de detalle con scroll limitado a 250px | Validación visual de modal/tabla y navbar pendiente; cobertura estática más superficial |
| Utilería `/planeacion/utileria` (Finalizar y Mover como modales) | index, finalizar-ordenes, mover-ordenes; JS utilería | Menú 1→2 columnas; Finalizar con cuerpo scroll y tabla overflow-x | Mover fija 1200px y depende de arrastre HTML |
| Ventas `/ventas` con datos compara/histórico | Livewire dashboard, `resources/css/ventas/dashboard.css`, JS dashboard/histórico/multiselect | dvh, breakpoints 1100/760px, filtros limitados al viewport, gráficos por panel visible | Columna sticky grande en móvil y pequeños objetivos táctiles |
| Costos `/costos`, `/costos/cuotas` | Cuotas Livewire, `components/tabla`, estilos diálogo compartidos | min-h-touch, flex-wrap, diálogo nativo, inputmode y teclado | Modal Calcular Urdido grid fijo de tres columnas; alto de teclado pendiente |

## Hallazgos priorizados

Todos son **riesgos estáticos**. Prioridad indica orden de validación/corrección sugerido, no un puntaje medido.

1. **P1 — Mover órdenes tiene ancho mínimo incompatible con tres tamaños objetivo.** `resources/views/planeacion/utileria/mover-ordenes.blade.php:18` combina `max-w-[95vw]` y `min-width:1200px`; CSS min-width prevalece si el máximo es menor. El panel requiere al menos 1200px aunque el viewport sea 390, 768 o 1024. En `:32` conserva paneles horizontales y mínimo de altura 450px; `:31` oculta overflow exterior. Propuesta: ancho limitado al viewport, paneles apilados o paso origen/destino, min-h-0 y cuerpo desplazable con acciones visibles. A 1280px entra por poco; probar bordes y cierre.

2. **P1 — Mover/reordenar depende del arrastre HTML del navegador.** `resources/js/modulos/programa-tejido/utileria/mover.ts:139` asigna `draggable`; `:347–399` enlaza dragstart/dragover/drop usando dataTransfer. En ese archivo no se detectaron listeners táctiles o pointer ni una alternativa de reordenamiento por teclado. El resultado depende del navegador y debe medirse en tablet; no se afirma que falle en todos. Propuesta: botones mover/subir/bajar y selección destino como alternativa completa; si se conserva arrastre, soporte touch con asa y umbral que no interfiera con scroll.

3. **P2 — Calendarios suma grandes áreas verticales y Telares fija 640px.** `resources/views/catalagos/calendarios/index.blade.php:34` primera tabla máx 300px; `:57` segunda tabla fija 600px, más separación y navbar. `resources/views/catalagos/catalagoTelares.blade.php:17` fija 640px. Existe scroll global de main, por lo que esto no demuestra contenido inaccesible, pero en 1024×768 habrá navegación vertical adicional y con teclado aún menos espacio. Propuesta: altura disponible con dvh/flex/min-h-0; calendario maestro-detalle colapsable en tamaños pequeños.

4. **P2 — Edición L.Mat es una tabla horizontal de 1040px.** `resources/js/catcodificacion/lmat-modal.js:1397–1398` proporciona overflow-x correcto y tabla min-w-[1040px], mientras `:1457` limita diálogo a 98vw. La página no necesariamente desborda: la dificultad es relacionar Artículo/Config/Tamaño/Color con Cantidad/% después de desplazar en móvil. Propuesta: edición por componente con resumen, o primera columna fija y detalle expandible; conservar tabla completa en escritorio. Medir foco al seleccionar y visibilidad de validación al abrir teclado.

5. **P2 — Liberar órdenes requiere navegación extensa entre columnas editables.** `resources/views/modulos/programa-tejido/liberar-ordenes/index.blade.php:483–490` establece scroll por ambos ejes y mínimos 300px para Prioridad/BomName, 260px Observaciones, etc.; `:741–800` consolida anchos. Es diseño de grilla explícito, no evidencia de overflow global. Propuesta: resumen fijo orden/telar más ficha de edición por orden en móvil; agrupar campos y navegar errores. Validar que el botón final y BOM obligatorio no se pierdan al desplazar.

6. **P2 — Ventas conserva una columna sticky de 250px en móvil.** `resources/css/ventas/dashboard.css:98` fija la columna de nombre a la izquierda; `:229` la ajusta a mínimo 250px bajo 760px. En viewport de 390px deja menos de 140px para valores, antes de padding; los números exigen scroll horizontal. Propuesta: nombre compacto/desplegable o sticky más corto con acceso al texto completo. Verificar filas reales largas y reconocimiento de métricas.

7. **P2 — Ventas define algunos controles menores que el estándar táctil de 44px.** `resources/css/ventas/dashboard.css:72` cierre de filtros 32×32px, `:125` limpiar filtro 16×16px; chips del comparativo `:186–187` mínimo 24.8/28.8px. Son dimensiones CSS declaradas, no mediciones del área efectiva renderizada. Propuesta: ampliar el área activa a 44px conservando iconos pequeños; revisar todos los chips con inspección DOM.

8. **P2 — Calcular Urdido en Costos no reduce columnas en móvil.** `resources/views/livewire/costos/cuotas.blade.php:192` usa grid-cols-3 con gap-4 para Año/Desde/Hasta; el diálogo compartido tiene ancho viewport menos 32px (`resources/css/app.css:306`). En móvil los select de meses largos quedan muy compactos. Alta/edición sí tiene breakpoints (`cuotas.blade.php:96,120`). Propuesta: una columna móvil, tres desde sm, comprobar etiquetas/error/teclado y botón final.

9. **P2 — Gantt de balanceo se compacta hasta 9px en móvil.** `resources/js/programa-tejido/balancear.ts:982` establece 9px en celdas bajo 640px; `:968` filas mínimas 32px, aunque el contenedor tiene scroll y altura adaptable. Riesgo de lectura y toque preciso, distinto del ajuste geométrico. Propuesta: mantener texto legible y desplazamiento/zoom temporal, vista resumida por telar y detalle al tocar.

10. **P3 — Formulario de modelos aumenta a cinco identificadores en tablet vertical.** `resources/views/catalagos/codificacion-form.blade.php:676–677` pasa el grid de identificación a cinco columnas a 768px; usa minmax(0,1fr) y campos min-width:0 (:688), por tanto puede caber sin overflow pero truncar contenido útil. Propuesta: probar códigos/nombres largos y ajustar a 2–3 columnas según ancho real. La progresión general 1/2/4/6 columnas en :665–685 es una fortaleza.

## Matriz de validación por tamaño

Cada celda es una prueba prioritaria propuesta; **ninguna expresa aprobación/reprobación visual**.

| Grupo | 390×844 | 768×1024 | 1024×768 | 1280×800 | 1440×900 |
|---|---|---|---|---|---|
| Catálogos y calendarios | Fechas, tablas, navbar | Identificadores y filtros | Alturas 600/640px | Tablas y modales | Datos largos |
| Codificación / L.Mat | Tabla/foco/teclado | Edición entre columnas | Modal y teclado | Tabla completa | Errores y cierre |
| Programa/Muestras/Liberar | Identidad de fila y confirmación | Grilla y selección | Scroll nested y acciones | Columnas y congeladas | Columnas completas |
| Balanceo | Gantt 9px y teclado | Gantt/tabla | Footer altura limitada | Gantt amplio | Detalle completo |
| Mover órdenes | Ancho mínimo/alternativa táctil | Ancho mínimo/arrastre | Ancho mínimo/altura | Margen disponible | Arrastre y footer |
| Ventas | Columna sticky/chips | Filtros y tabla | Comparativo apilado | Comparativo paralelo | Todas las gráficas |
| Costos | Grid de cálculo/teclado | Cuatro columnas edición | Scroll diálogo | Navbar/tabla | Datos y validación |

## Pendientes visuales

- Visitar cada ruta principal con sesión y permisos, en los cinco tamaños; registrar captura y tamaño real del contenedor, scrollWidth/clientWidth global e internos.
- Abrir modales y desplegables; comprobar cierre, foco, acciones finales, scroll interno y errores con textos largos.
- Usar datos reales, incluyendo BOM/nombres extensos, varias órdenes y valores negativos; tablas vacías no bastan para medir.
- Probar móvil/tablet con toque y teclado virtual: emulación de ancho por sí sola no valida arrastre ni teclado nativo.
- Separar scroll horizontal intencional de tabla de un desbordamiento de página. No marcar tabla amplia como fallo automático.
- Comparar programa actual y v2 solo si flag habilitado; Muestras reutiliza vista pero debe probarse con sus datos propios.

## Evidencia runtime añadida tras la inspección estática

Se inspeccionaron mediante `view_image` las **40 capturas originales** de ocho pantallas a 390×844, 768×1024, 1024×768, 1280×800 y 1440×900, y sus JSON del batch del agente principal. Esta sección actualiza el alcance inicialmente estático sin convertir riesgos de modales no abiertos en defectos ejecutados. No se controló el navegador desde este agente.

Directorio de evidencia: `/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/`. Cada archivo `.png` tiene un `.json` del mismo nombre. Los originales pueden actualizarse durante el batch; las observaciones siguientes describen la imagen inspeccionada en este pase.

**Precauciones de interpretación:** Debugbar añade la franja inferior roja/blanca local y contamina los conteos brutos de controles; no se atribuye a producción. En todos los 40 JSON, `document.clientWidth` y `document.scrollWidth` coinciden con el ancho objetivo: no se observó desbordamiento horizontal del documento. Eso no demuestra que todos los controles sean visibles: el clipping del navbar es independiente. Una tabla `outside` o de ancho mayor al viewport tampoco demuestra overflow global; varias tienen scroll interno deliberado. No se asignan porcentajes de responsividad ni se aprueban flujos que no se ejecutaron.

| Pantalla | 390×844 (original inspeccionado) | 768×1024 | 1024×768 | 1280×800 | 1440×900 |
|---|---|---|---|---|---|
| Programa tejido | Título solapa logo/primer botón; acciones posteriores fuera de pantalla; solo Estado/Reprogramar/Telar/Último visibles inicialmente | Título solapa logo; fila completa de iconos muy densa | Título separado del logo; más columnas iniciales legibles | Cabecera separada y grilla amplia | Cabecera separada y grilla amplia |
| Codificación | Título solapa logo/Peso M; L.Mat al borde; estado cargando, sin filas para evaluar | Título solapa logo/Peso M; acciones derechas cortadas; estado cargando | Título solapa logo/Peso M; estado cargando | Filas presentes en original inspeccionado; texto de grilla muy pequeño; título separado | Filas presentes; más columnas visibles; texto de grilla muy pequeño |
| Alineación | Título solapa logo; datos de Telar/Orden/Fecha legibles, siguientes columnas fuera de área inicial | Navbar separado; datos iniciales legibles | Navbar separado; más columnas | Navbar separado; tabla amplia | Navbar separado; tabla amplia |
| Utilería | Dos cards apiladas, títulos y texto legibles; navbar sin solape significativo | Dos cards lado a lado, legibles | Cards y cabecera legibles | Cards y cabecera legibles | Cards y cabecera legibles |
| Catálogo Telares | Título en tres líneas encima logo y llega al límite de cabecera; últimos botones recortados; las tres columnas de datos caben | Título en dos líneas, sin solape con logo; las tres columnas caben | Título separado, tabla legible | Título separado, tabla legible | Título separado, tabla legible |
| Matriz calibres | Título en tres líneas encima logo y arranque del contenido; Paro recortado; filtros apilados y legibles; columnas derechas requieren scroll | Título de tarjeta truncado, navbar legible; tabla parcial | Navbar/filtros y nueve columnas caben | Navbar/filtros y nueve columnas caben | Navbar/filtros y nueve columnas caben |
| Ventas | Título en cuatro líneas invade tabs; spinner, no tabla/gráfica para validar | Título y tabs legibles; spinner | Título y tabs legibles; spinner | Título y tabs legibles; spinner | Título y tabs legibles; spinner |
| Costos/Cuotas | Título solapa logo; Calcular Urdido cortado y acciones siguientes no visibles; selectores STD/Real, Directo/Absorbente y año caben | Título solapa logo; acciones finales fuera del área visible; selectores caben | Navbar separado; acciones y selectores legibles | Navbar separado; acciones y selectores legibles | Navbar separado; acciones y selectores legibles |

### Hallazgos runtime concretos

- **P1 — Navbar compartido superpone títulos y recorta acciones en móvil.** Evidencia exacta: `programa-tejido-390.png`, `codificacion-390.png`, `catalogo-telares-390.png`, `matriz-calibres-390.png`, `ventas-390.png`, `costos-390.png`. El título de Ventas invade el contenido de tabs, mientras Programa/Codificación/Cuotas pisan logo o primeras acciones. En `programa-tejido-390.json`, botones de ocultar columnas/filtros quedan medidos a x393 y x673 respectivamente, de 36×36px, fuera de un viewport390. **No se afirma que sean imposibles de alcanzar por un scroll no ejecutado**; sí quedan fuera de la presentación inicial. Propuesta: navbar de dos niveles o menú de acciones con título limitado y espacio táctil consistente.
- **P1/P2 — El problema continúa en tablet vertical para pantallas con muchas acciones.** `programa-tejido-768.png`, `codificacion-768.png`, `costos-768.png` mantienen título solapado con logo. En Programa ya se muestran sus iconos, pero no hay espacio limpio para título. En Costos y Codificación las acciones finales quedan cortadas. A 1024 Programa/Cuotas mejoran; Codificación todavía solapa su título. Priorizar barra de acciones por módulo y tamaño real disponible, no solo breakpoint de tablet.
- **P2 — Objetivos táctiles reales insuficientes en Programa.** JSON `programa-tejido-390.json` y `programa-tejido-1024.json` registran iconos del navbar de 36×36px y checkbox de tabla 16×16px. El checkbox puede tener interacción por fila adicional; el batch no la ejecutó, por lo que el tamaño pequeño del input no equivale automáticamente al tamaño total del objetivo. Aun así los botones de navbar sí son targets compactos. No se utiliza `undersizedCount` bruto porque incluye Debugbar y controles no operables.
- **P2 — Matriz calibres transforma correctamente filtros, pero no la edición de tabla.** En `matriz-calibres-390.png`, búsqueda/tipo/limpiar se apilan con texto legible. La tabla sigue horizontal: JSON mide ancho900px mientras documento390px. A 768 el título secundario se trunca; a 1024/1280/1440 las nueve columnas son visibles en la captura. Esto distingue adaptación parcial útil de fallo global inexistente.
- **P2 — Costos conserva una tabla2332px para todos los tamaños.** Los cinco `costos-*.json` miden tabla2332px y documento ajustado al viewport. `costos-390.png` muestra Depto/Año/Mes/Minutos, pero las cuotas monetarias quedan fuera del área inicial. Los selectores de la pantalla sí se adaptan. Recomendar resumen de cuota y detalle, manteniendo scroll de tabla para escritorio. No se abrió Calcular Urdido: su grid sigue como riesgo estático.
- **Limitación del primer pase, resuelta para listado estable — Estados cargando no son mediciones de filas ni de rendimiento.** `ventas-390.png` a `ventas-1440.png` permanecen con spinner en el pase inspeccionado. `codificacion-390.png`, `codificacion-768.png`, `codificacion-1024.png` también; originales1280/1440 sí contienen filas. No inferir que carga nunca termina ni atribuir las diferencias al viewport: son capturas de momentos distintos. La recaptura estable posterior, documentada abajo, ya valida datos de ambos listados en los cinco tamaños; este párrafo conserva solo el contexto de las primeras capturas.
- **Fortaleza observada — Utilería inicial y Telares tienen contenido legible en los cinco tamaños.** `utileria-390.png` y `utileria-768.png` confirman transición de cards apiladas a paralelas. `catalogo-telares-390.png` muestra Salón/Telar/Nombre juntos con filas legibles. La altura640px produce espacio libre en tablets altas, pero la captura no confirma bloqueo de acceso a filas; no se eleva ese riesgo a defecto medido.

### Pendientes que permanecen abiertos

Las mediciones iniciales no incluyen abrir Finalizar, L.Mat, Liberar, Balancear ni alta/edición de Costos. Posteriormente se inspeccionó el estado inicial abierto de Mover, sin seleccionar órdenes ni guardar; véase la ampliación siguiente. Siguen pendientes el arrastre real touch, teclado virtual, foco/validación, persistencia, acceso mediante scroll al resto de acciones y tablas, permisos diversos, Muestras, calendario/modelos y los paneles históricos/desplegados de Ventas. El listado estable de Ventas/Codificación ya se inspeccionó en los cinco tamaños, como se detalla abajo. Los hallazgos estáticos anteriores conservan ese estado.


### Ampliación runtime: modal Mover órdenes (apertura sin cambios)

Se inspeccionaron con `view_image` los originales `mover-ordenes-inicial-390.png`, `mover-ordenes-inicial-768.png`, `mover-ordenes-inicial-1280.png`, además de sus JSON. El agente principal abrió el modal y consultó su estado inicial; no seleccionó telares, movió órdenes ni guardó.

**P1 — Defecto confirmado: modal y cierre quedan recortados en móvil y tablet vertical.** El riesgo estático del ancho mínimo1200px se confirma visualmente: a390 y768 solo aparece la sección central del modal, con el título/parte izquierda y cierre fuera del viewport. Los selects quedan cortados por ambos extremos. A1280 se ven título, cierre, ambos select y Guardar completo (deshabilitado en estado inicial).

| Evidencia exacta | Origen x / ancho | Destino x / ancho | Cierre x / ancho | Resultado observado |
|---|---|---|---|---|
| `mover-ordenes-inicial-390.png` + `.json` | −364 / 530px | 224 / 530px | 739 / 32px | Origen recortado a izquierda, destino a derecha, X fuera de pantalla |
| `mover-ordenes-inicial-768.png` + `.json` | −175 / 530px | 413 / 530px | 928 / 32px | Ambos paneles recortados; X fuera de pantalla |
| `mover-ordenes-inicial-1280.png` + `.json` | 73 / 538px | 669 / 538px | 1192 / 32px | Modal completo dentro del viewport en esta apertura |

Los tres documentos mantienen `scrollWidth == clientWidth`; por tanto la ausencia de overflow global no detecta este clipping. La franja Debugbar inferior sigue siendo instrumental local. No se probó cerrar por backdrop/Escape ni arrastrar con toque, por lo que esos comportamientos permanecen pendientes. Propuesta prioritaria: quitar el mínimo1200px del panel, apilar o dividir origen/destino en pasos para ancho pequeño, conservar X/acciones dentro del viewport y scroll de cuerpo. No hay medición nueva a1024 ni1440 del modal abierto.


### Recapturas estables: Codificación y Ventas con datos

Se inspeccionaron con `view_image` los diez originales `codificacion-estable-{390,768,1024,1280,1440}.png` y `ventas-estable-{390,768,1024,1280,1440}.png`, y sus JSON. **Sustituyen la limitación del spinner para evaluar estos listados**, conservando las primeras capturas como evidencia histórica. No se abrieron subpaneles, filas desplegables, filtros ni modales en este pase.

| Listado estable | 390×844 | 768×1024 | 1024×768 | 1280×800 y 1440×900 |
|---|---|---|---|---|
| Codificación | Datos presentes; inicialmente solo Orden/Principal/Peso/Fecha. Navbar título solapa logo/Peso M, acciones derechas fuera de vista | Datos presentes, más columnas; título y acciones siguen solapados/recortados | Datos presentes; título solapa logo/Peso M | Datos presentes, navbar título separado; grilla mantiene tipografía muy compacta |
| Ventas resumen | Datos presentes, etiqueta Empresa/Tipo/Cliente ocupa250px y queda una columna Piezas completa y parte de Kg; título cuatro líneas invade tabs | Datos presentes, grupo Plan visible y parte Pedido; navbar separado | Datos presentes, Plan y parte Pedido | Datos presentes, Plan/Pedido y parte Real; navbar separado |

Mediciones verificadas: los cinco JSON de Codificación registran ancho de tabla**17466px**, **501 filas DOM** (incluye encabezado, no se presenta como501 registros de negocio). Ventas tiene **5 filas DOM**, tabla**2248px a390** y **2218px en los otros tamaños**. En los diez documentos se mantiene `scrollWidth == clientWidth`, por tanto la amplitud de las tablas corresponde a superficie interna y no prueba desbordamiento del documento.

El riesgo estático de primera columna grande en Ventas queda confirmado en la presentación móvil estable: en `ventas-estable-390.png`, la columna izquierda fija250px deja aproximadamente140px para datos en un viewport390; el resumen requiere desplazamiento horizontal para comparar Plan/Pedido/Real. No se probó el gesto de desplazamiento ni gráficos históricos. En `ventas-estable-390.png` también se confirma que la invasión del título sobre tabs continúa después de cargar datos; no es causada por el spinner.
