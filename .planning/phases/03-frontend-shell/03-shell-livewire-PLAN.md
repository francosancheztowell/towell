---
phase: 03-frontend-shell
plan: "03"
version: 2
type: execute
wave: 1
depends_on: ["02", "04-perf", "05"]
autonomous: false
requirements: [PT-UI-01, PT-ROL-01]
aprobado: 2026-09-29 (owner, sesión claude/pt-03-shell-livewire)
files_modified:
  - app/Livewire/Planeacion/ProgramaTejidoBoard.php
  - app/Services/Planeacion/ProgramaTejido/ShellV2.php
  - app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadService.php
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php
  - config/planeacion.php
  - resources/views/livewire/planeacion/programa-tejido-board.blade.php
  - resources/views/modulos/programa-tejido/req-programa-tejido.blade.php
  - resources/views/modulos/programa-tejido/req-programa-tejido-v2.blade.php
  - resources/views/modulos/programa-tejido/partials/*.blade.php
  - resources/js/modulos/programa-tejido-v2/index.ts
  - tests/Feature/Planeacion/ProgramaTejidoShellV2Test.php
  - tests/Unit/Planeacion/ProgramaTejidoShellV2EstructuraTest.php
---

# PT 03 — Shell Livewire · v2

La v1 (abajo, como historial) se escribió antes de PT 02, PT 04-perf, PT 05 y 17-02. Esta sección
**manda** sobre la v1 donde difieran.

## Cambios v1 → v2

| Tema | v1 | v2 (lo que se ejecuta) |
|---|---|---|
| Superficie | `app/Support/Planeacion/ProgramaTejidoSurface` (planeado) | Existe: enum `App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface` (PT-02, D-4). El componente recibe `superficie` en `mount()` y lo guarda `#[Locked]`; nunca `::actual()` ni `request()->is()` dentro del componente. |
| Tabla en `/livewire/update` | — | El middleware `ProgramaTejidoContext` no corre ahí (config caería a Programa): el componente consulta con `setTable($superficie->tabla())` explícito. |
| Lectura | `ProgramaTejidoReadService::paginate()` | La grilla legacy no pagina (85 filas; 04-PERF-MEDIDO: paginar no es el problema). La lista del SELECT de `index()` pasa a `ProgramaTejidoReadService::COLUMNAS_GRILLA` (movimiento puro) y la usan legacy y v2. |
| UI | Tabla mínima nueva + buscador | **Mismo diseño (D-E):** la grilla se extrae a partials que incluyen legacy y v2; en v2 va dentro de `wire:ignore` y la sigue manejando el mismo bundle `programa-tejido/index.js`. Sin buscador ni UI nueva. |
| Assets | `vite.config.js` + `resources/js/planeacion/*` + CSS nuevo | `vite.config.js` congelado en Ola 3: entrada `resources/js/modulos/programa-tejido-v2/index.ts` (glob). Sin CSS nuevo (mismo `public/css/programa-tejido/*`). |
| Canary | Allowlist por `numero_empleado` | Mismo idiom que PT 05 (`MutacionesV2::activa`): `PLANEACION_SHELL_V2=off|canary|on` + `PLANEACION_SHELL_V2_CANARY=<Id>,<Id>`. Default `off`. |
| Flag apagado | Cero assets v2 | Además: respuesta **byte a byte** igual a la legacy (diff real antes/después). |
| 04-perf / 17-02 | — | La página ya tiene 0 `<script>` inline de PT y `#pt-boot` JSON; títulos por `page-title`, zoom por default, `sesion.ts` ya cubre el 419 de Livewire. No se reintroduce nada inline. |
| GATE | Checkpoint humano | Además: TTFB, KB de HTML (gzip) y tiempo de interacción v2 vs legacy con el método de `04-PERF-MEDIDO.md`. **Si v2 no mejora, se documenta con números, queda apagado y 04-ux no sigue sobre Livewire.** |

## Tareas v2

1. **03.1 Componente** `App\Livewire\Planeacion\ProgramaTejidoBoard`: `mount(string $superficie)`, `#[Computed] registros()` (nunca propiedad pública), error → `report()` + estado de error legacy.
2. **03.2 Mismo diseño:** `partials/grilla.blade.php` y `partials/complementos.blade.php` extraídos de `req-programa-tejido.blade.php` (legacy byte a byte igual); vista Livewire con la grilla en `wire:ignore`; wrapper `req-programa-tejido-v2.blade.php` (< 40 líneas) con `@vite` de v2 solo ahí; `programa-tejido-v2/index.ts` mínimo.
3. **03.3 Canary:** `ShellV2::activo()`; `ProgramaTejidoController::index()` bifurca antes de consultar; rutas sin cambios.
4. **03.4 Tests:** flag apagado → legacy sin rastro de v2/Livewire; canary → v2 con la superficie correcta; Muestras sigue en Muestras tras `$refresh`; dataset fuera del snapshot; superficie bloqueada; estructura del wrapper; paridad de la tabla v2 vs legacy.
5. **03.5 GATE:** arnés `03-frontend-shell/arnes/` (sobre el de 19-01) + `medir-shell.mjs`; tabla en `03-SUMMARY.md`; capturas lado a lado 1280×800 y 768×1024; runbook para Laragon.
6. **HANDOFF 17-02 B1** (dias-liberar sin `onclick`), **B2** (menús contextuales → `accionesTactiles` + un botón "⋮"), **PT-05 B4** (Redbooth a `resources/js/modulos/redbooth/index.ts`): commits aparte.

Prohibido: `vite.config.js`, `package.json`, `resources/js/{utils,componentes}/**`, módulos 19-xx, `bootstrap/**`, `config/database.php`.

---

# v1 (historial, 2026-08)


<objective>
Reemplazar el fetch/DOM a mano de Programa Tejido y Muestras (`req-programa-tejido.blade.php` + `filter-engine.js`) por un shell Livewire, siguiendo exactamente los patrones ya usados en este mismo codebase (`Crudo\MachineDetail` para `#[Computed]`/DI/cache-fallback, `UrdEng\ProgramBoard` para shape de componente/`#[Url]`/eventos), activable solo para usuarios en una allowlist canary con rollback inmediato a Blade legacy.

Purpose: Dar a Programa Tejido una UI v2 de bajo riesgo (legacy intacto, wrapper delgado, componente aislado) que resuelve Programa vs Muestras de forma explícita — el research de esta fase identificó que `ProgramaTejidoContext` (sniffing de URL) se rompe en cuanto Livewire empieza a mandar acciones a `/livewire/update`, así que este plan nunca repite ese patrón dentro del componente.

Output: `App\Livewire\Planeacion\ProgramaTejidoBoard` + vista + wrapper delgados para Programa y Muestras + canary config-driven + tests estructurales/feature que prueban que el flag apagado no carga nada de v2.

**Dependencia dura de Fase 2:** Este plan asume que Fase 2 (`02-containment-read`) ya aterrizó `App\Support\Planeacion\ProgramaTejidoSurface`, `App\Support\Planeacion\ProgramaTejidoContextResolver`, `App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService` y `App\Http\Resources\Planeacion\ProgramaTejidoRowResource` (ver `02-containment-read-PLAN.md`). Esos archivos NO existen todavía en el árbol al momento de escribir este plan (`config/planeacion.php` tampoco existe aún). Los nombres de método exactos abajo son el contrato *planeado* de Fase 2, no código verificado — el primer paso de ejecución de este plan (Task 03.1) DEBE releer esos archivos reales antes de escribir una sola línea y ajustar las llamadas si el nombre/firma real difiere. No reinventar resolución de superficie ni lectura aquí si Fase 2 ya la resolvió.
</objective>

<execution_context>
@C:/Users/fsanchez/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/fsanchez/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/REQUIREMENTS.md
@.planning/phases/03-frontend-shell/03-RESEARCH.md
@.planning/phases/02-containment-read/02-containment-read-PLAN.md
@app/Livewire/Crudo/MachineDetail.php
@app/Livewire/UrdEng/ProgramBoard.php
@app/Support/Programas/ProgramaModulo.php
@app/Http/Middleware/ProgramaTejidoContext.php
@app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php
@app/Http/Controllers/Urdido/ProgramaUrdido/ProgramarUrdidoController.php
@resources/views/modulos/urdido/programar-urdido-livewire.blade.php
@resources/js/urd-eng/feedback.ts
@tests/Unit/Programas/ProgramBoardStructureTest.php

<interfaces>
<!-- Contrato PLANEADO de Fase 2 (02-containment-read-PLAN.md) — no verificado en código todavía. -->
<!-- Task 03.1 debe releer los archivos reales de Fase 2 antes de asumir estas firmas. -->

De `app/Support/Planeacion/ProgramaTejidoSurface.php` (planeado, Fase 2 task 02.1):
```php
// Enum/value object que resuelve superficie (Programa|Muestras), tabla, tabla de líneas,
// route manifest, namespace de preferencias y capacidades — reemplaza el sniffing de URL.
enum ProgramaTejidoSurface // nombre exacto a confirmar
{
    case Programa;
    case Muestras;

    public static function resolve(string $value): self; // 'programa'|'muestras'
    public function table(): string;         // ReqProgramaTejido | MuestrasPrograma
    public function lineTable(): string;     // ReqProgramaTejidoLine | MuestrasProgramaLine
    public function routeName(string $action): string; // ej. 'programa-tejido.prioridad.mover' | 'muestras.prioridad.mover'
}
```

De `app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadService.php` (planeado, Fase 2 task 02.3):
```php
// Read-only, paginado, respeta allowlist de columnas por superficie. Nunca dispara observers.
public function paginate(ProgramaTejidoSurface $surface, IndexProgramaTejidoRequest $request): LengthAwarePaginator;
```

De `app/Http/Middleware/ProgramaTejidoContext.php` (ACTUAL, verificado — el patrón que este plan NO debe repetir dentro de Livewire):
```php
private function isMuestrasRequest(Request $request): bool
{
    return $request->is('planeacion/muestras*') || $request->is('planeacion/muestras-line*') || $request->is('muestras*');
}
```
Este middleware sigue vigente para el request inicial (renderiza el wrapper correcto), pero el componente Livewire NUNCA debe volver a llamar a `request()->is(...)` para decidir superficie — debe recibir `surface` explícito en `mount()`.

De `app/Livewire/UrdEng/ProgramBoard.php` (ACTUAL, patrón a copiar 1:1 para module/surface prop):
```php
#[Locked]
public string $module = ProgramaModulo::Urdido->value;

public function boot(ProgramBoardReadService $readService, ProgramBoardActionService $actionService): void { ... }
public function mount(string $module): void {
    $resolved = ProgramaModulo::resolve($module);
    $this->module = $resolved->value;
    ...
}
private function moduleEnum(): ProgramaModulo { return ProgramaModulo::resolve($this->module); }
```

De `app/Livewire/Crudo/MachineDetail.php` (ACTUAL, patrón `#[Computed]` a copiar 1:1):
```php
#[Computed]
public function detail(): ?array
{
    if ($this->selectedTelar === null || ... || ! $this->detailLoaded) {
        return null;
    }
    try {
        $detail = $this->provider->detail(...);
        Cache::put($fallbackKey, $detail, now()->addSeconds(...));
        return $detail;
    } catch (Throwable $exception) {
        report($exception);
        $this->detailError = '...';
        $cached = Cache::get($fallbackKey);
        return is_array($cached) ? $cached : null;
    }
}
```
</interfaces>
</context>

<tasks>

<task type="auto">
<name>Task 03.1: Contrato del componente ProgramaTejidoBoard</name>
<files>app/Livewire/Planeacion/ProgramaTejidoBoard.php, resources/views/livewire/planeacion/programa-tejido-board.blade.php</files>
<action>
Antes de escribir código: releer `app/Support/Planeacion/ProgramaTejidoSurface.php`, `ProgramaTejidoContextResolver.php` y `app/Services/Planeacion/ProgramaTejido/ProgramaTejidoReadService.php` reales (si Fase 2 ya ejecutó) y ajustar nombres/firmas si difieren del contrato planeado arriba. Si esos archivos aún no existen, detener este task y registrar el bloqueo (Fase 2 no ha aterrizado) en vez de reimplementar resolución de superficie o lectura por cuenta propia.

Crear `App\Livewire\Planeacion\ProgramaTejidoBoard` siguiendo `UrdEng\ProgramBoard`:
- Sin constructor; DI de `ProgramaTejidoReadService` (y lo que Fase 2 exponga) en `boot()`.
- `mount(string $surface)`: resuelve `ProgramaTejidoSurface::resolve($surface)` (o el helper equivalente de Fase 2) y lo guarda en una propiedad `#[Locked] public string $surface`. NO usar `request()->is(...)` en ningún punto del componente — esa es la trampa de `ProgramaTejidoContext` que rompe en `/livewire/update` (ver Pitfall 1 de `03-RESEARCH.md`).
- `#[Url]` para `search`, `status`/filtro simple y `page` (mismo patrón que `search`/`status` de `ProgramBoard`), normalizados en `updatedSearch()`/`mount()`.
- `#[Computed] public function rows(): LengthAwarePaginator|array` que llama al read service de Fase 2 pasando la superficie resuelta — nunca query directa a `ReqProgramaTejido`/`MuestrasPrograma`. Envolver en try/catch como `MachineDetail::detail()`: en error, `report($exception)`, setear `$this->dataError`, devolver colección vacía (no cache-fallback todavía, eso es Fase 4/lectura pesada — un catch simple basta aquí).
- Sin propiedad pública para las filas — solo el método `#[Computed]`.
- `render()` retorna `view('livewire.planeacion.programa-tejido-board', [...])`.

Crear `resources/views/livewire/planeacion/programa-tejido-board.blade.php`: contenedor con encabezado, buscador, y tres estados explícitos (loading vía `wire:loading`, error si `$dataError` no es null, empty si `rows` está vacío) y una tabla mínima con las columnas ya seleccionadas por `ProgramaTejidoController::index()` hoy (Id, Folio/NoProduccion, SalonTejidoId, NoTelarId, Posicion, Status/EnProceso). Sin `<script>` inline, sin `@script`. No portar el partial legacy de 497 líneas ni su JS de context-menu (Pitfall 5 del research) — construir markup nuevo mínimo, la tabla completa/paginación es alcance de Fase 4.
</action>
<verify>
<automated>php artisan tinker --execute="echo class_exists('App\\Livewire\\Planeacion\\ProgramaTejidoBoard') ? 'OK' : 'MISSING';"</automated>
</verify>
<done>El componente existe, resuelve superficie explícitamente en mount(), expone filas solo vía #[Computed], y la vista renderiza sin script inline.</done>
</task>

<task type="auto">
<name>Task 03.2: Glue JS/CSS aislado (feedback + entry Vite)</name>
<files>resources/js/planeacion/feedback.ts, resources/js/planeacion/program-board.ts, resources/css/planeacion/programa-tejido.css, vite.config.js</files>
<action>
Copiar el patrón de `resources/js/urd-eng/feedback.ts` verbatim (Swal-first, fallback a `window.notify`, listeners de `program-board-notify`/`program-board-modal` renombrados a `programa-tejido-notify`/`programa-tejido-modal` para no colisionar con UrdEng) a `resources/js/planeacion/feedback.ts`. `program-board.ts` es el único entry Vite: importa `feedback.ts` y llama `initializeFeedback()` en `DOMContentLoaded`, igual que hace el entry de `program-board.ts` de UrdEng — no crear un segundo bundler config.

Editar `vite.config.js`: el array `input` de `laravel-vite-plugin` es estático y explícito (sin glob), así que hay que agregar ahí, sin condición, las dos rutas nuevas siguiendo exactamente el mismo patrón que las entradas `urd-eng` ya existentes (`resources/css/urd-eng/program-board.css` / `resources/js/urd-eng/program-board.ts`):
```js
input: [
  'resources/css/app.css',
  'resources/js/app.js',
  'resources/js/app-core.js',
  'resources/js/app-filters.js',
  'resources/css/trazabilidad/index.css',
  'resources/js/trazabilidad/index.ts',
  'resources/css/crudo/dashboard.css',
  'resources/js/crudo/dashboard.ts',
  'resources/css/urd-eng/program-board.css',
  'resources/js/urd-eng/program-board.ts',
  'resources/css/planeacion/programa-tejido.css',
  'resources/js/planeacion/program-board.ts',
  'resources/js/catcodificacion/index.js',
  'resources/js/lmat-lista/index.js',
],
```
Sin esta entrada, `@vite('resources/css/planeacion/programa-tejido.css')`/`@vite('resources/js/planeacion/program-board.ts')` en los wrappers de Task 03.3 lanzan "Unable to locate file in Vite manifest" en runtime y `npm run build` no genera chunk separado — el `<done>` de este task depende de este edit.

`resources/css/planeacion/programa-tejido.css`: namespacear todos los selectores bajo una clase raíz (ej. `.programa-tejido-board`), sin selectores globales ni `!important`, mismo criterio que `resources/css/urd-eng/program-board.css`.

En el componente `dispatch()` los eventos `programa-tejido-notify`/`programa-tejido-modal` desde `ProgramaTejidoBoard` cuando aplique (por ahora, al menos un `dispatch` de error en el catch de `rows()`).
</action>
<verify>
<automated>npm run build</automated>
</verify>
<done>Build genera un chunk separado para `program-board.ts`/`programa-tejido.css`; ningún selector fuera del namespace `.programa-tejido-board`.</done>
</task>

<task type="auto">
<name>Task 03.3: Canary allowlist + wiring de ruta + wrappers delgados</name>
<files>config/planeacion.php, app/Support/Planeacion/ProgramaTejidoCanary.php, app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php, resources/views/modulos/programa-tejido/req-programa-tejido-livewire.blade.php, resources/views/modulos/programa-tejido/muestras-livewire.blade.php</files>
<action>
Decisión de canary (per research Open Question 1, resuelta aquí en vez de diferirse — ponytail: sin paquete de flags nuevo): allowlist config-driven por `numero_empleado`, consistente con el idiom `usuarioPuedeEditar()`/`puesto` ya usado en `ProgramarUrdidoController`.

En `config/planeacion.php` (crear si Fase 2 no lo creó todavía, o añadir la clave si ya existe): `'programa_tejido_v2_canary_users' => array_filter(explode(',', (string) env('PLANEACION_PROGRAMA_TEJIDO_V2_CANARY', ''))),`.

Crear `App\Support\Planeacion\ProgramaTejidoCanary` con un único método estático `public static function allows(?Usuario $user): bool` que compara `trim((string) $user?->numero_empleado)` contra la allowlist de config (`in_array` estricto, lista vacía = nadie ve v2 por default). Sin abstracción de "feature flag service" genérica — es una allowlist, nada más.

En `ProgramaTejidoController::index()`: mantener exactamente la lógica actual de `$isMuestras` (línea 33, `request()->is('planeacion/muestras')`) para decidir la superficie del render inicial — eso sigue siendo válido para el primer HTML servido (el middleware ya resuelve bien el primer request, el problema es solo dentro de Livewire). Añadir al inicio: si `ProgramaTejidoCanary::allows(Auth::user())`, retornar `view('modulos.programa-tejido.req-programa-tejido-livewire')` o `view('modulos.programa-tejido.muestras-livewire')` según `$isMuestras`, pasando `'surface' => $isMuestras ? 'muestras' : 'programa'`; si no, continuar con el flujo legacy sin ningún cambio.

Wrappers (`req-programa-tejido-livewire.blade.php`, `muestras-livewire.blade.php`): copiar la forma exacta de `programar-urdido-livewire.blade.php` (<40 líneas, `@extends('layouts.app', ['ocultarBotones' => true])`, `<livewire:planeacion.programa-tejido-board surface="programa|muestras" />`, `@push('styles') @vite('resources/css/planeacion/programa-tejido.css') @endpush`, `@push('scripts') @vite('resources/js/planeacion/program-board.ts') @endpush`). Los `@vite` de v2 viven SOLO en estos dos archivos — no tocar `layouts/app.blade.php` ni la vista legacy (Pitfall 3 del research: flag apagado debe significar cero assets v2).
</action>
<verify>
<automated>php artisan route:list --name=catalogos.req-programa-tejido --name=muestras.index</automated>
</verify>
<done>Rutas `catalogos.req-programa-tejido` y `muestras.index` conservan nombre/URI; un usuario en la allowlist recibe el wrapper Livewire, cualquier otro recibe exactamente la vista legacy sin diferencias.</done>
</task>

<task type="auto" tdd="true">
<name>Task 03.4: Tests estructurales y de canary</name>
<files>tests/Unit/Programas/ProgramaTejidoBoardStructureTest.php, tests/Feature/Planeacion/ProgramaTejidoCanaryTest.php</files>
<behavior>
- Test 1: los wrappers `req-programa-tejido-livewire.blade.php`/`muestras-livewire.blade.php` tienen menos de 40 líneas, contienen `<livewire:planeacion.programa-tejido-board`, el `surface="..."` correcto, los dos `@vite(...)` de v2, y NO contienen `<script>`.
- Test 2: la vista Livewire (`programa-tejido-board.blade.php`) no contiene `<script>` ni `@script`.
- Test 3: la vista legacy `req-programa-tejido.blade.php` sigue existiendo sin modificar (`assertFileExists`) y su HTML NO contiene ninguna referencia a `program-board.ts`/`programa-tejido.css` de v2 (Pitfall 3 — flag apagado, cero assets v2).
- Test 4 (Feature): con un usuario fuera de la allowlist, `GET /planeacion/programa-tejido` y `GET /planeacion/muestras` devuelven la vista legacy (`assertViewIs` o nombre de vista); con un usuario en la allowlist (config seteado en el test), devuelven el wrapper v2 correspondiente con `surface` correcto en los datos de la vista.
</behavior>
<action>Escribir ambos archivos de test mirando `tests/Unit/Programas/ProgramBoardStructureTest.php` como plantilla directa (mismo estilo de aserciones sobre contenido de archivo). El test de canary usa `config(['planeacion.programa_tejido_v2_canary_users' => [...]])` y actúa como usuario autenticado (`actingAs`) con `numero_empleado` fijado.</action>
<verify>
<automated>php artisan test tests/Unit/Programas/ProgramaTejidoBoardStructureTest.php tests/Feature/Planeacion/ProgramaTejidoCanaryTest.php</automated>
</verify>
<done>Los 4 tests pasan; fallan si algún wrapper crece de 40 líneas, si se agrega `<script>` inline, o si el flag apagado sirve assets v2.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>Task 03.5: Verificación canary Programa + Muestras</name>
<what-built>Shell Livewire (`ProgramaTejidoBoard`) activable por allowlist de `numero_empleado`, con rollback inmediato a Blade legacy y sin cambios en las rutas/nombres existentes. Programa y Muestras resuelven su superficie explícitamente en `mount()`, no por sniffing de URL.</what-built>
<how-to-verify>
1. Agregar tu `numero_empleado` a `PLANEACION_PROGRAMA_TEJIDO_V2_CANARY` en `.env`, limpiar config (`php artisan config:clear`).
2. Visitar `/planeacion/programa-tejido`: debe cargar el shell Livewire (no el Blade de 339 líneas). Interactuar (buscar, cambiar filtro) y confirmar que las filas siguen siendo las de `ReqProgramaTejido`, no las de Muestras.
3. Visitar `/planeacion/muestras`: debe cargar el shell Livewire con datos de `MuestrasPrograma`. Repetir una interacción (buscar) y confirmar que las filas siguen siendo de Muestras después de esa interacción — este es el punto exacto donde `ProgramaTejidoContext` fallaría si el surface no se hubiera fijado en `mount()`.
4. Quitar tu `numero_empleado` de la allowlist, `config:clear`, recargar ambas URLs sin limpiar caché de browser: deben volver a mostrar exactamente el Blade legacy.
5. Con el canary apagado, revisar Network tab: no debe aparecer `program-board.ts` ni `programa-tejido.css` de v2 en ninguna de las dos páginas.
</how-to-verify>
<resume-signal>Escribe "approved" o describe qué falló (ej. "Muestras muestra filas de Programa tras buscar" apunta a que mount() no fijó el surface correctamente).</resume-signal>
</task>

</tasks>

<verification>
- `php artisan test tests/Unit/Programas/ProgramaTejidoBoardStructureTest.php tests/Feature/Planeacion/ProgramaTejidoCanaryTest.php` pasa.
- `npm run build` genera `program-board.ts`/`programa-tejido.css` como chunk separado.
- `php artisan route:list` conserva nombres/URIs de `catalogos.req-programa-tejido` y `muestras.index`.
- Con el canary apagado: HTML servido es byte-idéntico al legacy actual (ningún `@vite` de v2 presente).
- Checkpoint 03.5 aprobado por el dueño en ambas superficies (Programa y Muestras), incluyendo el caso de interacción-después-de-mount que rompía el middleware legacy.
</verification>

<success_criteria>
Existe un componente Livewire (`ProgramaTejidoBoard`) que reemplaza el fetch/DOM manual de Programa Tejido/Muestras para los usuarios en la allowlist canary, con datasets grandes solo en `#[Computed]` (nunca propiedad pública), resolviendo Programa vs Muestras explícitamente en `mount()` sin depender del sniffing de URL de `ProgramaTejidoContext`. El flag apagado no carga ningún asset v2 y el rollback es inmediato sin tocar BD ni caché de browser. Las mutaciones pesadas (grid completo, drag/drop, filtros avanzados) quedan fuera — eso es Fase 4.
</success_criteria>

<rollback>
Quitar el `numero_empleado` de `PLANEACION_PROGRAMA_TEJIDO_V2_CANARY` (o vaciar la variable por completo) y `php artisan config:clear`. `ProgramaTejidoController::index()` vuelve a servir siempre la vista legacy; los archivos v2 quedan en el árbol pero sin consumidores activos.
</rollback>

<output>
After completion, create `.planning/phases/03-frontend-shell/03-shell-livewire-SUMMARY.md`
</output>
