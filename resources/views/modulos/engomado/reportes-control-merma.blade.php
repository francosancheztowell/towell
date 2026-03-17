@extends('layouts.app')

@section('page-title', 'Control Merma')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarControlMerma()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('engomado.reportes.control-merma.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-control-merma-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">CONTROL MERMA</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (($filas ?? collect())->isNotEmpty())
                <span class="text-gray-500 text-sm">{{ $filas->count() }} filas</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-blue-700 text-white z-10">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Fecha</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Máquina</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Orden</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Cuenta</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Hilo</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Sin Goma</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">Con Goma</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">URD A/B/C</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($filas ?? collect()) as $fila)
                        @php
                            $urdResumen = collect($fila['urd_slots'] ?? [])
                                ->filter(fn ($slot) => !empty($slot['label']))
                                ->map(fn ($slot) => trim(($slot['label'] ?? '') . ' ' . ($slot['count'] ?? '')))
                                ->implode(' | ');
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-1 border border-gray-300">
                                {{ !empty($fila['fecha']) ? $fila['fecha']->format('d/m/Y') : '' }}
                            </td>
                            <td class="px-2 py-1 border border-gray-300">{{ $fila['maquina_display'] ?? '' }}</td>
                            <td class="px-2 py-1 border border-gray-300">{{ $fila['folio'] ?? '' }}</td>
                            <td class="px-2 py-1 border border-gray-300 text-right">{{ $fila['cuenta'] ?? '' }}</td>
                            <td class="px-2 py-1 border border-gray-300 text-right">{{ $fila['hilo'] ?? '' }}</td>
                            <td class="px-2 py-1 border border-gray-300 text-right">
                                {{ isset($fila['merma_sin_goma']) ? number_format((float) $fila['merma_sin_goma'], 2) : '' }}
                            </td>
                            <td class="px-2 py-1 border border-gray-300 text-right">
                                {{ isset($fila['merma_con_goma']) ? number_format((float) $fila['merma_con_goma'], 2) : '' }}
                            </td>
                            <td class="px-2 py-1 border border-gray-300">{{ $urdResumen !== '' ? $urdResumen : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalConsultarControlMerma() {
        const hoy = new Date().toISOString().split('T')[0];
        const fechaIni = '{{ $fechaIni ?? '' }}' || hoy;
        const fechaFin = '{{ $fechaFin ?? '' }}' || hoy;

        Swal.fire({
            title: 'Consultar control de merma',
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
                return { fecha_ini: fi, fecha_fin: ff };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("engomado.reportes.control-merma") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarControlMerma();
        @endif
    });
</script>
@endpush
