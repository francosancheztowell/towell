# Criterio go/no-go — grupo de agentes Grok Bot (Towell)

**Fecha:** 2026-09-18 ~11:05 CT.  
**Contexto:** hoy solo corre **towellin**. Reutilizar Atrix / Critiquito / Nova cuando aporte; canal Slack/Discord **solo** si hay ≥2 especialistas activos.  
**Evidencia:** inventario 28 bugs (3×P0), dual path Urd/Eng, AuthZ=`auth` only, fat controllers locales (Liberar 2537, …).

---

## Recomendación HOY

# **WAIT** — no desplegar grupo de especialistas *ahora*.

### Por qué WAIT (evidencia, no vibes)

1. **P0 abiertos bloquean trabajo paralelo seguro.** `routes/public.php` CRUD módulos sin login + `GET /obtener-empleados/{area}` + APIs solo `auth` (`userCan`/`can:` en routes = **0** en LA-GTECLAVE). Un especialista UrdEng/Planea pegándole a endpoints “porque el menú lo oculta” amplifica el radio de explosión.
2. **Dual path Engomado no está muerto.** Default Blade; Livewire escondido en ruta *nombrada* `.legacy`. Dos agentes “arreglando status” = dos verdades peores.
3. **Vault incompleto para split.** Faltaba esta matriz Livewire + mapa AuthZ + deuda JS (se escriben en esta pasada). Sin notas de módulo firmes, los bots inventan alcance.
4. **Fat controllers = anti-patrón CloudAgent paralelo.** Liberar / Felpa / Cortes / ReportesUrdido / OT Mecánicos no admiten dos agentes en el mismo archivo.
5. **Señal de volumen aún es “una auditoría + remediación P0/P1”**, no 4 streams concurrentes con owners claros. towellin + (opcional) un crítico de código basta.

---

## Umbrales para voltear a GO

Voltear a **GO (grupo chico)** solo si **todas** se cumplen:

| # | Señal | Medida concreta |
|---|--------|-----------------|
| A | P0 seguridad mínima | `/modulos-sin-auth` eliminado o bajo `auth`+`userCan`; empleados bajo auth |
| B | Una verdad Urd/Eng status | `ProgramarEngomadoController::actualizarStatus` (y urdido) delegan a `ProgramBoardActionService` **o** default UI = Livewire y POST legacy 410 |
| C | Vault listo | Esta matriz + `inventario-bugs` + mapa AuthZ + al menos notas Planeación / UrdEng / Mantto en cerebro |
| D | Cola ≥3 workstreams | Tres PRs/tareas **en módulos distintos** sin compartir el mismo fat controller (ej. AuthZ middleware routes ≠ Engomado status ≠ L.Mat backend 100%) |
| E | Canal con protocolo | Nombre fijo + lead towellin + “un owner por archivo” |

Señal de volumen adicional (cualquiera refuerza GO):  
- ≥5 bugs P1 en **áreas distintas** activos la misma semana;  
- Franco pide en paralelo piso Crudo + tablero UrdEng + paros;  
- Critiquito/Atrix ya generan ruido útil y hace falta partición.

---

## Set MÍNIMO si GO (cuando se cumplan umbrales)

| Rol | Agente | Scope | No tocar |
|-----|--------|-------|----------|
| **Lead** | **towellin** | Prioridad, vault, merges, veto Livewire/Liberar | — |
| Especialista | **UrdEng** | Tablero status, freeze legacy, tests ProgramBoard | LiberarOrdenes, OEE Atadores |
| Especialista | **Planea** | AuthZ routes planeación, L.Mat %100 backend, CreaProd no-touch | Reescritura Livewire liberar |
| Especialista | **Mantto/Piso** (uno solo al inicio) | Paros AuthZ + quitar userId=6 **o** Crudo/Trazabilidad shell — no ambos | Dual MecActividades sin unificar |
| QA transversal | **QA** (Critiquito/Nova reuse) | Feature tests mutaciones; vetar StructureTest-as-green | Refactors cosméticos |

**Canal:** `#towell-agents` (o `#towellin-ops`) — crear **solo** al activar ≥2 especialistas.  
**Fuera del mínimo v1:** bots “Tejido”, “Atadores”, “Config”, “Integraciones”, “Design”, “Docs-only”.

---

## Qué DEBE estar en vault antes de partir

1. [[Arquitectura/livewire-cuando-si-cuando-no]] — esta matriz.  
2. [[Auditoria/inventario-bugs]] — 28+ items vivos.  
3. [[Auditoria/authz-mapa-gaps]] — menú vs rutas.  
4. [[Auditoria/js-y-frontend-deuda]] — fetch/Swal dual stack.  
5. Notas de módulo (mínimo): Planeación, Urdido/Engomado, Mantenimiento — en `docs/cerebro-towell` / vault.  
6. [[Orquestacion/00-Orquestacion]] — stance Livewire + este go/no-go.

---

## Anti-patrones (prohibido)

- **10 bots** “por carpeta de Controllers”.  
- **Dos CloudAgents** en `LiberarOrdenesController` / mismo Blade 3k LOC.  
- **Notion otra vez** como source of truth (vault + git docs).  
- Especialista Livewire en Planeación “porque está de moda”.  
- Canal sin lead: mensajes huérfanos sin owner de archivo.  
- Inventar bugs en inventario sin path/símbolo.

---

## Si Franco fuerza GO mañana

Usar **solo** lead towellin + **UrdEng** + **QA**, canal `#towell-agents`, scope cerrado: “delegar status Engomado al service + redirect default”. Nada de Liberar Livewire. Seguir siendo **WAIT** para el resto hasta umbrales A–C.
