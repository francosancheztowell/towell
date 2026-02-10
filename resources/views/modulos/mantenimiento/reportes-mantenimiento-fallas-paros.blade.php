@extends('layouts.app')

@section('navbar-right')
<div class="flex items-center gap-2">
    <button type="button" onclick="mostrarModalFechas()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-search"></i> Consultar
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('mantenimiento.reportes.fallas-paros.excel', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </a>
    @endif
</div>
@endsection

@section('page-title', 'Fallas y Paros')
@section('content')
<div class="w-full p-4" id="reportes-mant-container">
    <div class="bg-white rounded-t-lg px-4 py-2 flex flex-wrap items-center gap-4">
        <span class="font-bold text-gray-800">Reporte Fallas y Paros</span>
        <span class="text-gray-600 text-sm">
            {{ $fechaIni ? \Carbon\Carbon::parse($fechaIni)->translatedFormat('d/m/Y') : '—' }}
            al
            {{ $fechaFin ? \Carbon\Carbon::parse($fechaFin)->translatedFormat('d/m/Y') : '—' }}
        </span>
    </div>

    <div class="flex-1 overflow-auto max-h-[70vh] rounded-b-lg border border-t-0 border-gray-300 bg-white">
        <table class="w-full border-collapse text-sm min-w-full">
            <thead>
                <tr class="text-white text-center">
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Folio</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Estatus</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Fecha</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Hora</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Departamento</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Maquina</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">TipoFalla</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Falla</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">HoraFin</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">ClaveEmpl</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">NombreEn</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Turno</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">Obs</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">CveAtendio</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">NomAtend</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">ObsCierre</th>
                    <th class="sticky top-0 z-10 bg-blue-500 px-2 py-2 font-semibold text-lg whitespace-nowrap">FechaFin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $r)
                <tr class="hover:bg-gray-50 border-b border-gray-200">
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Folio }}</td>
                    <td class="px-2 py-2 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium {{ strtolower($r->Estatus ?? '') === 'activo' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $r->Estatus ?? '—' }}
                        </span>
                    </td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Fecha ? $r->Fecha->format('d/m/Y') : '' }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Hora }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Depto }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->MaquinaId }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->TipoFallaId }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Falla }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->HoraFin }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->CveEmpl }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->NomEmpl }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Turno }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->Obs }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->CveAtendio }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->NomAtendio }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->ObsCierre }}</td>
                    <td class="px-2 py-2 text-gray-900 text-center">{{ $r->FechaFin ? $r->FechaFin->format('d/m/Y') : '' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="17" class="border border-gray-300 px-2 py-2 text-center text-gray-500">
                        {{ ($fechaIni && $fechaFin) ? 'No hay registros para el rango de fechas seleccionado.' : 'Seleccione un rango de fechas para consultar.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Rango de Fechas --}}
<div id="modal-fechas-reporte" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white max-w-md w-full rounded-xl shadow-xl p-6 m-4" onclick="event.stopPropagation()">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fa-solid fa-calendar-days text-blue-600 mr-2"></i>Rango de fechas
        </h2>
        <div class="space-y-4">
            <div>
                <label for="fecha_ini_reporte" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                <input type="date" id="fecha_ini_reporte" value="{{ $fechaIni }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="fecha_fin_reporte" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                <input type="date" id="fecha_fin_reporte" value="{{ $fechaFin }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex gap-2 mt-6">
            <button type="button" id="btn-confirmar-fechas-reporte" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm">
                Consultar
            </button>
            <button type="button" id="btn-cerrar-modal-fechas-reporte" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                Cancelar
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function mostrarModalFechas() {
    document.getElementById('modal-fechas-reporte').classList.remove('hidden');
    document.getElementById('modal-fechas-reporte').classList.add('flex');
    const hoy = new Date().toISOString().split('T')[0];
    const fi = document.getElementById('fecha_ini_reporte');
    const ff = document.getElementById('fecha_fin_reporte');
    if (!fi.value) fi.value = hoy;
    if (!ff.value) ff.value = hoy;
    fi.focus();
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-fechas-reporte');
    const fechaIni = document.getElementById('fecha_ini_reporte');
    const fechaFin = document.getElementById('fecha_fin_reporte');
    const btnConfirmar = document.getElementById('btn-confirmar-fechas-reporte');
    const btnCerrar = document.getElementById('btn-cerrar-modal-fechas-reporte');

    function cerrarModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function confirmar() {
        const fi = fechaIni.value?.trim();
        const ff = fechaFin.value?.trim();
        if (!fi || !ff) {
            alert('Seleccione fecha inicial y final');
            return;
        }
        if (new Date(fi) > new Date(ff)) {
            alert('La fecha inicial no puede ser mayor que la final');
            return;
        }
        const params = new URLSearchParams({ fecha_ini: fi, fecha_fin: ff });
        window.location.href = '{{ route("mantenimiento.reportes.fallas-paros") }}?' + params.toString();
    }

    btnConfirmar?.addEventListener('click', confirmar);
    btnCerrar?.addEventListener('click', cerrarModal);
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) cerrarModal();
    });

    @if (empty($fechaIni) || empty($fechaFin))
    mostrarModalFechas();
    @endif
});
</script>
@endpush
