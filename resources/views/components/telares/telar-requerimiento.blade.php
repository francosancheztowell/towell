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
                        onclick="abrirModalSeleccion('{{ $telar->Telar }}', 'rizo', '{{ $telar->Cuenta ?? '' }}', '{{ $telar->CalibreRizo2 ?? '' }}', '{{ $telar->Fibra_Rizo ?? '' }}')"
                    >
                        <i class="fas fa-chevron-right text-sm"></i>
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

        // Esperar a que las tablas estén renderizadas antes de cargar requerimientos
        // Usar setTimeout para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            // Cargar requerimientos existentes (filtrado por salón)
            loadRequerimientos(telarId, salonTelar);
        }, 100);

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
            console.log(`📅 Usando fecha modificada del header: ${fechaISO}`);

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
    const ordenSiguiente = ordenSigData;

    const cuentaRizo = datos.Cuenta || '';
    const cuentaPie = datos.Cuenta_Pie || '';
    const calibreRizo = datos.CalibreRizo2 || 0;
    const calibrePie = datos.CalibrePie2 || 0;
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

    console.log(`💾 Guardando registro con fecha: ${fechaConvertida} (${fechaISO ? 'fecha modificada del header' : 'fecha del calendario'})`);

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

            // Buscar todas las tablas que contienen checkboxes de este telar
            // IMPORTANTE: Las tablas deben estar en orden (primera = hoy)
            const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
            const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
                const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"]`) !== null;
                return tieneCheckboxDelTelar;
            });

            console.log(`🔍 Telar ${telarId}: Total de tablas en el documento: ${todasLasTablasDelDocumento.length}`);
            console.log(`🔍 Telar ${telarId}: Tablas con checkboxes del telar: ${todasLasTablasDelTelar.length}`);

            if (todasLasTablasDelTelar.length === 0) {
                console.error(`❌ ERROR: No se encontraron tablas para el telar ${telarId}`);
                // Intentar buscar de nuevo después de un delay
                setTimeout(() => {
                    console.log(`🔄 Reintentando buscar tablas para telar ${telarId}...`);
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
                    const fechaTexto = th.innerText.trim();
                    console.log(`   Tabla ${index + 1}: ${fechaTexto}`);
                }
            });

            // La primera tabla es la del primer día (hoy) - debe ser la primera en el orden del DOM
            const primeraTabla = todasLasTablasDelTelar[0];

            if (!primeraTabla) {
                console.error(`❌ ERROR: No se pudo obtener la primera tabla para el telar ${telarId}`);
                return;
            }

            const primeraTablaFecha = primeraTabla.querySelector('th')?.innerText.trim() || 'N/A';
            console.log(`✅ Primera tabla del telar ${telarId}: ${primeraTablaFecha}`);

            // Limpiar todos los checkboxes de este telar en todas las tablas
            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                    checkbox.checked = false;
                });
            });

            // Obtener el rango de fechas del calendario (primer día = hoy, último día = hoy + 6)
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const ultimoDia = new Date(hoy);
            ultimoDia.setDate(hoy.getDate() + 6); // 7 días totales (hoy + 6 días más)
            ultimoDia.setHours(23, 59, 59, 999);

            // Log para depuración
            console.log(`Telar ${telarId}: Rango del calendario - Desde: ${hoy.toISOString().split('T')[0]}, Hasta: ${ultimoDia.toISOString().split('T')[0]}`);

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
                    console.error('Error parseando fecha:', fechaISO, e);
                }
                return null;
            }

            // Función para obtener la primera tabla del calendario (hoy)
            function obtenerPrimeraTabla() {
                return primeraTabla; // Primera tabla = hoy (primer día del calendario)
            }

            // Marcar por coincidencia de telar+tipo+fecha+turno Y SALON
            console.log(`🔍 Buscando registros para telar ${telarId}, salón esperado: "${salon}"`);
            console.log(`📊 Total de registros disponibles en inventario: ${registros.length}`);

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

                // Log detallado para cada registro del telar
                console.log(`  📋 Registro: Telar=${reg.no_telar}, Salon="${reg.salon}" (esperado: "${salon}"), Coincide=${coincide}, Fecha=${reg.fecha}, Tipo=${reg.tipo}, Turno=${reg.turno}, Status=${reg.status || 'N/A'}`);

                return coincide;
            });

            console.log(`✅ Total de registros del telar ${telarId} después de filtrar: ${registrosTelar.length}`);

            if (registrosTelar.length === 0) {
                console.warn(`⚠️ No se encontraron registros para telar ${telarId} con salón "${salon}"`);
                // Mostrar todos los registros del telar para depuración
                const todosRegistrosTelar = registros.filter(reg => String(reg.no_telar) === String(telarId));
                console.log(`📋 Registros encontrados para telar ${telarId} (sin filtrar por salón):`, todosRegistrosTelar);
                if (todosRegistrosTelar.length > 0) {
                    console.log(`💡 Sugerencia: Verificar que el salón del registro coincida con el salón esperado.`);
                    console.log(`   Salones en registros:`, [...new Set(todosRegistrosTelar.map(r => r.salon))]);
                }
            }

            // PASO 1: Identificar si hay fechas antiguas para actualizar headers PRIMERO
            let fechaMasAntiguaEnPrimeraTabla = null;

            registrosTelar.forEach(reg => {
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                if (fechaRegistro) {
                    const timestampRegistro = fechaRegistro.getTime();
                    const timestampHoy = hoy.getTime();
                    const fechaEsAnterior = timestampRegistro < timestampHoy;

                    // Si la fecha es anterior, rastrearla para actualizar el header
                    if (fechaEsAnterior && fechaRegistro) {
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

            // Si hay fechas antiguas, actualizar TODOS los headers primero
            if (fechaMasAntiguaEnPrimeraTabla) {
                console.log(`📅 Fecha antigua detectada: ${fechaMasAntiguaEnPrimeraTabla.toISOString().split('T')[0]}. Actualizando headers...`);

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
                    console.log(`✅ Primera tabla: "${fechaFormateada1.fechaFormateada} ${fechaFormateada1.diaSemana}" (fecha antigua)`);
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
                        console.log(`📅 Tabla ${index + 1} OCULTADA (excede rango - se recorta)`);
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

                    if (index === 1) {
                        console.log(`✅ Segunda tabla: "${fechaFormateada.fechaFormateada} ${fechaFormateada.diaSemana}" (HOY)`);
                    }
                });
            } else {
                // Si no hay fechas antiguas, asegurar que todas las tablas estén visibles
                todasLasTablasDelTelar.forEach(tabla => {
                    tabla.style.display = '';
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

                    console.log(`📅 Procesando: Fecha=${fechaISO}, Anterior=${fechaEsAnterior}, Posterior=${fechaEsPosterior}`);

                    if (fechaEsAnterior || fechaEsPosterior) {
                        // Fecha fuera del rango: usar primera tabla
                        usarPrimeraFecha = true;
                        tablaDestino = obtenerPrimeraTabla();
                        console.log(`✅ Usando primera tabla para fecha ${fechaISO}`);
                    } else {
                        // Fecha dentro del rango: buscar tabla por fecha completa en atributo
                        let thFecha = null;
                        todasLasTablasDelTelar.forEach(tabla => {
                            if (tabla.style.display === 'none') return; // Saltar tablas ocultas
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
                            console.log(`✅ Tabla encontrada para fecha ${fechaISO}`);
                        } else {
                            // Buscar por texto como fallback
                            const [y, m, d] = fechaISO.split('-');
                            const fechaDM = d && m ? `${parseInt(d)}/${parseInt(m)}` : '';
                            thFecha = Array.from(fechasTablas).find(th => {
                                if (!th.closest('table') || th.closest('table').style.display === 'none') return false;
                                return th.innerText.trim().includes(fechaDM);
                            });

                            if (thFecha) {
                                tablaDestino = thFecha.closest('table');
                            } else {
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
                    console.warn(`⚠️ No se encontró tabla válida para fecha ${fechaISO}`);
                    return;
                }

                // Marcar checkbox
                const valorEsperado = `${tipo}${reg.turno}`;
                const checkboxes = tablaDestino.querySelectorAll(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                if (checkboxes.length === 0) {
                    console.warn(`⚠️ No se encontraron checkboxes para telar ${telarId}, tipo ${tipo}, turno ${reg.turno}`);
                    return;
                }

                checkboxes.forEach(cb => {
                    if (cb.value === valorEsperado) {
                        cb.checked = true;
                        if (usarPrimeraFecha && fechaISO) {
                            cb.title = `Fecha original: ${fechaISO} (mostrado en primera fecha del calendario)`;
                            cb.setAttribute('data-fecha-original', fechaISO);
                            cb.classList.add('fecha-antigua');
                            console.log(`✅ Checkbox marcado en primera tabla: ${tipo}${reg.turno} (fecha: ${fechaISO})`);
                        } else {
                            console.log(`✅ Checkbox marcado: ${tipo}${reg.turno} (fecha: ${fechaISO})`);
                        }
                    }
                });
            });
        })
        .catch(error => {
            console.error('Error al cargar requerimientos:', error);
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

