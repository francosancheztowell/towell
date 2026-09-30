# 22-02s — Dependencias con advisories (SUMMARY)

**Rama:** `claude/22-cal-deps` (base `claude/friendly-hopper-506bg9`, mergeada al final; `origin/main` ya estaba contenido) · **ID:** CAL-01 (audit) · **Plan:** `22-02s-PLAN.md` (aprobado por el owner tal cual) · **Sin PR.**

## Resultado

| Audit | Antes | Después |
|---|---:|---:|
| `composer audit` — critical | 2 | **0** |
| high | 18 | **0** |
| medium | 27 | **0** |
| low | 8 | **0** |
| sin severidad (GHSA duplicado de laravel CRLF) | 1 | **0** |
| **total composer** | **56 en 16 paquetes** | **0** |
| `npm audit` — high (`nanoid`) | 1 | **0** |

**Sin riesgos residuales:** todos los advisories tenían arreglo sin subir mayores.

## Versiones

| Paquete | Antes | Después |
|---|---|---|
| `phpoffice/phpspreadsheet` | 1.30.2 | **1.30.7** (2 críticos: SSRF/RCE en `IOFactory::load` y su bypass) |
| `maatwebsite/excel` | 3.1.67 | **3.1.70** |
| `laravel/framework` | v12.53.0 | **v12.69.3** |
| `livewire/livewire` | v4.3.3 | v4.4.7 |
| `guzzlehttp/guzzle` | 7.10.0 | 7.15.5 |
| `guzzlehttp/psr7` | 2.8.0 | 2.13.1 |
| `league/commonmark` | 2.8.2 | 2.10.3 |
| `league/flysystem` | 3.32.0 | 3.36.0 |
| `dompdf/dompdf` | v3.1.5 | v3.1.6 |
| `symfony/{http-foundation,http-kernel,routing,yaml}` | v7.4.6 | v7.4.20 |
| `symfony/{mailer,mime}` | v7.4.6 | v7.4.19 |
| `symfony/polyfill-intl-idn` | v1.33.0 | v1.42.0 |
| `nanoid` (npm, dev) | 3.3.17 | 3.3.19 |

En total, el lock tiene 75 paquetes actualizados (los de la tabla más sus dependencias transitivas: carbon, monolog, otros symfony/*, polyfills…). **Ninguno sube de versión mayor.** Se quitó `thecodingmachine/safe`, porque `sabberworm/php-css-parser` 9.5 ya no lo pide.

## Decisiones

- **`config.platform.php = 8.2.0`** (lo único que cambia en `composer.json`).
  - La CLI de producción es **PHP 8.3.28**: lo dicen `scheduler.bat` y el runbook §8.
  - El piso del lock anterior era `^8.2`.
  - El PHP que usa Apache no está confirmado, así que se dejó el piso más bajo. Con 8.2.0 se resolvieron todos los advisories; no se perdió ningún arreglo.
- **`composer update -w` en vez de `-W`.** `-W` también arrastraba `mockery`/`phpunit` (requisitos raíz) y con ellos `hamcrest/hamcrest-php` 2 → **3**, una versión mayor que ningún advisory pide. `-w` actualiza solo los 16 paquetes listados y sus dependencias.
- **npm:** `npm audit fix` sin `--force`; `package.json` no se tocó. Se descartó la línea `"name": "towell"` que npm agrega por el nombre del directorio, para dejar el diff en solo nanoid.

## Pregunta para el owner

**¿Qué PHP corre Apache en Laragon?** Hay que confirmarlo con `php -v` y con el `phpinfo()` web (runbook `deploy.md` §9).
- Si CLI y web son ≥ 8.3, subir el piso a `8.3.0` es opcional.
- Si alguno fuera < 8.2, **no desplegar** esta rama.

## Verificación de Excel

- **Snapshots celda a celda de CAL** (`tests/Unit/Calidad/ExportsUrdidoEngomadoSnapshotTest.php`): verdes sin regenerar los fixtures.
- **Import real de .xlsx en cola** (`ReqModelosCodificadosImportTest`) y **tests de exports** existentes (Atadores, Alineación, ControlMerma, PromedioParos, OEE): verdes.
- **Nuevo `tests/Unit/Calidad/ExcelDependenciasTest.php`:**
  - **Import síncrono** (`Excel::import`, el camino de Calendarios/Velocidades/Telares/Eficiencias/Aplicaciones): lee un .xlsx con `ReqCalendarioTabImport`. Cubre `BeforeImport` truncate, fila vacía, fila incompleta y UTF-8.
  - **Export descargado:** `Excel::download` devuelve un `BinaryFileResponse`; se revisan `Content-Disposition` y el tipo `spreadsheetml`, y el archivo se lee de vuelta con `IOFactory`.
  - **Stream wrappers:** `IOFactory::load` rechaza `phar://`, `\x01phar://` y `zip://…phar://`. En 1.30.2 esa protección no existía (`Shared/File::prohibitWrappers` aparece en 1.30.4), así que este test habría fallado con la versión anterior.

### Probar a mano en producción después de desplegar

1. **Programa Tejido:** import de actualización y el import simple (`ReqProgramaTejido{Update,Simple}Import`), que son los que menos cobertura tienen (2–3 %).
2. **Codificación / modelos codificados:** import en cola y la barra de progreso (`CodificacionController::importProgress`).
3. **CatCodificados:** import (síncrono y en cola).
4. **Calendarios:** import de tabla y de líneas.
5. **Otros imports de Configuración:** Velocidades, Eficiencias, Telares, Aplicaciones.
6. **OEE Atadores:** carga de archivo (`OeeAtadoresFileService`, usa `IOFactory::load`).
7. **Exports:** reportes de Urdido (BPM, roturas, resumen semanal, panel km), Engomado (merma, BPM, resumen semanal), Tejido (marcas finales, cortes de eficiencia, inventario de telas, RPM, paros), Atadores, Mantenimiento, Alineación y Crudo.
8. **PDF (dompdf 3.1.6):** una pantalla que genere un PDF con imágenes.
9. **Livewire 4.4:** abrir el panel `/admin` y una pantalla Livewire de módulo.

## Evidencia

Todo corrió **después** del update y del merge de la rama base:

- `php artisan test`: 2285 passed (23 699 assertions) antes del test nuevo; corrida final con él: **2288 passed (23 709 assertions)**.
- `vendor/bin/phpstan analyse --memory-limit=2G`: `[OK] No errors`. **`phpstan-baseline.neon` no cambió.**
  - Sin baseline salen 3 139 errores contra 3 162 entradas.
  - La diferencia son entradas de `CalificarJuliosController` (traits: el efecto frío/caliente ya documentado en `phpstan.neon`) y dos de `CortesEficienciaController` (19-02). Ninguna viene del update, así que no se regeneró el baseline, que es un archivo caliente entre sesiones.
- `npm run typecheck && npm run test:js && npm run build`: verdes (295 tests JS).
- `npm run ratchet`: `ratchet ok (12 metricas, ninguna subio)`.
- `composer quality`: pint, phpstan, phpmd (`sin violaciones nuevas`) y ratchet verdes.
- `node scripts/calidad.mjs audit`: `composer.lock cambio sin advisories nuevos (0 preexistentes, 56 en la base)`.
- `composer audit`: `No security vulnerability advisories found.` · `npm audit`: `found 0 vulnerabilities`.
- `security-review`: sin hallazgos. Se revisó el origen de cada paquete del lock (mismo proveedor en GitHub; nanoid desde registry.npmjs.org) y que no hubiera scripts, plugins ni repositorios nuevos.

## Despliegue

Nada que migrar ni variables nuevas. Pasos en `docs/cerebro-towell/Runbooks/deploy.md` §9:

```bat
php artisan optimize:clear
git pull
composer install --no-dev -o
npm ci && npm run build
php artisan optimize
```

Livewire sirve su JS desde `vendor/` con un hash de versión en la URL, así que no hace falta publicar assets.

## Rollback

`deploy.md` §4 con el commit anterior a esta rama. Si solo hay que regresar las dependencias:

```bat
git checkout <commit-anterior> -- composer.lock package-lock.json
composer install --no-dev -o
npm ci && npm run build
php artisan optimize
```

Antes del siguiente `git pull`: `git checkout HEAD -- composer.lock package-lock.json`.

## Archivos

`composer.json` (solo `config.platform`), `composer.lock`, `package-lock.json`, `tests/Unit/Calidad/ExcelDependenciasTest.php`, `docs/cerebro-towell/Runbooks/deploy.md` (§9 nueva), `22-02s-PLAN.md`, este SUMMARY.

Sin HANDOFF: el update no rompió código de ningún módulo. Un pendiente menor para el integrador: agregar §9 a la línea de `deploy.md` en `docs/cerebro-towell/Runbooks/00-Indice-runbooks.md`, que no es de este track.
