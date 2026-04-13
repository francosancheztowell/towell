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

- En modales que ya tienen cierre con X, no duplicar botón "Cancelar" si el usuario prefiere cerrar solo con la X (patrón usado en flujos de programa de tejido).
- En tablas modales de programa de tejido: encabezados breves cuando se pida (por ejemplo "Saldos"), evitar badges recortados (`whitespace-nowrap` / ancho mínimo), y resaltar saldos negativos o modelo REPASO1 con badge rojo cuando aplique.
- En el modal de detalle de telar (`/planeacion/programa-tejido`) y en modales de programa de tejido, mostrar cantidades como enteros con redondeo estándar (parte decimal ≥ 0,5 hacia arriba; en caso contrario hacia abajo).
- En tablas con filas de alerta (p. ej. alineación con registros activos en `ManFallasParos` para el mismo `NoTelar`): colorear con fondo amarillo tenue + ícono de alerta; si la fila también está seleccionada, mantener el color amarillo sin sobreescribirlo con el azul de selección.

## Learned Workspace Facts

- En módulos de producción con `ProduccionTrait`, `maxKgNetoAllowed()` es `null` por defecto; `ModuloProduccionUrdidoController` lo fija en 700. En producción Engomado, Kg. Bruto tiene tope 2000 kg (regla de ese módulo; independiente del tope de Kg. Neto en Urdido).
- En balanceo automático (`BalancearTejido`), el cierre del total frente al objetivo (sobra o falta) debe concentrarse en el **último** registro del grupo en orden `Posicion` / `NoTelarId`, sin repartir recortes en los demás telares del grupo.
- En clases bajo `App\...`, usar `use Carbon\Carbon` (o `CarbonInterface` para constantes como `MONDAY`) para evitar que `Carbon::...` se resuelva al namespace local y para satisfacer analizadores estáticos.
- Métodos PHP muy largos con muchas ramas (p. ej. `balancearAutomatico`) pueden disparar avisos del IDE del tipo "too many types"; extraer lógica a métodos privados y declarar retorno explícito (`JsonResponse`) suele aliviarlo.
- Exportación OEE Atadores en Python (`scripts/oee_export.py`, vía `OeeAtadoresFileService`): conservar encabezados y estructura de semanas como el PHP; si hay más atadores que la plantilla, extender filas sin desalinear semanas; tras `openpyxl.insert_rows`/`delete_rows` corregir textos de fórmulas con `openpyxl.formula.translate.Translator` (p. ej. `=C46+1`).
- En alineación (`AlineacionController`), **Peso min** / **Peso max** y **Muestra min** / **Muestra max** muestran los mismos valores: si `trim(Tolerancia del catálogo) === 'N'` y `PesoCrudo > 0`, se calculan con `PesoCrudo/(1+3%)`, `PesoCrudo/(1+0%)` y `PesoCrudo/(1+5%)` → `(int) round(min(...))` y `(int) round(max(...))`; si no aplica, las cuatro celdas quedan vacías (`''`).
- En `scripts/oee_export.py`, el layout compacto solo en Python (`COMPACT_DEFAULT_BLOCK_HEIGHT`, `COMPACT_CAPACITACION_HEIGHT`) acorta bloques respecto al export PHP; opcionalmente se neutralizan rellenos y reglas de formato condicional en DETALLE (aprox. columnas A–CU) y en la zona OEE de hojas SEMANA (aprox. filas 4–22, columnas B–M), con altura de fila uniforme en esas zonas.
- En `balancear.blade.php`: `previewFechasExactas` acepta `{ force: true }` para forzar el preview aunque no haya cambios de pedido; llamar con esta opción al abrir el modal y tras balanceo automático para alinear F.Inicio/F.Final con el Gantt. En `didOpen`, ejecutar `await renderGanttOrd` y luego `await previewFechasExactas(..., { force: true })` secuencialmente para evitar condición de carrera entre Gantt y preview.
- En handlers `beforeinput` sobre `<input type="number">`: `selectionStart`/`selectionEnd` retorna `null` en Chrome/Edge (lanza `TypeError` en Firefox); siempre permitir el separador decimal (`.` o `,`) sin validar posición de cursor; si `el.value` está vacío (estado inválido intermedio, p. ej. al escribir `381.`), permitir el siguiente dígito sin bloquear.
- En `modulo-produccion-engomado`, «Calificar julios» usa el folio de la orden pero consulta y guarda en `UrdProduccionUrdido` (`modal-calificar-julios.blade.php`, `getJulios` / `calificar` en `CalificarJuliosController`); `modal-calificar-julios-eng` es el flujo sobre `EngProduccionEngomado` (`getJuliosEng` / `calificarEng`).
- En `modal-calificar-julios.blade.php` y `modal-calificar-julios-eng.blade.php`, el operador mostrado con la fecha del julio corresponde al turno con mayor `Metros1`–`Metros3`; si todos los metros son 0, usar el primer `NomEmpl` no vacío. Tras renombrar helpers JS en el modal urdido, revisar llamadas en `renderTabla` (evitar nombres obsoletos como `buildInfoOperador`).
