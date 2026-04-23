@extends('layouts.app')

@section('page-title', 'Reporte Marcas Finales')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button type="button" onclick="mostrarModalReporteMarcasFinales()"
            class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-search"></i> Consultar
        </button>
        @if (!empty($fechaIni) && !empty($fechaFin) && !$preview->isEmpty())
            <a href="{{ route('tejido.reportes.marcas-finales.export', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
        @endif
    </div>
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
                        @php
                            $turnoHeaderClass = [
                                1 => 'bg-sky-100 text-sky-800 border-sky-200',
                                2 => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                3 => 'bg-amber-100 text-amber-800 border-amber-200',
                                4 => 'bg-violet-100 text-violet-800 border-violet-200',
                            ];
                            $maquinasConPromedio = ['Jacquard Sulzer', 'Jacquard Smith', 'Smith Liso'];
                        @endphp

                        {{-- Paginación por días --}}
                        @if ($diasDisponibles->count() > 1)
                            <div class="mb-6 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">Día:</span>
                                @foreach ($diasDisponibles as $dia)
                                    <a href="{{ route('tejido.reportes.marcas-finales', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'dia' => $dia]) }}"
                                        class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $dia === $diaActual ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                        {{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        {{-- Título del día actual --}}
                        @if ($diaActual)
                            <div class="mb-4 pb-3 border-b border-gray-200">
                                <h2 class="text-lg font-bold text-gray-800">
                                    <i class="fas fa-calendar-day text-blue-500 mr-2"></i>
                                    {{ \Carbon\Carbon::parse($diaActual)->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                                </h2>
                            </div>
                        @endif

                        <div class="space-y-8">
                            @foreach ($preview as $grupoTurno)
                                <section
                                    class="border rounded-xl overflow-hidden {{ $turnoHeaderClass[$grupoTurno->turno] ?? 'border-gray-200' }}">
                                    <div
                                        class="px-5 py-3 border-b {{ $turnoHeaderClass[$grupoTurno->turno] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        <h2 class="text-base font-bold">Turno {{ $grupoTurno->turno }}</h2>
                                    </div>

                                    <div class="p-4 space-y-5 bg-white">
                                        @foreach ($grupoTurno->maquinas as $maquina)
                                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                                <div
                                                    class="bg-slate-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                                                    <h3 class="text-sm font-semibold text-slate-800">{{ $maquina->maquina }}</h3>
                                                    <div class="text-xs text-slate-600 flex items-center gap-4">
                                                        <span>Telares:
                                                            <strong>{{ number_format($maquina->total_telares, 0) }}</strong></span>
                                                        <span>Total Marcas:
                                                            <strong>{{ number_format($maquina->total_marcas, 0) }}</strong></span>
                                                    </div>
                                                </div>

                                                <div class="overflow-auto">
                                                    <table class="min-w-full text-sm">
                                                        <thead class="bg-blue-50 text-blue-800">
                                                            <tr>
                                                                <th class="px-3 py-2 text-center font-semibold border-b border-blue-100">
                                                                    Telar</th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">
                                                                    Marcas</th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">
                                                                    Horas</th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">
                                                                    Trama</th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">Pie
                                                                </th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">Rizo
                                                                </th>
                                                                <th class="px-3 py-2 text-right font-semibold border-b border-blue-100">
                                                                    Otros</th>
                                                                @if (in_array($maquina->maquina, $maquinasConPromedio, true))
                                                                    <th class="px-3 py-2 text-center font-semibold border-b border-blue-100 bg-amber-100 text-amber-800">
                                                                        Velocidad</th>
                                                                    <th class="px-3 py-2 text-right font-semibold border-b border-blue-100 bg-rose-100 text-rose-800">
                                                                        Eficiencia</th>
                                                                @endif
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            @forelse ($maquina->telares as $registro)
                                                                <tr class="hover:bg-gray-50">
                                                                    <td class="px-3 py-2 text-center tabular-nums">
                                                                        {{ number_format($registro->telar, 0) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">
                                                                        {{ number_format($registro->marcas ?? 0, 0) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ number_format((float) ($registro->horas ?? 0), 2) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ number_format($registro->trama ?? 0, 0) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ number_format($registro->pie ?? 0, 0) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ number_format($registro->rizo ?? 0, 0) }}</td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ number_format($registro->otros ?? 0, 0) }}</td>
                                                                    @if (in_array($maquina->maquina, $maquinasConPromedio, true))
                                                                        @php
                                                                            $horas = (float) ($registro->horas ?? 0);
                                                                            $marcas = (int) ($registro->marcas ?? 0);
                                                                        @endphp
                                                                        <td class="px-3 py-2 text-center bg-amber-50">
                                                                            <input type="number" step="0.01" min="0" value="0"
                                                                                class="velocidad-input w-16 px-1 py-1 text-center text-sm border border-amber-300 rounded focus:ring-1 focus:ring-amber-400 focus:border-amber-400"
                                                                                data-telar="{{ $registro->telar }}"
                                                                                data-marcas="{{ $marcas }}"
                                                                                data-horas="{{ $horas }}"
                                                                                onchange="calcularEficiencia(this)" oninput="calcularEficiencia(this)">
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right tabular-nums font-bold bg-rose-50 text-rose-700 eficiencia-cell" data-telar="{{ $registro->telar }}">
                                                                            0.00</td>
                                                                    @endif
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="{{ in_array($maquina->maquina, $maquinasConPromedio, true) ? 9 : 7 }}" class="px-3 py-3 text-center text-gray-500">Sin telares para
                                                                        esta máquina en el turno.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
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

        function calcularEficiencia(input) {
            const marcas = parseFloat(input.dataset.marcas) || 0;
            const horas = parseFloat(input.dataset.horas) || 0;
            const velocidad = parseFloat(input.value) || 0;

            let eficiencia = 0;
            if (velocidad > 0 && horas > 0) {
                eficiencia = (marcas / (velocidad * 60 * horas)) * 100000;
            }

            const row = input.closest('tr');
            const eficienciaCell = row.querySelector('.eficiencia-cell');
            if (eficienciaCell) {
                eficienciaCell.textContent = eficiencia.toFixed(2);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if (empty($fechaIni) || empty($fechaFin))
                mostrarModalReporteMarcasFinales();
            @endif
            });
    </script>
@endpush
