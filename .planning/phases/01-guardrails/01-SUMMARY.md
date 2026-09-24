# PT-01 Guardrails — SUMMARY

**Rama:** `claude/pt-01-guardrails` (base `claude/friendly-hopper-506bg9`) · **Fecha:** 2026-09-24
**IDs:** PT-CON-01, PT-CON-02, PT-DOM-01, PT-DOM-02, PT-ROL-01
**Estado:** 01.1, 01.4 y la parte sqlite de 01.2 y 01.5 están hechas y verificadas. **01.3 espera la decisión del owner.** La parte en vivo de 01.2 y 01.5 espera a que el owner corra el runbook en Laragon.

No se tocó código funcional, rutas, vistas, JS, migraciones ni configuración existente. Todo es aditivo: tests, un comando read-only, un config nuevo que no lee ningún código de runtime, y docs/SQL.

## 1. Qué se entregó

| Tarea | Entregable | Estado |
|---|---|---|
| 01.1 Superficie HTTP | `tests/Feature/Planeacion/ProgramaTejidoRouteSurfaceTest.php` + snapshot `tests/fixtures/planeacion/programa-tejido/rutas.json` (240 rutas: 195 bajo `planeacion/`, 22 `programa-tejido/*`, 22 `muestras/*`, 1 `modulo-codificación`) con método, URI, nombre, middleware, action, superficie y capacidad; paridad Programa↔Muestras por contrato | ✅ verificado en sqlite |
| 01.2 Matriz de schema | `config/planeacion.php` (`superficies`: tablas, módulo de permiso, 6 columnas ausentes, 11 longitudes **en null**, capacidades) + `ProgramaTejidoSchemaCapabilityTest` + `sql/01-schema-fisico.sql` (read-only, 6 result sets) | ✅ parte sqlite / ⏳ datos físicos: owner |
| 01.3 Decisión | `01-DECISION-PROGRAMA-MUESTRAS.md`: A/B por capacidad (Redbooth, marbetes, producción, descarga, finalización + longitudes), con impacto, rollback, datos, owner y aceptación | ⏳ **bloqueante: espera al owner** |
| 01.4 Fixtures y aislamiento | `tests/Feature/Planeacion/Concerns/ProgramaTejidoFixtures.php` + `ProgramaTejidoIsolationTest` | ✅ verificado en sqlite |
| 01.5 Invariantes y fórmulas | `ProgramaTejidoInvariantTest`, `tests/Unit/Planeacion/ProgramaTejidoFormulaCharacterizationTest`, comando `planeacion:programa-tejido-health` + `ProgramaTejidoHealthCheckTest` | ✅ parte sqlite / ⏳ baseline live: owner |
| Runbook | `RUNBOOK-LARAGON.md` | ✅ |

## 2. Evidencia

```
php artisan test tests/Feature/Planeacion tests/Unit/Planeacion
  Tests:    47 passed (646 assertions)          # 44 nuevos + 3 previos

php artisan test                                # suite completa, sqlite
  antes:    20 failed, 49 skipped, 1132 passed
  después:  20 failed, 49 skipped, 1176 passed  # mismos 20 fallos, sin regresiones
```

Los 20 fallos previos son todos de este entorno: falta `public/build/manifest.json` (Vite no está compilado en la nube). Afectan vistas Livewire/Blade de Atadores, Engomado, Tejido, Programas, PWA y también `ProgramaTejidoSmokeTest` / `ProgramaTejidoJsSyntaxTest`. Mismo listado antes y después (diff vacío). No se corrió `npm ci && npm run build`: esta fase no toca JS y `public/build` no se edita.

Otros checks:
- `vendor/bin/pint` solo sobre archivos tocados: pass.
- Skill `code-review` sobre el diff: 2 hallazgos en el comando, ambos corregidos. Si `planeacion.superficies` está vacío, el comando ahora sale con 2 en vez de reportar sano. `--json` ahora usa `JSON_INVALID_UTF8_SUBSTITUTE`, para que un mensaje de SQL Server con encoding raro no deje el JSON vacío. Se agregó un test para el primer caso.
- Mutación manual del snapshot (cambiar el módulo de `PUT planeacion/muestras/{id}`): el test falla con `Contrato cambiado`, como debe.
- `git diff -- database/migrations`: vacío.

## 3. Hallazgos que el owner debe conocer (congelados en tests)

| # | Hallazgo | Test que lo congela |
|---|---|---|
| 1 | **En Muestras, el recálculo de producción falla en silencio.** El UPDATE del observer incluye `RollosProgramados`, que Muestras no tiene. Se pierden Repeticiones, PzasRollo, MtsRollo, TotalRollos y TotalPzas, y el save responde éxito. Alcanza a update, dividir y balancear en Muestras | `ProgramaTejidoInvariantTest::test_en_muestras_el_recalculo_de_produccion_falla_en_silencio` |
| 2 | **Liberar en Muestras siempre falla** (asigna `NoMarbete`/`RollosProgramados`). Además su ruta exige `crear,2` (Programa) en vez del módulo 5 | `RouteSurfaceTest::test_las_mutaciones_de_muestras_exigen_el_modulo_de_muestras`; decisión §2 |
| 3 | **Descargar en Muestras pisa `\\192.168.2.11\txts\ProgramaTejido.txt`** (nombre fijo, `DescargarProgramaController.php:58`) | documentado en decisión §4.4 (no se testea: es UNC) |
| 4 | Si falla el insert de líneas, las líneas viejas se conservan (la transacción funciona), pero la cabecera queda con los valores nuevos. Resultado: inconsistencia silenciosa con sólo `Log::warning` | `test_si_falla_insertar_lineas_se_conservan_las_previas_y_el_save_reporta_exito` |
| 5 | Si la tabla CatCodificados no es legible, `sincronizarCatCodificados` se salta **sin registrar nada** (`getColumnListing` devuelve `[]`). Las fórmulas quedan escritas en la cabecera y no en CatCodificados, porque no hay transacción | `test_si_falta_cat_codificados_la_cabecera_queda_recalculada_y_cat_sin_sincronizar` |
| 6 | Sin maestro `ReqPesosRollosTejido` legible, el peso de rollo cae a 41.5 kg sin aviso (catch que devuelve null) | `test_editar_pedido_recalcula_produccion_y_sincroniza_cat_codificados_solo_de_su_telar` |
| 7 | `StringTruncator` usa los límites de Programa en las dos superficies. En Muestras, un valor que cabe en Programa puede dar SQLSTATE 22001 | `IsolationTest::test_el_truncado_usa_los_limites_de_programa_en_ambas_superficies` y `SchemaCapabilityTest` |
| 8 | `ProgramaTejidoContext` también atrapa cualquier URI que empiece con `muestras` (p. ej. `muestrasx/...`). Hoy no hay rutas así | `IsolationTest` (dataset "prefijo ambiguo") |
| 9 | El modelo `Muestras` hereda `$fillable` de Programa: acepta 5 columnas que no existen físicamente | `SchemaCapabilityTest::test_el_modelo_compartido_acepta_columnas_que_muestras_no_tiene` |
| 10 | Las migraciones no declaran el índice único `(SalonTejidoId, NoTelarId, Posicion)` ni la FK de líneas. Existen solo en live (según el research) → RS3/RS4 | `sql/01-schema-fisico.sql` |

Aislamiento verificado: leer no escribe; mutar o borrar una superficie no toca la otra ni sus líneas ni CatCodificados, aunque los Id se solapen; el observer regenera las líneas de la tabla correcta; el modelo `Muestras` ignora el contexto por URL.

## 4. Qué tiene que correr el owner en Laragon

Ver `RUNBOOK-LARAGON.md`. Resumen:
1. `php artisan test tests/Feature/Planeacion tests/Unit/Planeacion` → 47 en verde.
2. `php artisan planeacion:programa-tejido-health --json` → comparar con la línea base 69 / 853 / 0 / 0 e invariantes en 0; anotar los "avisos" (todavía sin línea base).
3. `sql/01-schema-fisico.sql` en SSMS → llenar `superficies.muestras.longitudes` con RS2 y anotar índices/FK/triggers (RS3–RS5).
4. `php artisan migrate:status` (solo consulta).

**Huecos marcados, sin inventar:** las 11 longitudes físicas de Muestras (`null` en config; el test lista las 11 como pendientes), la existencia real del índice único y de las FK, los triggers, y la línea base live actual.

## 5. Decisión que se espera

`01-DECISION-PROGRAMA-MUESTRAS.md` §5: A (paridad física aditiva) o B (exclusiva de Programa) **por capacidad**. Recomendación de la sesión:

| Capacidad | Recomendación |
|---|---|
| Redbooth | B |
| Marbetes | depende de **si las muestras se liberan** (A si sí, B + retirar rutas de liberar si no) |
| Producción | A |
| Descarga UNC | B inmediato (A solo si alguien consume un TXT de muestras) |
| Finalización | B (es el estado actual, solo se hace explícito) |
| Longitudes | A (ensanchar las 11 en Muestras, 0 filas) |

Nada que dependa de la decisión se ejecutó.

## 6. Archivos

Nuevos (todos dentro de la propiedad PT):
- `app/Console/Commands/PlaneacionProgramaTejidoHealthCheck.php`
- `config/planeacion.php`
- `tests/Feature/Planeacion/{Concerns/ProgramaTejidoFixtures,ProgramaTejidoRouteSurfaceTest,ProgramaTejidoSchemaCapabilityTest,ProgramaTejidoIsolationTest,ProgramaTejidoInvariantTest,ProgramaTejidoHealthCheckTest}.php`
- `tests/Unit/Planeacion/ProgramaTejidoFormulaCharacterizationTest.php`
- `tests/fixtures/planeacion/programa-tejido/rutas.json` (datos generados; se regenera con `PT_ROUTES_SNAPSHOT=update`)
- `.planning/phases/01-guardrails/{01-DECISION-PROGRAMA-MUESTRAS.md,RUNBOOK-LARAGON.md,01-SUMMARY.md,sql/01-schema-fisico.sql}`

Decisiones de implementación:
- **Fixtures en `tests/fixtures/…` (minúscula), no en `tests/Fixtures/…`.** `tests/fixtures/` ya existe, y en Windows/Laragon las dos serían la misma carpeta. El helper PHP vive en `tests/Feature/Planeacion/Concerns/` (glob PT). Si el integrador prefiere otra ubicación, basta con moverlo.
- **`UsesSqlsrvSqlite` no se modificó.** El fixture lo usa y crea `MuestrasPrograma` sin las 6 columnas ausentes, con su propio constructor de tablas.
- **`config/planeacion.php` no declara `programa_tejido_table`.** Así no cambia el comportamiento de `ReqProgramaTejido::getTable()`. Hoy ningún código de runtime lee `superficies`; es la semilla del `ProgramaTejidoSurface` de la fase 02.
- **Severidades del comando.** "error" = invariante con línea base 0 verificada en live. "aviso" = regla sin línea base (Ultimo, líderes de grupo, CatCodificados); no rompe el gate hasta confirmarla con datos reales.
- **Tamaño.** 2 280 líneas agregadas (con este SUMMARY), de las cuales 311 son el snapshot generado y unas 400 son docs/SQL. El código + tests quedan por debajo de 1 500.

## 7. Pendientes / para la fase 02

- Contener los catches silenciosos 1, 4, 5 y 6: al contenerlos, los tests `…_en_silencio` / `…_reporta_exito` se **invierten**, no se borran.
- Aplicar la decisión 01.3: `capacidades` de Muestras en config, `.sql` aditivos vía HANDOFF para `database/sql/`, y guards 422 para las capacidades B.
- Corregir el permiso de `muestras.liberar-ordenes.procesar` (`crear,2` → `crear,5`). Requiere confirmación del owner porque cambia quién puede liberar muestras.
- Subir a "error" los avisos del comando que tengan línea base 0 en live.
- Correr `npm ci && npm run build` en CI (fase 10) para que `ProgramaTejidoSmokeTest` y `ProgramaTejidoJsSyntaxTest` vuelvan a ejecutar.

## 8. Cómo desplegar

Nada que desplegar en producción. `config/planeacion.php` es nuevo; si producción usa `config:cache`, hay que correr `php artisan config:clear` antes del comando de salud (si no, el comando sale con 2 y lo avisa). No hay migraciones, `.sql` que ejecutar ni variables `.env` nuevas.
