# Contrato de Monitoreo (fases 11 · 12 · 13 · 14)

**Estado:** aprobado 2026-09-24. **Dueño:** MON-A (fase 11). Cambios al contrato solo vía `HANDOFF.md` + integrador; las fases 12 (cliente), 13 (panel) y 14 (Pulse) trabajan contra este documento aunque el servidor aún no esté mergeado.

---

## 1. Principios

1. **Nunca romper la request del usuario.** Toda captura va en `try/catch`, sin lanzar; si la BD de monitoreo falla, se escribe al log y se sigue.
2. **Barato en el camino crítico.** El GET de una pantalla no hace escrituras extra salvo el "touch" de actividad cacheado (≤ 1 UPDATE cada 30 s por dispositivo). Las vistas se registran desde el cliente después del `load`.
3. **Kill switch:** `MONITOREO_ENABLED=false` apaga middleware de captura, endpoints (responden 204 sin tocar BD) y el script cliente (el layout no lo imprime).
4. **Sin datos sensibles:** nunca guardar contraseñas, payloads de formularios ni query strings completos; `Url` = solo path; en errores, solo **nombres** de llaves del input.
5. **Separado de la transacción que falló:** las escrituras de errores usan una conexión clonada `sqlsrv_monitoreo` (misma config que `sqlsrv`, registrada en runtime por `MonitoreoServiceProvider`), para no revertirse con la transacción del negocio.
6. **Acceso admin:** Gate `admin` = `Usuario.area` normalizada (trim, sin acentos, minúsculas) ∈ `config('monitoreo.areas_admin')` (default `['sistemas']`, env `MONITOREO_AREAS_ADMIN`, CSV).

## 2. Tablas (SQL Server, esquema `dbo`)

Tipos pensados para SQL Server; la migración usa Schema builder para que también corra en sqlite de tests. Fechas en hora local de planta (`America/Mexico_City`, igual que el resto del ERP). Espejo `.sql` en `database/sql/sysmon_tablas.sql` para el DBA.

### `SYSMonDispositivo`
| Columna | Tipo | Nota |
|---|---|---|
| Id | bigint identity PK | |
| Uuid | char(36) UNIQUE | cookie `towell_disp` |
| Nombre | nvarchar(80) null | lo pone el usuario (user-modal) o el admin |
| Tipo | nvarchar(20) | tablet \| movil \| pc \| desconocido |
| Modelo | nvarchar(80) null | `detectDeviceModel()` |
| SO | nvarchar(60) null | |
| Navegador | nvarchar(60) null | |
| UaHash | char(40) | sha1 del user agent (detecta cambio de navegador) |
| UltimaIp | nvarchar(45) | |
| UltimoUsuarioId | int null | `SYSUsuario.idusuario` |
| UltimaSesionId | bigint null | |
| PrimeraVez | datetime2 | |
| UltimaActividad | datetime2 | touch/latido |
| UltimaRuta | nvarchar(150) null | nombre de ruta |
| Visible | bit | pestaña visible en el último latido |
| InactivoSeg | int | segundos sin input al último latido |
| VersionFront | nvarchar(40) null | hash del manifest de Vite |
| Pantalla | nvarchar(20) null | `1280x800` |
| CierreSolicitadoEn | datetime2 null | |
| CierreSolicitadoPor | int null | idusuario del admin |

Índices: UX(Uuid), IX(UltimaActividad).

### `SYSMonSesion`
Id bigint PK · DispositivoId bigint · UsuarioId int · Origen nvarchar(12) (`login`\|`recordarme`) · Ip nvarchar(45) · Inicio datetime2 · UltimaActividad datetime2 · Fin datetime2 null · MotivoFin nvarchar(12) null (`logout`\|`remoto`\|`expirada`\|`reemplazada`).
Índices: IX(UsuarioId, Inicio), IX(DispositivoId, Inicio), filtrado `WHERE Fin IS NULL` (en SQL Server, vía `.sql`).
Una sesión "expira" cuando `UltimaActividad` supera `config('monitoreo.sesion_expira_min')` (default 120) — lo cierra la poda/programación, no el request.

### `SYSMonVista`
Id bigint PK · Uuid char(36) UNIQUE · SesionId bigint null · DispositivoId bigint · UsuarioId int · Ruta nvarchar(150) (nombre de ruta; si no tiene nombre, URI con placeholders — nunca IDs) · Url nvarchar(300) (solo path) · Tipo nvarchar(6) (`carga`\|`suave`) · Inicio datetime2 · Fin datetime2 null · VisibleMs int null · ServidorMs int null · ConsultasN int null · ConsultasMs int null · TtfbMs int null · DomMs int null · CargaMs int null · Kb int null.
Índices: IX(Ruta, Inicio) INCLUDE(CargaMs, ServidorMs) · IX(DispositivoId, Inicio).

### `SYSMonError`
Id bigint PK · Huella char(40) UNIQUE · Origen nvarchar(10) (`php`\|`js`\|`livewire`\|`red`\|`http5xx`) · Clase nvarchar(200) · Mensaje nvarchar(1000) (saneado; en `QueryException` solo SQL con placeholders, sin bindings) · Archivo nvarchar(300) null · Linea int null · Ruta nvarchar(150) null · Estado nvarchar(10) (`nuevo`\|`visto`\|`resuelto`\|`ignorado`) · Ocurrencias int · PrimeraVez datetime2 · UltimaVez datetime2 · ResueltoPor int null · ResueltoEn datetime2 null · Nota nvarchar(500) null · AlertadoEn datetime2 null.
Índices: UX(Huella), IX(Estado, UltimaVez).
**Huella** = sha1(`origen|clase|archivo-relativo|línea|mensaje normalizado`), donde normalizar = quitar números, UUIDs, rutas absolutas y valores entre comillas. Un error `resuelto` que vuelve a ocurrir pasa a `nuevo` (regresión) y dispara alerta.

### `SYSMonErrorEvento`
Id bigint PK · ErrorId bigint · Fecha datetime2 · UsuarioId int null · DispositivoId bigint null · SesionId bigint null · Url nvarchar(300) null · Metodo nvarchar(8) null · Status smallint null · VersionFront nvarchar(40) null · Traza nvarchar(max) null (≤ 8 KB; stack + nombres de llaves del input).
Índice: IX(ErrorId, Fecha). Tope: 50 eventos por huella por día (el contador `Ocurrencias` siempre suma).

### `SYSMonAcceso`
Id bigint PK · Fecha datetime2 · Tipo nvarchar(20) (`login`\|`login_fallido`\|`logout`\|`logout_remoto`\|`recordarme`\|`bloqueo`\|`authz_denegaria`\|`admin_accion`) · NumeroEmpleado nvarchar(20) null (lo tecleado) · UsuarioId int null · DispositivoId bigint null · Ip nvarchar(45) · Motivo nvarchar(200) null · ActorId int null (admin que ejecutó la acción).
Índices: IX(Fecha), IX(Tipo, Fecha), IX(UsuarioId, Fecha).

### Retención (`MassPrunable` + `model:prune` diario 02:00)
Vista 90 d · ErrorEvento 90 d · Sesión 180 d · Acceso 365 d · Error `resuelto`/`ignorado` sin ocurrencias en 180 d. Valores en `config('monitoreo.retencion')`.

## 3. Identidad y sesión

- Cookie **`towell_disp`**: UUID v4, 5 años, `HttpOnly`, `SameSite=Lax`, `Secure` solo si la request es HTTPS. La pone el middleware `IdentificarDispositivo` en **cualquier** request web (incluido el login de invitado) si falta. Excluida del cifrado de cookies (`encryptCookies(except: ['towell_disp'])`) solo si hace falta leerla fuera de Laravel; si no, cifrada.
- La sesión Laravel guarda `mon_sesion_id` (id de `SYSMonSesion`), se conserva tras `session()->regenerate()`.
- Listener de `Illuminate\Auth\Events\Login`: si la ruta actual es `login` (POST) → `Origen=login`, si no → `recordarme` (restauración por cookie remember). Cierra con `MotivoFin=reemplazada` cualquier sesión abierta previa **del mismo dispositivo**.
- `Logout` / `CurrentDeviceLogout` → `Fin`, `MotivoFin=logout`.
- **Logout normal = `Auth::logoutCurrentDevice()`** (decisión 2026-09-24): no invalida el remember de otras tablets del mismo usuario.

## 4. Endpoints de telemetría (cliente → servidor)

Archivo `routes/modules/telemetria.php`, requerido desde `routes/web.php` dentro del grupo `auth`. Middleware adicional: `throttle:telemetria` (definido en el provider). `withoutMiddleware([SetSqlContextInfo::class, ProgramaTejidoContext::class])`. CSRF normal (el cliente manda `X-CSRF-TOKEN` desde `<meta name="csrf-token">`; `sendBeacon` manda `_token` en `FormData`). Todas responden rápido y **nunca 500**: ante error interno → 204 y log.

| Método y ruta | Nombre | Cuerpo (JSON, o FormData en beacon) | Respuesta |
|---|---|---|---|
| POST `/telemetria/latido` | `telemetria.latido` | `{ vista?: uuid, ruta: string, visible: bool, inactivoSeg: int, version?: string, pantalla?: "WxH" }` | `200 { cerrar: bool, intervalo: int }` (`intervalo` en segundos: 60 visible / 300 oculta, configurable) |
| POST `/telemetria/vista` | `telemetria.vista` | `{ uuid, tipo: "carga"\|"suave", ruta, url, nav: { ttfb?, dom?, carga?, kb? }, st?: { app?, db?, q? } }` | `204` |
| POST `/telemetria/vista/{uuid}/fin` | `telemetria.vista.fin` | `{ visibleMs: int }` | `204` |
| POST `/telemetria/error` | `telemetria.error` | `{ origen: "js"\|"livewire"\|"red", mensaje, fuente?, linea?, col?, stack?, url, vista?, status?, metodo? }` | `204` (throttle 30/min por dispositivo; excedente se descarta en silencio) |
| POST `/telemetria/dispositivo/nombre` | `telemetria.dispositivo.nombre` | `{ nombre: string ≤ 80 }` | `204` |

Validación: longitudes truncadas (no rechazar), enteros acotados (0 – 600 000 ms), `ruta`/`url` sin query string.

### Señales en el HTML (las imprime el servidor)
- `<meta name="towell-ruta" content="{nombre de ruta}">` y `<meta name="towell-version" content="{hash manifest Vite}">` en `components/layout-head.blade.php`.
- `<meta name="towell-telemetria" content="1">` solo si `MONITOREO_ENABLED` y hay usuario autenticado; el cliente no arranca si no existe.
- Header `Server-Timing: app;dur=<ms>, db;dur=<ms>;desc="<n> q"` en respuestas HTML GET (middleware `ServerTiming`).

### Eventos del navegador (contrato cliente)
- `towell:http-error` — lo emite `utils/http` (fase 15): `detail = { status, url, method }`.
- `towell:conexion` — lo emite el cliente de telemetría (fase 12): `detail = { online: bool }`; lo consume UX (banner sin conexión).
- Livewire: `Livewire.hook('request', ({ fail }) => ...)` para fallos; `document` `livewire:navigated` para navegación suave.

## 5. Cierre remoto

1. Admin pulsa "Cerrar sesión" en `/admin` → `SYSMonDispositivo.CierreSolicitadoEn/Por` + `Cache::put("mon:cerrar:{uuid}", true, 1 día)` + `SYSMonAcceso(Tipo=admin_accion, ActorId)`.
2. Middleware `AplicarCierreRemoto` (grupo web, después de auth): si existe la llave para la cookie del dispositivo → `Auth::logoutCurrentDevice()`, invalidar sesión, `Cache::forget`, `SYSMonAcceso(logout_remoto)`, `SYSMonSesion.MotivoFin=remoto`; HTML → redirect a login con flash "Un administrador cerró la sesión de este equipo."; XHR/Livewire → 401 JSON.
3. El latido responde `cerrar: true` → el cliente recarga la página (y cae en el paso 2).

## 6. Servidor → panel (lecturas, fase 13)

El panel lee las tablas directamente (query builder/Eloquent de solo lectura). Definiciones:
- **En línea:** `UltimaActividad` < `config('monitoreo.en_linea_seg')` (150) y `Visible=1`.
- **Inactivo:** en línea pero `InactivoSeg` > `config('monitoreo.inactivo_seg')` (600) o `Visible=0`.
- **Desconectado:** resto.
- **Lenta:** `ServidorMs` > 800 o `CargaMs` > 3000 (configurables).
- **Front desactualizado:** `VersionFront` ≠ versión actual del manifest.

## 7. Rutas admin

`routes/modules/admin.php`, prefijo `/admin`, nombre `admin.`, middleware `auth` + `can:admin`:
`/admin` (en línea) · `/admin/sesiones` · `/admin/navegacion` · `/admin/rendimiento` · `/admin/errores` · `/admin/errores/{id}` · `/admin/accesos` · `/admin/pulse` (fase 14, `PULSE_PATH=admin/pulse`, gate `viewPulse` → `admin`).
Fase 11 crea el archivo con la ruta `/admin` apuntando a una vista mínima (placeholder) y el Gate; fase 13 la llena.

## 8. Configuración (`config/monitoreo.php`)

```php
'enabled' => env('MONITOREO_ENABLED', true),
'areas_admin' => array_filter(array_map('trim', explode(',', env('MONITOREO_AREAS_ADMIN', 'Sistemas')))),
'latido_seg' => ['visible' => 60, 'oculta' => 300],
'touch_cache_seg' => 30,
'en_linea_seg' => 150,
'inactivo_seg' => 600,
'sesion_expira_min' => 120,
'umbrales' => ['servidor_ms' => 800, 'carga_ms' => 3000],
'poll_seconds' => 15,
'errores' => ['max_eventos_dia' => 50, 'telegram_max_hora' => 10, 'throttle_cliente_min' => 30],
'retencion' => ['vista_dias' => 90, 'evento_dias' => 90, 'sesion_dias' => 180, 'acceso_dias' => 365, 'error_resuelto_dias' => 180],
```

## 9. Alertas Telegram

- Nueva columna booleana `ErroresSistema` en `dbo.SYSMensajes`; se agrega a `SYSMensaje::columnasModuloPermitidas()` y a la pantalla de Mensajes.
- `ErrorTelegramNotifier` copia el patrón de `app/Services/Mantenimiento/ParoTelegramNotifier.php` (timeout 5 s, nunca lanza, texto plano sin `parse_mode`, loguea resultado). Destinatarios: `SYSMensaje::getChatIdsPorModulo('ErroresSistema')`.
- Se dispara con `dispatch(...)->afterResponse()` solo en huella **nueva** o **regresión**, máximo `telegram_max_hora` por hora (global). Mensaje: origen, clase, mensaje corto, ruta, ocurrencias, link a `/admin/errores/{id}`.
