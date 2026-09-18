# Módulo: Planeación

## Rol
Núcleo del ERP: programa de tejido, catálogos, codificación, L.Mat, utilería (finalizar/mover), alineación, muestras.

## Rutas
`routes/modules/planeacion.php` (~194). Prefijo `/planeacion` · `planeacion.*`

### Subáreas
- Catálogos: telares, eficiencia, velocidad, calendarios, aplicaciones, matriz hilos/calibres, pesos rollos, L.Mat
- Codificación: CatCodificados + legacy codificacion-modelos; Excel; L.Mat APIs; peso muestra; revivir programa
- Programa tejido: liberar, marbetes, balancear/Gantt, cambiar/duplicar/dividir telar, reprogramar, repaso, Redbooth, auditoría
- Muestras: paralelo liberar/balancear
- Utilería: finalizar, mover
- Alineación: API + Excel/PDF

## Código
Controllers `Planeacion/{Alineacion,Auditoria,CatalogoPlaneacion,CatCodificados,CatLMat,ProgramaTejido,Utilerias}`; Services `Planeacion/`; Models `Planeacion/`.

## Reglas (AGENTS — verificar vs código)
- Liberar: L.Mat bomId/bomName obligatorios; Prioridad string
- Finalizar: no si producción 0/null
- Mover: AGENTS dice no tocar FechaFinaliza — **código puede nullarla** (ver Auditoría)
- L.Mat: máx 6 filas; %100 solo JS hoy; AX sqlsrv_ti

## Tablas
ReqProgramaTejido, CatCodificados, CatLMat, CatMatrizCalibres, BOMTABLE/BOMVERSION
