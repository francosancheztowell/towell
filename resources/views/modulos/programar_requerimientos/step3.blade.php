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

                @foreach ($componentes as $comp)
                    @php
                        // metros base por BOMID (lmaturdido)
                        $metrosBase = (float) ($metrosPorBom[$comp->BOMID] ?? 0);
                        $bomQty = (float) ($comp->BOMQTY ?? 0);
                        $reqCalc = $metrosBase * $bomQty; // 🌟 Requerimiento calculado

                        $meta = [
                            ['Artículo', $comp->ITEMID ?? ''],
                            ['Configuración', $comp->CONFIGID ?? ''],
                            ['Tamaño', $comp->INVENTSIZEID ?? ''],
                            ['Color', $comp->INVENTCOLORID ?? ''],
                            ['Nombre Color', $comp->CONFIGID ?? ''],
                            ['Requerimiento', decimales($reqCalc)], // 👈 aquí el cálculo
                        ];
                    @endphp

                    <div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-sky-50">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-2">
                            @foreach ($meta as [$label, $value])
                                <div class="rounded-2xl border border-indigo-200/70 bg-white/90 shadow-sm p-1">
                                    <div class="text-[10px] uppercase tracking-wide font-semibold text-blue-900/70">
                                        {{ $label }}</div>
                                    <div
                                        class="mt-1 h-6 rounded-lg px-2 flex items-center font-bold text-slate-800 bg-gradient-to-b from-blue-50 via-sky-50 to-indigo-50">
                                        {{ $value }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach



                {{-- Tabla estilo “glass/gradient header” --}}
                <div class="overflow-auto">
                    <table class="w-full text-sm table-rounded" id="tabla-inventario">
                        <thead>
                            <tr class="text-white uppercase text-xs tracking-wider"
                                style="background:linear-gradient(90deg,#6683f7,#4e97ba,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                <th class="th">Artículo</th>
                                <th class="th">Config</th>
                                <th class="th">Tamaño</th>
                                <th class="th">Color</th>
                                <th class="th">Nom Color</th>
                                <th class="th">Almacén</th>
                                <th class="th">Lote</th>
                                <th class="th">Localidad</th>
                                <th class="th">Serie</th>
                                <th class="th text-right">Conos</th>
                                <th class="th">Lote Prove</th>
                                <th class="th text-right">Provee</th>
                                <th class="th">Entrada</th>
                                <th class="th text-right">Kg</th>
                                <th class="th text-center">Seleccionar</th>
                            </tr>
                        </thead>
                        {{-- Articulo	Config	Tamaño	Color	Nom Color	Almacen	Lote	Localidad	Serie	Conos	Lote Prove	Provee	Entrada	Kg	Seleccionar
 --}}
                        <tbody class="divide-y divide-blue-100/70">
                            @forelse(($inventario ?? []) as $inv)
                                <tr class="tr">
                                    <td class="td">{{ $inv->ITEMID }}</td>
                                    <td class="td">{{ $inv->CONFIGID }}</td>
                                    <td class="td">{{ $inv->INVENTSIZEID }}</td>
                                    <td class="td">{{ $inv->INVENTCOLORID }}</td>
                                    <td class="td">Nom Color</td>
                                    <td class="td">{{ $inv->INVENTLOCATIONID }}</td>
                                    <td class="td">{{ $inv->INVENTBATCHID }}</td>
                                    <td class="td">{{ $inv->WMSLOCATIONID }}</td>
                                    <td class="td">{{ $inv->INVENTSERIALID }}</td>
                                    <td class="td td-num">{{ $inv->TIRAS }}</td>
                                    <td class="td">{{ $inv->CALIDAD }}</td>
                                    <td class="td td-num">{{ $inv->CLIENTE }}</td>
                                    <td class="td">{{ $inv->FECHA }}</td>
                                    <td class="td td-num">{{ $inv->PHYSICALINVENT }}</td>
                                    <td class="td text-center">
                                        <input type="checkbox" name="seleccionados[]" class="chk">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="px-6 py-10 text-center text-blue-700">
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
        .table-rounded thead th:first-child {
            border-top-left-radius: 22px;
        }

        .table-rounded thead th:last-child {
            border-top-right-radius: 22px;
        }

        .th {
            padding: .20rem .5rem;
            white-space: nowrap;
        }

        .td {
            padding-top: 3px;
            padding-right: 3px;
            background: rgba(255, 255, 255, .96);
            color: #0f172a;
        }

        .td-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        tbody tr:hover .td {
            background: #f0f7ff;
            transition: background-color .15s ease;
        }

        /* sutil “divider” como en la tarjeta de ejemplo */
        tbody tr .td {
            border-left: 1px solid rgba(191, 219, 254, .6);
        }

        tbody tr .td:last-child {
            border-right: 1px solid rgba(191, 219, 254, .6);
        }

        thead th {
            border-right: 1px solid rgba(255, 255, 255, .25);
        }

        thead th:last-child {
            border-right: none;
        }

        .chk {
            width: 1.2rem;
            height: 1.2rem;
            accent-color: #2563eb;
        }
    </style>
@endsection
