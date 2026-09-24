# 11-03 — Resumen: alertas de errores por correo a destinatario fijo

**Rama:** `claude/11-03-alertas-correo` (base `claude/friendly-hopper-506bg9`) · **IDs:** MON-10, MON-11
**Decisión del owner (2026-09-24):** «esto es fijo, solo quiero que me lleguen a mí». Las alertas de error nuevo o regresión van **solo por correo** a `francost15@gmail.com`. No van por Telegram ni a los suscriptores de `SYSMensajes`.

## Qué se hizo

| Pieza | Archivo(s) |
|---|---|
| Notifier por correo (antes `ErrorTelegramNotifier`) | `app/Services/Monitoreo/ErrorAlertaNotifier.php`, `ErrorRecorder.php` (inyección) |
| Mailable en español | `app/Mail/Monitoreo/ErrorSistemaMail.php` |
| Destinatario y tope | `config/monitoreo.php`: `errores.correo_alertas` (env `MONITOREO_ALERTA_CORREO`, por defecto `francost15@gmail.com`); `telegram_max_hora` → `alertas_max_hora` |
| Canal Telegram `ErroresSistema` eliminado (nunca se desplegó) | Se borraron `database/migrations/2026_09_24_000002_add_errores_sistema_to_sysmensajes.php` y `database/sql/sysmon_sysmensajes.sql`. `SYSMensaje`, `MensajesController` y `mensajes.blade.php` volvieron al estado previo a 11-02: sin columna, `colspan` en 19 y «Correo» otra vez en `cells[18]`. |
| Contrato | `11-CONTRACT.md` §8 (llaves) y §9 (canal = correo fijo); nota en `11-02-SUMMARY.md` |
| Tests | `tests/Feature/Monitoreo/AlertasCorreoTest.php` (sustituye a `AlertasTelegramTest`); `ErroresTest` apaga el envío con `correo_alertas = ''` |

Se conserva todo lo de 11-02:
- solo alerta en error nuevo o regresión;
- tope global por hora;
- `afterResponse` en web y envío en el momento en consola;
- marca de un solo uso;
- `AlertadoEn` solo se marca si el envío no falló;
- nunca lanza.

El patrón es el de `CrudoAlineacionNotifier`: `Mail::to()->send`, try/catch y registro del resultado en el log. El destinatario se valida con `SYSMensaje::soloCorreosValidos`; si está vacío o no es válido, no se envía y queda un warning en el log.

**Correo:**
- Asunto: `[Towell] ERROR NUEVO: <Clase>` o `[Towell] REGRESIÓN: <Clase>`.
- Cuerpo: origen, clase, mensaje (máximo 300 caracteres), ruta, ocurrencias, primera y última vez, y link a `url('/admin/errores/{id}')`.
- El HTML se arma en el Mailable con todo escapado con `e()`. No lleva vista Blade para no salir de los globs del track. No incluye la traza ni el input.

## Garantías (con test)

- Un error nuevo manda 1 correo solo a `francost15@gmail.com`, con el link al detalle, y marca `AlertadoEn`. Si se repite, no manda nada.
- Una regresión manda 1 correo con asunto `REGRESIÓN`. Un error nuevo con varias ocurrencias no se anuncia como regresión.
- Se respeta el tope por hora: con `alertas_max_hora=2` y 4 errores salen 2 correos, y los 4 errores quedan registrados.
- Si el mailer falla (mailer inexistente), la request devuelve su 500 normal con código de referencia y `AlertadoEn` queda en null.
- Con destinatario vacío o inválido no se envía nada.
- `correo_alertas` se puede sobreescribir por config.

## Evidencia

```
php artisan test tests/Feature/Monitoreo
  Tests:    56 passed (395 assertions)

php artisan test
  Tests:    6 failed, 49 skipped, 1246 passed
  # Los 6 fallos son los preexistentes: ProgramBoardLivewireTest ×2,
  # ProgramBoardStructureTest ×1, NuevoRequerimientoLivewireTest ×3

vendor/bin/pint --test (archivos PHP tocados)   pass
grep ErroresSistema|ErrorTelegramNotifier|telegram_max_hora en app, config, database, resources, routes, tests, bootstrap   0 resultados
```

- **Skill `code-review` (medium):** sin bugs. Dejó dos notas: el destinatario por defecto es el del owner, que es lo decidido, y el timeout SMTP de la nota 1 de abajo.
- **Skill `security-review`:** sin hallazgos. Todo el contenido va escapado, el asunto viaja como header tipado y el destinatario sale solo de config.

## Cómo desplegar

1. Los `MAIL_*` de Resend ya existen en el `.env` de producción y no hay que cambiar nada. Opcional: `MONITOREO_ALERTA_CORREO=otro@correo` para cambiar el destinatario.
2. Si en algún servidor ya se había corrido la migración `2026_09_24_000002_add_errores_sistema_to_sysmensajes` (no debería), la columna queda huérfana y no estorba. Para quitarla hay que borrar primero su constraint de default: se llama `DF_SYSMensajes_ErroresSistema` si se usó el `.sql`, y si fue con `migrate` el nombre lo generó SQL Server y se ve en `sys.default_constraints`. Luego `ALTER TABLE dbo.SYSMensajes DROP COLUMN ErroresSistema;` y borrar su fila de `dbo.migrations`.
3. `php artisan optimize:clear && php artisan optimize`.

**Para el integrador:** agregar a `.env.example` (lo edita la sesión `claude/10-base`):

```
# Destinatario fijo de las alertas de errores nuevos/regresiones (opcional; por defecto francost15@gmail.com)
MONITOREO_ALERTA_CORREO=
```

Ojo: `MONITOREO_ALERTA_CORREO=` vacío en `.env` **apaga** las alertas, porque `env()` devuelve `''`. Para usar el valor por defecto, dejarla comentada o no ponerla.

## Notas y pendientes

1. En consola (`queue:work`, scheduler) el correo sale en el momento. Si el SMTP de Resend tarda, retiene al worker hasta el timeout del mailer (`config/mail.php`, que no es de este track). Ahí se podría fijar `timeout` para el mailer smtp.
2. El link «Ver detalle» usa `url()`, igual que el mensaje de Telegram anterior. Depende de `APP_URL` o del host de la request.
3. No hace falta `HANDOFF.md`: solo se tocaron archivos propios. La variable va arriba, para el integrador.
