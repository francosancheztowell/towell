# Towell — Cerebro (continuación)
Generado 2026-09-18. Hub Notion (draft privado): https://app.notion.com/p/3dfd9e900c8481a2a84bde42a1f56e0d
Notion alcanzó límite de bloques free; este archivo completa lo que no cupo.

## Módulos pendientes de ficha Notion

### Atadores (~39 rutas)
- programaatadores, iniciar, calificar, save, devoluciones
- catálogos: actividades, comentarios, máquinas
- reportes + OEE async (verificar/despachar/estado)
- Código: Controllers/Services Atadores, OeeAtadores

### Tejedores (~47)
- configurar telaresxoperador, actividades BPM, catálogo calibres
- bpmtejedores / tel-bpm (terminar, autorizar, rechazar)
- desarrolladores / muestras; atadodejulio; cortadoderollo
- inventario-telares; reportes

### Configuración (~53)
- usuarios + QR + permisos
- gestión módulos dinámicos (toggle acceso/permiso, sincronizar, duplicar)
- cargar planeación Excel; basededatos; departamentos; secuencia folios; mensajes Telegram

### Crudo (~7)
- /Crudo, reporte-día, flog-imagen, auditorías (hoy/store/con paro)

### Integraciones
- navigation: produccionProceso + submodulos API
- telegram: send/bot-info/get-chat-id
- redbooth: OAuth + tasks/files
- producto-terminado: shell liviano
- También: api.php, public.php, ai.php

## Orquestación recomendada

### Skills (Grok Bot)
1. towell-consultar-cerebro — Notion + AGENTS.md antes de cambiar
2. towell-local-inspect — Shell en LA-GTECLAVE path Towell
3. towell-planeacion-programa — liberar/balancear/L.Mat/utilería
4. towell-mantenimiento-paros — API paros y reglas de área
5. towell-urd-eng-flow — programa-urd-eng ↔ urdido ↔ engomado ↔ atadores
6. towell-regresion-checklist — post-cambio por módulo

### Agentes nuevos (un job cada uno)
| Nombre | Job | Anti-jobs |
| Towell Planea | planeación + programa tejido | no urd/eng ni mantto |
| Towell Piso | tejido, tejedores, atadores, crudo | no catálogos planeación |
| Towell UrdEng | urdido, engomado, programa-urd-eng | no config usuarios |
| Towell Mantto | mantenimiento + mecánicos | no programa tejido |
| Towell QA | regresión / UI / checklists | no implementa solo |

Reusar: Atrix (senior código), Nova (rutinas/conectores), Critiquito (UI).

### Canal
Towell Ops: towellin + especialistas.

### Prioridad
1. Skills cerebro + inspect
2. Crear Towell Planea primero
3. Canal cuando haya ≥2 especialistas
4. Ampliar Notion cuando haya espacio de bloques
