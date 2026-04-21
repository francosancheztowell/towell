# Repository Guidelines

## Project Structure & Module Organization
Towell is a Laravel 12 application.
- Backend code: `app/` (`Http/Controllers`, `Models`, `Services`, `Repositories`, `Imports`, `Exports`, `Helpers`).
- Routes: `routes/web.php`, `routes/public.php`, `routes/ai.php`, `routes/console.php`.
- Frontend assets: `resources/js`, `resources/css`, Blade views in `resources/views`, reusable templates in `resources/templates`.
- Database: `database/migrations`, `database/seeders`.
- Public/build output: `public/` (bundled via Vite).

## Build, Test, and Development Commands
- `composer install` and `npm install`: install backend/frontend dependencies.
- `composer dev`: run full local stack (Laravel server, queue listener, logs, Vite).
- `php artisan serve`: run only the Laravel server.
- `npm run dev`: run Vite dev server for assets.
- `npm run build`: production asset build.
- `php artisan test`: run PHPUnit suite.
- `vendor/bin/pint`: apply Laravel Pint formatting.

## Coding Style & Naming Conventions
- Follow PSR-12 and Laravel defaults (4-space indentation, UTF-8, one class per file).
- Class names: `PascalCase`; methods/variables: `camelCase`; config/env keys: `UPPER_SNAKE_CASE`.
- Keep controllers thin; move business logic to `Services`/`Repositories`.
- Use Form Requests for validation and Eloquent scopes for reusable query constraints.

## Testing Guidelines
- PHPUnit is configured in `phpunit.xml` with `Unit` and `Feature` suites.
- Place tests under `tests/Unit` or `tests/Feature` with names ending in `Test.php` (for example, `PlaneacionServiceTest.php`).
- Prefer feature tests for HTTP flows and permissions; unit test isolated services/helpers.
- Run `php artisan test` before opening a PR.

## Commit & Pull Request Guidelines
- Recent history uses short, task-focused Spanish messages (for example, `produccion urdido`, `logica de validacion...`). Keep commits concise and scoped.
- Recommended format: `<area>: <brief change>` (example: `planeacion: evitar duplicados en programa`).
- PRs should include: objective, affected modules/routes, migration impact, test evidence, and UI screenshots when views change.

## Security & Configuration Tips
- Never commit `.env` or credentials.
- This project targets SQL Server by default (`DB_CONNECTION=sqlsrv`); validate DB settings before migrations.
- Clear caches after config/routing updates: `php artisan optimize:clear`.

## Learned User Preferences

- En **Liberar órdenes** (programa de tejido), al liberar, **L.Mat** (`bomId`) y **Nombre L.Mat** (`bomName`) son obligatorios: validación Laravel (`required`) y comprobación en JS antes del POST (SweetAlert si falta alguno).
- En modales que ya tienen cierre con X, no duplicar botón "Cancelar" si el usuario prefiere cerrar solo con la X (patrón usado en flujos de programa de tejido).
- En tablas modales de programa de tejido: encabezados breves cuando se pida (por ejemplo "Saldos"), evitar badges recortados (`whitespace-nowrap` / ancho mínimo), y resaltar saldos negativos o modelo REPASO1 con badge rojo cuando aplique.
- En el modal de detalle de telar (`/planeacion/programa-tejido`) y en modales de programa de tejido, mostrar cantidades como enteros con redondeo estándar (parte decimal ≥ 0,5 hacia arriba; en caso contrario hacia abajo).
- En tablas con filas de alerta (p. ej. alineación con registros activos en `ManFallasParos` para el mismo `NoTelar`): colorear con fondo amarillo tenue + ícono de alerta; si la fila también está seleccionada, mantener el color amarillo sin sobreescribirlo con el azul de selección.
- En el modal **Finalizar órdenes** (`planeacion/utileria/finalizar-ordenes`): deshabilitar la confirmación si alguna orden seleccionada tiene `produccion` nula o cero; normalizar IDs (número vs string) en el `Set` de selección para que checkboxes y filas no se desincronicen.
- En **captura de fórmula** engomado (`modulos/engomado/captura-formula`), el select debe listar **todas** las fórmulas del BOM del folio (misma expectativa que en creación de órdenes / consulta AX), no solo la fórmula ya guardada en el folio.
- En **Liberar órdenes**, **Prioridad** debe guardarse como el usuario la ve o la edita (texto): en `ReqProgramaTejido` usar cast `string` para `Prioridad` (no `integer`, para que valores no numéricos no se conviertan en 0); al armar el valor inicial en Blade no usar `empty()` sobre la prioridad actual porque `empty('0')` es verdadero en PHP.

## Learned Workspace Facts

- En módulos de producción con `ProduccionTrait`, `maxKgNetoAllowed()` es `null` por defecto; `ModuloProduccionUrdidoController` lo fija en 700. En producción Engomado, Kg. Bruto tiene tope 2000 kg (regla de ese módulo; independiente del tope de Kg. Neto en Urdido).
- En balanceo automático (`BalancearTejido`), el cierre del total frente al objetivo (sobra o falta) debe concentrarse en el **último** registro del grupo en orden `Posicion` / `NoTelarId`, sin repartir recortes en los demás telares del grupo.
- Exportación OEE Atadores en Python (`scripts/oee_export.py`, `OeeAtadoresFileService`): conservar encabezados y semanas como el PHP; extender filas si hay más atadores; tras `insert_rows`/`delete_rows` corregir fórmulas con `openpyxl.formula.translate.Translator`; el layout compacto opcional (`COMPACT_*`) acorta bloques y puede neutralizar formatos en zonas DETALLE/SEMANA.
- En **Liberar órdenes** (`LiberarOrdenesController`): `obtenerCodigoDibujo` lee `CatCodificados` (no `ReqModelosCodificados`); el front puede enviar `combinations` como `itemId::inventSizeId::salon` (`data-salon-tejido-id` / `SalonTejidoId` vs `Departamento`); resolución en orden ItemId+InventSizeId+Departamento → ItemId+Departamento → ItemId+InventSizeId; en cada intento, `Id` descendente y primer `CodigoDibujo` no vacío. El POST de liberar debe incluir `codigoDibujo` de la celda para `actualizarCatCodificados`. En `index`/`liberar`, **Repeticiones**, **marbetes** (`SaldoMarbete`), **MtsRollo**, **PzasRollo**, **TotalRollos**, **TotalPzas** y **Densidad** pueden calcularse con **fórmulas** si faltan en BD o POST; **BomId/BomName**, **CombinaTram**, **HiloAX** y **cambio repaso** (`CambioHilo`) suelen venir del **request**.
- En **tejedores/desarrolladores**, si la fila no tiene `NoProduccion`, los detalles pueden cargarse con `GET .../registro/{id}/detalles`; al guardar, `resolverContextoOrigen` asigna `NoProduccion` usando `registroId` cuando el registro seguía sin orden. En alta de `CatCodificados` nuevo se copian `ActualizaLmat` y `CreaProd` del programa (no existe campo `CreaLmat` en el repo); este flujo no asigna en aplicación `FechaCreacion`/`HoraCreacion`/`UsuarioCrea` ni campos de modificación en `CatCodificados`.
- En alineación (`AlineacionController`), **Peso min** / **Peso max** y **Muestra min** / **Muestra max** muestran los mismos valores: si `trim(Tolerancia del catálogo) === 'N'` y `PesoCrudo > 0`, se calculan con `PesoCrudo/(1+3%)`, `PesoCrudo/(1+0%)` y `PesoCrudo/(1+5%)` → `(int) round(min(...))` y `(int) round(max(...))`; si no aplica, las cuatro celdas quedan vacías (`''`).
- En `balancear.blade.php`: `previewFechasExactas` acepta `{ force: true }` para forzar el preview aunque no haya cambios de pedido; llamar con esta opción al abrir el modal y tras balanceo automático para alinear F.Inicio/F.Final con el Gantt. En `didOpen`, ejecutar `await renderGanttOrd` y luego `await previewFechasExactas(..., { force: true })` secuencialmente para evitar condición de carrera entre Gantt y preview.
- En handlers `beforeinput` sobre `<input type="number">`: `selectionStart`/`selectionEnd` retorna `null` en Chrome/Edge (lanza `TypeError` en Firefox); siempre permitir el separador decimal (`.` o `,`) sin validar posición de cursor; si `el.value` está vacío (estado inválido intermedio, p. ej. al escribir `381.`), permitir el siguiente dígito sin bloquear.
- «Calificar julios»: urdido usa `UrdProduccionUrdido` (`modal-calificar-julios.blade.php`, `CalificarJuliosController`); engomado usa `EngProduccionEngomado` (`modal-calificar-julios-eng`, `getJuliosEng` / `calificarEng`). En ambos modales, el operador mostrado con la fecha del julio es el turno con mayor `Metros1`–`Metros3` (si todos cero, primer `NomEmpl` no vacío); tras renombrar helpers JS en urdido, revisar `renderTabla` (evitar `buildInfoOperador` obsoleto).
- En **nuevo paro** (`mantenimiento/nuevo-paro`), no reportar si en `ManFallasParos` ya existe un paro con **`Estatus` Activo**, el mismo telar (`MaquinaId`) y el mismo **tipo de falla** (`TipoFallaId`); la vista llama **GET** `api/mantenimiento/paros/validar-duplicado` antes del POST y `MantenimientoParosController::store` aplica la misma validación.
- En el reporte **Saldos 2026** (tejido, `resources/views/modulos/tejido/reportes/saldos-2026.blade.php` y `app/Exports/Saldos2026Export.php`): la columna **Toallas Tejidas** muestra **Producción** (`_sumProduccion`/`Produccion`); la columna **Faltan** muestra **SaldoPedido** (`_sumSaldoPedido`/`SaldoPedido`); el orden es **Toallas Tejidas** y después **Faltan**. El **Avance** es **Producción/Solicitado** entre 0% y 100% (misma regla en pantalla y en el Excel del export).
- En **codificación** (`/planeacion/codificacion`, modal **Peso muestra**): por defecto el select de orden usa `GET .../api/ordenes-en-proceso` (`ReqProgramaTejido` con `EnProceso = 1` y `NoProduccion` no vacío, ordenado); con el checkbox **Usar orden de la fila seleccionada** se toma `OrdenTejido` de la grilla y se carga con `GET .../api/catcodificados-por-orden/{orden}` sin depender solo de esa lista.
