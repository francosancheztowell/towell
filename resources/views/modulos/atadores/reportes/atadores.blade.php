@extends('layouts.app')

@section('page-title', 'OEE Atadores')

@section('navbar-right')
    <button type="button" onclick="mostrarModalRangoFechasAtadores()"
        class="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-calendar-alt"></i> Seleccionar Fechas
    </button>
    @if (!empty($fechaIni) && !empty($fechaFin))
        <a href="{{ route('atadores.reportes.atadores.descargar', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
            class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-download"></i> Exportar Excel
        </a>
        <button type="button" onclick="exportarAOeeAtadores()"
            class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-export"></i> Exportar a OEE
        </button>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">OEE Atadores</h1>
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
                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if (empty($fechaIni) || empty($fechaFin))
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Seleccione un rango de fechas</p>
                        <p class="text-gray-400 text-sm mt-2">Puede descargar el Excel del rango seleccionado o exportar los datos al archivo <strong>OEE_ATADORES.xlsx</strong>.</p>
                        <button type="button" onclick="mostrarModalRangoFechasAtadores()"
                            class="mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i> Seleccionar Fechas
                        </button>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-table text-6xl text-blue-400 mb-4"></i>
                        <p class="text-gray-700 text-lg mb-2">Rango seleccionado</p>
                        <p class="text-gray-500 text-sm mb-6">
                            {{ \Carbon\Carbon::parse($lunesIni ?? $fechaIni)->format('d/m/Y') }}
                            al
                            {{ \Carbon\Carbon::parse($domingoFin ?? $fechaFin)->format('d/m/Y') }}
                            &mdash; solo registros <strong>Autorizado</strong> por <strong>FechaArranque</strong>.
                        </p>
                        <div class="flex items-center justify-center gap-4 flex-wrap">
                            <a href="{{ route('atadores.reportes.atadores.descargar', ['fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin]) }}"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors">
                                <i class="fas fa-download"></i> Exportar Excel
                            </a>
                            <button type="button" onclick="exportarAOeeAtadores()"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors">
                                <i class="fas fa-file-export"></i> Exportar a OEE
                            </button>
                        </div>
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

    async function exportarAOeeAtadores() {
        const fechaIni = '{{ $fechaIni ?? '' }}';
        const fechaFin = '{{ $fechaFin ?? '' }}';

        if (!fechaIni || !fechaFin) return;

        // 1. Verificar semanas con datos existentes
        Swal.fire({ title: 'Verificando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        let verificacion;
        try {
            const params = new URLSearchParams({ fecha_ini: fechaIni, fecha_fin: fechaFin });
            const resp = await fetch('{{ route("atadores.reportes.oee.verificar") }}?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            verificacion = await resp.json();
            if (verificacion.error) throw new Error(verificacion.error);
        } catch (e) {
            Swal.fire('Error', e.message || 'No se pudo verificar el archivo OEE.', 'error');
            return;
        }

        const semanasRango = verificacion.semanas_rango ?? [];
        const semanasConDatos = verificacion.semanas_con_datos ?? [];

        // 2. Confirmar con el usuario
        let html = `<p class="text-sm text-gray-600 mb-3">Se actualizarán las semanas <strong>${semanasRango.join(', ')}</strong> en <code>OEE_ATADORES.xlsx</code>.</p>`;
        if (semanasConDatos.length > 0) {
            html += `<div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                Las semanas <strong>${semanasConDatos.join(', ')}</strong> ya tienen datos y serán sobreescritas.
            </div>`;
        }

        const confirm = await Swal.fire({
            title: 'Exportar a OEE',
            html,
            icon: semanasConDatos.length > 0 ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: 'Exportar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6b7280',
        });

        if (!confirm.isConfirmed) return;

        // 3. Despachar job y hacer polling
        Swal.fire({ title: 'Procesando...', html: 'El archivo OEE se está actualizando.<br><small class="text-gray-500">Puede tomar hasta un minuto.</small>', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        let token;
        try {
            const resp = await fetch('{{ route("atadores.reportes.oee.despachar") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ fecha_ini: fechaIni, fecha_fin: fechaFin }),
            });
            const data = await resp.json();
            if (data.error) throw new Error(data.error);
            token = data.token;
        } catch (e) {
            Swal.fire('Error', e.message || 'No se pudo iniciar la exportación.', 'error');
            return;
        }

        // Polling cada 3 segundos, máximo 5 minutos
        const deadline = Date.now() + 5 * 60 * 1000;
        const interval = setInterval(async () => {
            if (Date.now() > deadline) {
                clearInterval(interval);
                Swal.fire('Tiempo agotado', 'La exportación tardó demasiado. Verifique el archivo manualmente.', 'warning');
                return;
            }
            try {
                const resp = await fetch('{{ url("atadores/reportes-atadores/oee/estado") }}/' + token, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                if (data.estado === 'completado') {
                    clearInterval(interval);
                    Swal.fire({ icon: 'success', title: '¡Listo!', text: 'Archivo OEE actualizado correctamente.', timer: 3000, showConfirmButton: false });
                } else if (data.estado === 'error') {
                    clearInterval(interval);
                    Swal.fire('Error', data.mensaje || 'No se pudo actualizar el archivo OEE.', 'error');
                }
            } catch (e) { /* red error, seguir intentando */ }
        }, 3000);
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

</script>
@endpush
