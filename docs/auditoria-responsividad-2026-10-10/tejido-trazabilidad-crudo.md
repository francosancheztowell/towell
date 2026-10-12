# Tejido, Tejedores/Desarrolladores, Crudo, Producto terminado y Trazabilidad

Fecha: 10 de octubre de 2026. Revisión de código, sin modificar la aplicación. Se leyeron AGENTS.md, las rutas de los cinco módulos, controladores para resolver vistas, plantillas representativas, estilos específicos y JavaScript. No se utilizaron auditorías anteriores como evidencia.

## Alcance y límites

Perfiles solicitados: 390×844, 768×1024, 1024×768, 1280×800 y 1440×900, en píxeles CSS. Este agente no controló navegador; posteriormente revisó PNG originales y JSON obtenidos por el agente padre. Las secciones de código conservan riesgos estáticos; la sección runtime distingue las mediciones reales. No hay puntajes de responsividad ni tiempos de carga inventados.

## Matriz de cobertura

| Submódulo y rutas principales | Archivos examinados | Fortalezas en código | Riesgos y cobertura pendiente |
|---|---|---|---|
| Inventario telas: `/tejido/inventario-telas`, `/jacquard`, `/itema`, `/karl-mayer`; `/inventario-telares` | `modulos/tejido/inventario-telas/inventario-telas.blade.php`, `resources/css/tejido/inventario-telas.css`, JS `tejido/inventario-telas.ts` | `min-width:0`, filas apiladas bajo 900, calendario de 7 columnas flexibles | Dos columnas persisten en móvil; verificar campos y fechas largas, calendario táctil y Karl Mayer con varios julios |
| Inventario trama: `/tejido/invtrama`, `/tejido/inventario/trama/nuevo-requerimiento`, `/consultar-requerimiento` y resumen | Envoltorios Blade y Livewire `inventario-trama/nuevo-requerimiento`, `consultar-requerimiento` | Formulario 1/2/3 columnas, scroll horizontal local, modal con scroll vertical | Rail lateral permanente consume ancho móvil; tabla mínima 640 px |
| Marcas finales: `/modulo-marcas`, `/consultar`, `/{folio}`, `/reporte`, `/visualizar/{folio}` | `modulos/marcas-finales/nuevo-marcas`, `marcasFinales`, `reporte-marcas`; JS nuevo/consulta/reporte | Tabla del reporte en contenedor `overflow-auto` | Reporte usa `w-screen h-full overflow-hidden`; validar toolbar, scroll y operaciones por fila |
| Cortes eficiencia: `/modulo-cortes-de-eficiencia`, `/consultar`, `/{id}`, `/visualizar/{folio}` | `modulos/cortes-eficiencia/cortes-eficiencia`, consulta/visualización; JS captura/observaciones | Scroll local de tabla, encabezados sticky | Captura extensa horizontalmente; acción de tomar hora pequeña; riesgo de scroll anidado y teclado |
| Reenconado: `/tejido/produccion-reenconado` (alias `/produccion/reenconado-cabezuela`) | `modulos/produccion-reenconado-cabezuela.blade.php`, JS reenconado | Datos generales 2 columnas móvil; modal con scroll vertical | Producción conserva 5 columnas incluso móvil |
| Secuencias: inv telas, inv trama, marcas finales, corte eficiencia | `modulos/tejido/secuencia/comun.blade.php` y cuatro variantes, JS secuencia | Tabla con overflow horizontal y vertical | Modal usa 2 columnas sin breakpoint; ordenamiento táctil necesita prueba |
| Reportes Tejido: inv telas, promedio paros/eficiencia, marcas finales, saldos 2026, RPM semanal | Cinco reportes y parcial rango de fechas | Contenedores de tabla con overflow en inv telas, saldos y RPM | Tablas analíticas extensas; verificar acceso a filtros/exportar, encabezados sticky y desplazamiento local |
| Desarrolladores y muestras: `/tejedores/desarrolladores`, `/tejedores/desarrolladores-muestras` | Envoltorios y Livewire `desarrolladores/captura.blade.php` | Campos 44 px, detalle como fichas bajo 768, grid 1/2/3/4 columnas, barra de acciones sticky | Alerta saldo sin límite vertical; a 768 pasa a tabla mínima 896 px |
| Tejedores: `/tejedores/atadodejulio`, `/cortadoderollo`; reportes programa | `modulos/notificar-montado-julios/index`, `tejedores/notificar-mont-rollos/index`, reportes | Montado usa grid 2/4 columnas; flujos de selección y detalle | Validar nombres largos, acciones finales y scroll con teclado en capturas |
| Catálogos Tejedores: `/tejedores/configurar/catalogo-calibres`, `/configurar/telaresxoperador`, `/tel-telares-operador` | Livewire catálogo calibres y `modulos/tel-telares-operador/index` | Catálogo modal scrollable, formulario 1/2 columnas | CRUD y asignación múltiple sin medición runtime |
| Crudo: `/Crudo`, auditorías y detalle | Livewire dashboard/machine-floor/machine-detail, componentes machine-card; CSS `crudo/dashboard.css`, JS dashboard | Breakpoints móvil/tablet/orientación, grids `minmax(0,…)`, modal móvil ancho completo | Controles locales de 36 px; reglas táctiles difieren según pointer; modales usan vh |
| Producto terminado: `/producto-terminado/tiempos` | `modulos/producto-terminado/tiempos/index.blade.php` | Paneles con scroll local, min-h-0/min-w-0 y filas con teclado | Layout de 2 columnas constante y ocultamiento horizontal; texto/valores sin espacio en móvil |
| Trazabilidad: `/trazabilidad` y detalles matriz/producción/flog/ventas | `modulos/trazabilidad/index`, parciales actuales, Livewire filtros; CSS y TS específicos | Grids de 1 a varias columnas, controles min-h-touch, matriz con scroll local y primer dato sticky, visor 100dvh | Tablas conservan densidad; resumen rollos 3 columnas constante; números largos y modal pendientes |

## Hallazgos sustanciales

1. **Alta prioridad — Producto terminado conserva el tablero de escritorio en móvil.** `resources/views/modulos/producto-terminado/tiempos/index.blade.php:72` fija `grid-cols-2 grid-rows-2` sin variante por ancho. Líneas 38 y 56 ocultan el desbordamiento horizontal y fijan altura `100vh-64px`; líneas 88–91 distribuyen cuatro columnas porcentuales dentro de medio viewport. No demuestra desbordamiento, pero la ausencia de scroll no prueba que la información sea legible. En 390 conviene una sola columna y paneles apilados, o selección de un panel. Verificar títulos truncados, cifras grandes y datos accesibles al tocar (el `title` en línea 106 no sustituye una interacción móvil).

2. **Alta prioridad — Reenconado mantiene cinco entradas numéricas en una misma fila.** `resources/views/modulos/produccion-reenconado-cabezuela.blade.php:206` tiene `grid-cols-5 gap-3` sin breakpoint; líneas 208–225 incluyen Cantidad, Cabezuela, Conos, Tiempo y Eficiencia. Los inputs usan `min-w-0`, así que pueden encogerse demasiado en 390, incluso si no hay overflow. Cambiar a 1/2 columnas móvil y 5 en escritorio. Probar decimales, teclado y botón guardar; la zona de formulario tiene scroll en línea 138.

3. **Alta prioridad — Cortes de eficiencia exige navegación horizontal extensa para captura.** `resources/views/modulos/cortes-eficiencia/cortes-eficiencia.blade.php:85`, `:88`, `:91`, `:118`, `:121`, `:124` asignan mínimos 80/100/120 y 100/100/80 px por horario. Tres horarios ya suman nominalmente 1.140 px mínimos (300 + 3×280; depende de resolución del navegador y contenido). La tabla no puede caber completa en 390 o 768. Hay overflow controlado en líneas 79–80, pero se necesita probar conservación del telar al desplazarse, lectura del horario y capturar observaciones. Preferir una vista de captura por telar/horario móvil, conservando la tabla analítica en escritorio.

4. **Prioridad media — Acción de tomar hora en Cortes carece de blanco táctil explícito.** `resources/views/modulos/cortes-eficiencia/cortes-eficiencia.blade.php:98–105`: botón `p-1` con icono `w-4 h-4`; nominalmente 24×24 px con escala Tailwind estándar, antes de estilos globales. Medir hitbox efectivo; llevarlo a 44×44 para uso operativo, sin aumentar innecesariamente la densidad de toda la tabla.

5. **Prioridad media — Inventario trama consume ancho móvil antes de la tabla.** `resources/views/livewire/inventario-trama/nuevo-requerimiento.blade.php:14` conserva rail de 86 px y línea 28 margen de 88 px, además del padding. Líneas 92–93 contienen tabla mínima 640 px en scroll local. En 390 la tabla requiere desplazamiento y la cabecera pierde espacio. Es un riesgo de eficiencia operativa, no un overflow del documento confirmado. Probar rail horizontal o encabezado compacto móvil y nombres de artículo largos en línea 66.

6. **Prioridad media — Alerta saldo de Desarrolladores no tiene estrategia vertical propia.** `resources/views/livewire/desarrolladores/captura.blade.php:761–765` centra una tarjeta con `overflow-hidden`, sin `max-height` ni `overflow-y-auto`. La alerta incluye texto y cuatro botones de 48 px en líneas 778–796. Pantalla con teclado, zoom o poca altura puede ocultar acciones. Usar límite basado en `dvh`, cuerpo scrollable y probar foco. El resto de la captura es una base fuerte: líneas 7 y 12–13 fijan campos 44 px y detalle en fichas móvil; líneas 454–455 pasan a tabla de 56 rem a partir de 768, cuyo scroll también debe validarse.

7. **Prioridad media — Crudo implementa adaptación amplia pero densidad táctil desigual.** `resources/css/crudo/dashboard.css:83`, `:108–109` fijan filtros/iconos a 2,25 rem (36 px con raíz 16), y `:1405–1418` define filtros de defectos con mínimo 1,6 rem y padding pequeño. No implica fallo WCAG por sí solo; comprobar separación y blanco efectivo. Para operadores, homogeneizar a 44 px. Las reglas de tablet cambian por `(hover:none) and (pointer:coarse)` en líneas 3987–3988: el mismo viewport con ratón y con touch puede renderizar diferente. Validar ambos, especialmente 1280×800.

8. **Prioridad media — Altura vh en Crudo exige prueba con teclado.** `resources/css/crudo/dashboard.css:1319–1321` usa modal de ancho `min(92rem,98vw)` y alto máximo `min(62rem,95vh)`; a menos de 520 px cambia a 94vh en línea 4741. No confirma recorte, pues hay otros contenedores scrollables, pero `vh` puede no reflejar el área visible con teclado. Probar comentario/auditoría, botones al final y retorno de scroll; valorar `dvh` o integración con área visual si la prueba reproduce el problema.

9. **Prioridad media — Inventario telas conserva dos columnas de información en teléfono.** `resources/css/tejido/inventario-telas.css:131–136` usa `repeat(2,minmax(0,1fr))`; bajo 639 solamente cambia el rail a 4,25 rem en líneas 377–380. Reduce bien anchos mínimos, pero falta una variante explícita de una columna para campos largos. En 390 verificar fechas, cuenta/calibre y varios julios de Karl Mayer. Fortalezas: filas de requerimiento se apilan bajo 900 en líneas 352–369, y pasa a 4 columnas de proceso a partir de 900 en líneas 158–164.

10. **Prioridad media — Trazabilidad necesita comprobar densidad dentro del modal, aunque la página sí cambia de grid.** `resources/views/modulos/trazabilidad/_modal_rollos_maquina.blade.php:9` conserva tres KPI por fila y líneas 24–32 una tabla de cinco columnas. Valores grandes en 390 podrían exceder sus tarjetas. La matriz de crudo sí es conscientemente horizontal: `resources/css/trazabilidad/index.css:75–86` usa mínimo por contenido/celda y la vista `_produccion_crudo_card.blade.php:71` contiene overflow-x-auto. El visor usa 100dvh en CSS línea 374 y tiene pointer/teclado en `resources/js/trazabilidad/flog-image-viewer.ts:91–107`; medir botones/zoom y retorno del scroll al cerrar.

## Pruebas visuales pendientes

Estas pruebas interactivas continúan pendientes aunque se haya medido la pantalla inicial de varios módulos en la sección siguiente.

En cada perfil solicitado registrar screenshot, ancho de documento versus viewport, ancho scrollable de cada tabla, rectángulos efectivos de controles, acceso a acciones y foco. El scroll lateral local de una tabla no debe contarse como fallo global; verificar que usuario identifique el desplazamiento y retenga la fila/telar relevante.

1. Abrir Tiempos con datos y elegir orden; comprobar cifras, truncado y paneles en 390/768.
2. Reenconado: abrir nuevo, escribir decimales en los cinco campos, desplegar teclado, llegar a guardar sin enviar el formulario.
3. Cortes: desplazarse al tercer horario, identificar telar, abrir observaciones y tomar hora solo si se permite mutación; en auditoría usar inspección visual sin guardar.
4. Desarrolladores normal y muestras: revisar a ambos lados del breakpoint 768, teclado y alerta saldo usando datos de prueba; no finalizar órdenes reales.
5. Inventarios telas y trama: incluir artículos/colores largos, calendario con turnos llenos y Karl Mayer con varios julios; abrir modales sin guardar.
6. Crudo: medir con mouse y touch a 1280, abrir detalle/auditoría, teclado en comentario, cerrar y confirmar restauración de desplazamiento.
7. Trazabilidad: cargar resumen y los cuatro detalles, abrir rollos e imagen, probar zoom/cierre y conservar filtros/desplazamiento.
8. Reportes y catálogos: rango fechas, encabezados/acciones sticky y tablas grandes; accesibilidad por teclado y orientación.

## Recomendación

Resolver primero los grids incondicionales de Tiempos y Reenconado y validar Cortes en móvil. Aprovechar los patrones existentes de Desarrolladores (fichas móvil, campos de 44 px) y Trazabilidad (grids progresivos y scroll local). NativePHP no corrige estos problemas de layout al usar WebView: esta inversión favorece PWA, web y posible app empaquetada. La conclusión de usabilidad queda pendiente de medición runtime del equipo padre.

## Evidencia runtime recibida y revisada

Se revisaron visualmente los ocho PNG originales de 390 y los PNG de 768 de Inventario telas, Crudo y Desarrolladores; se leyeron las métricas JSON de los ocho módulos en los cinco perfiles. Evidencia base: `/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/`. Cada nombre de la tabla corresponde a archivos `nombre-ancho.png` y `nombre-ancho.json`. La barra Laravel Debugbar inferior pertenece al entorno local: se excluye de hallazgos y no se interpreta su contador de controles como evaluación del producto. Los totales `undersizedCount` incluyen esa barra y elementos internos de widgets; no son porcentajes válidos de accesibilidad.

| Módulo / nombre evidencia | Estado capturado | Mediciones comprobadas y límite |
|---|---|---|
| Inventario trama / `inventario-trama` | Nuevo requerimiento con telares y datos existentes | Documento sin overflow horizontal en los cinco anchos. En 390: tabla 640 px, comienza en x=105; input cantidad 80×30 px fuera del fragmento visible de tabla. Confirma scroll local necesario, no overflow global. Modal no abierto |
| Inventario telas / `inventario-telas` | Detalle Karl Mayer del telar 402 visible en el PNG, con varias barras/julios | Documento sin overflow horizontal en los cinco anchos. Calendario en 390 tiene celdas de aproximadamente 37 px por día y checks 16×16; labels/hitbox global no se midieron. No se probó cambio de telar ni guardado |
| Reenconado / `reenconado` | Listado inicial vacío, sin modal de captura | Tabla mide 1.237 px en 390/768/1024, documento coincide con viewport. No verifica el riesgo de 5 campos: el formulario no se abrió |
| Cortes eficiencia / `cortes-eficiencia` | Consulta/listado, aviso de folio en proceso | Documento coincide con viewport en los cinco anchos; listado entra a 390. No es la captura por horarios: no valida ni desmiente el riesgo estático de 1.140 px |
| Desarrolladores / `desarrolladores` | Selector inicial sin elegir telar/orden | Documento coincide con viewport en cinco anchos. PNG móvil muestra selector y contenido bien apilados. No cubre detalle, guardado, alerta saldo o muestras |
| Crudo / `crudo` | Dashboard ANDON con datos y aviso interno de actualización fallida en texto JSON | Documento coincide con viewport. A 390 el main tiene clientWidth 375 y scrollWidth 423 con overflowX hidden; a 768/1024/1280/1440 coincide el ancho de main. Detalles/auditorías no abiertos |
| Trazabilidad / `trazabilidad` | Filtros iniciales sin selección | Documento coincide con viewport. En 390 los tres filtros aparecen apilados sin recorte. No cubre resultados ni modales. Inputs internos Tom Select de 18 px no equivalen al blanco táctil de todo el control |
| Tiempos / `tiempos` | Respuesta 403 Acceso denegado | Solo se comprobó pantalla de acceso denegado en cinco perfiles; tablero sin cobertura runtime. Mantener riesgos de dos columnas como estáticos |

### Hallazgos verificados en estas capturas

1. **Cabeceras móviles superpuestas en varios módulos.** Los PNG `inventario-trama-390.png`, `inventario-telas-390.png`, `reenconado-390.png`, `cortes-eficiencia-390.png` y `desarrolladores-390.png` muestran título sobre el logotipo, recorte o título repartido más allá de la cabecera. En 768, los PNG revisados de Inventario telas y Desarrolladores muestran título completo y separado. Es un defecto visible de cabecera compartida, independiente de Debugbar. Prioridad alta para navegación móvil.

2. **Crudo pierde acceso visible a acciones de cabecera en 390.** `crudo-390.png` muestra la fecha parcialmente cortada y omite actualizar/exportar/fullscreen a la derecha. El JSON ubica la fecha en x=321, ancho 122; actualizar x=449, exportar x=478 y fullscreen x=506, todos fuera de un viewport de 390. En `crudo-768.png` aparecen estas acciones. Esto es recorte de cabecera, aunque el documento no presente overflow global. Además, el main móvil oculta 48 px de desbordamiento horizontal (423−375), cuya ubicación exacta en el piso de máquinas debe comprobarse desplazando la pantalla; no se atribuye a una tabla concreta sin evidencia.

3. **Inventario Karl Mayer comprime datos hasta cortar/pegar etiquetas y valores.** En `inventario-telas-390.png`, Tamaño queda cortado en el extremo derecho y Producción se junta visualmente al valor 98.0; descripción del artículo se parte en numerosas líneas. `inventario-telas-768.png` muestra más espacio y tamaño completo. Es evidencia de legibilidad insuficiente en teléfono pese a `document.scrollWidth=390`. Corregir distribución de información bajo el ancho de tablet; validar también varios julios y el calendario al hacer scroll. Checks 16×16 constituyen una señal para medir hitbox de labels, no un fallo WCAG confirmado.

4. **Confirmación positiva limitada: entradas iniciales Desarrolladores y Trazabilidad sí se adaptan a móvil.** Los PNG de 390 muestran formularios de selección dentro del viewport, filtros de Trazabilidad en una sola columna y selector de telar sin recorte. No autoriza afirmar que todo el flujo sea responsivo: no se eligieron órdenes ni se abrió captura. Reenconado requiere scroll horizontal local, comprobado por su tabla de 1.237 px, y no muestra desbordamiento global; no se considera esto por sí solo un defecto.

Priorizar la cabecera compartida y legibilidad de Inventario Karl Mayer como problemas visibles; mantener Tiempos, formulario Reenconado, captura Cortes y modales como riesgos de código pendientes de pruebas interactivas.
