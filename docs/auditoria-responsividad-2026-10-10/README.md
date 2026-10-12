# Auditoría de responsividad de Towell

Revisión iniciada el 10 y mediciones terminadas el 11 de octubre de 2026. Cuatro agentes revisaron los módulos existentes y contrastaron código con capturas reales. Se añadieron informes y evidencia; no se modificó la aplicación ni se ejecutaron acciones de alta, edición, borrado o guardado de negocio.

## Resultado

La adaptación es desigual. Algunos menús y formularios iniciales se acomodan bien; navegación compartida, acciones y ciertos modales fallan en pantallas pequeñas. Hay problemas también a 768 px: no es solamente una necesidad futura de teléfonos. La primera inversión debe ser corregir el layout compartido y los flujos de captura prioritarios.

## Método y evidencia

Chrome local mediante Playwright autorizado, sesión iniciada manualmente, en `http://127.0.0.1:8000`. Perfiles en píxeles CSS: **390×844, 768×1024, 1024×768, 1280×800 y 1440×900**. Se obtuvieron capturas y geometría DOM: ancho de documento/contenedores/tablas, posición de controles y filas renderizadas. Hay **33 entradas × 5 perfiles = 165 mediciones finales seleccionadas** (Inicio, 31 rutas solicitadas y modal Mover). Incluyen una pantalla denegada y una redirección duplicada. Se conservan las capturas anteriores de carga; Codificación, Ventas y OT usan recapturas con datos en el manifiesto final. Esto es cobertura representativa de estados, no prueba de todos los submódulos ni todos sus flujos.

- [Galería de los cinco tamaños](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/informe.html).
- [Manifiesto final con estado y archivos](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/manifest-final.json).
- [Cobertura de la primera pasada](/mnt/c/Users/fsanchez/.codex/visualizations/2026/10/11/01a1296c-7808-79f0-a1db-939ff1698ea4/towell-responsividad/coverage.json).

Se inspeccionaron las imágenes originales, además de las mediciones. El documento no presenta overflow horizontal en las mediciones finales; **esto no demuestra que las acciones sean accesibles**: `overflow:hidden` puede esconderlas. Las tablas mayores al viewport con scroll local se distinguen de botones recortados.

No se emuló `pointer: coarse`, toque, teclado virtual, PWA instalada, safe areas ni orientación de un dispositivo real; se cambió el viewport de Chrome de escritorio. Los conteos de controles menores a44px incluyen Debugbar/logos/controles fuera de vista y no constituyen puntuación de accesibilidad. No se midieron rendimiento ni tiempos de respuesta comparativos. La Debugbar inferior se excluyó de las conclusiones de producción. El fallo del runtime integrado del navegador motivó el fallback Playwright por consola autorizado.

## Correcciones prioritarias confirmadas

| Prioridad | Problema y evidencia | Cambio recomendado |
|---|---|---|
| P1 | Navbar: logo y títulos se superponen a390 en múltiples módulos; persiste a768 en algunos. Programa Urd/Eng y Atadores dejan acciones fuera de pantalla. | Redistribuir cabecera: título en fila propia cuando no quepa, menú de acciones accesible y espacio real para controles. |
| P1 | Mover órdenes: el modal mínimo1200px queda centrado y cortado. A390, origen x=-364/ancho530, destino x224/ancho530 y X x739. A768, X x928. | Ancho máximo del viewport; paneles apilados o paso origen→destino, cuerpo desplazable y cierre/confirmación visibles. |
| P1 | Usuarios: main útil375px frente scrollWidth542px, overflowX hidden. Duplicar parcialmente visible y Eliminar fuera en390. | Acciones que envuelvan o menú por tarjeta; cards sin ancho mínimo incompatible. |
| P1 | Crudo: main375px frente scrollWidth423px, overflow hidden; fecha corta y actualizar/exportar fuera en390. | Filtros apilados y acciones accesibles sin clipping. |
| P2 | Programa Urd/Eng390: captura muestra cabeceras/filtros sin filas, aunque DOM tiene19. | Revisar alturas/flex/min-h-0 y desplazamiento para mantener área utilizable del tablero; confirmar navegación hasta filas. |
| P2 | OT cargado390: folio/estado truncados y Editar parcialmente cortado. Karl Mayer: tamaño recortado y etiqueta Producción demasiado cerca del valor. | Listado móvil por tarjeta/resumen y detalle; permitir salto de texto y acciones de tamaño utilizable. |
| P2 | Programa tejido tabla11680px; Codificación estable17466px/501filas. Hay scroll local. | Conservar tabla extensa para trabajo de escritorio; en captura móvil mostrar orden, datos esenciales y detalle progresivo. El ancho solo no prueba un defecto. |

Las fichas de cada agente contienen archivos/líneas, más riesgos estáticos y fortalezas. Los riesgos de arrastre táctil, teclado y captura no se presentan como fallos demostrados.

## Cobertura por pantalla

Ancho máximo de tabla renderizada en390 /768 /1024 /1280 /1440px. Guion significa sin tabla en ese estado. El ancho grande puede ser desplazamiento local deliberado.

| Entrada | Ruta solicitada | Tabla máxima por perfil (px) | Estado y límite |
|---|---|---|---|
| inicio | `/produccionProceso` | — / — / — / — / — | Inicio: acciones/título recortados en móvil. |
| programa-tejido | `/planeacion/programa-tejido` | 11680 / 11680 / 11680 / 11680 / 11680 | Listado cargado; tabla ancha y navbar móvil recortada. |
| codificacion | `/planeacion/codificacion` | 17466 / 17466 / 17466 / 17466 / 17466 | Recaptura estable con 501 filas; modal L.Mat sin probar. |
| alineacion | `/planeacion/alineacion` | 4198 / 4198 / 4198 / 4198 / 4198 | Listado cargado; modal/detalle sin probar. |
| utileria | `/planeacion/utileria` | — / — / — / — / — | Menú inicial adaptativo; modal Mover medido por separado. |
| catalogo-telares | `/planeacion/catalogos/telares` | 380 / 758 / 1014 / 1270 / 1430 | Listado legible; cabecera móvil solapada. |
| matriz-calibres | `/planeacion/catalogos/matrizcalibres` | 900 / 900 / 975 / 1231 / 1391 | Listado cargado; scroll local de tabla. |
| inventario-trama | `/tejido/inventario/trama/nuevo-requerimiento` | 640 / 640 / 847 / 1103 / 1118 | Tarjetas iniciales; nueva captura/guardado sin probar. |
| inventario-telas | `/tejido/inventario-telas/karl-mayer` | 38 / 92 / 104 / 131 / 154 | Detalle Karl Mayer inicial; tamaño cortado en móvil. |
| reenconado | `/tejido/produccion-reenconado` | 1237 / 1237 / 1237 / 1280 / 1440 | Listado inicial; formulario de captura sin probar. |
| cortes-eficiencia | `/modulo-cortes-de-eficiencia` | 358 / 704 / 928 / 1152 / 1152 | Redirige a Consultar; captura de cortes sin probar. |
| urdido-programa | `/urdido/programaurdido` | 832 / 832 / 832 / 832 / 832 | Tablero inicial; edición de orden/producción sin probar. |
| engomado-programa | `/engomado/programaengomado` | 869 / 869 / 1006 / 1262 / 1422 | Tablero inicial; edición de orden/producción sin probar. |
| programa-urd-eng | `/programaurdeng` | 1129 / 1129 / 1129 / 1288 / 1412 | 390: filas no visibles en captura aunque existen en DOM; confirmar scroll/altura con interacción. |
| atadores-programa | `/atadores/programaatadores` | 1171 / 1497 / 1497 / 1497 / 1497 | Listado inicial; calificación de atado/KM sin probar. |
| desarrolladores | `/tejedores/desarrolladores` | — / — / — / — / — | Estado inicial sin orden seleccionada; captura sin probar. |
| mantenimiento-nuevo | `/mantenimiento/nuevo-paro` | — / — / — / — / — | Formulario inicial; teclado/validación/envío sin probar. |
| mantenimiento-listado | `/mantenimiento/solicitudes` | 608 / 752 / 1008 / 1264 / 1424 | Listado; no evaluar finalización desde este estado. |
| mantenimiento-finalizar | `/mantenimiento/finalizar-paro` | 608 / 752 / 1008 / 1264 / 1424 | Redirige a solicitudes: duplicado de listado, no formulario de finalización. |
| mecanicos-estado | `/mecanicos/estado-maquina` | 1100 / 1104 / 1104 / 1223 / 1383 | Estado/listado cargado; scroll local de tabla. |
| mecanicos-ot | `/mecanicos/ordenes-trabajo` | 365 / 727 / 983 / 1239 / 1399 | Recaptura estable con 98 filas; texto truncado/Editar recortado a390. |
| bpm-urdido | `/urd-bpm` | 613 / 943 / 1188 / 1297 / 1391 | Listado inicial; checklist sin probar. |
| bpm-engomado | `/eng-bpm` | 602 / 916 / 1160 / 1269 / 1391 | Listado inicial; checklist sin probar. |
| bpm-tejedores | `/tejedores/bpmtejedores` | 639 / 988 / 1232 / 1566 / 1566 | Listado inicial; checklist sin probar. |
| crudo | `/Crudo` | 356 / 382 / 136 / 149 / 149 | Tablero cargado; fecha y acciones móviles recortadas. |
| trazabilidad | `/trazabilidad` | — / — / — / — / — | Estado inicial sin Flog: matriz/producción/galería sin probar. |
| tiempos | `/producto-terminado/tiempos` | — / — / — / — / — | Acceso denegado: tablero sin cobertura visual. |
| ventas | `/ventas` | 2248 / 2218 / 2218 / 2218 / 2218 | Recaptura estable con datos; cabecera invade pestañas a390. |
| costos | `/costos/cuotas` | 2332 / 2332 / 2332 / 2332 / 2332 | Listado cuotas cargado; cálculos/captura sin probar. |
| usuarios | `/configuracion/usuarios` | — / — / — / — / — | Cards cargadas; acciones recortadas a390. |
| modulos-permisos | `/configuracion/utileria/modulos` | — / — / — / — / — | Estado inicial sin módulo seleccionado: tabla permisos sin probar. |
| admin-navegacion | `/admin/navegacion` | — / — / — / — / — | Estado inicial sin dispositivo seleccionado: resumen sin probar. |
| mover-ordenes-inicial | `/planeacion/utileria · modal abierto` | — / — / — / — / — | Modal abierto sin seleccionar ni guardar; recorte confirmado390/768. |

## Informes por familia

- [Planeación, Ventas y Costos](/mnt/c/xampp/htdocs/Towell/docs/auditoria-responsividad-2026-10-10/planeacion-ventas-costos.md).
- [Urdido, Engomado y Atadores](/mnt/c/xampp/htdocs/Towell/docs/auditoria-responsividad-2026-10-10/urdido-engomado-atadores.md).
- [Tejido, Desarrolladores, Crudo, Producto terminado y Trazabilidad](/mnt/c/xampp/htdocs/Towell/docs/auditoria-responsividad-2026-10-10/tejido-trazabilidad-crudo.md).
- [Mantenimiento, Mecánicos, BPM y Sistema](/mnt/c/xampp/htdocs/Towell/docs/auditoria-responsividad-2026-10-10/mantenimiento-bpm-sistema.md).

## Laravel/PWA y NativePHP

`composer.json` declara Laravel^13.0, Livewire^4.3 y Flux^2.20; PHP^8.3 es el requisito mínimo declarado, no una medición del PHP8.5 que indicas tener en ejecución. `package.json` declara Tailwind^4.3.3, Vite^8.3.1, TypeScript, SortableJS, Tom Select y Chart.js. El sistema mezcla Blade/JavaScript propio con Livewire; la adaptación requiere revisar ambos. Las conexiones SQL Server y `sqlsrv_ti`/AX están presentes en configuración y servicios: datos compartidos siguen siendo un requisito de arquitectura.

Recomendación: mantener Laravel web/PWA y corregir primero su experiencia responsive. Para coexistencia móvil, valorar NativePHP mediante un piloto de **un flujo operativo** con API autenticada hacia el servidor central, en lugar de distribuir conexiones/credenciales de SQL Server/AX en tablets. Esta propuesta es una decisión de arquitectura basada en el proyecto, todavía no una implementación validada.

La documentación actual distingue la interfaz HTML en WebView, que mantiene soporte, y la arquitectura SuperNative con componentes EDGE/SwiftUI/Compose. Ejecutar el HTML existente en otro contenedor no modifica sus restricciones CSS; una interfaz nativa requiere evaluar su implementación y el costo de mantener la experiencia web. No asumir que Flux se convierte automáticamente en componentes nativos ni que PHP8.5/Laravel13 y todos los paquetes están certificados para la versión móvil elegida. Fuente consultada el11 de octubre: [Arquitectura oficial NativePHP Mobile4](https://nativephp.com/docs/mobile/4/architecture/about-the-new-architecture).

Esta auditoría no demuestra una mejora de rendimiento por NativePHP. El piloto deberá medir tiempo de apertura, carga de datos/API, interacción, consumo de memoria y comportamiento con red lenta, usando el mismo flujo y datos en PWA y app.

## Orden sugerido de implementación y validación

1. Corregir cabecera compartida, clipping de acciones y modal Mover órdenes; repetir los cinco perfiles con datos largos.
2. Crear vistas de captura compactas por orden para producción, atadores y mantenimiento; mantener tablas de análisis en escritorio.
3. Validar en Android/tablet real e iPad si forman parte del parque: portrait/landscape, scroll con dedo, arrastre o alternativa, teclado abierto, modales y PWA instalada.
4. Completar los flujos pendientes: liberación/L.Mat/balanceo, capturas urdido/engomado/atadores, checklist BPM, OT, finalización de paro, trazabilidad con Flog y Tiempos con acceso. Para regresión de guardado, usar datos de prueba y entorno autorizado.
5. Definir qué necesidad concreta exige NativePHP (hardware, distribución, trabajo desconectado) y probar ese flujo, manteniendo backend web central. La responsividad por sí sola no justifica cambiar de tecnología.
