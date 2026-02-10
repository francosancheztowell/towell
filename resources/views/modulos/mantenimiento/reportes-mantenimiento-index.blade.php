@extends('layouts.app')

@section('page-title', 'Reportes Mantenimiento')

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h1 class="text-xl font-bold text-white">Reportes Mantenimiento</h1>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach ($reportes as $num => $reporte)
                    <button type="button"
                            data-url="{{ $reporte['url'] }}"
                            class="block w-full text-left px-6 py-4 hover:bg-gray-50 transition-colors {{ !$reporte['disponible'] ? 'opacity-80 cursor-not-allowed' : 'cursor-pointer' }} btn-reporte-mant">
                        <div class="flex items-center gap-4">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                                {{ $num + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-900 block">{{ $reporte['nombre'] }}</span>
                                <span class="text-sm text-gray-500">{{ $reporte['accion'] }}</span>
                            </div>
                            @if ($reporte['disponible'])
                                <i class="fas fa-chevron-right text-gray-400 flex-shrink-0"></i>
                            @else
                                <span class="text-xs text-amber-600 font-medium flex-shrink-0">Próximamente</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Modal Rango de Fechas --}}
    <div id="modal-fechas-mant" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white max-w-md w-full rounded-xl shadow-xl p-6 m-4" onclick="event.stopPropagation()">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fa-solid fa-calendar-days text-blue-600 mr-2"></i>Rango de fechas
            </h2>
            <div class="space-y-4">
                <div>
                    <label for="fecha_ini_mant" class="block text-sm font-medium text-gray-700 mb-1">Fecha inicial</label>
                    <input type="date" id="fecha_ini_mant" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="fecha_fin_mant" class="block text-sm font-medium text-gray-700 mb-1">Fecha final</label>
                    <input type="date" id="fecha_fin_mant" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="button" id="btn-confirmar-fechas" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm">
                    Consultar
                </button>
                <button type="button" id="btn-cerrar-modal-fechas" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-fechas-mant');
    const fechaIni = document.getElementById('fecha_ini_mant');
    const fechaFin = document.getElementById('fecha_fin_mant');
    const btnConfirmar = document.getElementById('btn-confirmar-fechas');
    const btnCerrar = document.getElementById('btn-cerrar-modal-fechas');

    let urlDestino = '';

    function hoy() {
        return new Date().toISOString().split('T')[0];
    }

    function abrirModal(url) {
        urlDestino = url;
        fechaIni.value = hoy();
        fechaFin.value = hoy();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        fechaIni.focus();
    }

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
        window.location.href = urlDestino + (urlDestino.includes('?') ? '&' : '?') + params.toString();
    }

    document.querySelectorAll('.btn-reporte-mant').forEach(btn => {
        if (!btn.classList.contains('cursor-not-allowed')) {
            btn.addEventListener('click', function() {
                abrirModal(this.dataset.url || '');
            });
        }
    });

    btnConfirmar?.addEventListener('click', confirmar);
    btnCerrar?.addEventListener('click', cerrarModal);
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) cerrarModal();
    });

    fechaIni?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); confirmar(); }
    });
    fechaFin?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); confirmar(); }
    });
});
</script>
@endpush
