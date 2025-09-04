{{-- resources/views/inventario/disponible.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="min-h-screen p1">
        <div class="max-w-[1400px] mx-auto">

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
                        $meta = [
                            ['Artículo', $comp->ITEMID ?? ''],
                            ['Configuración', $comp->CONFIGID ?? ''],
                            ['Tamaño', $comp->INVENTSIZEID ?? ''],
                            ['Color', $comp->INVENTCOLORID ?? ''],
                            ['Nombre Color', $comp->CONFIGID ?? ''],
                            ['Requerimiento', $comp->PENDING ?? ''],
                        ];
                    @endphp

                    <div class=" bg-gradient-to-br from-blue-50 via-indigo-50 to-sky-50">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-2">
                            @foreach ($meta as [$label, $value])
                                <div class="rounded-2xl border border-indigo-200/70 bg-white/90 shadow-sm p-1">
                                    <div class="text-[10px] uppercase tracking-wide font-semibold text-blue-900/70">
                                        {{ $label }}
                                    </div>
                                    <div
                                        class="mt-1 h-6 rounded-lg px-2 flex items-center font-bold text-slate-800
                        bg-gradient-to-b from-blue-50 via-sky-50 to-indigo-50">
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

                        <tbody class="divide-y divide-blue-100/70">
                            {{-- Cuando tengas datos:
            @forelse(($inventario ?? []) as $row)
              <tr class="tr">
                <td class="td">{{ $row->articulo }}</td>
                <td class="td">{{ $row->config }}</td>
                <td class="td">{{ $row->tamano }}</td>
                <td class="td">{{ $row->color }}</td>
                <td class="td">{{ $row->nom_color }}</td>
                <td class="td">{{ $row->almacen }}</td>
                <td class="td">{{ $row->lote }}</td>
                <td class="td">{{ $row->localidad }}</td>
                <td class="td">{{ $row->serie }}</td>
                <td class="td td-num">{{ $row->conos }}</td>
                <td class="td">{{ $row->lote_prove }}</td>
                <td class="td td-num">{{ $row->provee }}</td>
                <td class="td">{{ \Carbon\Carbon::parse($row->entrada)->format('d/m/Y') }}</td>
                <td class="td td-num">{{ number_format($row->kg, 2) }}</td>
                <td class="td text-center">
                  <input type="checkbox" name="seleccionados[]" value="{{ $row->id }}" class="chk">
                </td>
              </tr>
            @empty
            --}}
                            <tr>
                                <td colspan="15" class="px-6 py-10 text-center text-blue-700">
                                    No hay registros por ahora.
                                </td>
                            </tr>
                            {{-- @endforelse --}}
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
            padding: .9rem 1rem;
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
            width: 1rem;
            height: 1rem;
            accent-color: #2563eb;
        }
    </style>
@endsection
