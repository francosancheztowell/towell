# Receta: componentes de UI (Towell)

**Fase 16 (DS-01..12) · 2026-09-25.** Galería viva: `/dev/ui-kit` (solo con `APP_ENV=local`).
Código: `resources/views/components/{ui,empty}/`, runtime `resources/js/componentes/`, tokens en `resources/css/app.css` (`@theme`).

> Regla de oro: **si existe el componente, se usa el componente.** Nada de overlays de modal a mano, `thead` con clases nuevas o `bg-blue-600 hover:bg-blue-700` sueltos. Si al componente le falta algo, se evoluciona (con API compatible) en vez de crear otro.

---

## 1. Reglas de diseño (tokens)

| Regla | Cómo |
|---|---|
| Texto mínimo **12 px** | `text-caption` (= `text-xs`). Prohibido `text-[10px]`, `text-[11px]`. |
| Objetivo táctil **≥ 44 px** | `min-h-touch` / `size-touch` en todo control nuevo (la planta usa tablets). |
| Color con significado | `primary` (azul de los botones), `accent` (morado de editar), `success`, `warning`, `danger`, `info`, `surface`, `surface-muted`, `line`, `ink`, `ink-muted`. Ej.: `bg-primary hover:bg-primary-hover`, `text-ink-muted`, `border-line`. |
| Foco visible | `focus-visible:ring-2 focus-visible:ring-primary` (los componentes ya lo traen). |
| Movimiento | Animaciones solo con `motion-safe:` o dentro de `prefers-reduced-motion`. |

Los tokens mapean a la paleta que la app ya usaba: adoptarlos no cambia el aspecto.

## 2. ¿Qué uso?

| Necesito… | Componente | Notas |
|---|---|---|
| Un botón | `x-ui.button` | Planos: `create`, `edit`, `delete`, `report`, `neutral`, `ghost`; tamaños `touch` (default), `nav`, `icon` (con `label`). Con `href` pinta `<a>`. Los de degradado (`primary`…`secondary`, `sm/md/lg`) quedan por compatibilidad. |
| Botones del navbar con permisos | `x-navbar.button-create/edit/delete/report` | Siguen igual; verifican `userCan` con `module`/`moduleId`. |
| Un modal | `x-ui.modal-base` | `<dialog>`. Ver §3. |
| Una tabla estática o de JS | `x-ui.table` + `x-ui.table-empty` | `variant="primary"` (encabezado azul) o `subtle` (gris). |
| Un CRUD con búsqueda/orden/paginado | Livewire + `ConTabla` + `x-tabla` | `x-tabla` ya compone `x-ui.table`. Ver `livewire-cuando-si-cuando-no.md`. |
| Buscar/filtrar una tabla | `x-ui.filter-bar` | Cliente (`target="#tabla"`) o Livewire (`model="buscar"`). |
| Un campo de formulario | `x-ui.field` | `as="text|number|textarea|select…"` o slot con control propio. |
| Una etiqueta de estado | `x-ui.badge` | `tone` + `icon` o `dot`. |
| “No hay nada” | `x-empty.empty-state` (bloque) / `x-ui.table-empty` (fila) | Acepta un botón de acción en el slot. |
| Esperar | `x-ui.spinner` (local), `x-ui.skeleton` (contenido), `window.loader.show()/hide()` (pantalla completa) | No crear overlays de carga propios. |
| Avisar tras un redirect | nada: `redirect()->with('error', '…')` | El layout monta `x-ui.flash`. |
| Avisar desde JS | `notify.success/error/…` (toasts) | `resources/js/utils/notifications.ts`. |
| Un aviso fijo en la página | `x-ui.alert` | Se cierra sin JS. |

## 3. Modales (`x-ui.modal-base`)

```blade
<x-ui.button variant="create" icon="fa-plus" data-ui-modal-open="modalMaquina">Nueva</x-ui.button>

<x-ui.modal-base id="modalMaquina" title="Nueva máquina" size="md" :close-on-backdrop="false">
    <form id="formMaquina" class="space-y-4">
        <x-ui.field as="text" name="MaquinaId" label="Máquina ID" required />
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="modalMaquina">Cancelar</x-ui.button>
        <x-ui.button variant="create" type="submit" form="formMaquina" icon="fa-floppy-disk">Guardar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
```

- Props: `id`, `title`, `size` (`sm|md|lg|xl`), `onclose` (JS del botón ×), `closeOnBackdrop`, `tone` (`default|danger`), slot `footer`.
- **Abrir/cerrar**: la clase `hidden` es la fuente de verdad (así funciona el JS existente, p. ej. Programa Tejido). Desde JS: `window.uiModal.abrir('id')` / `window.uiModal.cerrar('id')`. Declarativo: `data-ui-modal-open="id"`, `data-ui-modal-close-target="id"`.
- El runtime (`resources/js/componentes/dialog.ts`) sincroniza `open`, lleva el foco al primer control, lo atrapa con Tab, lo devuelve al cerrar y hace que Esc ejecute el `onclose`.
- Es un `<dialog>` **no modal** (sin `showModal()`) a propósito: con el top layer, SweetAlert2 y los toasts quedarían debajo y sin poder usarse. Revisar en ADOP cuando notify/Swal vivan en el top layer.

### Migrar un modal hecho a mano

Antes (patrón repetido 77 veces):
```blade
<div id="formModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
    <div class="flex justify-between items-center border-b p-4"><h2>Título</h2><button onclick="closeFormModal()">×</button></div>
    …
```
Después:
```blade
<x-ui.modal-base id="formModal" title="Título" size="lg">…</x-ui.modal-base>
```
1. Conserva el `id`: el JS que hace `classList.remove('hidden')` sigue funcionando. Si alternaba `flex`/`hidden`, basta con `hidden`.
2. Borra `closeFormModal()` y los listeners de Esc/clic fuera: los da el componente (`onclose` solo si hay que limpiar estado).
3. Los botones del pie van al slot `footer` como `x-ui.button`.
4. `bg-opacity-*` no existe en Tailwind v4: el modal a mano salía con **fondo negro sólido**. Migrarlo lo arregla.

## 4. Tablas (`x-ui.table`)

```blade
<x-ui.table variant="subtle" id="tablaMaquinas" :zebra="true">
    <x-slot:head><tr><th scope="col">Máquina</th><th scope="col">Estado</th></tr></x-slot:head>
    @forelse ($maquinas as $m)
        <tr data-filter-row data-estado="{{ $m->Estado }}">
            <td class="font-medium">{{ $m->MaquinaId }}</td>
            <td><x-ui.badge tone="success" dot>{{ $m->Estado }}</x-ui.badge></td>
        </tr>
    @empty
        <x-ui.table-empty :colspan="2" message="No hay máquinas registradas" icon="fa-gears" />
    @endforelse
</x-ui.table>
```
- `th`/`td` toman padding y tipografía del variant con especificidad baja: cualquier clase en la celda gana.
- `:loading="true" :columns="N"` pinta filas skeleton.
- Si necesitas atributos en el `<tbody>` (Alpine), pon tu propio `<tbody …>` en el slot: se respeta tal cual.
- Selección de fila: `bg-blue-100 shadow-[inset_4px_0_0_0_#3b82f6]` + `aria-selected` (la de `x-tabla`).

### Migrar una tabla hecha a mano
1. `<table class="…">` + `<thead class="bg-…">` → `<x-ui.table variant="…">` + `<x-slot:head>`.
2. Borra las clases repetidas de cada `th`/`td` (`px-6 py-3 text-xs uppercase…`): las pone el variant.
3. El `@empty` con `<tr><td colspan>` → `x-ui.table-empty`.
4. Si filtrabas con un `applyFilters` propio: `x-ui.filter-bar target="#id"` + `data-filter-row` en las filas.

## 5. Formularios (`x-ui.field`)

```blade
<x-ui.field as="number" name="Porcentaje" label="Porcentaje" required hint="De 0 a 100" min="0" max="100" step="0.01" />
<x-ui.field name="Turno" label="Turno" as="select"><option value="1">1</option></x-ui.field>
<x-ui.field name="Obs" label="Observaciones"><textarea id="Obs" name="Obs" class="…"></textarea></x-ui.field>
```
El error sale de `$errors->first($name)` o de la prop `error`. Desde JS (respuesta 422 de `http`), escribe el mensaje en `[data-ui-field-error]` del campo y quita `hidden`.

## 6. Carga

- `window.loader.show(300)` / `window.loader.hide()` para operaciones que bloquean la pantalla (300 ms de espera evitan el parpadeo). La navegación ya lo usa (`app-core.js`).
- `animate-spin` (Tailwind) y `fa-spin` (Font Awesome) funcionan en todos lados: se quitaron el `@keyframes spin` del loader (los desplazaba) y el `.fa-spin` redefinido en el layout.

## 7. Qué NO hacer

- `onclick=` inline, `<script>` inline grandes, `fetch(...).then(r => r.json())`, `Swal.fire` para un simple aviso: el ratchet (`npm run ratchet`) no deja que suban. Usa `data-*` + módulo TS, `window.http`, `notify`.
- Otro componente “parecido” en vez de evolucionar el existente.
- Texto < 12 px, controles < 44 px en pantallas de planta.
- `bg-opacity-*`, `text-opacity-*` (Tailwind v3): usa `bg-black/40`.

## 8. JS de una pantalla (patrón del piloto)

Piloto DS-12: `modulos/catalogos-atadores/index.blade.php` (una vista para Actividades, Comentarios y Máquinas).
- La configuración vive en PHP (`CatalogosAtadoresVista`) y llega al JS por `data-catalogo='@json(...)'`.
- El módulo `resources/js/modulos/<modulo>/index.ts` se carga con `@vite` desde la vista (entrada de Vite por glob `resources/js/modulos/**/index.ts`).
- CRUD de catálogo sobre REST: `resources/js/catalogos/catalog-base.ts` (evolución de `public/js/catalogs/CatalogBase.js`, que queda para los 4 catálogos de Planeación hasta su 19-xx).
