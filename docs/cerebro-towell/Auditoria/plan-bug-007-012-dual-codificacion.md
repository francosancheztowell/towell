# Plan — unificar L.Mat CRUDO (BUG-007) y dual Codificación (BUG-012)

**Fecha:** 2026-09-19  
**PR-A (este cambio):** vault **BUG-007** — unificar SQL L.Mat CRUDO en `LiberarBomCrudoResolver`.  
**Fuera de este PR:** vault **BUG-012** — dual `ReqModelosCodificados` / `CatCodificados` (superficies HTTP, Excel, rutas). El go-text que mezcla “ReqModelos/CatCodificados” describe **BUG-012**, no este trabajo.

---

## Alcance de este PR (BUG-007)

Hoy hay dos consultas incompatibles a AX (`sqlsrv_ti`, `BOMTABLE` + `BOMVERSION`, `ITEMGROUPID = CRUDO`):

| Camino | SQL | Límite | Salón |
|--------|-----|--------|-------|
| `LiberarBomCrudoResolver::query` | `EXISTS` en `BOMVERSION` (no duplica por versiones AX) | ninguno | `salonAliasesAx($salon)` o, si no hay salón, `todosLosAliasesAx()` |
| `CatCodificacionController::queryLmatDesdeTi` | `JOIN BOMVERSION` | `limit(50)` | siempre todos los alias; no usa el salón de Cat |

Efecto: el select de Peso muestra puede listar BOMs distintos a Liberar, repetir filas y truncar a 50.

### Trabajo PR-A

1. Inyectar `LiberarBomCrudoResolver` en `CatCodificacionController`.
2. `queryLmatDesdeTi` delega a `resolver->query()` y mapea a `{bomId, bomName}` (contrato Blade/JS de `listaLmat`).
3. Borrar el JOIN + `limit(50)`.
4. Pasar salón cuando Cat lo tiene (`Departamento` = salón; `TelarId` como fallback vía `TelarSalonResolver`). Si no hay salón, `query(..., null)` → todos los alias AX.
5. Tests: Cat usa el resolver (EXISTS, sin tope 50, mismo shape); tests de Liberar BOM siguen verdes.
6. Inventario: BUG-007 → mitigado (o parcial si el mapeo UI aún divergiera).

### No tocar

- `CreaProd`
- UI / Livewire Liberar
- Borrar `ReqModelosCodificados` o `CodificacionController` (**eso es BUG-012**)
- Engomado / Urdido / paros

---

## Follow-up: BUG-012 (no este PR)

Dos superficies de Codificación:

- Canónica planta: `CatCodificacionController` + `resources/views/catcodificacion/` + `CatCodificados`
- Legacy: `CodificacionController` + `catalagos/catalogoCodificacion.blade.php` + `ReqModelosCodificados`

Siguiente PR (no mezclar con SQL CRUDO): decidir canónico, congelar legacy, no borrar tablas/controllers hasta tener plan de corte.

---

## Criterio de hecho (BUG-007)

- Una sola query CRUDO (`LiberarBomCrudoResolver::query`) para Liberar y Cat Peso muestra.
- Mismo `(item, size, salon)` → mismas filas `{bomId, bomName}`.
- Lista Cat sin `limit(50)`.
- `CreaProd` y dual ReqModelos intactos.
