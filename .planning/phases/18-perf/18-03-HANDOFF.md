# HANDOFF — 18-03 (Telegram sin bloquear) → otros dueños

## 1. Mensaje real en 3 alertas de éxito → dueño de las vistas de Tejido (19-xx Tejido)

Los botones ya devuelven el resultado real en `data.message` ("Reporte enviado por Telegram a 2 de 3
destinatarios"). La vista principal de cortes (`cortes-eficiencia.blade.php:731`) ya lo muestra. Estas tres
alertas de éxito ponen un texto fijo e ignoran el número (las de error ya usan `data.message`):

| Archivo | Línea | Cambio (1 línea) |
|---|---|---|
| `resources/views/modulos/cortes-eficiencia/visualizar-cortes-eficiencia.blade.php` | 402 | `alert(data.message \|\| 'Imagen descargada y enviada por Telegram exitosamente.');` |
| `resources/views/modulos/cortes-eficiencia/visualizar-cortes-eficiencia.blade.php` | 440 | `alert(data.message \|\| 'Reporte enviado por Telegram exitosamente.');` |
| `resources/views/modulos/marcas-finales/reporte-marcas.blade.php` | 224 | `alert(data.message \|\| 'Reporte enviado por Telegram exitosamente.');` |

**Por qué:** sin esto el usuario sigue viendo "exitosamente" cuando llegó a 1 de 3. El caso "no llegó a
nadie" ya se ve bien sin el cambio (`success:false` → rama de error con `data.message`).

## 2. `defer()` en Paros y Crudo no libera la respuesta en producción → dueños de Mantenimiento y Crudo

Medido en 18-03 (SUMMARY §Decisión): con Apache/mod_php o php-cgi el navegador espera a que terminen los
callbacks de `defer()`. Estos siguen haciendo esperar al operador lo que tarde Telegram (ahora ≤ 8 s por el
helper, antes 5 s × N):

- `app/Http/Controllers/Mantenimiento/MantenimientoParosController.php:545` y `:897` (`notifyCreated` / `notifyClosed`).
- `app/Http/Controllers/Crudo/CrudoAuditController.php:68` (paro de crudo) y `:99` (correo de alineación).

**Cambio propuesto:** que `ParoTelegramNotifier::dispatch()` encole con
`EnviarMensajeTelegram::encolar(new EnviarMensajeTelegram(...))` en vez de mandar (los controllers pueden
seguir llamando igual; el `defer()` sobra pero no estorba). Requiere el worker del runbook §8 en producción.
No lo hice porque cambia el comportamiento de dos módulos fuera de este plan.

## 3. `SendUrdidoQualityNotification::dispatchAfterResponse` → dueño de Urdido / Programas

`app/Services/Programas/ProgramBoardActionService.php:241` y
`app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php:395`: `dispatchAfterResponse`
corre el job en el mismo proceso después de `send()`, igual que `defer()`: el navegador espera. Con el
worker ya instalado, basta `SendUrdidoQualityNotification::dispatch(...)`.

## 4. Otros envíos a Telegram que pueden adoptar `App\Services\Telegram\TelegramEnvio`

Fuera de este plan, siguen secuenciales: `NotificacionTelegramDesarrolladorService` (5 s × N),
`Mecanicos/ReporteEstadoMaquinaTelegramNotifier`, `Telegram/TelegramController::send`,
`Jobs/Programas/SendUrdidoQualityNotification`.

## 5. (Opcional) Worker desde `scheduler.bat` → integrador

El runbook crea una tarea programada aparte. Alternativa con una sola tarea: agregar al schedule
(`routes/console.php`) `Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();`.
No lo toqué: `routes/**` estaba prohibido en esta sesión.
