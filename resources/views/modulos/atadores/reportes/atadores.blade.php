@extends('layouts.app')

@section('page-title', '00E Atadores')

@section('navbar-right')
    <button type="button" onclick="mostrarModalRangoSemanasAtadores()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-calendar-week"></i> Seleccionar Rango
    </button>
    @if (!empty($semanaIni) && !empty($semanaFin))
        <a href="{{ route('atadores.reportes.atadores.excel', ['semana_ini' => $semanaIni, 'semana_fin' => $semanaFin]) }}"
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
                @if (!empty($lunesIni) && !empty($domingoFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($lunesIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($domingoFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if (empty($semanaIni) || empty($semanaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-week text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione una semana inicial y final para generar el reporte</p>
                        <button type="button" onclick="mostrarModalRangoSemanasAtadores()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Rango
                        </button>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-file-excel text-6xl text-green-500 mb-4"></i>
                        <p class="text-gray-700 text-lg mb-2">Reporte listo para descargar</p>
                        <p class="text-gray-500 text-sm mb-4">
                            Se genera una sola hoja, repitiendo el formato completo por cada semana del rango, usando <strong>FechaArranque</strong> y solo registros <strong>Autorizado</strong>
                        </p>
                        <a href="{{ route('atadores.reportes.atadores.excel', ['semana_ini' => $semanaIni, 'semana_fin' => $semanaFin]) }}"
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
    function formatearSemanaIso(fecha) {
        const base = new Date(Date.UTC(fecha.getFullYear(), fecha.getMonth(), fecha.getDate()));
        const dia = base.getUTCDay() || 7;
        base.setUTCDate(base.getUTCDate() + 4 - dia);
        const anio = base.getUTCFullYear();
        const inicio = new Date(Date.UTC(anio, 0, 1));
        const semana = Math.ceil((((base - inicio) / 86400000) + 1) / 7);
        return `${anio}-W${String(semana).padStart(2, '0')}`;
    }

    function obtenerSemanaActualIso() {
        return formatearSemanaIso(new Date());
    }

    function mostrarModalRangoSemanasAtadores() {
        const semanaIni = '{{ $semanaIni ?? '' }}' || obtenerSemanaActualIso();
        const semanaFin = '{{ $semanaFin ?? '' }}' || semanaIni;

        Swal.fire({
            title: 'Seleccionar rango de semanas',
            html: `
                <div class="text-left space-y-4">
                    <div>
                        <label for="swal_semana_ini_ata" class="block text-sm font-medium text-gray-700 mb-1">Semana inicial</label>
                        <input type="week" id="swal_semana_ini_ata" value="${semanaIni}" class="swal2-input w-full" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="swal_semana_fin_ata" class="block text-sm font-medium text-gray-700 mb-1">Semana final</label>
                        <input type="week" id="swal_semana_fin_ata" value="${semanaFin}" class="swal2-input w-full" style="margin: 0; width: 100%;">
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
                const inicio = document.getElementById('swal_semana_ini_ata')?.value;
                const fin = document.getElementById('swal_semana_fin_ata')?.value;
                if (!inicio || !fin) {
                    Swal.showValidationMessage('Seleccione una semana inicial y final validas');
                    return false;
                }
                if (inicio > fin) {
                    Swal.showValidationMessage('La semana inicial no puede ser mayor que la final');
                    return false;
                }
                return { semana_ini: inicio, semana_fin: fin };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("atadores.reportes.atadores") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($semanaIni) || empty($semanaFin))
        mostrarModalRangoSemanasAtadores();
        @endif
    });
</script>
@endpush
