@extends('layouts.app')

@section('page-title', 'BPM Urdido')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarBpmUrdido()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('urdido.reportes.urdido.bpm.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-bpm-urdido-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">BPM URDIDO</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($filas))
                <span class="text-gray-500 text-sm">{{ count($filas) }} líneas</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-blue-700 text-white z-10">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">#</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Folio</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Status</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Fecha</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveEntrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">NombreEntrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Turno Entrega</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveRecibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">NombreRecibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Turno Recibe</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ClaveAutoriza</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Nombre Autoriza</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Orden</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Actividad</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($filas ?? []) as $fila)
                        @php
                            $status = strtolower((string) ($fila->Status ?? ''));
                            $statusClass = 'bg-gray-100 text-gray-800';
                            $valorTxt = strtoupper((string) ($fila->ValorTexto ?? 'S/N'));
                            $valorClass = 'bg-gray-100 text-gray-700';
                            if ($status === 'creado') {
                                $statusClass = 'bg-blue-100 text-blue-800';
                            } elseif ($status === 'terminado') {
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                            } elseif ($status === 'autorizado') {
                                $statusClass = 'bg-green-100 text-green-800';
                            }
                            if ($valorTxt === 'CORRECTO') {
                                $valorClass = 'bg-green-100 text-green-800';
                            } elseif ($valorTxt === 'INCORRECTO') {
                                $valorClass = 'bg-red-100 text-red-800';
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-0.5 border border-gray-300 text-center font-semibold">{{ $fila->InicioFolio ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->Folio ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">
                                    {{ $fila->Status ?? '' }}
                                </span>
                            </td>
                            <td class="px-2 py-0.5 border border-gray-300">
                                {{ !empty($fila->Fecha) ? \Carbon\Carbon::parse($fila->Fecha)->format('d/m/Y') : '' }}
                            </td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplEnt ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplEnt ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->TurnoEntrega ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplRec ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplRec ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->TurnoRecibe ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->CveEmplAutoriza ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->NombreEmplAutoriza ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $fila->Orden ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $fila->Actividad ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $valorClass }}">
                                    {{ $fila->ValorTexto ?? 'S/N' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalConsultarBpmUrdido() {
        const hoy = new Date().toISOString().split('T')[0];
        const fechaIni = '{{ $fechaIni ?? '' }}' || hoy;
        const fechaFin = '{{ $fechaFin ?? '' }}' || hoy;
        const soloFinalizados = {{ ($soloFinalizados ?? true) ? 'true' : 'false' }};

        Swal.fire({
            title: 'Consultar en rango',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label for="swal_fecha_ini" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                        <input type="date" id="swal_fecha_ini" value="${fechaIni}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="swal_fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                        <input type="date" id="swal_fecha_fin" value="${fechaFin}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="swal_solo_finalizados" ${soloFinalizados ? 'checked' : ''} class="rounded border-gray-300 text-blue-600">
                        <label for="swal_solo_finalizados" class="text-sm text-gray-700">Solo terminados/autorizados</label>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Consultar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            focusConfirm: false,
            preConfirm: () => {
                const fi = document.getElementById('swal_fecha_ini')?.value;
                const ff = document.getElementById('swal_fecha_fin')?.value;
                if (!fi || !ff) {
                    Swal.showValidationMessage('Seleccione fecha inicial y final');
                    return false;
                }
                if (new Date(fi) > new Date(ff)) {
                    Swal.showValidationMessage('La fecha inicial no puede ser mayor que la final');
                    return false;
                }
                const solo = document.getElementById('swal_solo_finalizados')?.checked ? '1' : '0';
                return { fecha_ini: fi, fecha_fin: ff, solo_finalizados: solo };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("urdido.reportes.urdido.bpm") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarBpmUrdido();
        @endif
    });
</script>
@endpush
