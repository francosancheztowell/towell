# Fase 20 — Arquitectura y seguridad

**Track:** ARQ/SEC · **Ola:** 20-01..03 en Ola 2; SEC-06/07 y ARQ-05 dentro de cada 19-xx · **IDs:** ARQ-01..05, SEC-04..07 · **Ramas:** `claude/20-01-arq`, `claude/20-02-errores`, `claude/20-03-authz-auditar`
**Antes de ejecutar:** escribir el PLAN de cada subfase.

## 20-01 — Movimientos y deduplicación (se mergea primero en Ola 2)
- ARQ-01 PR de **movimientos puros**: `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/*Service.php` → `app/Services/Tejedores/Desarrolladores/` (actualizar namespaces, imports y bindings en `AppServiceProvider`). `Planeacion/ProgramaTejido/{funciones,helper}` **no** (son de PT).
- ARQ-02 Folios: 7 reimplementaciones (4 controllers, 2 services, `TurnoHelper`) → `app/Helpers/FolioHelper.php` (`obtenerSiguienteFolio()` al confirmar, `obtenerFolioSugerido()` para UI). `obtenerFolioUrdEng()` duplicado en `CrearOrdenesService.php:199` y `CrearOrdenKarlMayerController.php:190`.
- ARQ-03 `getTurnoInfo()` duplicado (CortesEficienciaController, NuevoRequerimientoController) → `TurnoHelper`.
- ARQ-04 `UrdEngNucleos` en `app/Models/UrdEngomado/` y `app/Models/urdengomado/` (BUG-022): dejar uno.

## 20-02 — Errores JSON
- SEC-04 Render central en `bootstrap/app.php` para JSON 5xx: mensaje genérico en español + `trace_id` = Id del evento de `SYSMonErrorEvento` (lo expone `ErrorRecorder`, fase 11). Reusar `app/Support/Http/Concerns/HandlesApiErrors.php`.
- SEC-07 (en 19-xx): reemplazar ~401 `catch → $e->getMessage()` por dejar burbujear o `HandlesApiErrors` (ratchet `getMessage()` baja).

## 20-03 — AuthZ en modo auditar
- SEC-05 `EnsureModulePermission` (`module.permission:<accion>,<idrol>`) acepta modo `auditar` (`module.permission:crear,123,auditar`): si el usuario no tendría permiso, registra `SYSMonAcceso` tipo `authz_denegaria` y **deja pasar**.
- Aplicar modo auditar a las ~106 rutas de escritura sin permiso (tejido 26, urdido 19, engomado 18, tejedores 11, mecánicos 10, …; Planeación la hace PT 01.1) con el mapa acción ↔ idrol por módulo documentado en `20-03-MAPA-AUTHZ.md`.
- SEC-06 (en 19-xx): tras 2 semanas de datos sin falsos positivos en `/admin/accesos`, pasar a enforce con tests estilo `tests/Feature/PlaneacionMutationAuthorizationTest.php`. **Excepción aprobada:** alta de paros abierta a cualquier usuario autenticado.
- ARQ-05 (en 19-xx): Form Requests en las mutaciones que se toquen.

## Criterios de éxito
- Ningún usuario legítimo bloqueado (evidencia: 0 `authz_denegaria` inesperados antes de enforce).
- `security-review` en cada subfase.

## Skills
`security-review`, `laravel-specialist`, `php-pro`, `code-review`.
