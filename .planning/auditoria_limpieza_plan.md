# Plan de limpieza — auditoría 2026-09-22 (ordenado por riesgo)

Fuente: auditoría de 100 agentes, 68 hallazgos verificados. ~13-14k líneas netas.

Dos ejes de riesgo, no uno:
- **Riesgo de código** — probabilidad de romper algo al aplicar el cambio.
- **Riesgo de negocio** — qué ve o deja de ver la planta, aunque el código sea trivial.

Reglas que atraviesan todo:
- **Borrar antes de consolidar.** Si no, refactorizas código muerto.
- Todo borrado que `CLAUDE.md` o `docs/` documenten viaja con su edición de doc en el **mismo commit**.
- Cada tanda cierra con `php artisan test` y arranque de `composer dev`.

---

# R0 — No puede romper nada

Cero referencias verificadas, o cambios que no alteran comportamiento. Se pueden hacer hoy, en cualquier orden.

## R0.1 — Borrado puro (~4,500 L)

Un solo commit: "borrar código sin referencias".

| Qué | L | Por qué es R0 |
|---|---|---|
| `app/Observers/SimulacionProgramaTejidoObserver.php` | 550 | No registrado; type-hintea `App\Models\Simulaciones\*`, que no existe. Si algo lo instanciara, hoy ya reventaría |
| `app/Livewire/ProgramaUrdEng/CrearKarlMayer.php` + su vista | 547 | Ninguna pantalla lo monta; su TS de apoyo ya se borró |
| 11 clases sin referencia (`ImportDataProcessor`, `PronosticosService`, `ReqCalendarioImport`, `ReqCalendarioLine*`, `ReporteResumenEngomadoExport`, `AtaMontadoTelasSheet`…) | 2,045 | Grep por FQCN en app/ resources/ routes/ config/ database/ tests/ = 0 |
| 7 modelos huérfanos + `database/factories/UserFactory.php` | 292 | Nadie llama `::factory()` y `User` ni usa `HasFactory` |
| `app/Traits/HasUserPermissions.php` | 115 | Cero clases lo usan; solo delega en `userPermissions()` |
| `app/Http/Middleware/ForceHttps.php` | 47 | Append comentado en `bootstrap/app.php:25` **y** su `config/force_https.php` no existe → no-op aunque se activara |
| Scopes/helpers/relaciones sin callers de `SYSRoles.php` | 139 | — |
| 10 scopes huérfanos + `scopeConAcceso` (SYSRoles:247) | 52 | — |
| `app/Mcp/` + `routes/ai.php` + `laravel/mcp` | 32 | Stub vacío `WeatherServer` + ruta comentada a clase inexistente |
| Conexión `sqlsrv_Reportes_Towell` (`config/database.php:152-163`) | 12 | 5 env vars que nadie define, cero referencias |
| `require_once` en `AppServiceProvider:89` | 1 | Composer ya lo carga vía `autoload.files` |
| `laravel/boost` → `require-dev` | — | Tooling de dev registrando Provider en producción |

**Dejar `app/Models/Sistema/User.php`**: lo usa `tests/Concerns/UsesSqlsrvSqlite`.
**Antes de SYSRoles**: `grep -rn '\->moduloPadre'` (el grep suelto da falsos positivos).
**Mismo commit**: limpiar `CLAUDE.md` (ImportDataProcessor, PronosticosService, HasUserPermissions, ForceHttps)
y `docs/documentacion-modulos/12-*` y `13-*`.

Cierre: `composer dump-autoload && php artisan route:list && php artisan test`.

## R0.2 — Ruido de repo (0 L, ~90 MB)

`tmp_promedio_debug.xlsx`, 3 plantillas `.xlsx` huérfanas, los `.pyc`.
`.gitignore`: `storage/debugbar/` (66 MB), `storage/logs/*.log` (21 MB), `scripts/__pycache__/`, `*.pyc`.
`config/logging.php` → `daily`, 14 días.
`axios` de `devDependencies` a `dependencies` — **es runtime** de `resources/js/utils/http.js`.

## R0.3 — Peso del bundle (0 L, ~500 KB por carga)

No borra líneas y no cambia comportamiento; es la mejora medible para las tablets de planta.

- Chart.js fuera del chunk global (`bootstrap.js:69`): `await import()` en los 4 reportes que dibujan.
  Hoy 205 KB se descargan en las 107 vistas de `layouts.app`.
- Font Awesome: fuera `brands` (110 KB) y `regular` (19 KB) — 4 iconos en total → SVG inline.
  Dejar comentario en `fontawesome-display.css`.
- jQuery + Select2 (~150 KB + shim de 37 L en `bootstrap.js:16-52`) solo en las 5 entradas que llaman
  `.select2()`. `$.ajax` = 0 usos, `$(document).ready` = 0. Cargarlos localmente primero; `<select>`
  nativo solo donde la lista sea corta.

Medir con `npm run build` antes y después.

---

# R1 — Solo rompe si un grep mintió

Verificación barata y nombrada antes de cada corte. Si la verificación no se hace, el ítem no se toca.

## R1.1 — Rutas muertas (~560 L)

| Orden | Qué | Verificación previa |
|---|---|---|
| 1 | 6 rutas de `editar-ordenes-programadas` (`urdido.php:72-75`, `engomado.php:76-77`) | Ninguna: **hoy ya devuelven 500** (`BadMethodCallException`). Conservar el nombre `{modulo}.editar.ordenes` |
| 2 | 7 endpoints de catálogo PT × 2 prefijos, 195 L (`planeacion.php:324-343`, `403-424`) | `grep -rn "flogs-by\|tamano-clave"` por si un Blade concatena la URL. **`salon-options` NO se toca**: su método vive vía `/salon-tejido-options` y hay test |
| 3 | Inventario Trama: 19 rutas AJAX + 11 métodos, 185 L | Conservar `index()` y la ruta `tejido.inventario.trama.nuevo.requerimiento` (la usa `ConsultarRequerimiento`). Quitar `actualizarCantidadUrl`/`guardarUrl` del VM (`NuevoRequerimientoService:71-72`) |
| 4 | `ModulosController` 5 métodos + `/modulos-sin-auth`, 174 L | Borrar en el mismo commit los 5 casos del data provider de `PublicSensitiveRoutesAuthTest` |
| 5 | `/test-404` de `routes/public.php` | Ninguna |

Deploy con cache-bust de assets: una tablet con JS viejo pegándole a `/modulo-nuevo-requerimiento/guardar` daría 404.

## R1.2 — OeeAtadoresFileService (~2,602 L) — **bloqueado**

Verificación que lo habilita: **en 192.168.2.15, ¿la tarea programada / `scheduler.bat` corre `scripts/oee_export.py`?**

Si sí: dejar `verificarSemanasConDatos()` + sus 13 helpers (~328 L), borrar los 53 métodos privados
inalcanzables, `actualizarArchivo()`, los tests que lo sostienen (mismo commit, o no compila) y
`config/oee.php:14 'export_driver'` (no se lee en ningún lado).

Si no: este ítem no existe.

## R1.3 — FormRequests Redbooth (120 L)

Los 4 `Api\Redbooth\External*` extienden a los de `Integraciones` sobreescribiendo `authorize()`.
Reglas idénticas; la superficie está viva (`tests/Feature/ExternalRedboothApiTest`), así que se fusiona, no se borra.

---

# R2 — Cambia comportamiento a propósito

Cada uno necesita una comparación antes/después o un test. Van de uno en uno, no en tanda.

## R2.1 — Migraciones + índices (mejor ms/riesgo del lote)

Causa raíz única de tres hallazgos. **En este orden**:

1. Borrar `database/migrations/2024_01_01_000001_add_performance_indexes.php` — inejecutable
   (`ONLINE=ON` en SQL Server 2008 R2 Standard + tabla y columnas inexistentes). Bloquea las 32 pendientes.
   Los índices que importan ya existen creados a mano.
2. Correr `2026_07_03_123445_dedupe_and_constrain_tej_eficiencia_line`.
   ⚠ **Fusiona y BORRA filas** (190 grupos duplicados). Backup de `TejEficienciaLine` antes.
   El índice único falla si no se deduplica primero.
3. Índice de trabajo `(NoTelarId, Date DESC, Turno DESC)` — hoy es un HEAP de 18,460 filas sin un solo índice.
4. Recién ahí, `getDatosTelares()` (`CortesEficienciaController.php:133`): 1+N → `ROW_NUMBER() OVER (PARTITION BY NoTelarId …)`.
   ⚠ Desempate no trivial: Horario 3 > 2 > 1, primer valor **no cero** hasta 20 registros atrás, por separado
   para RPM y Eficiencia. Diffear salida vieja vs nueva antes de mergear.

Verificación previa: `SELECT * FROM migrations` en producción, ¿coincide con las 15/51 locales?

## R2.2 — Bug del flog vigente (24 L) — **es un bug, no un refactor**

`CodificacionController.php:1415` ordena `orderByDesc('ft.IDFLOG')` (alfabético del string) y devuelve
**CE-99 en vez de CE-100**. Las 3 copias van a `LiberarFlogSugeridoService`, que ya tiene
`ESTADOS_VIGENTES` y el criterio correcto de "el más reciente".

Si ese dato se imprime o se libera, **este ítem sube al principio de todo el plan**, por encima de R0.
Confirmar con planeación.

## R2.3 — Capa de permisos, una sola pasada (~124 L + 390 queries)

- `ModulosController:544-563`: propagar permisos = 197 queries (1 + 2×98 usuarios) → `SYSUsuariosRoles::upsert()`.
  Requiere índice único `(idusuario, idrol)`; si no existe, `whereIn` + `insert` masivo.
- `limpiarCacheTodosUsuarios`: ~196 queries más en alta, edición y borrado.
- Podar `UsuarioRepository`: `getAll()` reimplementa `paginate()` a mano; `getByArea()` y
  `PermissionService::getPermisosUsuario()` sin callers (105 L).

(`HasUserPermissions` ya se fue en R0.1. Tocar esta capa **una vez**, no tres.)

## R2.4 — Reportes Engomado/Urdido (~757 L). Orden obligatorio

1. Borrar `buildReporteResumenData` (151, muerto). **Primero**, o el N+1 de la copia de Engomado viaja al trait.
2. Extraer helpers BPM + resumen semanal + `parseReportDate` (161) a `BpmReportService($tablaCabecera, $tablaLineas)`.
   ⚠ Divergencia ya presente: `NomEmplAutoriza` (Engomado) vs `NombreEmplAutoriza` (Urdido).
3. Fusionar los 2 pares de Export (445). Difieren en 3 renglones cada par, pero pueden haber divergido
   sin querer en un ancho de columna: imprimir un reporte de cada uno y comparar antes.

## R2.5 — Cortes mecánicos (~253 L)

| Qué | L | El detalle que lo saca de R1 |
|---|---|---|
| `SecuenciaCorteEficiencia` / `SecuenciaMarcasFinales` → abstracto + 2 hijos | 144 | De paso, envolver `updateOrden()` (UPDATE por fila en foreach) en `DB::transaction` |
| `SSYSFoliosSecuencia::nextFolio*` → un `nextFolioBy(array $where, int $pad)` | 55 | **Folios = identificador de negocio.** Un error produce folios duplicados o saltados en producción. Test antes |
| Cascada de reservas (`InventarioTelaresController:266` y `:498`) | 50 | El de `:498` **borra**. Reordenar la cascada al unificar borra reservas de otro registro |
| `getFlogsByTamanoClave` → `whereIn` | 4 | Sobre **pares** `(itemId, inventSizeId)`. Plano por columna = producto cartesiano |

---

# R3 — No ahora

No es que sean malos hallazgos; es que el riesgo supera al ahorro con el árbol como está hoy.

| Qué | L | Por qué espera |
|---|---|---|
| 214 `catch`→render global | 1,200 | Cambia el contrato de 214 endpoints (27 con `rollBack`, 71 con status ≠500, ramas por `DomainException`) y **no hay tests de API**. Módulo por módulo, o nunca |
| fetch→`window.http` | 950 | `http.js` **lanza** en !2xx donde `fetch` resuelve: todo `if (!r.ok)` y todo manejo de 422 hay que reescribirlo. Solo dentro de la migración a Livewire ya decidida |
| 48,828 L de JS inline en 135 Blade | 0 | Relocalización, no borrado. Los scripts llevan `{{ route() }}` y `@json()` dentro del JS |
| Captura de producción Engomado/Urdido | 600 | Los 2 Blade más grandes del repo, y `calcularNeto` ya divergió (engomado topa por `MAX_KG_BRUTO` y reescribe; urdido topa por `MAX_KG_NETO` y marca error). Romper el piso cuesta más que 600 L |
| `ReqProgramaTejidoSimple/UpdateImport` | 390 | 4 divergencias reales de comportamiento (`parseFloat`/`parseDate` con librerías distintas). Es cambio funcional disfrazado de refactor |
| Tests-policía | 914 | **Arreglar primero las 3 fallas que son bugs reales.** Las 2 de `EditarOrdenPermisosTest` dicen que el control de permisos de edición de órdenes no está bloqueando |
| Modelos duplicados sobre `SYSUsuario` / `SysDepartamentos` | 82 | PKs incompatibles (`id` int vs `Depto` string). Hay que elegir canónico y migrar llamadores |
| `predis/predis` | 2 | El hallazgo leyó el `.env` **local**. Verificar `CACHE_STORE`/`QUEUE_CONNECTION`/`SESSION_DRIVER` en 192.168.2.15 |
| 56 rutas Muestras ↔ PT | 65 | Ratio malo: el `foreach` ahorra poco y el monkey-patch de `window.fetch` se queda (y se romperá al migrar a `window.http`, que no pasa por `window.fetch`) |

---

# RN — Riesgo de negocio, no de código

El código es trivial de tocar; lo que cambia es lo que la planta ve. **Requieren tu OK, no un PR.**

| Qué | Riesgo código | Riesgo negocio |
|---|---|---|
| `TiemposPreparacionMock` (301 L) en la ruta **viva** `/producto-terminado/tiempos` | cero | **Alto: alguien puede estar decidiendo con números inventados hoy.** Decidir: repositorio real, o quitar la ruta del menú |
| 3 filas de `SYSRoles` (idrol 18, 19, 70) apuntan a URLs inexistentes | cero (es data) | Tarjeta del menú da 404 a 23/18/14 usuarios. ¿Módulos cancelados o en obra? |
| `device_helpers.php` (625 L de parser de User-Agent) | cero | El modal del navbar deja de decir "Galaxy Tab S9 FE" y dice "Android". Cosmético, pero es algo que el usuario ve |
| 27 MB de imágenes "sin usar" | **desconocido** | **Falso positivo probable**: las fotos se sirven con `asset('images/fotos_modulos/'.$modulo->imagen)`, que ningún grep de literales ve. Congelado hasta resolverlo |

---

# Secuencia recomendada

```
hoy        R0.1  R0.2  R0.3          ~4,500 L, 90 MB, 500 KB/carga — sin riesgo
esta sem.  R1.1  R1.3                ~680 L — con sus greps de verificación
           [verificar premisas en 192.168.2.15]
luego      R1.2                      ~2,602 L si Python está confirmado
           R2.1                      el mejor ms/riesgo; backup antes del dedupe
           R2.2                      antes que todo si el flog se imprime
           R2.3  R2.4  R2.5          uno por uno, con diff antes/después
parar      R3 y RN no se hacen por inercia
```

## Sin auditar todavía

`Crudo/` (~6,600 LOC con Contracts + DTOs + Enums + Repository + ValueObjects para **un** módulo),
`mecanicos/` (~4,200), `Mantenimiento/` (~2,300), `Trazabilidad` (~2,600), Atadores no-OEE (~7,600),
`app/Jobs/`, `app/Console/Commands/`, `app/Support/`. Nadie cruzó los 145 gates `module.permission`
contra `SYSRoles` (que tiene 5 nombres duplicados) ni contra los controladores sin gate.

Segunda pasada cuando el árbol esté limpio, no antes.
