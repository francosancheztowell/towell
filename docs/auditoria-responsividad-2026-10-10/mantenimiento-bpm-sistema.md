# Responsividad: mantenimiento, mecánicos, BPM y sistema

Fecha: 10 de octubre de 2026. Revisión de código actual, sin cambiar la aplicación.

## Método y límites

Se inspeccionaron rutas de `routes/modules/{mantenimiento,mecanicos,bpm,configuracion,admin,navigation,telegram,redbooth}.php`, hosts Blade, componentes Livewire, layouts y estilos/JavaScript relacionados. AGENTS.md leído. No se usaron auditorías anteriores como evidencia.

Esta revisión combina análisis estático con capturas y mediciones de geometría del navegador local, documentadas en la sección Evidencia de navegador añadida. No se asignan puntuaciones. Los tamaños declarados en CSS no equivalen al ancho renderizado: cada hallazgo indica si procede del código o de una captura real. Teclado, interacción y formularios no abiertos siguen pendientes de prueba visual. Perfiles pedidos: 390×844, 768×1024, 1024×768, 1280×800 y 1440×900. El breakpoint `short` se activa a altura ≤540 px, por tanto no se activa en ninguno de esos cinco perfiles sin teclado/reducción de viewport.

## Cobertura por submódulo

| Submódulo y rutas principales | Vistas/componentes inspeccionados | Fortalezas presentes | Riesgo estático / prueba pendiente |
|---|---|---|---|
| Mantenimiento: `/mantenimiento`, `/solicitudes`, `/reporte-fallos-paros` | `modulos/mantenimiento/reporte-fallos-paros/index`, módulo TS `solicitudes`; grid de módulos | Tabla Flux con scroll compartido, radio seleccionable, filtros en diálogo nativo | Diez columnas a 390 px; acciones trasladadas al navbar; selección y apertura de finalizar requieren ensayo táctil |
| Mantenimiento: `/nuevo-paro`, `/finalizar-paro` | vistas correspondientes y TS de ambos formularios | Nuevo paro pasa de una a dos columnas en md; finalizar pasa de una a tres; radios reales para estrellas | Estrellas sin wrap, botones inferiores con teclado abierto y campos en 3 columnas a 768 px |
| Mantenimiento: `/catalogodefallas`, `/operadores-mantenimiento` | Livewire `catalogo-fallas`, `catalogo-operadores` | Filtros min-w-0, formularios una/dos columnas, overlays overflow-y-auto | Botones de catálogo en navbar global; alto disponible del formulario con teclado |
| Mantenimiento: `/reportes`, `/reportes/fallas-paros` | `reportes-mantenimiento-index`, `reportes-mantenimiento-fallas-paros`, TS reportes | Filtros flex-wrap, tabla overflow-auto y límite 70vh | Tabla administrativa ancha, scroll vertical anidado en app-main |
| Mecánicos: `/ordenes-trabajo`, `/{folio}/captura` | index, captura, `_refacciones` | Scroll propio, overscroll-contain, menú de línea insertado en body, encabezado adaptable | Tabla captura usa 9px en móvil y muchas columnas; tabla refacciones ≥720px |
| Mecánicos: `/estado-maquina`, `/{folio}` | hosts y Livewire `verifica-maquina/index`, `show` | Matriz permite enfocar un telar; controles con clamp; render optimista; lista y matriz con scroll | Lista ≥1100px; captura detallada divide lateral/matriz desde 900px; altura rígida, teclado |
| Mecánicos: `/catalogos/actividades` | catálogo Blade | Tabla en overflow-auto | Modal sin altura máxima ni scroll interno |
| Mecánicos: `/reportes/ot-diarias`, `/reportes/estado-maquina` | ambas vistas, `_ot-diarias-tabla` | Filtros flex-wrap y tablas con overflow-x-auto | Reporte de estado reserva 264px en columnas sticky; gráficos/tablas exportadas no implican buena legibilidad móvil |
| BPM Urdido, Engomado, Tejedores | `modulos/bpm/pagina`, Livewire `folios`, `checklist`, `actividades`, `estatus` | Una implementación compartida; botones táctiles; diálogo nativo; marca checklist 44px; actividad sticky | Checklist Tejedores agrega 64px por telar; lista/actividades traslada botones al navbar |
| Configuración: usuarios listar/crear/editar | `modulos/usuarios/select`, `form_usuario` | Tarjetas de lista; formulario 1/2/3 columnas; permisos tienen presentación separada móvil/escritorio | Panel permisos tiene altura fija 200px móvil/350px escritorio; lista extensa requiere scroll propio |
| Configuración: módulos y permisos | `modulos/gestion-modulos/index`, `_nodo`, TS gestión módulos | Layout apilado bajo md; controles permisos size-touch; diálogos; búsqueda de árbol | Desde 768px lateral fija 320px más tabla ≥704px, con scroll horizontal del detalle |
| Configuración: departamentos, folios | `departamentos`, `secuencia-de-folios` | Tablas con scroll y modal ancho máximo 448px | Formularios centrados sin max-height/overflow; cierre compacto |
| Configuración: mensajes / Telegram | `mensajes`, `modulos/telegram/get-chat-id` | Mensajes ya limita modal a 90dvh y separa cuerpo con scroll; Telegram tiene tabla y pre con scroll horizontal | Telegram botón Copiar no declara tamaño táctil; tablas mensajes de numerosas banderas siguen densas |
| Configuración: cargar planeación, base de datos | `cargar-planeacion`, `basededatos`, TS base de datos | Carga de archivo y progreso; base de datos usa tabla Flux y controles táctiles | Estado ocupado, mensajes largos y teclado/selector archivos deben probarse |
| Admin: resumen, en línea, sesiones, navegación, rendimiento, errores/detalle, accesos | `layouts/admin`, 8 vistas Livewire y CSS admin | Layout separado 100dvh, sidebar Flux, main min-h-0 con scroll, filtros wrap, detalle de error apila hasta xl | Resumen navegación usa grid de tres columnas sin breakpoint; tablas necesitan ensayo a 390px; panel Pulse registrado por proveedor no revisado |
| Inicio y navegación | `/produccionProceso`, `/submodulos/{modulo}`, `/submodulos-nivel3/{padre}`, `produccionProceso`, `modulos/submodulos`, `configuracion`, `layout/module-grid`, navbar | Tarjetas ≥48px, rejilla por cantidad/breakpoint, imágenes con tamaño declarado y carga diferida | Navbar fijo comparte acciones de módulos; títulos largos, avatar y Paro compiten en 390px |
| Redbooth | rutas integración OAuth/JSON/descarga; parciales codificación y programa tejido; TS trazabilidad | Modal ancho limitado por vw, imágenes max-width:100%, tablas rich adaptadas a móvil; menú se limita al viewport | En codificación la entrada es contextmenu; altura vh; editor/visor requieren teclado/orientación y contenido largo |

Las rutas POST/PUT/DELETE y APIs son soporte de las pantallas, no pantallas adicionales. Telegram no tiene dashboard independiente en sus rutas: la interfaz encontrada es get-chat-id. Redbooth utiliza modales dentro de otros módulos. PDFs y Excel son salidas de exportación, no se calificaron como pantallas responsivas.

## Hallazgos concretos para priorizar pruebas y ajustes

1. **Alta — Navbar compartido, riesgo de compresión o recorte móvil.** `resources/views/components/navbar/navbar.blade.php:41` fija `h-14`; línea 46 tiene centro `flex-1 min-w-0`, pero línea 59 mantiene derecha `flex-shrink-0`, con configuración, acciones de pantalla, Paro y avatar. `resources/views/components/tabla.blade.php:12` describe el traslado de acciones al navbar. No existe wrap en esa fila. Probar títulos largos y catálogos/BPM con todas sus acciones a 390×844; valorar menú de acciones y título truncado/adaptable. No se afirma que ya se haya observado recorte.

2. **Alta — Modales de folios, departamentos y catálogo actividades sin estrategia de altura para teclado.** `resources/views/modulos/configuracion/secuencia-de-folios.blade.php:55` overlay centrado y línea 56 tarjeta sin max-height/overflow. Mismo patrón en `modulos/configuracion/departamentos.blade.php:47` y `modulos/mecanicos/catalogos/actividades/index.blade.php:46`. El body/layout limita scroll global. Probar focus último campo + teclado, botón Guardar y X. Adoptar patrón ya existente en mensajes o diálogo compartido con 100dvh y scroll.

3. **Alta — Captura OT reduce texto a 9px en móvil.** `resources/views/modulos/mecanicos/ordenes-trabajo/captura.blade.php:184` declara `text-[9px]`, luego 10px en sm, 12px md y 14px lg, dentro de tabla `w-max whitespace-nowrap`. No es overflow global confirmado; hay scroll horizontal local en línea 183. Riesgo de lectura y precisión táctil. Probar captura a 390px; priorizar un resumen por intervención y editor de fila con tipografía de formulario.

4. **Media — Listado estado de máquina conserva ancho mínimo 1100px.** `resources/views/livewire/mecanicos/verifica-maquina/index.blade.php:22`: `min-w-[1100px] short:min-w-[960px]`; línea 18 contiene overflow-auto. A 390px/768px/1024px el ancho declarado excede el perfil antes de paddings; no necesariamente desborda el documento. En perfiles pedidos short no reduce ancho. Evaluar priorizar folio/estado/acciones, columnas ocultables o tarjetas móviles.

5. **Media — Reporte estado máquina dedica 264px a columnas sticky.** `resources/views/modulos/mecanicos/reportes/estado-maquina.blade.php:82` fija Control ≥180px, línea 83 Prioridad ≥84px con left 180px. En 390px dejan como máximo 126px antes de márgenes para datos semanales. Es cálculo de restricciones CSS, no medición. Probar estabilidad del sticky y acceso a días; reducir/fijar solo Control en móvil o ofrecer detalle por máquina.

6. **Media — Checklist BPM Tejedores depende de desplazamiento horizontal.** `resources/views/livewire/bpm/checklist.blade.php:43` Actividad ≥192px y línea 45 añade ≥64px por telar; marcas size-touch en línea 64. Buen tamaño de captura, pero muchas columnas obligan a desplazar. Probar folio con varios telares y actividad larga a 390px/768px. Considerar seleccionar telar o grupos manteniendo etiqueta de actividad visible.

7. **Media — Calificación de paro requiere prueba específica por distribución de estrellas.** `resources/views/modulos/mantenimiento/finalizar-paro/index.blade.php:251` fija fila sin wrap, gaps 16px móvil/8px md/48px lg, ancho completo y margen izquierdo; líneas 253–265 usan 48px/60px de fuente con padding 16px por estrella y shrink-0. A 390px hay riesgo de exceder ancho útil; a 1024px lg vuelve a aumentar separaciones. Los glifos exactos dependen de fuente. Probar que las cinco estrellas sean visibles y seleccionables; escalar gap y fuente por ancho disponible.

8. **Media — Redbooth en codificación no muestra alternativa explícita de apertura táctil en el parcial.** `resources/views/catcodificacion/partials/redbooth.blade.php:19` escucha contextmenu para mostrar menú. Líneas 28–29 acotan posición al viewport (fortaleza). Revisar si el flujo exterior aporta botón o long-press fiable; no asumir compatibilidad del gesto entre navegadores. `resources/views/modulos/programa-tejido/modal/redbooth.blade.php:2` usa 94vh; probar editor con teclado y cambiar a altura dinámica si falla.

9. **Media — Gestión módulos deja poco ancho al panel en tablet vertical.** `resources/views/modulos/gestion-modulos/index.blade.php:33` lateral md:w-80 (320px), lg:w-96; línea 132 tabla min-width 44rem (704px). A 768px el panel tiene como máximo 448px antes de bordes/márgenes; su overflow-auto contiene la tabla. Es decisión de diseño, no fallo medido. Probar selección/edición de permisos en scroll horizontal; valorar drawer o lateral colapsable a md.

10. **Media — Admin navegación tiene resumen de tres columnas fijo.** `resources/views/livewire/admin/navegacion.blade.php:34` usa `grid-cols-[minmax(0,14rem)_1fr_auto]`; línea 39 impide wrap de duración y conteo. Ruta se trunca pero puede dejar la barra sin espacio en móvil con valores largos. A diferencia de resumen admin (breakpoints en su rejilla), este listado no cambia estructura por viewport. Probar datos reales de duración/cantidad y usar dos filas en móvil si se comprime.

## Fortalezas compartidas verificadas

- `resources/css/app.css:34` define touch en 2.75rem (44px); muchos controles nuevos lo usan explícitamente. No implica que todos los controles antiguos midan 44px.
- `resources/css/app.css:304` diálogo compartido: ancho viewport menos 2rem, max-height dinámica y overflow-y-auto; BPM folios y formularios de actividades lo usan.
- `resources/views/modulos/configuracion/mensajes.blade.php:102` modal con padding responsivo, max-height min(90dvh,90vh), y línea 111 cuerpo con scroll separado. Es patrón reutilizable para otros catálogos.
- `resources/views/layouts/admin.blade.php:31`/88 utiliza 100dvh y scroll en main; detalle error apila hasta xl y pre tiene overflow-auto.
- `resources/views/livewire/mecanicos/verifica-maquina/show.blade.php:197` mini controles con clamp y mínimo 44px de alto; línea 215 reduce a 40px al activar short, relevante con teclado o landscape corto aunque no para los perfiles nominales.

## Plan de comprobación visual

Para cada uno de los cinco perfiles: documentar viewport efectivo, ancho documento versus viewport, ancho/alto y scroll de contenedor tabla, tamaño de acciones principales, visibilidad de footer/X, screenshots y ruta/rol. Separar tablas con scroll local previsto de overflow horizontal del documento.

Flujos prioritarios: (1) home y navbar con permisos amplios, (2) nuevo/finalizar paro y cinco estrellas, (3) captura OT/refacciones, (4) estado máquina con captura y modales, (5) checklist BPM Tejedores de varios telares, (6) folios/departamentos con teclado, (7) permisos módulos, (8) admin navegación con datos, (9) editor/visor Redbooth.

Repetir móvil/tablet vertical con teclado abierto, orientación y zoom de texto. Emulación de viewport no reproduce por sí sola teclado físico de iOS/Android; esa limitación debe anotarse. No ejecutar guardados/altas/envíos Telegram para medir diseño salvo autorización específica o entorno de prueba.

Conclusión: este conjunto tiene bases responsivas reutilizables; las prioridades estáticas son navbar, modales antiguos y densidad de tablas/captura. No hay prueba actual que permita atribuir estos riesgos a Livewire/Flux ni concluir que empaquetar NativePHP los resolvería.

## Evidencia de navegador añadida

Se revisaron los JSON y originales PNG obtenidos por el agente principal en el navegador local. Esta sección actualiza el alcance: la parte anterior sigue siendo análisis estático, mientras que aquí hay capturas y geometría real. Carpeta de evidencia: `/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/`. No se controló el navegador desde este agente.

Todos los conjuntos siguientes tienen los cinco perfiles pedidos. En **todos**, document.clientWidth y document.scrollWidth coinciden con el ancho nominal; ello descarta overflow horizontal del documento en esas capturas, pero **no descarta recortes internos**, como demuestra Usuarios. La franja inferior de Debugbar corresponde al entorno local y no constituye evidencia de un problema de producción. También contamina el conteo automático de controles pequeños: no se usa undersizedCount como puntuación de producto.

| Pantalla capturada | Resultado comprobado | Límites de la captura |
|---|---|---|
| Inicio | Rejilla de dos columnas móvil visible. Navbar 390px superpone título/logo y recorta Salir | No se midió acceso a las tarjetas más abajo ni interacción de menú |
| Nuevo paro | Formulario 390px con campos apilados y sin overflow horizontal; main tiene scrollHeight1021 frente clientHeight844, por tanto el contenido continúa verticalmente | Sin selección de departamento/máquina, sin teclado ni envío |
| Listado paros | Documento contenido; tabla medida 608px a 390px con scroll local | No se abrió finalizar ni filtros |
| Finalizar paro | Los cinco JSON resuelven URL `/mantenimiento/solicitudes` | Redirección sin ID: **no se probó formulario finalización ni estrellas** |
| Estado máquina | Tabla 1100px a 390px y 1104px a 768/1024; documento contenido, columnas fuera de vista inicialmente | No se abrió detalle/matriz ni modal Nuevo |
| Órdenes trabajo | Recapturas estables con filas reales en cinco perfiles: texto/folio/estado comprimidos a 390px, Editar parcialmente recortado; documento contenido | Se verificó listado cargado; no se abrió pantalla captura ni filtros/alta |
| Folios BPM Urdido/Engomado/Tejedores | Listados con datos; filtros apilan móvil y tabla tiene scroll local. A 390px se solapa título/logo con acciones navbar | No se abrieron checklist/alta; ancho tabla depende de datos |
| Usuarios | **Recorte real de acciones** a 390px: Duplicar parcialmente visible, Eliminar fuera del área; main clientWidth375 y scrollWidth542 con overflowX hidden | En anchos ≥768px JSON no reporta botones fuera; no se probó formulario usuario |
| Gestión módulos/permisos | Árbol y estado “Elige un módulo” se adaptan; a 768px sidebar320px y panel vacante visibles | Ningún módulo seleccionado: no valida tabla permisos ni edición |
| Admin navegación | Filtros apilan y estado vacío cabe móvil. Sidebar desplazado fuera en móvil es comportamiento cerrado previsto | Sin dispositivo/usuario seleccionado: no valida resumen de tres columnas ni tabla de registros |

### Prioridades verificadas

1. **Corregir barra superior móvil compartida.** [Inicio 390px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/inicio-390.png), [Estado máquina 390px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/mecanicos-estado-390.png) y [BPM Urdido 390px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/bpm-urdido-390.png) muestran título superpuesto y/o recortado; Inicio además recorta Salir. Respaldan hallazgo estático 1 aunque document.scrollWidth sea390. Priorizar composición alternativa móvil: acciones en menú, título con ancho controlado, logo reducido.

2. **Reorganizar acciones de tarjetas Usuarios a 390px.** [Usuarios 390px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/usuarios-390.png) y `usuarios-390.json` confirman controles fuera de main: Eliminar mide97px y comienza aproximadamente x388–445 según fila. Código causa compatible en `resources/views/modulos/usuarios/select.blade.php:83` flex horizontal y línea120 acciones `flex-shrink-0`, sin wrap. Pasar acciones a una segunda fila o menú móvil. Este es un fallo verificado, distinto de una tabla que permite scroll local.

3. **Mejorar legibilidad y acceso a Editar en listado OT móvil.** [Órdenes trabajo estable 390px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/mecanicos-ot-estable-390.png) confirma once columnas con filas reales, títulos comprimidos, folios/estados truncados y botón Editar parcialmente recortado a la derecha. [Listado estable 768px](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/mecanicos-ot-estable-768.png) muestra Editar completo, aunque folios siguen truncados. `resources/views/modulos/mecanicos/ordenes-trabajo/index.blade.php:38` usa table-fixed y10px de texto con anchos porcentuales. Priorizar columnas esenciales o scroll horizontal con ancho legible. El resultado estable reemplaza la primera captura en estado de carga; no se verificó la pantalla de captura ni se ejecutaron acciones.

Los hallazgos estáticos de estrellas, teclado, checklist, modales, permisos seleccionados y resumen Admin **siguen pendientes**. No se convirtieron en fallos verificados por la mera existencia de screenshots de sus listados/hosts.

Recaptura estable de OT: archivos `mecanicos-ot-estable-{390,768,1024,1280,1440}.json/png`. En cinco perfiles document.scrollWidth = clientWidth. Tabla renderizada:365px a390px,727px a768px,983px a1024px,1239px a1280px,1399px a1440px. Esas medidas confirman compresión de columnas en el listado móvil, no overflow global. Las filas cargaron en las recapturas y la hipótesis de carga persistente queda descartada para este conjunto.
