@props([
    'telar',
    'ordenSig' => null,
    'salon' => 'Jacquard',
    'dias' => 7,
    'turnos' => 3
])

@php
    //esta es una funcion para verificar si el usuario tiene permiso de crear requerimientos
    // Verificar permisos del usuario actual
    $usuarioActual = Auth::user();
    $idusuario = $usuarioActual ? $usuarioActual->idusuario : null;

    // Obtener permisos del usuario para el módulo "Requerimientos" (idrol 21)
    $permisos = null;
    if ($idusuario) {
        $permisos = \App\Models\SYSUsuariosRoles::where('idusuario', $idusuario)
            ->where('idrol', 21) // Requerimientos
            ->first();
    }

    // Verificar si tiene permiso de crear
    $puedeCrear = $permisos ? $permisos->crear == 1 : false;
@endphp

<div class="p-3 md:p-1.5 lg:p-3">
    <div class="md:flex md:flex-col lg:flex-row">
        <!-- Información de cuentas -->
        <div class="mb-2 md:mb-1.5 lg:mb-0 mr-0 md:mr-0 lg:mr-4 mt-0 md:mt-0 lg:mt-[32px] rounded-lg p-3 md:p-1.5 lg:p-3 border border-gray-200">
            <div class="text-sm md:text-[10px] lg:text-sm font-semibold text-gray-700 mb-2 md:mb-0.5 lg:mb-2 md:inline md:mr-2 lg:block lg:mr-0">Cuentas:</div>
            <div class="space-y-1 md:space-y-0 md:inline-flex md:gap-3 lg:space-y-1 lg:gap-0 lg:block text-sm md:text-[10px] lg:text-sm">
                <div class="flex items-center md:justify-start lg:justify-between">
                <div class="flex items-center">
                    <span class="font-medium text-gray-600">RIZO</span>
                        <span class="ml-2 md:ml-1 lg:ml-2 font-bold text-blue-600" id="cuenta-rizo-{{ $telar->Telar }}">
                            {{ $telar->Cuenta ?? '' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="ml-2 md:ml-1 lg:ml-2 p-1 md:p-0.5 lg:p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                        title="Seleccionar cuenta RIZO"
                        onclick="abrirModalSeleccion('{{ $telar->Telar }}', 'rizo', '{{ $telar->Cuenta ?? '' }}', '{{ $telar->Calibre_Rizo ?? '' }}', '{{ $telar->Fibra_Rizo ?? '' }}')"
                    >
                        <svg class="w-4 h-4 md:w-3 md:h-3 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex items-center md:justify-start lg:justify-between">
                <div class="flex items-center">
                    <span class="font-medium text-gray-600">PIE</span>
                        <span class="ml-2 md:ml-1 lg:ml-2 font-bold text-blue-600" id="cuenta-pie-{{ $telar->Telar }}">
                            {{ $telar->Cuenta_Pie ?? '' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="ml-2 md:ml-1 lg:ml-2 p-1 md:p-0.5 lg:p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                        title="Seleccionar cuenta PIE"
                        onclick="abrirModalSeleccion('{{ $telar->Telar }}', 'pie', '{{ $telar->Cuenta_Pie ?? '' }}', '{{ $telar->Calibre_Pie ?? '' }}', '{{ $telar->Fibra_Pie ?? '' }}')"
                    >
                        <svg class="w-4 h-4 md:w-3 md:h-3 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="lg:flex lg:items-start">
            <div class="mr-4 md:mr-1.5 lg:mr-4 hidden lg:block">
                <b id="fecha-{{ $telar->Telar }}"></b>
            </div>

            <!-- Calendario de turnos generado dinámicamente -->
            <div class="flex gap-1 overflow-x-auto pb-2">
        @for($dia = 0; $dia < $dias; $dia++)
            @php
                $fecha = \Carbon\Carbon::now()->addDays($dia)->format('d/m');
                $fechaCompleta = \Carbon\Carbon::now()->addDays($dia)->format('d-m-Y');
                $claseTabla = 't' . ($dia + 1);
                $prefijoId = $telar->Telar . '_' . $claseTabla;
                // Mapeo de días en español
                $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                $diaSemana = $diasSemana[\Carbon\Carbon::now()->addDays($dia)->dayOfWeek];
            @endphp

            <table class="border border-gray-300 rounded overflow-hidden shadow-sm w-24 flex-shrink-0">
                <thead>
                    <tr>
                        <th colspan="{{ $turnos }}" class="text-center border-b border-gray-300 bg-blue-500 text-white px-1 py-1 text-xs font-bold">
                            <div class="text-xs leading-tight">{{ $fecha }}</div>
                            <div class="text-xs opacity-75 leading-tight">{{ $diaSemana }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @for($turno = 1; $turno <= $turnos; $turno++)
                            <td class="border border-gray-200 text-center px-1 py-1 bg-white min-w-[40px]">
                                <div class="font-bold text-gray-700 mb-1 text-xs">{{ $turno }}</div>
                                <div class="space-y-0.5">
                                    <label class="block">
                                        <input
                                            type="checkbox"
                                            name="rizo{{ $turno }}"
                                            class="{{ $claseTabla }}rizo w-3 h-3 text-blue-600 rounded border-gray-300 focus:ring-blue-500 {{ !$puedeCrear ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            value="rizo{{ $turno }}"
                                            id="{{ $prefijoId }}_rizo{{ $turno }}"
                                            data-telar="{{ $telar->Telar }}"
                                            data-tipo="rizo"
                                            {{ !$puedeCrear ? 'disabled' : '' }}
                                        >
                                    </label>
                                    <label class="block">
                                        <input
                                            type="checkbox"
                                            name="pie{{ $turno }}"
                                            class="{{ $claseTabla }}pie w-3 h-3 text-blue-600 rounded border-gray-300 focus:ring-blue-500 {{ !$puedeCrear ? 'opacity-50 cursor-not-allowed' : '' }}"
                                            value="pie{{ $turno }}"
                                            id="{{ $prefijoId }}_pie{{ $turno }}"
                                            data-telar="{{ $telar->Telar }}"
                                            data-tipo="pie"
                                            {{ !$puedeCrear ? 'disabled' : '' }}
                                        >
                                    </label>
                                </div>
                            </td>
                        @endfor
                    </tr>
                </tbody>
            </table>
        @endfor
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección -->
<div id="modalSeleccion" class="fixed inset-0 bg-black/40 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center">
    <div class="relative mx-auto p-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white transform transition-all">
        <!-- Header del Modal con gradiente -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold" id="modalTitulo">Telar JACQUARD SULZER <span id="modalTelarNumero" class="text-yellow-300"></span></h3>
                <button type="button" onclick="cerrarModalSeleccion()" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Contenido del Modal -->
        <div class="p-6">
            <!-- Tabla de Selección mejorada -->
            <div class="overflow-hidden border border-gray-200 rounded-lg shadow-sm">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Seleccionar</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cuenta</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Calibre</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Fibra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- Fila: Producción en Proceso -->
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="proceso" id="radioProceso" class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500 focus:ring-2">
                            </td>
                            <td class="px-4 py-4 border-r border-gray-200 text-base font-semibold text-gray-900" id="cuentaProceso">-</td>
                            <td class="px-4 py-4 border-r border-gray-200 text-base font-semibold text-gray-900" id="calibreProceso">-</td>
                            <td class="px-4 py-4 text-base font-semibold text-gray-900" id="fibraProceso">-</td>
                        </tr>
                        <!-- Fila: Siguiente Orden -->
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="siguiente" id="radioSiguiente" class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500 focus:ring-2">
                            </td>
                            <td class="px-4 py-4 border-r border-gray-200 text-base font-semibold text-gray-900" id="cuentaSiguiente">-</td>
                            <td class="px-4 py-4 border-r border-gray-200 text-base font-semibold text-gray-900" id="calibreSiguiente">-</td>
                            <td class="px-4 py-4 text-base font-semibold text-gray-900" id="fibraSiguiente">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Botones de Acción mejorados -->
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cerrarModalSeleccion()" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200">
                    Cancelar
                </button>
                <button type="button" onclick="confirmarSeleccion()" class="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 border border-transparent rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg transition-all duration-200">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Crear scope aislado para este telar
    const telarId = {{ $telar->Telar }};
    const telarData = @json($telar);
    const ordenSigData = @json($ordenSig);
    const salonTelar = '{{ $salon }}'; // Jacquard, Itema, etc.

    function inicializarTelar() {
        // Configurar event listeners para checkboxes con datos específicos de este telar
        setupRequerimientoCheckboxes(telarId, telarData, ordenSigData, salonTelar);

        // Cargar requerimientos existentes (filtrado por salón)
        loadRequerimientos(telarId, salonTelar);

        // Cargar inventario de telares al inicio (solo una vez)
        if (!window.inventarioCargado) {
            obtenerInventarioTelares();
            window.inventarioCargado = true;
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarTelar);
    } else {
        // DOM ya está listo, ejecutar inmediatamente
        inicializarTelar();
    }
})();

function setupRequerimientoCheckboxes(telarId, telarData, ordenSigData, salon) {
    const checkboxes = document.querySelectorAll(`input[data-telar="${telarId}"]`);

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            handleRequerimientoChange(this, telarId, telarData, ordenSigData, salon);
        });
    });
}

function handleRequerimientoChange(checkbox, telarId, telarData, ordenSigData, salon) {
    const fila = checkbox.closest('tr');
    const tabla = fila.closest('table');
    const fechaElement = tabla.querySelector('th');
    const fecha = fechaElement ? fechaElement.innerText.trim() : '';

    const valorCheckbox = checkbox.value;
    const tipo = checkbox.dataset.tipo;

    // Usar datos pasados como parámetros (específicos de este telar)
    const datos = telarData;
    const ordenSiguiente = ordenSigData;

    const cuentaRizo = datos.Cuenta || '';
    const cuentaPie = datos.Cuenta_Pie || '';
    const calibreRizo = datos.Calibre_Rizo || 0;
    const calibrePie = datos.Calibre_Pie || 0;
    const hilo = datos.Hilo || '';

    // Extraer el número de turno del valor del checkbox (ej: "rizo1" -> 1, "pie2" -> 2)
    const numeroTurno = parseInt(valorCheckbox.replace(/\D/g, ''));

    // Preparar datos para envío
    const rizo = tipo === 'rizo' ? 1 : 0;
    const pie = tipo === 'pie' ? 1 : 0;

    // Desmarcar otros checkboxes del mismo tipo en todas las fechas
    document.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`).forEach(cb => {
        if (cb !== checkbox) cb.checked = false;
    });

    // Convertir fecha del formato dd/mm a formato ISO (YYYY-MM-DD)
    function convertirFecha(fechaTexto) {
        // Extraer solo la fecha dd/mm del texto (puede incluir día de la semana)
        const fechaMatch = fechaTexto.match(/(\d{1,2})\/(\d{1,2})/);
        if (fechaMatch) {
            const dia = fechaMatch[1].padStart(2, '0');
            const mes = fechaMatch[2].padStart(2, '0');
            const año = new Date().getFullYear();
            return `${año}-${mes}-${dia}`;
        }
        return null;
    }

    // Validar que tenemos los datos necesarios
    const cuentaSeleccionada = tipo === 'rizo' ? cuentaRizo : cuentaPie;
    const calibreSeleccionado = tipo === 'rizo' ? calibreRizo : calibrePie;

    // Convertir fecha y validar
    const fechaConvertida = convertirFecha(fecha);
    if (!fechaConvertida) {
        alert('Error al extraer la fecha del calendario. Intente de nuevo.');
        checkbox.checked = false;
        return;
    }

    // Verificar que tenemos cuenta válida (calibre puede ser null)
    if (!cuentaSeleccionada || cuentaSeleccionada === '') {
        alert('No se encontró cuenta para este telar. Verifique los datos del telar.');
        checkbox.checked = false; // Desmarcar checkbox
        return;
    }

    // Verificar si hay una selección guardada en el modal para este tipo
    const seleccionGuardada = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];

    // Determinar TODOS los datos según la selección del modal
    let cuentaFinal, calibreFinal, hiloSeleccionado, noOrden;

    if (seleccionGuardada) {
        // Usar datos del modal si hay selección guardada (proceso o siguiente)
        cuentaFinal = seleccionGuardada.datos?.cuenta || cuentaSeleccionada;
        calibreFinal = seleccionGuardada.datos?.calibre || calibreSeleccionado;
        hiloSeleccionado = seleccionGuardada.datos?.fibra || '';
        noOrden = String(seleccionGuardada.ordenProd || '');
    } else {
        // Por defecto: usar datos del proceso actual
        cuentaFinal = cuentaSeleccionada;
        calibreFinal = calibreSeleccionado;
        hiloSeleccionado = tipo === 'rizo' ? (datos.Fibra_Rizo || '') : (datos.Fibra_Pie || '');
        noOrden = String(datos.Orden_Prod || '');
    }

    // Preparar datos para la nueva tabla TejInventarioTelares
    const datosInventario = {
        no_telar: String(telarId),
        tipo: tipo === 'rizo' ? 'Rizo' : 'Pie',
        cuenta: String(cuentaFinal),
        calibre: calibreFinal ? parseFloat(calibreFinal) : null,
        fecha: fechaConvertida,
        turno: parseInt(numeroTurno),
        salon: salon, // Usar el salón correcto del componente
        hilo: hiloSeleccionado || '',
        no_orden: noOrden || ''
    };

    // Enviar datos a la nueva tabla de inventario
    axios.post('/inventario-telares/guardar', datosInventario, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        // Después de guardar exitosamente, obtener los datos actualizados
        obtenerInventarioTelares();

        // Mostrar notificación de éxito
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Guardado con éxito',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                position: 'bottom-end',
                toast: true
            });
        }
    })
    .catch(error => {
        // Mostrar notificación de error con más detalles
        if (typeof Swal !== 'undefined') {
            let errorMessage = 'Error desconocido';

            if (error.response?.data?.errors) {
                // Mostrar errores de validación
                const errors = error.response.data.errors;
                errorMessage = Object.values(errors).flat().join(', ');
            } else if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: errorMessage,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                position: 'bottom-end',
                toast: true
            });
        }
    });

    // NOTA: Sistema anterior deshabilitado - ahora usamos solo /inventario-telares/guardar
    // El endpoint /guardar-requerimiento causaba errores de SQL y ya no es necesario
}

function loadRequerimientos(telarId, salon) {
    // Usar inventario real para marcar selección (GET)
    fetch('/inventario-telares')
        .then(r => r.json())
        .then(json => {
            const registros = json?.data || [];

            // Limitar la búsqueda al contenedor del telar correspondiente
            const container = document.getElementById(`telar-${telarId}`) || document;
            const fechasTablas = container.querySelectorAll('th');

            // Limpiar todos los checkboxes SOLO dentro de este telar
            container.querySelectorAll(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                checkbox.checked = false;
            });

            // Marcar por coincidencia de telar+tipo+fecha+turno Y SALON
            const registrosTelar = registros.filter(reg =>
                String(reg.no_telar) === String(telarId) &&
                String(reg.salon || '').toLowerCase() === String(salon || '').toLowerCase()
            );

            registrosTelar.forEach(reg => {
                const tipo = (reg.tipo || '').toLowerCase();
                const fechaISO = reg.fecha;
                const [y, m, d] = (fechaISO || '').split('-');
                const fechaDM = d && m ? `${parseInt(d)}/${parseInt(m)}` : '';

                const thFecha = Array.from(fechasTablas).find(th => {
                    const texto = (th.innerText || '').trim();
                    return texto.includes(fechaDM);
                });

                if (!thFecha) return;

                const table = thFecha.closest('table');
                if (!table) return;

                const valorEsperado = `${tipo}${reg.turno}`;
                const checkboxes = table.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                checkboxes.forEach(cb => {
                    if (cb.value === valorEsperado) cb.checked = true;
                });
            });
        })
        .catch(error => {
            // Error silencioso
        });
}

// Variables globales para el modal
if (typeof window.modalData === 'undefined') {
    window.modalData = {
        telarId: null,
        tipo: null,
        datosProceso: null,
        datosSiguiente: null,
        seleccionGuardada: {} // Guardar selecciones por telar y tipo
    };
}

// Función para abrir el modal de selección
function abrirModalSeleccion(telarId, tipo, cuenta, calibre, fibra) {
    window.modalData.telarId = telarId;
    window.modalData.tipo = tipo;

    // Actualizar título del modal
    document.getElementById('modalTelarNumero').textContent = telarId;

    // Obtener datos del proceso actual y siguiente orden
    Promise.all([
        obtenerDatosProcesoActual(telarId),
        obtenerDatosSiguienteOrden(telarId)
    ]).then(([datosProceso, datosSiguiente]) => {
        // Configurar datos del proceso actual según el tipo (RIZO o PIE)
        window.modalData.datosProceso = {
            cuenta: tipo === 'rizo' ?
                (datosProceso?.Cuenta && datosProceso.Cuenta.trim() !== '' ? datosProceso.Cuenta : '-') :
                (datosProceso?.Cuenta_Pie && datosProceso.Cuenta_Pie.trim() !== '' ? datosProceso.Cuenta_Pie : '-'),
            calibre: tipo === 'rizo' ?
                (datosProceso?.Calibre_Rizo && datosProceso.Calibre_Rizo !== '' ? datosProceso.Calibre_Rizo : '-') :
                (datosProceso?.Calibre_Pie && datosProceso.Calibre_Pie !== '' ? datosProceso.Calibre_Pie : '-'),
            fibra: tipo === 'rizo' ?
                (datosProceso?.Fibra_Rizo && datosProceso.Fibra_Rizo.trim() !== '' ? datosProceso.Fibra_Rizo : '-') :
                (datosProceso?.Fibra_Pie && datosProceso.Fibra_Pie.trim() !== '' ? datosProceso.Fibra_Pie : '-'),
            ordenProd: datosProceso?.Orden_Prod || ''
        };

        // Configurar datos de la siguiente orden según el tipo (RIZO o PIE)
        window.modalData.datosSiguiente = {
            cuenta: tipo === 'rizo' ?
                (datosSiguiente?.Cuenta && datosSiguiente.Cuenta.trim() !== '' ? datosSiguiente.Cuenta : '-') :
                (datosSiguiente?.Cuenta_Pie && datosSiguiente.Cuenta_Pie.trim() !== '' ? datosSiguiente.Cuenta_Pie : '-'),
            calibre: tipo === 'rizo' ?
                (datosSiguiente?.Calibre_Rizo && datosSiguiente.Calibre_Rizo !== '' ? datosSiguiente.Calibre_Rizo : '-') :
                (datosSiguiente?.Calibre_Pie && datosSiguiente.Calibre_Pie !== '' ? datosSiguiente.Calibre_Pie : '-'),
            fibra: tipo === 'rizo' ?
                (datosSiguiente?.Fibra_Rizo && datosSiguiente.Fibra_Rizo.trim() !== '' ? datosSiguiente.Fibra_Rizo : '-') :
                (datosSiguiente?.Fibra_Pie && datosSiguiente.Fibra_Pie.trim() !== '' ? datosSiguiente.Fibra_Pie : '-'),
            ordenProd: datosSiguiente?.Orden_Prod || ''
        };

        // Actualizar tabla del modal
        document.getElementById('cuentaProceso').textContent = window.modalData.datosProceso.cuenta;
        document.getElementById('calibreProceso').textContent = window.modalData.datosProceso.calibre;
        document.getElementById('fibraProceso').textContent = window.modalData.datosProceso.fibra;

        document.getElementById('cuentaSiguiente').textContent = window.modalData.datosSiguiente.cuenta;
        document.getElementById('calibreSiguiente').textContent = window.modalData.datosSiguiente.calibre;
        document.getElementById('fibraSiguiente').textContent = window.modalData.datosSiguiente.fibra;

        // Mostrar modal
        document.getElementById('modalSeleccion').classList.remove('hidden');

        // Limpiar selección anterior y seleccionar por defecto "Producción en Proceso"
        document.querySelectorAll('input[name="seleccion"]').forEach(radio => {
            radio.checked = false;
        });

        // Seleccionar por defecto "Producción en Proceso"
        document.getElementById('radioProceso').checked = true;
    });
}

// Función para cerrar el modal
function cerrarModalSeleccion() {
    document.getElementById('modalSeleccion').classList.add('hidden');
    window.modalData = {
        telarId: null,
        tipo: null,
        datosProceso: null,
        datosSiguiente: null
    };
}

// Función para confirmar la selección
function confirmarSeleccion() {
    const seleccionado = document.querySelector('input[name="seleccion"]:checked');

    if (!seleccionado) {
        alert('Por favor seleccione una opción.');
        return;
    }

    let datosSeleccionados;

    if (seleccionado.value === 'proceso') {
        datosSeleccionados = window.modalData.datosProceso;
    } else {
        datosSeleccionados = window.modalData.datosSiguiente;
    }

    // Actualizar visualmente la cuenta seleccionada
    const elemento = document.getElementById(`cuenta-${window.modalData.tipo}-${window.modalData.telarId}`);
    if (elemento) {
        elemento.textContent = datosSeleccionados.cuenta;
    }

    // Guardar la selección Y los datos completos para este telar y tipo
    if (!window.modalData.seleccionGuardada[window.modalData.telarId]) {
        window.modalData.seleccionGuardada[window.modalData.telarId] = {};
    }
    window.modalData.seleccionGuardada[window.modalData.telarId][window.modalData.tipo] = {
        seleccion: seleccionado.value,
        datos: datosSeleccionados,
        ordenProd: seleccionado.value === 'proceso'
            ? window.modalData.datosProceso?.ordenProd
            : window.modalData.datosSiguiente?.ordenProd
    };

    // Cerrar modal
    cerrarModalSeleccion();

    // Mostrar notificación de éxito
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Selección actualizada',
            text: `Se ha seleccionado ${seleccionado.value === 'proceso' ? 'Producción en Proceso' : 'Siguiente Orden'} para ${window.modalData.tipo.toUpperCase()}`,
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            position: 'bottom-end',
            toast: true
        });
    }
}

// Función para obtener datos del proceso actual
async function obtenerDatosProcesoActual(telarId) {
    try {
        const response = await fetch(`/api/telares/proceso-actual/${telarId}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            return await response.json();
        } else {
            return null;
        }
    } catch (error) {
        return null;
    }
}

// Función para obtener datos de la siguiente orden
async function obtenerDatosSiguienteOrden(telarId) {
    try {
        const response = await fetch(`/api/telares/siguiente-orden/${telarId}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            return await response.json();
        } else {
            return null;
        }
    } catch (error) {
        return null;
    }
}

// Función para obtener el inventario completo de telares
async function obtenerInventarioTelares() {
    try {
        const response = await fetch('/inventario-telares', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (response.ok) {
            const data = await response.json();
            return data.data;
        } else {
            return [];
        }
    } catch (error) {
        return [];
    }
}
</script>

