# PT-02 Contención + lectura — SUMMARY

**Rama:** `claude/pt-01.1-02` (base `claude/friendly-hopper-506bg9`) · **Fecha:** 2026-09-24
**IDs:** PT-CON-01, PT-DOM-02, PT-READ-01, PT-ROL-01 (+ decisión 01.3)
**Commits:** `e3245d9` (código 01.1 + 02), `b4332b5` (fix de la revisión de seguridad), merge de la base, docs.
**Estado:**
- 02.1 a 02.4 hechos y verificados en sqlite. Decisión 01.3 aplicada en código; su DDL queda como `.sql` sin ejecutar.
- **02.5 (aprobar contrato read v2) espera al owner.**
- Liberar con "M": especificado en `02-MUESTRAS-LIBERAR.md` para PT-05/06.

## 1. Qué se entregó

| Tarea | Entregable |
|---|---|
| 02.0 Decisión 01.3 | `config/planeacion.php`: `superficies.muestras.capacidades` = redbooth B, marbetes A, producción A, descarga B, finalización B, longitudes A (+ `longitudes` en Programa) |
| | `.sql` aditivos para las capacidades A: `database/sql/pt_muestras_marbetes.sql`, `pt_muestras_produccion.sql`, `pt_muestras_longitudes.sql` |
| | Guards 422 para las capacidades B (Descarga, Redbooth), ocultos en la UI de Muestras |
| 02.1 Contexto explícito | `app/Services/Planeacion/ProgramaTejido/ProgramaTejidoSurface.php` (enum): tablas, módulo, capacidades efectivas, rutas y `exigir()` → 422. `ProgramaTejidoContext` queda como adaptador |
| 02.1 Catches silenciosos | Hallazgos 1, 4, 5 y 6 contenidos en `ReqProgramaTejidoObserver` (detalle en §3) |
| 02.2 Presentación | `index()` pasa `superficie`, `isMuestras` y `capacidades` a la vista (antes `$isMuestras` no llegaba, y Muestras mostraba Redbooth) |
| | Estado de error distinto del vacío: sin "Cargar Excel" y sin `getMessage()` en el HTML |
| | `PT_BOOT.capacidades` |
| 02.3 Lectura v2 | `GET planeacion/{programa-tejido,muestras}/v2/registros` (`acceso,2|5`), 404 con el flag apagado. `ProgramaTejidoReadController` → `ProgramaTejidoReadService` |
| 02.4 Shadow | `ProgramaTejidoReadComparison`: muestreo `PLANEACION_READ_V2_SHADOW_SAMPLE` (default 0); corre en `terminating` sobre `leer()` paginado y solo registra ids y campos divergentes |
| Liberar "M" | `02-MUESTRAS-LIBERAR.md`: requisitos R1–R9 y 7 tests especificados |

**Detalle de los `.sql`:**
- tienen preflight `IF COL_LENGTH(...) IS NULL`;
- el tipo se copia de `ReqProgramaTejido` vía `sys.columns`, no se escribe a mano;
- corren con `XACT_ABORT` y en transacción;
- traen rollback comentado y "NOTA PARA EL DBA".

**Detalle de los guards B:**
- Descarga en Muestras → 422 antes de leer líneas. Corrige el hallazgo 3: Muestras pisaba `ProgramaTejido.txt`.
- Redbooth desde Muestras → 422.
- En la UI de Muestras se ocultan el menú y el modal de Redbooth (por la vista) y el botón Descargar (por JS; ver HANDOFF B1).
- Finalización: sin código. Un test fija que `utileria/*` nunca es Muestras.

**Detalle de la lectura v2:**
- Allowlists de columnas, `sort`, `dir`, `per_page` ∈ {50, 100} y filtros validados por tipo de columna.
- `Id` como desempate del orden, y sin columnas repetidas en ORDER BY (SQL Server Msg 169).
- Consulta de solo lectura (`toBase()`, sin observers).

**Capacidades efectivas.** `soporta()` es `true` solo si la capacidad está decidida **y** sus columnas ya existen. Hoy, en Muestras, `marbetes` y `produccion` reportan `false` hasta que se aplique su DDL y se actualice `columnas_ausentes`.

## 2. Evidencia

```
php artisan test                       base: 1254 passed  →  rama: 1323 passed (19491 assertions), tras merge y fix
php artisan test tests/Feature/Planeacion tests/Unit/Planeacion   116 passed (875 assertions), tras el fix
vendor/bin/phpstan analyse             [OK] No errors
npm run typecheck && npm run test:js && npm run build   ok
npm run ratchet                        ratchet ok (10 metricas, ninguna subio)
vendor/bin/pint (solo archivos tocados) ok
planeacion:programa-tejido-health      sobre el fixture PT en sqlite: "Invariantes OK." exit 0 en ambas superficies
                                       (el entorno no tiene database/sqlite: el comando suelto dice "No se pudo consultar")
```

La suite completa se volvió a correr después del merge, del fix y de los docs: 1323 passed.

**Snapshot de rutas.** El diff se revisó (ver `01.1-SUMMARY.md` §2). Solo cambian 3 middlewares y se agregan 2 rutas v2.

**Revisiones:**
- **code-review:** 10 hallazgos. Se corrigieron:
  - relanzamiento en callers post-commit;
  - sort duplicado (Msg 169);
  - orden no determinista;
  - filtros sin tipo (500 en vez de 422);
  - sombra que no ejercitaba `leer()`;
  - capacidades A reportadas antes de su DDL;
  - aviso una vez por proceso → una por hora vía cache.
  
  El del botón del navbar va en HANDOFF B1.
- **security-review:** 1 hallazgo alto, corregido en `b4332b5`. `fromRequest()` usaba el path codificado, así que `/planeacion/%6Duestras/{id}` llegaba a `muestras.*` con permiso de Muestras pero operaba sobre las tablas de Programa. Hay datasets de regresión.

## 3. Catches contenidos (los tests se invirtieron, no se borraron)

| # | Antes | Ahora | Test |
|---|---|---|---|
| 1 | En Muestras el UPDATE de fórmulas incluía `RollosProgramados` → fallaba → warning y éxito aparente, sin ninguna de las 5 fórmulas | El UPDATE se filtra a las columnas físicas de la superficie: las fórmulas se persisten en Muestras ya. Cualquier fallo → `Log::error` + `report()` | `test_en_muestras_el_recalculo_de_produccion_persiste_las_formulas` |
| 4 | Si fallaba el INSERT de líneas → warning, cabecera nueva con líneas viejas, save "exitoso" | `saved()` **relanza si hay transacción abierta** (quien llama revierte todo) y `UpdateTejido` pide relanzar. Los callers que regeneran **después** del commit (dividir, finalizar, eliminar, lotes de calendario) no relanzan: `Log::error` + `report()`, para no dar un 500 post-commit que invite a repetir la operación. El UPDATE de fórmulas de eficiencia también se filtra a columnas físicas | `test_si_falla_insertar_lineas_se_conservan_las_previas_y_el_save_reporta_el_fallo` |
| 5 | La cabecera se recalculaba aunque CatCodificados fallara, y sin columnas legibles el sync se saltaba sin log | Cabecera + CatCodificados en una transacción; tabla ilegible → `Log::error` | `test_si_falta_cat_codificados_la_cabecera_no_queda_recalculada_y_se_registra` |
| 6 | Con el maestro de pesos ilegible, se usaban 41.5 kg sin aviso | Mismo respaldo (no cambia ningún número), pero con `Log::error` + `report()`, a lo más una vez por hora | aserción en `test_editar_pedido_recalcula_produccion_...` |

## 4. Decisiones tomadas y pendientes del owner

- **D-1 (pendiente, owner).** El observer consulta `ReqPesosRollosTejido` (plural), pero el modelo, `LiberarMarbetesCalculator` y la documentación usan **`ReqPesosRolloTejido`** (singular).
  - Si en live solo existe la singular, **todo recálculo del observer sin `PesoRollo` capturado usa 41.5 kg**, y el cron de 30 min lo repite. Desde PT-02 queda registrado como error.
  - Corregir el nombre cambiaría números en planta, así que no se hizo.
  - Para verificarlo: `SELECT OBJECT_ID('dbo.ReqPesosRollosTejido') AS plural, OBJECT_ID('dbo.ReqPesosRolloTejido') AS singular;`
- **D-2.** No se pasó `$moduloPT` del menú contextual a la superficie, por la regla de UI (ver HANDOFF B2/B3).
- **D-3.** El estado de error del index es un bloque nuevo con el mismo marcado del estado vacío. Es contención de 02.2: antes, un error de BD invitaba a reimportar el Excel.
- **D-4.** `ProgramaTejidoSurface` vive en `app/Services/Planeacion/ProgramaTejido/`, no en `app/Support/`, que no es de PT. La validación de v2 va en el controller y no en una FormRequest (`app/Http/Requests` no es de PT).
- **D-5.** La sombra compara ids y campos, no valores. En empates exactos de telar/salón/posición/fecha el orden legacy es indefinido, así que `orden_igual=false` puede ser un falso positivo.
- **Tamaño.** Unas 1 870 líneas agregadas frente a la base; de ellas, ~320 son SQL y snapshot, y ~600 son tests. El código de producción queda por debajo de 1 000. Si el integrador quiere partirlo, `e3245d9` separa limpio de `b4332b5`.

## 5. Cómo desplegar

1. Código: `php artisan optimize:clear` (o `config:clear` + `route:clear` si usan cache).
2. Sin variables nuevas obligatorias. Opcionales, todas apagadas por default: `PLANEACION_PROGRAMA_READ_V2`, `PLANEACION_MUESTRAS_READ_V2`, `PLANEACION_READ_V2_SHADOW_SAMPLE`.
3. Permisos: para liberar Muestras hace falta `crear` en el módulo Muestras; para descargar, `registrar` en Programa Tejido (ver `01.1-SUMMARY.md` §5).
4. DDL de Muestras (opcional ahora; necesario para liberar Muestras): DBA con los `.sql`, primero en staging. Antes y después: `01-schema-fisico.sql` + `php artisan planeacion:programa-tejido-health --json`. **Reiniciar workers y colas después** (el listado de columnas se cachea por proceso). Luego, la sesión PT actualiza `columnas_ausentes` y `longitudes`.
5. Canary v2 (02.5): encender `PLANEACION_READ_V2_SHADOW_SAMPLE=0.05` un día, revisar `programa_tejido.read_v2.divergencia` en el log y después decidir los flags de lectura.

No hay migraciones.
