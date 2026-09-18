# Auditoría Towell — corte TI + docs vs código (2026-09-18)

Notion free blocks agotados: detalle aquí hasta que haya espacio.

## Mapa TI (sqlsrv_ti / TI_PRO) → pantallas

| Hits | Archivo | Dominio |
|---|---|---|
| 12 | LiberarOrdenesController | Liberar órdenes |
| 10 | CatLMatController | L.Mat |
| 4 | ProgramaTejidoCatalogosController | Catálogos programa |
| 4 | CatCodificacionController | Codificación |
| 4 | EngProduccionFormulacionController | Fórmula engomado |
| 3 | ProduccionReenconadoCabezuelaController | Reenconado |
| 3 | NotificarMontRollosController | Cortado rollo |
| 3 | CatalogosMaterialesLMatService | L.Mat service |
| 3 | NuevoRequerimientoController | Inv trama |
| 2 | TrazabilidadFlogsService / CrudoFlogService | Flogs |
| 2 | AtaDevolucionesController | Devoluciones |
| 1 | BomMateriales, InventarioReservas, ProgramarUrdEng, imports, Pronosticos, SqlServerCrudoRead, DuplicarTejido, Codificacion legacy | varios |

Objetos AX frecuentes: InventSize, InventColor, BOMVersion/BOMTABLE, InventTable, ConfigTable, TwFlogs*, TwSalon, TwBomEmpaque.

## Docs vs código (confirmado)

1. **FechaFinaliza al mover:** AGENTS dice no tocar; `MoverOrdenesController` llama `actualizarFechasArranqueFinaliza($reg, null, null)` con default que puede **escribir null** en programa y CatCodificados.
2. **validar-duplicado paros:** documentado en AGENTS; **ruta inexistente**.
3. Cache menú = `modulos_v3`.
4. L.Mat %100: backend `porcentaje` nullable, sin suma 100.
5. `actualizarFechasArranqueFinaliza` sincroniza **CatCodificados**, no ReqModelosCodificados.
6. **ponytail** = comentario/autor en `MovimientoDesarrolladorService`, no skill global.

## Dual codificación ProdTowel
`ReqModelosCodificados` vs `CatCodificados` — dos tablas, dos controllers.

## Superficie pública extra (public.php)
Además de login/logout/offline:
- GET /obtener-empleados/{area} **sin auth** (lista empleados por área)
- GET /test-404
- CRUD /modulos-sin-auth (P0)

## Engomado status (matiz al PR #32)
ProgramarEngomadoController sí tiene chequeo de Urdido Finalizado en el flujo de verificación (~305-316) al intentar En Proceso.
Hay comentario "~362 Restricción eliminada - se permite cualquier cantidad de órdenes en proceso" (límite de 2 máquinas relajado en legacy).
Contrastar con ProgramBoardActionService (límite 2 + AX). No dar por cerrado el P1 sin leer ctualizarStatus completo vs Livewire path.

## Front
~323 etch( vs ~69 axios/http en resources — dos estilos de cliente HTTP.
