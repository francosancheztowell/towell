@extends('layouts.app')

@section('page-title', 'Reportes Engomado')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarReportesEngomado()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('engomado.reportes.engomado.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-engomado-container">
        {{-- Encabezado: total por máquina, suma total y rango --}}
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            @foreach ($porMaquina ?? [] as $maq)
                <span class="text-gray-700 text-sm">
                    <span class="font-semibold">{{ $maq['label'] }}:</span>
                    {{ number_format($maq['totalKg'] ?? 0, 2) }} Kg
                </span>
            @endforeach
            <span class="font-bold text-gray-800 border-l border-gray-300 pl-4">Total:</span>
            <span class="font-bold text-gray-900">{{ number_format($totalKg ?? 0, 2) }} Kg</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
        </div>

        {{-- Secciones por máquina --}}
        <div class="flex flex-wrap gap-4 overflow-x-auto pb-4 bg-white border border-t-0 border-gray-300 rounded-b-lg p-4">
            @foreach ($porMaquina ?? [] as $maq)
                <div class="flex-shrink-0 min-w-[200px] border border-gray-300 rounded-lg overflow-hidden">
                    <div class="bg-blue-500 px-2 py-1.5 text-center font-bold text-white border-b border-gray-300">
                        {{ $maq['label'] }}
                    </div>

                    <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr>
                                    <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">ORDEN</th>
                                    <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">JULIO</th>
                                    <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">P. NETO</th>
                                    <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">METROS</th>
                                    <th class="px-1.5 py-1 text-center font-semibold text-xs border border-gray-300">Operador</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($maq['filas'] ?? []) as $fila)
                                    <tr>
                                        <td class="px-1.5 py-0.5 border border-gray-300">{{ $fila['orden'] ?? '' }}</td>
                                        <td class="px-1.5 py-0.5 border border-gray-300">{{ $fila['julio'] ?? '' }}</td>
                                        <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ isset($fila['p_neto']) && $fila['p_neto'] !== '' ? number_format((float)$fila['p_neto'], 2) : '' }}</td>
                                        <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ $fila['metros'] ?? '' }}</td>
                                        <td class="px-1.5 py-0.5 border border-gray-300 text-center">{{ $fila['ope'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            @if (empty($porMaquina))
                <div class="flex-1 text-center text-gray-500 py-8">
                    No hay datos de producción para el rango de fechas seleccionado.
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalConsultarReportesEngomado() {
        const fechaIni = '{{ $fechaIni ?? '' }}';
        const fechaFin = '{{ $fechaFin ?? '' }}';
        const soloFinalizados = {{ ($soloFinalizados ?? true) ? 'true' : 'false' }};

        Swal.fire({
            title: 'Consultar en rango',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label for="swal_fecha_ini_eng" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                        <input type="date" id="swal_fecha_ini_eng" value="${fechaIni}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="swal_fecha_fin_eng" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                        <input type="date" id="swal_fecha_fin_eng" value="${fechaFin}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="swal_solo_finalizados_eng" ${soloFinalizados ? 'checked' : ''} class="rounded border-gray-300 text-blue-600">
                        <label for="swal_solo_finalizados_eng" class="text-sm text-gray-700">Solo finalizados</label>
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
                const fi = document.getElementById('swal_fecha_ini_eng')?.value;
                const ff = document.getElementById('swal_fecha_fin_eng')?.value;
                if (!fi || !ff) {
                    Swal.showValidationMessage('Seleccione fecha inicial y final');
                    return false;
                }
                if (new Date(fi) > new Date(ff)) {
                    Swal.showValidationMessage('La fecha inicial no puede ser mayor que la final');
                    return false;
                }
                const solo = document.getElementById('swal_solo_finalizados_eng')?.checked ? '1' : '0';
                return { fecha_ini: fi, fecha_fin: ff, solo_finalizados: solo };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("engomado.reportes.engomado.produccion") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarReportesEngomado();
        @endif
    });
</script>
@endpush
