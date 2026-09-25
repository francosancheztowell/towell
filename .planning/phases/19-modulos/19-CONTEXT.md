# Fase 19 — Módulos: JS inline → TS (+ Livewire donde aplica)

**Track:** MIG · **Ola:** 3 · **IDs:** MIG-<MOD>-01..04 (+ PERF-08..11, SEC-06/07, UX-18 del módulo) · **Ramas:** `claude/19-xx-<modulo>`
**Depende de:** G2 (utils TS, Tom Select, Vite por glob, componentes DS, AuthZ en modo auditar, auditoría UX).
**Antes de ejecutar:** cada sesión escribe su `19-XX-PLAN.md`; `19-00-RECETA.md` lo escribe la primera sesión de la ola y las demás lo siguen.

## Situación
~49k líneas de JS inline en 135 blades (57 % de las líneas Blade); 386 `onclick=`, 124 `window.X = function`, 301 `innerHTML =`, 225 `@json`. Top: `engomado/captura-formula/index` (2 827), `engomado/modulo-produccion-engomado` (2 561), `catalagos/catalogoCodificacion` (2 322), `urdido/produccion/_scripts` (2 256), `atadores/calificar-atadores` (1 551), `programa_urd_eng/programacion-requerimientos` (1 358).

## Receta (una pantalla a la vez)
1. Mover el `<script>` a `resources/js/modulos/<mod>/<pantalla>/index.ts` (Vite lo toma por glob). Si es enorme: primero `.js` tal cual, luego tipar función por función.
2. Datos del servidor por `<script type="application/json" id="datos-pagina">@json($datos)</script>` + `JSON.parse`.
3. `onclick="fn()"` → `data-accion="fn"` + `delegate()` de `utils/dom`.
4. `fetch` → `http`; `Swal`/toasts → `notify`; `escapeHtml`/`debounce` → `utils/format`; modales/tablas/filtros → componentes DS.
5. Puente `window.fn = fn` temporal solo si otro archivo lo llama (anotar en SUMMARY para ADOP).
6. Tests node de la lógica pura (cálculos, formatos, validaciones).
7. En la misma sesión: N+1/queries del módulo (PERF-08..11), AuthZ enforce tras 2 semanas en auditar (SEC-06) con tests, quitar `catch → getMessage()` (SEC-07), checklist UX (UX-18).
8. Ratchet baja; capturas antes/después en tablet; 7 días sin subida de errores/p95 en `/admin`.

## Prioridad
Por telemetría: vistas/semana × KB de JS inline × errores (consulta en `/admin/rendimiento` + `/admin/errores`). Orden tentativo:

| Plan | Módulo | Alcance |
|---|---|---|
| 19-01 | Urdido + Engomado | captura-formula, modulo-produccion-engomado, urdido/produccion/_scripts; dedupe BPM (incl. tel-bpm), BPM-Line, calificar-julios (`modal-calificar-julios` vs `-eng`) |
| 19-02 | Tejido | 4 `tejido/secuencia/*` → 1 vista parametrizada; cortes-eficiencia (pdf.js ya por npm); 26 rutas de escritura sin permiso |
| 19-03 | Atadores | calificar-atadores, programa atadores (hoy re-descarga el HTML completo cada 5 s aunque la pestaña esté oculta → Livewire `wire:poll.visible` o fetch JSON), N+1 `exists()` |
| 19-04 | Tejedores / Desarrolladores | actividades-bpm, tel-telares-operador, N+1 `MovimientoDesarrolladorService` |
| 19-05 | Programa Urd-Eng | programacion-requerimientos, `public/js/modulos/programa_urd_eng/creacion-ordenes.js` (1 460) a Vite (BUG-033), karl-mayer |
| 19-06 | Codificación | Dos pantallas distintas que se quedan (owner 2026-09-25): catálogos (`CodificacionController`, `ReqModelosCodificados`, `catalagos/catalogoCodificacion`) y codificación (`CatCodificacionController`, `CatCodificados`, `catcodificacion/`); migrar ambas + `lmat-lista` |
| 19-07 | Mecánicos | reportes (html2canvas por npm), OT (1 592 LOC controller), BUG-021 |
| 19-08 | Mantenimiento | catálogos a Livewire (`ConTabla`); paros tras AuthZ + quitar `userId === 6` (BUG-025); alta de paros sigue abierta a todos |
| 19-09 | Configuración / Usuarios | usuarios (permisos en botones, alta sin descargar página), mensajes, gestión de módulos |
| 19-10 | Trazabilidad / Crudo / Ventas | ya TS/Livewire: solo adopción de utils/componentes |

## Livewire
Solo donde `docs/cerebro-towell/Arquitectura/livewire-cuando-si-cuando-no.md` dice sí (tableros, piso/andón, catálogos de Mantenimiento, verificación de máquina) — Programa Tejido va por su track (decisión 2026-09-24). **No** Liberar, Excel one-shot ni dumps de reportes.

## Propiedad
Vertical por módulo (ver `../../PROTOCOLO-SESIONES.md` §5): la sesión es dueña de `routes/modules/<mod>.php`, controllers/services/livewire/views/js/tests del módulo. Utils y componentes: solo lectura (HANDOFF).
