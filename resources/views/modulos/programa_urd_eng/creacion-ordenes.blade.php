@extends('layouts.app')

@section('page-title', 'Creación de Órdenes')

@section('navbar-right')
<div class="flex items-center gap-1">
   <x-navbar.button-create onclick="crearOrdenes()" title="Crear Órdenes" />
</div>
@endsection

@section('content')
<div class="w-full">
    {{-- =================== Tabla de requerimientos agrupados =================== --}}
    <div class="bg-white overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table id="tablaOrdenes" class="w-full">
                <thead>
                    <tr class="bg-slate-100 border-b">
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Telar</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Fec Req</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Cuenta</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Calibre</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Hilo</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Urdido</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-20">Tipo</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-28">Destino</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Metros</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-24">Kilos</th>
                        <th class="px-2 py-2 text-center text-xs font-semibold w-32">L.Mat Urdido</th>
                    </tr>
                </thead>
                <tbody id="tbodyOrdenes" class="bg-white divide-y">
                    {{-- filas dinámicas --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== Tablas 2 y 3: Materiales Urdido y Engomado (mismo nivel) =================== --}}
    <div class="flex gap-4 mb-4">
        {{-- =================== Tabla 2: Materiales Urdido =================== --}}
        <div class="w-1/3 bg-white overflow-hidden rounded-2xl flex flex-col" style="max-height: 250px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaMaterialesUrdido" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Articulo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Config</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Consumo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Kilos</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMaterialesUrdido" class="bg-white divide-y">
                        {{-- filas dinámicas --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- =================== Tabla 3: Materiales Engomado =================== --}}
        <div class="flex-1 bg-white overflow-hidden rounded-2xl flex flex-col" style="max-height: 250px;">
            <div class="overflow-x-auto overflow-y-auto flex-1">
                <table id="tablaMaterialesEngomado" class="w-full">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="bg-slate-100 border-b">
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Articulo</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Config</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Tamaño</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Color</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Almacen</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Lote</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">Localidad</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Serie</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Kilos</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Conos</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-28">Lote Proveedor</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-24">No Proveedor</th>
                            <th class="px-2 py-2 text-center text-xs font-semibold w-20">Seleccionar</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMaterialesEngomado" class="bg-white divide-y">
                        {{-- filas dinámicas --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    /* =================== Estado & Constantes =================== */
    let telaresData = normalizeInput(@json($telaresSeleccionados ?? []));
    let filaSeleccionadaId = null; // ID de la fila actualmente seleccionada
    let gruposData = {}; // Almacenar datos de cada grupo (BOM, kilos, etc.)

    // Claves para localStorage
    const STORAGE_KEY_MATERIALES = 'creacion_ordenes_materiales';
    const STORAGE_KEY_SELECCIONES = 'creacion_ordenes_selecciones';

    if (telaresData.length === 0) {
        const urlParams = new URLSearchParams(location.search);
        const raw = urlParams.get('telares');
        if (raw) {
            try {
                telaresData = normalizeInput(JSON.parse(decodeURIComponent(raw)));
            } catch (e) {
                console.error('Error parseando telares:', e);
            }
        }
    }

    /* =================== Funciones localStorage =================== */
    function guardarMaterialesEnStorage(bomId, materialesUrdido, materialesEngomado) {
        try {
            if (!bomId || bomId.trim() === '') {
                console.warn('guardarMaterialesEnStorage: BOM ID vacío, no se guardará');
                return;
            }

            const storage = JSON.parse(localStorage.getItem(STORAGE_KEY_MATERIALES) || '{}');
            storage[bomId] = {
                materialesUrdido: Array.isArray(materialesUrdido) ? materialesUrdido : [],
                materialesEngomado: Array.isArray(materialesEngomado) ? materialesEngomado : [],
                timestamp: Date.now()
            };
            localStorage.setItem(STORAGE_KEY_MATERIALES, JSON.stringify(storage));
            console.log('Materiales guardados en localStorage para BOM:', bomId, '- Urdido:', materialesUrdido?.length || 0, '- Engomado:', materialesEngomado?.length || 0);
        } catch (e) {
            console.error('Error guardando materiales en localStorage:', e);
        }
    }

    function obtenerMaterialesDeStorage(bomId) {
        try {
            const storage = JSON.parse(localStorage.getItem(STORAGE_KEY_MATERIALES) || '{}');
            return storage[bomId] || null;
        } catch (e) {
            console.error('Error obteniendo materiales de localStorage:', e);
            return null;
        }
    }

    function guardarSeleccionesEnStorage(bomId, selecciones) {
        try {
            const storage = JSON.parse(localStorage.getItem(STORAGE_KEY_SELECCIONES) || '{}');
            storage[bomId] = selecciones;
            localStorage.setItem(STORAGE_KEY_SELECCIONES, JSON.stringify(storage));
            console.log('Selecciones guardadas en localStorage para BOM:', bomId);
        } catch (e) {
            console.error('Error guardando selecciones en localStorage:', e);
        }
    }

    function obtenerSeleccionesDeStorage(bomId) {
        try {
            const storage = JSON.parse(localStorage.getItem(STORAGE_KEY_SELECCIONES) || '{}');
            return storage[bomId] || [];
        } catch (e) {
            console.error('Error obteniendo selecciones de localStorage:', e);
            return [];
        }
    }

    function limpiarMaterialesDeStorage(bomId) {
        try {
            const storage = JSON.parse(localStorage.getItem(STORAGE_KEY_MATERIALES) || '{}');
            delete storage[bomId];
            localStorage.setItem(STORAGE_KEY_MATERIALES, JSON.stringify(storage));
            console.log('Materiales eliminados de localStorage para BOM:', bomId);
        } catch (e) {
            console.error('Error limpiando materiales de localStorage:', e);
        }
    }

    /* =================== Helpers =================== */
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
            tipo: normalizarTipo(t.tipo),
            hilo: t.hilo && String(t.hilo).trim() !== '' ? String(t.hilo).trim() : null,
            metros: t.metros ? parseFloat(String(t.metros).replace(/,/g, '')) : 0,
            kilos: t.kilos ? parseFloat(String(t.kilos).replace(/,/g, '')) : 0,
            agrupar: t.agrupar || false
        }));
    }

    function formatNumberInput(value, decimals = 2) {
        if (!value || value === '') return '';
        const num = parseFloat(String(value).replace(/,/g, ''));
        if (isNaN(num)) return '';
        return num.toLocaleString('es-MX', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function parseNumberInput(value) {
        if (!value || value === '') return '';
        return String(value).replace(/,/g, '');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr + 'T00:00:00');
            if (isNaN(date.getTime())) return dateStr;
            return date.toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit', year:'numeric'});
        } catch {
            return dateStr;
        }
    }

    /* =================== Agrupar telares =================== */
    function agruparTelares(telares) {
        const grupos = {};
        const noAgrupados = [];

        telares.forEach(telar => {
            if (telar.agrupar) {
                // Normalizar tipo y crear clave de agrupación
                const tipoNormalizado = normalizarTipo(telar.tipo);
                const tipoUpper = String(tipoNormalizado || '').toUpperCase().trim();
                const esPie = tipoUpper === 'PIE';
                const cuenta = String(telar.cuenta || '').trim();
                const calibre = telar.calibre != null && telar.calibre !== '' ? parseFloat(telar.calibre).toFixed(2) : '';
                const hilo = esPie ? '' : (telar.hilo && String(telar.hilo).trim() !== '' ? String(telar.hilo).trim() : '');
                const urdido = String(telar.urdido || '').trim();
                const tipoAtado = String(telar.tipo_atado || 'Normal').trim();
                const destino = String(telar.destino || '').trim();

                const clave = esPie
                    ? `${cuenta}|${calibre}|${tipoUpper}|${urdido}|${tipoAtado}|${destino}`
                    : `${cuenta}|${hilo}|${calibre}|${tipoUpper}|${urdido}|${tipoAtado}|${destino}`;

                if (!grupos[clave]) {
                    grupos[clave] = {
                        telares: [],
                        cuenta: cuenta,
                        calibre: calibre,
                        hilo: hilo,
                        tipo: tipoNormalizado, // Guardar tipo normalizado (Rizo/Pie)
                        urdido: urdido,
                        tipoAtado: tipoAtado,
                        destino: destino,
                        fechaReq: telar.fecha_req || '',
                        metros: 0,
                        kilos: 0
                    };
                }

                grupos[clave].telares.push(telar);
                grupos[clave].metros += telar.metros || 0;
                grupos[clave].kilos += telar.kilos || 0;
            } else {
                noAgrupados.push(telar);
            }
        });

        // Convertir grupos a array y agregar no agrupados
        const resultado = Object.values(grupos).map(grupo => ({
            ...grupo,
            telaresStr: grupo.telares.map(t => t.no_telar).join(',')
        }));

        noAgrupados.forEach(telar => {
            resultado.push({
                telares: [telar],
                telaresStr: telar.no_telar,
                cuenta: telar.cuenta || '',
                calibre: telar.calibre || '',
                hilo: telar.hilo || '',
                tipo: normalizarTipo(telar.tipo), // Normalizar tipo para telares no agrupados
                urdido: telar.urdido || '',
                tipoAtado: telar.tipo_atado || 'Normal',
                destino: telar.destino || '',
                fechaReq: telar.fecha_req || '',
                metros: telar.metros || 0,
                kilos: telar.kilos || 0
            });
        });

        return resultado;
    }

    /* =================== Render tabla =================== */
    function renderTabla() {
        const tbody = document.getElementById('tbodyOrdenes');
        tbody.innerHTML = '';

        if (telaresData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                        <p>No hay telares seleccionados.</p>
                    </td>
                </tr>`;
            return;
        }

        const grupos = agruparTelares(telaresData);

        grupos.forEach((grupo, index) => {
            const tipoCls = (String(grupo.tipo||'').toUpperCase()==='RIZO')
                ? 'bg-rose-100 text-rose-700' : (String(grupo.tipo||'').toUpperCase()==='PIE'
                ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');

            // Generar ID único para cada fila
            const filaId = `fila-${index}-${grupo.telaresStr}`;

            // Almacenar datos del grupo
            gruposData[filaId] = {
                grupo: grupo,
                bomId: '',
                kilos: grupo.kilos || 0,
                materialesUrdido: null // Para guardar temporalmente los materiales de urdido
            };

            const tr = document.createElement('tr');
            tr.id = filaId;
            tr.className = 'border-b hover:bg-gray-50 cursor-pointer transition-colors';
            tr.dataset.filaId = filaId;

            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${grupo.telaresStr || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${formatDate(grupo.fechaReq)}</td>
                <td class="px-2 py-3 text-xs text-center">${grupo.cuenta || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${grupo.calibre || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${grupo.hilo || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${grupo.urdido || '-'}</td>
                <td class="px-2 py-3 text-center">
                    <span class="px-2 py-1 inline-block text-xs font-medium rounded-md ${tipoCls}">${grupo.tipo || 'N/A'}</span>
                </td>
                <td class="px-2 py-3 text-xs text-center">${grupo.destino || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(grupo.metros)}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(grupo.kilos)}</td>
                <td class="px-2 py-3 text-center">
                    <input type="text"
                           placeholder="Buscar BOM..."
                           class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                           data-grupo="${grupo.telaresStr}"
                           data-fila-id="${filaId}"
                           data-kilos="${grupo.kilos || 0}"
                           data-bom-input="true"
                           data-bom-id=""
                           autocomplete="off"
                           onclick="event.stopPropagation();">
                </td>
            `;

            // Agregar evento click a la fila (excepto cuando se hace click en el input)
            tr.addEventListener('click', (e) => {
                // No seleccionar si se hace click en el input
                if (e.target.tagName === 'INPUT') {
                    return;
                }
                seleccionarFila(filaId);
            });

            tbody.appendChild(tr);

            // Restaurar BOM si ya existe en gruposData después de que se agrega el input
            setTimeout(() => {
                const inputBom = tr.querySelector('[data-bom-input="true"]');
                if (inputBom && gruposData[filaId] && gruposData[filaId].bomId) {
                    inputBom.value = gruposData[filaId].bomId;
                    inputBom.dataset.bomId = gruposData[filaId].bomId;
                }
            }, 0);
        });

        // Seleccionar la primera fila por defecto si hay filas
        if (grupos.length > 0) {
            const primeraFilaId = `fila-0-${grupos[0].telaresStr}`;
            setTimeout(() => {
                seleccionarFila(primeraFilaId);
            }, 150);
        }
    }

    /* =================== Seleccionar fila =================== */
    function seleccionarFila(filaId) {
        // Remover selección anterior
        if (filaSeleccionadaId) {
            const filaAnterior = document.getElementById(filaSeleccionadaId);
            if (filaAnterior) {
                filaAnterior.classList.remove('bg-blue-100', 'border-l-4', 'border-blue-500');
                filaAnterior.classList.add('hover:bg-gray-50');
            }
        }

        // Seleccionar nueva fila
        filaSeleccionadaId = filaId;
        const fila = document.getElementById(filaId);
        if (fila) {
            fila.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500');
            fila.classList.remove('hover:bg-gray-50');
        }

        // Obtener datos del grupo seleccionado
        const grupoData = gruposData[filaId];
        if (!grupoData) {
            console.warn('No se encontraron datos para la fila:', filaId);
            renderTablaMaterialesUrdido([], 0);
            renderTablaMaterialesEngomado([]);
            return;
        }

        // Obtener BOM de la fila seleccionada
        const inputBom = fila?.querySelector('[data-bom-input="true"]');
        // Priorizar el BOM guardado en gruposData, luego el dataset, luego el value
        const bomId = grupoData.bomId || inputBom?.dataset.bomId || inputBom?.value || '';

        // Actualizar el input si hay un BOM guardado pero no está en el input
        if (grupoData.bomId && inputBom && (!inputBom.value || inputBom.value !== grupoData.bomId)) {
            inputBom.value = grupoData.bomId;
            inputBom.dataset.bomId = grupoData.bomId;
        }

        if (bomId && bomId.trim() !== '') {
            // Cargar materiales para el BOM seleccionado (no forzar consulta, usar localStorage si existe)
            const kilos = grupoData.kilos || 0;
            cargarMaterialesUrdido(bomId.trim(), kilos, false);
            grupoData.bomId = bomId.trim();
        } else {
            // Si no hay BOM, limpiar las tablas
            renderTablaMaterialesUrdido([], grupoData.kilos);
            renderTablaMaterialesEngomado([], null);
        }
    }

    /* =================== Autocompletado BOM Urdido =================== */
    function inicializarAutocompletadoBOM() {
        const inputs = document.querySelectorAll('[data-bom-input="true"]');

        // Crear contenedor global para sugerencias fuera del flujo de la tabla
        let globalSuggestionsContainer = document.getElementById('bom-suggestions-global');
        if (!globalSuggestionsContainer) {
            globalSuggestionsContainer = document.createElement('div');
            globalSuggestionsContainer.id = 'bom-suggestions-global';
            globalSuggestionsContainer.className = 'fixed z-[99999] bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
            document.body.appendChild(globalSuggestionsContainer);
        }

        let activeInput = null;
        let selectedIndex = -1;
        let debounceTimer = null;
        let scrollHandler = null;
        let resizeHandler = null;
        let clickHandler = null;

        // Función para posicionar el contenedor de sugerencias
        const posicionarSugerencias = (inputEl) => {
            if (!inputEl) return;
            const rect = inputEl.getBoundingClientRect();
            globalSuggestionsContainer.style.top = (rect.bottom + window.scrollY + 4) + 'px';
            globalSuggestionsContainer.style.left = (rect.left + window.scrollX) + 'px';
            globalSuggestionsContainer.style.width = rect.width + 'px';
        };

        // Función para buscar BOM
        const buscarBOM = async (query, inputEl) => {
            if (!query || query.trim().length < 1) {
                globalSuggestionsContainer.classList.add('hidden');
                activeInput = null;
                return;
            }

            try {
                const url = new URL('{{ route("programa.urd.eng.buscar.bom.urdido") }}', window.location.origin);
                url.searchParams.set('q', query.trim());

                const response = await fetch(url.toString());
                if (!response.ok) {
                    globalSuggestionsContainer.classList.add('hidden');
                    activeInput = null;
                    return;
                }

                const data = await response.json();
                mostrarSugerencias(data, inputEl);
                posicionarSugerencias(inputEl);
            } catch (e) {
                console.error('Error en autocompletado BOM:', e);
                globalSuggestionsContainer.classList.add('hidden');
                activeInput = null;
            }
        };

        // Función para mostrar sugerencias
        const mostrarSugerencias = (sugerencias, inputEl) => {
            if (!sugerencias || sugerencias.length === 0) {
                globalSuggestionsContainer.classList.add('hidden');
                activeInput = null;
                return;
            }

            globalSuggestionsContainer.innerHTML = '';
            selectedIndex = -1;
            activeInput = inputEl;

            sugerencias.forEach((sugerencia, index) => {
                const div = document.createElement('div');
                div.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer text-xs border-b border-gray-100 last:border-b-0';
                div.textContent = `${sugerencia.BOMID} - ${sugerencia.ITEMNAME || sugerencia.ITEMID || ''}`;
                div.dataset.bomid = sugerencia.BOMID;
                div.dataset.index = index;

                div.addEventListener('click', () => {
                    if (activeInput) {
                        activeInput.value = sugerencia.BOMID;
                        activeInput.dataset.bomId = sugerencia.BOMID;
                        globalSuggestionsContainer.classList.add('hidden');
                        selectedIndex = -1;

                        // Obtener datos de la fila asociada
                        const filaId = activeInput.dataset.filaId;
                        const kilosProgramados = parseFloat(activeInput.dataset.kilos || 0);

                        // Actualizar datos del grupo
                        if (filaId && gruposData[filaId]) {
                            const bomIdAnterior = gruposData[filaId].bomId;
                            gruposData[filaId].bomId = sugerencia.BOMID;

                            // Si cambió el BOM, limpiar materiales anteriores del storage
                            if (bomIdAnterior && bomIdAnterior !== sugerencia.BOMID) {
                                limpiarMaterialesDeStorage(bomIdAnterior);
                            }
                        }

                        // Si esta es la fila seleccionada, cargar materiales inmediatamente (forzar consulta)
                        // Si no está seleccionada, los materiales se cargarán cuando se seleccione la fila
                        if (filaId === filaSeleccionadaId) {
                            cargarMaterialesUrdido(sugerencia.BOMID, kilosProgramados, true);
                        }

                        activeInput = null;
                    }
                });

                div.addEventListener('mouseenter', () => {
                    globalSuggestionsContainer.querySelectorAll('div').forEach(d => {
                        d.classList.remove('bg-blue-100');
                    });
                    div.classList.add('bg-blue-100');
                    selectedIndex = index;
                });

                globalSuggestionsContainer.appendChild(div);
            });

            globalSuggestionsContainer.classList.remove('hidden');
            posicionarSugerencias(inputEl);
        };

        // Limpiar event listeners anteriores
        const limpiarEventListeners = () => {
            if (scrollHandler) {
                window.removeEventListener('scroll', scrollHandler, true);
            }
            if (resizeHandler) {
                window.removeEventListener('resize', resizeHandler);
            }
            if (clickHandler) {
                document.removeEventListener('click', clickHandler, true);
            }
        };

        // Crear nuevos event listeners
        scrollHandler = () => {
            if (activeInput && !globalSuggestionsContainer.classList.contains('hidden')) {
                posicionarSugerencias(activeInput);
            }
        };

        resizeHandler = () => {
            if (activeInput && !globalSuggestionsContainer.classList.contains('hidden')) {
                posicionarSugerencias(activeInput);
            }
        };

        clickHandler = (e) => {
            if (activeInput && !activeInput.contains(e.target) && !globalSuggestionsContainer.contains(e.target)) {
                globalSuggestionsContainer.classList.add('hidden');
                selectedIndex = -1;
                activeInput = null;
            }
        };

        window.addEventListener('scroll', scrollHandler, true);
        window.addEventListener('resize', resizeHandler);
        document.addEventListener('click', clickHandler, true);

        // Configurar cada input
        inputs.forEach(input => {
            input.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                const query = e.target.value;
                activeInput = e.target;
                debounceTimer = setTimeout(() => {
                    buscarBOM(query, e.target);
                }, 300);
            });

            input.addEventListener('focus', (e) => {
                activeInput = e.target;
                if (e.target.value.trim().length >= 1) {
                    buscarBOM(e.target.value, e.target);
                }
            });

            // Actualizar tablas cuando se cambia el BOM manualmente y la fila está seleccionada
            input.addEventListener('blur', (e) => {
                const filaId = e.target.dataset.filaId;
                const bomId = e.target.value.trim();

                // Si la fila está seleccionada y hay un BOM, actualizar las tablas
                if (filaId === filaSeleccionadaId && bomId) {
                    const kilos = parseFloat(e.target.dataset.kilos || 0);
                    if (gruposData[filaId]) {
                        const bomIdAnterior = gruposData[filaId].bomId;
                        gruposData[filaId].bomId = bomId;
                        e.target.dataset.bomId = bomId;

                        // Si cambió el BOM, limpiar materiales anteriores del storage y forzar consulta
                        if (bomIdAnterior && bomIdAnterior !== bomId) {
                            limpiarMaterialesDeStorage(bomIdAnterior);
                            cargarMaterialesUrdido(bomId, kilos, true);
                        } else {
                            // Si es el mismo BOM, no forzar consulta (usará localStorage si existe)
                            cargarMaterialesUrdido(bomId, kilos, false);
                        }
                    }
                }
            });

            // Evitar que el click en el input seleccione la fila
            input.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            input.addEventListener('keydown', (e) => {
                const items = globalSuggestionsContainer.querySelectorAll('div');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!globalSuggestionsContainer.classList.contains('hidden') && items.length > 0) {
                        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                        if (items[selectedIndex]) {
                            items[selectedIndex].scrollIntoView({ block: 'nearest' });
                            items.forEach((item, idx) => {
                                item.classList.toggle('bg-blue-100', idx === selectedIndex);
                            });
                        }
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!globalSuggestionsContainer.classList.contains('hidden') && items.length > 0) {
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        items.forEach((item, idx) => {
                            item.classList.toggle('bg-blue-100', idx === selectedIndex);
                        });
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && items[selectedIndex]) {
                        items[selectedIndex].click();
                    } else if (activeInput && activeInput.value.trim() !== '') {
                        // Si no hay selección pero hay texto, intentar cargar con ese valor
                        const filaId = activeInput.dataset.filaId;
                        const bomId = activeInput.value.trim();
                        const kilosProgramados = parseFloat(activeInput.dataset.kilos || 0);

                        if (filaId && gruposData[filaId]) {
                            const bomIdAnterior = gruposData[filaId].bomId;
                            gruposData[filaId].bomId = bomId;
                            activeInput.dataset.bomId = bomId;

                            // Si cambió el BOM, limpiar materiales anteriores del storage
                            if (bomIdAnterior && bomIdAnterior !== bomId) {
                                limpiarMaterialesDeStorage(bomIdAnterior);
                            }

                            if (filaId === filaSeleccionadaId) {
                                // Forzar consulta si cambió el BOM, sino usar localStorage
                                const forzarConsulta = bomIdAnterior && bomIdAnterior !== bomId;
                                cargarMaterialesUrdido(bomId, kilosProgramados, forzarConsulta);
                            }
                        }

                        globalSuggestionsContainer.classList.add('hidden');
                        activeInput = null;
                        selectedIndex = -1;
                    } else {
                        globalSuggestionsContainer.classList.add('hidden');
                        activeInput = null;
                        selectedIndex = -1;
                    }
                } else if (e.key === 'Escape') {
                    globalSuggestionsContainer.classList.add('hidden');
                    activeInput = null;
                    selectedIndex = -1;
                }
            });
        });
    }

    /* =================== Eventos =================== */
    document.getElementById('btnReservarInventario')?.addEventListener('click', () => {
        // TODO: Implementar lógica de reservar inventario
        console.log('Reservar inventario');
        alert('Función de reservar inventario pendiente de implementar');
    });

    document.getElementById('btnCrearOrdenes')?.addEventListener('click', () => {
        // TODO: Implementar lógica de crear órdenes
        console.log('Crear órdenes');
        alert('Función de crear órdenes pendiente de implementar');
    });

    /* =================== Cargar materiales de urdido =================== */
    async function cargarMaterialesUrdido(bomId, kilosProgramados = 0, forzarConsulta = false) {
        console.log('cargarMaterialesUrdido: BOM ID recibido', bomId, 'Kilos programados:', kilosProgramados, 'Forzar consulta:', forzarConsulta);
        if (!bomId || bomId.trim() === '') {
            console.log('cargarMaterialesUrdido: BOM ID vacío');
            renderTablaMaterialesUrdido([], kilosProgramados, null);
            renderTablaMaterialesEngomado([], null);
            return;
        }

        const bomIdClean = bomId.trim();

        // Verificar si ya existen en localStorage (a menos que se fuerce la consulta)
        if (!forzarConsulta) {
            const materialesGuardados = obtenerMaterialesDeStorage(bomIdClean);
            // Verificar que existan ambos materiales Y que el array de engomado no esté vacío
            if (materialesGuardados &&
                materialesGuardados.materialesUrdido &&
                Array.isArray(materialesGuardados.materialesUrdido) &&
                materialesGuardados.materialesUrdido.length > 0 &&
                materialesGuardados.materialesEngomado &&
                Array.isArray(materialesGuardados.materialesEngomado) &&
                materialesGuardados.materialesEngomado.length > 0) {
                console.log('cargarMaterialesUrdido: Materiales encontrados en localStorage, usando datos guardados');
                console.log('  - Materiales Urdido:', materialesGuardados.materialesUrdido.length);
                console.log('  - Materiales Engomado:', materialesGuardados.materialesEngomado.length);
                // Renderizar materiales de urdido sin llamar a cargarMaterialesEngomado
                renderTablaMaterialesUrdidoDesdeStorage(materialesGuardados.materialesUrdido, kilosProgramados, bomIdClean);
                // Renderizar materiales de engomado directamente desde storage
                renderTablaMaterialesEngomado(materialesGuardados.materialesEngomado, bomIdClean);

                // Restaurar selecciones guardadas
                const seleccionesGuardadas = obtenerSeleccionesDeStorage(bomIdClean);
                if (seleccionesGuardadas && seleccionesGuardadas.length > 0) {
                    setTimeout(() => {
                        restaurarSelecciones(seleccionesGuardadas);
                    }, 100);
                }
                return;
            } else {
                console.log('cargarMaterialesUrdido: No hay materiales completos en localStorage, consultando API...');
            }
        }

        // Si no hay en localStorage o se fuerza la consulta, hacer la petición
        try {
            const url = new URL('{{ route("programa.urd.eng.materiales.urdido") }}', window.location.origin);
            url.searchParams.set('bomId', bomIdClean);

            console.log('cargarMaterialesUrdido: Consultando API...', url.toString());

            const response = await fetch(url.toString());
            console.log('cargarMaterialesUrdido: Response status', response.status);

            if (!response.ok) {
                console.error('cargarMaterialesUrdido: Response no OK', response.status);
                renderTablaMaterialesUrdido([], kilosProgramados, bomIdClean);
                return;
            }

            const data = await response.json();
            console.log('cargarMaterialesUrdido: Data recibida', data);
            console.log('cargarMaterialesUrdido: Cantidad de materiales', Array.isArray(data) ? data.length : 'no es array');

            // NO guardar en localStorage todavía - esperar a que se carguen los materiales de engomado
            // Solo renderizar y cargar materiales de engomado
            // Como se consultó desde la API, forzar consulta de materiales de engomado también
            renderTablaMaterialesUrdido(data, kilosProgramados, bomIdClean, true);
        } catch (e) {
            console.error('Error al cargar materiales de urdido:', e);
            console.error('Error stack:', e.stack);
            renderTablaMaterialesUrdido([], kilosProgramados, bomIdClean, false);
        }
    }

    /* =================== Render tablas de materiales =================== */
    function renderTablaMaterialesUrdido(materiales = [], kilosProgramados = 0, bomId = null, forzarConsultaEngomado = false) {
        const tbody = document.getElementById('tbodyMaterialesUrdido');

        if (!materiales || materiales.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                        <p>No hay materiales de urdido disponibles.</p>
                    </td>
                </tr>`;
            // Limpiar tabla de engomado si no hay materiales
            renderTablaMaterialesEngomado([], bomId);
            return;
        }

        // Si hay BOM, guardar materiales de urdido temporalmente en gruposData para poder guardarlos después con los de engomado
        if (bomId && gruposData && filaSeleccionadaId) {
            // Guardar en la fila seleccionada actual
            if (gruposData[filaSeleccionadaId]) {
                gruposData[filaSeleccionadaId].materialesUrdido = materiales;
                gruposData[filaSeleccionadaId].bomId = bomId;
                console.log('renderTablaMaterialesUrdido: Materiales de urdido guardados temporalmente en gruposData para fila:', filaSeleccionadaId, 'BOM:', bomId);
            }
        } else if (bomId && gruposData) {
            // Si no hay fila seleccionada, buscar por BOM
            Object.keys(gruposData).forEach(filaId => {
                if (gruposData[filaId].bomId === bomId) {
                    gruposData[filaId].materialesUrdido = materiales;
                    console.log('renderTablaMaterialesUrdido: Materiales de urdido guardados temporalmente en gruposData para BOM:', bomId);
                }
            });
        }

        tbody.innerHTML = '';

        // Convertir kilos programados a número
        const kilosProg = parseFloat(kilosProgramados || 0);

        materiales.forEach(material => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';

            // Calcular kilos: Kilos Programados * Consumo (consumo redondeado a 3 decimales)
            const consumoOriginal = parseFloat(material.BomQty || 0);
            const consumo = Math.round((consumoOriginal || 0) * 1000) / 1000;
            const kilos = kilosProg * consumo;

            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${material.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(consumo, 3)}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(kilos)}</td>
            `;
            tbody.appendChild(tr);
        });

        // Cargar materiales de engomado basados en los ItemIds y ConfigIds de los materiales de urdido
        const itemIds = [...new Set(materiales.map(m => m.ItemId).filter(id => id && id !== ''))];
        const configIds = [...new Set(materiales.map(m => m.ConfigId).filter(id => id && id !== '' && id !== null))];
        console.log('renderTablaMaterialesUrdido: ItemIds extraídos (únicos)', itemIds);
        console.log('renderTablaMaterialesUrdido: ConfigIds extraídos (únicos)', configIds);
        console.log('renderTablaMaterialesUrdido: BOM ID para cargar materiales de engomado:', bomId);
        console.log('renderTablaMaterialesUrdido: Forzar consulta engomado:', forzarConsultaEngomado);
        if (itemIds.length > 0) {
            console.log('renderTablaMaterialesUrdido: Llamando a cargarMaterialesEngomado con bomId:', bomId, 'forzarConsulta:', forzarConsultaEngomado);
            cargarMaterialesEngomado(itemIds, configIds, bomId, forzarConsultaEngomado);
        } else {
            console.log('renderTablaMaterialesUrdido: No hay ItemIds válidos');
            renderTablaMaterialesEngomado([], bomId);
        }
    }

    /* =================== Render desde storage (sin consultar) =================== */
    function renderTablaMaterialesUrdidoDesdeStorage(materiales = [], kilosProgramados = 0, bomId = null) {
        const tbody = document.getElementById('tbodyMaterialesUrdido');

        if (!materiales || materiales.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                        <p>No hay materiales de urdido disponibles.</p>
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = '';

        // Convertir kilos programados a número
        const kilosProg = parseFloat(kilosProgramados || 0);

        materiales.forEach(material => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';

            // Calcular kilos: Kilos Programados * Consumo (consumo redondeado a 3 decimales)
            const consumoOriginal = parseFloat(material.BomQty || 0);
            const consumo = Math.round((consumoOriginal || 0) * 1000) / 1000;
            const kilos = kilosProg * consumo;

            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${material.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(consumo, 3)}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(kilos)}</td>
            `;
            tbody.appendChild(tr);
        });

        console.log('renderTablaMaterialesUrdidoDesdeStorage: Tabla renderizada desde localStorage');
    }

    /* =================== Cargar materiales de engomado =================== */
    async function cargarMaterialesEngomado(itemIds, configIds = [], bomId = null, forzarConsulta = false) {
        console.log('cargarMaterialesEngomado: INICIO - ItemIds:', itemIds, 'ConfigIds:', configIds, 'BOM ID:', bomId, 'Forzar:', forzarConsulta);

        if (!itemIds || itemIds.length === 0) {
            console.log('cargarMaterialesEngomado: No hay ItemIds');
            renderTablaMaterialesEngomado([], bomId);
            return;
        }

        // Si hay BOM y no se fuerza la consulta, verificar localStorage
        if (bomId && !forzarConsulta) {
            console.log('cargarMaterialesEngomado: Verificando localStorage para BOM:', bomId);
            const materialesGuardados = obtenerMaterialesDeStorage(bomId);
            console.log('cargarMaterialesEngomado: Materiales guardados encontrados:', materialesGuardados);

            // Verificar que existan materiales de engomado Y que el array no esté vacío
            if (materialesGuardados && materialesGuardados.materialesEngomado && Array.isArray(materialesGuardados.materialesEngomado) && materialesGuardados.materialesEngomado.length > 0) {
                console.log('cargarMaterialesEngomado: Materiales de engomado encontrados en localStorage', materialesGuardados.materialesEngomado.length, 'registros');
                renderTablaMaterialesEngomado(materialesGuardados.materialesEngomado, bomId);

                // Restaurar selecciones guardadas
                const seleccionesGuardadas = obtenerSeleccionesDeStorage(bomId);
                if (seleccionesGuardadas && seleccionesGuardadas.length > 0) {
                    setTimeout(() => {
                        restaurarSelecciones(seleccionesGuardadas);
                    }, 50);
                }
                return;
            } else {
                console.log('cargarMaterialesEngomado: No hay materiales de engomado en localStorage o están vacíos');
                if (materialesGuardados) {
                    console.log('cargarMaterialesEngomado: Estado de materiales guardados:', {
                        tieneMaterialesEngomado: !!materialesGuardados.materialesEngomado,
                        esArray: Array.isArray(materialesGuardados.materialesEngomado),
                        longitud: materialesGuardados.materialesEngomado ? materialesGuardados.materialesEngomado.length : 0
                    });
                }
                console.log('cargarMaterialesEngomado: Consultando API...');
            }
        } else {
            if (!bomId) {
                console.log('cargarMaterialesEngomado: No hay BOM ID, consultando API sin verificar localStorage');
            }
            if (forzarConsulta) {
                console.log('cargarMaterialesEngomado: Consulta forzada, omitiendo localStorage');
            }
        }

        console.log('cargarMaterialesEngomado: Preparando consulta API - ItemIds:', itemIds.length, 'ConfigIds:', configIds.length);

        try {
            // Construir URL con parámetros como query string
            const baseUrl = '{{ route("programa.urd.eng.materiales.engomado") }}';
            const params = new URLSearchParams();
            itemIds.forEach(itemId => {
                params.append('itemIds[]', itemId);
            });
            // Agregar ConfigIds si existen
            if (configIds && configIds.length > 0) {
                configIds.forEach(configId => {
                    params.append('configIds[]', configId);
                });
            }
            const url = `${baseUrl}?${params.toString()}`;

            console.log('cargarMaterialesEngomado: URL completa', url);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            console.log('cargarMaterialesEngomado: Response status', response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('cargarMaterialesEngomado: Error response', errorText);
                renderTablaMaterialesEngomado([], bomId);
                return;
            }

            const data = await response.json();
            console.log('cargarMaterialesEngomado: Data recibida', data);
            console.log('cargarMaterialesEngomado: Cantidad de registros', Array.isArray(data) ? data.length : 'no es array');

            // Guardar en localStorage si hay BOM (guardar ambos: urdido y engomado)
            if (bomId) {
                let materialesUrdido = [];

                // Primero intentar obtener desde gruposData usando la fila seleccionada
                if (filaSeleccionadaId && gruposData[filaSeleccionadaId] && gruposData[filaSeleccionadaId].materialesUrdido) {
                    materialesUrdido = gruposData[filaSeleccionadaId].materialesUrdido;
                    console.log('cargarMaterialesEngomado: Materiales de urdido obtenidos desde gruposData (fila seleccionada)');
                } else {
                    // Si no se encontraron en gruposData, buscar por BOM en todas las filas
                    Object.keys(gruposData).forEach(filaId => {
                        if (gruposData[filaId].bomId === bomId && gruposData[filaId].materialesUrdido) {
                            materialesUrdido = gruposData[filaId].materialesUrdido;
                            console.log('cargarMaterialesEngomado: Materiales de urdido obtenidos desde gruposData (por BOM)');
                        }
                    });
                }

                // Si no se encontraron en gruposData, intentar desde localStorage
                if (materialesUrdido.length === 0) {
                    const materialesGuardados = obtenerMaterialesDeStorage(bomId);
                    if (materialesGuardados && materialesGuardados.materialesUrdido && Array.isArray(materialesGuardados.materialesUrdido)) {
                        materialesUrdido = materialesGuardados.materialesUrdido;
                        console.log('cargarMaterialesEngomado: Materiales de urdido obtenidos desde localStorage');
                    }
                }

                // Si aún no se tienen materiales de urdido, obtenerlos desde la tabla renderizada
                if (materialesUrdido.length === 0) {
                    console.warn('cargarMaterialesEngomado: No se encontraron materiales de urdido, se guardarán solo los de engomado');
                }

                // Guardar ambos materiales en localStorage
                guardarMaterialesEnStorage(bomId, materialesUrdido, data);
                console.log('cargarMaterialesEngomado: Materiales guardados en localStorage - Urdido:', materialesUrdido.length, 'Engomado:', data.length);
            }

            renderTablaMaterialesEngomado(data, bomId);
        } catch (e) {
            console.error('Error al cargar materiales de engomado:', e);
            console.error('Error stack:', e.stack);
            renderTablaMaterialesEngomado([], bomId);
        }
    }

    function renderTablaMaterialesEngomado(materiales = [], bomId = null) {
        const tbody = document.getElementById('tbodyMaterialesEngomado');
        console.log('renderTablaMaterialesEngomado: Materiales recibidos', materiales);
        console.log('renderTablaMaterialesEngomado: Es array?', Array.isArray(materiales));
        console.log('renderTablaMaterialesEngomado: Cantidad', Array.isArray(materiales) ? materiales.length : 'no es array');

        if (!materiales || !Array.isArray(materiales) || materiales.length === 0) {
            console.log('renderTablaMaterialesEngomado: No hay materiales, mostrando mensaje vacío');
            tbody.innerHTML = `
                <tr>
                    <td colspan="13" class="px-4 py-8 text-center text-gray-500">
                        <i class="fa-solid fa-circle-info text-gray-400 mb-2"></i>
                        <p>No hay materiales de engomado disponibles.</p>
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = '';
        console.log('renderTablaMaterialesEngomado: Renderizando', materiales.length, 'materiales');

        materiales.forEach((material, index) => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';

            // Formatear fecha de producción
            const prodDate = material.ProdDate ? formatDate(material.ProdDate) : '-';
            const kilos = parseFloat(material.PhysicalInvent || 0);
            const conos = parseFloat(material.TwTiras || 0);
            // Lote Proveedor y No Proveedor desde InventSerial
            const loteProveedor = material.TwCalidadFlog || '-';
            const noProveedor = material.TwClienteFlog || '-';

            // Crear clave única para el checkbox
            const checkboxKey = `${material.ItemId || ''}_${material.InventSerialId || ''}`;

            tr.innerHTML = `
                <td class="px-2 py-3 text-xs text-center">${material.ItemId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.ConfigId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.InventSizeId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.InventColorId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.InventLocationId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.InventBatchId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.WMSLocationId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${material.InventSerialId || '-'}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(kilos)}</td>
                <td class="px-2 py-3 text-xs text-center">${formatNumberInput(conos)}</td>
                <td class="px-2 py-3 text-xs text-center">${loteProveedor}</td>
                <td class="px-2 py-3 text-xs text-center">${noProveedor}</td>
                <td class="px-2 py-3 text-center">
                    <input type="checkbox"
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 checkbox-material"
                           data-material-id="${material.ItemId || ''}"
                           data-serial-id="${material.InventSerialId || ''}"
                           data-checkbox-key="${checkboxKey}"
                           data-bom-id="${bomId || ''}">
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Agregar event listeners a los checkboxes
        agregarEventListenersCheckboxes(bomId);

        // Restaurar selecciones guardadas después de renderizar
        if (bomId) {
            setTimeout(() => {
                const seleccionesGuardadas = obtenerSeleccionesDeStorage(bomId);
                if (seleccionesGuardadas && seleccionesGuardadas.length > 0) {
                    restaurarSelecciones(seleccionesGuardadas);
                }
            }, 50);
        }

        console.log('renderTablaMaterialesEngomado: Tabla renderizada con', tbody.children.length, 'filas');
    }

    /* =================== Gestión de selecciones =================== */
    function agregarEventListenersCheckboxes(bomId) {
        const checkboxes = document.querySelectorAll('.checkbox-material');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                guardarSeleccionesCheckboxes(bomId);
            });
        });
    }

    function guardarSeleccionesCheckboxes(bomId) {
        if (!bomId) {
            // Intentar obtener BOM del primer checkbox (todos los checkboxes tienen el mismo BOM)
            const primerCheckbox = document.querySelector('.checkbox-material');
            if (primerCheckbox && primerCheckbox.dataset.bomId) {
                bomId = primerCheckbox.dataset.bomId;
            } else {
                // Si no, obtener BOM de la fila seleccionada
                const grupoData = gruposData[filaSeleccionadaId];
                if (grupoData && grupoData.bomId) {
                    bomId = grupoData.bomId;
                } else {
                    console.warn('guardarSeleccionesCheckboxes: No se pudo obtener el BOM');
                    return;
                }
            }
        }

        const checkboxes = document.querySelectorAll('.checkbox-material');
        const selecciones = [];

        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selecciones.push({
                    materialId: checkbox.dataset.materialId || '',
                    serialId: checkbox.dataset.serialId || '',
                    checkboxKey: checkbox.dataset.checkboxKey || ''
                });
            }
        });

        guardarSeleccionesEnStorage(bomId, selecciones);
        console.log('Selecciones guardadas para BOM:', bomId, 'Total:', selecciones.length);
    }

    function restaurarSelecciones(selecciones) {
        if (!selecciones || selecciones.length === 0) return;

        const checkboxes = document.querySelectorAll('.checkbox-material');
        const seleccionesKeys = selecciones.map(s => s.checkboxKey || `${s.materialId}_${s.serialId}`);

        checkboxes.forEach(checkbox => {
            const checkboxKey = checkbox.dataset.checkboxKey || '';
            if (seleccionesKeys.includes(checkboxKey)) {
                checkbox.checked = true;
            }
        });

        console.log('Selecciones restauradas:', selecciones.length, 'elementos');
    }

    /* =================== Init =================== */
    renderTabla();
    renderTablaMaterialesUrdido();
    renderTablaMaterialesEngomado();

    // Inicializar autocompletado después de renderizar la tabla
    setTimeout(() => {
        inicializarAutocompletadoBOM();
    }, 200);
});
</script>
@endsection

