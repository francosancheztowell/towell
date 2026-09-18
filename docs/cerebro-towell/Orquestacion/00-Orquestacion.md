# Orquestacion Towell Ops

Lead: **towellin**. Canal in-app: **Towell Ops** (solo agentes Towell).

## Miembros del canal (no mezclar otros bots)
| Agente | Rol |
|--------|-----|
| towellin | Lead, prioridad, veto, vault |
| Towell Planea | Liberar / L.Mat / codificacion / utileria / AuthZ planeacion |
| Towell UrdEng | Tablero status Urd/Eng, congelar legacy, ProgramBoard |
| Towell Mantto | Paros, mecanicos, refacciones |
| Towell QA | Tests, checklist regresion, veto StructureTest-as-green |

**Fuera del canal:** Atrix, Nova, Critiquito, Olimpo, Orion — se pueden consultar 1:1 si hace falta, no se sientan en Towell Ops.

## Skills
- [towell-consultar-cerebro](sand-workflow:towell-consultar-cerebro)
- [towell-local-inspect](sand-workflow:towell-local-inspect)
- [towell-planeacion](sand-workflow:towell-planeacion)
- [towell-urd-eng](sand-workflow:towell-urd-eng)
- [towell-mantenimiento-paros](sand-workflow:towell-mantenimiento-paros)
- [towell-regresion-checklist](sand-workflow:towell-regresion-checklist)

Repo: laravel-specialist, php-pro, webapp-testing, Boost. MCP: Context7 para docs Laravel/Livewire.

## Protocolo
1. Un owner por archivo / fat controller.
2. Cerebro = vault Obsidian, no Notion.
3. PRs chicos; CloudAgent en francosancheztowell/towell.
4. Inventario bugs solo con evidencia.
5. Ver [[criterio-grupo-agentes]] y [[../Arquitectura/livewire-cuando-si-cuando-no]].

## Primera cola sugerida
1. Planea: P0 public.php
2. UrdEng: una verdad actualizarStatus Engomado
3. QA: tests de esos PRs
