@extends('layouts.app')

@section('page-title', 'Kaizen urd-eng')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarKaizen()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('urdido.reportes.urdido.kaizen.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-kaizen-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">Kaizen AX ENGOMADO / AX URDIDO</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
        </div>

        <div class="flex flex-wrap gap-4 overflow-x-auto pb-4 bg-white border border-t-0 border-gray-300 rounded-b-lg p-4">
            {{-- AX ENGOMADO --}}
            <div class="flex-shrink-0 min-w-[400px] border border-gray-300 rounded-lg overflow-hidden">
                <div class="bg-emerald-500 px-2 py-1.5 text-center font-bold text-white border-b border-gray-300">
                    AX ENGOMADO
                </div>
                <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="sticky top-0 bg-gray-100 z-10">
                            <tr>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Fecha mod</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">AÑO</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">MES</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Código</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Localidad</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Estado</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Lote</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">CALIBRE</th>
                                <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">Cantidad</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Config</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Tamaño</th>
                                <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">Mts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($filasEngomado ?? []) as $f)
                                <tr>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['fecha_mod'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['anio'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['mes'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['codigo'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['localidad'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['estado'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['lote'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['calibre'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ isset($f['cantidad']) ? number_format($f['cantidad'], 2) : '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['configuracion'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['tamano'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ $f['mts'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- AX URDIDO --}}
            <div class="flex-shrink-0 min-w-[400px] border border-gray-300 rounded-lg overflow-hidden">
                <div class="bg-blue-500 px-2 py-1.5 text-center font-bold text-white border-b border-gray-300">
                    AX URDIDO
                </div>
                <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="sticky top-0 bg-gray-100 z-10">
                            <tr>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Fecha mod</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">AÑO</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">MES</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Código</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Localidad</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Estado</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Lote</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">CALIBRE</th>
                                <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">Cantidad</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Config</th>
                                <th class="px-1.5 py-1 text-left font-semibold text-xs border border-gray-300">Tamaño</th>
                                <th class="px-1.5 py-1 text-right font-semibold text-xs border border-gray-300">Mts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($filasUrdido ?? []) as $f)
                                <tr>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['fecha_mod'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['anio'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['mes'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['codigo'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['localidad'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['estado'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['lote'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['calibre'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ isset($f['cantidad']) ? number_format($f['cantidad'], 2) : '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['configuracion'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300">{{ $f['tamano'] ?? '' }}</td>
                                    <td class="px-1.5 py-0.5 border border-gray-300 text-right">{{ $f['mts'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (empty($filasEngomado) && empty($filasUrdido) && !empty($fechaIni) && !empty($fechaFin))
                <div class="flex-1 text-center text-gray-500 py-8">
                    No hay datos para el rango de fechas seleccionado.
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalConsultarKaizen() {
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
                        <label for="swal_solo_finalizados" class="text-sm text-gray-700">Solo finalizados</label>
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
                window.location.href = '{{ route("urdido.reportes.urdido.kaizen") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarKaizen();
        @endif
    });
</script>
@endpush
