# 11-01 — Resumen: esquema, identidad, accesos, cierre remoto y Gate admin

**Rama:** `claude/11-mon-servidor` · **IDs:** MON-01, MON-02, MON-03, MON-04, MON-09, MON-12, MON-14 (+ la ruta `/admin` placeholder de MON-21)
**Contrato:** `11-CONTRACT.md`. Sin cambios incompatibles; los agregados y precisiones están en «Decisiones».

## Qué se hizo

| Pieza | Archivo(s) |
|---|---|
| Config (llaves del contrato §8, más `errores.ignorar`) | `config/monitoreo.php` |
| Provider: Gate `admin`, RateLimiters `login` y `telemetria`, estado de request `scoped` | `app/Providers/MonitoreoServiceProvider.php`, `bootstrap/providers.php` |
| 6 tablas: migración portable + `.sql` espejo con índice filtrado e INCLUDE | `database/migrations/2026_09_24_000001_create_sysmon_tables.php`, `database/sql/sysmon_tablas.sql` |
| Modelos con `MassPrunable` según `monitoreo.retencion` | `app/Models/Sistema/Monitoreo/{MonDispositivo,MonSesion,MonVista,MonError,MonErrorEvento,MonAcceso}.php` |
| Poda diaria a las 02:00 y cierre de sesiones expiradas cada 15 min | `routes/console.php` (solo se agregaron líneas) |
| Identidad: cookie `towell_disp` y touch con candado de 30 s | `app/Http/Middleware/Monitoreo/IdentificarDispositivo.php`, `app/Services/Monitoreo/DispositivoService.php` |
| Sesiones y accesos | `app/Listeners/Monitoreo/{RegistrarLogin,RegistrarLogout}.php`, `app/Services/Monitoreo/{SesionService,AccesoService}.php` |
| Login: rate limit previo a la contraseña, `login_fallido`, `bloqueo`, `Auth::logoutCurrentDevice()` | `app/Http/Controllers/AuthController.php` |
| Cierre remoto por dispositivo | `app/Http/Middleware/Monitoreo/AplicarCierreRemoto.php`, `app/Services/Monitoreo/CierreRemotoService.php` |
| `/admin` protegido (`can:admin`), vista placeholder | `routes/modules/admin.php`, `routes/web.php` (1 `require`), `resources/views/modulos/admin/index.blade.php` |
| Utilidades: kill switch, «nunca lanzar», conexión de errores, Gate | `app/Services/Monitoreo/{Monitoreo,EstadoRequest,AccesoAdmin}.php` |
| Middlewares en el grupo `web` (append) | `bootstrap/app.php` |

## Comportamiento clave

- **Nunca rompe la request.** Toda escritura pasa por `Monitoreo::seguro()`: ante cualquier `Throwable` escribe un `Log::warning` y sigue. Hay test con las tablas borradas: responde 200.
- **Barato.** El touch corre en `terminate()`, ya con la respuesta enviada, y a lo más una vez cada 30 s por dispositivo gracias a `Cache::add`. Los invitados reciben la cookie, pero no se crea renglón hasta que hay login o acceso.
- **Logout por dispositivo.** El logout ya no rota el `remember_token`, así que las demás tablets del mismo usuario siguen recordadas (hay test). El remember siempre activo y las contraseñas legacy no cambiaron.
- **Rate limit de login.** 10 por minuto por empleado+IP y 30 por minuto por IP. Se revisa **antes** de validar la contraseña. El intento 11 muestra «Demasiados intentos, espera N segundos» con el mismo estilo de error (`session('error')`) y registra `bloqueo`. Un login correcto limpia el contador del empleado.
- **Cierre remoto.**
  - `CierreRemotoService::solicitar($dispositivo, $admin)` es la API que usará la fase 13. Graba `CierreSolicitadoEn/Por`, la llave de cache `mon:cerrar:{uuid}` (1 día) y un acceso `admin_accion` con `ActorId`.
  - En la siguiente request de **ese** dispositivo: `logoutCurrentDevice()`, sesión invalidada y cookie remember borrada. HTML recibe redirect a login con flash; XHR o Livewire reciben `401 {message, code:'cierre_remoto'}`. `SYSMonSesion.MotivoFin` queda en `remoto` y se registra `SYSMonAcceso` de tipo `logout_remoto`.
  - Las demás tablets del usuario no se ven afectadas (hay test).
- **Kill switch.** Con `MONITOREO_ENABLED=false` no hay cookie ni escrituras, y el login funciona igual. El rate limit de login sigue activo porque es seguridad, no monitoreo.

## Decisiones (para el integrador y las fases 12–14)

1. **Listeners por descubrimiento de eventos**, no con `Event::listen`. Laravel 12 descubre `app/Listeners` por defecto; registrarlos también a mano duplicaba cada login. Se comprobó con `php artisan event:list`. Si el despliegue usa `event:cache`, hay que regenerarlo (`php artisan optimize`).
2. **Conexión `sqlsrv_monitoreo` perezosa.** Se registra en `Monitoreo::conexionErrores()` en el primer uso, copiando la config **vigente** de `sqlsrv`. Si se clonara en `boot()`, los tests (que cambian `sqlsrv` a sqlite después del boot) escribirían en el SQL Server real de quien los corre. No se tocó `config/database.php`.
3. **Tablas sin prefijo `dbo.` en los modelos** (`SYSMonDispositivo`, etc.), como `App\Models\Sistema\User`. Resuelven al esquema por defecto del login de la app, que es `dbo`. El `.sql` crea `dbo.SYSMon*`.
4. **Origen `login` vs `recordarme`.** El POST `/login` no tiene nombre de ruta (solo el GET se llama `login`), así que el listener lo distingue por `POST` + path `login`.
5. **`AplicarCierreRemoto` no actúa en `telemetria.*`.** Así el latido puede contestar `cerrar: true` (contrato §5.3) en vez de un 401.
6. **Llave agregada `monitoreo.errores.ignorar`** (lista de excepciones que no se registran). Es aditiva y la usa 11-02.
7. **Programación extra:** `monitoreo:cerrar-sesiones-expiradas` cada 15 min. Es la parte «la cierra la programación» del contrato §2.

## Evidencia

```
php artisan test tests/Feature/Monitoreo
  Tests:    22 passed (166 assertions)

php artisan test   (suite completa)
  Tests:    6 failed, 49 skipped, 1168 passed
```

Los 6 fallos son los mismos que la línea base previa a la fase y no se relacionan con ella: `ProgramBoardLivewireTest` ×2, `ProgramBoardStructureTest` ×1 y `NuevoRequerimientoLivewireTest` ×3.

Pint pasó sobre los archivos PHP tocados. En `routes/web.php` se revirtió el formato para dejar solo la línea `require`.

## Cómo desplegar

1. **Esquema** (una de dos):
   - `php artisan migrate` y luego, en SSMS, **solo** la sección «ÍNDICES EXTRA» de `database/sql/sysmon_tablas.sql` (índice filtrado de sesiones abiertas e INCLUDE de vistas); o
   - el DBA corre `database/sql/sysmon_tablas.sql` completo y registra la migración en `dbo.migrations`. El `INSERT` viene al inicio del script.
2. **`.env`** (opcional; los valores por defecto sirven):
   ```
   MONITOREO_ENABLED=true
   MONITOREO_AREAS_ADMIN=Sistemas
   ```
3. **Verificar el valor real de `area`** antes de dar acceso a `/admin` (el Gate compara sin acentos, sin espacios y en minúsculas):
   ```sql
   SELECT area, COUNT(*) AS usuarios
   FROM dbo.SYSUsuario
   GROUP BY area
   ORDER BY area;
   ```
   Si Sistemas aparece con otro nombre (por ejemplo `TI` o `Informática`), ponerlo en `MONITOREO_AREAS_ADMIN` (CSV).
4. `php artisan optimize:clear && php artisan optimize` (config, rutas y cache de eventos).
5. El scheduler ya debe estar corriendo (`schedule:run` cada minuto) para la poda de las 02:00 y el cierre de sesiones expiradas.

## Pendientes y notas

- `trustProxies(at: '*')` (ya existía) hace que `request()->ip()` confíe en `X-Forwarded-For`. Alguien que lo falsifique rota la IP de las llaves del rate limit de login. Conviene restringirlo a la IP real del proxy o balanceador cuando se conozca (ARQ, ola 2).
- `phpstan/phpstan` no se pudo instalar en este entorno: la API de GitHub devolvió 403 al zipball. Todavía no existe `phpstan.neon` (fase 10), así que no afecta los checks de esta fase.
