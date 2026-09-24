# 13-01 — Resumen: panel /admin de monitoreo (Livewire)

**Rama:** `claude/13-14-mon-pulse-panel` (base `claude/friendly-hopper-506bg9`) · **IDs:** MON-21 (rutas y Gate; el enlace en user-modal es de la sesión 12), MON-22, MON-23, MON-24, MON-25, MON-26, MON-27, MON-28
**Plan:** `13-01-PLAN.md`. **Contrato:** `../11-mon-servidor/11-CONTRACT.md` §5–§7. Las tablas de MON-A solo se leen, salvo las escrituras que el contrato asigna al panel.

## Qué se hizo

| Ruta | Componente | Qué muestra o hace |
|---|---|---|
| `/admin` (`admin.index`) | `App\Livewire\Admin\EnLinea` | Tarjetas con conteo en línea / inactivos / desconectados (24 h), que también filtran. Tabla de dispositivos: estado, dispositivo, usuario, área, IP, tipo/SO/navegador/pantalla, página, tiempo en sesión, última actividad y front. **Cerrar sesión** (confirmación con `notify.confirm` → `CierreRemotoService::solicitar()`, marca «cierre pendiente») y **Renombrar** (modal, máximo 80). `wire:poll.visible` cada `max(10, monitoreo.poll_seconds)` s; el poll se pausa mientras el modal está abierto. |
| `/admin/sesiones` | `Sesiones` | Historial filtrable por usuario/número/dispositivo/IP (búsqueda), abiertas/cerradas, dispositivo (`?dispositivo=`) y rango de fechas (por defecto 7 días). Muestra duración, origen, IP y motivo de fin. |
| `/admin/navegacion` | `Navegacion` | Pide un dispositivo o un número de empleado, más el día. Muestra barras con el tiempo visible por página y la línea de tiempo del día (hora, página, URL, tiempo visible, tipo, servidor, carga). |
| `/admin/rendimiento` | `Rendimiento` | p50/p95 de `ServidorMs` y `CargaMs` por ruta, con n, en los últimos 7 días contra los 7 previos (Δ% del p95). Resalta en rojo las rutas lentas según `monitoreo.umbrales`. Tiene filtro «solo lentas», búsqueda, «Recalcular» y enlace a Pulse. |
| `/admin/errores` | `Errores` | Errores agrupados por huella, filtrados por estado (por defecto abiertos = nuevo + visto) y origen, con búsqueda. Doble clic, Enter o «Ver detalle» abre el detalle. |
| `/admin/errores/{id}` | `ErrorDetalle` | Datos del error, los últimos 50 eventos (usuario, dispositivo, URL, método/status, versión del front, traza plegable) y el cambio de estado nuevo/visto/resuelto/ignorado con nota. |
| `/admin/accesos` | `Accesos` | Logins, fallidos, bloqueos, logouts (incluidos los remotos) y acciones de admin, con el nombre del admin que actuó. Filtros por tipo, fechas y búsqueda. |

**Piezas comunes:**
- `Concerns/SoloAdmin`: `bootSoloAdmin()` → `Gate::authorize('admin')`.
- `Concerns/FiltroFechas`.
- `Services/Monitoreo/PanelConsultas`: estados, percentiles y duración.
- `Services/Monitoreo/AuditoriaAdmin`.
- `resources/views/modulos/admin/panel.blade.php`: una sola vista host que monta el componente de cada `Route::view`.
- `_nav.blade.php`: las pestañas, más «Pulse» si está encendido.
- Todas las tablas usan `ConTabla` + `<x-tabla>` (con `PaginacionCompat`, apta para SQL Server 2008). Los botones son `x-navbar.button-*` y `x-ui.button`, y los avisos van por `dispatch('aviso')` → `notify`.
- Se borró el placeholder `modulos/admin/index.blade.php`.

## Garantías (con test)

- **Autorización (MON-21).**
  - Las 7 pantallas responden 200 a Sistemas, 403 a otra área y redirigen al invitado a `/login`.
  - Cada componente, montado directo con `Livewire::test` por un usuario de otra área, da 403.
  - Si el usuario cambia de área a media sesión, la siguiente acción (`cerrarSesion`) también da 403 y no se audita nada.
  - `/admin/errores/999999` y `/admin/errores/abc` dan 404.
  - `ErrorDetalle::$errorId` es `#[Locked]`.
- **En línea (MON-22).**
  - Los estados siguen el contrato §6 (ver Decisiones 1).
  - Las tarjetas y el filtro coinciden, y la búsqueda funciona por número de empleado.
  - El poll es de 10 s como mínimo.
  - La pantalla **no consulta `SYSMonVista`**: el test escucha todas las consultas.
- **Cierre remoto y auditoría (MON-28).**
  - Cerrar sesión deja la llave `mon:cerrar:{uuid}`, `CierreSolicitadoPor` y un registro `admin_accion` con `Motivo=cierre_remoto`, `ActorId`, `DispositivoId` y `UsuarioId`.
  - Renombrar valida la longitud (máximo 80), quita espacios y audita `renombrar_dispositivo: "anterior" → "nuevo"`.
  - Cambiar el estado de un error audita `error_estado: #id nuevo → resuelto`; editar solo la nota audita `error_nota: #id (estado)`.
- **Errores (MON-25).**
  - Los filtros funcionan.
  - El detalle muestra los eventos y la traza.
  - `resuelto` o `ignorado` llenan `ResueltoPor/En`; editar la nota de uno ya cerrado los conserva, y reabrirlo los limpia.
  - Un estado inválido o una nota de más de 500 caracteres se rechazan.
- **Rendimiento (MON-26).**
  - Percentiles exactos: 20 valores de 10 a 200 dan p50 = 100 y p95 = 190; `[1000, 2000, 3000]` da p50 = 2000 y p95 = 3000.
  - La comparación semanal da +100% y 0%.
  - Una ruta que solo tiene datos en la semana previa no aparece.
  - Una métrica fuera de la lista blanca lanza excepción.
- **Historial (MON-23/24/27).**
  - La duración de la sesión se calcula bien (1 h 05 min).
  - Una fecha inválida en la URL se ignora.
  - Navegación: sin filtro muestra el estado vacío; por dispositivo, agrupa por página (125 s + 60 s = «3 min · 2 vistas») y excluye otros días y otros dispositivos; por empleado, sale de las sesiones del usuario y excluye las vistas de otros usuarios en la misma tablet.

## Decisiones

1. **«Reciente» con la pestaña oculta.** El contrato dice en línea = `UltimaActividad` < 150 s y `Visible=1`. Con la pestaña oculta el latido llega cada 300 s (`latido_seg.oculta`), así que una tablet oculta aparecería como desconectada entre latidos. Por eso:
   - la ventana para `Visible=0` es 1.5 × 300 = 450 s;
   - en línea = visible, < 150 s e `InactivoSeg` ≤ 600;
   - inactivo = reciente pero oculta o con `InactivoSeg` > 600;
   - desconectado = el resto.

   Todo vive en `PanelConsultas::filtrarEstado()/estado()`.
2. **Percentiles sin `PERCENTILE_CONT`.** Producción es **SQL Server 2008 R2**, que no tiene `PERCENTILE_CONT` (llegó en 2012). Se usa el percentil de rango más cercano con `ROW_NUMBER() OVER (PARTITION BY Ruta ORDER BY ms)` + `COUNT(*) OVER`. Es el mismo SQL en 2008 R2 y en sqlite: no hay rama por driver ni fallback AVG/MAX. El resultado se guarda 5 minutos en cache (`mon:panel:rendimiento`, botón «Recalcular»).
3. **La navegación por usuario no escanea `SYSMonVista` por `UsuarioId`**, que no tiene índice. Los dispositivos del usuario ese día salen de `SYSMonSesion` (IX Usuario, Inicio) y luego se filtra con `IX(DispositivoId, Inicio)`.
4. **Sin Chart.js.** Las gráficas requerían tocar `resources/js`, que está prohibido en esta ola. Se usan barras CSS (tiempo por página) y tablas con Δ%. Queda como mejora para la fase 16/19.
5. **Resumen diario por Telegram (opcional): no se hizo.** Las alertas son por correo (11-03).
6. **Tamaño.** 13-01 suma unas 2 100 líneas: unas 1 500 sin contar los tests y 580 de tests, más que el tope de 1 500 del protocolo. No se partió porque el owner pidió una sola rama 14→13 con commits por paso: `eb36345` deuda phpstan, `9968a2d` SEC-02, `78428bc` Pulse, `f9af7a3` panel, `b849b63` fix de code-review.

## Evidencia

```
php artisan test tests/Feature/Monitoreo
  Tests:    98 passed (715 assertions)     # 61 previos + 11 Pulse + 26 del panel
php artisan test
  Tests:    1296 passed (19579 assertions)
vendor/bin/phpstan analyse --memory-limit=2G      [OK] No errors
npm run build                                     ✓ built
npm run ratchet                                   ratchet ok (ninguna subió: 0 <script>, 0 fetch, 0 onclick nuevos)
vendor/bin/pint --test <archivos PHP tocados>     pass
```

- **Skill `security-review`:** sin hallazgos.
  - `ordenPor`, que viene de la URL, se valida contra las columnas declaradas.
  - Las búsquedas van con bindings, y la métrica del SQL de percentiles está en lista blanca.
  - Todo se imprime con `{{ }}` o `@js`.
  - `SoloAdmin` se revisa en cada update.
- **Skill `code-review` (medium):** 1 hallazgo, ya corregido. Guardar la nota de un error ya resuelto sobrescribía `ResueltoPor/En`.
- **Skill `run`.** Se levantó la app real con `php -S`, `sqlsrv` apuntando a SQLite con datos sembrados (solo en el scratchpad; no se tocó `config/database.php`) y Chromium headless (Playwright):
  - Sistemas: 200 en `/admin`, `/admin/sesiones`, `/admin/navegacion`, `/admin/rendimiento`, `/admin/errores`, `/admin/errores/1`, `/admin/accesos` y `/admin/pulse`.
  - Tejido: 403 en `/admin` y en `/admin/pulse`.
  - Seleccionar una fila, pulsar «Cerrar sesión» y confirmar: el toast dice «se cerrará en su próxima actividad» y la fila muestra «cierre pendiente».
  - Capturas en `capturas/`: `en-linea.png`, `cerrar-hecho.png`, `sesiones.png`, `navegacion.png`, `rendimiento.png`, `errores.png`, `error-detalle.png`, `accesos.png`, `pulse.png`, `tejido-403.png`.
  - Los errores de consola que aparecieron no son del panel: `app-pwa.js:67` sale porque en headless se bloqueó el service worker, y hay un recurso externo que no carga sin red.

## Cómo desplegar

1. Lo de 11-01 (tablas `SYSMon*`) y lo de `../14-mon-pulse/14-01-SUMMARY.md` (Pulse o su fallback).
2. No hay migraciones ni `.sql` nuevos para el panel. Los índices del contrato (`IX_SYSMonVista_Ruta_Inicio` INCLUDE, `IX_SYSMonSesion_Usuario_Inicio`, `IX_SYSMonDispositivo_UltimaActividad`) son los que usan las consultas.
3. `php artisan optimize:clear && php artisan optimize` (rutas y vistas nuevas).
4. **Verificar en Laragon** con un usuario de Sistemas:
   - `/admin` debe mostrar su propia tablet en ≤ 15 s.
   - «Cerrar sesión» sobre otra tablet de prueba la saca en ≤ 60 s, en su siguiente latido.
   - Opcional: cronometrar `/admin/rendimiento` con 90 días de datos (objetivo < 200 ms; si no se cumple, el cache de 5 min lo amortigua y hay que revisar el plan de `IX_SYSMonVista_Ruta_Inicio`).

## Pendientes y notas

- **Enlace «Admin» en `user-modal`** (resto de MON-21): lo agrega la sesión `claude/12-mon-cliente` con `@can('admin')` → `route('admin.index')`. La ruta ya existe con ese nombre.
- Las fechas relativas («1 minute ago») salen en inglés porque `config/app.php` no tiene locale `es`. Es de UX-global (17-02).
- Sin HANDOFF: solo se tocaron archivos propios.
