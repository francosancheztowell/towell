# Auditoría de calidad de código — 2026-09-29

Primera pasada con herramientas de análisis sobre `app/` (487 archivos, 114 306 líneas).
Complementa (no reemplaza) phpstan nivel 5 + baseline, Pint y el ratchet de frontend.

## 1. Lo que se hizo en la sesión

| Cambio | Dónde | Estado |
|---|---|---|
| PHP local 8.2.12 → **8.3.35** (prod ya estaba en 8.3.28, CI en 8.4) | `C:\xampp\php` (respaldo en `C:\xampp\php82`) | Hecho, fuera del repo |
| Drivers SQL Server 5.13.3 para 8.3 TS x64 | `C:\xampp\php\ext` | Hecho |
| Apache carga `libssh2`/`nghttp2` de PHP (curl no cargaba) | `C:\xampp\apache\conf\extra\httpd-xampp.conf` | Hecho |
| pcov 1.0.12, **apagado por defecto** (`pcov.enabled=0`) | `C:\xampp\php\php.ini` | Hecho |
| `composer audit`: 56 avisos en 16 paquetes → **0** (solo minors/patches) | `composer.lock` | Hecho, tests + phpstan verdes |
| Dev: `laravel/doctor`, `nunomaduro/phpinsights` 2.14.2, `phpmd/phpmd` 2.15, `systemsdk/phpcpd` 8.0, `infection/infection` 0.35 | `composer.json` | Hecho, sin commit |
| Config de phpinsights (`threads: 4`; la autodetección usa `wmic`, que Win 11 no trae) | `phpinsights.php` | Hecho |
| CRLF sueltos en el `$signature` de 2 comandos (tumbaban phpinsights) | `EnviarReporteCrudoCommand`, `OptimizeModuleImagesCommand` | Hecho, sin diff en git |

**Incidente:** `php artisan doctor` ofreció correr `migrate` y se aceptó contra `ProdTowel` (192.168.2.28).
Falló en la primera migración (`ONLINE = ON` requiere Enterprise; prod es Standard). Pendiente confirmar en SSMS
que no quedó ningún índice creado hoy. **No volver a aceptar el migrate de doctor mientras `.env` apunte a prod.**

## 2. Qué arrojó cada herramienta

### laravel/doctor — entorno, no código
24 chequeos de configuración/conexiones. Útil: detectó las 56 vulnerabilidades y 37 migraciones pendientes.
Falso positivo: "Database connects" (usa un atributo PDO que `sqlsrv` no soporta; la app conecta).

### PHP Insights — calificación general (reglas por defecto, sin afinar)

| Área | Score | Hallazgos | Ruido principal |
|---|---|---|---|
| Code | 55.7 | 5 209 | ~2 700 type hints, 326 `strict_types`, 521 `empty()` |
| Complexity | 46.9 | 773 | umbral de 5 es irreal para controladores |
| Architecture | 52.9 | 1 284 | 354 "clase no final" |
| Style | 60.2 | 11 891 | 10 899 largo de línea (Pint no lo aplica) |
| Security | — | 0 | |

El número subestima el código: ~85 % es ruido de estilo. Lo real está en complejidad y largo de funciones.

### PHPMD — 4 831 avisos
~3 500 son ruido para Laravel (`StaticAccess` 2 684 = facades, `ShortVariable`, `LongVariable`, `ElseExpression`).
Verificado a mano:

| Regla | # | Veredicto |
|---|---|---|
| UnusedPrivateMethod | 12 | **Confirmado.** 0 referencias, ni como string. ~500 líneas muertas, casi todo en `OeeAtadoresFileService` (`syncSemanaSheets` 125 líneas, `rebuild*Sheet`, `snapshot/reapplySectionStyles`…) |
| UnusedLocalVariable | 60 | Probable; revisar uno por uno (9 en `CatalagoVelocidadController`) |
| UnusedFormalParameter | 55 | Mixto: muchos los impone una interfaz de Laravel/Excel |
| EmptyCatchBlock | 29 | **Parcial.** Los de `CalendarioController` son `rollBack()` dentro de un catch y `disableQueryLog()`: deliberados. Revisar el resto |
| ErrorControlOperator (`@`) | 30 | **Mayormente falso positivo**: `@ini_set` / `@set_time_limit` en exports pesados |
| MissingImport | 52 | Estilo (nombres totalmente calificados en línea), no bug |

### phpcpd — duplicación 2.15 % (2 456 líneas, 51 clones)
Bajo para el tamaño. Casi todo son pares copiados:

| Par | Líneas duplicadas |
|---|---|
| `ReqProgramaTejidoSimpleImport` ↔ `ReqProgramaTejidoUpdateImport` | ~670 en 5 bloques (el mayor, 221) |
| `ReporteResumenSemanal{Engomado,Urdido}Export` | 177, mismas líneas |
| `Bpm{Engomado,Urdido}Export` | 125 + 98 |
| `{Eng,Urd}BpmLineController` | 62 |
| `Reportes{Engomado,Urdido}Controller` | 62 |
| `ConfiguracionController` consigo mismo | 62 |

### Hotspots — funciones más largas y clases más complejas

| Archivo | Función más larga | Complejidad clase | Cobertura de líneas (pcov) |
|---|---|---|---|
| `ProgramaTejido/funciones/DividirTejido.php` | **636** (+302) | 326 | **0 %** (0/867) |
| `ProgramaTejido/funciones/UpdateTejido.php` | **559** | 291 | 26 % |
| `ProgramaTejido/funciones/DuplicarTejido.php` | **467** | — | **0 %** (0/531) |
| `Atadores/ProgramaAtadores/AtadoresController.php` | 418 | — | 57 % |
| `ProgramaTejido/LiberarOrdenesController.php` | 348 | 160 | 81 % |
| `CatLMat/CatLMatController.php` | 333 | — | 74 % |
| `ProgramaTejido/ProgramaTejidoOperacionesController.php` | 250 | — | **0 %** (1/402) |
| `Imports/ReqModelosCodificadosImport.php` | 249 | — | **0 %** |
| `Imports/ReqProgramaTejidoSimpleImport.php` | 243 | 167 | 3 % |
| `Imports/ReqProgramaTejidoUpdateImport.php` | — | 196 | 2 % |
| `Services/OeeAtadores/OeeAtadoresFileService.php` | — | **355** | 31 % |
| `OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php` | — | 200 | 68 % |
| `Urdido/ReportesUrdidoController.php` | — | 171 | 38 % |
| `CatCalendarios/CalendarioController.php` | — | — | **0 %** (0/836) |

Contexto: 1 675 tests / 244 archivos; **cobertura total 38.2 %** (21 916 / 57 446 líneas de `app/`);
phpstan nivel 5 con baseline de 3 441 errores. Los tests corren sobre sqlite, así que el SQL específico de
SQL Server no se ejerce aunque la línea cuente como cubierta.

## 3. Lectura

- El código **no está roto**: 0 vulnerabilidades, phpstan limpio sobre su baseline, 1 675 tests verdes, duplicación baja.
- La deuda está **concentrada**, no repartida: ~10 archivos con métodos de 250–636 líneas, la mitad en Programa Tejido.
  Ahí cualquier cambio es riesgoso y justo ahí hay menos tests.
- Los scores de phpinsights son engañosos sin afinar; sirven como **ratchet** (que no bajen), no como meta.
- "Estricto" = **nada nuevo empeora** (gates con baseline en CI) + deuda vieja se paga por hotspot con tests primero.
  Un big-bang de estilo sobre 11 000 avisos solo generaría diff y conflictos con las olas en curso.

## 4. Plan — Track CAL (Phase 22)

Reglas del track: medir antes/después; tests de caracterización antes de refactorizar; los hotspots de PT
se pagan **dentro de PT fase 5 (Mutaciones)**, no en paralelo; borrados puros sí, abstracciones solo con duplicado
literal (ver descarte de fases 5/6 JS de PT).

### 22-01 Guardarraíles de calidad (gates) — prioridad 1
- `phpinsights.php` afinado: quitar largo de línea, `final`, `strict_types`, `empty()`, short ternary, type hints
  (los cubre phpstan). Umbrales `min-*` = score afinado de hoy → no puede bajar.
- `phpmd.xml`: `unusedcode` + `codesize` + reglas útiles de `cleancode`/`design`; fuera `StaticAccess`,
  `ShortVariable`, `LongVariable`, `ElseExpression`, `MissingImport`. `--generate-baseline` → CI falla solo con avisos nuevos.
- phpcpd en CI con techo 2.15 % (ratchet hacia abajo).
- `composer audit` en CI (hoy 0; que siga en 0).
- `composer quality` = phpstan + phpinsights + phpmd + phpcpd, un solo comando.
- Cobertura base medida: 38.2 % total (ver tabla de hotspots). Ratchet: la cobertura total no baja.
- **Success:** CI rojo si sube complejidad, duplicación, avisos phpmd o vulnerabilidades; verde hoy.

### 22-02 Borrado de código muerto — riesgo bajo
- 12 métodos privados sin referencias (~500 líneas; `OeeAtadoresFileService` −10 métodos).
- Variables locales sin usar (60) y la propiedad privada sin usar de `ReqCalendarioLineImport`.
- Parámetros sin usar solo donde no los impone una firma externa.
- **Success:** baseline de phpmd baja; tests + phpstan verdes; 0 cambios de comportamiento.

### 22-03 Errores tragados
- Revisar los 29 `catch` vacíos: los del flujo principal → `report($e)` (llega a `SYSMonError`);
  los deliberados (`rollBack`, `disableQueryLog`) quedan con comentario del porqué.
- **Success:** 0 `catch` vacíos sin comentario; los errores reales aparecen en `/admin`.

### 22-04 Tests de caracterización de hotspots — prerrequisito de 22-05 y PT-05
- **0 % hoy, primero:** `DividirTejido` (867 líneas), `DuplicarTejido` (531), `CalendarioController` (836),
  `ProgramaTejidoOperacionesController` (402), `ReqModelosCodificadosImport` (497),
  `ReqProgramaTejido{Simple,Update}Import` (2–3 %).
- Después: `UpdateTejido` (26 %), `OeeAtadoresFileService` (31 %), `ReportesUrdidoController` (38 %).
- Infection sobre los que ya tienen cobertura alta (`LiberarOrdenesController` 81 %, `CatLMatController` 74 %,
  `OrdenDeCambioFelpaController` 68 %): MSI base = cuánto de esa cobertura realmente detecta fallas.
- **Success:** cada hotspot ≥ 70 % de líneas y MSI ≥ 60 % antes de refactorizarlo.

### 22-05 Duplicación literal Urdido/Engomado — ~590 líneas
- Exports resumen semanal y BPM, `*BpmLineController`, `Reportes*Controller`: extraer lo idéntico, parametrizado
  por tabla/módulo. Solo lo byte-idéntico que reporta phpcpd.
- `ConfiguracionController` (clon interno de 62 líneas).
- **Success:** duplicación < 1.6 %; tests de 22-04 verdes antes y después.

### 22-06 Complejidad fuera de PT
- `OeeAtadoresFileService` (tras 22-02), `AtadoresController` (método de 418 líneas), `CatLMatController` (333),
  `ReportesUrdidoController`, `CodificacionController`: partir métodos por paso de negocio, sin cambiar contratos.
- **Success:** ninguna función > 150 líneas en estos archivos; complejidad de clase −30 %; MSI no baja.

### → PT fase 5 (Mutaciones), ya en el roadmap
- `DividirTejido`, `UpdateTejido`, `DuplicarTejido`, `LiberarOrdenesController`, `OrdenDeCambioFelpaController`,
  `TejidoHelpers` y los ~670 líneas duplicadas entre `ReqProgramaTejido{Simple,Update}Import`.
- Entra con los tests/MSI de 22-04 como gate.

### 22-07 Subir el listón
- phpstan nivel 5 → 6 con baseline nuevo (el ratchet impide crecer).
- Subir umbrales `min-*` de phpinsights al cerrar cada fase.
- Infection con MSI mínimo sobre archivos cambiados en CI.

### Orden y dependencias
`22-01` → `22-02` → `22-03` (paralelo) → `22-04` → `22-05` · `22-06` · PT-05 → `22-07`

## 5. Pendientes del owner
1. `Start-Service Apache2.4` como administrador.
2. Confirmar en SSMS que el migrate fallido no dejó índices en `ProdTowel` (`sys.indexes` con `create_date` de hoy).
3. Decidir si las 37 migraciones pendientes se aplican a prod (ojo: `ONLINE = ON` no existe en Standard).
4. Commit de `composer.json`, `composer.lock`, `phpinsights.php` y este documento.
