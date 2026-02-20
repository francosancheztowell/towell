@extends('layouts.app')

@section('page-title', 'Reporte Inv Telas')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarReporteInvTelas()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('tejido.reportes.inv-telas.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
        <a href="{{ route('tejido.reportes.inv-telas.pdf', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> Descargar PDF
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
                <h1 class="text-xl font-bold text-white">Reporte Inv Telas</h1>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6 overflow-x-auto">
                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione un rango de fechas (máximo 5 días) para consultar el reporte</p>
                        <button type="button" onclick="mostrarModalConsultarReporteInvTelas()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    <table class="min-w-full border border-gray-300 text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">No. Telar</th>
                                <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">FIBRA</th>
                                <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">CALIBRE</th>
                                <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">CUENTA R/P</th>
                                @foreach ($dias as $dia)
                                    <th class="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-800">{{ $dia['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($secciones as $seccion)
                                <tr class="bg-yellow-100 font-semibold">
                                    <td colspan="{{ 4 + count($dias) }}" class="border border-gray-300 px-3 py-2">
                                        {{ $seccion['nombre'] }}
                                    </td>
                                </tr>
                                @foreach ($seccion['filas'] as $fila)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2">{{ $fila['no_telar'] }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ $fila['fibra'] ?: '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $fila['calibre'] ?: '-' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center">
                                            @php
                                                $cuentaRizo = trim((string) ($fila['cuenta_rizo'] ?? ''));
                                                $cuentaPie = trim((string) ($fila['cuenta_pie'] ?? ''));
                                            @endphp
                                            @if ($cuentaRizo !== '' && $cuentaPie !== '')
                                                {{ 'R: ' . $cuentaRizo . ' | P: ' . $cuentaPie }}
                                            @elseif ($cuentaRizo !== '')
                                                {{ 'R: ' . $cuentaRizo }}
                                            @elseif ($cuentaPie !== '')
                                                {{ 'P: ' . $cuentaPie }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        @foreach ($dias as $dia)
                                            <td class="border border-gray-300 px-3 py-2 text-center">
                                                {{ $fila['por_dia'][$dia['fecha']] ?? '' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.volverAlIndice = function() {
        window.location.href = '{{ route("tejido.reportes.index") }}';
    };

    function mostrarModalConsultarReporteInvTelas() {
        const hoy = new Date().toISOString().split('T')[0];
        const fechaIni = '{{ $fechaIni ?? '' }}' || hoy;
        const fechaFin = '{{ $fechaFin ?? '' }}' || hoy;

        Swal.fire({
            title: 'Consultar en rango',
            html: `
                <div class="text-left space-y-4">
                    <p class="text-sm text-gray-600">Seleccione un rango de máximo 5 días.</p>
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
                const dias = Math.ceil((new Date(ff) - new Date(fi)) / (1000 * 60 * 60 * 24)) + 1;
                if (dias > 5) {
                    Swal.showValidationMessage('El rango debe ser de máximo 5 días');
                    return false;
                }
                return { fecha_ini: fi, fecha_fin: ff };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const params = new URLSearchParams(result.value);
                window.location.href = '{{ route("tejido.reportes.inv-telas") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarReporteInvTelas();
        @endif
    });
</script>
@endpush
