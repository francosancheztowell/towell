@extends('layouts.app')

@section('page-title', 'Creación de Órdenes')

@section('navbar-right')
<div class="flex items-center gap-1">
    <button id="btnReservarInventario" type="button" title="Reservar inventario"
        class="px-4 py-2.5 text-green-500 font-semibold hover:text-green-600 transition-all duration-200 flex items-center gap-2 group">
        <i class="fa-solid fa-boxes-stacked w-7 h-7 group-hover:scale-110 transition-transform duration-200"></i>
    </button>
    <button id="btnCrearOrdenes" type="button" title="Crear órdenes"
        class="px-4 py-2.5 text-blue-500 font-semibold hover:text-blue-600 transition-all duration-200 flex items-center gap-2 group">
        <i class="fa-solid fa-file-circle-plus w-7 h-7 group-hover:scale-110 transition-transform duration-200"></i>
    </button>
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
                            <th class="px-2 py-2 text-center text-xs font-semibold w-32">Configuración</th>
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

        grupos.forEach(grupo => {
            const tipoCls = (String(grupo.tipo||'').toUpperCase()==='RIZO')
                ? 'bg-rose-100 text-rose-700' : (String(grupo.tipo||'').toUpperCase()==='PIE'
                ? 'bg-teal-100 text-teal-700' : 'bg-gray-100 text-gray-700');

            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';
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
                           data-kilos="${grupo.kilos || 0}"
                           data-bom-input="true"
                           data-bom-id=""
                           autocomplete="off">
                </td>
            `;
            tbody.appendChild(tr);
        });
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
                        // Cargar materiales de urdido cuando se selecciona un BOM
                        const kilosProgramados = parseFloat(activeInput.dataset.kilos || 0);
                        cargarMaterialesUrdido(sugerencia.BOMID, kilosProgramados);
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
    async function cargarMaterialesUrdido(bomId, kilosProgramados = 0) {
        console.log('cargarMaterialesUrdido: BOM ID recibido', bomId, 'Kilos programados:', kilosProgramados);
        if (!bomId || bomId.trim() === '') {
            console.log('cargarMaterialesUrdido: BOM ID vacío');
            renderTablaMaterialesUrdido([], kilosProgramados);
            return;
        }

        try {
            const url = new URL('{{ route("programa.urd.eng.materiales.urdido") }}', window.location.origin);
            url.searchParams.set('bomId', bomId.trim());

            console.log('cargarMaterialesUrdido: URL', url.toString());

            const response = await fetch(url.toString());
            console.log('cargarMaterialesUrdido: Response status', response.status);

            if (!response.ok) {
                console.error('cargarMaterialesUrdido: Response no OK', response.status);
                renderTablaMaterialesUrdido([], kilosProgramados);
                return;
            }

            const data = await response.json();
            console.log('cargarMaterialesUrdido: Data recibida', data);
            console.log('cargarMaterialesUrdido: Cantidad de materiales', Array.isArray(data) ? data.length : 'no es array');
            renderTablaMaterialesUrdido(data, kilosProgramados);
        } catch (e) {
            console.error('Error al cargar materiales de urdido:', e);
            console.error('Error stack:', e.stack);
            renderTablaMaterialesUrdido([], kilosProgramados);
        }
    }

    /* =================== Render tablas de materiales =================== */
    function renderTablaMaterialesUrdido(materiales = [], kilosProgramados = 0) {
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
            renderTablaMaterialesEngomado([]);
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

        // Cargar materiales de engomado basados en los ItemIds de los materiales de urdido
        const itemIds = materiales.map(m => m.ItemId).filter(id => id && id !== '');
        console.log('renderTablaMaterialesUrdido: ItemIds extraídos', itemIds);
        if (itemIds.length > 0) {
            cargarMaterialesEngomado(itemIds);
        } else {
            console.log('renderTablaMaterialesUrdido: No hay ItemIds válidos');
            renderTablaMaterialesEngomado([]);
        }
    }

    /* =================== Cargar materiales de engomado =================== */
    async function cargarMaterialesEngomado(itemIds) {
        if (!itemIds || itemIds.length === 0) {
            console.log('cargarMaterialesEngomado: No hay ItemIds');
            renderTablaMaterialesEngomado([]);
            return;
        }

        console.log('cargarMaterialesEngomado: ItemIds recibidos', itemIds);

        try {
            // Construir URL con parámetros como query string
            const baseUrl = '{{ route("programa.urd.eng.materiales.engomado") }}';
            const params = new URLSearchParams();
            itemIds.forEach(itemId => {
                params.append('itemIds[]', itemId);
            });
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
            console.log('cargarMaterialesEngomado: Response headers', response.headers);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('cargarMaterialesEngomado: Error response', errorText);
                renderTablaMaterialesEngomado([]);
                return;
            }

            const data = await response.json();
            console.log('cargarMaterialesEngomado: Data recibida', data);
            console.log('cargarMaterialesEngomado: Cantidad de registros', Array.isArray(data) ? data.length : 'no es array');
            renderTablaMaterialesEngomado(data);
        } catch (e) {
            console.error('Error al cargar materiales de engomado:', e);
            console.error('Error stack:', e.stack);
            renderTablaMaterialesEngomado([]);
        }
    }

    function renderTablaMaterialesEngomado(materiales = []) {
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
            console.log(`renderTablaMaterialesEngomado: Material ${index}`, material);
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';

            // Formatear fecha de producción
            const prodDate = material.ProdDate ? formatDate(material.ProdDate) : '-';
            const kilos = parseFloat(material.PhysicalInvent || 0);
            const conos = parseFloat(material.TwTiras || 0);

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
                <td class="px-2 py-3 text-xs text-center">-</td>
                <td class="px-2 py-3 text-xs text-center">-</td>
                <td class="px-2 py-3 text-center">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" data-material-id="${material.ItemId || ''}" data-serial-id="${material.InventSerialId || ''}">
                </td>
            `;
            tbody.appendChild(tr);
        });

        console.log('renderTablaMaterialesEngomado: Tabla renderizada con', tbody.children.length, 'filas');
    }

    /* =================== Init =================== */
    renderTabla();
    renderTablaMaterialesUrdido();
    renderTablaMaterialesEngomado();

    // Inicializar autocompletado después de renderizar la tabla
    setTimeout(() => {
        inicializarAutocompletadoBOM();
    }, 100);
});
</script>
@endsection

