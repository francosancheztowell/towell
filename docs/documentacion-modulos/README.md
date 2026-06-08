# Documentación detallada por módulo — Towell

## Introducción

**Towell** es una aplicación web Laravel 12 para la gestión de planeación de producción y negocio en la industria textil (planeación, tejido, urdido, engomado, atadores, tejedores, mantenimiento y configuración), con un sistema de permisos por módulo basado en roles.

Esta carpeta reúne la **documentación técnica detallada de cada ámbito del proyecto**: rutas, controllers y sus funciones (públicas y privadas relevantes), modelos y tablas SQL Server, vistas Blade y su JavaScript inline, servicios, observers, integraciones (Excel, PDF, Telegram) y las reglas de negocio asociadas.

Cada documento se generó analizando el código fuente real del módulo correspondiente (rutas en `routes/modules/`, controllers en `app/Http/Controllers/`, modelos en `app/Models/` y vistas en `resources/views/`). Los hallazgos —rutas rotas, métodos inexistentes, *typos*, código legacy o inconsistencias— se registran explícitamente en cada documento. Úsalo como referencia para entender, mantener o extender cada módulo.

## Índice de documentos

| # | Documento | Título | Resumen |
|---|-----------|--------|---------|
| 01 | [01-planeacion-catalogos.md](01-planeacion-catalogos.md) | Planeación — Catálogos | Submódulo Planeación → Catálogos (telares, eficiencias, velocidades, calendarios, aplicaciones, matriz de hilos y pesos por rollo): datos maestros que alimentan el Programa de Tejido y disparan recálculos en cascada. Incluye rutas, 7 controllers, modelos/tablas, 26 blades, clases JS de catálogo y reglas/fórmulas de negocio. |
| 02 | [02-planeacion-programa-tejido.md](02-planeacion-programa-tejido.md) | Planeación — Programa de Tejido | Ámbito Planeación → Programa de Tejido: 12 controllers, subcarpetas `funciones/` y `helper/`, `ProgramaPrioridadService`, observers, middleware `ProgramaTejidoContext`, modelos `ReqProgramaTejido`/`Line`, 17 blades y 3 JS. Cubre secuenciación por telar, fechas en cascada, duplicar/dividir/vincular/balancear, liberación con folio + Excel y rutas espejo de Muestras. |
| 03 | [03-planeacion-codificacion.md](03-planeacion-codificacion.md) | Planeación — Codificación y Modelos | Dos catálogos paralelos (`ReqModelosCodificados` vía `CodificacionController` y `CatCodificados` vía `CatCodificacionController`): rutas, recálculo de marbetes, peso muestra/LMAT, revivir órdenes, import Excel con progreso/cancelación y formularios legacy de modelos. |
| 04 | [04-planeacion-otros.md](04-planeacion-otros.md) | Planeación — Alineación y Utilerías | Pantalla de monitoreo de órdenes en proceso (Alineación, solo lectura con refresco cada 5 min) y herramientas de Utilería (Finalizar y Mover órdenes), más el service `RevivirOrdenProgramaDesdeCat`. Rutas, controllers, modelos, 4 vistas y la lógica de saldos compartidos y recálculo de fechas/posiciones. |
| 05 | [05-tejido.md](05-tejido.md) | Tejido | Operación del salón de telares: inventario de telas, requerimientos de trama, cortes de eficiencia, marcas finales, producción de reenconado, reportes (Inv Telas, Promedio Paros, Marcas, Saldos 2026, RPM Semanal) y configuración de secuencias. Consume `ReqProgramaTejido` y AX vía `sqlsrv_ti`. |
| 06 | [06-tejedores.md](06-tejedores.md) | Tejedores | Inventario de julios por telar, checklist BPM, telares por operador, catálogo de desarrolladores, formulario de Desarrolladores/Muestras y notificaciones de atado de julio y cortado de rollo/marbetes (TI_PRO). Conecta Planeación con la operación física e integra Telegram y Excel. |
| 07 | [07-urdido.md](07-urdido.md) | Urdido | Preparación de urdimbre sobre julios: Programar Urdido (prioridades MC Coy/Karl Mayer, status, calidad con aviso Telegram), captura de Producción (`ProduccionTrait`), Edición con sync a Engomado y auditoría, BPM, catálogos y 5 reportes exportables a Excel con respaldo en ruta de red. |
| 08 | [08-engomado.md](08-engomado.md) | Engomado | *Sizing* textil: programación/priorización por máquina WP2/WP3, captura de fórmulas químicas con componentes desde AX, captura de producción por julio, BPM, calificación de julios con defectos, catálogos y reportes (BPM, Control de Merma, Resumen Semanal, Excel/PDF). Depende de Urdido vía Folio compartido. |
| 09 | [09-atadores.md](09-atadores.md) | Atadores | Ciclo de atado de julios (Activo→En Proceso→Terminado→Calificado→Autorizado) entre Tejido y el histórico de telares. Programa Atadores, Calificar, Reportes (OEE anual vía PhpSpreadsheet/job en cola y Python) y 3 catálogos CRUD. Usa `sqlsrv` y MySQL; notifica por Telegram al terminar. |
| 10 | [10-programa-urd-eng.md](10-programa-urd-eng.md) | Programa Urdido-Engomado | Puente entre Planeación y Urdido/Engomado: reserva julios del ERP (TI-PRO), proyecta requerimientos por 5 semanas y genera órdenes de urdido/engomado (incl. flujo Karl Mayer). 9 controllers, 5 services, 1 JS dedicado, 4 vistas y el catálogo de núcleos sobre `sqlsrv` + `sqlsrv_ti`. |
| 11 | [11-mantenimiento.md](11-mantenimiento.md) | Mantenimiento | Reporte, seguimiento y cierre de fallas/paros de máquinas y telares, con catálogos de fallas y operadores, reportes por fecha con export a Excel y notificaciones Telegram. Integra máquinas y órdenes de trabajo de Urdido, Engomado, Atadores y Tejido. |
| 12 | [12-sistema-configuracion.md](12-sistema-configuracion.md) | Sistema, Autenticación y Configuración | Login por número de empleado con migración legacy a bcrypt y QR, sistema de permisos de 2 niveles (`SYSRoles.reigstrar` vs `SYSUsuariosRoles.registrar`), jerarquía de módulos de 3 niveles con caché `modulos_v2`, y CRUDs de usuarios, módulos, departamentos, folios y mensajes Telegram, más carga de planeación por Excel. |
| 13 | [13-helpers-transversales.md](13-helpers-transversales.md) | Helpers, Servicios Transversales, PDF, Excel, Telegram | Ámbito transversal: helpers (`FolioHelper`, `TurnoHelper`, `StringTruncator`, `AuditoriaHelper`, `ImageOptimizer`, `format_helpers`, `device_helpers`), `PDFController` + vistas PDF (DomPDF), `TelegramController` y `SYSMensaje`, panorama de 12 Imports y 28 Exports Excel, services `ImportDataProcessor` y `PronosticosService`, comandos Artisan, `ProduccionTrait` y MCP. |
| 14 | [14-frontend-js.md](14-frontend-js.md) | Frontend — JS compartido, Layouts y Componentes Blade | Capa transversal de frontend: entradas Vite (`app.js`, `app-core.js`, `app-filters.js`, `bootstrap.js`), utilidades globales `window.http` y `window.notify`, 3 layouts Blade y 34 componentes reutilizables (navbar con permisos `userCan`, UI, telares/requerimientos, catálogos, modales). |

## Convenciones del proyecto

- **Permisos.** El control de acceso es por módulo (5 tipos: `acceso`, `crear`, `modificar`, `eliminar`, `registrar`). Verifícalos en backend/Blade con los helpers globales `userCan('crear', 'NombreModulo')` y `userPermissions('NombreModulo')`. **Ojo con el *typo*:** la columna en `SYSRoles` es `reigstrar` (mal escrita), mientras que en `SYSUsuariosRoles` es `registrar` (correcta). Respeta ambas grafías según la tabla.
- **Folios.** Usa `FolioHelper` para los folios secuenciales de `dbo.SSYSFoliosSecuencias`: `obtenerFolioSugerido()` para *preview* en la UI (no incrementa) y `obtenerSiguienteFolio()` solo al confirmar/guardar (auto-incrementa).
- **Turnos.** `TurnoHelper` determina el turno de producción actual (T1: 6:30–14:30, T2: 14:30–22:30, T3: 22:30–6:30) en zona horaria `America/Mexico_City`.
- **Conexiones SQL Server.** Conexión principal `sqlsrv`; además `sqlsrv_ti` → base `TI_PRO` y `sqlsrv_tow_pro` → base `TOW_PRO` (orígenes de datos de producción/ERP). Las tablas usan el prefijo de esquema `dbo.`.
- **Typo del directorio `catalagos`.** El directorio de vistas `resources/views/catalagos/` está mal escrito (debería ser `catalogos`). **Consérvalo tal cual** al referenciar vistas existentes.
- **HTTP y notificaciones.** Prefiere las utilidades globales sobre `fetch` crudo: `window.http` (cliente único sobre axios, añade CSRF automáticamente y lanza errores normalizados con `err.status`/`err.data`/`err.errors`) y `window.notify` (`success/error/warning/info`, `confirm`, `validation`, `loading/close`, con escape de HTML). La migración desde `fetch` + `showToast()` inline está en curso, módulo por módulo.

## Estadísticas de documentación

Conteos por módulo de funciones de controller documentadas (públicas y privadas/protected relevantes), número de controllers y vistas Blade cubiertas. Los conteos de funciones son aproximados en los ámbitos con mucho JavaScript inline.

| Documento | Controllers | Funciones documentadas | Vistas |
|-----------|:-----------:|:----------------------:|:------:|
| 01 — Planeación — Catálogos | 7 | 42 | 26 |
| 02 — Planeación — Programa de Tejido | 12 | 53 | 17 |
| 03 — Planeación — Codificación y Modelos | 2 | 29 | 4 |
| 04 — Planeación — Alineación y Utilerías | 3 | 8 | 4 |
| 05 — Tejido | 15 | 63 | 28 |
| 06 — Tejedores | 12 | 61 | 17 |
| 07 — Urdido | 8 | 39 | 22 |
| 08 — Engomado | 10 | 49 | 17 |
| 09 — Atadores | 5 | 25 | 9 |
| 10 — Programa Urdido-Engomado | 9 | 28 | 4 |
| 11 — Mantenimiento | 4 | 22 | 7 |
| 12 — Sistema, Autenticación y Configuración | 10 | 42 | 15 |
| 13 — Helpers, Servicios Transversales, PDF, Excel, Telegram | 2 | 4 | 2 |
| 14 — Frontend — JS compartido, Layouts y Componentes Blade | 0 | 0 | 37 |
| **Total** | **99** | **465** | **209** |
