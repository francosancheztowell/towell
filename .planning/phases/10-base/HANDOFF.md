# Fase 10 — HANDOFF a otros dueños

Cambios que la fase 10 necesita en archivos que no son suyos, o que dejó avisados.
Nada aquí bloquea el CI: todo ya está en verde con los baselines.

## 1. Tejedores (19-xx): quitar la ruta debug `tel-bpm/log-debug`

- **Archivos:** `resources/views/modulos/bpm-tejedores/tel-bpm/index.blade.php` (líneas ~333–341 y cada `logStep(...)`), `app/Http/Controllers/Tejedores/BPMTejedores/TelBpmController.php::logDebug`.
- **Cambio:** borrar `logDebugUrl`, la función `logStep` y sus llamadas en la vista; luego borrar el método `logDebug`. Después, la fase dueña de `routes/modules/tejedores.php` (o la 10 en una segunda pasada) quita `Route::get('tel-bpm/log-debug', …)`.
- **Por qué:** `logDebug` devuelve un 204 vacío, así que cada paso del modal hace una petición GET inútil. La ruta no se quitó en la fase 10 porque la vista la resuelve con `route('tel-bpm.log-debug')`: sin la ruta, la pantalla truena con `RouteNotFoundException`.

## 2. MON-A (fase 11): deuda de phpstan que trajo la integración

La fase 10 las dejó en `phpstan-baseline.neon` para no poner el CI en rojo, pero algunas parecen errores reales:

| Archivo:línea | Error | Nota |
|---|---|---|
| `app/Services/Monitoreo/CierreRemotoService.php:39,42` | Propiedad no definida `MonDispositivo::$Uuid`, `$UltimoUsuarioId` | Agregar `@property` al modelo o revisar el nombre de columna |
| `app/Http/Controllers/Configuracion/MensajesController.php:306` | Propiedad no definida `SYSMensaje::$ErroresSistema` | Igual |
| `app/Http/Controllers/Configuracion/MensajesController.php:202,203` | `??` sobre una expresión no nullable | Probablemente sobra |
| `app/Listeners/Monitoreo/RegistrarLogout.php:28` | `=== null` sobre `Authenticatable` siempre falso | Revisar el tipo: en logout `$event->user` puede ser null |
| `app/Services/Monitoreo/AccesoAdmin.php:14` | `?->` innecesario | Cosmético |
| `app/Services/Monitoreo/ErrorRecorder.php:311` | Offset `'function'` en un frame de trace | Revisar |

Además, `ErrorRecorder.php` suma 1 al ratchet `getMessage() en response()->json` (284 → 285). Si ese JSON llega al cliente, se recomienda un mensaje genérico con código de referencia.

## 3. PT: health check

- `app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php:102`: `ConnectionInterface::query()` no está en la interfaz. En runtime funciona porque la conexión concreta sí lo tiene, pero conviene tipar `Illuminate\Database\Connection`. Está en el baseline.
- No hay tests de PT fallando en sqlite: la fase 10 no tocó `tests/*/Planeacion/**`.

## 4. urd-eng (dueño de `resources/views/livewire/urd-eng/**`, `resources/js/urd-eng/**`)

Aviso, no pedido. Con aprobación del owner, la fase 10 movió el `<script>` inline que 434fee0 había vuelto a meter en `program-board.blade.php` a `resources/js/urd-eng/priority-sort.ts`, importado por `program-board.ts`, sin cambiar el comportamiento. `ProgramBoardStructureTest` prohíbe `<script>` en esa vista: el drag de prioridad debe seguir viviendo en TS.

## 5. FE (fase 15): tipado de `tejido/`

`tsconfig.json` excluye `resources/js/tejido/**`: `inventario-telas.ts` tiene 1 error en `strict`. FE-06 lo tipa y quita el `exclude`.

## 6. MON-A: `trustProxies` (SEC-02)

`bootstrap/app.php:31` → `trustProxies(at: '*')`. Aplicar la recomendación del SUMMARY (§ Decisiones pendientes, punto 4) cuando el owner confirme si hay un proxy delante de Laragon.
