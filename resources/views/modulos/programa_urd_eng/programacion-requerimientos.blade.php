@extends('layouts.app')

@section('page-title', 'Programación de Requerimientos')

@section('navbar-right')
<div class="flex items-center gap-3">
    <!-- Botones de acción -->
    <button id="btnSiguiente" type="button" class="px-6 py-2.5 bg-blue-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 flex items-center gap-2 group">
        <i class="fa-solid fa-arrow-right w-4 h-4 group-hover:translate-x-1 transition-transform duration-200"></i>
    </button>
</div>
@endsection

@section('content')
<div class="w-full">

    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaRequerimientos" class="w-full">
                <thead>
                    <tr class="bg-gray-100 border-b border-gray-300">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Telar</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Fecha Req</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Cuenta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Calibre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Hilo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Urdido</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Tipo Atado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700  tracking-wider">Metros</th>
                    </tr>
                </thead>
                <tbody id="tbodyRequerimientos" class="bg-white divide-y divide-gray-200">
                    <!-- Las filas se agregarán dinámicamente aquí -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla de Resumen por Semana -->
    <div class="bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tablaResumen" class="w-full">
                <thead>
                    <tr class="bg-gray-100 ">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Telar</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Cuenta</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Hilo</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Modelo</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Semana 1</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Semana 2</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Semana 3</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Sem Actual +3</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800 tracking-wider">
                            <div class="flex items-center gap-2">
                                <span>Total</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyResumen" class="bg-white divide-y divide-gray-200">
                    <!-- Las filas se agregarán dinámicamente aquí -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // Obtener telares desde PHP (pasados desde el controlador)
    const telares = @json($telaresSeleccionados ?? []);

    // Si no hay telares desde PHP, intentar desde URL o sessionStorage
    let telaresData = telares && telares.length > 0 ? telares : [];

    if (telaresData.length === 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const telaresJson = urlParams.get('telares') || sessionStorage.getItem('selectedTelares');

        if (telaresJson) {
            try {
                telaresData = JSON.parse(decodeURIComponent(telaresJson));
                sessionStorage.removeItem('selectedTelares');
            } catch(e) {
                console.error('Error al parsear telares:', e);
            }
        }
    }

    // Opciones para dropdowns
    const opcionesUrdido = [
        'Mc Coy 1', 'Mc Coy 2', 'Mc Coy 3'
    ];

    const opcionesTipoAtado = [
        'Normal', 'Especial', 'Jacquard', 'Doble'
    ];

    const opcionesDestino = [
        'JACQUARD', 'ITEMA', 'xsITH', 'KARL MAYER', 'SULZER'
    ];

    // Función para formatear fecha (DD/MM/YYYY)
    function formatearFecha(fecha) {
        if (!fecha) return '';
        try {
            const d = new Date(fecha);
            if (isNaN(d.getTime())) return '';
            const dia = String(d.getDate()).padStart(2, '0');
            const mes = String(d.getMonth() + 1).padStart(2, '0');
            const año = d.getFullYear();
            return `${dia}/${mes}/${año}`;
        } catch(e) {
            return '';
        }
    }

    // Función para crear una fila de la tabla
    function crearFila(telar, index) {
        const fechaReq = telar.fecha_req || new Date().toISOString().split('T')[0];
        const fechaFormateada = formatearFecha(fechaReq);

        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-200';
        tr.dataset.index = index;
        tr.dataset.telarId = telar.no_telar || '';

        tr.innerHTML = `
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="text"
                       class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default"
                       value="${telar.no_telar || ''}"
                       data-field="telar"
                       disabled>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="date"
                       class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default"
                       value="${fechaReq}"
                       data-field="fecha_req"
                       disabled>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="text"
                       class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default"
                       value="${telar.cuenta || ''}"
                       data-field="cuenta"
                       disabled>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="number"
                       step="0.01"
                       class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default"
                       value="${telar.calibre || ''}"
                       data-field="calibre"
                       disabled>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="text"
                       class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default"
                       value="${telar.hilo || ''}"
                       data-field="hilo"
                       disabled>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <select class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                        data-field="urdido">
                    <option value="">Seleccione...</option>
                    ${opcionesUrdido.map(opt =>
                        `<option value="${opt}" ${telar.urdido === opt ? 'selected' : ''}>${opt}</option>`
                    ).join('')}
                </select>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <span class="px-3 py-2 inline-block text-xs font-medium ${telar.tipo === 'Rizo' ? 'bg-rose-100 text-rose-700' : telar.tipo === 'Pie' ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700'} rounded-md">
                    ${telar.tipo || 'N/A'}
                </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <select class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default appearance-none"
                        data-field="destino"
                        disabled>
                    ${opcionesDestino.map(opt =>
                        `<option value="${opt}" ${(telar.destino || telar.salon || 'JACQUARD').toUpperCase() === opt ? 'selected' : ''}>${opt}</option>`
                    ).join('')}
                </select>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <select class="w-full px-3 py-2 text-xs bg-transparent border-0 focus:outline-none cursor-default appearance-none"
                        data-field="tipo_atado"
                        disabled>
                    ${opcionesTipoAtado.map(opt =>
                        `<option value="${opt}" ${(telar.tipo_atado || 'Normal') === opt ? 'selected' : ''}>${opt}</option>`
                    ).join('')}
                </select>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="number"
                        placeholder="Ingrese metros"
                       step="0.01"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       value="${telar.metros || ''}"
                       data-field="metros">
            </td>

        `;

        // Nota: Los cambios en la tabla superior no afectan la tabla de resumen
        // porque la tabla de resumen obtiene datos directamente de ReqProgramaTejido

        // Agregar evento para eliminar fila
        const btnEliminar = tr.querySelector('.btnEliminarFila');
        if (btnEliminar) {
            btnEliminar.addEventListener('click', function() {
                if (confirm('¿Estás seguro de eliminar esta fila?')) {
                    tr.remove();
                    actualizarIndices();
                }
            });
        }

        return tr;
    }

    // Función para actualizar índices de las filas
    function actualizarIndices() {
        const filas = document.querySelectorAll('#tbodyRequerimientos tr');
        filas.forEach((fila, index) => {
            fila.dataset.index = index;
        });
        // La tabla de resumen se actualiza desde el servidor, no depende de los índices
    }

    // Función para agregar una nueva fila vacía (deshabilitada)
    function agregarFilaVacia() {
        const tbody = document.getElementById('tbodyRequerimientos');
        // Si hay mensaje de "no hay datos", limpiarlo
        if (tbody.children.length === 1 && tbody.children[0].querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }
        const nuevaFila = crearFila({
            no_telar: '',
            fecha_req: new Date().toISOString().split('T')[0],
            cuenta: '',
            calibre: '',
            hilo: '',
            urdido: '',
            tipo: 'Pie',
            destino: 'JACQUARD',
            tipo_atado: 'Normal',
            metros: ''
        }, tbody.children.length);
        tbody.appendChild(nuevaFila);
        // La tabla de resumen se actualiza desde el servidor
    }

    // Validar que todos los telares tengan el mismo tipo, calibre, hilo y salón
    function validarTelares(telares) {
        if (!telares || telares.length === 0) {
            return { valido: false, mensaje: 'No hay telares seleccionados' };
        }

        if (telares.length === 1) {
            return {
                valido: true,
                tipo: telares[0].tipo,
                calibre: telares[0].calibre || '',
                hilo: telares[0].hilo || '',
                salon: telares[0].salon || ''
            };
        }

        const primerTelar = telares[0];
        const tipoEsperado = String(primerTelar.tipo || '').toUpperCase().trim();
        const calibreEsperado = String(primerTelar.calibre || '').trim();
        const hiloEsperado = String(primerTelar.hilo || '').trim();
        const salonEsperado = String(primerTelar.salon || '').trim();

        // Solo validar tipo (es obligatorio)
        if (!tipoEsperado) {
            return { valido: false, mensaje: 'El telar debe tener un tipo definido' };
        }

        for (let i = 1; i < telares.length; i++) {
            const telar = telares[i];
            const tipoActual = String(telar.tipo || '').toUpperCase().trim();
            const calibreActual = String(telar.calibre || '').trim();
            const hiloActual = String(telar.hilo || '').trim();
            const salonActual = String(telar.salon || '').trim();

            // Tipo es obligatorio
            if (tipoActual !== tipoEsperado) {
                return {
                    valido: false,
                    mensaje: `El telar ${telar.no_telar || 'N/A'} tiene tipo "${telar.tipo || 'N/A'}" pero se esperaba tipo "${primerTelar.tipo || 'N/A'}". Todos los telares deben tener el mismo tipo.`
                };
            }

            // Calibre: solo validar si ambos tienen calibre definido
            if (calibreEsperado && calibreActual) {
                const calibreActualNum = parseFloat(calibreActual) || 0;
                const calibreEsperadoNum = parseFloat(calibreEsperado) || 0;
                if (Math.abs(calibreActualNum - calibreEsperadoNum) >= 0.01) {
                    return {
                        valido: false,
                        mensaje: `El telar ${telar.no_telar || 'N/A'} tiene calibre "${calibreActual}" pero se esperaba calibre "${calibreEsperado}". Todos los telares deben tener el mismo calibre.`
                    };
                }
            }

            // Hilo: solo validar si ambos tienen hilo definido
            if (hiloEsperado && hiloActual) {
                if (hiloActual !== hiloEsperado) {
                    return {
                        valido: false,
                        mensaje: `El telar ${telar.no_telar || 'N/A'} tiene hilo "${hiloActual}" pero se esperaba hilo "${hiloEsperado}". Todos los telares deben tener el mismo hilo.`
                    };
                }
            }

            // Salón: solo validar si ambos tienen salón definido
            if (salonEsperado && salonActual) {
                if (salonActual !== salonEsperado) {
                    return {
                        valido: false,
                        mensaje: `El telar ${telar.no_telar || 'N/A'} tiene salón "${salonActual}" pero se esperaba salón "${salonEsperado}". Todos los telares deben tener el mismo salón.`
                    };
                }
            }
        }

        return {
            valido: true,
            tipo: primerTelar.tipo,
            calibre: calibreEsperado || '',
            hilo: hiloEsperado || '',
            salon: salonEsperado || ''
        };
    }

    // Renderizar filas iniciales con los telares seleccionados
    function renderizarTabla() {
        const tbody = document.getElementById('tbodyRequerimientos');
        tbody.innerHTML = '';

        if (telaresData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-info-circle w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                        <p>No hay telares seleccionados. Agrega filas usando el botón "Agregar Fila".</p>
                    </td>
                </tr>
            `;
            return;
        }

        // Validar que todos los telares tengan el mismo tipo, calibre, hilo y salón
        const validacion = validarTelares(telaresData);
        if (!validacion.valido) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-red-500">
                        <i class="fa-solid fa-exclamation-triangle w-8 h-8 mx-auto mb-2 text-red-400"></i>
                        <p class="font-semibold">Error de validación</p>
                        <p class="text-sm mt-2">${validacion.mensaje}</p>
                        <p class="text-xs mt-4 text-gray-600">Por favor, selecciona solo telares con el mismo tipo, calibre, hilo y salón.</p>
                    </td>
                </tr>
            `;

            // Mostrar mensaje en la tabla de resumen también
            const tbodyResumen = document.getElementById('tbodyResumen');
            if (tbodyResumen) {
                tbodyResumen.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-red-500">
                            <i class="fa-solid fa-exclamation-triangle w-8 h-8 mx-auto mb-2 text-red-400"></i>
                            <p class="font-semibold">No se puede cargar el resumen</p>
                            <p class="text-sm mt-2">${validacion.mensaje}</p>
                        </td>
                    </tr>
                `;
            }
            return;
        }

        // Filtrar telares que no coincidan con la validación
        // Nota: No filtramos por cuenta - puede haber diferentes cuentas
        const telaresFiltrados = telaresData.filter(telar => {
            const tipoActual = String(telar.tipo || '').toUpperCase().trim();
            const tipoEsperado = String(validacion.tipo || '').toUpperCase().trim();

            // Tipo es obligatorio
            if (tipoActual !== tipoEsperado) {
                return false;
            }

            // Calibre: solo comparar si ambos tienen calibre
            if (validacion.calibre && telar.calibre) {
                const calibreActual = parseFloat(telar.calibre) || 0;
                const calibreEsperado = parseFloat(validacion.calibre) || 0;
                if (Math.abs(calibreActual - calibreEsperado) >= 0.01) {
                    return false;
                }
            }

            // Hilo: solo comparar si ambos tienen hilo
            if (validacion.hilo && telar.hilo) {
                if (String(telar.hilo || '').trim() !== String(validacion.hilo || '').trim()) {
                    return false;
                }
            }

            // Salón: solo comparar si ambos tienen salón
            if (validacion.salon && telar.salon) {
                if (String(telar.salon || '').trim() !== String(validacion.salon || '').trim()) {
                    return false;
                }
            }

            return true;
        });

        // Renderizar solo los telares válidos
        telaresFiltrados.forEach((telar, index) => {
            const fila = crearFila(telar, index);
            tbody.appendChild(fila);
        });

        // Cargar tabla de resumen desde el servidor después de cargar los datos
        if (telaresFiltrados.length > 0) {
            // Actualizar telaresData con los filtrados
            telaresData = telaresFiltrados;
            setTimeout(() => {
                cargarResumenSemanas();
            }, 100);
        } else {
            // Si no hay telares válidos, mostrar mensaje en la tabla de resumen
            const tbodyResumen = document.getElementById('tbodyResumen');
            if (tbodyResumen) {
                tbodyResumen.innerHTML = `
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            <i class="fa-solid fa-info-circle w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                            <p>No hay telares válidos para mostrar el resumen.</p>
                        </td>
                    </tr>
                `;
            }
        }
    }

    // Función para obtener todos los datos de la tabla
    function obtenerDatosTabla() {
        const filas = document.querySelectorAll('#tbodyRequerimientos tr');
        const datos = [];

        filas.forEach(fila => {
            const inputs = fila.querySelectorAll('input[data-field], select[data-field]');
            const filaData = {};

            inputs.forEach(input => {
                const field = input.dataset.field;
                let value = input.value;

                // Convertir números
                if (field === 'calibre' || field === 'metros') {
                    value = value ? parseFloat(value) : null;
                }

                filaData[field] = value;
            });

            // Agregar tipo (es estático en la vista)
            const tipoSpan = fila.querySelector('td:nth-child(7) span');
            if (tipoSpan) {
                filaData.tipo = tipoSpan.textContent.trim();
            }

            // Agregar telar ID
            filaData.telar_id = fila.dataset.telarId;

            if (filaData.telar || filaData.telar_id) {
                datos.push(filaData);
            }
        });

        return datos;
    }

    // Función para obtener el número de semana (1-5) basado en la fecha
    function obtenerSemana(fecha) {
        if (!fecha) return null;
        try {
            const fechaObj = new Date(fecha);
            if (isNaN(fechaObj.getTime())) return null;

            // Obtener la fecha actual y el inicio del mes actual
            const hoy = new Date();
            const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

            // Ajustar fechaObj a medianoche para evitar problemas de zona horaria
            const fechaAjustada = new Date(fechaObj.getFullYear(), fechaObj.getMonth(), fechaObj.getDate());
            const inicioAjustado = new Date(inicioMes.getFullYear(), inicioMes.getMonth(), inicioMes.getDate());

            // Calcular la diferencia en días desde el inicio del mes
            const diffDias = Math.floor((fechaAjustada - inicioAjustado) / (1000 * 60 * 60 * 24));

            // Si la fecha es anterior al inicio del mes, retornar null
            if (diffDias < 0) return null;

            // Calcular la semana (asumiendo que cada semana tiene 7 días)
            // Semana 1: días 0-6, Semana 2: días 7-13, Semana 3: días 14-20, Semana 4: días 21-27, Semana 5: días 28-34
            const semana = Math.floor(diffDias / 7) + 1;

            // Limitar a 5 semanas máximo
            return semana >= 1 && semana <= 5 ? semana : null;
        } catch(e) {
            console.error('Error al calcular semana:', e);
            return null;
        }
    }

    // Función para calcular el resumen por semana
    function calcularResumen() {
        const datos = obtenerDatosTabla();
        const resumen = {};

        datos.forEach(fila => {
            const telar = fila.telar || '';
            const cuenta = fila.cuenta || '';
            const hilo = fila.hilo || '';
            const modelo = fila.urdido || ''; // Usar urdido como modelo
            const metros = parseFloat(fila.metros || 0);
            const fechaReq = fila.fecha_req;
            const semana = obtenerSemana(fechaReq);

            if (!telar || !metros || metros <= 0) return;

            const clave = `${telar}|${cuenta}|${hilo}|${modelo}`;

            if (!resumen[clave]) {
                resumen[clave] = {
                    telar: telar,
                    cuenta: cuenta,
                    hilo: hilo,
                    modelo: modelo,
                    semana1: 0,
                    semana2: 0,
                    semana3: 0,
                    semana4: 0,
                    semana5: 0,
                    total: 0
                };
            }

            // Agregar metros a la semana correspondiente
            if (semana !== null && semana >= 1 && semana <= 5) {
                resumen[clave][`semana${semana}`] += metros;
            }

            // Agregar al total siempre (incluso si no tiene semana válida)
            resumen[clave].total += metros;
        });

        return Object.values(resumen);
    }

    // Función para cargar datos de resumen desde el servidor
    async function cargarResumenSemanas() {
        const tbody = document.getElementById('tbodyResumen');
        if (!tbody) return;

        // Mostrar loading
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                    <div class="flex items-center justify-center gap-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-2 border-gray-300 border-t-blue-500"></div>
                        <span>Cargando datos...</span>
                    </div>
                </td>
            </tr>
        `;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const url = '{{ route("programa.urd.eng.programacion.resumen.semanas") }}';

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    telares: telaresData
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                // Si hay un error de validación o del servidor, mostrar el mensaje específico
                const errorMessage = result.message || `Error al obtener datos (HTTP ${response.status})`;
                throw new Error(errorMessage);
            }

            // Renderizar datos
            renderizarTablaResumenDesdeServidor(result.data);

        } catch (error) {
            console.error('Error al cargar resumen de semanas:', error);
            let mensajeError = error.message || 'Error al cargar datos';

            // Si el error es de validación (400), mostrar el mensaje del servidor
            if (error.message && error.message.includes('mismo tipo y cuenta')) {
                mensajeError = error.message;
            }

            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-red-500">
                        <i class="fa-solid fa-exclamation-triangle w-8 h-8 mx-auto mb-2 text-red-400"></i>
                        <p class="font-semibold">Error al cargar datos</p>
                        <p class="text-sm mt-2">${mensajeError}</p>
                    </td>
                </tr>
            `;
        }
    }

    // Función para renderizar la tabla de resumen con datos del servidor
    function renderizarTablaResumenDesdeServidor(data) {
        const tbody = document.getElementById('tbodyResumen');
        if (!tbody) return;

        tbody.innerHTML = '';

        // Validar telares antes de mostrar datos
        const validacion = validarTelares(telaresData);
        if (!validacion.valido) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-red-500">
                        <i class="fa-solid fa-exclamation-triangle w-8 h-8 mx-auto mb-2 text-red-400"></i>
                        <p class="font-semibold">Error de validación</p>
                        <p class="text-sm mt-2">${validacion.mensaje}</p>
                    </td>
                </tr>
            `;
            return;
        }

        // Obtener tipo, calibre, hilo y salón esperados
        const tipoEsperado = String(validacion.tipo || '').toUpperCase().trim();
        const calibreEsperado = validacion.calibre || '';
        const hiloEsperado = validacion.hilo || '';
        const salonEsperado = validacion.salon || '';

        // Combinar datos de Rizo y Pie, filtrando por tipo, calibre, hilo y salón
        // Nota: La cuenta puede variar, así que NO la filtramos - mostramos todas las cuentas que coincidan con calibre/hilo/salón
        const todosLosDatos = [];

        // Agregar datos de Rizo solo si el tipo es Rizo
        if (tipoEsperado === 'RIZO' && data.rizo && Array.isArray(data.rizo) && data.rizo.length > 0) {
            data.rizo.forEach(item => {
                const hiloItem = String(item.Hilo || '').trim();
                // Solo agregar si el hilo coincide (para Rizo, el hilo es lo que importa)
                // No filtramos por cuenta - puede haber diferentes cuentas
                if (hiloItem === hiloEsperado) {
                    todosLosDatos.push({
                        tipo: 'Rizo',
                        telar: item.TelarId || '',
                        cuenta: item.CuentaRizo || '',
                        hilo: item.Hilo || '',
                        calibre: null,
                        modelo: item.Modelo || '',
                        semActual: item.SemActualMtsRizo || 0,
                        semActual1: item.SemActual1MtsRizo || 0,
                        semActual2: item.SemActual2MtsRizo || 0,
                        semActual3: item.SemActual3MtsRizo || 0,
                        total: item.Total || 0
                    });
                }
            });
        }

        // Agregar datos de Pie solo si el tipo es Pie
        if (tipoEsperado === 'PIE' && data.pie && Array.isArray(data.pie) && data.pie.length > 0) {
            data.pie.forEach(item => {
                const calibreItem = String(item.CalibrePie || '').trim();
                // Comparar calibres como números para evitar problemas de formato
                const calibreItemNum = parseFloat(calibreItem) || 0;
                const calibreEsperadoNum = parseFloat(calibreEsperado) || 0;
                // Solo agregar si el calibre coincide (para Pie, el calibre es lo que importa)
                // No filtramos por cuenta - puede haber diferentes cuentas
                if (Math.abs(calibreItemNum - calibreEsperadoNum) < 0.01) {
                    todosLosDatos.push({
                        tipo: 'Pie',
                        telar: item.TelarId || '',
                        cuenta: item.CuentaPie || '',
                        hilo: null,
                        calibre: item.CalibrePie || '',
                        modelo: item.Modelo || '',
                        semActual: item.SemActualMtsPie || 0,
                        semActual1: item.SemActual1MtsPie || 0,
                        semActual2: item.SemActual2MtsPie || 0,
                        semActual3: item.SemActual3MtsPie || 0,
                        total: item.Total || 0
                    });
                }
            });
        }

        if (todosLosDatos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-info-circle w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                        <p>No hay datos de programación para los telares seleccionados.</p>
                    </td>
                </tr>
            `;
            return;
        }

        // Renderizar filas
        todosLosDatos.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-200 hover:bg-gray-50';

            // Determinar columnas según el tipo
            let columnaCuenta = item.cuenta || '-';
            let columnaHilo = item.tipo === 'Rizo'
                ? (item.hilo || '-')
                : (item.calibre ? item.calibre.toString() : '-');

            tr.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${item.telar}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${columnaCuenta}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${columnaHilo}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${item.modelo || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700 text-right">${item.semActual > 0 ? item.semActual.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700 text-right">${item.semActual1 > 0 ? item.semActual1.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700 text-right">${item.semActual2 > 0 ? item.semActual2.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700 text-right">${item.semActual3 > 0 ? item.semActual3.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-gray-800 text-right">${item.total > 0 ? item.total.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-'}</td>
            `;

            tbody.appendChild(tr);
        });
    }

    // Función para renderizar la tabla de resumen (mantener para compatibilidad, pero ahora llama al servidor)
    function renderizarTablaResumen() {
        // Llamar a la función que carga desde el servidor
        cargarResumenSemanas();
    }

    // Event listeners
    document.getElementById('btnAgregarFila')?.addEventListener('click', function() {
        agregarFilaVacia();
    });

    document.getElementById('btnGuardar')?.addEventListener('click', function() {
        const datos = obtenerDatosTabla();
        console.log('Datos a guardar:', datos);

        // Aquí puedes agregar la lógica para guardar los datos
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Datos guardados',
            text: `${datos.length} requerimiento(s) guardado(s)`,
            showConfirmButton: false,
            timer: 2000
        });
    });

    // Inicializar tabla
    renderizarTabla();
});
</script>
@endsection
