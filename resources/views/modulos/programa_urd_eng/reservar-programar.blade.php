@extends('layouts.app')

@push('scripts')
    @vite('resources/js/modulos/programa-urd-eng/reservar-programar/index.ts')
@endpush

@section('page-title', 'Reservar y Prog')

@section('navbar-right')
<div class="flex items-center gap-2">
    @if($canModificar ?? false)
    <a href="{{ route('programa.urd.eng.karl.mayer') }}"
       id="btnKarlMayer"
       class="px-3 py-2 rounded-lg transition flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium"
       title="Karl Mayer">
        <i class="fa-solid fa-robot text-base" aria-hidden="true"></i>
        <span>Karl Mayer</span>
    </a>
    @endif
    <x-navbar.button-edit
        id="btnReservar"
        data-accion="reservar"
        type="button"
        title="Reservar"
        icon="fa-save"
        iconColor="text-white"
        hoverBg="hover:bg-gray-600"
        bg="bg-gray-500"
        text="Reservar"
        module="Programa Urd / Eng"
        :disabled="true"
        />

    <x-navbar.button-delete
        id="btnLiberarTelar"
        data-accion="liberar"
        title="Liberar"
        icon="fa-unlock"
        iconColor="text-white"
        hoverBg="hover:bg-red-600"
        bg="bg-red-500"
        text="Liberar"
        module="Programa Urd / Eng"
        :disabled="false"
        />

    <x-navbar.button-create
        id="btnProgramar"
        data-accion="programar"
        title="Programar"
        icon="fa-calendar-check"
        iconColor="text-white"
        hoverBg="hover:bg-purple-600"
        bg="bg-purple-500"
        text="Programar"
        module="Programa Urd / Eng"
        />

</div>
@endsection

@section('content')
@php
    // Datos para resources/js/modulos/programa-urd-eng/reservar-programar/index.ts (19-00-RECETA §2).
    $puConfig = [
        'api' => [
            'inventarioTelares' => route('programa.urd.eng.inventario.telares'),
            'inventarioDisponibleGet' => route('programa.urd.eng.inventario.disponible.get'),
            'programarRequerimientos' => route('programa.urd.eng.programacion.requerimientos'),
            'actualizarTelar' => route('programa.urd.eng.actualizar.telar'),
            'reservarInventario' => route('programa.urd.eng.reservar.inventario'),
            'liberarTelar' => route('programa.urd.eng.liberar.telar'),
        ],
        'can' => ['modificar' => $canModificar ?? false, 'crear' => $canCrear ?? false, 'eliminar' => $canEliminar ?? false],
        'telares' => $inventarioTelares ?? [],
    ];
@endphp

<div id="pu-pagina" class="w-full pu-split relative" data-pagina='@json($puConfig)'>

    {{-- =================== Tabla: Programación (telares) =================== --}}
    <div class="pu-panel">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 bg-slate-900 px-4 py-2">
            <h2 class="text-sm font-bold tracking-wide text-white">Programación de telares</h2>
            <div id="puChips" role="group" aria-label="Filtros rápidos por salón y estado" class="ml-auto flex min-w-0 flex-1 flex-wrap items-center justify-end gap-1.5"></div>
        </div>
        <div class="relative flex min-h-0 w-full flex-1 flex-col">
        <div class="relative flex min-h-0 w-full flex-1 flex-col">
            @php
                $headers = [
                    ['key'=>'no_telar',  'label'=>'No. Telar'],
                    ['key'=>'tipo',      'label'=>'Tipo'],
                    ['key'=>'cuenta',    'label'=>'Cuenta'],
                    ['key'=>'calibre',   'label'=>'Calibre'],
                    ['key'=>'fecha',     'label'=>'Fecha'],
                    ['key'=>'turno',     'label'=>'Turno'],
                    ['key'=>'hilo',      'label'=>'Hilo'],
                    ['key'=>'metros',    'label'=>'Metros'],
                    ['key'=>'no_julio',  'label'=>'Julio'],
                    ['key'=>'no_orden',  'label'=>'Orden'],
                    ['key'=>'estado',    'label'=>'Estado'],
                    ['key'=>'tipo_atado','label'=>'Atado'],
                    ['key'=>'salon',     'label'=>'Salón'],
                ];
            @endphp

            @if(($inventarioTelares ?? collect())->count())
                <div class="pu-scroll w-full">
                        <table id="telaresTable" class="w-full table-auto divide-y divide-gray-200">
                            <thead class="bg-white text-gray-900 sticky top-0 z-20">
                                <tr class="pu-groups">
                                    <th colspan="2" class="pu-g pu-g-a">Máquina</th>
                                    <th colspan="3" class="pu-g pu-g-b">Material</th>
                                    <th colspan="6" class="pu-g pu-g-c">Programa</th>
                                    <th colspan="2" class="pu-g pu-g-d">Atado</th>
                                    @if($canCrear ?? false)
                                    <th rowspan="2" class="pu-g pu-g-e">Selección</th>
                                    @endif
                                </tr>
                                <tr>
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap sortable"
                                            data-column="{{ $h['key'] }}">
                                            <button type="button"
                                                    class="w-full flex items-center justify-center gap-2 cursor-pointer">
                                                <span>{{ $h['label'] }}</span>
                                                <i class="fa-solid fa-sort text-gray-400 sort-icon" aria-hidden="true"></i>
                                            </button>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                {{-- Las filas las pinta tabla-telares.ts desde data-pagina en cuanto
                                     carga el DOM (una sola fuente de marcado por celda). --}}
                            </tbody>
                        </table>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fa-solid fa-box-open w-12 h-12 text-gray-400" aria-hidden="true"></i>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No hay inventario disponible</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        No se han registrado telares en el inventario.
                    </p>
                    <div class="mt-6">
                        <button type="button" id="btnReloadTelares" data-accion="recargar-telares"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-rotate w-4 h-4 mr-1" aria-hidden="true"></i> Recargar
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- =================== Tabla: Inventario disponible =================== --}}
    <div class="pu-panel">
        <div class="bg-blue-500 px-4 py-2 flex justify-between items-center gap-2">
            <h2 class="text-sm font-bold tracking-wide text-white text-center flex-1">Inventario Disponible</h2>
            {{-- Contador y selección por lote: solo con una barra de Karl Mayer seleccionada. --}}
            <span id="puJuliosContador" aria-live="polite"
                  class="hidden rounded-lg bg-white/20 px-3 py-1.5 text-sm font-semibold text-white whitespace-nowrap"></span>
            <button type="button"
                    id="btnSeleccionarLote"
                    data-accion="seleccionar-lote"
                    class="hidden flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white hover:bg-blue-50 text-blue-700 text-sm font-semibold shadow-sm transition-colors whitespace-nowrap"
                    title="Seleccionar los julios libres de este lote hasta llenar la barra">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <span>Seleccionar todos los julios de este lote</span>
            </button>
            <button type="button"
                    id="btnQuitarFiltroInventario"
                    data-accion="alternar-filtro-inventario"
                    class="hidden flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white hover:bg-blue-50 text-blue-700 text-sm font-semibold shadow-sm transition-colors"
                    title="Quitar filtro y mostrar todos los registros">
                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                <span>Quitar Filtro</span>
            </button>
        </div>

        <div class="relative flex min-h-0 w-full flex-1 flex-col">
            <div class="pu-scroll w-full">
                    <table id="inventarioTable" class="w-full table-auto divide-y divide-gray-200">
                        <thead class="bg-gray-100 text-gray-900 sticky top-0 z-20">
                            <tr>
                                @php
                                    $inventarioHeaders = [
                                        ['key' => 'ItemId', 'label' => 'Articulo'],
                                        ['key' => 'Tipo', 'label' => 'Tipo'],
                                        ['key' => 'ConfigId', 'label' => 'Fibra'],
                                        ['key' => 'InventSizeId', 'label' => 'Cuenta'],
                                        ['key' => 'InventColorId', 'label' => 'Cod Color'],
                                        ['key' => 'InventBatchId', 'label' => 'Lote'],
                                        ['key' => 'WMSLocationId', 'label' => 'Localidad'],
                                        ['key' => 'InventSerialId', 'label' => 'No. Julio'],
                                        ['key' => 'ProdDate', 'label' => 'Fecha'],
                                        ['key' => 'Metros', 'label' => 'Metros'],
                                        ['key' => 'InventQty', 'label' => 'Kilos'],
                                        ['key' => 'NoTelarId', 'label' => 'Telar'],
                                    ];
                                @endphp
                                @foreach($inventarioHeaders as $h)
                                    <th class="px-3 py-2 text-center text-xs font-medium tracking-wider whitespace-nowrap sortable-inventario"
                                        data-column="{{ $h['key'] }}">
                                        <button type="button"
                                                class="w-full flex items-center justify-center gap-2 cursor-pointer">
                                            <span>{{ $h['label'] }}</span>
                                            <i class="fa-solid fa-sort text-gray-400 sort-icon-inventario" aria-hidden="true"></i>
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">
                                    <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2" aria-hidden="true"></i>
                                    No hay datos de inventario disponible por el momento
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>
</div>

{{-- Menú de encabezado: clic derecho o mantener presionado sobre una columna (filtros.ts). --}}
<div id="tableContextMenu" role="menu" aria-label="Filtros de columna"
     class="hidden fixed z-50 min-w-[220px] bg-white border border-gray-200 rounded-lg shadow-lg p-1">
    <button type="button" data-action="filter-column" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Filtrar columna
    </button>
    <button type="button" data-action="clear-column-filter" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Quitar filtro de columna
    </button>
    <button type="button" data-action="clear-table-filters" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        Quitar filtros de tabla
    </button>
</div>

{{-- Menú de fila de telar: "⋮", clic derecho o mantener presionado (edicion.ts). --}}
<div id="puMenuFila" role="menu" aria-label="Acciones del telar"
     class="hidden fixed z-50 min-w-[220px] bg-white border border-gray-200 rounded-lg shadow-lg p-1">
    <button type="button" role="menuitem" data-action="editar-cuenta" class="w-full min-h-touch text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        <i class="fa-solid fa-pen mr-2 text-gray-500" aria-hidden="true"></i>Editar cuenta
    </button>
    <button type="button" role="menuitem" data-action="editar-calibre" class="w-full min-h-touch text-left px-3 py-2 text-sm hover:bg-gray-100 rounded-md">
        <i class="fa-solid fa-pen mr-2 text-gray-500" aria-hidden="true"></i>Editar calibre
    </button>
</div>

{{-- Valor del filtro de columna (antes un SweetAlert con input). --}}
<x-ui.modal-base id="puModalFiltro" title="Filtrar columna" size="sm" :close-on-backdrop="true">
    <form id="puFormFiltro" class="space-y-3" novalidate>
        <p class="text-sm text-gray-600">Columna: <span id="puFiltroColumna" class="font-semibold text-gray-900"></span></p>
        <div data-pu-campo>
            <label for="puFiltroValor" class="mb-1 block text-sm font-medium text-gray-700">Valor</label>
            <input id="puFiltroValor" type="text" placeholder="Valor de filtro" autocomplete="off"
                   class="w-full min-h-touch rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div data-pu-campo class="hidden">
            <label for="puFiltroSiNo" class="mb-1 block text-sm font-medium text-gray-700">Valor</label>
            <select id="puFiltroSiNo"
                    class="w-full min-h-touch rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
        </div>
    </form>
    <x-slot:footer>
        <x-ui.button variant="neutral" data-ui-modal-close-target="puModalFiltro">Cancelar</x-ui.button>
        <x-ui.button variant="create" type="submit" form="puFormFiltro">Aplicar</x-ui.button>
    </x-slot:footer>
</x-ui.modal-base>

{{-- =================== Estilos: split viewport + grupos + responsive =================== --}}
<style>
    /* La página nunca scrollea: los dos paneles se reparten la altura visible. */
    .pu-split{height:calc(100dvh - 64px);min-height:30rem;max-width:100%;overflow-x:clip;display:flex;flex-direction:column;gap:.75rem;padding:.75rem;}
    .pu-panel{flex:1 1 0;min-height:0;min-width:0;display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.06);}
    .pu-scroll{flex:1 1 0;min-height:0;min-width:0;overflow:auto;scrollbar-width:thin;scrollbar-color:#94a3b8 transparent;}
    .pu-scroll::-webkit-scrollbar{height:8px;width:8px;}
    .pu-scroll::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:8px;}
    .pu-scroll::-webkit-scrollbar-track{background:transparent;}

    /* Barras de Karl Mayer: la celda muestra un julio y se abre hacia arriba y abajo. */
    .pu-stack{display:inline-flex;flex-direction:column;align-items:center;gap:.1rem;line-height:1.25;vertical-align:middle;}
    .pu-stack .pu-extra{display:none;}
    #telaresTable tbody tr[data-expandido="1"] .pu-extra{display:block;}
    .pu-toggle{margin-left:.25rem;display:inline-flex;align-items:center;gap:.2rem;border-radius:.375rem;background:#f1f5f9;color:#475569;font-size:.75rem;font-weight:700;padding:.05rem .3rem;vertical-align:middle;}
    .pu-toggle:hover{background:#e2e8f0;}
    #telaresTable tbody tr[data-expandido="1"] .pu-toggle i{transform:rotate(180deg);}
    #telaresTable tbody tr.is-selected .pu-toggle{background:rgba(255,255,255,.25);color:#fff;}

    #telaresTable, #inventarioTable { width:100%; }
    #telaresTable thead, #inventarioTable thead { box-shadow:0 2px 4px rgba(15,23,42,.08); }
    #telaresTable td, #inventarioTable td { font-variant-numeric:tabular-nums; }

    /* Encabezados de grupo: un tinte por familia de columnas. */
    .pu-g{font-size:.75rem;font-weight:800;letter-spacing:.04em;padding:.3rem .5rem;text-align:center;border-bottom:2px solid;white-space:nowrap;}
    .pu-g-a{background:#f1f5f9;color:#334155;border-color:#94a3b8;}
    .pu-g-b{background:#fef9c3;color:#854d0e;border-color:#eab308;}
    .pu-g-c{background:#e0f2fe;color:#0c4a6e;border-color:#0284c9;}
    .pu-g-d{background:#ede9fe;color:#5b21b6;border-color:#8b5cf6;}
    .pu-g-e{background:#dcfce7;color:#166534;border-color:#22c55e;}

    /* Separadores verticales entre grupos (telares: 2|3|6|2|1, inventario: 4|5|2|1). */
    #telaresTable thead tr:last-child th:nth-child(3),
    #telaresTable thead tr:last-child th:nth-child(6),
    #telaresTable thead tr:last-child th:nth-child(12),
    #telaresTable thead tr:last-child th:nth-child(14),
    #telaresTable tbody td:nth-child(3),
    #telaresTable tbody td:nth-child(6),
    #telaresTable tbody td:nth-child(12),
    #telaresTable tbody td:nth-child(14){border-left:2px solid #cbd5e1;}
    #inventarioTable thead tr:last-child th:nth-child(5),
    #inventarioTable thead tr:last-child th:nth-child(10),
    #inventarioTable thead tr:last-child th:nth-child(12),
    #inventarioTable tbody td:nth-child(5),
    #inventarioTable tbody td:nth-child(10),
    #inventarioTable tbody td:nth-child(12){border-left:2px solid #cbd5e1;}

    /* Primera columna fija en telares para no perder la referencia al scrollear. */
    #telaresTable thead tr:last-child th:first-child,
    #telaresTable tbody td:first-child{position:sticky;left:0;z-index:5;}
    #telaresTable thead tr:last-child th:first-child{background:#fff;}
    #telaresTable thead .pu-groups th:first-child{position:sticky;left:0;}
    #telaresTable tbody td:first-child{background:inherit;}
    #telaresTable tbody tr.bg-blue-100.border-l-4{
        border-left-color:#60a5fa !important;
        border-left-width:4px;
    }
    #telaresTable tbody tr.bg-blue-100:hover{ background-color:#bfdbfe !important; }
    button:focus-visible, select:focus-visible, input:focus-visible{outline:2px solid #2563eb;outline-offset:1px;}
    /* Las filas son seleccionables con el teclado: sin anillo visible no sirve de nada. */
    #telaresTable tbody tr:focus-visible,
    #inventarioTable tbody tr:focus-visible{outline:2px solid #2563eb;outline-offset:-2px;}

    /* Tablet: celdas compactas para que quepan más columnas sin apretar. */
    @media (max-width:1279.98px){
        #telaresTable :is(th,td), #inventarioTable :is(th,td){padding:.35rem .45rem;font-size:.75rem;}
        .pu-g{font-size:.75rem;padding:.25rem .4rem;}
    }
    /* Móvil: los paneles se apilan con tope y la página vuelve a scrollear. */
    @media (max-width:767.98px){
        .pu-split{height:auto;min-height:0;}
        .pu-panel{max-height:70dvh;flex:none;}
    }
    /* Micro-interacciones: transiciones de fila, press en botones y chips. */
    #telaresTable tbody tr, #inventarioTable tbody tr{transition:background-color .12s ease;}
    #btnProgramar, #btnReservar, #btnLiberarTelar{transition:opacity .15s ease, transform .1s ease;}
    #btnProgramar:active:not(:disabled), #btnReservar:active:not(:disabled), #btnLiberarTelar:active:not(:disabled){transform:scale(.97);}
    #btnProgramar:disabled, #btnReservar:disabled, #btnLiberarTelar:disabled{cursor:not-allowed;opacity:.55;}
    .pu-chip{transition:background-color .12s ease, color .12s ease, transform .1s ease;}
    .pu-chip:active{transform:scale(.96);}
    .pu-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:9999px;font-size:.75rem;font-weight:700;letter-spacing:.02em;border:1px solid rgba(255,255,255,.35);color:#e2e8f0;background:rgba(255,255,255,.08);cursor:pointer;transition:all .12s;white-space:nowrap;}
    .pu-chip:hover{background:rgba(255,255,255,.2);color:#fff;}
    .pu-chip[aria-pressed="true"]{background:#fff;color:#0f172a;border-color:#fff;}
    .pu-chip-sep{width:1px;height:1rem;background:rgba(255,255,255,.25);}
    .sort-priority {
        min-width: 16px;
        height: 16px;
        border-radius: 9999px;
        font-size: 12px;
        line-height: 16px;
        text-align: center;
        background: #dbeafe;
        color: #1d4ed8;
        font-weight: 700;
    }
</style>

@endsection

