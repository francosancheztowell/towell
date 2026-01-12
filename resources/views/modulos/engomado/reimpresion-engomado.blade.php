@extends('layouts.app')

@section('page-title', 'Reimpresion Engomado')

@section('content')
<div class="w-full">
    <div class="bg-white">



        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-blue-500 text-white">
                    <tr>
                        <th class="px-2 py-2 text-left font-semibold">Folio</th>
                        <th class="px-2 py-2 text-left font-semibold">Fecha</th>
                        <th class="px-2 py-2 text-left font-semibold">Cuenta</th>
                        <th class="px-2 py-2 text-left font-semibold">Tipo</th>
                        <th class="px-2 py-2 text-left font-semibold">Maquina</th>
                        <th class="px-2 py-2 text-right font-semibold">Metros</th>
                        <th class="px-2 py-2 text-center font-semibold">PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($ordenes as $orden)
                        <tr>
                            <td class="px-2 py-2">{{ $orden->Folio ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '-' }}</td>
                            <td class="px-2 py-2">{{ $orden->Cuenta ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $orden->RizoPie ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $orden->MaquinaEng ?? '-' }}</td>
                            <td class="px-2 py-2 text-right">
                                {{ $orden->Metros ? number_format($orden->Metros, 0, '.', ',') : '-' }}
                            </td>
                            <td class="px-2 py-2 text-center">
                                <a
                                    href="{{ route('engomado.modulo.produccion.engomado.pdf') }}?orden_id={{ $orden->Id }}&tipo=engomado&reimpresion=1"
                                    target="_blank"
                                    class="inline-flex items-center px-2 py-1 text-md bg-red-600 text-white rounded hover:bg-red-700"
                                >

                                    <i class="fas fa-file-pdf w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-4 text-center text-gray-500">
                                No hay ordenes finalizadas con esos criterios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
