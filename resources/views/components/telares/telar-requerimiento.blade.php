@props([
    'telar',
    'ordenSig' => null,
    'salon' => '',
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
        $permisos = \App\Models\Sistema\SYSUsuariosRoles::where('idusuario', $idusuario)
            ->where('idrol', 21) // Requerimientos
            ->first();
    }

    // Verificar si tiene permiso de crear
    $puedeCrear = $permisos ? $permisos->crear == 1 : false;

    $containerClass = 'p-3 md:p-1.5 lg:p-3';
    $accountBoxClass = 'mb-2 md:mb-1.5 lg:mb-0 mr-0 md:mr-0 lg:mr-4 mt-0 md:mt-0 lg:mt-[32px] rounded-lg p-3 md:p-1.5 lg:p-3 border border-gray-200';
    $accountTitleClass = 'text-sm md:text-[10px] lg:text-sm font-semibold text-gray-700 mb-2 md:mb-0.5 lg:mb-2 md:inline md:mr-2 lg:block lg:mr-0';
    $accountListClass = 'space-y-1 md:space-y-0 md:inline-flex md:gap-3 lg:space-y-1 lg:gap-0 lg:block text-sm md:text-[10px] lg:text-sm';
    $accountRowClass = 'flex items-center md:justify-start lg:justify-between';
    $accountValueClass = 'ml-2 md:ml-1 lg:ml-2 font-bold text-blue-600';
    $accountButtonClass = 'ml-2 md:ml-1 lg:ml-2 p-1 md:p-0.5 lg:p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors';
    $calendarTableClass = 'border border-gray-300 rounded overflow-hidden shadow-sm w-24 flex-shrink-0';
    $calendarHeaderClass = 'text-center border-b border-gray-300 bg-blue-500 text-white px-1 py-1 text-xs font-bold';
    $calendarCellClass = 'border border-gray-200 text-center px-1 py-1 bg-white min-w-[40px]';
    $checkboxBaseClass = 'w-3 h-3 text-blue-600 rounded border-gray-300 focus:ring-blue-500';
    $checkboxDisabledClass = 'opacity-50 cursor-not-allowed';
    $modalThBaseClass = 'px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider';
    $modalThBorderClass = $modalThBaseClass . ' border-r border-gray-200';
    $modalRowClass = 'hover:bg-blue-50 transition-colors';
    $modalRadioClass = 'w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500 focus:ring-2';
    $modalTdBaseClass = 'px-4 py-4 text-base font-semibold text-gray-900';
    $modalTdBorderClass = $modalTdBaseClass . ' border-r border-gray-200';
    $modalCancelButtonClass = 'px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200';
    $modalConfirmButtonClass = 'px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 border border-transparent rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg transition-all duration-200';
@endphp

<div class="{{ $containerClass }}">
    <div class="md:flex md:flex-col lg:flex-row">
        <!-- Información de cuentas -->
        <div class="{{ $accountBoxClass }}">
            <div class="{{ $accountTitleClass }}">Cuentas:</div>
            <div class="{{ $accountListClass }}">
                <div class="{{ $accountRowClass }}">
                <div class="flex items-center">
                    <span class="font-medium text-gray-600">RIZO</span>
                        <span class="{{ $accountValueClass }}" id="cuenta-rizo-{{ $telar->Telar }}">
                            {{ $telar->Cuenta ?? '' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="{{ $accountButtonClass }}"
                        title="Seleccionar cuenta RIZO"
                        onclick="abrirModalSeleccion('{{ $telar->Telar }}', 'rizo', '{{ $telar->Cuenta ?? '' }}', '{{ $telar->CalibreRizo2 ?? '' }}', '{{ $telar->Fibra_Rizo ?? '' }}')"
                    >
                        <i class="fas fa-chevron-right text-sm"></i>
                    </button>
                </div>
                <div class="{{ $accountRowClass }}">
                <div class="flex items-center">
                    <span class="font-medium text-gray-600">PIE</span>
                        <span class="{{ $accountValueClass }}" id="cuenta-pie-{{ $telar->Telar }}">
                            {{ $telar->Cuenta_Pie ?? '' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="{{ $accountButtonClass }}"
                        title="Seleccionar cuenta PIE"
                        onclick="abrirModalSeleccion('{{ $telar->Telar }}', 'pie', '{{ $telar->Cuenta_Pie ?? '' }}', '{{ $telar->CalibrePie2 ?? '' }}', '{{ $telar->Fibra_Pie ?? '' }}')"
                    >
                        <i class="fas fa-chevron-right text-sm"></i>
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
                $claseTabla = 't' . ($dia + 1);
                $prefijoId = $telar->Telar . '_' . $claseTabla;
                // Mapeo de días en español
                $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                $diaSemana = $diasSemana[\Carbon\Carbon::now()->addDays($dia)->dayOfWeek];
            @endphp

            <table class="{{ $calendarTableClass }}">
                <thead>
                    <tr>
                        <th colspan="{{ $turnos }}" class="{{ $calendarHeaderClass }}">
                            <div class="text-xs leading-tight">{{ $fecha }}</div>
                            <div class="text-xs opacity-75 leading-tight">{{ $diaSemana }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @for($turno = 1; $turno <= $turnos; $turno++)
                            <td class="{{ $calendarCellClass }}">
                                <div class="font-bold text-gray-700 mb-1 text-xs">{{ $turno }}</div>
                                <div class="space-y-0.5">
                                    <label class="block">
                                        <input
                                            type="checkbox"
                                            name="rizo{{ $turno }}"
                                            class="{{ $claseTabla }}rizo {{ $checkboxBaseClass }} {{ !$puedeCrear ? $checkboxDisabledClass : '' }}"
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
                                            class="{{ $claseTabla }}pie {{ $checkboxBaseClass }} {{ !$puedeCrear ? $checkboxDisabledClass : '' }}"
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
<div id="modalSeleccion" class="fixed inset-0 bg-black/40 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative mx-auto p-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white transform transition-all">
        <!-- Header del Modal con gradiente -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold" id="modalTitulo">Telar JACQUARD SULZER <span id="modalTelarNumero" class="text-yellow-300"></span></h3>
                <button type="button" onclick="cerrarModalSeleccion()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
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
                            <th class="{{ $modalThBorderClass }}">Seleccionar</th>
                            <th class="{{ $modalThBorderClass }}">Cuenta</th>
                            <th class="{{ $modalThBorderClass }}">Calibre</th>
                            <th class="{{ $modalThBaseClass }}">Fibra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- Fila: Producción en Proceso -->
                        <tr class="{{ $modalRowClass }}">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="proceso" id="radioProceso" class="{{ $modalRadioClass }}">
                            </td>
                            <td class="{{ $modalTdBorderClass }}" id="cuentaProceso">-</td>
                            <td class="{{ $modalTdBorderClass }}" id="calibreProceso">-</td>
                            <td class="{{ $modalTdBaseClass }}" id="fibraProceso">-</td>
                        </tr>
                        <!-- Fila: Siguiente Orden -->
                        <tr class="{{ $modalRowClass }}">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="siguiente" id="radioSiguiente" class="{{ $modalRadioClass }}">
                            </td>
                            <td class="{{ $modalTdBorderClass }}" id="cuentaSiguiente">-</td>
                            <td class="{{ $modalTdBorderClass }}" id="calibreSiguiente">-</td>
                            <td class="{{ $modalTdBaseClass }}" id="fibraSiguiente">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Botones de Acción mejorados -->
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cerrarModalSeleccion()" class="{{ $modalCancelButtonClass }}">
                    Cancelar
                </button>
                <button type="button" onclick="confirmarSeleccion()" class="{{ $modalConfirmButtonClass }}">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Tela Reservada -->
<div id="modalTelaReservada" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 100001 !important; background-color: rgba(0, 0, 0, 0.6) !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100% !important; height: 100% !important;" onclick="if(event.target === this && typeof window.cerrarModalTelaReservada === 'function') window.cerrarModalTelaReservada()">
    <div class="relative mx-auto p-0 w-full max-w-xl shadow-2xl rounded-xl bg-white transform transition-all" style="position: relative !important; z-index: 100002 !important;" onclick="event.stopPropagation()">
        <!-- Contenido del Modal -->
        <div class="p-8">
            <!-- Mensaje principal -->
            <div class="text-center mb-8">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-yellow-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Ya tiene tela reservada</h3>
                <p class="text-gray-600">Este telar tiene tela reservada. ¿Qué desea hacer?</p>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Botón Eliminar -->
                <button
                    type="button"
                    id="btnEliminarReservado"
                    onclick="confirmarEliminarConReserva()"
                    class="flex-1 px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <div class="flex flex-col items-center">
                        <span class="text-base mb-1">Eliminar</span>
                        <span class="text-xs opacity-90">Si elimina el registro elimina la reserva</span>
                    </div>
                </button>

                <!-- Botón Actualizar (muestra calendario para seleccionar nueva fecha) -->
                <button
                    type="button"
                    id="btnActualizarReservado"
                    onclick="mostrarCalendarioParaActualizar()"
                    class="flex-1 px-4 py-2.5 bg-yellow-600 text-white font-semibold rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <span class="text-base">Actualizar</span>
                </button>
                <button
                    type="button"
                    id="btnCancelarReservado"
                    onclick="if(typeof window.cerrarModalTelaReservada === 'function') window.cerrarModalTelaReservada()"
                    class="flex-1 px-4 py-2.5 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <span class="text-base">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Calendario Semanal -->
<div id="modalCalendarioSemanal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99998; background-color: rgba(0, 0, 0, 0.6); position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100% !important; height: 100% !important; display: none !important;">
    <div class="relative mx-auto p-0 w-full max-w-4xl px-4 shadow-2xl bg-white transform transition-all border border-gray-300" style="position: relative; z-index: 100000;" onclick="event.stopPropagation()">
        <!-- Contenido del Modal -->
        <div class="p-6 md:p-8">
            <!-- Header -->
            <div class="flex items-center justify-center mb-6 md:mb-8">
                <h3 class="text-xl md:text-2xl font-bold text-gray-900 uppercase tracking-wide">Seleccionar Fecha</h3>
            </div>

            <!-- Calendario Semanal Horizontal -->
            <div class="mb-6 md:mb-8">
                <div id="calendarioSemanalGrid" class="grid grid-cols-7 gap-0 border border-gray-300 w-full" style="background-color: #f9fafb; display: grid; grid-template-columns: repeat(7, 1fr);">
                    <!-- Los días se generarán dinámicamente con JavaScript -->
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-center gap-2 mt-6 md:mt-8">
                <button
                    type="button"
                    onclick="if(typeof window.cerrarModalCalendarioSemanal === 'function') { window.cerrarModalCalendarioSemanal(); } else { console.error('cerrarModalCalendarioSemanal no está definida'); }"
                    class="px-6 py-2 md:px-8 md:py-2.5 text-sm md:text-base bg-gray-200 text-gray-800 font-semibold border border-gray-400 hover:bg-gray-300 focus:outline-none transition-all"
                >
                    Cancelar
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

    function inicializarFechasTablas() {
        // Inicializar atributos data-fecha-completa en todas las tablas del telar
        const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
        const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
            const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"]`) !== null;
            return tieneCheckboxDelTelar;
        });

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        todasLasTablasDelTelar.forEach((tabla, index) => {
            const thHeader = tabla.querySelector('th');
            if (thHeader && !thHeader.getAttribute('data-fecha-completa')) {
                const fechaOriginal = new Date(hoy);
                fechaOriginal.setDate(hoy.getDate() + index);
                const año = fechaOriginal.getFullYear();
                const mes = fechaOriginal.getMonth() + 1;
                const dia = fechaOriginal.getDate();
                const fechaCompleta = `${año}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                thHeader.setAttribute('data-fecha-completa', fechaCompleta);
            }
        });
    }

    function inicializarTelar() {
        // Configurar event listeners para checkboxes con datos específicos de este telar
        setupRequerimientoCheckboxes(telarId, telarData, ordenSigData, salonTelar);

        // Inicializar fechas de las tablas primero
        inicializarFechasTablas();

        // Esperar a que las tablas estén renderizadas antes de cargar requerimientos
        // Usar setTimeout para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            // Obtener el tipo del componente desde los checkboxes (rizo o pie)
            const primerCheckbox = document.querySelector(`input[data-telar="${telarId}"]`);
            const tipoComponente = primerCheckbox ? primerCheckbox.getAttribute('data-tipo') : null;

            if (!tipoComponente) {
                // Si no se encuentra el tipo, cargar sin filtro
                loadRequerimientos(telarId, salonTelar);
                return;
            }

            // Verificar si hay una selección guardada para este telar y tipo
            // Si existe, usar esa fibra para filtrar desde el inicio
            const seleccionGuardada = window.modalData?.seleccionGuardada?.[String(telarId)]?.[tipoComponente];

            if (seleccionGuardada && seleccionGuardada.datos && seleccionGuardada.datos.fibra) {
                const fibraGuardada = seleccionGuardada.datos.fibra;
                const fibraNormalizada = fibraGuardada ? String(fibraGuardada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';

                console.log('Cargando requerimientos con selección guardada:', {
                    telarId: telarId,
                    tipo: tipoComponente,
                    seleccion: seleccionGuardada.seleccion,
                    fibra: fibraNormalizada,
                    fibraValida: fibraValida
                });

                if (fibraValida) {
                    // Cargar requerimientos filtrando por la fibra guardada
                    loadRequerimientosConFiltro(telarId, salonTelar, tipoComponente, fibraNormalizada);
                } else {
                    // Si no hay fibra válida, cargar todos los requerimientos sin filtro
                    loadRequerimientos(telarId, salonTelar);
                }
            } else {
                // No hay selección guardada, cargar todos los requerimientos sin filtro
                loadRequerimientos(telarId, salonTelar);
            }
        }, 100);

        // Precargar inventario al inicio (solo una vez) usando el sistema de caché
        if (!window.inventarioCargado) {
            obtenerInventarioConCache();
            window.inventarioCargado = true;
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarTelar);
    } else {
        // DOM ya está listo, ejecutar después de un pequeño delay para asegurar que las tablas estén renderizadas
        setTimeout(inicializarTelar, 50);
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
    // Evitar procesar cambios mientras se están cargando requerimientos para este telar
    const key = `${telarId}_${salon}`;
    if (window.cargandoRequerimientosPorTelar && window.cargandoRequerimientosPorTelar[key]) {
        // Revertir el cambio si estamos cargando
        checkbox.checked = !checkbox.checked;
        return;
    }

    const fila = checkbox.closest('tr');
    const tabla = fila.closest('table');
    const fechaElement = tabla.querySelector('th');

    // Verificar si el header tiene una fecha modificada (fecha antigua)
    let fecha = '';
    let fechaISO = null;

    if (fechaElement) {
        // Si el header tiene una fecha original (modificada), usar esa fecha
        const fechaOriginalAttr = fechaElement.getAttribute('data-fecha-original');
        const fechaCompletaAttr = fechaElement.getAttribute('data-fecha-completa');

        if (fechaOriginalAttr || fechaCompletaAttr) {
            // Usar la fecha completa del atributo si está disponible
            fechaISO = fechaCompletaAttr || fechaOriginalAttr;
            // Convertir fecha ISO a formato dd/mm para el texto
            if (fechaISO) {
                const [y, m, d] = fechaISO.split('-');
                fecha = `${d}/${m}`;
            }
        } else {
            // Usar el texto del header normalmente
            fecha = fechaElement.innerText.trim();
        }
    }

    const valorCheckbox = checkbox.value;
    const tipo = checkbox.dataset.tipo;

    // Usar datos pasados como parámetros (específicos de este telar)
    const datos = telarData;

    const cuentaRizo = datos.Cuenta || '';
    const cuentaPie = datos.Cuenta_Pie || '';
    const calibreRizo = datos.CalibreRizo2 || 0;
    const calibrePie = datos.CalibrePie2 || 0;

    // Extraer el número de turno del valor del checkbox (ej: "rizo1" -> 1, "pie2" -> 2)
    const numeroTurno = parseInt(valorCheckbox.replace(/\D/g, ''));

    // Convertir fecha del formato dd/mm a formato ISO (YYYY-MM-DD)
    function convertirFecha(fechaTexto, fechaISOExistente) {
        // Si ya tenemos una fecha ISO (del header modificado), usarla directamente
        if (fechaISOExistente) {
            return fechaISOExistente;
        }

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

    // Convertir fecha y validar (usar fechaISO si está disponible del header modificado)
    const fechaConvertida = convertirFecha(fecha, fechaISO);
    if (!fechaConvertida) {
        alert('Error al extraer la fecha del calendario. Intente de nuevo.');
        checkbox.checked = false;
        return;
    }

    // Si el checkbox se deseleccionó, verificar estado antes de eliminar
    if (!checkbox.checked) {
        // Verificar si el checkbox ya fue eliminado (no debe validar de nuevo)
        if (checkbox.getAttribute('data-eliminado') === 'true') {
            // Si ya fue eliminado, no hacer nada
            return;
        }

        // Marcar este checkbox como cambio reciente para preservarlo durante la recarga
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        const datosEliminar = {
            no_telar: String(telarId),
            tipo: tipo === 'rizo' ? 'Rizo' : 'Pie',
            fecha: fechaConvertida,
            turno: parseInt(numeroTurno)
        };

        // Verificar estado del telar antes de eliminar
        // IMPORTANTE: La verificación siempre debe usar el backend para obtener el estado más reciente
        // No confiar en el caché del frontend después de invalidaciones
        // El backend siempre tiene la verdad sobre el estado del registro específico
        verificarEstadoTelarAntesDeEliminar(telarId, tipo === 'rizo' ? 'Rizo' : 'Pie', datosEliminar, checkbox, telarData);

        return; // Salir de la función si se deseleccionó
    }

    // Si el checkbox se seleccionó, guardar el registro
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

    // Marcar este checkbox como cambio reciente para preservarlo durante la recarga
    checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

    // Enviar datos a la nueva tabla de inventario
    axios.post('/inventario-telares/guardar', datosInventario, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => {
            // Invalidar caché para que se actualice en la próxima carga
            invalidarCacheInventario();

            // El checkbox ya está marcado visualmente, mantenerlo así
            // Remover el atributo de cambio reciente después de un momento
            setTimeout(() => {
                checkbox.removeAttribute('data-cambio-reciente');
            }, 3000);

            // Mostrar notificación de éxito
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado con éxito',
                    showConfirmButton: false,
                    timer: 700,
                    timerProgressBar: true,
                    position: 'top-end',
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
                timer: 2500,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
        // Desmarcar checkbox si hubo error
        checkbox.checked = false;
    });

    // NOTA: Sistema anterior deshabilitado - ahora usamos solo /inventario-telares/guardar
    // El endpoint /guardar-requerimiento causaba errores de SQL y ya no es necesario
}

// Variable para evitar actualizaciones simultáneas por telar
if (typeof window.cargandoRequerimientosPorTelar === 'undefined') {
    window.cargandoRequerimientosPorTelar = {};
}

// Sistema de caché para inventario compartido entre todos los telares
if (typeof window.inventarioCache === 'undefined') {
    window.inventarioCache = {
        data: null,
        timestamp: null,
        loading: false,
        promises: [],
        maxAge: 5000 // 5 segundos de caché
    };
}

// Función para obtener inventario con caché y evitar múltiples peticiones simultáneas
async function obtenerInventarioConCache(filtros = {}) {
    const ahora = Date.now();

    // Crear clave de caché basada en los filtros
    const filtrosKey = JSON.stringify(filtros);
    const cacheKey = `inventario_${filtrosKey}`;

    // Si hay datos en caché para estos filtros y son recientes, devolverlos
    if (window.inventarioCache[cacheKey] && window.inventarioCache[cacheKey].timestamp &&
        (ahora - window.inventarioCache[cacheKey].timestamp) < window.inventarioCache.maxAge) {
        return Promise.resolve(window.inventarioCache[cacheKey].data);
    }

    // Si ya hay una petición en curso para estos filtros, esperar a que termine
    if (window.inventarioCache[`loading_${cacheKey}`]) {
        return new Promise((resolve) => {
            if (!window.inventarioCache[`promises_${cacheKey}`]) {
                window.inventarioCache[`promises_${cacheKey}`] = [];
            }
            window.inventarioCache[`promises_${cacheKey}`].push(resolve);
        });
    }

    // Iniciar nueva petición
    window.inventarioCache[`loading_${cacheKey}`] = true;

    try {
        // Construir URL con filtros
        let url = '/inventario-telares';
        const filtrosArray = [];

        if (filtros.hilo) {
            filtrosArray.push({ columna: 'hilo', valor: filtros.hilo });
        }
        if (filtros.no_telar) {
            filtrosArray.push({ columna: 'no_telar', valor: filtros.no_telar });
        }
        if (filtros.tipo) {
            filtrosArray.push({ columna: 'tipo', valor: filtros.tipo });
        }
        if (filtros.salon) {
            filtrosArray.push({ columna: 'salon', valor: filtros.salon });
        }

        if (filtrosArray.length > 0) {
            const params = new URLSearchParams();
            params.append('filtros', JSON.stringify(filtrosArray));
            url += '?' + params.toString();
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            cache: 'no-cache' // Evitar caché del navegador
        });

        if (response.ok) {
            const json = await response.json();
            const registros = json?.data || [];

            // Actualizar caché para estos filtros
            if (!window.inventarioCache[cacheKey]) {
                window.inventarioCache[cacheKey] = {};
            }
            window.inventarioCache[cacheKey].data = registros;
            window.inventarioCache[cacheKey].timestamp = ahora;

            // Resolver todas las promesas pendientes para estos filtros
            if (window.inventarioCache[`promises_${cacheKey}`]) {
                window.inventarioCache[`promises_${cacheKey}`].forEach(resolve => resolve(registros));
                window.inventarioCache[`promises_${cacheKey}`] = [];
            }
            window.inventarioCache[`loading_${cacheKey}`] = false;

            return registros;
        } else {
            throw new Error('Error al obtener inventario');
        }
    } catch (error) {
        // Resolver todas las promesas pendientes con error
        if (window.inventarioCache[`promises_${cacheKey}`]) {
            window.inventarioCache[`promises_${cacheKey}`].forEach(resolve => resolve([]));
            window.inventarioCache[`promises_${cacheKey}`] = [];
        }
        window.inventarioCache[`loading_${cacheKey}`] = false;

        // Si hay datos antiguos en caché, usarlos
        if (window.inventarioCache.data) {
            return window.inventarioCache.data;
        }

        return [];
    }
}

// Función para invalidar el caché (llamar después de guardar/eliminar)
function invalidarCacheInventario() {
    // Limpiar caché antiguo (compatibilidad)
    window.inventarioCache.data = null;
    window.inventarioCache.timestamp = null;

    // Limpiar todos los cachés filtrados
    Object.keys(window.inventarioCache).forEach(key => {
        if (key.startsWith('inventario_') || key.startsWith('loading_inventario_') || key.startsWith('promises_inventario_')) {
            delete window.inventarioCache[key];
        }
    });
}

function loadRequerimientos(telarId, salon, tipo = null, fibraFiltro = null) {
    // Si se proporciona tipo y fibra, usar la función con filtro
    if (tipo && fibraFiltro) {
        return loadRequerimientosConFiltro(telarId, salon, tipo, fibraFiltro);
    }

    // Evitar múltiples llamadas simultáneas para el mismo telar
    const key = `${telarId}_${salon}`;
    if (window.cargandoRequerimientosPorTelar[key]) {
        return;
    }

    window.cargandoRequerimientosPorTelar[key] = true;

    // Usar inventario con caché (más rápido)
    obtenerInventarioConCache()
        .then(registros => {

            // Buscar todas las tablas que contienen checkboxes de este telar
            // IMPORTANTE: Las tablas deben estar en orden (primera = hoy)
            const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
            const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
                const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"]`) !== null;
                return tieneCheckboxDelTelar;
            });

            if (todasLasTablasDelTelar.length === 0) {
                window.cargandoRequerimientosPorTelar[key] = false;
                // Intentar buscar de nuevo después de un delay
                setTimeout(() => {
                    loadRequerimientos(telarId, salon);
                }, 500);
                return;
            }

            // Obtener todos los headers de fecha (th) de estas tablas en orden
            const fechasTablas = [];
            todasLasTablasDelTelar.forEach((table, index) => {
                const th = table.querySelector('th');
                if (th) {
                    fechasTablas.push(th);
                }
            });

            // La primera tabla es la del primer día (hoy) - debe ser la primera en el orden del DOM
            const primeraTabla = todasLasTablasDelTelar[0];

            if (!primeraTabla) {
                window.cargandoRequerimientosPorTelar[key] = false;
                return;
            }

            // Guardar el estado actual de los checkboxes con cambios recientes antes de limpiar
            // Esto nos permite preservar los cambios recientes del usuario
            const estadosCheckboxesRecientes = new Map();
            const checkboxesEliminados = new Set();

            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                    // Si el checkbox fue eliminado, preservarlo como desmarcado permanentemente
                    if (checkbox.getAttribute('data-eliminado') === 'true') {
                        checkboxesEliminados.add(checkbox.id);
                        estadosCheckboxesRecientes.set(checkbox.id, {
                            checked: false, // Siempre desmarcado si fue eliminado
                            timestamp: Date.now(),
                            eliminado: true
                        });
                        return; // No procesar más este checkbox
                    }

                    // Guardar el estado actual con un timestamp para saber si es un cambio reciente
                    const cambioReciente = checkbox.getAttribute('data-cambio-reciente');
                    if (cambioReciente) {
                        const timestampCambio = parseInt(cambioReciente);
                        const ahora = Date.now();
                        // Si el cambio fue hace menos de 10 segundos, preservarlo (aumentado de 3 a 10)
                        if (ahora - timestampCambio < 10000) {
                            estadosCheckboxesRecientes.set(checkbox.id, {
                                checked: checkbox.checked,
                                timestamp: timestampCambio
                            });
                        } else {
                            // Si el cambio es muy antiguo, remover el atributo (excepto si fue eliminado)
                            if (checkbox.getAttribute('data-eliminado') !== 'true') {
                                checkbox.removeAttribute('data-cambio-reciente');
                            }
                        }
                    }
                });
            });

            // Limpiar todos los checkboxes de este telar en todas las tablas
            // EXCEPTO los que tienen cambios recientes del usuario o fueron eliminados
            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                    // Si este checkbox fue eliminado, mantenerlo desmarcado
                    if (checkboxesEliminados.has(checkbox.id)) {
                        checkbox.checked = false;
                        return;
                    }

                    // Si este checkbox tenía un cambio reciente, preservar su estado
                    if (estadosCheckboxesRecientes.has(checkbox.id)) {
                        const estado = estadosCheckboxesRecientes.get(checkbox.id);
                        checkbox.checked = estado.checked; // Preservar el estado (marcado o desmarcado)
                    } else {
                        // Si no tiene cambio reciente, limpiarlo normalmente
                        checkbox.checked = false;
                    }
                });
            });

            // Obtener el rango de fechas del calendario (primer día = hoy, último día = hoy + 6)
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const ultimoDia = new Date(hoy);
            ultimoDia.setDate(hoy.getDate() + 6); // 7 días totales (hoy + 6 días más)
            ultimoDia.setHours(23, 59, 59, 999);

            // Función para convertir fecha ISO (YYYY-MM-DD) a objeto Date
            function parseFechaISO(fechaISO) {
                if (!fechaISO) return null;
                const partes = fechaISO.split('-');
                if (partes.length !== 3) return null;
                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1; // Los meses en JS van de 0-11
                const dia = parseInt(partes[2]);
                if (isNaN(año) || isNaN(mes) || isNaN(dia)) return null;
                try {
                    const fecha = new Date(año, mes, dia);
                    fecha.setHours(0, 0, 0, 0);
                    // Validar que la fecha es válida
                    if (fecha.getFullYear() === año && fecha.getMonth() === mes && fecha.getDate() === dia) {
                        return fecha;
                    }
                } catch (e) {
                }
                return null;
            }

            // Función para obtener la primera tabla del calendario (hoy)
            function obtenerPrimeraTabla() {
                return primeraTabla; // Primera tabla = hoy (primer día del calendario)
            }

            // Marcar por coincidencia de telar+tipo+fecha+turno Y SALON
            // Filtrar registros del telar
            const registrosTelar = registros.filter(reg => {
                const telarCoincide = String(reg.no_telar) === String(telarId);

                if (!telarCoincide) {
                    return false;
                }

                const salonRegistro = String(reg.salon || '').toLowerCase().trim();
                const salonEsperado = String(salon || '').toLowerCase().trim();

                // Comparación flexible de salón:
                // 1. Si el salón esperado está vacío o es null/undefined, aceptar TODOS los registros del telar
                // 2. Si no está vacío, hacer comparación case-insensitive y flexible
                let salonCoincide = true;
                if (salonEsperado && salonEsperado !== '') {
                    // Normalizar ambos salones para comparación (remover espacios extra, convertir a minúsculas)
                    const salonRegistroNormalizado = salonRegistro.replace(/\s+/g, ' ').trim();
                    const salonEsperadoNormalizado = salonEsperado.replace(/\s+/g, ' ').trim();

                    salonCoincide = salonRegistroNormalizado === salonEsperadoNormalizado ||
                                   salonRegistroNormalizado.includes(salonEsperadoNormalizado) ||
                                   salonEsperadoNormalizado.includes(salonRegistroNormalizado);
                }

                const coincide = telarCoincide && salonCoincide;

                return coincide;
            });

            // PASO 1: Identificar si hay fechas antiguas Y si hay registros dentro del rango
            let fechaMasAntiguaEnPrimeraTabla = null;
            let hayRegistrosEnRango = false;

            registrosTelar.forEach(reg => {
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                if (fechaRegistro) {
                    const timestampRegistro = fechaRegistro.getTime();
                    const timestampHoy = hoy.getTime();
                    const timestampUltimoDia = ultimoDia.getTime();
                    const fechaEsAnterior = timestampRegistro < timestampHoy;
                    const fechaEsPosterior = timestampRegistro > timestampUltimoDia;
                    const fechaEnRango = !fechaEsAnterior && !fechaEsPosterior;

                    // Si hay registros dentro del rango, marcarlo
                    if (fechaEnRango) {
                        hayRegistrosEnRango = true;
                    }

                    // Si la fecha es anterior, rastrearla para actualizar el header
                    // PERO solo si NO hay registros dentro del rango
                    if (fechaEsAnterior && fechaRegistro && !hayRegistrosEnRango) {
                        if (!fechaMasAntiguaEnPrimeraTabla || fechaRegistro < fechaMasAntiguaEnPrimeraTabla) {
                            fechaMasAntiguaEnPrimeraTabla = fechaRegistro;
                        }
                    }
                }
            });

            // PASO 2: Actualizar headers ANTES de procesar registros
            // Función para formatear fecha
            function formatearFechaHeader(fechaObj) {
                const dia = fechaObj.getDate();
                const mes = fechaObj.getMonth() + 1;
                const año = fechaObj.getFullYear();
                const fechaFormateada = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                const diaSemana = diasSemana[fechaObj.getDay()];
                return { fechaFormateada, diaSemana, año, mes, dia };
            }

            // Si hay fechas antiguas Y NO hay registros dentro del rango, actualizar TODOS los headers primero
            // Si hay registros dentro del rango, mantener las fechas originales de las tablas
            if (fechaMasAntiguaEnPrimeraTabla && !hayRegistrosEnRango) {
                // Actualizar la primera tabla con la fecha antigua
                const thHeaderPrimera = primeraTabla.querySelector('th');
                if (thHeaderPrimera) {
                    const fechaOriginalHeader = thHeaderPrimera.getAttribute('data-fecha-original-header') || thHeaderPrimera.innerText.trim();
                    if (!thHeaderPrimera.getAttribute('data-fecha-original-header')) {
                        thHeaderPrimera.setAttribute('data-fecha-original-header', fechaOriginalHeader);
                    }

                    const fechaFormateada1 = formatearFechaHeader(fechaMasAntiguaEnPrimeraTabla);
                    thHeaderPrimera.innerHTML = `
                        <div class="text-xs leading-tight">${fechaFormateada1.fechaFormateada}</div>
                        <div class="text-xs opacity-75 leading-tight">${fechaFormateada1.diaSemana}</div>
                    `;
                    thHeaderPrimera.setAttribute('data-fecha-original', fechaMasAntiguaEnPrimeraTabla.toISOString().split('T')[0]);
                    thHeaderPrimera.setAttribute('data-fecha-completa', `${fechaFormateada1.año}-${String(fechaFormateada1.mes).padStart(2, '0')}-${String(fechaFormateada1.dia).padStart(2, '0')}`);
                    thHeaderPrimera.classList.add('fecha-modificada');
                    thHeaderPrimera.style.backgroundColor = '#fef3c7';
                    thHeaderPrimera.style.borderLeft = '3px solid #f59e0b';
                }

                // Actualizar las demás tablas: segunda = hoy, tercera = hoy+1, etc.
                // La última tabla se oculta (se recorta)
                todasLasTablasDelTelar.forEach((tabla, index) => {
                    if (index === 0) return; // Ya actualizamos la primera

                    const thHeader = tabla.querySelector('th');
                    if (!thHeader) return;

                    // Calcular nueva fecha: hoy + (index - 1) días
                    // index 1 = hoy (segunda tabla)
                    // index 2 = hoy + 1 (tercera tabla)
                    // etc.
                    const nuevaFecha = new Date(hoy);
                    nuevaFecha.setDate(hoy.getDate() + (index - 1));

                    // Si excede el rango original (hoy+6), ocultar tabla
                    const fechaOriginalUltimaTabla = new Date(hoy);
                    fechaOriginalUltimaTabla.setDate(hoy.getDate() + 6);

                    if (nuevaFecha > fechaOriginalUltimaTabla) {
                        tabla.style.display = 'none';
                        return;
                    }

                    // Guardar fecha original del header
                    if (!thHeader.getAttribute('data-fecha-original-header')) {
                        thHeader.setAttribute('data-fecha-original-header', thHeader.innerText.trim());
                    }

                    const fechaFormateada = formatearFechaHeader(nuevaFecha);
                    thHeader.innerHTML = `
                        <div class="text-xs leading-tight">${fechaFormateada.fechaFormateada}</div>
                        <div class="text-xs opacity-75 leading-tight">${fechaFormateada.diaSemana}</div>
                    `;
                    thHeader.setAttribute('data-fecha-completa', `${fechaFormateada.año}-${String(fechaFormateada.mes).padStart(2, '0')}-${String(fechaFormateada.dia).padStart(2, '0')}`);
                    thHeader.classList.remove('fecha-modificada');
                    thHeader.style.backgroundColor = '';
                    thHeader.style.borderLeft = '';
                });
            } else {
                // Si no hay fechas antiguas o hay registros en rango, asegurar que todas las tablas estén visibles
                // y establecer las fechas correctas basadas en el índice
                todasLasTablasDelTelar.forEach((tabla, index) => {
                    tabla.style.display = '';
                    const thHeader = tabla.querySelector('th');
                    if (thHeader) {
                        // Calcular la fecha original basada en el índice (tabla 0 = hoy, tabla 1 = hoy+1, etc.)
                        const fechaOriginal = new Date(hoy);
                        fechaOriginal.setDate(hoy.getDate() + index);
                        const fechaFormateada = formatearFechaHeader(fechaOriginal);
                        const fechaCompletaEsperada = `${fechaFormateada.año}-${String(fechaFormateada.mes).padStart(2, '0')}-${String(fechaFormateada.dia).padStart(2, '0')}`;

                        // Siempre asegurar que el atributo data-fecha-completa esté correcto
                        thHeader.setAttribute('data-fecha-completa', fechaCompletaEsperada);

                        // Si hay registros en rango, restaurar fecha original del header si fue modificado
                        if (hayRegistrosEnRango) {
                            const fechaCompletaActual = thHeader.getAttribute('data-fecha-completa');

                            // Verificar si el contenido visual necesita actualizarse
                            const fechaVisualActual = thHeader.innerText.trim();
                            const fechaVisualEsperada = `${fechaFormateada.fechaFormateada}\n${fechaFormateada.diaSemana}`;

                            // Solo actualizar el contenido visual si no coincide
                            if (!fechaVisualActual.includes(fechaFormateada.fechaFormateada)) {
                                thHeader.innerHTML = `
                                    <div class="text-xs leading-tight">${fechaFormateada.fechaFormateada}</div>
                                    <div class="text-xs opacity-75 leading-tight">${fechaFormateada.diaSemana}</div>
                                `;
                            }

                            // Limpiar estilos de fecha modificada
                            thHeader.classList.remove('fecha-modificada');
                            thHeader.style.backgroundColor = '';
                            thHeader.style.borderLeft = '';
                        }
                    }
                });
            }

            // PASO 3: Procesar registros y marcarlos usando los headers actualizados
            registrosTelar.forEach(reg => {
                const tipo = (reg.tipo || '').toLowerCase();
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                let tablaDestino = null;
                let usarPrimeraFecha = false;

                if (fechaRegistro) {
                    const timestampRegistro = fechaRegistro.getTime();
                    const timestampHoy = hoy.getTime();
                    const timestampUltimoDia = ultimoDia.getTime();
                    const fechaEsAnterior = timestampRegistro < timestampHoy;
                    const fechaEsPosterior = timestampRegistro > timestampUltimoDia;

                    if (fechaEsAnterior || fechaEsPosterior) {
                        // Fecha fuera del rango: usar primera tabla
                        usarPrimeraFecha = true;
                        tablaDestino = obtenerPrimeraTabla();
                    } else {
                        // Fecha dentro del rango: calcular qué tabla corresponde
                        // Calcular la diferencia en días desde hoy
                        const diferenciaDias = Math.floor((timestampRegistro - timestampHoy) / (1000 * 60 * 60 * 24));

                        // La tabla correspondiente es la del índice = diferenciaDias
                        // tabla 0 = hoy (diferencia 0), tabla 1 = hoy+1 (diferencia 1), etc.
                        if (diferenciaDias >= 0 && diferenciaDias < todasLasTablasDelTelar.length) {
                            tablaDestino = todasLasTablasDelTelar[diferenciaDias];

                            // Verificar que la tabla tenga la fecha correcta
                            if (tablaDestino) {
                                const th = tablaDestino.querySelector('th');
                                if (th) {
                                    const fechaCompletaAttr = th.getAttribute('data-fecha-completa');
                                    // Si la fecha no coincide, actualizarla
                                    if (fechaCompletaAttr !== fechaISO) {
                                        const fechaFormateada = formatearFechaHeader(fechaRegistro);
                                        th.innerHTML = `
                                            <div class="text-xs leading-tight">${fechaFormateada.fechaFormateada}</div>
                                            <div class="text-xs opacity-75 leading-tight">${fechaFormateada.diaSemana}</div>
                                        `;
                                        th.setAttribute('data-fecha-completa', fechaISO);
                                    }
                                }
                            }
                        } else {
                            // Si el índice está fuera de rango, buscar por atributo como fallback
                            let thFecha = null;
                            todasLasTablasDelTelar.forEach(tabla => {
                                if (tabla.style.display === 'none') return;
                                const th = tabla.querySelector('th');
                                if (th) {
                                    const fechaCompletaAttr = th.getAttribute('data-fecha-completa');
                                    if (fechaCompletaAttr === fechaISO) {
                                        thFecha = th;
                                    }
                                }
                            });

                            if (thFecha) {
                                tablaDestino = thFecha.closest('table');
                            } else {
                                // Último fallback: usar primera tabla
                                usarPrimeraFecha = true;
                                tablaDestino = obtenerPrimeraTabla();
                            }
                        }
                    }
                } else {
                    usarPrimeraFecha = true;
                    tablaDestino = obtenerPrimeraTabla();
                }

                if (!tablaDestino || tablaDestino.style.display === 'none') {
                    return;
                }

                // Marcar checkbox
                const valorEsperado = `${tipo}${reg.turno}`;
                const checkboxes = tablaDestino.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                if (checkboxes.length === 0) {
                    return;
                }

                checkboxes.forEach(cb => {
                    if (cb.value === valorEsperado) {
                        // NO marcar si el checkbox fue eliminado
                        if (cb.getAttribute('data-eliminado') === 'true') {
                            cb.checked = false;
                            return;
                        }

                        // Solo marcar si no tiene un cambio reciente del usuario
                        // Si tiene un cambio reciente, preservar el estado del usuario
                        const cambioReciente = cb.getAttribute('data-cambio-reciente');
                        if (!cambioReciente) {
                            cb.checked = true;
                            if (usarPrimeraFecha && fechaISO) {
                                cb.title = `Fecha original: ${fechaISO} (mostrado en primera fecha del calendario)`;
                                cb.setAttribute('data-fecha-original', fechaISO);
                                cb.classList.add('fecha-antigua');
                            }
                        } else {
                            // Si tiene cambio reciente, verificar si el timestamp aún es válido
                            const timestampCambio = parseInt(cambioReciente);
                            const ahora = Date.now();
                            if (ahora - timestampCambio > 10000) {
                                // El cambio ya no es reciente, marcar normalmente (solo si no fue eliminado)
                                if (cb.getAttribute('data-eliminado') !== 'true') {
                                    cb.checked = true;
                                    cb.removeAttribute('data-cambio-reciente');
                                }
                            }
                            // Si el cambio es reciente, mantener el estado actual del checkbox
                        }
                    }
                });
            });

            window.cargandoRequerimientosPorTelar[key] = false;
        })
        .catch(error => {
            window.cargandoRequerimientosPorTelar[key] = false;
        });
}

// Función para cargar requerimientos filtrando por fibra específica
function loadRequerimientosConFiltro(telarId, salon, tipo, fibraFiltro) {
    // Evitar múltiples llamadas simultáneas para el mismo telar
    const key = `${telarId}_${salon}_${tipo}_${fibraFiltro}`;
    if (window.cargandoRequerimientosPorTelar[key]) {
        return;
    }

    window.cargandoRequerimientosPorTelar[key] = true;

    // Preparar filtros para el GET
    const filtros = {
        no_telar: String(telarId),
        tipo: tipo === 'rizo' ? 'Rizo' : 'Pie'
    };

    // Agregar filtro por hilo si se proporciona
    if (fibraFiltro && fibraFiltro !== '' && fibraFiltro !== '-') {
        filtros.hilo = String(fibraFiltro).trim();
    }

    // Agregar filtro por salón si se proporciona
    if (salon && salon !== '') {
        filtros.salon = String(salon).trim();
    }

    // Usar inventario con caché y filtros (más rápido y filtrado en el servidor)
    console.log('Obteniendo inventario con filtros:', filtros);
    obtenerInventarioConCache(filtros)
        .then(registros => {
            console.log('Registros recibidos del servidor:', {
                total: registros.length,
                registros: registros.slice(0, 5).map(r => ({
                    no_telar: r.no_telar,
                    tipo: r.tipo,
                    hilo: r.hilo,
                    fecha: r.fecha,
                    turno: r.turno
                })),
                filtrosAplicados: filtros,
                nota: 'El servidor debería haber filtrado por hilo, pero algunos registros pueden tener hilo vacío'
            });

            // Los registros ya vienen filtrados del servidor, pero hacer una verificación adicional
            // IMPORTANTE: Filtrar en el cliente también para asegurar que solo se muestren registros con el hilo correcto
            const registrosFiltrados = registros.filter(reg => {
                const telarCoincide = String(reg.no_telar) === String(telarId);
                if (!telarCoincide) return false;

                const salonRegistro = String(reg.salon || '').toLowerCase().trim();
                const salonEsperado = String(salon || '').toLowerCase().trim();
                let salonCoincide = true;
                if (salonEsperado && salonEsperado !== '') {
                    const salonRegistroNormalizado = salonRegistro.replace(/\s+/g, ' ').trim();
                    const salonEsperadoNormalizado = salonEsperado.replace(/\s+/g, ' ').trim();
                    salonCoincide = salonRegistroNormalizado === salonEsperadoNormalizado ||
                                   salonRegistroNormalizado.includes(salonEsperadoNormalizado) ||
                                   salonEsperadoNormalizado.includes(salonRegistroNormalizado);
                }

                const tipoRegistro = String(reg.tipo || '').toLowerCase().trim();
                const tipoEsperado = tipo === 'rizo' ? 'rizo' : 'pie';
                const tipoCoincide = tipoRegistro === tipoEsperado;

                // Filtrar por fibra si se proporciona
                // IMPORTANTE: Si hay filtro por fibra, SOLO aceptar registros con esa fibra exacta
                // Rechazar registros con hilo vacío, NULL, o diferente
                const fibraRegistro = String(reg.hilo || '').toLowerCase().trim();
                const fibraEsperada = String(fibraFiltro || '').toLowerCase().trim();
                const tieneFibraFiltro = fibraFiltro && fibraFiltro !== '' && fibraFiltro !== '-';

                // Si hay filtro por fibra, el registro DEBE tener la fibra exacta (no vacía, no NULL)
                let fibraCoincide = true;
                if (tieneFibraFiltro) {
                    // Con filtro: solo aceptar si la fibra coincide exactamente Y no está vacía
                    fibraCoincide = fibraRegistro !== '' && fibraRegistro === fibraEsperada;
                } else {
                    // Sin filtro: aceptar todos (incluidos los vacíos)
                    fibraCoincide = true;
                }

                // Debug: mostrar comparación de fibras (solo para el primer registro)
                if (registros.length > 0 && registros.indexOf(reg) === 0) {
                    console.log('Comparando fibras (filtro adicional en cliente):', {
                        fibraRegistro: fibraRegistro,
                        fibraEsperada: fibraEsperada,
                        tieneFibraFiltro: tieneFibraFiltro,
                        fibraCoincide: fibraCoincide,
                        fibraFiltroOriginal: fibraFiltro,
                        filtrosAplicadosEnGET: filtros,
                        motivoRechazo: (tieneFibraFiltro && (!fibraRegistro || fibraRegistro !== fibraEsperada)) ?
                            'Hilo vacío o diferente' : 'OK'
                    });
                }

                return telarCoincide && salonCoincide && tipoCoincide && fibraCoincide;
            });

            // Buscar todas las tablas que contienen checkboxes de este telar
            const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
            const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
                const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`) !== null;
                return tieneCheckboxDelTelar;
            });

            if (todasLasTablasDelTelar.length === 0) {
                window.cargandoRequerimientosPorTelar[key] = false;
                setTimeout(() => {
                    loadRequerimientosConFiltro(telarId, salon, tipo, fibraFiltro);
                }, 500);
                return;
            }

            // Limpiar TODOS los checkboxes de este telar y tipo primero
            // EXCEPTO los que fueron eliminados (mantenerlos desmarcados)
            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`).forEach(checkbox => {
                    // Si fue eliminado, mantenerlo desmarcado pero no remover el atributo
                    if (checkbox.getAttribute('data-eliminado') === 'true') {
                        checkbox.checked = false;
                    } else {
                        checkbox.checked = false;
                    }
                });
            });

            // Obtener el rango de fechas del calendario
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const ultimoDia = new Date(hoy);
            ultimoDia.setDate(hoy.getDate() + 6);
            ultimoDia.setHours(23, 59, 59, 999);

            // Función para convertir fecha ISO (YYYY-MM-DD) a objeto Date
            function parseFechaISO(fechaISO) {
                if (!fechaISO) return null;
                const partes = fechaISO.split('-');
                if (partes.length !== 3) return null;
                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1;
                const dia = parseInt(partes[2]);
                if (isNaN(año) || isNaN(mes) || isNaN(dia)) return null;
                try {
                    const fecha = new Date(año, mes, dia);
                    fecha.setHours(0, 0, 0, 0);
                    if (fecha.getFullYear() === año && fecha.getMonth() === mes && fecha.getDate() === dia) {
                        return fecha;
                    }
                } catch (e) {
                }
                return null;
            }

            // Marcar solo los checkboxes que corresponden a la fibra seleccionada
            registrosFiltrados.forEach(reg => {
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                if (!fechaRegistro) return;

                const timestampRegistro = fechaRegistro.getTime();
                const timestampHoy = hoy.getTime();
                const timestampUltimoDia = ultimoDia.getTime();
                const fechaEsAnterior = timestampRegistro < timestampHoy;
                const fechaEsPosterior = timestampRegistro > timestampUltimoDia;

                let tablaDestino = null;
                if (fechaEsAnterior || fechaEsPosterior) {
                    tablaDestino = todasLasTablasDelTelar[0];
                } else {
                    const diferenciaDias = Math.floor((timestampRegistro - timestampHoy) / (1000 * 60 * 60 * 24));
                    if (diferenciaDias >= 0 && diferenciaDias < todasLasTablasDelTelar.length) {
                        tablaDestino = todasLasTablasDelTelar[diferenciaDias];
                    }
                }

                if (!tablaDestino) return;

                // Marcar checkbox
                const valorEsperado = `${tipo}${reg.turno}`;
                const checkboxes = tablaDestino.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                checkboxes.forEach(cb => {
                    if (cb.value === valorEsperado) {
                        // NO marcar si el checkbox fue eliminado
                        if (cb.getAttribute('data-eliminado') === 'true') {
                            cb.checked = false;
                            return;
                        }
                        cb.checked = true;
                    }
                });
            });

            console.log('Checkboxes marcados después del filtro:', {
                registrosFiltrados: registrosFiltrados.length,
                filtroAplicado: filtros,
                telarId: telarId,
                tipo: tipo
            });

            window.cargandoRequerimientosPorTelar[key] = false;
        })
        .catch(error => {
            console.error('Error al cargar requerimientos con filtro:', error);
            window.cargandoRequerimientosPorTelar[key] = false;
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

    // Guardar el salón del telar para usarlo después
    // Buscar el salón desde el contexto del componente
    const salonTelar = document.querySelector(`input[data-telar="${telarId}"]`)?.closest('.telar-section')?.dataset?.salon ||
                       (tipo === 'rizo' ? 'Jacquard' : 'Itema'); // Fallback
    window.modalData.salonTelar = salonTelar;

    // Actualizar título del modal
    document.getElementById('modalTelarNumero').textContent = telarId;

    // Verificar si hay una selección guardada previa de "Siguiente Orden" para este telar y tipo
    const seleccionPrevia = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];
    const usarFibraPrevia = seleccionPrevia && seleccionPrevia.seleccion === 'siguiente' && seleccionPrevia.datos?.fibra;

    // Obtener datos del proceso actual y siguiente orden
    // Si hay una selección previa de "Siguiente Orden", usar esa fibra para el GET
    const promesas = [
        obtenerDatosProcesoActual(telarId),
        obtenerDatosSiguienteOrden(telarId, usarFibraPrevia ? seleccionPrevia.datos.fibra : null)
    ];

    Promise.all(promesas).then(([datosProceso, datosSiguiente]) => {
        // Configurar datos del proceso actual según el tipo (RIZO o PIE)
        window.modalData.datosProceso = {
            cuenta: tipo === 'rizo' ?
                (datosProceso?.Cuenta && datosProceso.Cuenta.trim() !== '' ? datosProceso.Cuenta : '-') :
                (datosProceso?.Cuenta_Pie && datosProceso.Cuenta_Pie.trim() !== '' ? datosProceso.Cuenta_Pie : '-'),
            calibre: tipo === 'rizo' ?
                (datosProceso?.CalibreRizo2 && datosProceso.CalibreRizo2 !== '' ? datosProceso.CalibreRizo2 : '-') :
                (datosProceso?.CalibrePie2 && datosProceso.CalibrePie2 !== '' ? datosProceso.CalibrePie2 : '-'),
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
                (datosSiguiente?.CalibreRizo2 && datosSiguiente.CalibreRizo2 !== '' ? datosSiguiente.CalibreRizo2 : '-') :
                (datosSiguiente?.CalibrePie2 && datosSiguiente.CalibrePie2 !== '' ? datosSiguiente.CalibrePie2 : '-'),
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
        const modal = document.getElementById('modalSeleccion');
        modal.classList.remove('hidden');
        modal.classList.add('flex', 'items-center', 'justify-center');

        // Agregar event listeners a los radio buttons para actualizar datos cuando cambien
        const radioProceso = document.getElementById('radioProceso');
        const radioSiguiente = document.getElementById('radioSiguiente');

        // Remover listeners anteriores si existen (clonar y reemplazar para limpiar listeners)
        const nuevoRadioProceso = radioProceso.cloneNode(true);
        radioProceso.parentNode.replaceChild(nuevoRadioProceso, radioProceso);
        const nuevoRadioSiguiente = radioSiguiente.cloneNode(true);
        radioSiguiente.parentNode.replaceChild(nuevoRadioSiguiente, radioSiguiente);

        // Limpiar selección anterior
        nuevoRadioProceso.checked = false;
        nuevoRadioSiguiente.checked = false;

        // Verificar si hay una selección guardada previa para este telar y tipo
        const seleccionPrevia = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];

        // Agregar listener para "Producción en Proceso"
        nuevoRadioProceso.addEventListener('change', function() {
            if (this.checked) {
                // Hacer GET del proceso actual
                obtenerDatosProcesoActual(telarId).then(datosProceso => {
                    if (datosProceso) {
                        window.modalData.datosProceso = {
                            cuenta: tipo === 'rizo' ?
                                (datosProceso?.Cuenta && datosProceso.Cuenta.trim() !== '' ? datosProceso.Cuenta : '-') :
                                (datosProceso?.Cuenta_Pie && datosProceso.Cuenta_Pie.trim() !== '' ? datosProceso.Cuenta_Pie : '-'),
                            calibre: tipo === 'rizo' ?
                                (datosProceso?.CalibreRizo2 && datosProceso.CalibreRizo2 !== '' ? datosProceso.CalibreRizo2 : '-') :
                                (datosProceso?.CalibrePie2 && datosProceso.CalibrePie2 !== '' ? datosProceso.CalibrePie2 : '-'),
                            fibra: tipo === 'rizo' ?
                                (datosProceso?.Fibra_Rizo && datosProceso.Fibra_Rizo.trim() !== '' ? datosProceso.Fibra_Rizo : '-') :
                                (datosProceso?.Fibra_Pie && datosProceso.Fibra_Pie.trim() !== '' ? datosProceso.Fibra_Pie : '-'),
                            ordenProd: datosProceso?.Orden_Prod || ''
                        };

                        // Actualizar tabla del modal
                        document.getElementById('cuentaProceso').textContent = window.modalData.datosProceso.cuenta;
                        document.getElementById('calibreProceso').textContent = window.modalData.datosProceso.calibre;
                        document.getElementById('fibraProceso').textContent = window.modalData.datosProceso.fibra;

                        // Actualizar checkboxes por la fibra del proceso actual (opcional: preview en tiempo real)
                        // Esto se puede hacer aquí o solo cuando se confirme la selección
                    }
                });
            }
        });

        // Agregar listener para "Siguiente Orden"
        nuevoRadioSiguiente.addEventListener('change', function() {
            if (this.checked) {
                // Verificar si hay una selección guardada previa para obtener la fibra
                const seleccionPreviaActual = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];
                const fibraPrevia = seleccionPreviaActual && seleccionPreviaActual.seleccion === 'siguiente' && seleccionPreviaActual.datos?.fibra ? seleccionPreviaActual.datos.fibra : null;

                // Hacer GET de la siguiente orden (con fibra si existe selección previa)
                obtenerDatosSiguienteOrden(telarId, fibraPrevia).then(datosSiguiente => {
                    if (datosSiguiente) {
                        window.modalData.datosSiguiente = {
                            cuenta: tipo === 'rizo' ?
                                (datosSiguiente?.Cuenta && datosSiguiente.Cuenta.trim() !== '' ? datosSiguiente.Cuenta : '-') :
                                (datosSiguiente?.Cuenta_Pie && datosSiguiente.Cuenta_Pie.trim() !== '' ? datosSiguiente.Cuenta_Pie : '-'),
                            calibre: tipo === 'rizo' ?
                                (datosSiguiente?.CalibreRizo2 && datosSiguiente.CalibreRizo2 !== '' ? datosSiguiente.CalibreRizo2 : '-') :
                                (datosSiguiente?.CalibrePie2 && datosSiguiente.CalibrePie2 !== '' ? datosSiguiente.CalibrePie2 : '-'),
                            fibra: tipo === 'rizo' ?
                                (datosSiguiente?.Fibra_Rizo && datosSiguiente.Fibra_Rizo.trim() !== '' ? datosSiguiente.Fibra_Rizo : '-') :
                                (datosSiguiente?.Fibra_Pie && datosSiguiente.Fibra_Pie.trim() !== '' ? datosSiguiente.Fibra_Pie : '-'),
                            ordenProd: datosSiguiente?.Orden_Prod || ''
                        };

                        // Actualizar tabla del modal
                        document.getElementById('cuentaSiguiente').textContent = window.modalData.datosSiguiente.cuenta;
                        document.getElementById('calibreSiguiente').textContent = window.modalData.datosSiguiente.calibre;
                        document.getElementById('fibraSiguiente').textContent = window.modalData.datosSiguiente.fibra;
                    }
                });
            }
        });

        // Si hay selección previa, seleccionar esa opción; si no, seleccionar "Producción en Proceso" por defecto
        if (seleccionPrevia) {
            if (seleccionPrevia.seleccion === 'siguiente') {
                nuevoRadioSiguiente.checked = true;
                // Disparar el evento change para cargar los datos de la siguiente orden
                nuevoRadioSiguiente.dispatchEvent(new Event('change'));
            } else if (seleccionPrevia.seleccion === 'proceso') {
                nuevoRadioProceso.checked = true;
                // Disparar el evento change para cargar los datos del proceso actual
                nuevoRadioProceso.dispatchEvent(new Event('change'));
            } else {
                // Seleccionar por defecto "Producción en Proceso"
                nuevoRadioProceso.checked = true;
                nuevoRadioProceso.dispatchEvent(new Event('change'));
            }
        } else {
            // Seleccionar por defecto "Producción en Proceso"
            nuevoRadioProceso.checked = true;
            nuevoRadioProceso.dispatchEvent(new Event('change'));
        }
    });
}

// Función para cerrar el modal
function cerrarModalSeleccion() {
    const modal = document.getElementById('modalSeleccion');
    modal.classList.add('hidden');
    modal.classList.remove('flex', 'items-center', 'justify-center');
    window.modalData = {
        telarId: null,
        tipo: null,
        datosProceso: null,
        datosSiguiente: null
    };
}

// Función para confirmar la selección
function confirmarSeleccion() {
    // Asegurar que window.modalData esté inicializado
    if (typeof window.modalData === 'undefined') {
        window.modalData = {
            telarId: null,
            tipo: null,
            datosProceso: null,
            datosSiguiente: null,
            seleccionGuardada: {}
        };
    }

    // Asegurar que seleccionGuardada esté inicializado
    if (!window.modalData.seleccionGuardada) {
        window.modalData.seleccionGuardada = {};
    }

    const seleccionado = document.querySelector('input[name="seleccion"]:checked');

    if (!seleccionado) {
        alert('Por favor seleccione una opción.');
        return;
    }

    // Validar que tenemos los datos necesarios
    if (!window.modalData.telarId || !window.modalData.tipo) {
        alert('Error: No se encontraron los datos del telar. Por favor, cierre y vuelva a abrir el modal.');
        return;
    }

    let datosSeleccionados;

    if (seleccionado.value === 'proceso') {
        datosSeleccionados = window.modalData.datosProceso;
    } else {
        datosSeleccionados = window.modalData.datosSiguiente;
    }

    // Validar que tenemos datos seleccionados
    if (!datosSeleccionados) {
        alert('Error: No se encontraron los datos seleccionados.');
        return;
    }

    // Actualizar visualmente la cuenta seleccionada
    const elemento = document.getElementById(`cuenta-${window.modalData.tipo}-${window.modalData.telarId}`);
    if (elemento) {
        elemento.textContent = datosSeleccionados.cuenta || '-';
    }

    // Guardar la selección Y los datos completos para este telar y tipo
    const telarId = String(window.modalData.telarId);
    const tipo = String(window.modalData.tipo);

    if (!window.modalData.seleccionGuardada[telarId]) {
        window.modalData.seleccionGuardada[telarId] = {};
    }

    window.modalData.seleccionGuardada[telarId][tipo] = {
        seleccion: seleccionado.value,
        datos: datosSeleccionados,
        ordenProd: seleccionado.value === 'proceso'
            ? (window.modalData.datosProceso?.ordenProd || '')
            : (window.modalData.datosSiguiente?.ordenProd || '')
    };

    // Cuando se selecciona una nueva fibra (proceso o siguiente), actualizar el hilo en el inventario y filtrar checkboxes
    const fibraSeleccionada = datosSeleccionados?.fibra || '';
    const salonTelar = window.modalData?.salonTelar || '';

    // Guardar valores antes del setTimeout para evitar que se pierdan
    const telarIdParaFiltro = window.modalData.telarId;
    const tipoParaFiltro = window.modalData.tipo;
    const esProceso = seleccionado.value === 'proceso';

    // Debug: mostrar la fibra seleccionada
    console.log('Fibra seleccionada:', {
        fibraOriginal: fibraSeleccionada,
        seleccion: seleccionado.value,
        esProceso: esProceso,
        telarId: telarIdParaFiltro,
        tipo: tipoParaFiltro,
        datosCompletos: datosSeleccionados
    });

    // Limpiar TODOS los checkboxes de este telar y tipo antes de aplicar el nuevo filtro
    const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
    const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
        const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarIdParaFiltro}"][data-tipo="${tipoParaFiltro}"]`) !== null;
        return tieneCheckboxDelTelar;
    });

    todasLasTablasDelTelar.forEach(table => {
        table.querySelectorAll(`input[data-telar="${telarIdParaFiltro}"][data-tipo="${tipoParaFiltro}"]`).forEach(checkbox => {
            checkbox.checked = false;
            // Limpiar también el atributo de cambio reciente
            checkbox.removeAttribute('data-cambio-reciente');
        });
    });

    console.log('Checkboxes limpiados antes de aplicar nuevo filtro:', {
        telarId: telarIdParaFiltro,
        tipo: tipoParaFiltro,
        checkboxesLimpiados: todasLasTablasDelTelar.reduce((total, table) => {
            return total + table.querySelectorAll(`input[data-telar="${telarIdParaFiltro}"][data-tipo="${tipoParaFiltro}"]`).length;
        }, 0)
    });

    // Actualizar el hilo en el inventario cuando se confirma una selección
    if (telarIdParaFiltro && tipoParaFiltro && fibraSeleccionada && fibraSeleccionada !== '-') {
        // Actualizar el hilo en todos los registros activos del inventario para este telar y tipo
        const hiloParaActualizar = String(fibraSeleccionada).trim();

        // Llamar al endpoint para actualizar el hilo
        fetch('/programa-urd-eng/actualizar-telar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                no_telar: telarIdParaFiltro,
                tipo: tipoParaFiltro === 'rizo' ? 'Rizo' : 'Pie',
                hilo: hiloParaActualizar
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Hilo actualizado en inventario:', data);

            // Invalidar TODOS los cachés para forzar una nueva consulta
            invalidarCacheInventario();

            // Esperar un poco más para asegurar que la base de datos se actualizó completamente
            // Recargar requerimientos después de actualizar el hilo
            setTimeout(() => {
                // Normalizar la fibra: remover espacios, convertir a minúsculas, y verificar que no sea '-' o vacía
                const fibraNormalizada = fibraSeleccionada ? String(fibraSeleccionada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';

                console.log('Filtrando por fibra después de actualizar hilo:', {
                    fibraNormalizada: fibraNormalizada,
                    fibraValida: fibraValida,
                    seleccion: seleccionado.value,
                    telarId: telarIdParaFiltro,
                    tipo: tipoParaFiltro,
                    salonTelar: salonTelar,
                    hiloActualizado: hiloParaActualizar
                });

                if (fibraValida) {
                    // Filtrar por la fibra específica seleccionada
                    // Invalidar caché nuevamente antes de cargar para asegurar que se use el hilo actualizado
                    invalidarCacheInventario();

                    // Esperar un poco más para asegurar que TODOS los registros se actualizaron en la BD
                    setTimeout(() => {
                        loadRequerimientosConFiltro(telarIdParaFiltro, salonTelar, tipoParaFiltro, fibraNormalizada);
                    }, 300); // Delay adicional para asegurar que el UPDATE completo terminó
                } else {
                    // Si no hay fibra válida, cargar todos los requerimientos sin filtro
                    loadRequerimientos(telarIdParaFiltro, salonTelar);
                }
            }, 500); // Aumentar el delay a 500ms para dar tiempo a que la BD se actualice
        })
        .catch(error => {
            console.error('Error al actualizar hilo en inventario:', error);
            // Aún así, intentar filtrar los checkboxes
            invalidarCacheInventario();
            setTimeout(() => {
                const fibraNormalizada = fibraSeleccionada ? String(fibraSeleccionada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';
                if (fibraValida) {
                    loadRequerimientosConFiltro(telarIdParaFiltro, salonTelar, tipoParaFiltro, fibraNormalizada);
                } else {
                    loadRequerimientos(telarIdParaFiltro, salonTelar);
                }
            }, 200);
        });
    } else {
        // Si no hay fibra válida, solo recargar requerimientos sin actualizar hilo
        invalidarCacheInventario();
        setTimeout(() => {
            loadRequerimientos(telarIdParaFiltro, salonTelar);
        }, 100);
    }

    // Cerrar modal
    cerrarModalSeleccion();

    // Mostrar notificación de éxito (muy rápida)
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Actualizado',
            showConfirmButton: false,
            timer: 500,
            timerProgressBar: false,
            position: 'top-end',
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
async function obtenerDatosSiguienteOrden(telarId, fibra = null) {
    try {
        let url = `/api/telares/siguiente-orden/${telarId}`;
        // Si se proporciona una fibra, agregarla como parámetro de consulta
        if (fibra) {
            url += `?fibra=${encodeURIComponent(fibra)}`;
        }

        const response = await fetch(url, {
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

// Función para obtener el inventario completo de telares (usa caché)
async function obtenerInventarioTelares() {
    return await obtenerInventarioConCache();
}

// Variable global para almacenar datos de eliminación pendiente
// Variables globales para el modal de eliminación (declaradas solo una vez)
if (typeof window.datosEliminacionPendiente === 'undefined') {
    window.datosEliminacionPendiente = null;
}
if (typeof window.checkboxEliminacionPendiente === 'undefined') {
    window.checkboxEliminacionPendiente = null;
}

// Variables para el modal de calendario semanal
if (typeof window.checkboxPendienteCalendario === 'undefined') {
    window.checkboxPendienteCalendario = null;
    window.telarIdPendienteCalendario = null;
    window.tipoPendienteCalendario = null;
    window.turnoPendienteCalendario = null;
    window.fechaOriginalPendienteCalendario = null;
    window.telarDataPendiente = null;
}

// Función para verificar estado del telar antes de eliminar
async function verificarEstadoTelarAntesDeEliminar(telarId, tipo, datosEliminar, checkbox, telarData = null) {
    try {
        // Validar que tenemos los datos necesarios para la verificación
        if (!datosEliminar || !datosEliminar.fecha || datosEliminar.turno === undefined) {
            console.error('Error: Faltan datos para verificar estado', datosEliminar);
            // Si faltan datos críticos, mostrar modal por seguridad
            window.datosEliminacionPendiente = datosEliminar;
            window.checkboxEliminacionPendiente = checkbox;
            if (telarData) {
                window.telarDataCompleto = telarData;
            }
            const telarSection = checkbox.closest('.telar-section') || checkbox.closest('[data-telar]');
            const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
            window.telarDataPendiente = {
                salon: salon,
                telarId: telarId
            };
            window.mostrarModalTelaReservada();
            return;
        }

        // Incluir fecha y turno en la verificación para verificar el registro específico
        // Asegurar que la fecha esté en formato YYYY-MM-DD
        let fechaParaVerificar = String(datosEliminar.fecha);
        // Si la fecha viene en formato diferente, intentar convertirla
        if (fechaParaVerificar && !fechaParaVerificar.match(/^\d{4}-\d{2}-\d{2}$/)) {
            try {
                const fechaParsed = new Date(fechaParaVerificar);
                if (!isNaN(fechaParsed.getTime())) {
                    const año = fechaParsed.getFullYear();
                    const mes = String(fechaParsed.getMonth() + 1).padStart(2, '0');
                    const dia = String(fechaParsed.getDate()).padStart(2, '0');
                    fechaParaVerificar = `${año}-${mes}-${dia}`;
                }
            } catch (e) {
                // Si no se puede parsear, usar la fecha tal cual
            }
        }

        const params = new URLSearchParams({
            no_telar: String(telarId),
            tipo: String(tipo),
            fecha: fechaParaVerificar,
            turno: String(datosEliminar.turno)
        });

        const response = await fetch(`/inventario-telares/verificar-estado?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            cache: 'no-cache' // Forzar que siempre consulte el servidor, no use caché del navegador
        });

        // Verificar que la respuesta sea válida
        if (!response.ok) {
            // Si es 404 (registro no encontrado), puede ser que ya fue eliminado
            if (response.status === 404) {
                const errorResult = await response.json().catch(() => ({ success: false, message: 'Registro no encontrado' }));
                // Si el registro no existe, eliminar directamente (ya fue eliminado)
                eliminarRegistro(datosEliminar, checkbox);
                return;
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        // Guardar datos completos del telar si están disponibles
        if (telarData) {
            window.telarDataCompleto = telarData;
        }

        // Verificar que la respuesta tenga la estructura esperada
        if (!result || typeof result !== 'object') {
            throw new Error('Respuesta inválida del servidor');
        }

        if (result.success === true) {
            // Si tiene reservado, mostrar modal
            // IMPORTANTE: Verificar explícitamente que reservado sea true (no solo truthy)
            if (result.reservado === true || result.reservado === 1 || result.reservado === '1') {
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                // Guardar datos completos del telar para usar en actualización (si están disponibles)
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                // Guardar datos del telar para usar en actualización
                const telarSection = checkbox.closest('.telar-section') || checkbox.closest('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            } else if (result.programado === true || result.programado === 1 || result.programado === '1') {
                // Si solo está programado (sin reservado), también mostrar modal
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                // Guardar datos completos del telar para usar en actualización (si están disponibles)
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                // Guardar datos del telar para usar en actualización
                const telarSection = checkbox.closest('.telar-section') || checkbox.closest('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            } else {
                // Si no tiene reservado ni programado, eliminar directamente
                eliminarRegistro(datosEliminar, checkbox);
            }
        } else {
            // Si hay error al verificar, verificar el tipo de error
            if (result.message && result.message.includes('no encontrado')) {
                // Si el registro no existe, eliminar directamente (ya fue eliminado)
                eliminarRegistro(datosEliminar, checkbox);
            } else {
                // Para otros errores, SIEMPRE mostrar modal por seguridad
                // NO confiar en telarData porque puede estar desactualizado después de múltiples operaciones
                // El backend es la única fuente de verdad confiable
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                const telarSection = checkbox.closest('.telar-section') || checkbox.closest('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            }
        }
    } catch (error) {
        console.error('Error al verificar estado:', error);
        // Si hay error de conexión o excepción, SIEMPRE mostrar modal por seguridad
        // NO confiar en telarData porque puede estar desactualizado después de múltiples operaciones
        // El backend es la única fuente de verdad confiable
        window.datosEliminacionPendiente = datosEliminar;
        window.checkboxEliminacionPendiente = checkbox;
        if (telarData) {
            window.telarDataCompleto = telarData;
        }
        const telarSection = checkbox.closest('.telar-section') || checkbox.closest('[data-telar]');
        const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
        window.telarDataPendiente = {
            salon: salon,
            telarId: telarId
        };
        window.mostrarModalTelaReservada();
    }
}

// Función para mostrar el modal de tela reservada (debe ser global)
window.mostrarModalTelaReservada = function() {
    // Buscar el modal en el DOM - puede estar en cualquier lugar
    let modal = document.getElementById('modalTelaReservada');

    // Si no se encuentra, puede que haya sido removido, buscar en el body o crear uno nuevo
    if (!modal) {
        // Buscar en todo el documento
        modal = document.querySelector('#modalTelaReservada');

        // Si aún no se encuentra, puede que haya sido removido del DOM
        // En este caso, necesitamos recrearlo o buscarlo de otra manera
        // Por ahora, simplemente retornar con un error silencioso
        console.warn('Modal modalTelaReservada no encontrado en el DOM');
        return;
    }

    // Asegurar que el modal esté en el body para que cubra toda la pantalla
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    // Asegurar que el modal cubra toda la pantalla con z-index muy alto
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '100001'; // Mayor que el modal 2 (100000)
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.display = 'flex'; // Forzar display flex
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.6)'; // Asegurar backdrop
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Asegurar que el contenido interno también tenga z-index alto
    const modalContent = modal.querySelector('div > div');
    if (modalContent) {
        modalContent.style.position = 'relative';
        modalContent.style.zIndex = '100002';
    }
};

// Función para mostrar el modal de calendario semanal (debe ser global)
window.mostrarModalCalendarioSemanal = function() {
    const modal = document.getElementById('modalCalendarioSemanal');
    const grid = document.getElementById('calendarioSemanalGrid');

    if (!modal || !grid) return;

    // Obtener el lunes de la semana actual
    const hoy = new Date();
    const diaSemana = hoy.getDay(); // 0 = domingo, 1 = lunes, etc.
    const diff = diaSemana === 0 ? -6 : 1 - diaSemana; // Ajustar para que el lunes sea el primer día
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() + diff);
    lunes.setHours(0, 0, 0, 0);

    // Nombres de los días
    const nombresDias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    // Limpiar grid
    grid.innerHTML = '';

    // Obtener la fecha original para resaltarla en azul
    const fechaOriginalISO = window.fechaOriginalPendienteCalendario;

    // Generar los 7 días de la semana
    for (let i = 0; i < 7; i++) {
        const fecha = new Date(lunes);
        fecha.setDate(lunes.getDate() + i);

        const dia = fecha.getDate();
        const mes = fecha.getMonth() + 1;
        const año = fecha.getFullYear();
        const fechaISO = `${año}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const fechaFormato = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;

        const esHoy = fecha.toDateString() === hoy.toDateString();
        // Verificar si es la fecha original (la que se está actualizando)
        const esFechaOriginal = fechaOriginalISO && fechaISO === fechaOriginalISO;

        const diaElement = document.createElement('button');
        diaElement.type = 'button';
        diaElement.className = `p-4 md:p-6 lg:p-8 border-r border-gray-300 last:border-r-0 transition-all hover:bg-gray-100 flex flex-col items-center justify-center min-h-[100px] md:min-h-[120px] ${
            esFechaOriginal
                ? 'bg-blue-600 border-blue-700'
                : esHoy
                ? 'bg-blue-100 border-blue-400'
                : 'bg-white'
        }`;
        diaElement.style.width = '100%';
        diaElement.style.display = 'flex';
        diaElement.style.flexDirection = 'column';
        diaElement.style.borderTop = '1px solid #d1d5db';
        diaElement.style.borderBottom = '1px solid #d1d5db';
        diaElement.innerHTML = `
            <div class="text-2xl md:text-3xl lg:text-4xl font-bold ${esFechaOriginal ? 'text-white' : 'text-gray-900'}">${dia}</div>
        `;
        diaElement.setAttribute('data-fecha', fechaISO);
        diaElement.onclick = () => seleccionarFechaCalendario(fechaISO);

        grid.appendChild(diaElement);
    }

    // Forzar que el grid sea horizontal
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
    grid.style.gridAutoFlow = 'row';
    grid.style.width = '100%';

    // Mover el modal al body si no está ya ahí
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    // Asegurar que el modal 1 esté completamente oculto
    const modales1 = document.querySelectorAll('#modalTelaReservada');
    modales1.forEach(m => {
        m.classList.add('hidden');
        m.classList.remove('flex');
        m.style.display = 'none';
    });

    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '100000'; // Mayor que el modal 1 para estar por encima
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.display = 'flex'; // Forzar display flex
    modal.style.visibility = 'visible'; // Asegurar que sea visible
    modal.style.opacity = '1'; // Asegurar opacidad completa
    modal.style.transform = 'none'; // Resetear transform
    modal.classList.remove('hidden');
    modal.classList.add('flex');
};

// Función para cerrar el modal de calendario semanal (debe ser global)
window.cerrarModalCalendarioSemanal = function() {
    const modal = document.getElementById('modalCalendarioSemanal');

    // Cerrar completamente TODOS los modales 2 INMEDIATAMENTE - MÉTODO AGRESIVO
    // Buscar TODOS los modales 2 porque puede haber múltiples instancias (una por telar)
    const todosLosModales2 = document.querySelectorAll('#modalCalendarioSemanal');

    todosLosModales2.forEach((modalItem, index) => {

        // Usar setAttribute con !important para sobrescribir estilos inline
        modalItem.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modalItem.classList.add('hidden');
        modalItem.classList.remove('flex');

        // Remover del DOM completamente
        const modalParent = modalItem.parentElement;
        if (modalParent) {
            modalParent.removeChild(modalItem);
        }
    });

    // También cerrar el modal encontrado por ID (por compatibilidad) si no se encontraron otros
    if (modal && todosLosModales2.length === 0) {
        modal.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        const modalParent = modal.parentElement;
        if (modalParent) {
            modalParent.removeChild(modal);
        }
    }

    // Guardar las referencias ANTES de limpiar cualquier cosa
    const datosPendientes = window.datosEliminacionPendiente;
    const checkboxPendiente = window.checkboxEliminacionPendiente;
    const telarDataPendiente = window.telarDataPendiente;
    const checkboxCalendario = window.checkboxPendienteCalendario;

    // Limpiar variables del calendario (pero mantener las del modal de confirmación)
    // Solo limpiar si NO se está seleccionando una fecha (es decir, si se cancela)
    // Si se seleccionó una fecha, las variables ya se limpiaron en seleccionarFechaCalendario
    if (checkboxCalendario && window.telarIdPendienteCalendario) {
        // Si aún existen las variables del calendario, significa que se canceló
        window.checkboxPendienteCalendario = null;
        window.telarIdPendienteCalendario = null;
        window.tipoPendienteCalendario = null;
        window.turnoPendienteCalendario = null;
        window.fechaOriginalPendienteCalendario = null;
    }
    // NO limpiar telarDataPendiente, datosEliminacionPendiente ni checkboxEliminacionPendiente
    // porque se necesitan para restaurar el modal 1 cuando se cancela

    // Si se cancela, restaurar el modal de confirmación (modal 1)
    // Las variables window.datosEliminacionPendiente y window.checkboxEliminacionPendiente
    // se mantienen porque no se limpiaron al mostrar el calendario
    if (datosPendientes && checkboxPendiente) {
        // Restaurar las variables por si se perdieron
        window.datosEliminacionPendiente = datosPendientes;
        window.checkboxEliminacionPendiente = checkboxPendiente;
        if (telarDataPendiente) {
            window.telarDataPendiente = telarDataPendiente;
        }

        // Delay más largo para asegurar que el modal 2 se cierre completamente
        setTimeout(() => {
            // Verificar y forzar cierre de TODOS los modales 2 - MÉTODO AGRESIVO
            const todosLosModales2 = document.querySelectorAll('#modalCalendarioSemanal');

            todosLosModales2.forEach((modal2, index) => {
                // Usar setAttribute con !important para sobrescribir estilos inline
                modal2.setAttribute('style', `
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    z-index: -9999 !important;
                    pointer-events: none !important;
                    position: fixed !important;
                    top: -9999px !important;
                    left: -9999px !important;
                    right: auto !important;
                    bottom: auto !important;
                    width: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
                    max-width: 0 !important;
                    max-height: 0 !important;
                `);
                modal2.classList.add('hidden');
                modal2.classList.remove('flex');

                // Remover del DOM completamente
                const modal2Parent = modal2.parentElement;
                if (modal2Parent) {
                    modal2Parent.removeChild(modal2);
                }
            });

            // Pequeño delay adicional antes de mostrar modal 1
            setTimeout(() => {
                // Mostrar el modal de tela reservada nuevamente (modal 1) con z-index alto
                if (typeof window.mostrarModalTelaReservada === 'function') {
                    window.mostrarModalTelaReservada();
                } else {
                    console.error('mostrarModalTelaReservada no está definida');
                }
            }, 50);
        }, 200);
    } else {
        // Si no hay datos pendientes del modal 1, re-marcar el checkbox
        if (checkboxCalendario) {
            checkboxCalendario.checked = true;
        }
    }
};

// Función para seleccionar fecha del calendario
function seleccionarFechaCalendario(fechaISO) {
    if (!window.checkboxPendienteCalendario || !window.telarIdPendienteCalendario) {
        cerrarModalCalendarioSemanal();
        return;
    }

    // Guardar referencias antes de actualizar
    const checkbox = window.checkboxPendienteCalendario;
    const telarId = window.telarIdPendienteCalendario;
    const tipo = window.tipoPendienteCalendario;
    const turno = window.turnoPendienteCalendario;
    const fechaOriginal = window.fechaOriginalPendienteCalendario;

    // Actualizar la fecha del registro
    const datosActualizar = {
        no_telar: String(telarId),
        tipo: tipo,
        fecha_original: fechaOriginal,
        turno: turno,
        fecha_nueva: fechaISO
    };

    // Hacer la petición de actualización y luego recargar la página
    axios.post('/inventario-telares/actualizar-fecha', datosActualizar, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        }
    })
    .then(() => {
        // Recargar la página después de actualizar
        window.location.reload();
    })
    .catch(error => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error al actualizar',
                text: error.response?.data?.message || 'No se pudo actualizar la fecha del registro.',
                showConfirmButton: true
            });
        }
    });
}

// Función para actualizar el registro con la nueva fecha
async function actualizarRegistroConNuevaFecha(checkbox, telarId, tipo, turno, fechaOriginal, fechaNueva) {
    // Obtener el salón del contexto guardado o del elemento
    const salon = window.telarDataPendiente?.salon ||
                 checkbox.closest('.telar-section')?.dataset?.salon ||
                 checkbox.closest('[data-telar]')?.dataset?.salon ||
                 window.salonTelar ||
                 'Jacquard';

    try {
        // Primero intentar usar los datos completos del telar guardados desde handleRequerimientoChange
        let telarData = window.telarDataCompleto;

        // Si no están disponibles, intentar obtener desde el caché
        if (!telarData) {
            try {
                // Obtener inventario completo desde el caché
                const inventario = await obtenerInventarioConCache();

                if (Array.isArray(inventario) && inventario.length > 0) {
                    telarData = inventario.find(t => String(t.no_telar) === String(telarId) || String(t.Telar) === String(telarId));
                }
            } catch (errorCache) {
                console.warn('Error al obtener desde caché, intentando obtener directamente:', errorCache);
            }

            // Si no se encontró en el caché, intentar obtener con filtro específico del telar
            if (!telarData) {
                try {
                    const inventarioFiltrado = await obtenerInventarioConCache({ no_telar: String(telarId) });
                    if (Array.isArray(inventarioFiltrado) && inventarioFiltrado.length > 0) {
                        telarData = inventarioFiltrado[0]; // El primero debería ser el telar buscado
                    }
                } catch (errorFiltrado) {
                    console.warn('Error al obtener con filtro:', errorFiltrado);
                }
            }
        }

        if (!telarData) {
            throw new Error('No se encontraron datos del telar. Por favor, recargue la página.');
        }

        // Obtener cuenta, calibre, hilo y orden según el tipo
        // Los datos pueden venir con diferentes nombres de campos según la fuente
        const cuenta = tipo === 'Rizo'
            ? (telarData.Cuenta || telarData.cuenta || '')
            : (telarData.Cuenta_Pie || telarData.CuentaPie || telarData.cuenta_pie || '');

        const calibre = tipo === 'Rizo'
            ? (telarData.CalibreRizo2 || telarData.CalibreRizo || telarData.calibre_rizo || null)
            : (telarData.CalibrePie2 || telarData.CalibrePie || telarData.calibre_pie || null);

        const hilo = tipo === 'Rizo'
            ? (telarData.Fibra_Rizo || telarData.FibraRizo || telarData.fibra_rizo || telarData.hilo || '')
            : (telarData.Fibra_Pie || telarData.FibraPie || telarData.fibra_pie || telarData.hilo || '');

        const noOrden = telarData.Orden_Prod || telarData.OrdenProd || telarData.orden_prod || telarData.no_orden || '';

        if (!cuenta || cuenta === '') {
            throw new Error('No se encontró cuenta para este telar. Verifique los datos del telar.');
        }

        // Preparar datos para crear el nuevo registro
        const datosCrear = {
            no_telar: String(telarId),
            tipo: tipo,
            fecha: fechaNueva,
            turno: turno,
            cuenta: String(cuenta),
            calibre: calibre ? parseFloat(calibre) : null,
            salon: salon,
            hilo: hilo || '',
            no_orden: String(noOrden || '')
        };

        // Actualizar la fecha del registro existente en lugar de eliminar y crear
        try {
            const datosActualizar = {
                no_telar: String(telarId),
                tipo: tipo,
                fecha_original: fechaOriginal,
                turno: turno,
                fecha_nueva: fechaNueva
            };

            const responseActualizar = await axios.post('/inventario-telares/actualizar-fecha', datosActualizar, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                }
            });

            if (!responseActualizar.data || responseActualizar.data.success === false) {
                throw new Error(responseActualizar.data?.message || 'Error al actualizar la fecha del registro');
            }
        } catch (errorActualizar) {
            console.error('Error al actualizar fecha del registro:', errorActualizar);
            throw new Error(errorActualizar.response?.data?.message || 'No se pudo actualizar la fecha del registro. El registro original se mantiene.');
        }

        // Invalidar caché para forzar recarga
        invalidarCacheInventario();

        // Desmarcar el checkbox viejo (de la fecha original)
        checkbox.checked = false;
        checkbox.setAttribute('data-eliminado', 'true');
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        // Recargar requerimientos para que aparezca en la nueva fecha y se marque el nuevo checkbox
        setTimeout(() => {
            // Obtener el tipo normalizado para la recarga
            const tipoNormalizado = tipo === 'Rizo' ? 'rizo' : 'pie';

            // Intentar obtener la fibra del telar para recargar con filtro si es necesario
            const fibraNormalizada = hilo ? String(hilo).trim().toLowerCase() : '';
            const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';

            if (fibraValida && typeof loadRequerimientosConFiltro === 'function') {
                loadRequerimientosConFiltro(telarId, salon, tipoNormalizado, fibraNormalizada);
            } else if (typeof loadRequerimientos === 'function') {
                loadRequerimientos(telarId, salon);
            }
        }, 300);

        // Formatear fecha para mostrar
        const [año, mes, dia] = fechaNueva.split('-');
        const fechaFormato = `${dia}/${mes}`;

        // Mostrar notificación de éxito
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Actualizado con éxito',
                text: `Fecha actualizada a ${fechaFormato}. El registro aparecerá en la nueva fecha.`,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
    } catch (error) {
        console.error('Error al actualizar registro:', error);

        // Re-marcar el checkbox en caso de error (el registro original sigue existiendo)
        checkbox.checked = true;
        checkbox.removeAttribute('data-eliminado');
        checkbox.removeAttribute('data-cambio-reciente');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error al actualizar',
                text: error.message || error.response?.data?.message || 'No se pudo actualizar el registro. El registro original se mantiene.',
                showConfirmButton: false,
                timer: 3000,
                position: 'top-end',
                toast: true
            });
        }
    }
}

// Función para cerrar el modal de tela reservada
window.cerrarModalTelaReservada = function() {

    // Guardar referencia al checkbox antes de limpiar variables
    const checkbox = window.checkboxEliminacionPendiente;

    // IMPORTANTE: NO remover el modal del DOM, solo ocultarlo
    // Esto asegura que siempre esté disponible para futuras aperturas

    // FORZAR cierre completo de TODOS los modales 2 (calendario) - PRIMERO
    // Buscar TODOS los modales 2 porque puede haber múltiples instancias (una por telar)
    const todosLosModales2 = document.querySelectorAll('#modalCalendarioSemanal');

    todosLosModales2.forEach((modal2, index) => {
        // Usar setAttribute con !important para sobrescribir estilos inline
        modal2.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modal2.classList.add('hidden');
        modal2.classList.remove('flex');

        // Remover del DOM completamente
                const modal2Parent = modal2.parentElement;
                if (modal2Parent) {
                    modal2Parent.removeChild(modal2);
                }
    });

    // FORZAR cierre completo de TODOS los modales 1 - SEGUNDO
    const modales = document.querySelectorAll('#modalTelaReservada');
    modales.forEach((modal, index) => {
        // Usar setAttribute con !important para sobrescribir estilos inline
        modal.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        // IMPORTANTE: NO remover del DOM, solo ocultar
        // Esto asegura que el modal siempre esté disponible para futuras aperturas
        // El modal debe permanecer en el DOM, solo oculto
    });

    // Limpiar TODAS las variables después de cerrar ambos modales
    window.datosEliminacionPendiente = null;
    window.checkboxEliminacionPendiente = null;
    window.checkboxPendienteCalendario = null;
    window.telarIdPendienteCalendario = null;
    window.tipoPendienteCalendario = null;
    window.turnoPendienteCalendario = null;
    window.fechaOriginalPendienteCalendario = null;
    window.telarDataPendiente = null;
    window.telarDataCompleto = null;

    // Si hay un checkbox pendiente, significa que se canceló la eliminación
    // Hacer reload de la página para limpiar todo y cargar el estado correcto
    if (checkbox) {
        // Recargar la página para limpiar todo y cargar el estado correcto
        window.location.reload();
    }
};

// Función para confirmar eliminación con reserva (elimina el registro y la reserva)
function confirmarEliminarConReserva() {
    if (window.datosEliminacionPendiente && window.checkboxEliminacionPendiente) {
        // Guardar referencias antes de cerrar el modal (que limpia las variables)
        const datosEliminar = window.datosEliminacionPendiente;
        const checkbox = window.checkboxEliminacionPendiente;

        // Limpiar la referencia del checkbox antes de eliminar
        // para que cerrarModalTelaReservada no haga reload
        window.checkboxEliminacionPendiente = null;

        // Cerrar el modal inmediatamente
        cerrarModalTelaReservada();

        // Luego ejecutar la eliminación con las referencias guardadas
        eliminarRegistro(datosEliminar, checkbox);
    }
}

// Función para mostrar calendario cuando se presiona "Actualizar"
function mostrarCalendarioParaActualizar() {
    // Guardar datos del checkbox pendiente para usar después de seleccionar fecha
    // IMPORTANTE: NO limpiar window.datosEliminacionPendiente ni window.checkboxEliminacionPendiente
    // porque se necesitan para restaurar el modal 1 si se cancela
    if (window.checkboxEliminacionPendiente && window.datosEliminacionPendiente) {
        window.checkboxPendienteCalendario = window.checkboxEliminacionPendiente;
        window.telarIdPendienteCalendario = window.datosEliminacionPendiente.no_telar;
        window.tipoPendienteCalendario = window.datosEliminacionPendiente.tipo;
        window.turnoPendienteCalendario = window.datosEliminacionPendiente.turno;
        window.fechaOriginalPendienteCalendario = window.datosEliminacionPendiente.fecha;
        // Mantener los datos del telar
        window.telarDataPendiente = window.telarDataPendiente || {
            salon: 'Jacquard',
            telarId: window.datosEliminacionPendiente.no_telar
        };

        // Cerrar completamente el modal de confirmación (modal 1)
        const modales = document.querySelectorAll('#modalTelaReservada');
        modales.forEach(modal => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.style.display = 'none';
            // Asegurar que el z-index no interfiera
            modal.style.zIndex = '99999';
        });

        // Pequeño delay para asegurar que el modal 1 se cierre completamente
        setTimeout(() => {
        // Mostrar modal de calendario semanal (modal 2)
        window.mostrarModalCalendarioSemanal();
        }, 100);
    }
}

// Función para eliminar el registro
function eliminarRegistro(datosEliminar, checkbox) {
    // Validar que el checkbox existe
    if (!checkbox) {
        console.error('Error: checkbox es null en eliminarRegistro');
        return;
    }

    // Marcar el checkbox como eliminado permanentemente para evitar que se vuelva a marcar
    checkbox.setAttribute('data-eliminado', 'true');
    checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

    // Asegurar que el checkbox esté desmarcado
    checkbox.checked = false;

    // Para DELETE, axios envía los datos en el body pero Laravel los lee desde input()
    axios({
        method: 'delete',
        url: '/inventario-telares/eliminar',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        data: datosEliminar
    })
    .then(response => {
        // Invalidar caché para que se actualice en la próxima carga (pero NO recargar automáticamente)
        invalidarCacheInventario();

        // El checkbox ya está desmarcado visualmente, mantenerlo así
        // Asegurar que el atributo data-eliminado esté presente para que no se vuelva a marcar nunca
        checkbox.setAttribute('data-eliminado', 'true');
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        // Asegurar que el checkbox esté desmarcado
        checkbox.checked = false;

        // NO recargar automáticamente los requerimientos - el checkbox permanecerá desmarcado
        // Si el usuario necesita ver los cambios, puede recargar manualmente la página

        // Mostrar notificación de éxito
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Eliminado con éxito',
                showConfirmButton: false,
                timer: 700,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
    })
    .catch(error => {
        // Si hay error, remover el atributo de eliminado para permitir reintentos
        checkbox.removeAttribute('data-eliminado');

        // Mostrar notificación de error
        if (typeof Swal !== 'undefined') {
            let errorMessage = 'Error desconocido';

            if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar',
                text: errorMessage,
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
        // Re-marcar el checkbox si hubo error
        checkbox.checked = true;
    });
}
</script>

