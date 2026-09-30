{{--
    Eficiencia STD y Velocidad STD: el mismo catálogo con otra columna de valor (dedupe 19-06b;
    antes catalagoEficiencia 703 y catalagoVelocidad 679 líneas casi iguales).
    Las vistas originales hacen @include con $variante ('eficiencia' | 'velocidad') para que los
    controllers no cambien. Lo que cambia entre ellas está en $vistas; el CRUD, en
    resources/js/modulos/catalogos-planeacion/estandar/index.ts.
--}}
@php
    $vistas = [
        'eficiencia' => ['titulo' => 'Catálogo de Eficiencia', 'filas' => $eficiencia ?? collect(), 'columnaFibra' => 'Tipo de Hilo', 'columnaValor' => 'Eficiencia', 'campo' => 'Eficiencia'],
        'velocidad' => ['titulo' => 'Catálogo de Velocidad', 'filas' => $velocidad ?? collect(), 'columnaFibra' => 'Fibra', 'columnaValor' => 'RPM', 'campo' => 'Velocidad'],
    ];
    $vista = $vistas[$variante];
    $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::estandar($variante);
    $mostrarValor = fn ($v) => $variante === 'eficiencia' ? number_format($v * 100, 0).'%' : $v.' RPM';
@endphp

@extends('layouts.app')

@section('page-title', $vista['titulo'])

@section('navbar-right')
<x-buttons.catalog-actions :route="$variante" :showFilters="true" />
@endsection

@section('content')
<div class="container-fluid" data-catalogo='@json($catalogo)'>
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto max-h-[calc(100vh-70px)]">
            <table class="table table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Salón</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Telar</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">{{ $vista['columnaFibra'] }}</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">{{ $vista['columnaValor'] }}</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Densidad</th>
                    </tr>
                </thead>
                <tbody id="{{ $variante }}-body" class="bg-white text-black" data-catalogo-filas>
                    @foreach ($vista['filas'] as $item)
                        @php
                            $recordId = $item->Id ?: $item->SalonTejidoId.'_'.$item->NoTelarId.'_'.$item->FibraId;
                            $valores = [
                                'Id' => $recordId,
                                'SalonTejidoId' => $item->SalonTejidoId,
                                'NoTelarId' => $item->NoTelarId,
                                'FibraId' => $item->FibraId,
                                'Densidad' => $item->Densidad ?? 'Normal',
                                $vista['campo'] => $item->{$vista['campo']},
                            ];
                        @endphp
                        <tr data-fila data-id="{{ $recordId }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                            class="text-center hover:bg-blue-50 transition cursor-pointer text-black aria-selected:bg-blue-500 aria-selected:text-white aria-selected:font-semibold aria-selected:hover:bg-blue-500">
                            <td class="py-1 px-4">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 font-semibold">{{ $mostrarValor($item->{$vista['campo']}) }}</td>
                            <td class="py-1 px-4">{{ $item->Densidad ?? 'Normal' }}</td>
                        </tr>
                    @endforeach
                    <tr data-catalogo-sin-coincidencias hidden>
                        <td colspan="5" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2" aria-hidden="true"></i><br>No se encontraron resultados
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('catalagos.comun.modales')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/estandar/index.ts')
@endpush
