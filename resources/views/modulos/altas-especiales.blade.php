@extends('layouts.app')

@section('page-title', 'Altas de compras especiales')

@section('navbar-right')
    <button id="btnFiltros" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-blue-600 hover:bg-blue-700" title="Filtros">
        <i class="fa-solid fa-filter"></i>
    </button>
    <button id="btnRestablecer" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-gray-600 hover:bg-gray-700 ml-2" title="Restablecer">
        <i class="fa-solid fa-rotate"></i>
    </button>
    <button id="btnProgramar"
            class="hidden bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm ml-2">
        <i class="fa-solid fa-plus mr-1"></i> Programar
    </button>
@endsection

@section('content')
<div class="w-full px-0 py-0">
    <div class="bg-white shadow overflow-hidden w-full">
        @if(isset($errorMensaje))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 m-3 text-sm" role="alert">
                <p class="font-bold">Error</p>
                <p>{{ $errorMensaje }}</p>
            </div>
        @endif

        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 600px;">
                <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs leading-tight">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Flog</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Estado</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-36">Proyecto</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-36">Cliente</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24">Calidad</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Ancho</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Largo</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-28">Artículo</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-44">Nombre</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Tamaño</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Hilo</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24">V. Agregado</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-28">Cancelación</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20">Cantidad</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20">Tipo</th>
                        </tr>
                    </thead>

                    <tbody id="tablaBody" class="bg-white divide-y divide-gray-200">
                        @php
                            $tieneDatos = isset($registros) && is_countable($registros) && count($registros) > 0;
                        @endphp

                        @if($tieneDatos)
                            @foreach($registros as $r)
                                <tr class="select-row cursor-pointer even:bg-gray-50 hover:bg-blue-50 transition-colors"
                                    data-id="{{ $r['FlogsId'] ?? '' }}"
                                    data-idflog="{{ $r['FlogsId'] ?? '' }}"
                                    data-itemid="{{ $r['ItemId'] ?? '' }}"
                                    data-inventsizeid="{{ $r['InventSizeId'] ?? '' }}"
                                    data-cantidad="{{ isset($r['Cantidad']) ? (float)$r['Cantidad'] : '' }}"
                                    data-tipohilo="{{ $r['TipoHilo'] ?? '' }}"
                                    data-flog="{{ strtolower($r['FlogsId'] ?? '') }}"
                                    data-estado="{{ strtolower($r['Estado'] ?? '') }}"
                                    data-proyecto="{{ strtolower($r['NombreProyecto'] ?? '') }}"
                                    data-cliente="{{ strtolower($r['CustName'] ?? '') }}"
                                    data-calidad="{{ strtolower($r['CategoriaCalidad'] ?? '') }}"
                                    data-ancho="{{ $r['Ancho'] ?? '' }}"
                                    data-largo="{{ $r['Largo'] ?? '' }}"
                                    data-articulo="{{ strtolower($r['ItemId'] ?? '') }}"
                                    data-nombre="{{ strtolower($r['ItemName'] ?? '') }}"
                                    data-tamano="{{ strtolower($r['InventSizeId'] ?? '') }}"
                                    data-hilo="{{ strtolower($r['TipoHilo'] ?? '') }}"
                                    data-valor="{{ strtolower($r['ValorAgregado'] ?? '') }}">
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['FlogsId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['Estado'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[10rem] text-gray-700">{{ $r['NombreProyecto'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[10rem] text-gray-700">{{ $r['CustName'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[8rem] text-gray-700">{{ $r['CategoriaCalidad'] ?? '' }}</td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Ancho']) ? number_format((float)$r['Ancho'], 2) : '' }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Largo']) ? number_format((float)$r['Largo'], 2) : '' }}
                                    </td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[7rem] text-gray-700">{{ $r['ItemId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[12rem] text-gray-700">{{ $r['ItemName'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['InventSizeId'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">{{ $r['TipoHilo'] ?? '' }}</td>
                                    <td class="px-2 py-2 whitespace-nowrap truncate max-w-[8rem] text-gray-700">{{ $r['ValorAgregado'] ?? '' }}</td>

                                    <td class="px-2 py-2 whitespace-nowrap truncate text-gray-700">
                                        @if(!empty($r['FechaCancelacion']))
                                            {{ \Carbon\Carbon::parse($r['FechaCancelacion'])->format('d/m/Y') }}
                                        @endif
                                    </td>

                                    <td class="px-2 py-2 text-right whitespace-nowrap truncate text-gray-700">
                                        {{ isset($r['Cantidad']) ? number_format((float)$r['Cantidad'], 2) : '0.00' }}
                                    </td>

                                    <td class="px-2 py-2 text-right whitespace-nowrap">
                                        @php $bata = $r['EsBata'] ?? null; @endphp
                                        @if($bata === 'bata')
                                            <span class="chip inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                                bg-indigo-100 text-indigo-800">
                                                Bata
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="15" class="px-6 py-10 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                                    <p class="mt-1 text-sm text-gray-500">No se encontraron registros de compras especiales.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal de filtros --}}
<div id="modalFiltros" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center h-full">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Filtrar registros</h3>
            <button id="cerrarModal" class="text-gray-500 hover:text-gray-700"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="p-4">
            <div id="filtrosContainer" class="space-y-3"></div>
            <button id="addFiltro" class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Agregar filtro</button>
        </div>
        <div class="p-4 border-t flex gap-2">
            <button id="aplicarFiltros" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Aplicar</button>
            <button id="cancelarFiltros" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded">Cancelar</button>
        </div>
    </div>
    </div>
</div>

<script>
const columnas = {'flog':'Flog','estado':'Estado','proyecto':'Proyecto','cliente':'Cliente','calidad':'Calidad','articulo':'Artículo','nombre':'Nombre','tamano':'Tamaño','hilo':'Hilo','valor':'V. Agregado'};
const mapeo = {'FlogsId':'flog','Estado':'estado','NombreProyecto':'proyecto','CustName':'cliente','CategoriaCalidad':'calidad','ItemId':'articulo','ItemName':'nombre','InventSizeId':'tamano','TipoHilo':'hilo','ValorAgregado':'valor'};

document.addEventListener('DOMContentLoaded', function () {
    let filtros = [], allRows = [], modal = document.getElementById('modalFiltros'), container = document.getElementById('filtrosContainer');

    function renderFiltros() {
        container.innerHTML = filtros.map((f, i) => `
            <div class="flex gap-2 items-end">
                <select class="flex-1 border rounded p-2" data-col>
                    <option value="">Columna...</option>
                    ${Object.entries(columnas).map(([k,v])=>`<option value="${k}" ${f.col===k?'selected':''}>${v}</option>`).join('')}
                </select>
                <input type="text" class="flex-1 border rounded p-2" value="${f.val}" data-val placeholder="Valor...">
                <button onclick="filtros.splice(${i},1);renderFiltros()" class="px-3 py-2 bg-red-600 text-white rounded"><i class="fa-solid fa-trash"></i></button>
            </div>
        `).join('') || '<p class="text-gray-500 text-sm">No hay filtros activos</p>';
    }

    function aplicarFiltrosTabla() {
        allRows.forEach(r => r.style.display = '');
        if(filtros.length === 0) return;
        let visibleCount = 0;
        allRows.forEach(row => {
            const match = filtros.every(f => {
                const val = row.getAttribute('data-'+f.col) || '';
                return f.val === '' || val.includes(f.val.toLowerCase());
            });
            if(match) visibleCount++;
            row.style.display = match ? '' : 'none';
        });
        if(visibleCount === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="15" class="px-6 py-10 text-center"><p class="text-gray-500">No hay resultados que coincidan con los filtros</p></td>';
            document.getElementById('tablaBody').appendChild(emptyRow);
        } else {
            const emptyMsg = document.getElementById('tablaBody').querySelector('td[colspan="15"]');
            if(emptyMsg) emptyMsg.closest('tr').remove();
        }
    }

    allRows = Array.from(document.querySelectorAll('tr.select-row'));
    document.getElementById('btnFiltros').onclick = () => { filtros = []; renderFiltros(); modal.style.display = 'block'; };
    document.getElementById('cerrarModal').onclick = () => { modal.style.display = 'none'; };
    document.getElementById('cancelarFiltros').onclick = () => { modal.style.display = 'none'; };
    document.getElementById('addFiltro').onclick = () => { filtros.push({col:'',val:''}); renderFiltros(); };
    document.getElementById('aplicarFiltros').onclick = () => {
        filtros = Array.from(container.querySelectorAll('[data-col]')).map((s,i) => ({col:s.value, val:container.querySelectorAll('[data-val]')[i].value}));
        aplicarFiltrosTabla();
        modal.style.display = 'none';
    };
    document.getElementById('btnRestablecer').onclick = () => {
        filtros = [];
        aplicarFiltrosTabla();
        Swal.fire({ icon:'success', title:'Filtros restablecidos', toast:true, position:'top-end', timer:2000, showConfirmButton:false });
    };

    const btnProgramar = document.getElementById('btnProgramar');
    let current = null;

    // helpers para chips (badges)
    function setChipSelected(chip, on) {
        if (!chip) return;

        // Guardar el estado original si es la primera vez
        if (!chip.dataset.originalClass) {
            // Si tiene bg-indigo-100, es bata (indigo), si no, es gris o no tiene color
            if (chip.classList.contains('bg-indigo-100')) {
                chip.dataset.originalClass = 'indigo';
            } else if (chip.classList.contains('bg-gray-100')) {
                chip.dataset.originalClass = 'gray';
            } else {
                // Si no tiene clase de color, asumir que es indigo (bata) ya que solo los bata tienen badge
                chip.dataset.originalClass = 'indigo';
            }
        }

        // Remover todas las clases de color
        chip.classList.remove('bg-indigo-100', 'text-indigo-800', 'bg-gray-100', 'text-gray-800', 'bg-blue-600', 'text-white');

        if (on) {
            // Seleccionado: azul
            chip.classList.add('bg-blue-600', 'text-white');
        } else {
            // Restaurar color original
            if (chip.dataset.originalClass === 'indigo') {
                chip.classList.add('bg-indigo-100', 'text-indigo-800');
            } else {
                chip.classList.add('bg-gray-100', 'text-gray-800');
            }
        }
    }

    function setRowSelected(row, selected) {
        const tds = row.querySelectorAll('td');
        const chip = row.querySelector('.chip');

        if (selected) {
            row.classList.add('bg-blue-500', 'text-white');
            row.classList.remove('even:bg-gray-50', 'hover:bg-blue-50');

            tds.forEach(td => {
                td.classList.remove('text-gray-700');
                td.classList.add('text-white');
            });

            setChipSelected(chip, true);
        } else {
            row.classList.remove('bg-blue-500', 'text-white');
            row.classList.add('hover:bg-blue-50');
            // restaurar texto
            tds.forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
            setChipSelected(chip, false);
        }
    }

    function updateButtonVisibility() {
        if (current) btnProgramar.classList.remove('hidden');
        else btnProgramar.classList.add('hidden');
    }

    btnProgramar.onclick = () => {
        if (!current) return;
        const idflog       = current.dataset.idflog || '';
        const itemid       = current.dataset.itemid || '';
        const inventsizeid = current.dataset.inventsizeid || '';
        const cantidad     = current.dataset.cantidad || '';
        const tipohilo     = current.dataset.tipohilo || '';

        const url = new URL('{{ route("programa-tejido.altas-especiales.nuevo") }}', window.location.origin);
        url.searchParams.set('idflog', idflog);
        if (itemid)       url.searchParams.set('itemid', itemid);
        if (inventsizeid) url.searchParams.set('inventsizeid', inventsizeid);
        if (cantidad)     url.searchParams.set('cantidad', cantidad);
        if (tipohilo)     url.searchParams.set('tipohilo', tipohilo);

        window.location.href = url.toString();
    };

    document.querySelectorAll('tr.select-row').forEach(row => {
        row.addEventListener('click', () => {
            if (current === row) {
                setRowSelected(row, false);
                current = null;
                updateButtonVisibility();
                return;
            }
            if (current) setRowSelected(current, false);
            current = row;
            setRowSelected(row, true);
            updateButtonVisibility();
        });
    });
});
</script>
@endsection
