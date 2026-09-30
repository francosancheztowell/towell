---
phase: 22-calidad
plan: "02s"
wave: 3
depends_on: [22-01]
autonomous: false
requirements: [CAL-01]
files_modified:
  - composer.json            # solo config.platform.php
  - composer.lock
  - package-lock.json
  - tests/Unit/Calidad/ExcelDependenciasTest.php
  - docs/cerebro-towell/Runbooks/deploy.md   # solo la sección de dependencias
  - .planning/phases/22-calidad/22-02s-PLAN.md
  - .planning/phases/22-calidad/22-02s-SUMMARY.md
must_haves:
  truths:
    - "Ningún paquete sube de versión mayor por una restricción nuestra; composer.json solo gana config.platform.php."
    - "El lock nuevo nunca exige un PHP mayor que el de producción (config.platform.php)."
    - "composer audit y npm audit quedan en 0 advisories, o cada uno que quede está documentado con su riesgo real."
    - "Los snapshots celda a celda de CAL y los tests de imports/exports pasan sin regenerar fixtures."
    - "phpstan-baseline.neon no sube."
  artifacts:
    - "composer.lock y package-lock.json actualizados."
    - "Test de ida y vuelta de Excel (import síncrono real de .xlsx + export descargado leído de vuelta)."
    - "22-02s-SUMMARY.md: audit antes/después por severidad, versiones, riesgos, despliegue y rollback."
---

<objective>
Bajar los advisories de seguridad de las dependencias (56 en composer, 1 en npm, 2026-09-30) sin
subir mayores ni tocar lógica de módulos, con el Excel (phpspreadsheet + maatwebsite) verificado
de punta a punta porque es donde están los críticos.
</objective>

<context>
Medido en la rama (base `claude/friendly-hopper-506bg9`, `composer audit --locked`):

| Severidad | Advisories |
|---|---:|
| critical | 2 (phpoffice/phpspreadsheet) |
| high | 18 |
| medium | 27 |
| low | 8 |
| sin severidad | 1 (duplicado GHSA de laravel/framework CRLF en regla `email`) |
| **total** | **56 en 16 paquetes** |

Paquetes: `dompdf/dompdf`, `guzzlehttp/guzzle`, `guzzlehttp/psr7`, `laravel/framework`,
`league/commonmark`, `league/flysystem`, `livewire/livewire`, `maatwebsite/excel`,
`phpoffice/phpspreadsheet`, `symfony/{http-foundation,http-kernel,mailer,mime,polyfill-intl-idn,routing,yaml}`.

npm: `nanoid` < 3.3.18 (alto), arreglo por `npm audit fix` sin `--force`.
</context>

<decisions>

**D1 — PHP de producción: `config.platform.php = 8.2.0`.**
Evidencia en el repo:
- `scheduler.bat` (versionado, corre en `C:\laragon\www\towell`) y el runbook §8 usan
  `C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe` → la CLI de producción es **8.3.28**.
- 18-03-SUMMARY lo cita como "la versión de `scheduler.bat` en producción".
- Piso del lock actual: el máximo `require php` de los paquetes instalados es `^8.2` (laravel/framework,
  brick/math, termwind…) → producción cumple **≥ 8.2**.
- Nadie confirmó qué PHP usa **Apache** de Laragon (puede tener otra versión seleccionada en el menú).

Se fija el piso más bajo que satisface el lock actual (8.2.0) porque (a) no hay confirmación del PHP
del servidor web, y (b) **no cuesta ningún arreglo**: el dry-run con 8.2.0 resuelve todos los
advisories. Efecto útil: impide que un update futuro traiga symfony 8 / paquetes `^8.4` que Laragon no
puede correr. Pregunta al owner en el SUMMARY: confirmar `php -v` en Laragon y en `phpinfo()` web; si
ambos son 8.3.x, subir el piso a `8.3.0` es opcional.

**D2 — `composer update -w` (no `-W`) con la lista exacta de paquetes con advisories.**
`-W` arrastra también las dependencias que son *requisito raíz* (`mockery/mockery`, `phpunit/phpunit`)
y con ellas `hamcrest/hamcrest-php` 2 → **3 (mayor)**, que ningún advisory pide. `-w` actualiza los
paquetes listados y sus dependencias transitivas, y deja quietos los requisitos raíz no listados.
Dry-run: 75 actualizaciones, 1 baja (`thecodingmachine/safe`, ya no lo pide `sabberworm/php-css-parser`),
ningún mayor. Versiones clave: phpspreadsheet 1.30.2 → 1.30.7, maatwebsite/excel 3.1.67 → 3.1.70,
laravel/framework 12.53.0 → 12.69.x, guzzle 7.10.0 → 7.15.x, psr7 → 2.13.x, commonmark → 2.10.x,
livewire 4.3.3 → 4.4.x, dompdf 3.1.5 → 3.1.6, symfony/* 7.4.x → último 7.4.

**D3 — npm:** `npm audit fix` sin `--force`; `package.json` no se toca (el arreglo es solo del lock).

**D4 — Si algo se rompe:** módulo con dueño activo (19-03 Atadores, 19-05 Programa Urd-Eng, 19-08
Mantenimiento) → HANDOFF, no se arregla aquí. Sin dueño activo → arreglo mínimo con test. Nunca se
desactiva un test.

**D5 — Verificación de Excel.** Ya existen: snapshots celda a celda (`tests/Unit/Calidad/ExportsUrdidoEngomadoSnapshotTest.php`),
import real de .xlsx en cola (`ReqModelosCodificadosImportTest`), y los tests de exports de Atadores,
Alineación, ControlMerma, PromedioParos. Se agrega `tests/Unit/Calidad/ExcelDependenciasTest.php`:
- import **síncrono** (`Excel::import`, el camino de Calendarios/Velocidades/Telares) de un .xlsx
  pequeño escrito con PhpSpreadsheet, con `ReqCalendarioTabImport` sobre sqlite;
- export **descargado** (`Excel::download` → `BinaryFileResponse`) con cabeceras de descarga y el
  archivo leído de vuelta con `IOFactory`;
- el arreglo del crítico en la práctica: `IOFactory::load` de un nombre `http://…` / `phar://…` falla
  sin tocar la red (sanidad del parche, no del código de Towell).

</decisions>

<tasks>

1. Audit "antes" (composer + npm) guardado para el SUMMARY.
2. `composer config platform.php 8.2.0` + `composer update -w <paquetes con advisories>`; revisar que no
   haya mayores (`composer show --locked` antes/después); `composer audit`.
3. `npm audit fix` sin `--force`; `npm audit`.
4. Test de Excel (D5); correr suite completa, phpstan (baseline no sube), JS, build, ratchet,
   `composer quality`.
5. Runbook `deploy.md`: sección "Dependencias" (cómo confirmar PHP, `composer install --no-dev -o`,
   rollback del lock).
6. `git merge origin/main` (si existe) y repetir todo; `security-review`; SUMMARY (+ HANDOFF si hace falta); push.

</tasks>

<verify>
composer audit = 0 (o residuales documentados); npm audit = 0; php artisan test verde sin regenerar
fixtures; phpstan OK sin subir el baseline; composer.json solo con `config.platform`.
</verify>
