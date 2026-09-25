# 18-03 — Telegram sin bloquear + avisos de modelo (SUMMARY)

**Rama:** `claude/telegram-no-bloquear` (base `claude/friendly-hopper-506bg9`). **IDs:** PERF-13 (Telegram), PERF-14 (avisos de modelo), sin ID previo.
**Plan:** `18-03-PLAN.md` (aprobado por el owner 2026-09-25). **HANDOFF:** `18-03-HANDOFF.md` (3 alertas de 1 línea en vistas de Tejido; propuestas para Paros/Crudo/Urdido).

> **Despliegue, antes que nada:** con `QUEUE_CONNECTION=database` (el valor de `.env.example`) y **sin worker**, los avisos de
> atado terminado, montado de julio y solicitud de trama se quedan en `dbo.jobs` sin salir. Orden: `database/sql/queue_jobs_tablas.sql`
> → tarea programada del worker (runbook `deploy.md` §8) → deploy. Si el worker no está listo: `QUEUE_CONNECTION=sync`.

## Decisión: cola `database`, no `defer()` (con evidencia)

`defer()` corre en `Kernel::terminate()`, después de `Response::send()`. Symfony solo corta la conexión con
`fastcgi_finish_request` (PHP-FPM) o `litespeed_finish_request`; en cualquier otro SAPI hace `flush()` y la
conexión sigue abierta hasta que termina el script.

Medido en esta sesión: Apache 2.4 + PHP 8.3 (la versión de `scheduler.bat` en producción: `php-8.3.28`), un
script que replica `Application::handleRequest()` (`JsonResponse::send()` con el HttpFoundation del repo y
luego `sleep(3)` como callback diferido), `curl` midiendo primer byte y respuesta completa:

| SAPI | `fastcgi_finish_request` | Primer byte | Respuesta completa |
|---|---|---|---|
| `apache2handler` (mod_php, lo normal en Laragon) | no existe | 0.008 s | **3.008 s** |
| `cgi-fcgi` (php-cgi + mod_fcgid) | no existe | **3.022 s** | 3.022 s |

Con mod_php el cuerpo va *chunked* y el último fragmento sale al terminar el script; con php-cgi mod_fcgid
guarda todo. `fetch().then(r => r.json())` espera el cuerpo completo: **en producción `defer()` no libera la
respuesta**. Por eso los efectos secundarios van a la cola `database` (`App\Jobs\Telegram\EnviarMensajeTelegram`).

**Verificación del owner:** `phpinfo()` → *Server API* (runbook §8, paso 1). `Apache 2.0 Handler` o `CGI/FastCGI`
confirman la decisión; si dijera `FPM/FastCGI`, `defer()` sí serviría (avisar).

Consecuencia fuera de este plan: `defer()` en Paros/Crudo y `dispatchAfterResponse` en Urdido tampoco liberan
la respuesta → `18-03-HANDOFF.md` §2–3.

## Qué se hizo

| ID | Cambio | Número antes → después |
|---|---|---|
| PERF-13 | `App\Services\Telegram\TelegramEnvio` (helper, 100 líneas útiles): `mensaje()` / `archivo()` con `Http::pool`, `connectTimeout(3)`, `timeout(8)` texto / `timeout(15)` PDF-imagen; devuelve el resultado por chat. `exitoso()`, `reintentable()`, `enviados()`, `resumen()` (texto para el usuario) y `registrarFallos()` (los mismos mensajes de log de los reportes). Lo usan los 6 puntos + `ParoTelegramNotifier`. | 3 chats, Telegram tarda 2 s: **6.02 s → 2.02 s** (medianas de 3). Telegram colgado: texto **60.0 s → 8.0 s**, PDF **90.1 s → 15.1 s**. |
| PERF-13 | Efectos secundarios a la cola: `AtadoresController::enviarNotificacionTelegramAtadoTerminado`, `NotificarMontadoJulioController::enviarNotificacionTelegram`, `RequerimientoStatusService::enviarTelegram` arman el mismo texto con los mismos destinatarios (incl. el respaldo `TELEGRAM_CHAT_ID` de Atadores) y llaman `EnviarMensajeTelegram::encolar()`. El job manda en paralelo, reintenta **una vez y solo** los chats con falla pasajera (conexión/429/5xx; sustituye el `retry(2, 200)` de Atadores sin duplicar a quien ya recibió) y loguea cada fallo con el mensaje, nivel y contexto de antes. Si el `dispatch` falla (no existe `dbo.jobs`), manda en línea y avisa en el log. | Lo que paga la petición: **0.01 s** (insert en `jobs`). Antes, con Telegram colgado: montado/trama **60 s**, atado terminado **120.7 s** (retry). Con `sync` (válvula): 2.02 s / peor caso 16.0 s. |
| PERF-13 | Botones explícitos síncronos (Cortes PDF `notificarTelegram`, Cortes imagen `notificarTelegramImagen`, Marcas `notificarTelegram`): los métodos de envío devuelven `{enviados, total}`; el JSON conserva `success` y `message` y agrega `enviados` y `destinatarios`. `message`: "Reporte enviado por Telegram a 2 de 3 destinatarios" / "Reporte: no se pudo enviar por Telegram (0 de 3 destinatarios)". `success=false` + 500 (el status que ya usaban para error) si no llegó a nadie. | Antes Cortes PDF y Marcas decían "exitosamente" aunque Telegram fallara (el resultado solo iba al log). |
| PERF-13 | Finalizar corte: el envío automático está **comentado** en `CortesEficienciaController` ("DESACTIVADO"). No se reactivó ni se tocó; si se reactiva, debe ir a la cola como los demás. | — |
| PERF-14 | `AppServiceProvider::boot` (fuera de producción): `preventSilentlyDiscardingAttributes` + `handleDiscardedAttributeViolationUsing` y `preventAccessingMissingAttributes` + `handleMissingAttributeViolationUsing` → `Log::warning('Modelo: …')` una vez por modelo+atributo por request. Nunca lanza; el atributo ausente sigue devolviendo `null`. | Suite completa: **1027 avisos** en el log (332 `fill()` descartó, 695 atributo no cargado), **113 pares** modelo+atributo distintos, **0 excepciones**. Los más repetidos: `Sistema\User::created_at/updated_at` (el helper de tests `createUsuario()` los pasa a `create()`), `ReqProgramaTejido::NombreCC1..5`, 14 columnas de `UrdProgramaUrdido`, `SYSRoles::Nivel`. |

Despliegue: `database/sql/queue_jobs_tablas.sql` (jobs + failed_jobs, idempotente, 2008 R2, registra sus
migraciones) y `docs/cerebro-towell/Runbooks/deploy.md` §8 (SAPI, tabla, `schtasks` cada minuto con
`queue:work --stop-when-empty --max-time=50`, verificación, válvula `sync`), §1 y paso 11 de §5.

## Cómo se midió

- `defer()`: Apache + `libapache2-mod-php8.3` y `php8.3-cgi` + `mod_fcgid` instalados en el contenedor (el PPA de 8.4 lo bloquea el proxy; el SAPI se comporta igual). Script en el scratchpad de la sesión.
- Telegram lento: `php -S` con `PHP_CLI_SERVER_WORKERS` que responde `{"ok":true}` tras 2 s (o 40 s = colgado); `Http::globalRequestMiddleware` redirige `api.telegram.org` al servidor local. "Antes" = el código original copiado tal cual (secuencial, `timeout(20)`, `retry(2,200)` en Atadores, `timeout(30)` en PDF). 3 destinatarios. `php -S` a veces reparte dos conexiones al mismo worker (3 `curl` en paralelo también tardan 4 s alguna vez): por eso la mediana de 3 corridas.

| Escenario (3 chats) | Antes | Después |
|---|---|---|
| Texto, Telegram 2 s | 6.02 s | 2.02 s |
| PDF 200 KB, Telegram 2 s | 6.02 s | 2.02 s |
| Texto, Telegram colgado | 60.04 s | 8.03 s |
| Atadores (con retry), colgado | 120.66 s | 16.04 s en el worker (`sync`); **0.01 s** en la petición |
| PDF, colgado | 90.07 s | 15.05 s |
| Respuesta de la acción diferida | = envío | 0.01 s (encolar) |

## Tests (nuevos, 22)

- `tests/Unit/Telegram/TelegramEnvioTest.php`: todos los chats reciben (dedup), timeouts 3/8/15 vistos en las opciones de Guzzle del stub, multipart, un chat caído no tumba a los demás, sin chats no hay peticiones, `resumen()`.
- `tests/Unit/Telegram/EnviarMensajeTelegramTest.php`: reintento solo de fallas pasajeras (502 → 2 intentos; 400 → 1; ok → 1) y log con el contexto; `encolar()` escribe en `jobs` sin tocar Telegram; sin tabla `jobs` manda en línea; nunca lanza.
- `tests/Feature/Telegram/AvisosDiferidosTest.php`: `POST /tejedores/atadodejulio/notificar` con Telegram de 2 s responde en < 2 s, `Http::assertNothingSent()` y el job lleva el mismo texto/destinatarios; atado terminado (destinatarios de Atadores y respaldo global); solicitar trama; sin bot no se encola.
- `tests/Feature/Telegram/BotonesTelegramTest.php`: imagen de cortes y marcas reportan "2 de 3" con un chat caído, y el fallo total con `success=false`.
- `tests/Feature/Telegram/AvisosDeModeloTest.php` (PERF-14; en esta carpeta por la propiedad de archivos): aviso único por atributo descartado y por atributo ausente (devuelve `null`), apagado en producción.

## Evidencia

```
php artisan test                       Tests: 5 failed, 1589 passed (20815 assertions)
  Los 5 fallos son preexistentes y fallan igual en la base sin estos cambios:
  CrudoDashboardServiceTest (4) y CrudoMachineDetailTest (1): fechas fijas de julio 2026
  (p. ej. paro del 2026-07-29) que ya salieron de la ventana de días del servicio.
php artisan test tests/Unit/Telegram tests/Feature/Telegram   22 passed
vendor/bin/phpstan analyse --memory-limit=2G                  [OK] No errors
npm run build                                                 ok
npm run ratchet                                               ratchet ok (10 metricas, ninguna subio)
vendor/bin/pint --test <archivos tocados>                     pass
```

`MarcasController.php` ya no pasaba Pint en la base (orden de `use` y 2 espacios); como CI corre Pint sobre
los archivos tocados, se formateó ese archivo (3 líneas ajenas al envío).

## Decisiones y riesgos

- **Un helper, no un framework:** `TelegramEnvio` no lee destinatarios ni decide logs; cada llamador conserva sus validaciones y mensajes. El job es genérico (texto + chats + cómo loguear) para los tres efectos secundarios.
- **Mismos logs:** mismos mensajes y niveles; el contexto por chat ahora trae `status` y `response` (JSON o cuerpo) en los tres; `RequerimientoStatusService` decía `body`. Excepción de conexión: `status=null`, `response` = mensaje.
- **`$tries = 1`** en el job: `handle()` nunca lanza, así que un segundo intento del job completo solo duplicaría avisos; el reintento es por chat dentro del job.
- **15 s por archivo con subida en paralelo** (revisión de código): N chats suben el archivo a la vez. Con los tamaños reales (PDF de la medición ~200 KB; imagen html2canvas típica < 1 MB) sobra; con una imagen de 6–10 MB a 5 chats en un enlace de 10 Mbps se pasaría de 15 s y el botón diría "0 de 5". Si pasa, subir `SEGUNDOS_ARCHIVO` en el helper (1 línea).
- **Paros (`ParoTelegramNotifier`)** solo cambió a paralelo + 3/8 s (antes 5 s × N); sigue en `defer()`, que en producción no libera la respuesta → HANDOFF §2.
- **Log por avisos de modelo:** activos solo fuera de producción (`APP_ENV != production`). En un Laragon de desarrollo el log crecerá con esas 113 combinaciones; son deuda real (selects parciales que luego leen columnas no seleccionadas, `fill()` con claves fuera de `$fillable`).

## Pendientes

- Owner: pasos de §8 del runbook (SAPI, `queue_jobs_tablas.sql`, `schtasks`, prueba de punta a punta).
- HANDOFF §1 (3 líneas en vistas de Tejido) para que el éxito parcial se vea en esas pantallas.
- HANDOFF §2–3: mover Paros, Crudo y Urdido a la cola cuando el worker esté en producción.
- Los 5 tests de Crudo con fechas fijas (no son de esta sesión).
