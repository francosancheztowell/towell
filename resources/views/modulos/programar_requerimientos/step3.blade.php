{{-- resources/views/inventario/disponible.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="table-scroll max-h-[550px] overflow-y-auto overflow-x-auto bigScroll">
        <div class="max-w-[1250px] mx-auto">

            {{-- Card contenedora --}}
            <div class="rounded-[28px] shadow-2xl border border-blue-200/60 overflow-hidden bg-white/80 backdrop-blur">

                {{-- Título con degradado --}}
                <div class="px-2 py-2.5 text-white font-black tracking-wide text-lg md:text-xl rounded-t-[28px]"
                    style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb);">
                    INVENTARIO DISPONIBLE DE MATERIA PRIMA

                    <button id="btnLimpiarFiltros" type="button"
                        class="ml-[100px] w-1/6 px-0.5 py-1 rounded-md border text-slate-700 bg-white hover:bg-slate-50 text-sm">
                        LIMPIAR FILTROS
                    </button>

                </div>

                {{-- FORM para guardar la selección --}}
                <form id="frmSeleccion" method="POST" action="{{ route('inventario.step3.store') }}">
                    @csrf
                    <input type="hidden" name="componentes" id="inputComponentes">
                    <input type="hidden" name="inventario" id="inputInventario">


                    <div class="w-full flex justify-end">
                        <button type="button"
                            class="px-2 py-1 -translate-y-[40px] w-1/6 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                            onclick="(function(){ 
                                if (history.length >= 3) {
                                sessionStorage.setItem('forceReload','1'); 
                                history.go(-2);
                                } else {
                                location.href='{{ url('/produccionProceso') }}?r='+Date.now();
                                }
                            })()">
                            CANCELAR
                        </button>
                        <button type="submit"
                            class="px-2 py-1 ml-2 mr-2 -translate-y-[40px] w-1/6 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
                            SOLICITAR CONSUMO
                        </button>
                    </div>



                    {{-- ===== Tabla 1: Componentes ===== --}}
                    <div class="table-scroll max-h-[360px] overflow-auto rounded-md ring-1 ring-gray-200">
                        <table class="w-full text-sm" id="tabla-componentes">
                            <thead class="sticky top-0 z-20">
                                <tr class="text-white uppercase text-xs tracking-wider text-center"
                                    style="background:linear-gradient(90deg,#6683f7,#4e97ba,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                    <th class="th">Artículo</th>
                                    <th class="th">Config</th>
                                    <th class="th">Tamaño</th>
                                    <th class="th">Color</th>
                                    <th class="th">NOMBRE COLOR</th>
                                    <th class="th">Req Total</th>
                                </tr>
                            </thead>

                            <tbody id="tbody-componentes" class="divide-y divide-blue-100/70">
                                @forelse(($componentesUnicos ?? []) as $c)
                                    @php
                                        $reqTotal = (float) ($c->requerido_total ?? 0);
                                        $fmt = fn($n) => function_exists('decimales')
                                            ? decimales($n)
                                            : number_format($n, 6);
                                    @endphp

                                    <tr class="tr cursor-pointer text-center" data-itemid="{{ $c->ITEMID ?? '' }}"
                                        data-configid="{{ $c->CONFIGID ?? '' }}" data-sizeid="{{ $c->INVENTSIZEID ?? '' }}"
                                        data-colorid="{{ $c->INVENTCOLORID ?? '' }}"
                                        data-dimid="{{ $c->INVENTDIMID ?? '' }}" data-reqtotal="{{ $reqTotal }}">
                                        <td class="td">{{ $c->ITEMID }}</td>
                                        <td class="td">{{ $c->CONFIGID }}</td>
                                        <td class="td">{{ $c->INVENTSIZEID }}</td>
                                        <td class="td">{{ $c->INVENTCOLORID }}</td>
                                        <td class="td"> - </td>
                                        <td class="td td-num">{{ $fmt(($metros[$loop->index] ?? 0) * 0.2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-blue-700">No hay registros por
                                            ahora.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- ===================== TABLA 2: Inventario ===================== --}}
                    <div class="overflow-auto rounded-xl border border-blue-200/60">
                        <table id="tabla-inventario" class="min-w-[1200px] w-full table-auto text-xs border-collapse">
                            <colgroup>
                                <col class="w-[120px]"> {{-- Artículo --}}
                                <col class="w-[90px]"> {{-- Config --}}
                                <col class="w-[90px]"> {{-- Tamaño --}}
                                <col class="w-[90px]"> {{-- Color --}}
                                <col class="w-[120px]"> {{-- Nom Color --}}
                                <col class="w-[110px]"> {{-- Almacén --}}
                                <col class="w-[140px]"> {{-- Lote --}}
                                <col class="w-[110px]"> {{-- Localidad --}}
                                <col class="w-[140px]"> {{-- Serie --}}
                                <col class="w-[80px]"> {{-- Conos --}}
                                <col class="w-[120px]"> {{-- Lote Prove --}}
                                <col class="w-[100px]"> {{-- Provee --}}
                                <col class="w-[110px]"> {{-- Entrada --}}
                                <col class="w-[100px]"> {{-- Kg --}}
                                <col class="w-[110px]"> {{-- Seleccionar --}}
                            </colgroup>

                            <thead class="sticky top-0 z-20">
                                <tr class="text-white uppercase text-[11px] tracking-wide"
                                    style="background:linear-gradient(90deg,#6683f7,#4e97ba,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                    <th class="px-2 py-1 text-center">Artículo</th>
                                    <th class="px-2 py-1 text-center">Config</th>
                                    <th class="px-2 py-1 text-center">Tamaño</th>
                                    <th class="px-2 py-1 text-center">Color</th>
                                    <th class="px-2 py-1 text-center">Nom Color</th>
                                    <th class="px-2 py-1 text-center">Almacén</th>
                                    <th class="px-2 py-1 text-center">Lote</th>
                                    <th class="px-2 py-1 text-center">Localidad</th>
                                    <th class="px-2 py-1 text-center">Serie</th>
                                    <th class="px-2 py-1 text-center">Conos</th>
                                    <th class="px-2 py-1 text-center">Lote Prove</th>
                                    <th class="px-2 py-1 text-center">Provee</th>
                                    <th class="px-2 py-1 text-center">Entrada</th>
                                    <th class="px-2 py-1 text-center">Kg</th>
                                    <th class="px-2 py-1 text-center">Seleccionar</th>
                                </tr>
                            </thead>

                            <tbody id="tbody-inventario" class="divide-y divide-blue-100/70">
                                @forelse(($inventario ?? []) as $inv)
                                    {{-- Fila con data-* para filtrado y para payload --}}
                                    <tr class="odd:bg-white even:bg-blue-50/40 hover:bg-blue-50/70"
                                        data-itemid="{{ $inv->ITEMID ?? '' }}" data-configid="{{ $inv->CONFIGID ?? '' }}"
                                        data-sizeid="{{ $inv->INVENTSIZEID ?? '' }}"
                                        data-colorid="{{ $inv->INVENTCOLORID ?? '' }}"
                                        data-dimid="{{ $inv->INVENTDIMID ?? '' }}"
                                        data-inventlocationid="{{ $inv->INVENTLOCATIONID ?? '' }}"
                                        data-inventbatchid="{{ $inv->INVENTBATCHID ?? '' }}"
                                        data-wmslocationid="{{ $inv->WMSLOCATIONID ?? '' }}"
                                        data-inventserialid="{{ $inv->INVENTSERIALID ?? '' }}"
                                        data-tiras="{{ $inv->TIRAS ?? '' }}" data-calidad="{{ $inv->CALIDAD ?? '' }}"
                                        data-cliente="{{ $inv->CLIENTE ?? '' }}" data-fecha="{{ $inv->FECHA ?? '' }}"
                                        data-physicalinvent="{{ $inv->PHYSICALINVENT ?? '' }}">
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->ITEMID }}</td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->CONFIGID }}</td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->INVENTSIZEID }}
                                        </td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->INVENTCOLORID }}
                                        </td>
                                        <td class="px-2 py-1 text-slate-800 truncate max-w-[10rem]"
                                            title="{{ $inv->INVENTDIMID }}">{{ $inv->INVENTDIMID }}</td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">
                                            {{ $inv->INVENTLOCATIONID }}</td>
                                        <td class="px-2 py-1 text-slate-800 truncate max-w-[12rem]"
                                            title="{{ $inv->INVENTBATCHID }}">{{ $inv->INVENTBATCHID }}</td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->WMSLOCATIONID }}
                                        </td>
                                        <td class="px-2 py-1 text-slate-800 truncate max-w-[12rem]"
                                            title="{{ $inv->INVENTSERIALID }}">{{ $inv->INVENTSERIALID }}</td>
                                        <td class="px-2 py-1 text-right font-mono">{{ $inv->TIRAS }}</td>
                                        <td class="px-2 py-1 text-slate-800 truncate max-w-[10rem]"
                                            title="{{ $inv->CALIDAD }}">{{ $inv->CALIDAD }}</td>
                                        <td class="px-2 py-1 text-right font-mono">{{ $inv->CLIENTE }}</td>
                                        <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->FECHA }}</td>
                                        <td class="px-2 py-1 text-right font-mono">
                                            {{ function_exists('decimales') ? decimales($inv->PHYSICALINVENT) : number_format($inv->PHYSICALINVENT, 6) }}
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <input type="checkbox" class="chk-inv w-4 h-4 accent-blue-600">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15" class="px-4 py-6 text-center text-blue-700">
                                            No hay registros por ahora.
                                        </td>
                                    </tr>
                                @endforelse

                                {{-- Fila para "sin coincidencias" (se controla por JS) --}}
                                <tr id="no-match-row" class="hidden">
                                    <td colspan="15" class="px-4 py-6 text-center text-slate-600">
                                        No hay coincidencias con los filtros seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- ===================== JS: selección + filtrado + envío ===================== --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tbodyTop = document.getElementById('tbody-componentes');
            const tbodyBottom = document.getElementById('tbody-inventario');
            const noMatchRow = document.getElementById('no-match-row');
            const form = document.getElementById('frmSeleccion');
            const inComp = document.getElementById('inputComponentes');
            const inInv = document.getElementById('inputInventario');

            // Normaliza agresivo: NFKC, NBSP -> espacio, guiones raros -> '-', espacios alrededor de '-' y múltiples espacios
            const norm = (v) => (v ?? '')
                .toString()
                .normalize('NFKC')
                .replace(/\u00A0/g, ' ') // NBSP
                .replace(/[–—−]/g, '-') // en/em dash / minus -> hyphen
                .replace(/\s*-\s*/g, '-') // "ALQ - ANILLO" -> "ALQ-ANILLO"
                .replace(/\s+/g, ' ') // reduce espacios
                .trim()
                .toUpperCase();

            // Clave SIN dimid
            const clave = (d) => [d.itemid, d.configid, d.sizeid, d.colorid].map(norm).join('|');

            // Pon STRIC T=true para exigir match en los 4 campos; en false prueba rápido solo por ITEMID
            const STRICT = false;
            const FIELDS = STRICT ? ['itemid', 'configid', 'sizeid', 'colorid'] : ['itemid'];

            function rowMatchesCandidate(bottomRow, candRow) {
                for (const f of FIELDS) {
                    const want = norm(candRow.dataset[f]);
                    const got = norm(bottomRow.dataset[f]);
                    if (want && got !== want) return false;
                }
                return true;
            }

            function applyFilter() {
                // Solo filas de datos (con data-itemid)
                const selected = Array.from(tbodyTop.querySelectorAll('tr.is-selected[data-itemid]'));
                const bottomRows = Array.from(tbodyBottom.querySelectorAll('tr[data-itemid]'));

                if (selected.length === 0) {
                    bottomRows.forEach(tr => tr.classList.remove('hidden'));
                    if (noMatchRow) noMatchRow.classList.add('hidden');
                    return;
                }

                let visibles = 0;
                bottomRows.forEach(tr => {
                    const show = selected.some(sel => rowMatchesCandidate(tr, sel));
                    tr.classList.toggle('hidden', !show);
                    if (show) visibles++;
                });

                if (noMatchRow) noMatchRow.classList.toggle('hidden', visibles !== 0);
            }

            function clearSelectionAndFilters() {
                tbodyTop.querySelectorAll('tr.is-selected').forEach(tr => tr.classList.remove('is-selected'));
                applyFilter();
            }

            // Click en filas de datos de la tabla 1
            tbodyTop.addEventListener('click', (e) => {
                const tr = e.target.closest('tr.tr');
                if (!tr || !tr.matches('tr[data-itemid]')) return;
                tr.classList.toggle('is-selected');
                applyFilter();
            });

            // Limpiar filtros
            const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
            if (btnLimpiarFiltros) btnLimpiarFiltros.addEventListener('click', clearSelectionAndFilters);

            // ----- Submit: armado de payload (igual) -----
            form.addEventListener('submit', (e) => {
                const compRows = Array.from(tbodyTop.querySelectorAll('tr.is-selected[data-itemid]'));
                const invRows = Array.from(tbodyBottom.querySelectorAll('tr[data-itemid]'))
                    .filter(tr => !tr.classList.contains('hidden'))
                    .filter(tr => tr.querySelector('input.chk-inv')?.checked);

                if (compRows.length === 0) {
                    e.preventDefault();
                    alert('Selecciona al menos un componente.');
                    return;
                }
                if (invRows.length === 0) {
                    e.preventDefault();
                    alert('Marca al menos un renglón del inventario.');
                    return;
                }

                const componentes = compRows.map(tr => ({
                    itemid: tr.dataset.itemid ?? '',
                    configid: tr.dataset.configid ?? '',
                    sizeid: tr.dataset.sizeid ?? '',
                    colorid: tr.dataset.colorid ?? '',
                    dimid: tr.dataset.dimid ?? '',
                    requerido_total: tr.dataset.reqtotal ? Number(tr.dataset.reqtotal) : null,
                    _key: clave(tr.dataset)
                }));

                const inventario = invRows.map(tr => {
                    const d = tr.dataset;
                    return {
                        itemid: d.itemid ?? '',
                        configid: d.configid ?? '',
                        sizeid: d.sizeid ?? '',
                        colorid: d.colorid ?? '',
                        dimid: d.dimid ?? '',
                        inventlocationid: d.inventlocationid ?? '',
                        inventbatchid: d.inventbatchid ?? '',
                        wmslocationid: d.wmslocationid ?? '',
                        inventserialid: d.inventserialid ?? '',
                        tiras: d.tiras ? Number(d.tiras) : null,
                        calidad: d.calidad ?? '',
                        cliente: d.cliente ?? '',
                        fecha: d.fecha ?? '',
                        physicalinvent: d.physicalinvent ? Number(d.physicalinvent) : null,
                        _key: clave(d)
                    };
                });

                inComp.value = JSON.stringify(componentes);
                inInv.value = JSON.stringify(inventario);
            });

            // Estado inicial
            applyFilter();
        });
    </script>


    {{-- SweetAlerts de éxito / error --}}
    @if (session('ok'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: @json(session('ok')),
                    timer: 1800,
                    showConfirmButton: false
                });
            });
        </script>
    @endif

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Ups…',
                    html: `{!! implode('<br>', $errors->all()) !!}`
                });
            });
        </script>
    @endif


    {{-- ===================== CSS fino ===================== --}}
    <style>
        tbody tr:hover .td {
            background: #f0f7ff;
            transition: background-color .15s ease;
        }

        tbody tr .td {
            border-left: 1px solid rgba(191, 219, 254, .6);
        }

        tbody tr .td:last-child {
            border-right: 1px solid rgba(191, 219, 254, .6);
        }

        /* 🔶 Resaltado de fila seleccionada (tabla 1) */
        #tbody-componentes tr.is-selected .td {
            background: #FEF08A !important;
        }

        /* amber-200 */
        #tbody-componentes tr.is-selected {
            outline: 2px solid #F59E0B;
            outline-offset: -2px;
        }

        /* amber-500 */
    </style>
@endsection
