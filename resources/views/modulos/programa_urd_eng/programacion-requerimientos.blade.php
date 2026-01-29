@extends('layouts.app')

@section('page-title', 'Programación de Requerimientos')

@section('navbar-right')
<div class="flex items-center gap-3">
    <!-- Botón único -->
    <button id="btnSiguiente" type="button" title="Siguiente"
        class="px-6 py-2.5 bg-blue-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 flex items-center gap-2 group">
        <i class="fa-solid fa-arrow-right w-4 h-4 group-hover:translate-x-1 transition-transform duration-200"></i>
    </button>
</div>
@endsection

@section('content')


<div class="w-full">

    {{-- =================== Tabla de requerimientos =================== --}}
    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaRequerimientos" class="w-full">
                <thead>
                    <tr class="bg-blue-500">
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Telar</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Fecha Req</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Cuenta</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Calibre</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Hilo</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Urdido</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-20">Tipo</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Destino</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-28">Tipo Atado</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Metros</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Kilos</th>
                        <th class="px-2 py-3 text-left text-md font-semibold text-white w-24">Agrupar</th>
                    </tr>
                </thead>
                <tbody id="tbodyRequerimientos" class="bg-white">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== Resumen por semana =================== --}}
    <div class="bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tablaResumen" class="w-full">
                <thead id="theadResumen">
                    <tr class="bg-slate-100">
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Telar</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Cuenta</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Hilo</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Calibre</th>
                        <th class="px-2 py-1.5 text-left text-[12px] font-semibold text-slate-700 bg-slate-100" rowspan="2">Modelo</th>
                        <th class="px-2 py-1.5 text-center text-[12px] font-semibold text-blue-700 bg-blue-50/50" colspan="5">Metros</th>
                        <th class="px-2 py-1.5 text-right text-[12px] font-semibold text-blue-700 bg-blue-50" rowspan="2">Total (mts)</th>
                        <th class="px-2 py-1.5 text-center text-[12px] font-semibold text-green-700 bg-green-50/50" colspan="5">Kilos</th>
                        <th class="px-2 py-1.5 text-right text-[12px] font-semibold text-green-700 bg-green-50" rowspan="2">Total (kg)</th>
                    </tr>
                    <tr>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="0">Semana 1</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="1">Semana 2</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="2">Semana 3</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="3">Semana 4</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-blue-600 bg-blue-50 semana-header" data-semana="4">Semana 5</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="0">Semana 1</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="1">Semana 2</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="2">Semana 3</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="3">Semana 4</th>
                        <th class="px-2 py-1 text-right text-[12px] font-semibold text-green-600 bg-green-50 semana-header" data-semana="4">Semana 5</th>
                    </tr>
                </thead>
                <tbody id="tbodyResumen" class="bg-white">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    /* =================== Estado & Constantes =================== */
    const RUTA_RESUMEN = '{{ route("programa.urd.eng.programacion.resumen.semanas") }}';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const opciones = {
        urdido: @json($opcionesUrdido ?? []),
        tipoAtado: ['Normal', 'Especial'],
        destino: ['JACQUARD', 'SMIT', 'SULZER', 'SMITH']
    };

    // Función para mapear salón a destino (ITEMA y SMITH ambos usan SMIT)
    function mapearSalonADestino(salon) {
        if (!salon) return 'JACQUARD';
        const s = String(salon).toUpperCase().trim();
        if (s === 'ITEMA' || s === 'SMITH') return 'SMIT';
        if (s === 'JACQUARD' || s === 'JAC') return 'JACQUARD';
        if (s === 'SMIT') return 'SMIT';
        if (s === 'SULZER') return 'SULZER';
        return 'JACQUARD'; // Default
    }

    let telaresData = normalizeInput(@json($telaresSeleccionados ?? []));

    if (telaresData.length === 0) {
        const urlParams = new URLSearchParams(location.search);
        const raw = urlParams.get('telares') || sessionStorage.getItem('selectedTelares');
        if (raw) {
            try { telaresData = normalizeInput(JSON.parse(decodeURIComponent(raw))); }
            catch { /* ignore */ }
                sessionStorage.removeItem('selectedTelares');
        }
    }

    /* =================== Helpers =================== */
    function todayISO() {
        const d = new Date(); const m = (d.getMonth()+1+'').padStart(2,'0'); const day = (d.getDate()+'').padStart(2,'0');
        return `${d.getFullYear()}-${m}-${day}`;
    }

    // Normalizar tipo a formato estándar: "Rizo" o "Pie" (primera letra mayúscula, resto minúsculas)
    function normalizarTipo(tipo) {
        if (!tipo || tipo === '') return '';
        const tipoUpper = String(tipo).toUpperCase().trim();
        if (tipoUpper === 'RIZO') return 'Rizo';
        if (tipoUpper === 'PIE') return 'Pie';
        return tipo; // Si no es RIZO ni PIE, retornar el original
    }

    function normalizeInput(arr) {
        return (arr || []).map(t => ({
            ...t,
            tipo: normalizarTipo(t.tipo), // Normalizar tipo a "Rizo" o "Pie"
            hilo: t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null
        }));
    }

    // Formatear número para input (con comas y puntos)
    function formatNumberInput(value) {
        if (!value || value === '') return '';
        const num = parseFloat(String(value).replace(/,/g, ''));
        if (isNaN(num)) return '';
        return num.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    // Parsear número desde input (remover comas y puntos)
    function parseNumberInput(value) {
        if (!value || value === '') return '';
        return String(value).replace(/,/g, '');
    }

    // Valida que todos compartan Tipo (obligatorio) y, si están presentes, mismo calibre/hilo/salón
    // NOTA: Para PIE, no se valida hilo
    function validarGrupo(telares) {
        if (!telares.length) return { valido:false, mensaje:'No hay telares seleccionados' };

        const base = telares[0] || {};
        const tipoBase = String(base.tipo || '').toUpperCase().trim();
        const calBase  = base.calibre != null && base.calibre !== '' ? parseFloat(base.calibre) : null;
        const hiloBase = base.hilo && String(base.hilo).trim() !== '' ? String(base.hilo).trim() : null;
        const salonBase= String(base.salon || '').trim();
        const esPie = tipoBase === 'PIE';

        if (!tipoBase) return { valido:false, mensaje:'El telar debe tener un tipo definido' };

        for (let i=1;i<telares.length;i++){
            const t = telares[i];
            const tipo = String(t.tipo || '').toUpperCase().trim();
            if (tipo !== tipoBase)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} tiene tipo "${t.tipo || 'N/A'}" pero se esperaba "${base.tipo || 'N/A'}".` };

            const cal = t.calibre != null && t.calibre !== '' ? parseFloat(t.calibre) : null;
            if (calBase!=null && cal!=null && Math.abs(calBase - cal) >= 0.01)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} calibre "${cal}" ≠ "${calBase}".` };

            // Para PIE, no se valida hilo
            if (!esPie) {
                const hilo = t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null;
                if (hiloBase && hilo && hilo !== hiloBase)
                    return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} hilo "${hilo}" ≠ "${hiloBase}".` };
            }

            const salon = String(t.salon || '').trim();
            if (salonBase && salon && salon !== salonBase)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} salón "${salon}" ≠ "${salonBase}".` };
        }

        // Para PIE, establecer hilo como null para que no se use en consultas
        // Normalizar el tipo antes de retornarlo
        const tipoNormalizado = normalizarTipo(base.tipo);
        return { valido:true, tipo:tipoNormalizado, calibre:calBase, hilo:esPie ? null : hiloBase, salon:salonBase };
    }

    /* =================== Render principal =================== */
    function crearFila(telar, index) {
        const fechaISO = telar.fecha_req || todayISO();
        // Normalizar tipo para mostrar
        const tipoNormalizado = normalizarTipo(telar.tipo);
        const tipoCls  = (String(tipoNormalizado||'').toUpperCase()==='RIZO')
            ? 'bg-rose-100 text-rose-700' : (String(tipoNormalizado||'').toUpperCase()==='PIE'
            ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');

        const tr = document.createElement('tr');
        tr.className = ' hover:bg-gray-50';
        tr.dataset.index = index;
        tr.dataset.telarId = telar.no_telar || '';

        tr.innerHTML = `
            <td class="px-2 py-3 w-20">
                <input type="text" class="w-full px-2 py-1.5 text-md bg-transparent border-0" value="${telar.no_telar || ''}" data-field="telar" disabled>
            </td>
            <td class="px-2 py-3 w-28">
                <input type="date" class="w-full px-2 py-1.5 text-md bg-transparent border-0" value="${fechaISO}" data-field="fecha_req" disabled>
            </td>
            <td class="px-2 py-3 w-20">
                <input type="text" class="w-full px-2 py-1.5 text-md bg-transparent border-0" value="${telar.cuenta || ''}" data-field="cuenta" disabled>
            </td>
            <td class="px-2 py-3 w-20">
                <input type="number" step="0.01" class="w-full px-2 py-1.5 text-md bg-transparent border-0" value="${telar.calibre ?? ''}" data-field="calibre" disabled>
            </td>
            <td class="px-2 py-3 w-24">
                <input type="text" class="w-full px-2 py-1.5 text-md bg-transparent border-0" value="${telar.hilo ?? ''}" data-field="hilo" disabled>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="urdido">
                    ${opciones.urdido.map((x, idx) => {
                        const isSelected = telar.urdido === x || (!telar.urdido && idx === 0);
                        return `<option value="${x}" ${isSelected ? 'selected' : ''}>${x}</option>`;
                    }).join('')}
                </select>
            </td>
            <td class="px-2 py-3 w-20">
                <span class="px-2 py-1 inline-block text-md font-medium rounded-md ${tipoCls}">${tipoNormalizado || 'N/A'}</span>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 text-md bg-transparent border-0 cursor-default appearance-none" data-field="destino" disabled>
                    ${(() => {
                        const destinoMapeado = telar.destino ? mapearSalonADestino(telar.destino) : mapearSalonADestino(telar.salon);
                        return opciones.destino.map(x => {
                            return `<option value="${x}" ${destinoMapeado === x ? 'selected' : ''}>${x}</option>`;
                        }).join('');
                    })()}
                </select>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="tipo_atado">
                    ${opciones.tipoAtado.map(x => `<option value="${x}" ${(telar.tipo_atado||'Normal')===x?'selected':''}>${x}</option>`).join('')}
                </select>
            </td>
            <td class="px-2 py-3 w-24">
                <input type="text" placeholder="Metros"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-right"
                       value="${telar.metros ? formatNumberInput(telar.metros) : ''}" data-field="metros">
            </td>
            <td class="px-2 py-3 w-24">
                <input type="text" placeholder="Kilos"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-md text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                       value="${telar.kilos ? formatNumberInput(telar.kilos) : ''}" data-field="kilos">
            </td>
            <td class="px-2 py-3 w-24 text-center">
                <input type="checkbox" class="w-4 h-4" ${telar.agrupar ? 'checked' : ''} data-field="agrupar">
            </td>
        `;
        return tr;
    }

    function renderTabla() {
        const tbody = document.getElementById('tbodyRequerimientos');
        tbody.innerHTML = '';

        if (!telaresData.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                        <p>No hay telares seleccionados.</p>
                    </td>
                </tr>`;
            renderResumenMensaje('No hay telares seleccionados para mostrar el resumen.');
            return;
        }

        const v = validarGrupo(telaresData);
        if (!v.valido) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-red-500">
                        <i class="fa-solid fa-triangle-exclamation text-red-400 mb-2"></i>
                        <p class="font-semibold">Error de validación</p>
                        <p class="text-sm mt-2">${v.mensaje}</p>
                        <p class="text-md mt-2 text-gray-500">Todos los telares deben tener el mismo tipo, y si existen, mismo calibre/hilo/salón.</p>
                    </td>
                </tr>`;
            renderResumenMensaje('No se puede cargar el resumen por error de validación.');
            return;
        }

        // Filtrado tolerante
        const tipoValidado = String(v.tipo||'').toUpperCase().trim();
        const esPie = tipoValidado === 'PIE';
        const filtrados = telaresData.filter(t => {
            const tipo = String(t.tipo||'').toUpperCase().trim();
            if (tipo !== tipoValidado) return false;

            const cal = t.calibre!=null && t.calibre!=='' ? parseFloat(t.calibre) : null;
            if (v.calibre!=null && cal!=null && Math.abs(v.calibre - cal) >= 0.01) return false;

            // Para PIE, no se filtra por hilo
            if (!esPie) {
                const hilo = t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null;
                if (v.hilo && hilo && v.hilo !== hilo) return false;
            }

            const salon = String(t.salon || '').trim();
            if (v.salon && salon && v.salon !== salon) return false;

            return true;
        });

        filtrados.forEach((t, i) => tbody.appendChild(crearFila(t, i)));

        // Aplicar agrupación automática
        aplicarAgrupacionAutomatica(filtrados);

        // Agregar event listeners a los checkboxes de agrupación
        agregarValidacionAgrupacion();

        // Mantener dataset filtrado para el resumen
        telaresData = filtrados;
        cargarResumenDesdeServidor(v);
    }

    // Función para aplicar agrupación automática basada en Cuenta, Hilo, Calibre y Tipo
    function aplicarAgrupacionAutomatica(telares) {
        if (!telares || telares.length === 0) return;

        // Agrupar telares por los criterios de agrupación
        const grupos = {};
        const tipoBase = String(telares[0]?.tipo || '').toUpperCase().trim();
        const esPie = tipoBase === 'PIE';

        telares.forEach((telar, index) => {
            // Normalizar valores para la clave de agrupación
            const cuenta = String(telar.cuenta || '').trim();
            const calibre = telar.calibre != null && telar.calibre !== '' ? parseFloat(telar.calibre).toFixed(2) : '';
            const hilo = esPie ? '' : (telar.hilo && String(telar.hilo).trim() !== '' ? String(telar.hilo).trim() : '');
            const tipo = String(telar.tipo || '').toUpperCase().trim();

            // Crear clave de agrupación (para PIE no incluye hilo)
            const clave = esPie
                ? `${cuenta}|${calibre}|${tipo}`
                : `${cuenta}|${hilo}|${calibre}|${tipo}`;

            if (!grupos[clave]) {
                grupos[clave] = [];
            }
            grupos[clave].push({ telar, index });
        });

        // Marcar checkboxes de agrupación para grupos con más de un telar
        Object.values(grupos).forEach(grupo => {
            if (grupo.length > 1) {
                // Si hay más de un telar en el grupo, marcar todos los checkboxes
                grupo.forEach(({ index }) => {
                    const fila = document.querySelector(`#tablaRequerimientos tbody tr[data-index="${index}"]`);
                    if (fila) {
                        const checkbox = fila.querySelector('input[data-field="agrupar"]');
                        if (checkbox) {
                            checkbox.checked = true;
                            // Actualizar también el dato en telaresData usando el telarId
                            const telarId = fila.dataset.telarId || '';
                            const telarEnData = telaresData.find(t => String(t.no_telar || '') === telarId);
                            if (telarEnData) {
                                telarEnData.agrupar = true;
                            }
                        }
                    }
                });
            }
        });
    }

    // Función para validar si dos telares pueden agruparse
    function puedenAgruparse(telar1, telar2) {
        const tipo1 = String(telar1.tipo || '').toUpperCase().trim();
        const tipo2 = String(telar2.tipo || '').toUpperCase().trim();
        const esPie = tipo1 === 'PIE';

        // Deben tener el mismo tipo
        if (tipo1 !== tipo2) {
            return { puede: false, motivo: 'tipo' };
        }

        // Deben tener la misma cuenta
        const cuenta1 = String(telar1.cuenta || '').trim();
        const cuenta2 = String(telar2.cuenta || '').trim();
        if (cuenta1 !== cuenta2) {
            return { puede: false, motivo: 'cuenta' };
        }

        // Deben tener el mismo calibre
        const cal1 = telar1.calibre != null && telar1.calibre !== '' ? parseFloat(telar1.calibre) : null;
        const cal2 = telar2.calibre != null && telar2.calibre !== '' ? parseFloat(telar2.calibre) : null;
        if (cal1 != null && cal2 != null && Math.abs(cal1 - cal2) >= 0.01) {
            return { puede: false, motivo: 'calibre' };
        }
        if ((cal1 == null) !== (cal2 == null)) {
            return { puede: false, motivo: 'calibre' };
        }

        // Para RIZO, deben tener el mismo hilo
        if (!esPie) {
            const hilo1 = telar1.hilo && String(telar1.hilo).trim() !== '' ? String(telar1.hilo).trim() : null;
            const hilo2 = telar2.hilo && String(telar2.hilo).trim() !== '' ? String(telar2.hilo).trim() : null;
            if (hilo1 && hilo2 && hilo1 !== hilo2) {
                return { puede: false, motivo: 'hilo' };
            }
        }

        return { puede: true };
    }

    // Función para obtener mensaje de error de agrupación
    function obtenerMensajeErrorAgrupacion(motivo) {
        const mensajes = {
            'tipo': 'No se pueden agrupar telares con diferentes tipos (RIZO/PIE).',
            'cuenta': 'No se pueden agrupar telares con diferentes cuentas.',
            'hilo': 'No se pueden agrupar telares con diferentes hilos.',
            'calibre': 'No se pueden agrupar telares con diferentes calibres.'
        };
        return mensajes[motivo] || 'No se pueden agrupar estos telares.';
    }

    // Función para mostrar alerta de agrupación con SweetAlert2 (toast)
    function mostrarAlertaAgrupacion(mensaje) {
        // Verificar si SweetAlert2 está disponible
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                width: 'auto',
                padding: '0.75rem 1rem',
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({
                icon: 'error',
                title: mensaje,
                iconColor: '#ef4444'
            });
        } else {
            // Fallback a alert nativo si SweetAlert2 no está disponible
            alert(mensaje);
        }
    }

    // Función para agregar validación a los checkboxes de agrupación
    function agregarValidacionAgrupacion() {
        const checkboxes = document.querySelectorAll('#tablaRequerimientos input[data-field="agrupar"]');

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function(e) {
                // Si se está desmarcando, permitir sin validación
                if (!this.checked) {
                    const fila = this.closest('tr');
                    const telarId = fila.dataset.telarId || '';
                    const telarEnData = telaresData.find(t => String(t.no_telar || '') === telarId);
                    if (telarEnData) {
                        telarEnData.agrupar = false;
                    }
                    return;
                }

                // Si se está marcando, validar que pueda agruparse con los demás
                const fila = this.closest('tr');
                const telarId = fila.dataset.telarId || '';
                const telarActual = telaresData.find(t => String(t.no_telar || '') === telarId);

                if (!telarActual) {
                    this.checked = false;
                    return;
                }

                // Obtener todos los telares que ya tienen el checkbox marcado (excepto el actual)
                // Buscar todas las filas y verificar cuáles tienen el checkbox marcado
                const todasLasFilas = document.querySelectorAll('#tablaRequerimientos tbody tr');
                const telaresAgrupados = [];

                todasLasFilas.forEach(fila => {
                    const inputTelarFila = fila.querySelector('input[data-field="telar"]');
                    const checkboxFila = fila.querySelector('input[data-field="agrupar"]');

                    if (!inputTelarFila || !checkboxFila) return;

                    const telarIdFila = inputTelarFila.value;
                    if (telarIdFila === telarId) return; // Saltar el telar actual

                    if (checkboxFila.checked) {
                        // Encontrar el telar en telaresData
                        const telarEncontrado = telaresData.find(t => String(t.no_telar || '') === telarIdFila);
                        if (telarEncontrado) {
                            telaresAgrupados.push(telarEncontrado);
                        }
                    }
                });

                // Si hay telares ya agrupados, validar que el actual pueda agruparse con ellos
                if (telaresAgrupados.length > 0) {
                    const telarReferencia = telaresAgrupados[0];
                    const validacion = puedenAgruparse(telarActual, telarReferencia);

                    if (!validacion.puede) {
                        // Mostrar alerta y desmarcar el checkbox
                        this.checked = false;
                        mostrarAlertaAgrupacion(obtenerMensajeErrorAgrupacion(validacion.motivo));
                        return;
                    }

                    // Validar también con todos los demás telares agrupados (por seguridad)
                    for (const otroTelar of telaresAgrupados) {
                        const validacionOtro = puedenAgruparse(telarActual, otroTelar);
                        if (!validacionOtro.puede) {
                            this.checked = false;
                            mostrarAlertaAgrupacion(obtenerMensajeErrorAgrupacion(validacionOtro.motivo));
                            return;
                        }
                    }
                }

                // Si pasa todas las validaciones, permitir la agrupación
                telarActual.agrupar = true;
            });
        });
    }

    /* =================== Resumen =================== */
    function renderResumenMensaje(msg) {
        const tb = document.getElementById('tbodyResumen');
        tb.innerHTML = `
            <tr>
                <td colspan="16" class="px-2 py-4 text-center text-gray-500 text-[10px]">
                    <i class="fa-solid fa-circle-info text-gray-400 mb-1"></i>
                    <p>${msg}</p>
                </td>
            </tr>`;
    }

    async function cargarResumenDesdeServidor(validacion) {
        const tbody = document.getElementById('tbodyResumen');
        tbody.innerHTML = `
            <tr>
                <td colspan="16" class="px-2 py-4 text-center text-gray-500 text-[10px]">
                    <div class="flex items-center justify-center gap-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-300 border-t-blue-500"></div>
                        <span>Cargando datos...</span>
                    </div>
                </td>
            </tr>`;

        try {
            const payload = normalizeInput(telaresData);


            const res = await fetch(RUTA_RESUMEN, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
                body: JSON.stringify({ telares: payload })
            });

            const json = await res.json();


            if (!res.ok) {
                throw new Error(json.message || `Error HTTP ${res.status}`);
            }

            if (json.success !== true) {
                throw new Error(json.message || 'La respuesta del servidor indica error');
            }

            if (!json.data) {
                throw new Error('El servidor no devolvió datos');
            }

            renderResumen(json.data, validacion, json.semanas || []);
        } catch (e) {
            console.error('Error al cargar resumen:', e);
            renderResumenMensaje('Error al cargar datos: ' + (e.message || 'Error desconocido'));
        }
    }

    function renderResumen(data, validacion, semanas) {
        const tb = document.getElementById('tbodyResumen');
        tb.innerHTML = '';

        // Actualizar encabezados con fechas
        if (Array.isArray(semanas) && semanas.length > 0) {
            semanas.forEach((sem, idx) => {
                const headers = document.querySelectorAll(`.semana-header[data-semana="${idx}"]`);
                if (headers.length === 0) return;

                let fechaIni = '';
                let fechaFin = '';

                if (sem.inicio) {
                    try {
                        const fecha = new Date(sem.inicio + 'T00:00:00');
                        if (!isNaN(fecha.getTime())) {
                            fechaIni = fecha.toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit'});
                        }
                    } catch(e) {
                        fechaIni = sem.inicio ? sem.inicio.substring(5, 10).replace('-', '/') : '';
                    }
                }

                if (sem.fin) {
                    try {
                        const fecha = new Date(sem.fin + 'T00:00:00');
                        if (!isNaN(fecha.getTime())) {
                            fechaFin = fecha.toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit'});
                        }
                    } catch(e) {
                        fechaFin = sem.fin ? sem.fin.substring(5, 10).replace('-', '/') : '';
                    }
                }

                headers.forEach(header => {
                    // Solo actualizar si no tiene fechas ya
                    if (header.querySelector('span.text-gray-500') === null && fechaIni && fechaFin) {
                        const originalText = header.textContent.trim();
                        header.innerHTML = `${originalText}<br><span class="text-gray-500 font-normal text-[9px]">${fechaIni} - ${fechaFin}</span>`;
                    }
                });
            });
        }

        const tipo = String(validacion.tipo || '').toUpperCase().trim();
        const calEsperado = validacion.calibre;
        const hiloEsperado = validacion.hilo; // null => no filtra por hilo

        // Log para debugging


        const items = [];

        if (tipo === 'RIZO' && Array.isArray(data.rizo)) {
            for (const it of data.rizo) {
                const hiloIt = String(it.Hilo || it.hilo || '').trim();
                // Validar que el hilo del resumen coincida con el hilo esperado (de la tabla de arriba)
                const matchHilo = (hiloEsperado == null) || (hiloIt.toUpperCase() === String(hiloEsperado).toUpperCase());
                if (!matchHilo) continue;

                items.push({
                    telar: it.TelarId || it.telarId || it.Telar || it.telar || '',
                    cuenta: it.CuentaRizo || it.cuentaRizo || it.Cuenta || it.cuenta || '',
                    hilo: hiloIt || '-',
                    calibre: (calEsperado != null && calEsperado !== '') ? calEsperado : (it.Calibre || it.calibre || '-'),
                    modelo: it.Modelo || it.modelo || '-',
                    s0: it.SemActualMtsRizo || it.semActualMtsRizo || it.SemActual || 0,
                    s1: it.SemActual1MtsRizo || it.semActual1MtsRizo || it.SemActual1 || 0,
                    s2: it.SemActual2MtsRizo || it.semActual2MtsRizo || it.SemActual2 || 0,
                    s3: it.SemActual3MtsRizo || it.semActual3MtsRizo || it.SemActual3 || 0,
                    s4: it.SemActual4MtsRizo || it.semActual4MtsRizo || it.SemActual4 || 0,
                    k0: it.SemActualKilosRizo || it.semActualKilosRizo || 0,
                    k1: it.SemActual1KilosRizo || it.semActual1KilosRizo || 0,
                    k2: it.SemActual2KilosRizo || it.semActual2KilosRizo || 0,
                    k3: it.SemActual3KilosRizo || it.semActual3KilosRizo || 0,
                    k4: it.SemActual4KilosRizo || it.semActual4KilosRizo || 0,
                    total: it.Total || 0,
                    totalKilos: it.TotalKilos || 0
                });
            }
        }

        if (tipo === 'PIE' && Array.isArray(data.pie)) {
            for (const it of data.pie) {
                const calIt = it.CalibrePie ?? it.calibrePie ?? it.Calibre ?? it.calibre;
                const calNum = calIt!=null && calIt!=='' ? parseFloat(calIt) : null;

                // Validar que el calibre del resumen coincida con el calibre esperado (tolerancia 0.11 para cubrir errores de precisión)
                const okCal = (calEsperado==null) || (calNum==null) || Math.abs(calEsperado - calNum) <= 0.11;
                if (!okCal) continue;

                // Para PIE, NO se filtra por hilo - se muestran todos los registros que coincidan en calibre
                const hiloIt = String(it.Hilo || it.hilo || '').trim();

                items.push({
                    telar: it.TelarId || it.telarId || it.Telar || it.telar || '',
                    cuenta: it.CuentaPie || it.cuentaPie || it.Cuenta || it.cuenta || '',
                    hilo: hiloIt || '-',
                    calibre: calIt ?? '-',
                    modelo: it.Modelo || it.modelo || '-',
                    s0: it.SemActualMtsPie || it.semActualMtsPie || it.SemActual || 0,
                    s1: it.SemActual1MtsPie || it.semActual1MtsPie || it.SemActual1 || 0,
                    s2: it.SemActual2MtsPie || it.semActual2MtsPie || it.SemActual2 || 0,
                    s3: it.SemActual3MtsPie || it.semActual3MtsPie || it.SemActual3 || 0,
                    s4: it.SemActual4MtsPie || it.semActual4MtsPie || it.SemActual4 || 0,
                    k0: it.SemActualKilosPie || it.semActualKilosPie || 0,
                    k1: it.SemActual1KilosPie || it.semActual1KilosPie || 0,
                    k2: it.SemActual2KilosPie || it.semActual2KilosPie || 0,
                    k3: it.SemActual3KilosPie || it.semActual3KilosPie || 0,
                    k4: it.SemActual4KilosPie || it.semActual4KilosPie || 0,
                    total: it.Total || 0,
                    totalKilos: it.TotalKilos || 0
                });
            }
        }

        if (!items.length) {
            // Verificar si hay datos en la respuesta pero no coinciden con los filtros
            const tieneDatosRizo = tipo === 'RIZO' && Array.isArray(data.rizo) && data.rizo.length > 0;
            const tieneDatosPie = tipo === 'PIE' && Array.isArray(data.pie) && data.pie.length > 0;

            let mensaje = `No hay datos de programación en el rango de 5 semanas.`;
            mensaje += `\nTipo: ${tipo || 'N/A'}`;
            mensaje += `\nCalibre: ${calEsperado ?? 'N/A'}`;
            mensaje += `\nHilo: ${tipo === 'PIE' ? 'No aplica' : (hiloEsperado ?? 'Todos')}`;

            if (tieneDatosRizo || tieneDatosPie) {
                const filtrosAplicados = tipo === 'PIE' ? 'calibre' : 'hilo/calibre';
                mensaje += `\n\nNota: Existen ${tieneDatosRizo ? data.rizo.length : data.pie.length} registro(s) en la base de datos, pero no coinciden con los filtros aplicados (${filtrosAplicados}) o no tienen fechas en el rango de las 5 semanas.`;
            } else {
                mensaje += `\n\nNota: No se encontraron registros de programación para los telares seleccionados en el rango de fechas de las 5 semanas (${validacion?.semanas?.[0]?.inicio || 'N/A'} a ${validacion?.semanas?.[4]?.fin || 'N/A'}).`;
            }

            renderResumenMensaje(mensaje);
            return;
        }

        // Agrupar items por telar para calcular sumas por telar
        const itemsPorTelar = {};
        for (const r of items) {
            const telarId = String(r.telar || '').trim();
            if (!itemsPorTelar[telarId]) {
                itemsPorTelar[telarId] = {
                    telar: telarId,
                    totalMetros: 0,
                    totalKilos: 0,
                    items: []
                };
            }
            itemsPorTelar[telarId].totalMetros += Number(r.total || 0);
            itemsPorTelar[telarId].totalKilos += Number(r.totalKilos || 0);
            itemsPorTelar[telarId].items.push(r);
        }

        // Calcular totales por columna de semana (metros y kilos)
        const totalesMetrosPorSemana = [0, 0, 0, 0, 0];
        const totalesKilosPorSemana = [0, 0, 0, 0, 0];
        let sumaTotalMetros = 0;
        let sumaTotalKilos = 0;

        // Primero calcular los totales por columna
        for (const r of items) {
            const semanasData = [
                { idx: 0, metros: Number(r.s0 || 0), kilos: Number(r.k0 || 0) },
                { idx: 1, metros: Number(r.s1 || 0), kilos: Number(r.k1 || 0) },
                { idx: 2, metros: Number(r.s2 || 0), kilos: Number(r.k2 || 0) },
                { idx: 3, metros: Number(r.s3 || 0), kilos: Number(r.k3 || 0) },
                { idx: 4, metros: Number(r.s4 || 0), kilos: Number(r.k4 || 0) }
            ];

            semanasData.forEach((semData, idx) => {
                totalesMetrosPorSemana[idx] += semData.metros;
                totalesKilosPorSemana[idx] += semData.kilos;
                sumaTotalMetros += semData.metros;
                sumaTotalKilos += semData.kilos;
            });
        }

        // Renderizar una fila por cada item con todas sus semanas
        for (const r of items) {
            const semanasData = [
                { idx: 0, metros: Number(r.s0 || 0), kilos: Number(r.k0 || 0) },
                { idx: 1, metros: Number(r.s1 || 0), kilos: Number(r.k1 || 0) },
                { idx: 2, metros: Number(r.s2 || 0), kilos: Number(r.k2 || 0) },
                { idx: 3, metros: Number(r.s3 || 0), kilos: Number(r.k3 || 0) },
                { idx: 4, metros: Number(r.s4 || 0), kilos: Number(r.k4 || 0) }
            ];

            // Solo crear fila si tiene al menos un valor en alguna semana
            const tieneDatos = semanasData.some(s => s.metros > 0 || s.kilos > 0);
            if (!tieneDatos) continue;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';

            // Crear las columnas de metros y kilos para todas las semanas
            const metrosCells = semanasData.map(s => {
                return `<td class="px-2 py-1.5 whitespace-nowrap text-md text-right">${fmtNum(s.metros)}</td>`;
            }).join('');

            const kilosCells = semanasData.map(s => {
                return `<td class="px-2 py-1.5 whitespace-nowrap text-md text-right">${fmtNum(s.kilos)}</td>`;
            }).join('');

            tr.innerHTML = `
                <td class="px-2 py-1.5 whitespace-nowrap text-[10px] text-gray-700">${r.telar}</td>
                <td class="px-2 py-1.5 whitespace-nowrap text-[10px] text-gray-700">${r.cuenta || '-'}</td>
                <td class="px-2 py-1.5 whitespace-nowrap text-[10px] text-gray-700">${r.hilo || '-'}</td>
                <td class="px-2 py-1.5 whitespace-nowrap text-[10px] text-gray-700">${r.calibre != null && r.calibre !== '' ? r.calibre : '-'}</td>
                <td class="px-2 py-1.5 whitespace-nowrap text-[10px] text-gray-700">${r.modelo || '-'}</td>
                ${metrosCells}
                <td class="px-2 py-1.5 whitespace-nowrap text-md font-semibold text-right text-blue-600 bg-blue-50">${fmtNum(r.total || 0)}</td>
                ${kilosCells}
                <td class="px-2 py-1.5 whitespace-nowrap text-md font-semibold text-right text-green-600 bg-green-50">${fmtNum(r.totalKilos || 0)}</td>
            `;
            tb.appendChild(tr);
        }

        // Agregar fila de totales con totales por cada columna de semana
        const trTotal = document.createElement('tr');
        trTotal.className = 'bg-gray-100 font-bold';
        trTotal.id = 'filaTotal';

        // Crear celdas de totales por semana para metros
        const totalesMetrosCells = totalesMetrosPorSemana.map(total => {
            return `<td class="px-2 py-2 whitespace-nowrap text-md font-semibold text-right text-blue-700 bg-blue-100">${fmtNum(total)}</td>`;
        }).join('');

        // Crear celdas de totales por semana para kilos
        const totalesKilosCells = totalesKilosPorSemana.map(total => {
            return `<td class="px-2 py-2 whitespace-nowrap text-md font-semibold text-right text-green-700 bg-green-100">${fmtNum(total)}</td>`;
        }).join('');

        trTotal.innerHTML = `
            <td class="px-2 py-2 whitespace-nowrap text-[10px] font-bold text-gray-800 bg-gray-100" colspan="5">TOTAL</td>
            ${totalesMetrosCells}
            <td class="px-2 py-2 whitespace-nowrap text-md font-semibold text-right text-blue-700 bg-blue-100">
                <span class="block">${fmtNum(sumaTotalMetros)}</span>
            </td>
            ${totalesKilosCells}
            <td class="px-2 py-2 whitespace-nowrap text-md font-semibold text-right text-green-700 bg-green-100">
                <span class="block">${fmtNum(sumaTotalKilos)}</span>
            </td>
        `;
        tb.appendChild(trTotal);

        // Función para calcular kilos programados por telar
        const calcularKilosProgramadosPorTelar = (telarId, metrosProg) => {
            const datosTelar = itemsPorTelar[telarId];
            if (!datosTelar || datosTelar.totalMetros <= 0 || datosTelar.totalKilos <= 0) return 0;
            const metros = Number(metrosProg) || 0;
            return (datosTelar.totalKilos / datosTelar.totalMetros) * metros;
        };

        // Actualizar valores en la tabla 1 por telar
        const filasTabla1 = document.querySelectorAll('#tablaRequerimientos tbody tr');
        filasTabla1.forEach(fila => {
            const telarId = fila.dataset.telarId || '';
            const datosTelar = itemsPorTelar[telarId];

            if (datosTelar && datosTelar.totalMetros > 0) {
                // Obtener inputs de esta fila
                const metrosInput = fila.querySelector('input[data-field="metros"]');
                const kilosInput = fila.querySelector('input[data-field="kilos"]');

                if (metrosInput && kilosInput) {
                    // Inicializar metros con la suma del telar
                    metrosInput.value = formatNumberInput(datosTelar.totalMetros);

                    // Calcular kilos iniciales para este telar
                    const kilosInicial = calcularKilosProgramadosPorTelar(telarId, datosTelar.totalMetros);
                    kilosInput.value = formatNumberInput(kilosInicial);

                    // Event listeners para formatear y calcular
                    // Limpiar formato al enfocar para edición
                    metrosInput.addEventListener('focus', function() {
                        this.value = parseNumberInput(this.value);
                    });

                    // Permitir solo números, comas y puntos
                    metrosInput.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^\d.,]/g, '');

                        // Calcular kilos en tiempo real
                        const metrosRaw = parseNumberInput(this.value);
                        const metros = Number(metrosRaw) || 0;
                        const kilos = calcularKilosProgramadosPorTelar(telarId, metros);

                        // Actualizar solo el input de kilos de esta fila
                        if (kilosInput) {
                            kilosInput.value = kilos > 0 ? formatNumberInput(kilos) : '';
                        }
                    });

                    // Formatear al perder el foco
                    metrosInput.addEventListener('blur', function() {
                        const value = parseNumberInput(this.value);
                        if (value) {
                            this.value = formatNumberInput(value);
                            // Recalcular kilos después de formatear
                            const metros = Number(value) || 0;
                            const kilos = calcularKilosProgramadosPorTelar(telarId, metros);
                            if (kilosInput) {
                                kilosInput.value = kilos > 0 ? formatNumberInput(kilos) : '';
                            }
                        }
                    });
                }
            }
        });
    }

    function fmtNum(n) {
        const v = Number(n||0);
        return v>0 ? v.toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
    }

    /* =================== Evento único =================== */
    document.getElementById('btnSiguiente')?.addEventListener('click', () => {
        // Recopilar datos de la tabla
        const filas = document.querySelectorAll('#tablaRequerimientos tbody tr');
        const datosTelares = [];

        filas.forEach(fila => {
            const telarId = fila.querySelector('input[data-field="telar"]')?.value || '';
            const fechaReq = fila.querySelector('input[data-field="fecha_req"]')?.value || '';
            const cuenta = fila.querySelector('input[data-field="cuenta"]')?.value || '';
            const calibre = fila.querySelector('input[data-field="calibre"]')?.value || '';
            const hilo = fila.querySelector('input[data-field="hilo"]')?.value || '';
            const urdido = fila.querySelector('select[data-field="urdido"]')?.value || '';
            // Obtener tipo desde el span dentro de la celda de tipo
            const tipoCell = fila.querySelector('td:nth-child(7)');
            const tipoRaw = tipoCell ? tipoCell.querySelector('span')?.textContent.trim() || '' : '';
            // Normalizar el tipo a formato estándar
            const tipo = normalizarTipo(tipoRaw);
            const destino = fila.querySelector('select[data-field="destino"]')?.value || '';
            const tipoAtado = fila.querySelector('select[data-field="tipo_atado"]')?.value || '';
            const metros = parseNumberInput(fila.querySelector('input[data-field="metros"]')?.value || '0') || '0';
            const kilos = parseNumberInput(fila.querySelector('input[data-field="kilos"]')?.value || '0') || '0';
            const agrupar = fila.querySelector('input[data-field="agrupar"]')?.checked || false;

            if (telarId) {
                datosTelares.push({
                    no_telar: telarId,
                    fecha_req: fechaReq,
                    cuenta: cuenta,
                    calibre: calibre,
                    hilo: hilo,
                    urdido: urdido,
                    tipo: tipo,
                    destino: destino,
                    tipo_atado: tipoAtado,
                    metros: metros,
                    kilos: kilos,
                    agrupar: agrupar
                });
            }
        });

        if (datosTelares.length === 0) {
            alert('No hay telares para procesar');
            return;
        }

        // Redirigir a la vista de creación de órdenes con los datos
        const datosEncoded = encodeURIComponent(JSON.stringify(datosTelares));
        window.location.href = `{{ route('programa.urd.eng.creacion.ordenes') }}?telares=${datosEncoded}`;
    });

    /* =================== Init =================== */
    renderTabla();
});
</script>
@endsection
