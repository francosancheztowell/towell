@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar requerimientos')

@section('content')
<div class="w-full">
    @if($requerimientos && $requerimientos->count() > 0)
        @php
            $primer        = $requerimientos->first();
            $selectedFolio = $primer->Folio ?? null;
            $selectedStat  = $primer->Status ?? null;
            $statusColors  = [
                'En Proceso' => 'bg-blue-100 text-blue-800',
                'En preparación' => 'bg-yellow-100 text-yellow-800',
                'Registrado' => 'bg-purple-100 text-purple-800',
                'Surtido Parcial' => 'bg-green-100 text-green-800',
                'Solicitado' => 'bg-orange-100 text-orange-800',
                'Surtido'    => 'bg-green-100 text-green-800',
                'Cancelado'  => 'bg-red-100 text-red-800',
            ];
            $turnoDesc     = ['1' => 'Turno 1', '2' => 'Turno 2', '3' => 'Turno 3'];
        @endphp

        <div class="bg-white overflow-hidden w-full">
            <div class="p-4 sm:p-6 md:p-8 lg:p-8 w-full">
                <div class="mb-6 flex flex-col md:flex-row lg:flex-row gap-3 sm:gap-4 md:gap-6 lg:gap-6 w-full">
                    <!-- Tabla 1: Folios -->
                    <div class="flex-1 border border-gray-300 rounded-lg overflow-hidden min-w-0 w-full">
                        <div class="overflow-y-auto h-32 md:h-32 lg:h-48">
                            <table class="w-full">
                                <thead class="sticky top-0 z-10">
                                    <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Status</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Turno</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Operador</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="tbody-folios">
                                    @foreach($requerimientos as $req)
                                        @php
                                            $statusClass = $statusColors[$req->Status] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <tr class="hover:bg-gray-50 cursor-pointer {{ $req->Folio === $selectedFolio ? 'bg-blue-100' : '' }}" data-folio="{{ $req->Folio }}">
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ $req->Folio }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ \Carbon\Carbon::parse($req->Fecha)->format('d/m/Y') }}</td>
                                            <td class="px-4 py-1 border-r border-gray-200">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">{{ $req->Status }}</span>
                                            </td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900 border-r border-gray-200">{{ $turnoDesc[$req->Turno] ?? $req->Turno }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold text-gray-900">{{ $req->numero_empleado ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Acciones dinámicas -->
                    <div class="flex flex-col space-y-2 lg:min-w-48" id="acciones-contenedor">
                        <button id="btn-solicitar" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-list mr-2"></i>Solicitar consumo
                        </button>
                        <button id="btn-editar" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-edit mr-2"></i>Editar
                        </button>
                        <button id="btn-cancelar" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button id="btn-resumen" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition-colors">
                            <i class="fas fa-eye mr-2"></i>Resumen de articulo
                        </button>
                        <input type="hidden" id="folio-seleccionado" value="{{ $selectedFolio }}">
                        <input type="hidden" id="status-seleccionado" value="{{ $selectedStat }}">
                    </div>
                </div>

                <!-- Tabla 2: Detalles del folio seleccionado -->
                <div class="border border-gray-300 rounded-lg overflow-hidden w-full">
                    <div class="overflow-y-auto h-32 md:h-96 xl:h-96">
                        <table class="w-full">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Folio</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Telar</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Articulo</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="detalles-tbody">
                                @if($primer && $primer->consumos && $primer->consumos->count() > 0)
                                    @foreach($primer->consumos as $consumo)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $primer->Folio }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->NoTelarId }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->CalibreTrama ? number_format($consumo->CalibreTrama, 2) : '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->NombreProducto ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->FibraTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->CodColorTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo->ColorTrama ?? '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $consumo->Cantidad ? number_format($consumo->Cantidad, 0) : '0' }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                            <i class="fas fa-inbox text-2xl mb-2"></i>
                                            <p>No hay consumos registrados</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Estado vacío -->
        <div id="no-requerimientos">
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <i class="fas fa-trash text-2xl mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                    <p class="text-gray-500">No se encontraron requerimientos guardados en el sistema</p>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
(function(){
    const $ = (sel, ctx=document) => ctx.querySelector(sel);
    const $$= (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

    const Endpoints = {
        detalles: folio => `/modulo-consultar-requerimiento/${folio}`,
        status:   folio => `/modulo-consultar-requerimiento/${folio}/status`,
        resumen:  folio => `/modulo-consultar-requerimiento/${folio}/resumen`,
        editar:   folio => @json(route('tejido.inventario.trama.nuevo.requerimiento')) + `?folio=${encodeURIComponent(folio)}`
    };

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // --------- Init ---------
    document.addEventListener('DOMContentLoaded', () => {
        bindFoliosTable();
        bindAcciones();
        autoSelectFromQueryOrFirst();
    });

    function bindFoliosTable(){
        const tbody = $('#tbody-folios');
        if (!tbody) return;
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-folio]');
            if (!tr) return;
            const folio = tr.getAttribute('data-folio');
            selectFolio(folio, tr);
        });
    }

    function bindAcciones(){
        $('#btn-solicitar')?.addEventListener('click', () => accionStatus('Solicitado'));
        $('#btn-editar')?.addEventListener('click', editarFolioSeleccionado);
        $('#btn-cancelar')?.addEventListener('click', () => accionStatus('Cancelado'));
        $('#btn-resumen')?.addEventListener('click', verResumenSeleccionado);
    }

    function autoSelectFromQueryOrFirst(){
        try {
            const params = new URLSearchParams(location.search);
            const folio  = params.get('folio');
            const row    = folio ? $(`#tbody-folios tr[data-folio="${folio}"]`) : null;
            if (row) { selectFolio(folio, row); row.scrollIntoView({ behavior:'smooth', block:'center' }); return; }
            if (!folio) {
                const first = $('#tbody-folios tr[data-folio]');
                if (first) selectFolio(first.getAttribute('data-folio'), first);
            } else {
                // cargar detalles directos si no está listado
                fetchDetalles(folio).then(({ok, req, consumos}) => { if (!ok) return; setSelectedFolio(folio, req?.Status); renderDetalles(consumos, folio); });
            }
        } catch(_) {}
    }

    // --------- Core UI ---------
    function selectFolio(folio, rowEl, forzarRecarga = false){
        $$('#tbody-folios tr').forEach(tr => tr.classList.remove('bg-blue-100'));
        if (rowEl) rowEl.classList.add('bg-blue-100');
        fetchDetalles(folio, forzarRecarga).then(({ok, req, consumos}) => {
            if (!ok) return;
            setSelectedFolio(folio, req?.Status);
            renderDetalles(consumos, folio);
        });
    }

    function setSelectedFolio(folio, status){
        $('#folio-seleccionado').value  = folio || '';
        $('#status-seleccionado').value = status || '';
        actualizarBotonesPorEstado();
    }

    function renderDetalles(consumos, folio){
        const tbody = $('#detalles-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!Array.isArray(consumos) || !consumos.length){
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-4 text-center text-gray-500"><i class="fas fa-inbox text-2xl mb-2"></i><p>No hay consumos registrados</p></td></tr>`;
            return;
        }
        // Ordenar por NoTelarId (ascendente)
        const consumosOrdenados = [...consumos].sort((a, b) => {
            const telarA = a.NoTelarId ?? '';
            const telarB = b.NoTelarId ?? '';
            // Convertir a número si es posible, sino comparar como string
            const numA = parseInt(telarA) || 0;
            const numB = parseInt(telarB) || 0;
            if (numA !== 0 && numB !== 0) {
                return numA - numB;
            }
            return String(telarA).localeCompare(String(telarB));
        });
        const rows = consumosOrdenados.map(c => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${folio}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NoTelarId ?? '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CalibreTrama ? Number(c.CalibreTrama).toFixed(2) : '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.NombreProducto ?? '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.FibraTrama ?? '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.CodColorTrama ?? '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">${c.ColorTrama ?? '-'}</td>
                <td class="px-4 py-2 text-sm text-gray-900">${c.Cantidad ? Number(c.Cantidad).toFixed(0) : '0'}</td>
            </tr>`).join('');
        tbody.insertAdjacentHTML('beforeend', rows);
    }

    function actualizarBotonesPorEstado(){
        const status = $('#status-seleccionado')?.value || '';
        const btnSolicitar = $('#btn-solicitar');
        const btnEditar    = $('#btn-editar');
        const btnCancelar  = $('#btn-cancelar');

        [btnSolicitar, btnEditar, btnCancelar].forEach(b => b?.classList.remove('hidden'));
        if (status === 'En Proceso') return; // puede todo
        if (status === 'Solicitado'){ btnSolicitar?.classList.add('hidden'); btnEditar?.classList.add('hidden'); return; }
        if (status === 'Surtido'){ btnSolicitar?.classList.add('hidden'); btnEditar?.classList.add('hidden'); btnCancelar?.classList.add('hidden'); return; }
        if (status === 'Cancelado'){ btnSolicitar?.classList.add('hidden'); btnEditar?.classList.add('hidden'); btnCancelar?.classList.add('hidden'); return; }
    }

    // --------- Fetch helpers ---------
    async function fetchDetalles(folio, forzarRecarga = false){
        try {
            // Agregar timestamp para evitar caché si se fuerza recarga
            const url = forzarRecarga
                ? `${Endpoints.detalles(folio)}?_t=${Date.now()}`
                : Endpoints.detalles(folio);
            const r = await fetch(url, {
                cache: forzarRecarga ? 'no-cache' : 'default',
                headers: forzarRecarga ? { 'Cache-Control': 'no-cache' } : {}
            });
            const data = await r.json();
            return { ok: !!data.success, req: data.requerimiento, consumos: data.consumos || [] };
        } catch(_) { return { ok:false, req:null, consumos:[] }; }
    }

    async function postStatus(folio, nuevoStatus){
        const r = await fetch(Endpoints.status(folio), {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ status: nuevoStatus })
        });
        return r.json();
    }

    // --------- API: funciones públicas (para compatibilidad) ---------
    window.selectFolio = selectFolio; // por si lo invocan externamente

    window.accionStatus = function(nuevoStatus){
        const folio = $('#folio-seleccionado')?.value; if (!folio) return;
        cambiarStatus(folio, nuevoStatus);
    };

    window.verResumenSeleccionado = function(){
        const folio = $('#folio-seleccionado')?.value; if (!folio) return;
        verResumen(folio);
    };

    window.cambiarStatus = function(folio, nuevoStatus){
        Swal.fire({ title:'Confirmación', text:`¿Está seguro de cambiar el status a "${nuevoStatus}"?`, icon:'question', showCancelButton:true, confirmButtonText:'Sí, continuar', cancelButtonText:'No, cancelar' })
            .then(async (result) => {
                if (!result.isConfirmed) return;
                try {
                    const data = await postStatus(folio, nuevoStatus);
                    if (data.success) {
                        await Swal.fire({ icon:'success', title:'Actualizado', text: data.message || 'Status actualizado correctamente' });
                        location.reload(); // mantener comportamiento original
                    } else {
                        Swal.fire({ icon:'error', title:'Error', text: data.message || 'No se pudo actualizar el status' });
                    }
                } catch (e) {
                    Swal.fire({ icon:'error', title:'Error', text:'Error al actualizar el status' });
                }
            });
    };

    window.verResumen = function(folio){
        window.open(Endpoints.resumen(folio), '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    };

    window.editarFolioSeleccionado = function(){
        const folio = $('#folio-seleccionado')?.value; if (!folio) return;
        // Guardar en sessionStorage que se está yendo a editar
        sessionStorage.setItem('editandoFolio', folio);
        sessionStorage.setItem('editandoTimestamp', Date.now().toString());
        location.href = Endpoints.editar(folio);
    };

    // Detectar cuando se regresa de edición (navegación hacia atrás)
    window.addEventListener('pageshow', function(event) {
        // pageshow se dispara cuando la página se muestra, incluyendo navegación hacia atrás
        if (event.persisted || (performance.navigation && performance.navigation.type === 2)) {
            // Página cargada desde caché (navegación hacia atrás)
            const folioEditado = sessionStorage.getItem('editandoFolio');
            const folioGuardado = sessionStorage.getItem('folioGuardado');
            const folioARecargar = folioGuardado || folioEditado;

            if (folioARecargar) {
                // Limpiar las marcas
                sessionStorage.removeItem('editandoFolio');
                sessionStorage.removeItem('editandoTimestamp');
                sessionStorage.removeItem('folioGuardado');
                sessionStorage.removeItem('folioGuardadoTimestamp');
                // Recargar los detalles del folio que se editó
                const row = $(`#tbody-folios tr[data-folio="${folioARecargar}"]`);
                if (row) {
                    // Forzar recarga de datos (no usar caché)
                    selectFolio(folioARecargar, row, true);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    // Si no está en la lista, recargar la página completa
                    location.reload();
                }
            }
        }
    });

    // También detectar cuando la página se vuelve visible (por si acaso)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Página visible de nuevo
            const folioEditado = sessionStorage.getItem('editandoFolio');
            const timestamp = sessionStorage.getItem('editandoTimestamp');
            if (folioEditado && timestamp) {
                // Si pasó más de 1 segundo desde que se marcó, probablemente ya se editó
                const tiempoTranscurrido = Date.now() - parseInt(timestamp);
                if (tiempoTranscurrido > 1000) {
                    sessionStorage.removeItem('editandoFolio');
                    sessionStorage.removeItem('editandoTimestamp');
                    const row = $(`#tbody-folios tr[data-folio="${folioEditado}"]`);
                    if (row) {
                        selectFolio(folioEditado, row, true);
                    }
                }
            }
        }
    });
})();
</script>
@endsection
