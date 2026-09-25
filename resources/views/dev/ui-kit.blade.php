{{--
    Galería de componentes (DS-11). Solo existe con APP_ENV=local (routes/modules/dev.php).
    Cada sección muestra el componente vivo, sus variantes y el código. Si agregas un
    componente a resources/views/components/ui, agrégalo aquí y a la receta:
    docs/cerebro-towell/Arquitectura/receta-componentes.md
--}}
@extends('layouts.app')

@section('page-title', 'UI kit')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create title="Nuevo (x-navbar.button-create)" data-ui-modal-open="kitModal" />
        <x-navbar.button-edit title="Editar" :disabled="false" />
        <x-navbar.button-delete title="Eliminar" :disabled="false" />
        <x-navbar.button-report title="Reporte" />
    </div>
@endsection

@php
    $secciones = [
        'tokens' => 'Tokens', 'botones' => 'Botones', 'badges' => 'Badges', 'campos' => 'Campos',
        'modal' => 'Modal', 'tabla' => 'Tabla', 'filtros' => 'Filtros', 'vacios' => 'Vacíos',
        'carga' => 'Carga', 'avisos' => 'Avisos', 'pt' => 'Modales de PT',
    ];
    $maquinas = [
        ['id' => 'KM-01', 'tipo' => 'Urdidora', 'estado' => 'Activa'],
        ['id' => 'KM-02', 'tipo' => 'Urdidora', 'estado' => 'Mantenimiento'],
        ['id' => 'STAUBLI-1', 'tipo' => 'Anudadora', 'estado' => 'Activa'],
        ['id' => 'STAUBLI-2', 'tipo' => 'Anudadora', 'estado' => 'Baja'],
    ];
    $tonoEstado = ['Activa' => 'success', 'Mantenimiento' => 'warning', 'Baja' => 'danger'];
@endphp

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6">

    <nav aria-label="Secciones de la galería" class="sticky top-0 z-20 -mx-4 flex gap-1 overflow-x-auto bg-blue-400/90 px-4 py-2 backdrop-blur">
        @foreach ($secciones as $ancla => $nombre)
            <a href="#{{ $ancla }}" class="inline-flex min-h-touch shrink-0 items-center rounded-lg px-3 text-sm font-semibold text-white hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">{{ $nombre }}</a>
        @endforeach
    </nav>

    @component('dev.ui-kit.seccion', ['id' => 'tokens', 'titulo' => 'Tokens', 'ds' => 'DS-01'])
        @slot('descripcion')Bloque <code>@theme</code> de <code>resources/css/app.css</code>. Mapean a la paleta que la app ya usa: adoptarlos no cambia el aspecto.@endslot
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach (['primary' => 'bg-primary', 'accent' => 'bg-accent', 'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'info' => 'bg-info', 'surface-muted' => 'bg-surface-muted', 'line' => 'bg-line', 'ink' => 'bg-ink', 'ink-muted' => 'bg-ink-muted'] as $color => $clase)
                <div class="overflow-hidden rounded-lg border border-line">
                    <div class="h-12 {{ $clase }}"></div>
                    <p class="px-2 py-1 text-caption font-semibold text-ink">{{ $color }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-4 text-ink">
            <span class="text-caption">text-caption · 12 px (mínimo)</span>
            <span class="text-sm">text-sm · 14 px</span>
            <span class="inline-flex size-touch items-center justify-center rounded-lg border-2 border-dashed border-primary text-caption">44</span>
            <span class="text-sm text-ink-muted">size-touch / min-h-touch: objetivo táctil de 44 px</span>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'botones', 'titulo' => 'Botones · x-ui.button', 'ds' => 'DS-05'])
        @slot('codigo')
@verbatim
<x-ui.button variant="create" icon="fa-plus">Crear</x-ui.button>
<x-ui.button variant="delete" size="icon" icon="fa-trash" label="Eliminar" />
<x-ui.button variant="ghost" href="/ruta">Volver</x-ui.button>
<x-ui.button type="submit">Guardar</x-ui.button>   degradado, el de siempre
@endverbatim
        @endslot
        @slot('descripcion')Planos con la paleta de los botones del navbar (código nuevo) y los de degradado que ya usa /admin, sin cambios.@endslot
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="create" icon="fa-plus">Crear</x-ui.button>
            <x-ui.button variant="edit" icon="fa-pen-to-square">Editar</x-ui.button>
            <x-ui.button variant="delete" icon="fa-trash">Eliminar</x-ui.button>
            <x-ui.button variant="report" icon="fa-file">Reporte</x-ui.button>
            <x-ui.button variant="neutral">Cancelar</x-ui.button>
            <x-ui.button variant="ghost" href="#botones">Enlace</x-ui.button>
            <x-ui.button variant="create" :loading="true">Guardando</x-ui.button>
            <x-ui.button variant="create" disabled>Deshabilitado</x-ui.button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="create" size="nav" icon="fa-plus">Tamaño nav</x-ui.button>
            <x-ui.button variant="create" size="icon" icon="fa-plus" label="Crear" />
            <x-ui.button variant="delete" size="icon" icon="fa-trash" label="Eliminar" />
            <x-ui.button variant="ghost" size="icon" icon="fa-ellipsis-vertical" label="Más opciones" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button>Primario</x-ui.button>
            <x-ui.button variant="secondary">Secundario</x-ui.button>
            <x-ui.button variant="success" icon="check">Éxito</x-ui.button>
            <x-ui.button variant="danger" size="sm">Peligro</x-ui.button>
            <x-ui.button variant="warning" size="lg">Aviso</x-ui.button>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'badges', 'titulo' => 'Badges · x-ui.badge', 'ds' => 'DS-06'])
        @slot('codigo')
@verbatim
<x-ui.badge tone="success" icon="fa-check">Liberada</x-ui.badge>
<x-ui.badge tone="warning" dot>En proceso</x-ui.badge>
@endverbatim
        @endslot
        <div class="flex flex-wrap gap-2">
            @foreach (['neutral', 'primary', 'success', 'warning', 'danger', 'info', 'accent'] as $tono)
                <x-ui.badge :tone="$tono">{{ ucfirst($tono) }}</x-ui.badge>
            @endforeach
            <x-ui.badge tone="success" icon="fa-check">Liberada</x-ui.badge>
            <x-ui.badge tone="warning" dot>En proceso</x-ui.badge>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'campos', 'titulo' => 'Campos · x-ui.field', 'ds' => 'DS-04'])
        @slot('codigo')
@verbatim
<x-ui.field as="text" name="MaquinaId" label="Máquina ID" required placeholder="KM-01" />
<x-ui.field as="number" name="Porcentaje" label="Porcentaje" hint="De 0 a 100" min="0" max="100" />
<x-ui.field name="Turno" label="Turno" as="select"><option>1</option></x-ui.field>
@endverbatim
        @endslot
        @slot('descripcion')Label ligado con <code>for</code>, asterisco de obligatorio, ayuda y error (de <code>$errors</code> o de la prop <code>error</code>) con <code>aria-describedby</code>.@endslot
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.field as="text" name="kit_maquina" label="Máquina ID" required placeholder="KM-01" />
            <x-ui.field as="number" name="kit_porcentaje" label="Porcentaje" hint="De 0 a 100" min="0" max="100" />
            <x-ui.field as="select" name="kit_turno" label="Turno">
                <option>Turno 1</option><option>Turno 2</option><option>Turno 3</option>
            </x-ui.field>
            <x-ui.field as="text" name="kit_error" label="Con error" value="xx" error="El ID ya existe." />
            <x-ui.field as="textarea" name="kit_nota" label="Nota" rows="3" placeholder="Observaciones" class="md:col-span-2" />
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'modal', 'titulo' => 'Modal · x-ui.modal-base', 'ds' => 'DS-02'])
        @slot('codigo')
@verbatim
<x-ui.button variant="create" data-ui-modal-open="miModal">Abrir</x-ui.button>

<x-ui.modal-base id="miModal" title="Nueva máquina" size="md" :close-on-backdrop="true">
    …formulario…
    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="miModal">Cancelar</x-ui.button>
        <x-ui.button variant="create" type="submit" form="miForm">Guardar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>
@endverbatim
        @endslot
        @slot('descripcion')Un &lt;dialog&gt; que se abre quitando la clase <code>hidden</code> (o con <code>data-ui-modal-open</code> / <code>window.uiModal.abrir(id)</code>). Foco al primer control, Tab atrapado, Esc cierra y el foco vuelve al botón.@endslot
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="create" icon="fa-window-maximize" data-ui-modal-open="kitModal">Abrir modal</x-ui.button>
            <x-ui.button variant="delete" icon="fa-trash" data-ui-modal-open="kitModalBorrar">Abrir confirmación</x-ui.button>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'tabla', 'titulo' => 'Tabla · x-ui.table', 'ds' => 'DS-03'])
        @slot('codigo')
@verbatim
<x-ui.table variant="subtle" :zebra="true">
    <x-slot:head><tr><th scope="col">Máquina</th><th scope="col">Estado</th></tr></x-slot:head>
    @forelse ($maquinas as $m) <tr data-filter-row>…</tr>
    @empty <x-ui.table-empty :colspan="2" message="Sin máquinas" />
    @endforelse
</x-ui.table>
@endverbatim
        @endslot
        @slot('descripcion')Shell: encabezado fijo, variante <code>primary</code> (la de x-tabla) o <code>subtle</code>, cebra, vacío y cargando. x-tabla (CRUD Livewire) se construye encima.@endslot
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-line">
                <x-ui.table :zebra="true">
                    <x-slot:head><tr><th scope="col">Máquina</th><th scope="col">Tipo</th><th scope="col">Estado</th></tr></x-slot:head>
                    @foreach ($maquinas as $m)
                        <tr><td class="font-medium">{{ $m['id'] }}</td><td>{{ $m['tipo'] }}</td><td><x-ui.badge :tone="$tonoEstado[$m['estado']]" dot>{{ $m['estado'] }}</x-ui.badge></td></tr>
                    @endforeach
                </x-ui.table>
            </div>
            <div class="overflow-hidden rounded-xl border border-line">
                <x-ui.table variant="subtle">
                    <x-slot:head><tr><th scope="col">Máquina</th><th scope="col">Tipo</th></tr></x-slot:head>
                    <x-ui.table-empty :colspan="2" message="No hay máquinas registradas" icon="fa-gears">
                        <x-ui.button variant="create" size="nav" icon="fa-plus">Crear máquina</x-ui.button>
                    </x-ui.table-empty>
                </x-ui.table>
            </div>
            <div class="overflow-hidden rounded-xl border border-line lg:col-span-2">
                <x-ui.table :loading="true" :columns="3" :loading-rows="3">
                    <x-slot:head><tr><th scope="col">Cargando…</th><th scope="col"></th><th scope="col"></th></tr></x-slot:head>
                </x-ui.table>
            </div>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'filtros', 'titulo' => 'Filtros · x-ui.filter-bar', 'ds' => 'DS-10'])
        @slot('codigo')
@verbatim
<x-ui.filter-bar target="#tablaMaquinas" placeholder="Buscar máquina">
    <select data-ui-filter-column="tipo">…</select>
</x-ui.filter-bar>
filas: <tr data-filter-row data-tipo="Urdidora">
@endverbatim
        @endslot
        @slot('descripcion')Modo cliente con el motor de filtros de la app (<code>filter-engine.ts</code>). En Livewire se usa <code>model="buscar"</code> y no hay JS.@endslot
        <div class="overflow-hidden rounded-xl border border-line">
            <x-ui.filter-bar target="#kitTablaFiltros" placeholder="Buscar máquina">
                <label class="sr-only" for="kitFiltroTipo">Tipo</label>
                <select id="kitFiltroTipo" data-ui-filter-column="tipo" class="min-h-touch rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">Tipo: todos</option><option>Urdidora</option><option>Anudadora</option>
                </select>
            </x-ui.filter-bar>
            <x-ui.table variant="subtle" id="kitTablaFiltros">
                <x-slot:head><tr><th scope="col">Máquina</th><th scope="col">Tipo</th></tr></x-slot:head>
                @foreach ($maquinas as $m)
                    <tr data-filter-row data-tipo="{{ $m['tipo'] }}"><td>{{ $m['id'] }}</td><td>{{ $m['tipo'] }}</td></tr>
                @endforeach
                <x-ui.table-empty :colspan="2" message="Ninguna máquina coincide" icon="fa-magnifying-glass" data-ui-filter-empty hidden />
            </x-ui.table>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'vacios', 'titulo' => 'Estados vacíos · x-empty.empty-state', 'ds' => 'DS-08'])
        @slot('codigo')
@verbatim
<x-empty.empty-state icon="fa-inbox" title="Sin órdenes" message="No hay órdenes para este turno">
    <x-ui.button variant="create" icon="fa-plus">Nueva orden</x-ui.button>
</x-empty.empty-state>
@endverbatim
        @endslot
        <div class="grid gap-4 md:grid-cols-2">
            <x-empty.empty-state icon="config" title="No hay módulos de configuración" message="No tienes permisos para acceder" class="py-0!" />
            <x-empty.empty-state icon="fa-inbox" title="Sin órdenes" message="No hay órdenes para este turno" class="py-0!">
                <x-ui.button variant="create" icon="fa-plus">Nueva orden</x-ui.button>
            </x-empty.empty-state>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'carga', 'titulo' => 'Carga · x-ui.spinner, x-ui.skeleton, loader global', 'ds' => 'DS-07'])
        @slot('codigo')
@verbatim
<x-ui.spinner />   <x-ui.spinner size="sm" label="Guardando" :inline="true" />
<x-ui.skeleton :lines="3" />
window.loader.show(300); … window.loader.hide();
@endverbatim
        @endslot
        @slot('descripcion')Pantalla completa: un solo loader (<code>window.loader</code>). Local: spinner o skeleton. <code>animate-spin</code> y <code>fa-spin</code> ya no se desplazan.@endslot
        <div class="flex flex-wrap items-center gap-6">
            <x-ui.spinner size="sm" />
            <x-ui.spinner />
            <x-ui.spinner size="lg" />
            <x-ui.spinner size="sm" label="Guardando…" :inline="true" />
            <i class="fa-solid fa-circle-notch fa-spin text-2xl text-primary" aria-hidden="true"></i>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.skeleton :lines="3" />
            <x-ui.skeleton shape="block" class="h-20" />
            <div class="flex items-center gap-3"><x-ui.skeleton shape="circle" class="size-12" /><x-ui.skeleton :lines="2" class="flex-1" /></div>
        </div>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'avisos', 'titulo' => 'Avisos · x-ui.alert y x-ui.flash', 'ds' => 'DS-09'])
        @slot('codigo')
@verbatim
<x-ui.alert type="error" title="No se pudo guardar" :items="$errors->all()" />
flash: el layout ya monta <x-ui.flash>; basta con redirect()->with("error", "…")
@endverbatim
        @endslot
        @slot('descripcion')El layout monta <code>x-ui.flash</code>: todo <code>redirect()->with('error'|'success'|…)</code> y los errores de validación se ven sin código en la vista (y no se duplican si la vista ya los pinta). Éxito/info se cierran solos a los 6 s.@endslot
        <x-ui.alert type="error" title="No se pudo guardar" :items="['El ID ya existe', 'El porcentaje debe ser de 0 a 100']" />
        <x-ui.alert type="success" message="Máquina guardada" />
        <x-ui.alert type="warning" message="El turno cierra en 10 minutos" :dismissible="false" />
        <x-ui.alert type="info">Contenido libre en el slot</x-ui.alert>
    @endcomponent

    @component('dev.ui-kit.seccion', ['id' => 'pt', 'titulo' => 'Compatibilidad · modales de Programa Tejido'])
        @slot('descripcion')Los tres usos reales de x-ui.modal-base (parciales de PT incluidos tal cual). Sus datos vienen de PT, así que aquí pueden abrir vacíos o con aviso de error; lo que se comprueba es abrir/cerrar con el JS de PT.@endslot
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="neutral" data-ui-modal-open="modalRepaso">Crear repaso</x-ui.button>
            <x-ui.button variant="neutral" data-ui-modal-open="modalActCalendarios">Actualizar calendarios</x-ui.button>
            <x-ui.button variant="neutral" data-ui-modal-open="modalMarbetes">Editar marbetes</x-ui.button>
        </div>
    @endcomponent
</div>

<x-ui.modal-base id="kitModal" title="Nueva máquina" size="md" :close-on-backdrop="true">
    <form id="kitModalForm" class="space-y-4">
        <x-ui.field as="text" name="kit_modal_id" label="Máquina ID" required placeholder="KM-01" />
        <x-ui.field as="select" name="kit_modal_tipo" label="Tipo"><option>Urdidora</option><option>Anudadora</option></x-ui.field>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="kitModal">Cancelar</x-ui.button>
        <x-ui.button variant="create" icon="fa-floppy-disk" type="submit" form="kitModalForm">Guardar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

<x-ui.modal-base id="kitModalBorrar" title="Confirmar eliminación" size="sm" tone="danger">
    <p class="text-sm text-gray-700">¿Eliminar la máquina <strong>KM-01</strong>? Esta acción no se puede deshacer.</p>
    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="kitModalBorrar">Cancelar</x-ui.button>
        <x-ui.button variant="delete" icon="fa-trash">Eliminar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

@include('modulos.programa-tejido.modal.act-calendarios')
@include('modulos.programa-tejido.modal.repaso')
@include('modulos.programa-tejido.modal.marbetes')
@endsection
