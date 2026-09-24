# Runbook PT-01 — lo que tiene que correr el owner en Laragon

Todo es **solo lectura**. Nada aquí ejecuta `migrate`, `ALTER`, `UPDATE` ni `DELETE`.
Tiempo estimado: 15 minutos.

## 0. Preparar

```powershell
git fetch origin claude/pt-01-guardrails
git checkout claude/pt-01-guardrails
composer install
php artisan optimize:clear
```

`config/database.php` no se toca (lo administra `scripts/db-config.ps1`).

## 1. Suite de caracterización (sqlite en memoria, no toca SQL Server)

```powershell
php artisan test tests/Feature/Planeacion tests/Unit/Planeacion
```

Esperado: 47 tests en verde (44 nuevos de PT-01 + 3 previos de `tests/Unit/Planeacion`). Si algo falla en Windows y no en la nube, anotarlo en el SUMMARY (sección "pendientes").

## 2. Gate de invariantes contra la BD real (read-only)

```powershell
php artisan planeacion:programa-tejido-health --json | Out-File -Encoding utf8 storage\app\pt01-health-antes.json
echo $LASTEXITCODE
```

| Exit | Significado |
|---|---|
| 0 | Todas las invariantes "error" en 0 |
| 1 | Alguna invariante "error" > 0 (ver `checks[].ok = false`) |
| 2 | No se pudo consultar una superficie (tabla inexistente, permisos, conexión) |

Comparar con la línea base del research (2026-07-22):

| Check | Programa | Muestras |
|---|---:|---:|
| `cabeceras` | 69 | 0 |
| `lineas` | 853 | 0 |
| `posiciones_duplicadas` | 0 | 0 |
| `telares_multi_en_proceso` | 0 | 0 |
| `posiciones_nulas` | 0 | 0 |
| `lineas_huerfanas` | 0 | 0 |
| `programas_sin_lineas` | 0 | 0 |

Los conteos de cabeceras/líneas cambian con la operación diaria; las invariantes "error" deben seguir en 0. Los checks "aviso" (`telares_multi_ultimo`, `grupos_sin_un_lider`, `sin_fila_cat_codificados`) **no tienen línea base todavía**: anotar sus valores en el SUMMARY para decidir en fase 02 si suben a "error".

> `programas_sin_lineas` usa el mismo criterio que el observer (FechaInicio y FechaFinal no nulas, FechaFinal > FechaInicio, `COALESCE(SaldoPedido, Produccion, TotalPedido) > 0`). Si el research contó distinto y el valor no es 0, reportarlo antes de tocar nada.

## 3. Matriz física de schema

En SSMS, contra la misma BD:

```
.planning/phases/01-guardrails/sql/01-schema-fisico.sql
```

Guardar los 6 result sets (clic derecho → *Save Results As…* CSV) en `storage\app\pt01-schema\RS1.csv … RS6.csv`.

Qué hacer con cada uno:

| RS | Qué es | Acción |
|---|---|---|
| RS1 | Columnas de las 4 tablas | Adjuntar al PR / HANDOFF |
| RS2 | Diferencias Programa ↔ Muestras | **Llenar `config/planeacion.php` → `superficies.muestras.longitudes`** con `longitud_muestras` de cada fila `LONGITUD`. Confirmar que `SOLO_PROGRAMA` son exactamente las 6 de `columnas_ausentes`. Cualquier otra fila = divergencia nueva: anotarla en el SUMMARY |
| RS3 | Índices | Confirmar si Programa tiene el índice único `(SalonTejidoId, NoTelarId, Posicion)` y si Muestras no. Anotar nombres |
| RS4 | Foreign keys | Confirmar la FK `ReqProgramaTejidoLine.ProgramaId → ReqProgramaTejido.Id` (y su `ON DELETE`) y si Muestras tiene la suya |
| RS5 | Triggers / checks | Anotar triggers (afectan `@@ROWCOUNT`; el observer ya desconfía del conteo) |
| RS6 | Conteos | Debe cuadrar con el paso 2 |

Después de llenar las longitudes:

```powershell
php artisan test --filter=ProgramaTejidoSchemaCapabilityTest
```

Va a fallar a propósito en `test_el_truncado_no_excede_la_longitud_fisica_de_cada_superficie`: lista los campos donde `StringTruncator` deja pasar más de lo que cabe en Muestras. Esos campos se copian a `GAPS_LONGITUD` y se vacía la lista de pendientes del mismo test. Ese cambio sí lo puede hacer la siguiente sesión PT con el CSV adjunto.

## 4. Migraciones (solo consulta)

```powershell
php artisan migrate:status > storage\app\pt01-migrate-status.txt
```

No correr `migrate`. El objetivo es listar las pendientes para reconciliarlas con RS3/RS4 en la fase siguiente.

## 5. Devolver

- `pt01-health-antes.json`, los 6 CSV y `pt01-migrate-status.txt`.
- La tabla de la §5 de `01-DECISION-PROGRAMA-MUESTRAS.md` con A/B por capacidad.
