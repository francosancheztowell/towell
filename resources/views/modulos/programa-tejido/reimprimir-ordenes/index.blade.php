@extends('layouts.app')

@section('page-title', 'Reimprimir Órdenes')

@section('content')
<div class="container-fluid">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">
            <i class="fas fa-print mr-2"></i>
            Reimprimir Órdenes de Cambio
        </h2>

        {{-- Buscador --}}
        <div class="mb-6">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label for="buscar-orden" class="block text-sm font-medium text-gray-700 mb-2">
                        Buscar Orden
                    </label>
                    <input
                        type="text"
                        id="buscar-orden"
                        placeholder="Buscar por número de orden, nombre o clave modelo..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    />
                </div>
                <button
                    id="btn-buscar"
                    class="px-6 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors"
                >
                    <i class="fas fa-search mr-2"></i>
                    Buscar
                </button>
            </div>
        </div>

        {{-- Lista de órdenes --}}
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    Órdenes Disponibles
                </h3>
                <div class="flex items-center gap-4">
                    <span id="contador-seleccionadas" class="text-sm text-gray-600">
                        0 seleccionadas
                    </span>
                    <button
                        id="btn-seleccionar-todas"
                        class="text-sm text-purple-600 hover:text-purple-800"
                    >
                        Seleccionar todas
                    </button>
                    <button
                        id="btn-deseleccionar-todas"
                        class="text-sm text-gray-600 hover:text-gray-800"
                    >
                        Deseleccionar todas
                    </button>
                </div>
            </div>

            <div id="loading-ordenes" class="hidden text-center py-8">
                <div class="inline-block h-8 w-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-2 text-gray-600">Cargando órdenes...</p>
            </div>

            <div id="lista-ordenes" class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        Ingresa un término de búsqueda para encontrar órdenes
                    </p>
                </div>
            </div>
        </div>

        {{-- Botones de acción --}}
        <div class="flex justify-end gap-4">
            <a
                href="{{ route('planeacion.codificacion.index') }}"
                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Cancelar
            </a>
            <button
                id="btn-reimprimir"
                disabled
                class="px-6 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <i class="fas fa-print mr-2"></i>
                Reimprimir Seleccionadas
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const state = {
        ordenes: [],
        ordenesSeleccionadas: new Set(),
        loading: false
    };

    const elementos = {
        buscarInput: document.getElementById('buscar-orden'),
        btnBuscar: document.getElementById('btn-buscar'),
        listaOrdenes: document.getElementById('lista-ordenes'),
        loadingOrdenes: document.getElementById('loading-ordenes'),
        btnReimprimir: document.getElementById('btn-reimprimir'),
        contadorSeleccionadas: document.getElementById('contador-seleccionadas'),
        btnSeleccionarTodas: document.getElementById('btn-seleccionar-todas'),
        btnDeseleccionarTodas: document.getElementById('btn-deseleccionar-todas')
    };

    // Buscar órdenes
    async function buscarOrdenes() {
        const busqueda = elementos.buscarInput.value.trim();

        if (!busqueda) {
            elementos.listaOrdenes.innerHTML = `
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        Ingresa un término de búsqueda para encontrar órdenes
                    </p>
                </div>
            `;
            return;
        }

        state.loading = true;
        elementos.loadingOrdenes.classList.remove('hidden');
        elementos.listaOrdenes.classList.add('hidden');

        try {
            const response = await fetch(`{{ route('planeacion.programa-tejido.reimprimir-ordenes.buscar') }}?busqueda=${encodeURIComponent(busqueda)}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                state.ordenes = data.data || [];
                renderOrdenes();
            } else {
                showToast(data.message || 'Error al buscar órdenes', 'error');
            }
        } catch (error) {
            showToast('Error al buscar órdenes: ' + error.message, 'error');
        } finally {
            state.loading = false;
            elementos.loadingOrdenes.classList.add('hidden');
            elementos.listaOrdenes.classList.remove('hidden');
        }
    }

    // Renderizar lista de órdenes
    function renderOrdenes() {
        if (state.ordenes.length === 0) {
            elementos.listaOrdenes.innerHTML = `
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        No se encontraron órdenes
                    </p>
                </div>
            `;
            return;
        }

        const html = state.ordenes.map(orden => {
            const estaSeleccionada = state.ordenesSeleccionadas.has(orden.OrdenTejido);
            const fechaTejido = orden.FechaTejido ? new Date(orden.FechaTejido).toLocaleDateString('es-MX') : '-';
            const fechaCumplimiento = orden.FechaCumplimiento ? new Date(orden.FechaCumplimiento).toLocaleDateString('es-MX') : '-';

            return `
                <div class="px-4 py-3 border-b border-gray-200 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-4">
                        <input
                            type="checkbox"
                            class="checkbox-orden w-5 h-5 text-purple-600 rounded focus:ring-purple-500"
                            data-orden="${orden.OrdenTejido}"
                            ${estaSeleccionada ? 'checked' : ''}
                        />
                        <div class="flex-1 grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
                            <div>
                                <span class="font-semibold text-gray-700">Orden:</span>
                                <span class="ml-2 text-gray-900">${orden.OrdenTejido || '-'}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Nombre:</span>
                                <span class="ml-2 text-gray-900">${orden.Nombre || '-'}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Clave:</span>
                                <span class="ml-2 text-gray-900">${orden.ClaveModelo || '-'}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Telar:</span>
                                <span class="ml-2 text-gray-900">${orden.TelarId || orden.NoTelarId || '-'}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Fecha:</span>
                                <span class="ml-2 text-gray-900">${fechaTejido}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        elementos.listaOrdenes.innerHTML = html;

        // Agregar event listeners a los checkboxes
        document.querySelectorAll('.checkbox-orden').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const orden = this.dataset.orden;
                if (this.checked) {
                    state.ordenesSeleccionadas.add(orden);
                } else {
                    state.ordenesSeleccionadas.delete(orden);
                }
                actualizarEstado();
            });
        });
    }

    // Actualizar estado de botones y contador
    function actualizarEstado() {
        const cantidad = state.ordenesSeleccionadas.size;
        elementos.contadorSeleccionadas.textContent = `${cantidad} seleccionada${cantidad !== 1 ? 's' : ''}`;
        elementos.btnReimprimir.disabled = cantidad === 0;
    }

    // Seleccionar todas
    function seleccionarTodas() {
        state.ordenes.forEach(orden => {
            if (orden.OrdenTejido) {
                state.ordenesSeleccionadas.add(orden.OrdenTejido);
            }
        });
        renderOrdenes();
        actualizarEstado();
    }

    // Deseleccionar todas
    function deseleccionarTodas() {
        state.ordenesSeleccionadas.clear();
        renderOrdenes();
        actualizarEstado();
    }

    // Reimprimir órdenes seleccionadas
    async function reimprimirOrdenes() {
        const ordenes = Array.from(state.ordenesSeleccionadas);

        if (ordenes.length === 0) {
            showToast('Debes seleccionar al menos una orden', 'warning');
            return;
        }

        if (!confirm(`¿Estás seguro de reimprimir ${ordenes.length} orden${ordenes.length !== 1 ? 'es' : ''}?`)) {
            return;
        }

        elementos.btnReimprimir.disabled = true;
        elementos.btnReimprimir.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generando Excel...';

        try {
            const response = await fetch('{{ route("planeacion.programa-tejido.reimprimir-ordenes.reimprimir") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    ordenes: ordenes
                })
            });

            const data = await response.json();

            if (data.success && data.fileData) {
                // Descargar el archivo
                const blob = base64ToBlob(data.fileData, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.fileName || 'REIMPRESION_ORDENES.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                showToast('Órdenes reimpresas correctamente', 'success');
            } else {
                showToast(data.message || 'Error al reimprimir las órdenes', 'error');
            }
        } catch (error) {
            showToast('Error al reimprimir las órdenes: ' + error.message, 'error');
        } finally {
            elementos.btnReimprimir.disabled = false;
            elementos.btnReimprimir.innerHTML = '<i class="fas fa-print mr-2"></i>Reimprimir Seleccionadas';
        }
    }

    // Convertir base64 a Blob
    function base64ToBlob(base64, mimeType) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }

    // Event listeners
    elementos.btnBuscar.addEventListener('click', buscarOrdenes);
    elementos.buscarInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            buscarOrdenes();
        }
    });
    elementos.btnReimprimir.addEventListener('click', reimprimirOrdenes);
    elementos.btnSeleccionarTodas.addEventListener('click', seleccionarTodas);
    elementos.btnDeseleccionarTodas.addEventListener('click', deseleccionarTodas);

    // Función helper para mostrar toasts (asumiendo que existe)
    function showToast(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type === 'error' ? 'error' : type === 'success' ? 'success' : 'info',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            alert(message);
        }
    }
})();
</script>
@endpush
@endsection
