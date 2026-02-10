@extends('layouts.app')

@section('page-title', 'Roturas x Millón')

@section('navbar-right')
    <button type="button" onclick="mostrarModalConsultarRoturas()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('urdido.reportes.urdido.roturas.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin, 'solo_finalizados' => ($soloFinalizados ?? true) ? '1' : '0']) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
@endsection

@section('content')
    <div class="w-full p-4" id="reportes-roturas-container">
        <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
            <span class="font-bold text-gray-800">ROTURAS X MILLÓN</span>
            <span class="text-gray-600 text-sm">
                {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
                al
                {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
            </span>
            @if (!empty($filas))
                <span class="text-gray-500 text-sm">{{ count($filas) }} órdenes</span>
            @endif
        </div>

        <div class="overflow-x-auto bg-white border border-t-0 border-gray-300 rounded-b-lg">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 bg-blue-700 text-white z-10">
                    <tr>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">MAQ</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">FECHA</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">ORDEN</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">PROVEEDOR</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">CUENTA</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">CALIBRE</th>
                        <th class="px-2 py-1.5 text-left font-semibold text-xs border border-blue-800">TIPO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MTS X JULIO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TOTAL JULIOS</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">HILOS X JULIO</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MILLÓN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MTS ORDEN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">MILLÓN MTS ANAL.</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. HILATURA</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. MÁQUINA</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">ROT. OPERACIÓN</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TRANSFER.</th>
                        <th class="px-2 py-1.5 text-right font-semibold text-xs border border-blue-800">TOTAL ROT.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($filas ?? []) as $f)
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['maq'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ isset($f['fecha']) && $f['fecha'] ? \Carbon\Carbon::parse($f['fecha'])->translatedFormat('d-M') : '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['orden'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['proveedor'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['cuenta'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['calibre'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300">{{ $f['tipo'] ?? '' }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['metros_julio'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['total_julios'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['hilos_julio'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">1,000,000</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['metros_orden'] ?? 0) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ number_format($f['millon_metros'] ?? 0, 2) }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_hilatura'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_maquina'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['rot_operacion'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right">{{ $f['transferencia'] ?? 0 }}</td>
                            <td class="px-2 py-0.5 border border-gray-300 text-right font-semibold">{{ $f['total_roturas'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18" class="px-2 py-3 text-center text-gray-500 text-xs border border-gray-300">Sin datos</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function mostrarModalConsultarRoturas() {
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
                window.location.href = '{{ route("urdido.reportes.urdido.roturas") }}?' + params.toString();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        @if (empty($fechaIni) || empty($fechaFin))
        mostrarModalConsultarRoturas();
        @endif
    });
</script>
@endpush
