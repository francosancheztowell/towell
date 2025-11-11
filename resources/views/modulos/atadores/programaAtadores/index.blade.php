@extends('layouts.app')

@section('page-title', 'Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create onclick="openModal('createModal')" title="Nuevo Modal" module="Atadores"/>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 h-[calc(100vh-100px)]">
    {{-- <div class=" rounded-lg shadow-md h-full flex flex-col"> --}}
        <div class="overflow-x-auto overflow-y-auto flex-1 mt-8 rounded-lg shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Fecha Req
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Turno Req
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Telar
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            No. Julio
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Ubicación
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Metros
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Orden
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Tipo Atado
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Cuenta
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Calibre
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider sticky top-0 bg-blue-500">
                            Hilo
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($inventarioTelares as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->fecha ? $item->fecha->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->turno ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_telar ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->tipo ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_julio ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->localidad ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->metros ? number_format($item->metros, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->no_orden ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->tipo_atado ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->cuenta ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->calibre ? number_format($item->calibre, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $item->hilo ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-4 text-center text-sm text-gray-500">
                                No hay datos disponibles en el inventario de telares
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    {{-- </div> --}}
</div>
@endsection