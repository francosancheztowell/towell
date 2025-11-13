@extends('layouts.app')

@section('page-title', 'Programar Urdido')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-edit onclick="subirPrioridad()" title="Subir Prioridad" icon="fa-arrow-up" iconColor="text-green-500" hoverBg="hover:bg-green-100" id="btnSubirPrioridad" />
    <x-navbar.button-edit onclick="bajarPrioridad()" title="Bajar Prioridad" icon="fa-arrow-down" iconColor="text-red-500" hoverBg="hover:bg-red-100" id="btnBajarPrioridad" />
    <x-navbar.button-create onclick="cargarOrdenes()" title="Cargar Información" icon="fa-download" iconColor="text-white" hoverBg="hover:bg-blue-100"  text="Cargar" bg="bg-blue-500"  />
</div>
@endsection

@section('content')
<div class="w-full">
    {{-- Primera fila: MC Coy 1 y 2 --}}
    <div class="grid grid-cols-2 gap-2 mb-2 ">
        @for($i = 1; $i <= 2; $i++)
            {{-- Sección MC Coy {{ $i }} --}}
            <div>
                <h2 class="text-xl font-semibold text-white text-center bg-blue-500 py-1 rounded-t-xl">MC Coy {{ $i }}</h2>
                <div class="h-[256px] border border-gray-300 border-t-0 rounded-b-xl bg-white flex flex-col overflow-hidden">
                    <div class="overflow-x-auto overflow-y-auto flex-1">
                        <table class="w-full table-auto border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr class="bg-gray-100" style="height: 24px; line-height: 24px;">
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Prioridad</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Folio</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Tipo</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Cuenta</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Calibre</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Metros</th>
                                </tr>
                            </thead>
                            <tbody id="mcCoy{{ $i }}TableBody" class="bg-white">
                                <tr>

                                    <td colspan="6" class="px-2 py-2 text-center text-gray-500 text-xl">
                                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500 mx-auto"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Segunda fila: MC Coy 3 y 4 --}}
    <div class="grid grid-cols-2 gap-2">
        @for($i = 3; $i <= 4; $i++)
            {{-- Sección MC Coy {{ $i }} --}}
            <div>
                <h2 class="text-xl font-semibold text-white text-center bg-blue-500 py-1 rounded-t-xl">MC Coy {{ $i }}</h2>
                <div class="h-[256px] border border-gray-300 border-t-0 rounded-b-xl bg-white flex flex-col overflow-hidden">
                    <div class="overflow-x-auto overflow-y-auto flex-1">
                        <table class="w-full table-auto border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr class="bg-gray-100" style="height: 24px; line-height: 24px;">
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Prioridad</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Folio</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Tipo</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Cuenta</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Calibre</th>
                                    <th class="px-2 py-0 text-center font-semibold text-md border border-gray-300 align-middle" style="height: 24px; vertical-align: middle; line-height: 24px;">Metros</th>
                                </tr>
                            </thead>
                            <tbody id="mcCoy{{ $i }}TableBody" class="bg-white">
                                <tr>
                                    <td colspan="6" class="px-2 py-2 text-center text-gray-500 text-xl">
                                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-300 border-t-blue-500 mx-auto"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endfor
    </div>
</div>

<script>
// Estado de la aplicación
const state = {
    ordenes: {},
    ordenSeleccionada: null,
};

// Función para formatear badge de tipo
// Si la fila está seleccionada, usar colores que contrasten con bg-blue-500
const tipoBadge = (tipo, isSelected = false) => {
    const tipoUpper = String(tipo || '').toUpperCase().trim();

    if (isSelected) {
        // Cuando está seleccionada, usar badges con fondo blanco y texto oscuro para contrastar con el fondo azul
        if (tipoUpper === 'RIZO') {
            return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-white text-rose-700 border border-rose-300 leading-tight">Rizo</span>';
        } else if (tipoUpper === 'PIE') {
            return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-white text-teal-700 border border-teal-300 leading-tight">Pie</span>';
        }
        return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-white text-gray-800 border border-gray-300 leading-tight">' + (tipo || '-') + '</span>';
    } else {
        // Badges normales cuando no está seleccionada
        if (tipoUpper === 'RIZO') {
            return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-700 leading-tight">Rizo</span>';
        } else if (tipoUpper === 'PIE') {
            return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-teal-100 text-teal-700 leading-tight">Pie</span>';
        }
        return '<span class="px-1 py-0.5 rounded text-[10px] font-medium bg-gray-200 text-gray-800 leading-tight">' + (tipo || '-') + '</span>';
    }
};

// Función para renderizar tabla
const renderTable = (tbodyId, ordenes) => {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (!ordenes || ordenes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-2 py-2 text-center text-gray-500 text-xl">No hay órdenes pendientes</td></tr>';
        return;
    }

    const html = ordenes.map((orden, index) => {
        const isSelected = state.ordenSeleccionada?.id === orden.id;
        // La prioridad viene del backend, pero si no está, usar el índice + 1
        const prioridad = orden.prioridad !== undefined ? orden.prioridad : (index + 1);
        // Clases condicionales: si está seleccionada, bg-blue-500 text-white, sino hover:bg-gray-50
        const rowClasses = isSelected
            ? 'bg-blue-500 text-white cursor-pointer h-6'
            : 'hover:bg-gray-50 cursor-pointer h-6';
        return `
            <tr class="${rowClasses}" data-orden-id="${orden.id}" style="transition: all 0.2s ease; height: 36px; line-height: 36px;">
                <td class="px-2 py-0 text-sm text-center border border-gray-300 font-semibold whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${prioridad}</td>
                <td class="px-2 py-0 text-sm border border-gray-300 whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${orden.folio || ''}</td>
                <td class="px-2 py-0 text-sm text-center border border-gray-300 whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${tipoBadge(orden.tipo, isSelected)}</td>
                <td class="px-2 py-0 text-sm border border-gray-300 whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${orden.cuenta || ''}</td>
                <td class="px-2 py-0 text-sm border border-gray-300 whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${orden.calibre || ''}</td>
                <td class="px-2 py-0 text-sm border border-gray-300 whitespace-nowrap align-middle" style="height: 36px; vertical-align: middle; line-height: 36px;">${orden.metros ? Math.round(parseFloat(orden.metros)) : ''}</td>
            </tr>
        `;
    }).join('');

    tbody.innerHTML = html;

    // Agregar event listeners a las filas para selección
    tbody.querySelectorAll('tr[data-orden-id]').forEach(row => {
        row.addEventListener('click', (e) => {
            const ordenId = parseInt(row.dataset.ordenId);
            const mcCoy = parseInt(tbodyId.replace('mcCoy', '').replace('TableBody', ''));
            const orden = state.ordenes[mcCoy]?.find(o => o.id === ordenId);

            if (orden) {
                // Guardar la orden anterior si existe
                const ordenAnteriorId = state.ordenSeleccionada?.id;

                // Actualizar estado primero
                state.ordenSeleccionada = orden;
                actualizarBotones();

                // Re-renderizar todas las tablas para actualizar estilos y badges
                for (let i = 1; i <= 4; i++) {
                    renderTable(`mcCoy${i}TableBody`, state.ordenes[i] || []);
                }
            }
        });
    });
};

// Función para actualizar el estado de los botones
const actualizarBotones = () => {
    const btnSubir = document.getElementById('btnSubirPrioridad');
    const btnBajar = document.getElementById('btnBajarPrioridad');
    const tieneSeleccion = state.ordenSeleccionada !== null;

    if (btnSubir) btnSubir.disabled = !tieneSeleccion;
    if (btnBajar) btnBajar.disabled = !tieneSeleccion;
};

// Función para cargar órdenes desde el backend
const cargarOrdenes = async (silent = false) => {
    try {
        const response = await fetch('{{ route("urdido.programar.urdido.ordenes") }}');
        const result = await response.json();

        if (result.success) {
            state.ordenes = result.data;

            // Guardar la orden seleccionada anterior si existe
            const ordenSeleccionadaAnterior = state.ordenSeleccionada;

            // Renderizar cada tabla
            for (let i = 1; i <= 4; i++) {
                renderTable(`mcCoy${i}TableBody`, result.data[i] || []);
            }

            // Restaurar la selección si existía y la orden todavía existe
            if (ordenSeleccionadaAnterior) {
                const mcCoy = ordenSeleccionadaAnterior.mccoy;
                const ordenActualizada = state.ordenes[mcCoy]?.find(o => o.id === ordenSeleccionadaAnterior.id);
                if (ordenActualizada) {
                    state.ordenSeleccionada = ordenActualizada;
                    // Re-renderizar solo la tabla correspondiente para aplicar la selección
                    renderTable(`mcCoy${mcCoy}TableBody`, state.ordenes[mcCoy] || []);
                } else {
                    state.ordenSeleccionada = null;
                    actualizarBotones();
                }
            }

            // Mostrar notificación de éxito solo si no es una recarga silenciosa
            if (!silent && typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Órdenes cargadas correctamente',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            throw new Error(result.error || 'Error al cargar órdenes');
        }
    } catch (error) {
        console.error('Error al cargar órdenes:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar órdenes: ' + error.message,
                confirmButtonColor: '#2563eb'
            });
        } else {
            alert('Error al cargar órdenes: ' + error.message);
        }
    }
};

// Función para subir prioridad
const subirPrioridad = async () => {
    if (!state.ordenSeleccionada) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: 'Seleccione una orden',
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            alert('Por favor, seleccione una orden');
        }
        return;
    }

    try {
        const response = await fetch('{{ route("urdido.programar.urdido.subir.prioridad") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ id: state.ordenSeleccionada.id })
        });

        const result = await response.json();

        if (result.success) {
            // Guardar la orden seleccionada antes de recargar
            const ordenSeleccionadaId = state.ordenSeleccionada?.id;

            // Recargar órdenes (silenciosamente para no mostrar notificación duplicada)
            await cargarOrdenes(true);

            // Intentar restaurar la selección si la orden todavía existe
            if (ordenSeleccionadaId) {
                for (let i = 1; i <= 4; i++) {
                    const orden = state.ordenes[i]?.find(o => o.id === ordenSeleccionadaId);
                    if (orden) {
                        state.ordenSeleccionada = orden;
                        renderTable(`mcCoy${i}TableBody`, state.ordenes[i] || []);
                        break;
                    }
                }
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: result.message || 'Prioridad actualizada correctamente',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            throw new Error(result.error || 'Error al subir prioridad');
        }
    } catch (error) {
        console.error('Error al subir prioridad:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al subir prioridad: ' + error.message,
                confirmButtonColor: '#2563eb'
            });
        } else {
            alert('Error al subir prioridad: ' + error.message);
        }
    }
};

// Función para bajar prioridad
const bajarPrioridad = async () => {
    if (!state.ordenSeleccionada) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: 'Seleccione una orden',
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            alert('Por favor, seleccione una orden');
        }
        return;
    }

    try {
        const response = await fetch('{{ route("urdido.programar.urdido.bajar.prioridad") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ id: state.ordenSeleccionada.id })
        });

        const result = await response.json();

        if (result.success) {
            // Guardar la orden seleccionada antes de recargar
            const ordenSeleccionadaId = state.ordenSeleccionada?.id;

            // Recargar órdenes (silenciosamente para no mostrar notificación duplicada)
            await cargarOrdenes(true);

            // Intentar restaurar la selección si la orden todavía existe
            if (ordenSeleccionadaId) {
                for (let i = 1; i <= 4; i++) {
                    const orden = state.ordenes[i]?.find(o => o.id === ordenSeleccionadaId);
                    if (orden) {
                        state.ordenSeleccionada = orden;
                        renderTable(`mcCoy${i}TableBody`, state.ordenes[i] || []);
                        break;
                    }
                }
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: result.message || 'Prioridad actualizada correctamente',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            throw new Error(result.error || 'Error al bajar prioridad');
        }
    } catch (error) {
        console.error('Error al bajar prioridad:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al bajar prioridad: ' + error.message,
                confirmButtonColor: '#2563eb'
            });
        } else {
            alert('Error al bajar prioridad: ' + error.message);
        }
    }
};

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
    actualizarBotones();
    // Cargar órdenes automáticamente
    cargarOrdenes();
});
</script>
@endsection

