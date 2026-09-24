# 11-02 — Resumen: errores, 5xx, Server-Timing, telemetría, Telegram y página 500

**Rama:** `claude/11-mon-servidor` · **IDs:** MON-05, MON-06, MON-07, MON-08, MON-10, MON-11, MON-13
**Contrato:** `11-CONTRACT.md`. Sin cambios en tablas, rutas ni cuerpos; hay dos precisiones en «Decisiones».

> **Superado por 11-03:** las alertas de errores ya no van por Telegram sino por correo a un destinatario fijo. La columna `SYSMensajes.ErroresSistema` (migración y `.sql`) se eliminó antes de desplegarse y los cambios en Mensajes se revirtieron. Ver `11-03-SUMMARY.md`. Los pasos 2 y 3 de «Cómo desplegar» ya no aplican.

## Qué se hizo

| Pieza | Archivo(s) |
|---|---|
| Errores PHP agrupados por huella, con eventos y tope diario | `app/Services/Monitoreo/ErrorRecorder.php`, `bootstrap/app.php` (`$exceptions->report`, sin `->stop()`) |
| 5xx que los `catch` devuelven sin pasar por el handler | `app/Http/Middleware/Monitoreo/CapturarRespuesta5xx.php` |
| `report($e)` en `apiErrorResponse` (la respuesta no cambia) | `app/Support/Http/Concerns/HandlesApiErrors.php` |
| Header `Server-Timing: app;dur=X, db;dur=Y;desc="N q"` en GET HTML | `app/Http/Middleware/Monitoreo/ServerTiming.php` + listener `QueryExecuted` en el provider |
| 5 endpoints de telemetría (contrato §4) | `app/Http/Controllers/Monitoreo/TelemetriaController.php`, `app/Http/Requests/Monitoreo/TelemetriaRequest.php`, `routes/modules/telemetria.php`, `routes/web.php` (1 `require`) |
| Señales en el HTML: `towell-ruta`, `towell-version` y `towell-telemetria` | `resources/views/components/layout-head.blade.php` (solo 3 `<meta>`), `Monitoreo::versionFront()` |
| Canal Telegram `ErroresSistema` | `database/migrations/2026_09_24_000002_add_errores_sistema_to_sysmensajes.php`, `database/sql/sysmon_sysmensajes.sql`, `SYSMensaje` (fillable, casts, `columnasModuloPermitidas`), `MensajesController`, `mensajes.blade.php` |
| Alertas: error nuevo o regresión, `afterResponse`, tope global por hora | `app/Services/Monitoreo/ErrorTelegramNotifier.php` |
| Página 500: se quitó «ha sido notificado» y se muestra «Código de referencia: #id» | `resources/views/errors/500.blade.php` |

## Garantías (con test)

- **Separado de la transacción que falla.** Un `report()` dentro de una transacción que luego se revierte queda registrado, porque escribe por `sqlsrv_monitoreo`.
- **Nunca lanza, nunca recursión.** Si falta la tabla `SYSMonError`, la request devuelve su error normal (página 500 o el JSON del `catch`).
- **Sin datos sensibles.**
  - La contraseña y los valores del input no aparecen en ninguna columna; la traza solo lleva `Input: numero_empleado, contrasenia, items.0.nota`.
  - En `QueryException` solo se guarda `SQLSTATE[..] <SQL con ?>`.
  - `Url` es solo el path.
  - Los mensajes `http5xx` se guardan normalizados (`Folio 555 no existe` → `Folio N no existe`).
- **Huella.**
  - Números, comillas, UUIDs y rutas distintos caen en la misma huella.
  - Un error `resuelto` que vuelve pasa a `nuevo` y alerta.
  - Tope de `max_eventos_dia` eventos por huella; `Ocurrencias` siempre suma.
- **Sin duplicados.** Un error reportado (handler o `HandlesApiErrors`) no se vuelve a contar como `http5xx`.
- **Telemetría.**
  - Requiere sesión, trunca en vez de rechazar y acota los enteros a 0–600 000 ms.
  - `vista` es idempotente por uuid.
  - `vista/{uuid}/fin` acepta FormData (beacon) y solo cierra vistas del usuario autenticado.
  - `error` descarta en silencio lo que pase de 30 por minuto por dispositivo.
  - Nunca responde 500, aunque falten las tablas; con `MONITOREO_ENABLED=false` responde 204 sin escribir.
  - El latido contesta `cerrar: true` tras solicitar el cierre, sin desloguear en esa llamada.
- **Telegram.**
  - Un error nuevo manda un mensaje a cada chat activo con `ErroresSistema=1`; uno repetido no manda nada.
  - Respeta el tope por hora.
  - Un fallo de Telegram no afecta la request y `AlertadoEn` queda en null.

## Decisiones y precisiones al contrato (para el integrador → fases 12 y 13)

1. **Huella de `http5xx`.** Es `sha1(http5xx | HTTP <status> | <ruta> | <status> | mensaje normalizado)`, o sea ruta|status|mensaje, como pide el plan. Ahí `Archivo` y `Linea` quedan en null.
2. **`Clase` de los errores de cliente.**
   - `js` y `livewire`: se toma del prefijo del mensaje (`TypeError: …` → `TypeError`) y, si no hay, queda `Error`.
   - `red`: queda `HTTP <status>`, y su mensaje se guarda normalizado.
   - `fuente` se guarda solo como path (sin host ni `?v=`).
3. **`EstadoRequest` se reinicia al inicio de `IdentificarDispositivo`.** El kernel HTTP no limpia los `scoped` entre requests del mismo proceso (tests, Octane). En FPM no cambia nada.
4. **`VisibleMs` se acota a 24 h, no a 600 000 ms.** El contrato §4 acota los enteros a 0–600 000 ms. Ese tope tiene sentido para tiempos de carga, pero un andón queda visible todo el turno, así que el tiempo de permanencia usa su propio tope.
5. **Alertas en consola.** En `queue:work` o el scheduler, la alerta se envía en el momento. Con `afterResponse` nunca saldría, porque el worker no termina entre jobs. Además, «REGRESIÓN» vs «ERROR NUEVO» se decide al registrar el error, no con `Ocurrencias` leído después.
6. **Alerta de un solo uso.** `Application::terminate()` no limpia sus callbacks, así que cada alerta programada lleva una marca para no repetirse en procesos que atienden varias requests.
7. **Página 500.** Muestra el Id de `SYSMonErrorEvento`. Si ese día ya se alcanzó el tope de eventos de la huella, no hay código y solo se ve el mensaje genérico.
8. **Mensajes.** La columna nueva va después de «Andon» y la de «Correo» pasa a ser la celda 19 en el JS de actualización.

## Evidencia

```
php artisan test tests/Feature/Monitoreo
  Tests:    55 passed (389 assertions)      # 22 de 11-01 + 33 de 11-02

php artisan test
  Tests:    6 failed, 49 skipped, 1201 passed
  # Los 6 fallos son los mismos de la línea base previa a la fase 11:
  # ProgramBoardLivewireTest ×2, ProgramBoardStructureTest ×1, NuevoRequerimientoLivewireTest ×3

npm run build   ✓ built
vendor/bin/pint (archivos tocados)   pass
```

La skill `code-review` (nivel medium) encontró tres problemas y los tres se corrigieron:
- el tope de `VisibleMs`;
- las alertas que no salían desde los workers de cola;
- las alertas nuevas que se anunciaban como regresión cuando la huella ya tenía varias ocurrencias.

La skill `security-review` sobre el diff completo no encontró vulnerabilidades de confianza alta o media. Quedó una nota de diseño: con `logoutCurrentDevice()` el `remember_token` no rota. Es la decisión del owner; si algún día se necesita, se puede agregar un «cerrar en todos los equipos» que lo rote.

## Cómo desplegar

1. Lo de `11-01-SUMMARY.md` (tablas SYSMon*, `.env`, `SELECT` de `area`).
2. **Columna de Telegram**, con una de dos opciones:
   - `php artisan migrate`;
   - o que el DBA corra `database/sql/sysmon_sysmensajes.sql` y registre la migración (el `INSERT` viene en el script).
3. En **Configuración › Mensajes**, marcar «Errores del sistema» a los destinatarios de Sistemas. Requiere `TELEGRAM_BOT_TOKEN` (ya existe).
4. `php artisan optimize:clear && php artisan optimize`.
5. Opcional: `MONITOREO_ENABLED=false` apaga todo sin desplegar código.

## Pendientes

- La fase 12 (cliente TS) consume las metas y los endpoints tal como están en el contrato §4.
- La fase 13 usa `CierreRemotoService::solicitar()` y lee las tablas directo.
- No hace falta `HANDOFF.md`: no hubo cambios en archivos de otros dueños.
