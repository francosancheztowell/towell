---
phase: 10-base
plan: "01"
branch: claude/10-base
base: claude/friendly-hopper-506bg9 (mergeada de nuevo tras integrar 11 y PT-01)
requirements: [BASE-01, BASE-02, BASE-03, BASE-04, BASE-05, BASE-06, BASE-07, BASE-08, BASE-09, BASE-10, BASE-11, BASE-12, SEC-01, SEC-02]
status: completo (BASE-07 y BASE-09 requieren acción del owner; tel-bpm/log-debug va en HANDOFF)
---

# 10-01 — Base y guardarraíles: resumen

Diff sin baseline de phpstan ni lockfile: 40 archivos, +972 / −3 184.

## Qué se hizo, por ID

| ID | Resultado |
|---|---|
| BASE-12 | Hook `SessionStart` (`.claude/settings.json` → `scripts/session-start.sh`). Solo corre en la nube: crea `bootstrap/cache` y `storage/framework/*`, `composer install`, `npm ci`, `.env` + key y `npm run build`. Idempotente. Probado en un clon limpio: 1 min 30 s. |
| BASE-01 | La suite pasa en sqlite sin saltar, desactivar ni borrar ningún test. En CI hay un job `php`. |
| BASE-02 | `phpstan.neon` con larastan nivel 5 sobre `app/`. `phpstan-baseline.neon` guarda la deuda actual (3 419 errores). Da el mismo resultado con caché fría y caliente. |
| BASE-03 | `scripts/ratchet.mjs` (sin dependencias) + `scripts/ratchet-baseline.json` + `npm run ratchet` + un paso en el job JS. 10 métricas, tests en `tests/Js/ratchet.test.mjs`. Probado: subir un `fetch(` a mano hace fallar el ratchet con exit 1. |
| BASE-04 | `10-BASELINE.md`: bundle de Vite y JS inline por vista medidos aquí, con los comandos para repetirlo. Runbook de TTFB y queries por pantalla con `db:profile`, con la tabla vacía para Laragon. |
| BASE-05/11 | Borrado lo muerto (evidencia abajo). `CLAUDE.md` actualizado. `tel-bpm/log-debug` **no** se borró (ver HANDOFF). |
| BASE-06 | Página QR con el paquete npm `qrcode` (`resources/js/usuarios/qr.ts`), cargado con `@vite`, sin `onclick` inline. Test de la página y smoke test en Chromium: canvas de 256×256, descarga del PNG, 0 errores. |
| BASE-07 | Ventas: la conexión **no** se restauró en `config/database.php` porque el protocolo prohíbe tocarlo. Pasos para el owner abajo; llaves nuevas en `.env.example`. |
| BASE-08 | `queue.failed.driver` → `database-uuids` (la migración de `failed_jobs` ya tiene `uuid`). El stack de log por defecto pasa a `daily` con 30 días. En `.env.example` quedó un solo bloque MAIL. |
| BASE-09 | No se hizo migración. Queda como pregunta al owner (abajo). |
| BASE-10 | `tsconfig.json`: `include resources/js/**/*.ts`, `exclude resources/js/tejido/**` (FE-06). Se tipan 32 archivos y `npm run typecheck` sale en verde. |
| SEC-01 | Sin cambios de código: fuera y dentro de PT, ningún SQL interpola input de request ni de BD. La nueva métrica del ratchet `SQL crudo con $interpolado` (8 hoy) no deja entrar uno nuevo sin revisión. |
| SEC-02 | Solo recomendación (abajo); `bootstrap/app.php` es de MON-A. |

## Conteos antes / después

**Tests PHP** (`php artisan test`, sqlite):

| | Fallidos | Omitidos | OK |
|---|---:|---:|---:|
| Antes (tras `npm run build`; sin build eran 20 fallos por "Vite manifest not found") | 6 | 49 | 1 146 |
| Después, sobre la base original | 0 | 0 | 1 154 |
| Después, con la base mergeada (11 + PT-01) | 0 | 0 | **1 253** |

Los 6 fallos:
- `NuevoRequerimientoLivewireTest` (3): el modal lee `InventTable/InventSum/InventDim/InventColor` de `sqlsrv_ti`. Ahora esas tablas se crean en sqlite, y el test también comprueba que el catálogo de calibres filtra por `HILO DIREC`.
- `ProgramBoardLivewireTest` (2): desde 434fee0 el diálogo de prioridad guarda la lista arrastrada (`saveOrderedPriorities` + `saveUrdidoSalons`) y ya no hace swap contra `priorityTargetId`. El fake del test no tenía esos métodos y caía al servicio real, que exige permiso. Los dos tests ahora cubren el flujo actual: guardar el orden arrastrado, y no guardar si no hay filas.
- `ProgramBoardStructureTest` (1): 434fee0 volvió a meter un `<script>` inline de 58 líneas en `livewire/urd-eng/program-board.blade.php`. Se movió a `resources/js/urd-eng/priority-sort.ts` sin cambiar el comportamiento. **Esa vista está fuera de la propiedad de esta fase; el owner aprobó la excepción en la sesión.**

Los 49 omitidos (`AuditoriaProgramaTejido*`, `ProgramaTejidoIndexSmokeTest`) prueban triggers y datos que solo existen en SQL Server. Quedan en `#[Group('sqlserver')]`, excluido en `phpunit.xml`, y conservan su guarda. `tests/README-sqlserver.md` explica cómo correrlos en Laragon (`--group=sqlserver`).

**JS** (`npm run test:js`): 26 → 37 tests (ratchet + PWA).

**phpstan:** baseline de 3 412 → 3 410 (tras los borrados) → 3 419 al mergear la base. Los 10 errores que trae la base van en HANDOFF.

**Ratchet** (baseline inicial → final):

| Métrica | Inicial | Final |
|---|---:|---:|
| `fetch(` | 302 | 300 |
| `Swal.fire` | 827 | 818 |
| `toastr.` | 29 | 29 |
| `onclick=` | 438 | 413 |
| `innerHTML =` | 407 | 394 |
| `X-CSRF-TOKEN` | 195 | 193 |
| `bg-opacity-` | 32 | 28 |
| `<script>` inline en blade | 173 | 170 |
| SQL crudo con `$interpolado` | — | 8 |
| `getMessage()` en `response()->json` | 284 | 285 (+1 de `ErrorRecorder`, que viene de la base) |

## Borrados, con evidencia

Grep en `app resources routes tests public config bootstrap database scripts`. Sin contar docs/planeación, que solo los mencionan como historia.

| Borrado | Evidencia |
|---|---|
| `resources/views/modulos/urdido/programar-urdido.blade.php` | Ningún `view('modulos.urdido.programar-urdido')`. Los controllers devuelven `*-livewire` y `/legacy` hace 301. Lo único que lo nombraba era `assertFileExists` en `ProgramBoardStructureTest`, que ahora usa `assertFileDoesNotExist`. |
| `resources/views/modulos/engomado/programar-engomado.blade.php` | Igual que la anterior. |
| `app/Services/ProgramaUrdEng/OrdenKarlMayerService.php` | `grep OrdenKarlMayerService` → solo su propia definición. `CrearOrdenKarlMayerController` mantiene su propia lógica: el servicio nunca se conectó. |
| Ruta `/test-404` + `app/Http/Controllers/SystemController.php` | La clase solo tenía `test404()` y solo la usaba esa ruta. `route:list` ya no la muestra. |
| `public/audio/click-sound.js` (y la carpeta `audio/`) | 0 referencias en código ni en `sw.js`. |
| `public/pwa-check.mjs` | 0 referencias. Su chequeo **se conservó** como `tests/Js/pwa-installable.test.mjs`, que ahora corre en CI. |
| `public/towell-ca.crt`, `public/towell.csr` | 0 referencias. Es el certificado autofirmado `CN=192.168.2.15, O=Towell, OU=Sistemas` (vigente hasta 2027-09-13) y su CSR. **No había llave privada**: el certificado y la CSR son material público, así que **no hace falta rotar**. Si las tablets instalaban el certificado bajándolo de `http://192.168.2.15/towell-ca.crt`, hay que distribuirlo por otro medio (o volver a servirlo a propósito, fuera de `public/` del app). |

## Decisiones pendientes del owner

1. **BASE-07, conexión de Ventas.** `app/Models/Ventas/*` usa `sqlsrv_Reportes_Towell`. f8d4a80 la agregó y 11740c7 la borró (con "0 referencias"); después 3c883e5 agregó los modelos. Como `config/database.php` está en skip-worktree, en Laragon:
   ```powershell
   .\scripts\db-config.ps1 unlock
   # agregar dentro de 'connections' de config/database.php:
   #   'sqlsrv_Reportes_Towell' => [
   #       'driver' => 'sqlsrv',
   #       'host' => env('DB_HOST_REPORTES_TOWELL'),
   #       'port' => env('DB_PORT_REPORTES_TOWELL', '1433'),
   #       'database' => env('DB_DATABASE_REPORTES_TOWELL'),
   #       'username' => env('DB_USERNAME_REPORTES_TOWELL'),
   #       'password' => env('DB_PASSWORD_REPORTES_TOWELL'),
   #       'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true,
   #       'trust_server_certificate' => true,
   #   ],
   .\scripts\db-config.ps1 save
   ```
   Después, las 5 llaves `DB_*_REPORTES_TOWELL` van en el `.env` de producción (están comentadas en `.env.example`) y se corre `php artisan config:clear`.
2. **BASE-09, `CACHE_STORE` / `SESSION_DRIVER` reales de producción.** El default del repo es `CACHE_STORE=database`, pero **no hay migración de la tabla `cache`**. Si producción usa `database`, correr `php artisan make:cache-table && php artisan migrate`. Si usa `redis` (predis está instalado), no hace falta nada. `SESSION_DRIVER=database` ya tiene su migración. Hace falta confirmar los valores del `.env` de producción.
3. **Log diario.** Si el `.env` de producción fija `LOG_STACK=single`, el nuevo default no aplica: cambiarlo a `LOG_STACK=daily` y `LOG_DAILY_DAYS=30`. `QUEUE_FAILED_DRIVER` no debería estar en `null`.
4. **SEC-02, `trustProxies`.** `bootstrap/app.php:31` tiene `trustProxies(at: '*')`: cualquier cliente puede falsificar su IP con `X-Forwarded-For`, y eso contamina IPs de auditoría/monitoreo y rate limits por IP. Recomendación: si Laragon/Apache recibe tráfico directo (sin proxy delante), **quitar** `trustProxies`. Si hay un proxy real, `trustProxies(at: ['<IP del proxy>'])`. Lo aplica MON-A (dueño de `bootstrap/app.php`) con la respuesta del owner.
5. **CI obligatorio.** Marcar como *required* en la protección de `main` los checks `checks` y `php` del workflow *Frontend checks*. El workflow también corre en push a `claude/**` (las sesiones no abren PR); si hay PR, correrá dos veces.
6. **Certificado de tablets:** ver la tabla de borrados.

## Cómo desplegar

- No hay migraciones ni `.sql`.
- `.env` de producción: ver los puntos 1–3.
- `npm ci && npm run build` (nueva dependencia `qrcode` y nuevo input de Vite `resources/js/usuarios/qr.ts`).
- `php artisan optimize:clear && php artisan optimize` (ruta `/test-404` quitada, config de queue/logging).

## Evidencia (última corrida, rama con la base mergeada)

```
php artisan test            → Tests: 1253 passed (19256 assertions)
npm run typecheck           → tsc --noEmit (sin errores)
npm run test:js             → # pass 37 / # fail 0
npm run build               → ✓ built
npm run ratchet             → ratchet ok (10 metricas, ninguna subio)
vendor/bin/phpstan analyse  → [OK] No errors (en frío y en caliente)
vendor/bin/pint --test <PHP cambiados> → pass
```

- **code-review:** 1 hallazgo, corregido. El `|| true` del paso Pint ocultaba un `git diff` fallido tras un force-push; además ahora se revisan los archivos renombrados.
- **security-review:** 0 hallazgos con confianza ≥ 8. El QR ahora pasa los datos por atributos `data-*` escapados en vez de meterlos en literales JS.

## Notas para el integrador

- El orden de la Ola 0 decía 10-base primero, pero 11 y PT-01 se integraron antes. Esta rama ya tiene mergeada la base actual (sin conflictos) y sus baselines incluyen ese código, así que integrarla no debería poner el CI en rojo.
- Composer en la nube: `phpstan/phpstan` solo publica dist en api.github.com, que el proxy responde con 403. El hook lo resuelve armando el zip desde git en la caché de composer.
