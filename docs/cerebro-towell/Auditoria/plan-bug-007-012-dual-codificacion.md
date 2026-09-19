# Plan: dual codificación / L.Mat (cola post-extracts Liberar)

**Fecha:** 2026-09-19  
**Owner:** Towell Planea  
**Go:** towellin (post-#52)

## Aclaración de IDs en vault

| Lo que dijo el go | ID en inventario | Evidencia |
|---|---|---|
| "BUG-007 dual ReqModelosCodificados/CatCodificados" | En vault eso es **BUG-012** | Dos superficies HTTP: `CodificacionController` (~1550 LOC) + `ReqModelosCodificados` vs `CatCodificacionController` (~892 LOC) + `CatCodificados` |
| BUG-007 en inventario | **L.Mat CRUDO: dos SQLs** | `LiberarBomCrudoResolver` (`whereExists` + filtro salón) vs `CatCodificacionController::queryLmatDesdeTi` (`JOIN BOMVERSION`, `limit(50)`, sin salón) |

Trabajamos **ambos**; orden abajo. Sin rediseño UI. `CreaProd=1` no se toca.

## Canónico propuesto

- **Datos de programa / liberar / L.Mat runtime:** `CatCodificados` + APIs `/planeacion/codificacion` y `/planeacion/lmat`.
- **Query L.Mat CRUDO AX:** una sola — `LiberarBomCrudoResolver` (o rename a `LmatCrudoQueryService` compartido). CatCodificacion **delega**; se quita `limit(50)` y se alinea filtro salón.
- **Legacy** `/planeacion/catalogos/codificacion-modelos` (`ReqModelosCodificados`): congelar escrituras nuevas; redirects de lectura hacia Cat cuando sea seguro.

## PRs

### PR-A — BUG-007 (SQL L.Mat unificado) — primero
1. Extraer/renombrar servicio compartido si hace falta (hoy ya existe `LiberarBomCrudoResolver`).
2. `CatCodificacionController::queryLmatDesdeTi` → llama al servicio (mismo contrato de filas).
3. Tests: mismas filas para `(item, size, salon)`; sin `limit(50)`; Feature Cat + Liberar BOM.
4. Inventario BUG-007 → mitigado (o parcial si aún hay divergencia de columnas UI).

### PR-B — BUG-012 (dual superficie) — después
1. Documentar canónico Cat en vault + AGENTS.
2. Redirect 301 de GETs legacy de listado/índice hacia `/planeacion/codificacion` **solo** si menú/usuarios ya no dependen del Blade viejo (verificar hits / menú SYSRoles).
3. Soft-freeze: deprecar `store/update/destroy` de `CodificacionController` (403 o flag) **o** hacerlos wrappers thin a Cat si todavía hay tráfico.
4. No borrar tabla `ReqModelosCodificados` en este slice (lectores en Tejedores/Alineación/etc.).

## Fuera de alcance
- Livewire Liberar, Engomado/Urdido, paros, rediseño pantallas, tocar `CreaProd`.

## Éxito
- Una query L.Mat CRUDO en código.
- Una superficie de escritura canónica documentada; legacy sin divergir en silencio.