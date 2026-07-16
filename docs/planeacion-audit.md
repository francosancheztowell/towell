# Auditoria critica del modulo Planeacion

Fecha de corte: 2026-07-16. Rama: `codex/planeacion-react-shadcn`. El analisis se hizo sobre `main@874f8399`.

## Resumen ejecutivo

Planeacion mezcla Laravel, Blade, JavaScript imperativo y estilos locales dentro de las mismas pantallas. El problema no es que PHP tenga demasiado control: varias reglas finales siguen en PHP, pero el navegador tambien reconstruye calculos, validaciones, tablas y estados sin un contrato comun. Eso produce doble mantenimiento y respuestas inconsistentes.

Metricas del alcance auditado:

- 59,444 lineas entre controladores de Planeacion, vistas relacionadas y JavaScript.
- 119 llamadas `fetch` en 27 archivos.
- 363 usos de SweetAlert en 37 archivos.
- 12 vistas con bloques `<style>` y 25 archivos con estilos inline.
- Los archivos mas grandes alcanzan 3,883 lineas en Blade, 2,077 en un controlador y 1,957 en JavaScript.

## Hallazgos confirmados

### Criticos

1. **HTML construido con datos sin escape consistente.** Vistas como `catalagos/pesos-rollos.blade.php` regeneran filas con `innerHTML` e interpolan valores de base de datos en formularios SweetAlert. Un nombre con caracteres HTML puede romper el DOM o convertirse en una superficie XSS.
2. **Errores internos expuestos.** Controladores CRUD devuelven `$e->getMessage()` al navegador. Un error SQL puede revelar nombres de tablas, columnas o detalles de conexion.
3. **Reglas duplicadas entre navegador y servidor.** Liberar, balancear, dividir, mover y finalizar contienen validaciones o calculos en JavaScript y PHP. Aunque PHP persiste, ambas implementaciones pueden divergir.

### Altos

1. **Carga completa sin paginacion.** Varios catalogos ejecutan `get()` y entregan todas las filas a Blade para filtrar y volver a renderizar en el cliente.
2. **Cascadas HTTP similares a N+1.** L.Mat solicita configuraciones, tamanos, colores y matrices por articulo/fila. Hay cache parcial, pero el contrato sigue fragmentado y puede multiplicar GET por modal.
3. **Endpoints solapados.** Telares, eficiencia, velocidad, calendarios y aplicaciones tienen rutas `planeacion/catalogos/...` y rutas paralelas `planeacion/...` hacia el mismo `index`. Codificacion mantiene variantes `get-all`, `all-fast` y consultas por orden con responsabilidades cercanas.
4. **Controladores y vistas fuera de escala.** `LiberarOrdenesController.php`, `CalendarioController.php`, `catalogoCodificacion.blade.php` y las vistas de programa concentran acceso a datos, transformacion, UI y reglas.
5. **CRUD no atomico frente a duplicados.** El patron `buscar existente -> crear` se repite sin contrato comun. La inspeccion read-only confirmo que `ReqPesosRolloTejido` solo tiene indice primario por `Id`; dos solicitudes simultaneas podrian superar la comprobacion por `ItemId + InventSizeId`.
6. **Suite global no hermetica.** Parte de las pruebas presupone tablas o datos externos que no crea en su `setUp`. En un worktree limpio, aun usando SQLite en memoria, quedan 25 fallos ajenos al piloto por esquemas incompletos, fixtures desfasados y expectativas dependientes del estado local.

### Medios

1. **Tres sistemas de solicitudes.** Conviven `fetch`, axios global y `window.http`.
2. **Tres sistemas de mensajes.** Conviven SweetAlert, Toastr y toasts manuales.
3. **CSS con multiples autoridades.** Tailwind, bloques `<style>`, estilos inline y `resources/css/programa-tejido/modals.css` compiten por precedencia.
4. **Estado global por `window`.** Funciones y caches de pantalla se publican globalmente, dificultando aislamiento y pruebas.

## Por que faltaban colores Tailwind

Tailwind v4 estaba instalado, pero `resources/css/app.css` solo declaraba fuentes Blade y JavaScript. Los futuros `.ts`/`.tsx` no estaban incluidos. Ademas, las clases construidas en tiempo de ejecucion, por ejemplo `bg-${color}-500`, no pueden ser descubiertas por el compilador. Finalmente, algunos estilos inline con `!important` sobreescriben las utilidades generadas.

Correccion aplicada:

- Se agregaron `@source '../**/*.ts'` y `@source '../**/*.tsx'`.
- El nuevo arbol usa tokens semanticos estaticos de shadcn (`primary`, `destructive`, `muted`).
- El guardrail rechaza clases Tailwind interpoladas en codigo migrado.

## Arquitectura objetivo

- **Laravel:** autenticacion, permisos, reglas, calculos definitivos, transacciones, auditoria y persistencia.
- **API `/planeacion/api/v1`:** Form Requests, DTO, Service y API Resources con errores Laravel `422`.
- **TanStack Query:** cache, deduplicacion e invalidacion de estado del servidor.
- **TanStack Table:** paginacion, ordenamiento, filtros y seleccion.
- **React Hook Form + Zod:** validacion inmediata de experiencia; nunca sustituye Form Requests.
- **Zustand:** solo estado de interfaz compartido entre componentes. No almacena filas del servidor.
- **shadcn/Tailwind:** unica autoridad visual en pantallas migradas.

## Primera migracion: Pesos por Rollo

Estado: implementada como piloto.

- Vista React predeterminada y fallback `?legacy=1`.
- API paginada con busqueda, filtros y ordenamiento.
- CRUD nuevo con DTO, Form Request, Resource y Service transaccional.
- Tabla reusable, Dialog, AlertDialog, Sonner y formulario Zod/RHF.
- Sin `fetch`, SweetAlert, CSS de modulo, `innerHTML` ni recarga completa.
- Pruebas de frontend y CRUD sobre SQLite en memoria; no escribe en SQL Server.
- Migracion reversible preparada para proteger `ItemId + InventSizeId`; no fue ejecutada.

## Dependencias y seguridad

El lock inicial tenia 25 avisos de seguridad en 11 paquetes. Se actualizaron dentro de las restricciones compatibles Laravel `12.53.0 -> 12.64.0`, Guzzle `7.10.0 -> 7.14.2`, PSR-7 `2.8.0 -> 2.12.5` y la familia Symfony `7.4.x` a versiones corregidas. El resultado es de 6 avisos concentrados en `phpoffice/phpspreadsheet` `1.30.2`.

Ese remanente no se actualizo a ciegas: `maatwebsite/excel 3.1.67` exige `phpoffice/phpspreadsheet ^1.30.0` y bloquea la version corregida `5.7.0`. Resolverlo requiere una migracion mayor y pruebas de regresion de todos los imports/exports; no forma parte segura de este primer corte de Planeacion.

Tambien se elimino el password fallback versionado de `sqlsrv_ti`; ahora la conexion exige `DB_PASSWORD_TIPRO` desde el entorno. `.env.example` documenta todos los parametros de las conexiones adicionales sin incluir valores reales. PHPUnit fija `APP_KEY` de pruebas y SQLite `:memory:` para impedir que una suite ejecutada desde un checkout con `.env` alcance accidentalmente SQL Server.

## Orden recomendado de migracion

1. Pesos por Rollo y Matriz de Calibres como patrones CRUD.
2. Eficiencia, Velocidad, Aplicaciones y Telares, consolidando rutas duplicadas.
3. Calendarios y Alineacion, separando consultas y transformaciones.
4. Utilerias Finalizar y Mover con acciones transaccionales.
5. Programa de Tejido, Liberar, Balancear y Codificacion/L.Mat al final, con pruebas de regresion por cada regla de negocio conocida.

## Riesgos que requieren validacion read-only

- Medir planes y conteos reales de las consultas de Programa de Tejido y Codificacion.
- Inventariar dependencias y latencia de `sqlsrv_ti`; nunca usar esa conexion en pruebas mutantes.
- Confirmar volumen por catalogo antes de fijar tamanos de pagina y estrategias de cache.

Validacion read-only completada para `ReqPesosRolloTejido`: 3 filas, 0 claves nulas, 0 grupos duplicados, longitudes maximas actuales de 7/3 caracteres, ambas conexiones operativas y sin indice compuesto. La migracion queda separada para aplicacion controlada; no se ejecuto ningun DDL.
