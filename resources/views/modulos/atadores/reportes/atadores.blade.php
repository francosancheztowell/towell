@extends('layouts.app')

@section('page-title', '00E Atadores')

@section('navbar-right')
    <button type="button" onclick="mostrarModalRangoFechasAtadores()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-calendar-alt"></i> Seleccionar Fechas
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('atadores.reportes.atadores.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">00E Atadores</h1>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <div class="text-right text-white text-sm">
                        <div>Seleccionado: {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</div>
                        @if (!empty($lunesIni) && !empty($domingoFin))
                            <div>Semanas por FechaArranque: {{ \Carbon\Carbon::parse($lunesIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($domingoFin)->format('d/m/Y') }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="p-6">
                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione una fecha inicial y final para generar el reporte</p>
                        <p class="text-gray-400 text-sm mt-2">El sistema arma las semanas de lunes a domingo usando <strong>FechaArranque</strong></p>
                        <button type="button" onclick="mostrarModalRangoFechasAtadores()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-file-excel text-6xl text-green-500 mb-4"></i>
                        <p class="text-gray-700 text-lg mb-2">Reporte listo para descargar</p>
                        <p class="text-gray-500 text-sm mb-4">
                            Se genera una sola hoja, repitiendo el formato completo por cada semana del rango. El corte semanal se obtiene de <strong>FechaArranque</strong> y solo considera registros <strong>Autorizado</strong>.
                        </p>
                        <a href="{{ route('atadores.reportes.atadores.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-download"></i> Descargar Excel
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function formatearFechaInput(fecha) {
        const year = fecha.getFullYear();
        const month = String(fecha.getMonth() + 1).padStart(2, '0');
        const day = String(fecha.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function obtenerFechaActualInput() {
        return formatearFechaInput(new Date());
    }

    function mostrarModalRangoFechasAtadores() {
        const fechaIni = '{{ $fechaIni ?? '' }}' || obtenerFechaActualInput();
        const fechaFin = '{{ $fechaFin ?? '' }}' || fechaIni;

        Swal.fire({
            title: 'Seleccionar rango de fechas',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label for="swal_fecha_ini_ata" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                        <input type="date" id="swal_fecha_ini_ata" value="${fechaIni}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="swal_fecha_fin_ata" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                        <input type="date" id="swal_fecha_fin_ata" value="${fechaFin}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">El sistema agrupa las semanas de lunes a domingo usando <strong>FechaArranque</strong>.</p>
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
                const inicio = document.getElementById('swal_fecha_ini_ata')?.value;
                const fin = document.getElementById('swal_fecha_fin_ata')?.value;
                if (!inicio || !fin) {
                    Swal.showValidationMessage('Seleccione una fecha inicial y final validas');
                    return false;
                }
                if (inicio > fin) {
                    Swal.showValidationMessage('La fecha inicial no puede ser mayor que la final');
                    return false;
                }
                return { fecha_ini: inicio, fecha_fin: fin };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("atadores.reportes.atadores") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalRangoFechasAtadores();
        @endif
    });
</script>
@endpush