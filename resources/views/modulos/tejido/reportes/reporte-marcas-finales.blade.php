@extends('layouts.app')

@section('page-title', 'Reporte Marcas Finales')

@section('navbar-right')
    <button type="button" onclick="mostrarModalReporteMarcasFinales()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
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
                <h1 class="text-xl font-bold text-white">Reporte Marcas Finales</h1>
                @if (!empty($fechaIni) && !empty($fechaFin))
                    <span class="text-white text-sm">
                        {{ \Carbon\Carbon::parse($fechaIni)->format('d/m/Y') }} al
                        {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="border border-dashed border-gray-300 rounded-xl p-8 text-center">
                        <i class="fas fa-calendar-alt text-5xl text-blue-200 mb-4"></i>
                        <p class="text-gray-600 text-lg">Seleccione un rango de fechas para mostrar el preview del reporte.</p>
                        <button type="button" onclick="mostrarModalReporteMarcasFinales()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar fechas
                        </button>
                    </div>
                @else
                    @if ($preview->isEmpty())
                        <div class="border border-dashed border-gray-300 rounded-xl p-8 text-center">
                            <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                            <p class="text-gray-600 text-lg">No hay registros de marcas finales en el rango seleccionado.</p>
                        </div>
                    @else
                        <div class="overflow-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-blue-50 text-blue-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold border-b border-blue-100">Fecha</th>
                                        <th class="px-4 py-3 text-center font-semibold border-b border-blue-100">Turno</th>
                                        <th class="px-4 py-3 text-left font-semibold border-b border-blue-100">Folio</th>
                                        <th class="px-4 py-3 text-center font-semibold border-b border-blue-100">Status</th>
                                        <th class="px-4 py-3 text-right font-semibold border-b border-blue-100">Telares</th>
                                        <th class="px-4 py-3 text-right font-semibold border-b border-blue-100">Marcas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($preview as $registro)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2">{{ \Carbon\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                                            <td class="px-4 py-2 text-center">{{ $registro->turno }}</td>
                                            <td class="px-4 py-2 font-mono">{{ $registro->folio }}</td>
                                            <td class="px-4 py-2 text-center">{{ $registro->status ?? '—' }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums">
                                                {{ number_format($registro->total_telares, 0) }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums font-semibold">
                                                {{ number_format($registro->total_marcas, 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function mostrarModalReporteMarcasFinales() {
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
                    window.location.href = '{{ route('tejido.reportes.marcas-finales') }}?' + params.toString();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if (empty($fechaIni) || empty($fechaFin))
                mostrarModalReporteMarcasFinales();
            @endif
        });
    </script>
@endpush