@extends('layouts.app')

@section('page-title', 'Promedio Paros y Eficiencia')

@section('navbar-right')
    <button type="button" onclick="mostrarModalPromedioParosEficiencia()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('tejido.reportes.promedio-paros-eficiencia.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">Promedio Paros y Eficiencia</h1>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6">
                <div class="border border-dashed border-gray-300 rounded-xl p-8 text-center">
                    <i class="fas fa-chart-line text-5xl text-blue-200 mb-4"></i>
                    @if (empty($fechaIni) || empty($fechaFin))
                        <p class="text-gray-600 text-lg">Seleccione un rango de fechas para generar el reporte en Excel.</p>
                        <button type="button" onclick="mostrarModalPromedioParosEficiencia()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar fechas
                        </button>
                    @else
                        <p class="text-gray-700 text-lg">El reporte esta listo para descargarse con el template de promedio de paros y eficiencia.</p>
                        <p class="text-sm text-gray-500 mt-2">Se combinan Marcas Finales y Cortes de Eficiencia por fecha, turno y telar.</p>
                        <a href="{{ route('tejido.reportes.promedio-paros-eficiencia.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                            class="inline-flex mt-5 items-center gap-2 px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-file-excel"></i> Descargar Excel
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalPromedioParosEficiencia() {
        const hoy = new Date().toISOString().split('T')[0];
        const fechaIni = '{{ $fechaIni ?? '' }}' || hoy;
        const fechaFin = '{{ $fechaFin ?? '' }}' || hoy;

        Swal.fire({
            title: 'Consultar rango',
            html: `
                <div class="text-left space-y-4">
                    <p class="text-sm text-gray-600">Seleccione la fecha inicial y final del reporte.</p>
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
                window.location.href = '{{ route('tejido.reportes.promedio-paros-eficiencia') }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
            mostrarModalPromedioParosEficiencia();
        @endif
    });
</script>
@endpush
