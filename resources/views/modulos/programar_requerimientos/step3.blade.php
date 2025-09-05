{{-- resources/views/inventario/disponible.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="table-scroll max-h-[550px] overflow-y-auto overflow-x-auto bigScroll">
        <div class="max-w-[1250px]  mx-auto">

            {{-- Card contenedora --}}
            <div class="rounded-[28px] shadow-2xl border border-blue-200/60 overflow-hidden bg-white/80 backdrop-blur">

                {{-- Título con degradado (look 2da imagen) --}}
                <div class="px-2 py-2.5 text-white font-black tracking-wide text-lg md:text-xl
                  rounded-t-[28px]"
                    style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb);">
                    INVENTARIO DISPONIBLE DE MATERIA PRIMA
                </div>

                {{-- Tarjetitas: DETALLE por LMA (BOMID) --}}
                {{-- Tabla: Componentes colapsados (componentesUnicos) --}}
                <div
                    class="table-scroll max-h-[60vh] overflow-y-auto overflow-x-auto rounded-xl border border-blue-200/60 mb-2">
                    <table class="w-full text-sm table-rounded" id="tabla-componentes">
                        <thead>
                            <tr class="text-white uppercase text-xs tracking-wider text-center"
                                style="background:linear-gradient(90deg,#6683f7,#4e97ba,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                <th class="th">Artículo</th>
                                <th class="th">Config</th>
                                <th class="th">Tamaño</th>
                                <th class="th">Color</th>
                                <th class="th">NOMBRE COLOR</th>
                                <th class="th text-right">Req Total</th>
                            </tr>
                        </thead>

                        <tbody id="tbody-componentes" class="divide-y divide-blue-100/70">
                            @forelse (($componentesUnicos ?? []) as $c)
                                @php
                                    $key = ($c->ITEMID ?? '') . '|' . ($c->INVENTDIMID ?? '');
                                    $reqTotal = (float) ($c->requerido_total ?? 0);
                                    $fmt = function ($n) {
                                        return function_exists('decimales') ? decimales($n) : number_format($n, 6);
                                    };
                                @endphp
                                <tr class="tr cursor-pointer text-center" data-key="{{ $key }}">
                                    <td class="td">{{ $c->ITEMID }}</td>
                                    <td class="td">{{ $c->CONFIGID }}</td>
                                    <td class="td">{{ $c->INVENTSIZEID }}</td>
                                    <td class="td">{{ $c->INVENTCOLORID }}</td>
                                    <td class="td">{{ $c->INVENTDIMID }}</td>
                                    <td class="td td-num">{{ $fmt($reqTotal) }}</td>
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

                {{-- Tabla estilo “glass/gradient header” --}}
                <div class="overflow-auto rounded-xl border border-blue-200/60">
                    <table id="tabla-inventario" class="min-w-[1200px] w-full table-auto text-xs border-collapse">
                        {{-- Controla anchos por columna --}}
                        <colgroup>
                            <col class="w-[120px]"> {{-- Artículo --}}
                            <col class="w-[90px]"> {{-- Config --}}
                            <col class="w-[90px]"> {{-- Tamaño --}}
                            <col class="w-[90px]"> {{-- Color --}}
                            <col class="w-[120px]"> {{-- Nom Color --}}
                            <col class="w-[110px]"> {{-- Almacén --}}
                            <col class="w-[140px]"> {{-- Lote (largo) --}}
                            <col class="w-[110px]"> {{-- Localidad --}}
                            <col class="w-[140px]"> {{-- Serie (largo) --}}
                            <col class="w-[80px]"> {{-- Conos --}}
                            <col class="w-[120px]"> {{-- Lote Prove --}}
                            <col class="w-[100px]"> {{-- Provee --}}
                            <col class="w-[110px]"> {{-- Entrada --}}
                            <col class="w-[100px]"> {{-- Kg --}}
                            <col class="w-[110px]"> {{-- Seleccionar --}}
                        </colgroup>

                        <thead>
                            <tr class="text-white uppercase text-[11px] tracking-wide sticky top-0 z-10"
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

                        <tbody class="divide-y divide-blue-100/70 ">
                            @forelse(($inventario ?? []) as $inv)
                                <tr class="odd:bg-white even:bg-blue-50/40 hover:bg-blue-50/70">
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->ITEMID }}</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->CONFIGID }}</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->INVENTSIZEID }}</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->INVENTCOLORID }}</td>
                                    <td class="px-2 py-1 text-slate-800 truncate max-w-[10rem]"
                                        title="{{ $inv->INVENTCOLORID }}">Nom Color</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->INVENTLOCATIONID }}
                                    </td>
                                    <td class="px-2 py-1 text-slate-800 truncate max-w-[12rem]"
                                        title="{{ $inv->INVENTBATCHID }}">{{ $inv->INVENTBATCHID }}</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->WMSLOCATIONID }}</td>
                                    <td class="px-2 py-1 text-slate-800 truncate max-w-[12rem]"
                                        title="{{ $inv->INVENTSERIALID }}">{{ $inv->INVENTSERIALID }}</td>
                                    <td class="px-2 py-1 text-right font-mono">{{ $inv->TIRAS }}</td>
                                    <td class="px-2 py-1 text-slate-800 truncate max-w-[10rem]"
                                        title="{{ $inv->CALIDAD }}">{{ $inv->CALIDAD }}</td>
                                    <td class="px-2 py-1 text-right font-mono">{{ $inv->CLIENTE }}</td>
                                    <td class="px-2 py-1 text-slate-800 whitespace-nowrap">{{ $inv->FECHA }}</td>
                                    <td class="px-2 py-1 text-right font-mono">{{ decimales($inv->PHYSICALINVENT) }}</td>
                                    <td class="px-2 py-1 text-center">
                                        <input type="checkbox" name="seleccionados[]" class="w-4 h-4 accent-blue-600">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="px-4 py-6 text-center text-blue-700">
                                        No hay registros por ahora.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    {{-- CSS fino para el look de la 2da imagen --}}
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

        /* 🔶 Resaltado de fila seleccionada */
        tbody tr.is-selected .td {
            background: #FEF08A !important;
        }

        /* amber-200 */
        tbody tr.is-selected {
            outline: 2px solid #F59E0B;
            outline-offset: -2px;
        }

        /* amber-500 */
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tbody = document.getElementById('tbody-componentes');
            if (!tbody) return;

            // Delegación: funciona aunque se re-renderice el tbody
            tbody.addEventListener('click', (e) => {
                const tr = e.target.closest('tr.tr');
                if (!tr) return;

                const chk = tr.querySelector('input.chk');

                // Click directo en el checkbox: solo pinta según su estado
                if (e.target === chk) {
                    tr.classList.toggle('is-selected', chk.checked);
                    return;
                }

                // Click en la fila: alterna selección y sincroniza checkbox
                const newState = !tr.classList.contains('is-selected');
                tr.classList.toggle('is-selected', newState);
                if (chk) chk.checked = newState;
            });

            // Si al render ya venían seleccionados, píntalos
            tbody.querySelectorAll('tr.tr').forEach(tr => {
                const chk = tr.querySelector('input.chk');
                if (chk && chk.checked) tr.classList.add('is-selected');
            });
        });
    </script>
@endsection
