# HANDOFF — PT 05 (rama `claude/pt-05-mutaciones`)

## A. Ya hecho fuera de la fila PT (aprobado por el owner en el plan)

| Archivo | Cambio | Por qué |
|---|---|---|
| `app/Models/Planeacion/ReqProgramaTejido.php` | `restoreObservers()` ya no llama `observe()`; `esUltimo()`, `VALORES_ULTIMO`, mutator `setUltimoAttribute` ('UL' → '1'); docblock `@property` de 10 columnas | PT-DUP-01/03 viven en el modelo según REQUIREMENTS. El doble registro del observer estaba verificado (1 → 2 → 3 listeners). El docblock solo afecta a phpstan (baseline −110 entradas) |
| `phpstan-baseline.neon` | Solo se quitaron 113 entradas que dejaron de aplicar | Compartido, solo bajar |
| `scripts/ratchet-baseline.json` | `<script> inline en blade` 159 → 158 | Compartido, solo bajar |

## B. Pedidos a otros dueños

| # | Archivo (dueño) | Cambio pedido | Por qué |
|---|---|---|---|
| 1 | `app/Console/Commands/RecalcularFechasProduccionCommand.php`, `app/Http/Controllers/Configuracion/ConfiguracionController.php`, `app/Http/Controllers/Planeacion/CatalogoPlaneacion/CatCalendarios/CalendarioController.php`, `app/Services/Tejedores/Desarrolladores/MovimientoDesarrolladorService.php` | Cambiar `unsetEventDispatcher()` + `observe(ReqProgramaTejidoObserver::class)` / `setEventDispatcher()` por `$d = ReqProgramaTejido::suppressObservers()` … `ReqProgramaTejido::restoreObservers($d)` en `finally` | PT-DUP-01 fuera de PT. `observe()` después de `unsetEventDispatcher()` no hace nada: esos flujos dejan el modelo **sin eventos** el resto del proceso (el comando y ConfiguracionController, en 4 sitios) |
| 2 | Mismos archivos + `app/Imports/ReqProgramaTejidoUpdateImport.php` (:369, :505), `app/Services/Tejedores/Desarrolladores/*` | Filtros `where('SalonTejidoId', …)->where('NoTelarId', …)` → `->salon()->telar()` **solo** donde el salón viene de la fila. Ojo: en el import el salón viene del Excel ('KM'), y en el comando/CalendarioController se itera por pares distintos de la BD (con alias mezclados procesaría dos veces) | PT-DUP-04 fuera de PT; ver tabla del agente en `05-SUMMARY.md` §4 |
| 3 | `app/Http/Controllers/Tejido/CortesEficiencia/CortesEficienciaController.php` (Tejido) | La otra mitad de PT-PERF-02: `obtenerDatosVisualizacionPorFecha()` hace 3 consultas por fecha del rango | No es PT |
| 4 | `vite.config.js` (congelado en Ola 3) o `resources/js/modulos/**` | **B3 (redbooth) no se hizo.** `modal/redbooth.blade.php` también lo incluyen `modulos/trazabilidad/index.blade.php` y `catcodificacion/partials/redbooth.blade.php`, que no cargan el bundle de programa-tejido. Moverlo exige una entrada Vite propia (p. ej. `resources/js/modulos/redbooth/index.ts`, que el glob ya toma) y cargarla desde el modal. Decidir dueño de esa carpeta | Si se mete al bundle de PT, las otras dos pantallas se quedan sin Redbooth |
| 5 | Owner / DBA | Correr `.planning/phases/05-mutations/sql/pt_ultimo_normalizar.sql` (staging, luego live). Si se prefiere en `database/sql/`, moverlo | Normaliza 'UL' → '1' en las dos superficies (decisión del owner) |
| 6 | Owner | Canary de las 3 familias v2 (runbook en `05-SUMMARY.md` §6) | 05.5, checkpoint humano |
