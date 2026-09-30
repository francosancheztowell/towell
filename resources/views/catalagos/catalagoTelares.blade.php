@extends('layouts.app')

@section('page-title', 'Catálogo de Telares')

@section('navbar-right')
<x-buttons.catalog-actions route="telares" :showFilters="true" />
@endsection

@php $catalogo = \App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatalogosPlaneacionVista::telares(); @endphp

@section('content')
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
    @endif

    <div class="bg-white overflow-hidden w-full" data-catalogo='@json($catalogo)'>
        <div class="overflow-y-auto h-[640px] [scrollbar-width:thin] w-full">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-20">
                    <tr>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Salón</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Telar</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Nombre</th>
                        <th scope="col" class="py-1 px-2 font-bold tracking-wider text-center">Grupo</th>
                    </tr>
                </thead>
                <tbody id="telares-body" class="bg-white text-black" data-catalogo-filas>
                    @foreach ($telares as $t)
                        @php
                            $uid = $t->SalonTejidoId . '_' . $t->NoTelarId;
                            $valores = ['uid' => $uid, 'SalonTejidoId' => $t->SalonTejidoId, 'NoTelarId' => $t->NoTelarId, 'Nombre' => $t->Nombre, 'Grupo' => $t->Grupo];
                        @endphp
                        <tr data-fila data-id="{{ $uid }}" data-valores='@json($valores)' tabindex="0" aria-selected="false"
                            class="text-center hover:bg-blue-50 transition cursor-pointer aria-selected:bg-blue-500 aria-selected:text-white aria-selected:hover:bg-blue-500">
                            <td class="py-2 px-4">{{ $t->SalonTejidoId }}</td>
                            <td class="py-2 px-4">{{ $t->NoTelarId }}</td>
                            <td class="py-2 px-4">{{ $t->Nombre }}</td>
                            <td class="py-2 px-4">{{ $t->Grupo ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                    <tr data-catalogo-sin-coincidencias hidden>
                        <td colspan="4" class="text-center py-8 text-gray-500">
                            <i class="fas fa-search text-4xl mb-2" aria-hidden="true"></i><br>No se encontraron resultados
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @include('catalagos.comun.modales')
@endsection

@push('scripts')
    @vite('resources/js/modulos/catalogos-planeacion/telares/index.ts')
@endpush
