# Responsividad: Urdido, Engomado, Programa Urd/Eng y Atadores

Fecha: 2026-10-10. Auditoría del código vigente más revisión posterior de capturas y mediciones de navegador producidas por el coordinador. No se modificó la aplicación. Los riesgos estáticos y los resultados visuales se presentan por separado. Este agente no controló el navegador.

## Cobertura e inventario

Fuentes de navegación: `routes/modules/urdido.php`, `engomado.php`, `programa-urd-eng.php`, `atadores.php`; controladores de esos módulos; vistas y TypeScript/CSS asociados. Se excluyen endpoints JSON, acciones de escritura y exportaciones como pantallas independientes.

| Submódulo | Rutas/pantallas principales inventariadas | Cobertura del código y fortalezas | Riesgos a verificar |
|---|---|---|---|
| Urdido: tablero y edición | `/urdido/programaurdido`, `/urdido/programar-urdido`, `/urdido/reimpresion-urdido`, `/urdido/editar-ordenes-programadas`, ventana de impresión | `livewire/urd-eng/program-board`, `edicion-ordenes`, `edicion-orden`; CSS y JS compartidos. Breakpoints de tablero, ResizeObserver de cabecera, controles táctiles del tablero, formulario auto-fit | Dos máquinas por fila a 768/1024; tabla de 48rem; prioridad por arrastre HTML nativo en modal; apertura de impresión |
| Urdido: producción y calificar julios | `/urdido/modulo-produccion-urdido`, modal de calificación | Vista y parciales `_header-orden`, `_tabla-registros`, `_modal-oficial`; JS producción y calificar-julios. Scroll interno, encabezados fijos y cabecera apilada bajo md | Tabla mínima 1120px; horas/hilos ocultos bajo lg; encabezado con columnas estrechas desde md; teclado y doble scroll |
| Urdido: catálogos/materiales | `/urdido/configuracion/catalogosjulios`, `/urdido/configuracion/catalogosmaquinas`, aliases `/catalogos-julios`, `/catalogo-maquinas`, `/urdido/lmaturdido` | Wrappers legacy a Livewire, `catalogo-julios`, `catalogo-maquinas`, `lista-materiales`; `x-tabla`, Flux, diálogos comunes y entradas decimales | Comprobar tabla compartida con datos largos, filtros, diálogos y teclado; no se ha medido |
| Urdido: reportes | `/urdido/reportesurdido`, `03-oee-urd-eng`, `kaizen`, `roturas-millon`, `bpm-urdido`, `resumen`, `panel-control` | Selector e inventario de vistas `reportes-*`; parciales comunes de rango/resumen | Reportes densos, filtros y gráficas requieren prueba propia por tipo; inventario no equivale a inspección visual exhaustiva |
| Engomado: tablero, edición, producción | `/engomado/programaengomado`, `/engomado/programar-engomado`, `/engomado/reimpresion-engomado`, `/engomado/editar-ordenes-programadas`, `/engomado/modulo-produccion-engomado` | Tablero/edición Livewire compartido; producción legacy inspeccionada. Modal con max-h/scroll y campos de 1 a 3 columnas | Cabecera de cinco columnas desde 768; tabla max-content con muchas columnas y 62vh; teclado |
| Engomado: fórmula, catálogos, reportes | `/engomado/capturadeformula`, alias `/captura-formula`, `/eng-formulacion`; `/engomado/configuracion/catalogodenucleos`, alias `catalogos-nucleos`, `catalogojulioseng`, `catalogo-ubicaciones`; `/urd-eng-nucleos`; `/engomado/reportesengomado`, `bpm-engomado`, `control-merma`, `resumen-engomado` | Fórmula usa tabla común/diálogos, creación 1→3 columnas, botón calidad 48×44; núcleos/ubicaciones con componentes UI. Inventario de reportes | Modal de fórmula 90vh; tabla calidad con overflow-hidden; ubicaciones solo declara scroll vertical; revisar datos largos |
| Programa Urd/Eng | `/programaurdeng`, `/programa-urd-eng/reservar-programar`, `/programacion-requerimientos`, `/creacion-ordenes`, `/karl-mayer` bajo ese prefijo; aliases de entrada | Reservar-programar: paneles flex, scroll interno, filtros wrap y ajuste móvil. Requerimientos: tablas desplazables. JS modular y acciones táctiles | Creación mantiene paneles estrechos sin breakpoint; KM tiene paneles 220/260px y pie fuera del scroll; dropdown fijo requiere teclado/orientación |
| Atadores: programa y captura | `/atadores/programaatadores`, `/produccionProceso/atadores`, `/atadores/iniciar`, `/atadores/calificar`, `/atadores/calificar/montado`, `/atadores/calificar/enhebrado` | Programa con tabla-pantalla; calificar usa grids 1→2→3, Montado/Enhebrado 1→2 lg, selects con min-w-0; JS incorpora menú por acciones táctiles | Devolución con observaciones 36rem; fechas KM siempre 2 columnas; botón asignarme 32×32; notas 2 columnas fijas |
| Atadores: catálogos/reportes | `/atadores/catalogos/actividades`, `/comentarios`, `/maquinas`; `/atadores/reportes-atadores`, `/programa`, `/atadores`, `/km` | Vista CRUD común `modulos/catalogos-atadores/index`; inventario de reportes. KM tiene tablas con overflow-x-auto y grid 1→3 xl | Densidad de reportes y gráfica en tamaño móvil; diálogo CRUD y textos largos sin medición |

## Hallazgos estáticos concretos

Todas las referencias siguientes son líneas comprobadas en el árbol de trabajo; severidad indica prioridad de verificación, no un fallo observado.

1. **Alta prioridad: creación de órdenes no apila sus paneles.** `resources/views/modulos/programa_urd_eng/creacion-ordenes.blade.php:84-87` define `flex` con panel `w-1/5`, y `:173-174` usa `w-1/6`. A 390px esos paneles parten de unos 78/65px antes de espacios y reducción del contenedor: las tablas pueden seguir funcionando mediante desplazamiento, pero la ventana útil resulta muy pequeña. A 768px sigue siendo estrecha. Recomendación: apilar en móvil y tablet vertical o dar una base mínima al panel, manteniendo scroll de tablas. Verificar con órdenes reales.

2. **Alta prioridad: captura de urdido conserva una tabla mínima de 1120px.** `resources/views/modulos/urdido/produccion/_tabla-registros.blade.php:29-30` limita el alto a 55vh y declara `min-w-[1120px]`. En 390/768/1024 necesariamente se depende de desplazamiento horizontal local; eso no prueba desbordamiento de la página. `:59-62` oculta horas e hilos antes de lg. Probar que el operario puede completar toda la fila y localizar esos datos al cambiar de orientación. Considerar un editor por fila para móvil.

3. **Alta prioridad: engomado activa cinco columnas de cabecera demasiado pronto para datos largos.** `resources/views/modulos/engomado/modulo-produccion-engomado.blade.php:58-60,107` activa cinco tracks desde 768px; `:111-175` mantiene labels no-wrap de 90–100px. Los tracks menores recibirían unos 130px a 768px antes de padding/gaps. Verificar proveedores, cuentas y nombres largos; considerar 2/3 columnas en tablet vertical y 5 solo con espacio suficiente.

4. **Alta prioridad: devolución KM exige una columna más ancha que un móvil.** `resources/views/modulos/atadores/calificar-atadores/index.blade.php:527-530` fija celda de observaciones a 36rem e input mínimo 34rem, dentro de wrapper desplazable (`:486`). La celda sola equivale a 576px con raíz 16px. Verificar navegación horizontal y edición con teclado; un textarea/editor por fila puede reducir el recorrido sin perder contenido.

5. **Media prioridad: el calendario KM queda en dos columnas y asignarme es compacto.** `resources/views/modulos/atadores/calificar-atadores/_proceso-km.blade.php:11-24` deja fechas datetime-local en dos columnas sin breakpoint. `:47-49` da al botón asignarme `h-8 w-8` (32×32px en raíz 16px), sin clase táctil común. Verificar widget nativo de fechas, texto visible y tamaño computado real a 390px. Proponer una columna en móvil y área táctil de al menos 44px si no hay otra regla que lo cubra.

6. **Media prioridad: editar prioridad usa HTML drag-and-drop, distinto del tablero principal.** `resources/js/urd-eng/priority-sort.ts:11-59` escucha dragstart/dragover/drop/dragend. El tablero usa SortableJS con handle (`resources/js/urd-eng/sortable-board.ts:32-40`). No hay equivalencia táctil visible en ese archivo del modal. Debe probarse en dispositivo táctil real; contemplar SortableJS o botones subir/bajar accesibles para ese flujo.

7. **Media prioridad: alcance de teclado de modales aún necesita medición.** `resources/css/urd-eng/program-board.css:17-19` usa máximo basado en 100vh con scroll; producción Engomado `:697` usa 90vh y `:810` 65vh; fórmula `resources/views/modulos/engomado/captura-formula/index.blade.php:192` usa 90vh. Estas restricciones no prueban que el foco y confirmar permanezcan visibles con teclado Android/iOS. Probar visualViewport, scroll al campo, cierre y confirmación en 390 y landscape.

8. **Media prioridad: cabecera de urdido encoge a seis partes desde md.** `resources/views/modulos/urdido/produccion/_header-orden.blade.php:13-15,40,72` divide en spans 2+2+4+4 desde 768px, con labels no-wrap y una tabla interna fixed (`:75-81`). A 768 las primeras columnas rondan 128px antes de gaps, y julio dispone de unos 256px aunque encabezados declaran 80+70+180px. Verificar compresión, clipping y largos. A 390 el apilado existe y es fortaleza, aunque consume alto antes de la tabla.

9. **Media prioridad: tablero urdido retiene dos máquinas por fila en tablets y un mínimo ancho de tabla.** `resources/css/urd-eng/program-board.css:705-708` solo colapsa bajo 700px; `:522-526` fija mínimo 48rem, por lo que la tabla se desplaza dentro de media pantalla en 768/1024. `:716-718` fija cada panel a clamp(18rem,42dvh,30rem). Es un diseño deliberado, no un defecto probado: medir cuánto ve el operador por máquina y si cambiar a una columna mejora tablet vertical sin perjudicar escritorio.

10. **Media prioridad: notas Atadores permanecen en dos columnas en móvil.** `resources/views/modulos/atadores/calificar-atadores/index.blade.php:682,691,705` mantiene grid de dos columnas con gap-6 y tarjetas de padding horizontal. El texto puede envolver, pero reduce mucho el ancho útil a 390px y alarga la página. Verificar lectura con notas reales; considerar 1→2 sm/md.

## Fortalezas comprobadas que deben conservarse

- `resources/js/urd-eng/responsive.ts:16-38`: mide cabecera real mediante ResizeObserver y ajusta padding/variables de layout, evitando asumir siempre un alto fijo.
- `resources/css/urd-eng/program-board.css:771-814`: Engomado pasa a tablas auto/max-content con scroll bajo 1024 y preserva observaciones; `:831-846` aumenta controles del tablero en pointer coarse a 44px. No se puede generalizar ese beneficio a todos los controles legacy.
- `resources/css/urd-eng/edicion-ordenes.css:141-142,192-201`: grilla de máquinas a una columna bajo 1200, ajuste táctil de celdas y alto basado en viewport dinámico.
- `resources/views/livewire/urd-eng/edicion-orden.blade.php:82`: formulario `auto-fit, minmax(9rem,1fr)`; tablas envueltas en overflow-x-auto (`:255,298`).
- `resources/views/modulos/programa_urd_eng/reservar-programar.blade.php:281-284,347-349`: paneles con min-width/min-height cero y scroll local, móvil con altura automática y tope 70dvh.
- `resources/views/modulos/engomado/captura-formula/index.blade.php:424,435`: calidad con botón 48×44 e input min-h-touch.

## Matriz de verificación pendiente

| Perfil | Pruebas prioritarias en estos módulos | Estado |
|---|---|---|
| 390×844 | Crear órdenes, devolver KM, fechas KM, abrir modal fórmula/oficial, foco/teclado y notas | Pendiente de navegador; hallazgos solo estáticos |
| 768×1024 | Cabeceras producción Urdido/Engomado, tablero 2 columnas, creación y formularios con datos largos | Pendiente |
| 1024×768 | Cambio de régimen del tablero Engomado en 1024, aparición de horas/hilos lg, alcance vertical con teclado | Pendiente |
| 1280×800 | Edición 2 máquinas, tablas de producción y prioridad, scroll interno del panel reservar | Pendiente |
| 1440×900 | Base escritorio y comparación de todas las acciones frente a tablet | Pendiente |

Métricas a tomar: ancho/alto del viewport y de main, scrollWidth−clientWidth de página y wrappers de tabla, rectángulos de objetivos táctiles, controles inaccesibles/ocultos, visibilidad del foco y botones con teclado, tiempo para completar una fila sin guardar cambios de prueba. Overflow local previsto se registra por separado del overflow global. Una emulación de viewport no sustituye teclado y touch del dispositivo real.

## Recomendación

Priorizar una prueba autenticada de creación de órdenes y captura de producción a 390/768, seguida de devolución y fechas KM. La base Livewire/Flux y el tablero compartido ya tienen adaptación explícita; las diferencias mayores están en pantallas legacy con paneles fraccionales y formularios tabulares. NativePHP no cambia por sí mismo esos contratos de ancho y alto. No se propone migración ni cambio de framework para resolver estos riesgos.

## Revisión runtime de capturas actuales

Se revisaron visualmente los PNG de **390×844, 768×1024 y 1440×900** de las cuatro pantallas siguientes y se leyeron los JSON de los cinco perfiles (20 mediciones). Capturas base: `/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/`. Los archivos PNG/JSON llevan prefijo de pantalla y sufijo de ancho. La barra inferior Laravel Debugbar pertenece al entorno local; no se computa como problema del producto. Los totales automáticos de controles pequeños incluyen Debugbar y elementos en scroll, por lo que no se usan como puntaje.

| Pantalla | 390px: observación visual | 768px: observación visual | 1440px: observación visual |
|---|---|---|---|
| `urdido-programa` | Cabecera en dos filas, acciones solo iconos; paneles apilados. Se ven prioridad, folio, tipo y cuenta; columnas finales necesitan scroll local. Primer panel tiene una fila y gran espacio vacío | Cuatro máquinas en dos columnas; cada tabla solo muestra primeras columnas. Estado vacío KM aparece cortado dentro de su scroll. Navbar conserva textos sin solaparse | Cuatro máquinas 2×2; más columnas visibles, todavía hay scroll horizontal por panel. Navbar y alerta de paro legibles |
| `engomado-programa` | Navbar se adapta en dos filas; máquinas apiladas y scroll local de tablas. Segundo panel requiere bajar; status/observaciones quedan a la derecha | Dos máquinas apiladas; casi toda la fila visible excepto extremo de observaciones. Sigue scroll local y alto considerable para tres filas | Tablas ocupan ancho completo; status y observaciones visibles. Navbar cabe y alertas son legibles |
| `programa-urd-eng` | **No se ven filas de las tablas**, solo cabeceras de paneles y filtros; logo/título se superponen. Reservar aparece parcialmente; Liberar y Programar quedan fuera de viewport | Filas de programación visibles y contenidas; inventario muestra estado sin datos. **Título se superpone con logo y acción Karl Mayer** | Cabecera bien separada, tabla de programación con selección visible; inventario vacío legible |
| `atadores-programa` | Filas legibles en primeras columnas mediante tabla local. **Título se superpone con logo y acciones; iniciar atado fuera de viewport** | Tabla muestra hasta julio y parte de ubicación. **Título sigue superpuesto con logo/acciones**; iniciar atado visible | Navbar completa cabe; columnas abundantes legibles y scroll local para resto |

### Hallazgos visuales prioritarios

1. **Programa Urd/Eng a 390px: región útil de tablas no aparece en captura.** Evidencia `programa-urd-eng-390.png`, contrastada con `programa-urd-eng-390.json`: `telaresTable` contiene 19 filas y mide 1129×1014, pero la imagen muestra únicamente la cabecera oscura/filtros y cabecera azul de inventario, seguida de área azul vacía. No se infiere que faltan datos: están en DOM. Debe corregirse primero el contrato de alto/flex de los paneles móviles y volver a comprobar que se puede leer/seleccionar una fila sin navegación adicional. El riesgo específico de creación de órdenes sigue sin validarse: esta captura corresponde a reservar-programar.

2. **Navbar Programa Urd/Eng y Atadores: recorte y solape comprobados.** Evidencia `programa-urd-eng-{390,768}.png` y `atadores-programa-{390,768}.png`. En Urd/Eng móvil los rectángulos medidos son Reservar x322/ancho106, Liberar x436/ancho96 y Programar x540/ancho119: exceden viewport390. Atadores mide Iniciar Atado x520/ancho133 a 390. El documento no aumenta su ancho y el layout los recorta; por tanto, ausencia de overflow global no significa acceso completo a acciones. Recomendación: segunda fila, menú de acciones o ajuste responsive común, verificando también título/logo.

3. **Tablero Urdido: recorrido horizontal por máquina persiste en escritorio y crece en tablet.** Evidencia `urdido-programa-{390,768,1440}.png/.json`. Tablas medidas entre 768 y 832px; a 768 cada panel ocupa aproximadamente media pantalla y solo se ven primeras columnas, obligando a desplazarse para status/observaciones. En 1440 aún existe scroll por panel. Esto es scroll local contenido, no desbordamiento global ni necesariamente fallo funcional. Priorizar editor de fila o una columna en tablet vertical si las tareas frecuentes necesitan esas columnas.

4. **Objetivos compactos medidos en tablero con perfil de navegador usado.** JSON móvil Urdido registra handle de prioridad 18×21px y select status120×27px; Engomado handle16×20px y status120×25px. CSS dispone de reglas pointer-coarse44px, pero estas mediciones no demuestran que ese media query estuviera activo. Es evidencia de tamaño bajo el perfil capturado, no medición definitiva de tablet real. Verificar pointer coarse/touch real antes de afirmar incumplimiento general.

### Ancho global medido y límites

En **las veinte mediciones**, `document.scrollWidth === document.clientWidth`: 390,768,1024,1280 y1440 respectivamente. No se detectó overflow horizontal global en estos estados. Sí hay tablas mayores que su ventana de scroll: Urdido768–832px; Engomado869px bajo1024 y ancho adaptable por encima; Programa Urd/Eng1129px en390/768/1024; Atadores1171px en390 y1497px en768–1440. Esa amplitud local se trata como decisión de navegación, no como fallo automático.

Estados revisados: Urdido con una orden por tres máquinas y KM vacío; Engomado con tres órdenes por máquina; programación con19 filas de tabla y sin inventario; Atadores20 filas. No se abrió producción, fórmula, fechas, modal prioridad o devolución, ni se levantó teclado. No se validó selección/guardado/arrastre ni se afirmó que los botones recortados fueran inaccesibles por todas las alternativas. Los diez riesgos de código continúan pendientes salvo las partes de tablero y navbar explícitamente comprobadas aquí.
