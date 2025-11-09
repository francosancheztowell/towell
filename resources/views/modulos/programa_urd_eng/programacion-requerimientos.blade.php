@extends('layouts.app')

@section('page-title', 'Programación de Requerimientos')

@section('navbar-right')
<div class="flex items-center gap-3">
    <!-- Botón único -->
    <button id="btnSiguiente" type="button"
        class="px-6 py-2.5 bg-blue-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 flex items-center gap-2 group">
        <i class="fa-solid fa-arrow-right w-4 h-4 group-hover:translate-x-1 transition-transform duration-200"></i>
    </button>
</div>
@endsection

@section('content')
<div class="w-full">

    {{-- =================== Tabla de requerimientos =================== --}}
    <div class="bg-white overflow-hidden mb-4 border">
        <div class="overflow-x-auto">
            <table id="tablaRequerimientos" class="w-full">
                <thead>
                    <tr class="bg-gray-100 border-b">
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-20">Telar</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-28">Fecha Req</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-20">Cuenta</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-20">Calibre</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-24">Hilo</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-28">Urdido</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-20">Tipo</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-28">Destino</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-28">Tipo Atado</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-24">Metros</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-24">Kilos</th>
                        <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 w-24">Agrupar</th>
                    </tr>
                </thead>
                <tbody id="tbodyRequerimientos" class="bg-white divide-y">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== Resumen por semana =================== --}}
    <div class="bg-white overflow-hidden rounded-lg border">
        <div class="overflow-x-auto">
            <table id="tablaResumen" class="w-full">
                <thead>
                    <tr class="bg-gray-100 border-b">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800">Telar</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800">Cuenta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800">Hilo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800">Calibre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-800">Modelo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Sem Actual</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Sem Actual +1</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Sem Actual +2</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Sem Actual +3</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Sem Actual +4</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-800">Total</th>
                    </tr>
                </thead>
                <tbody id="tbodyResumen" class="bg-white divide-y">
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
        urdido: ['Mc Coy 1', 'Mc Coy 2', 'Mc Coy 3'],
        tipoAtado: ['Normal', 'Especial'],
        destino: ['JACQUARD', 'SMIT', 'SULZER', 'SMITH']
    };

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
    function normalizeInput(arr) {
        return (arr || []).map(t => ({
            ...t,
            hilo: t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null
        }));
    }

    // Valida que todos compartan Tipo (obligatorio) y, si están presentes, mismo calibre/hilo/salón
    function validarGrupo(telares) {
        if (!telares.length) return { valido:false, mensaje:'No hay telares seleccionados' };

        const base = telares[0] || {};
        const tipoBase = String(base.tipo || '').toUpperCase().trim();
        const calBase  = base.calibre != null && base.calibre !== '' ? parseFloat(base.calibre) : null;
        const hiloBase = base.hilo && String(base.hilo).trim() !== '' ? String(base.hilo).trim() : null;
        const salonBase= String(base.salon || '').trim();

        if (!tipoBase) return { valido:false, mensaje:'El telar debe tener un tipo definido' };

        for (let i=1;i<telares.length;i++){
            const t = telares[i];
            const tipo = String(t.tipo || '').toUpperCase().trim();
            if (tipo !== tipoBase)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} tiene tipo "${t.tipo || 'N/A'}" pero se esperaba "${base.tipo || 'N/A'}".` };

            const cal = t.calibre != null && t.calibre !== '' ? parseFloat(t.calibre) : null;
            if (calBase!=null && cal!=null && Math.abs(calBase - cal) >= 0.01)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} calibre "${cal}" ≠ "${calBase}".` };

            const hilo = t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null;
            if (hiloBase && hilo && hilo !== hiloBase)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} hilo "${hilo}" ≠ "${hiloBase}".` };

            const salon = String(t.salon || '').trim();
            if (salonBase && salon && salon !== salonBase)
                return { valido:false, mensaje:`El telar ${t.no_telar || 'N/A'} salón "${salon}" ≠ "${salonBase}".` };
        }

        return { valido:true, tipo:base.tipo, calibre:calBase, hilo:hiloBase, salon:salonBase };
    }

    /* =================== Render principal =================== */
    function crearFila(telar, index) {
        const fechaISO = telar.fecha_req || todayISO();
        const tipoCls  = (String(telar.tipo||'').toUpperCase()==='RIZO')
            ? 'bg-rose-100 text-rose-700' : (String(telar.tipo||'').toUpperCase()==='PIE'
            ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');

        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        tr.dataset.index = index;
        tr.dataset.telarId = telar.no_telar || '';

        tr.innerHTML = `
            <td class="px-2 py-3 w-20">
                <input type="text" class="w-full px-2 py-1.5 text-xs bg-transparent border-0" value="${telar.no_telar || ''}" data-field="telar" disabled>
            </td>
            <td class="px-2 py-3 w-28">
                <input type="date" class="w-full px-2 py-1.5 text-xs bg-transparent border-0" value="${fechaISO}" data-field="fecha_req" disabled>
            </td>
            <td class="px-2 py-3 w-20">
                <input type="text" class="w-full px-2 py-1.5 text-xs bg-transparent border-0" value="${telar.cuenta || ''}" data-field="cuenta" disabled>
            </td>
            <td class="px-2 py-3 w-20">
                <input type="number" step="0.01" class="w-full px-2 py-1.5 text-xs bg-transparent border-0" value="${telar.calibre ?? ''}" data-field="calibre" disabled>
            </td>
            <td class="px-2 py-3 w-24">
                <input type="text" class="w-full px-2 py-1.5 text-xs bg-transparent border-0" value="${telar.hilo ?? ''}" data-field="hilo" disabled>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="urdido">
                    <option value="">Seleccione...</option>
                    ${opciones.urdido.map(x => `<option value="${x}" ${telar.urdido===x?'selected':''}>${x}</option>`).join('')}
                </select>
            </td>
            <td class="px-2 py-3 w-20">
                <span class="px-2 py-1 inline-block text-xs font-medium rounded-md ${tipoCls}">${telar.tipo || 'N/A'}</span>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 text-xs bg-transparent border-0 cursor-default appearance-none" data-field="destino" disabled>
                    ${opciones.destino.map(x => {
                        const d = (telar.destino || telar.salon || 'JACQUARD').toUpperCase();
                        return `<option value="${x}" ${d===x?'selected':''}>${x}</option>`;
                    }).join('')}
                </select>
            </td>
            <td class="px-2 py-3 w-28">
                <select class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" data-field="tipo_atado">
                    ${opciones.tipoAtado.map(x => `<option value="${x}" ${(telar.tipo_atado||'Normal')===x?'selected':''}>${x}</option>`).join('')}
                </select>
            </td>
            <td class="px-2 py-3 w-24">
                <input type="number" step="0.01" placeholder="Metros"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                       value="${telar.metros ?? ''}" data-field="metros">
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
                        <p class="text-xs mt-2 text-gray-500">Todos los telares deben tener el mismo tipo, y si existen, mismo calibre/hilo/salón.</p>
                    </td>
                </tr>`;
            renderResumenMensaje('No se puede cargar el resumen por error de validación.');
            return;
        }

        // Filtrado tolerante
        const filtrados = telaresData.filter(t => {
            const tipo = String(t.tipo||'').toUpperCase().trim();
            if (tipo !== String(v.tipo||'').toUpperCase().trim()) return false;

            const cal = t.calibre!=null && t.calibre!=='' ? parseFloat(t.calibre) : null;
            if (v.calibre!=null && cal!=null && Math.abs(v.calibre - cal) >= 0.01) return false;

            const hilo = t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null;
            if (v.hilo && hilo && v.hilo !== hilo) return false;

            const salon = String(t.salon || '').trim();
            if (v.salon && salon && v.salon !== salon) return false;

            return true;
        });

        filtrados.forEach((t, i) => tbody.appendChild(crearFila(t, i)));

        // Mantener dataset filtrado para el resumen
        telaresData = filtrados;
        cargarResumenDesdeServidor(v);
    }

    /* =================== Resumen =================== */
    function renderResumenMensaje(msg) {
        const tb = document.getElementById('tbodyResumen');
        tb.innerHTML = `
            <tr>
                <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                    <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                    <p>${msg}</p>
                </td>
            </tr>`;
    }

    async function cargarResumenDesdeServidor(validacion) {
        const tbody = document.getElementById('tbodyResumen');
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                    <div class="flex items-center justify-center gap-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-2 border-gray-300 border-t-blue-500"></div>
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
            if (!res.ok || json.success !== true || !json.data) {
                throw new Error(json.message || `Error HTTP ${res.status}`);
            }

            renderResumen(json.data, validacion);
        } catch (e) {
            renderResumenMensaje(e.message || 'Error al cargar datos');
        }
    }

    function renderResumen(data, validacion) {
        const tb = document.getElementById('tbodyResumen');
        tb.innerHTML = '';

        const tipo = String(validacion.tipo || '').toUpperCase().trim();
        const calEsperado = validacion.calibre;
        const hiloEsperado = validacion.hilo; // null => no filtra por hilo

        // Log para debugging
        console.log('renderResumen - Datos recibidos', {
            tipo,
            calEsperado,
            hiloEsperado,
            dataRizo: data.rizo,
            dataPie: data.pie,
            tieneRizo: Array.isArray(data.rizo) && data.rizo.length > 0,
            tienePie: Array.isArray(data.pie) && data.pie.length > 0
        });

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
                    s4: it.SemActual4MtsRizo || it.semActual4MtsRizo || it.SemActual4 || 0, // Semana 5 (Sem Actual +4)
                    total: it.Total || 0
                });
            }
        }

        if (tipo === 'PIE' && Array.isArray(data.pie)) {
            for (const it of data.pie) {
                const calIt = it.CalibrePie ?? it.calibrePie ?? it.Calibre ?? it.calibre;
                const calNum = calIt!=null && calIt!=='' ? parseFloat(calIt) : null;

                // Validar que el calibre del resumen coincida con el calibre esperado
                const okCal = (calEsperado==null) || (calNum==null) || Math.abs(calEsperado - calNum) < 0.01;
                if (!okCal) continue;

                // Para PIE, el hilo debe ser el mismo que el de la tabla de arriba (hiloEsperado)
                // Validar que el hilo coincida si está presente en el item
                const hiloIt = String(it.Hilo || it.hilo || '').trim();
                if (hiloEsperado != null && hiloIt && hiloIt.toUpperCase() !== String(hiloEsperado).toUpperCase()) {
                    // Si el item tiene hilo pero no coincide, continuar
                    continue;
                }

                items.push({
                    telar: it.TelarId || it.telarId || it.Telar || it.telar || '',
                    cuenta: it.CuentaPie || it.cuentaPie || it.Cuenta || it.cuenta || '',
                    hilo: (hiloEsperado != null && hiloEsperado !== '') ? hiloEsperado : (hiloIt || '-'),
                    calibre: calIt ?? '-',
                    modelo: it.Modelo || it.modelo || '-',
                    s0: it.SemActualMtsPie || it.semActualMtsPie || it.SemActual || 0,
                    s1: it.SemActual1MtsPie || it.semActual1MtsPie || it.SemActual1 || 0,
                    s2: it.SemActual2MtsPie || it.semActual2MtsPie || it.SemActual2 || 0,
                    s3: it.SemActual3MtsPie || it.semActual3MtsPie || it.SemActual3 || 0,
                    s4: it.SemActual4MtsPie || it.semActual4MtsPie || it.SemActual4 || 0, // Semana 5 (Sem Actual +4)
                    total: it.Total || 0
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
            mensaje += `\nHilo: ${hiloEsperado ?? 'Todos'}`;

            if (tieneDatosRizo || tieneDatosPie) {
                mensaje += `\n\nNota: Existen ${tieneDatosRizo ? data.rizo.length : data.pie.length} registro(s) en la base de datos, pero no coinciden con los filtros aplicados (hilo/calibre) o no tienen fechas en el rango de las 5 semanas.`;
            } else {
                mensaje += `\n\nNota: No se encontraron registros de programación para los telares seleccionados en el rango de fechas de las 5 semanas (${validacion?.semanas?.[0]?.inicio || 'N/A'} a ${validacion?.semanas?.[4]?.fin || 'N/A'}).`;
            }

            renderResumenMensaje(mensaje);
            return;
        }

        for (const r of items) {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${r.telar}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${r.cuenta || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${r.hilo || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${r.calibre != null && r.calibre !== '' ? r.calibre : '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">${r.modelo || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">${fmtNum(r.s0)}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">${fmtNum(r.s1)}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">${fmtNum(r.s2)}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">${fmtNum(r.s3)}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-right">${fmtNum(r.s4)}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-right">${fmtNum(r.total)}</td>
            `;
            tb.appendChild(tr);
        }
    }

    function fmtNum(n) {
        const v = Number(n||0);
        return v>0 ? v.toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
    }

    /* =================== Evento único =================== */
    document.getElementById('btnSiguiente')?.addEventListener('click', () => {
        // Coloca aquí la acción de “Siguiente”
        console.log('Siguiente');
    });

    /* =================== Init =================== */
    renderTabla();
});
</script>
@endsection
