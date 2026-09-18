# SQL — mapa y conflictos

Mapa real de SQL Server (LA-GTECLAVE). Sin credenciales.

## Conexiones Laravel

| Connection | Database | Tablas dbo | ¿App la usa? | Estado |
|---|---|---|---|---|
| `sqlsrv` | **ProdTowel** | **95** | Sí | OK — mismo host que TI |
| `sqlsrv_ti` | **TI_PRO** | **2060** | Sí (~61 refs) | OK — AX/TI |
| `sqlsrv_tow_pro` | **TOW_PRO** | **2074** | **No (0 refs)** | Configurada; código muerto |
| `sqlsrv_tow_tow` | (sin env) | — | Refacciones mecánicos | **ROTA** |

## Conflictos
1. **TI_PRO ∩ TOW_PRO = 2060** nombres — AX gemelos en hosts distintos; app solo usa TI.
2. **ProdTowel ∩ TI = 0** nombres — bien; pero Liberar/L.Mat/Flogs/BOM saltan a TI con SQL crudo sin anti-corrupción.
3. **Tow_Tow** sin env mientras `RefaccionesParoService` lo exige.
4. Dual **ReqModelosCodificados** / **CatCodificados**; posible `req_matriz_hilos` vs `ReqMatrizHilos`.

## ProdTowel
Prefijos: Ata*, Cat*, Eng*, Inv*, Man*, Mec*, Req*, SYS*, Tej*, Tel*, Traza*, Urd*, …
Dump: `C:\Users\fsanchez\agent-tools\towell_tables_dump.txt`

## Quién pega a TI
LiberarOrdenes, CatLMat, CatCodificacion, catálogos programa, Eng formulacion, Flogs, BomMateriales, InventarioReservas, Pronosticos, imports…

## Veredicto
1. Apagar o documentar `sqlsrv_tow_pro`.
2. Configurar o eliminar `sqlsrv_tow_tow`.
3. Inventariar lecturas TI → pantalla (ver [[../Auditoria/corte-ti-y-docs-vs-codigo]]).
4. Unificar o deprecar dual codificación.
5. No confiar solo en migraciones Laravel.

Ver también [[00-Indice-SQL]].
