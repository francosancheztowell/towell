@extends('layouts.app')

@section('page-title', 'Marcas')

@section('navbar-right')
    <!-- Botones de acción para Marcas -->
    <div class="flex items-center gap-1 hidden">
        <button id="btn-nuevo" onclick="nuevaMarca()" class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors" title="Nuevo">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
        <button id="btn-editar" onclick="editarMarca()" class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors cursor-not-allowed" disabled title="Editar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </button>
        <button id="btn-finalizar" onclick="finalizarMarca()" class="p-2 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors cursor-not-allowed" disabled title="Finalizar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </button>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Info del Folio Activo -->
    <div id="folio-activo-info" class="bg-purple-50 border-l-4 border-purple-500 text-purple-900 p-3 mb-4 hidden">
        <div class="flex items-center space-x-3">
            <i class="fas fa-edit text-purple-500"></i>
            <span id="tipo-edicion" class="font-medium">Nueva Marca</span>
            <span class="text-purple-500">|</span>
            <span>Folio: <span id="folio-activo" class="font-bold"></span></span>
        </div>
    </div>

    <!-- Mensaje inicial (eliminado - se muestra directamente la tabla) -->
    <div id="mensaje-inicial" class="hidden"></div>

    <!-- Main Data Table Section - Compacta (Visible desde el inicio) -->
    <div id="segunda-tabla" class="bg-white shadow overflow-hidden mb-6 -mt-4" style="max-width: 100%;">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 80vh;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-purple-500 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">Telar</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">Salón</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider" style="position: sticky; top: 0; z-index: 30; background-color: #9333ea; min-width: 100px;">% Efi</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-purple-600" style="position: sticky; top: 0; z-index: 30; background-color: #7c3aed; min-width: 120px;">Marcas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-blue-400" style="position: sticky; top: 0; z-index: 30; background-color: #60a5fa; min-width: 100px;">Trama</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-green-400" style="position: sticky; top: 0; z-index: 30; background-color: #4ade80; min-width: 100px;">Pie</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-yellow-400" style="position: sticky; top: 0; z-index: 30; background-color: #fbbf24; min-width: 100px;">Rizo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase tracking-wider bg-red-400" style="position: sticky; top: 0; z-index: 30; background-color: #f87171; min-width: 100px;">Otros</th>
                        </tr>
                    </thead>
                    <tbody id="telares-body" class="bg-white divide-y divide-gray-200">
                        <!-- Telares (orden según InvSecuenciaMarcas) -->
                        @foreach($telares ?? [] as $telar)
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap border-r border-gray-200">{{ $telar->NoTelarId }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap text-center border-r border-gray-200">
                                <input type="text" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-50 text-gray-600 text-center cursor-not-allowed" value="{{ $telar->SalonId ?? '-' }}" data-telar="{{ $telar->NoTelarId }}" data-field="salon" readonly>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap text-center border-r border-gray-200">
                                <input type="text" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm bg-gray-50 text-gray-600 text-center cursor-not-allowed" placeholder="..." data-telar="{{ $telar->NoTelarId }}" data-field="porcentaje_efi" readonly>
                            </td>

                            <!-- Marcas (calculado automáticamente) -->
                            <td class="px-4 py-3 text-center border-r border-gray-200 bg-purple-50">
                                <div class="marcas-display font-bold text-lg text-purple-700" data-telar="{{ $telar->NoTelarId }}">0</div>
                            </td>

                            <!-- Trama -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-blue-50 hover:border-blue-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="trama">
                                        <span class="valor-display-text text-blue-600 font-semibold">0</span>
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-blue-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                        <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                            <div class="number-options-flex p-2 flex gap-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Pie -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-green-50 hover:border-green-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="pie">
                                        <span class="valor-display-text text-green-600 font-semibold">0</span>
                                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-green-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                        <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                            <div class="number-options-flex p-2 flex gap-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Rizo -->
                            <td class="px-2 py-2 border-r border-gray-200">
                                <div class="relative">
                                    <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-yellow-50 hover:border-yellow-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="rizo">
                                        <span class="valor-display-text text-yellow-600 font-semibold">0</span>
                                        <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-yellow-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                        <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                            <div class="number-options-flex p-2 flex gap-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Otros -->
                            <td class="px-2 py-2">
                                <div class="relative">
                                    <button type="button" class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900 hover:bg-red-50 hover:border-red-400 transition-colors flex items-center justify-between bg-white shadow-sm" data-telar="{{ $telar->NoTelarId }}" data-type="otros">
                                        <span class="valor-display-text text-red-600 font-semibold">0</span>
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-red-300 rounded-lg shadow-xl z-50" style="transform: translateX(-50%);">
                                        <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="max-width: 300px;">
                                            <div class="number-options-flex p-2 flex gap-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    /*
     * SISTEMA DE GUARDADO AUTOMÁTICO PARA MARCAS
     * ============================================
     * Los datos se guardan automáticamente 1 segundo después de cada cambio.
     * 
     * Flujo de guardado:
     * 1. Al crear una nueva marca (botón +), se genera un folio y se establece isNewRecord = true
     * 2. Cualquier cambio en la tabla (Trama, Pie, Rizo, Otros) dispara guardarAutomatico()
     * 3. guardarAutomatico() usa la ruta store que internamente usa updateOrCreate()
     * 4. Marcas se calcula automáticamente como: Trama + Pie + Rizo + Otros
     * 5. Después del primer guardado exitoso, isNewRecord cambia a false
     * 6. Si se presiona "Editar" en una marca existente, isNewRecord = false desde el inicio
     * 
     * No es necesario presionar ningún botón de guardar manualmente.
     */
    
    // Variables globales
    let currentFolio = null;
    let isEditing = false;
    let isNewRecord = true; // Controla si es un registro nuevo (CREATE) o existente (UPDATE)

    // Cache de elementos DOM
    const elements = {
        folio: null,
        fecha: null,
        turno: null,
        usuario: null,
        noEmpleado: null,
        status: null,
        segundaTabla: null,
        headerSection: null
    };

    // Inicializar cache de elementos
    function initElements() {
        elements.folio = document.getElementById('folio');
        elements.fecha = document.getElementById('fecha');
        elements.turno = document.getElementById('turno');
        elements.usuario = document.getElementById('usuario');
        elements.noEmpleado = document.getElementById('noEmpleado');
        elements.status = document.getElementById('status');
        elements.segundaTabla = document.getElementById('segunda-tabla');
        elements.headerSection = document.getElementById('header-section');
    }

    // Funciones para manejo de selectores de valores
    function toggleValorSelector(btn) {
        // Cerrar todos los otros selectores primero
        closeAllValorSelectors();
        
        const container = btn.parentElement;
        const selector = container.querySelector('.valor-edit-container');
        const telar = btn.getAttribute('data-telar');
        const tipo = btn.getAttribute('data-type');
        
        if (selector.classList.contains('hidden')) {
            // Obtener valor actual del display
            const currentText = btn.querySelector('.valor-display-text').textContent;
            const currentValue = parseInt(currentText) || 0;
            
            // Generar opciones dinámicamente
            generateNumberOptions(selector, tipo, currentValue);
            
            // Mostrar selector
            selector.classList.remove('hidden');
            
            // Scroll al valor actual
            scrollToCurrentValue(selector, currentValue);
        } else {
            // Ocultar selector
            selector.classList.add('hidden');
        }
    }

    function closeAllValorSelectors() {
        document.querySelectorAll('.valor-edit-container').forEach(container => {
            container.classList.add('hidden');
            
            // Opcional: limpiar opciones para liberar memoria
            const optionsContainer = container.querySelector('.number-options-flex');
            if (optionsContainer && optionsContainer.children.length > 100) {
                setTimeout(() => {
                    if (container.classList.contains('hidden')) {
                        optionsContainer.innerHTML = '';
                    }
                }, 5000); // Limpiar después de 5 segundos si sigue cerrado
            }
        });
    }

    function generateNumberOptions(selector, tipo, currentValue) {
        const optionsContainer = selector.querySelector('.number-options-flex');
        
        // Si ya tiene opciones, no regenerar (cache)
        if (optionsContainer.children.length > 0) {
            highlightCurrentOption(selector, currentValue);
            return;
        }
        
        // Rango de valores: 100 a 250 para marcas
        const minValue = 85;
        const maxValue = 250;
        const hoverClass = tipo === 'trama' ? 'hover:bg-blue-100' : 
                          tipo === 'pie' ? 'hover:bg-green-100' : 
                          tipo === 'rizo' ? 'hover:bg-yellow-100' : 'hover:bg-red-100';
        
        // Renderizado optimizado: solo crear opciones visibles inicialmente
        const viewportWidth = 300; // Ancho estimado del viewport del selector
        const optionWidth = 36; // w-8 + spacing
        const visibleOptions = Math.ceil(viewportWidth / optionWidth);
        const bufferOptions = 20; // Opciones extra para scroll suave
        
        // Calcular rango inicial basado en currentValue
        const startRange = Math.max(minValue, currentValue - Math.floor(visibleOptions / 2) - bufferOptions);
        const endRange = Math.min(maxValue + 1, startRange + visibleOptions + (bufferOptions * 2));
        
        const fragment = document.createDocumentFragment();
        
        // Crear opciones en el rango visible (de minValue a maxValue)
        for (let i = startRange; i < endRange; i++) {
            const option = document.createElement('span');
            option.className = `number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer ${hoverClass} rounded transition-colors bg-gray-100 text-gray-700`;
            option.setAttribute('data-value', i.toString());
            option.textContent = i.toString();
            
            // Highlight si es el valor actual
            if (i === currentValue) {
                option.classList.remove('bg-gray-100', 'text-gray-700');
                option.classList.add('bg-purple-500', 'text-white');
            }
            
            fragment.appendChild(option);
        }
        
        // Agregar placeholders para mantener el scroll correcto
        if (startRange > minValue) {
            const startPlaceholder = document.createElement('div');
            startPlaceholder.className = 'inline-block';
            startPlaceholder.style.width = `${(startRange - minValue) * optionWidth}px`;
            startPlaceholder.style.height = '32px';
            optionsContainer.appendChild(startPlaceholder);
        }
        
        optionsContainer.appendChild(fragment);
        
        if (endRange < maxValue + 1) {
            const endPlaceholder = document.createElement('div');
            endPlaceholder.className = 'inline-block';
            endPlaceholder.style.width = `${(maxValue + 1 - endRange) * optionWidth}px`;
            endPlaceholder.style.height = '32px';
            optionsContainer.appendChild(endPlaceholder);
        }
        
        // Configurar lazy loading para el resto de opciones si es necesario
        setupLazyOptionLoading(selector, tipo, maxValue, optionWidth, hoverClass);
    }

    function setupLazyOptionLoading(selector, tipo, maxValue, optionWidth, hoverClass) {
        const scrollContainer = selector.querySelector('.number-scroll-container');
        const optionsContainer = selector.querySelector('.number-options-flex');
        
        let isLoading = false;
        
        scrollContainer.addEventListener('scroll', () => {
            if (isLoading) return;
            
            const scrollLeft = scrollContainer.scrollLeft;
            const scrollWidth = scrollContainer.scrollWidth;
            const clientWidth = scrollContainer.clientWidth;
            
            // Si está cerca del final, cargar más opciones
            if (scrollLeft + clientWidth > scrollWidth - 100) {
                isLoading = true;
                
                // Generar más opciones si es necesario
                const currentOptions = optionsContainer.querySelectorAll('.number-option').length;
                if (currentOptions < maxValue + 1) {
                    const fragment = document.createDocumentFragment();
                    const start = currentOptions;
                    const end = Math.min(start + 50, maxValue + 1);
                    
                    for (let i = start; i < end; i++) {
                        const option = document.createElement('span');
                        option.className = `number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer ${hoverClass} rounded transition-colors bg-gray-100 text-gray-700`;
                        option.setAttribute('data-value', i.toString());
                        option.textContent = i.toString();
                        fragment.appendChild(option);
                    }
                    
                    optionsContainer.appendChild(fragment);
                }
                
                isLoading = false;
            }
        });
    }

    function selectNumberOption(option) {
        const value = parseInt(option.getAttribute('data-value'));
        const selector = option.closest('.valor-edit-container');
        const btn = selector.previousElementSibling;
        const displayText = btn.querySelector('.valor-display-text');
        const telar = btn.getAttribute('data-telar');
        const tipo = btn.getAttribute('data-type');
        
        // Actualizar el display
        displayText.textContent = value;
        
        // Cerrar selector
        selector.classList.add('hidden');
        
        // Actualizar marcas totales
        actualizarMarcasTotales(telar);
        
        // Guardar automáticamente
        guardarAutomatico();
    }

    function highlightCurrentOption(selector, value) {
        const options = selector.querySelectorAll('.number-option');
        options.forEach(opt => {
            if (parseInt(opt.getAttribute('data-value')) === value) {
                opt.classList.remove('bg-gray-100', 'text-gray-700');
                opt.classList.add('bg-purple-500', 'text-white');
            } else {
                opt.classList.remove('bg-purple-500', 'text-white');
                opt.classList.add('bg-gray-100', 'text-gray-700');
            }
        });
    }

    function scrollToCurrentValue(selector, value) {
        setTimeout(() => {
            const scrollContainer = selector.querySelector('.number-scroll-container');
            const option = selector.querySelector(`.number-option[data-value="${value}"]`);
            
            if (option && scrollContainer) {
                const optionOffset = option.offsetLeft;
                const containerWidth = scrollContainer.clientWidth;
                const optionWidth = option.offsetWidth;
                
                // Centrar la opción en el contenedor
                scrollContainer.scrollLeft = optionOffset - (containerWidth / 2) + (optionWidth / 2);
            }
        }, 10);
    }

    function actualizarMarcasTotales(telar) {
        // Obtener valores de cada columna para el telar
        const trama = parseInt(document.querySelector(`button[data-telar="${telar}"][data-type="trama"] .valor-display-text`)?.textContent) || 0;
        const pie = parseInt(document.querySelector(`button[data-telar="${telar}"][data-type="pie"] .valor-display-text`)?.textContent) || 0;
        const rizo = parseInt(document.querySelector(`button[data-telar="${telar}"][data-type="rizo"] .valor-display-text`)?.textContent) || 0;
        const otros = parseInt(document.querySelector(`button[data-telar="${telar}"][data-type="otros"] .valor-display-text`)?.textContent) || 0;
        
        // Calcular total de marcas
        const marcasTotales = trama + pie + rizo + otros;
        
        // Actualizar display de marcas totales
        const marcasDisplay = document.querySelector(`.marcas-display[data-telar="${telar}"]`);
        if (marcasDisplay) {
            marcasDisplay.textContent = marcasTotales;
        }
    }

    let guardarTimeout = null;
    function guardarAutomatico() {
        // Cancelar guardado previo pendiente
        if (guardarTimeout) {
            clearTimeout(guardarTimeout);
        }
        
        // Esperar 1 segundo antes de guardar
        guardarTimeout = setTimeout(() => {
            guardarDatosTabla();
        }, 1000);
    }

    function guardarDatosTabla() {
        if (!currentFolio) {
            console.error('No hay folio activo');
            return;
        }
        
        // Recopilar datos de la tabla
        const datos = [];
        document.querySelectorAll('#telares-body tr').forEach(row => {
            const telarCell = row.querySelector('td:first-child');
            if (!telarCell) return;
            
            const telar = telarCell.textContent.trim();
            const porcentajeEfi = row.querySelector(`input[data-telar="${telar}"][data-field="porcentaje_efi"]`)?.value || '';
            const trama = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="trama"] .valor-display-text`)?.textContent) || 0;
            const pie = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="pie"] .valor-display-text`)?.textContent) || 0;
            const rizo = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="rizo"] .valor-display-text`)?.textContent) || 0;
            const otros = parseInt(row.querySelector(`button[data-telar="${telar}"][data-type="otros"] .valor-display-text`)?.textContent) || 0;
            const marcas = trama + pie + rizo + otros;
            
            datos.push({
                NoTelarId: telar,
                PorcentajeEfi: porcentajeEfi,
                Trama: trama,
                Pie: pie,
                Rizo: rizo,
                Otros: otros,
                Marcas: marcas
            });
        });
        
        // Enviar al backend
        fetch('/modulo-marcas/store', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                folio: currentFolio,
                fecha: elements.fecha?.value,
                turno: elements.turno?.value,
                status: elements.status?.value,
                lineas: datos
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Datos guardados automáticamente');
                // Después del primer guardado exitoso, ya no es un registro nuevo
                if (isNewRecord) {
                    isNewRecord = false;
                }
            } else {
                console.error('Error al guardar:', data.message);
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
        });
    }

    function nuevaMarca() {
        // Generar nuevo folio
        generarNuevoFolio();
    }

    function generarNuevoFolio() {
        fetch('/modulo-marcas/generar-folio', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.folio) {
                currentFolio = data.folio;
                isNewRecord = true;
                isEditing = true;
                
                // Actualizar UI
                if (elements.folio) elements.folio.value = data.folio;
                if (elements.fecha) elements.fecha.value = new Date().toISOString().split('T')[0];
                if (elements.status) elements.status.value = 'En Proceso';
                if (elements.usuario) elements.usuario.value = data.usuario || '';
                if (elements.noEmpleado) elements.noEmpleado.value = data.numero_empleado || '';
                
                // Mostrar info del folio
                const folioInfo = document.getElementById('folio-activo-info');
                if (folioInfo) {
                    folioInfo.classList.remove('hidden');
                    document.getElementById('folio-activo').textContent = data.folio;
                }
                
                // Mostrar secciones
                if (elements.headerSection) elements.headerSection.style.display = 'block';
                
                console.log('Folio generado correctamente: ' + data.folio);
            }
        })
        .catch(error => {
            console.error('Error al generar folio:', error);
            Swal.fire('Error', 'No se pudo generar el folio', 'error');
        });
    }

    function cargarDatosSTD() {
        // Cargar Salón y %Efi desde InvSecuenciaMarcas y ReqProgramaTejido
        fetch('/modulo-marcas/obtener-datos-std', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.datos) {
                // Actualizar campos de Salón y %Efi
                data.datos.forEach(item => {
                    // Actualizar Salón
                    const salonInput = document.querySelector(`input[data-telar="${item.telar}"][data-field="salon"]`);
                    if (salonInput) {
                        salonInput.value = item.salon || '-';
                    }
                    
                    // Actualizar %Efi
                    const efiInput = document.querySelector(`input[data-telar="${item.telar}"][data-field="porcentaje_efi"]`);
                    if (efiInput) {
                        efiInput.value = item.porcentaje_efi ? item.porcentaje_efi + '%' : '-';
                    }
                });
                
                console.log('Datos STD cargados correctamente');
            }
        })
        .catch(error => {
            console.error('Error al cargar datos STD:', error);
        });
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        initElements();
        
        // Event delegation para botones de valor
        document.addEventListener('click', function(e) {
            if (e.target.closest('.valor-display-btn')) {
                const btn = e.target.closest('.valor-display-btn');
                toggleValorSelector(btn);
            } else if (e.target.classList.contains('number-option')) {
                selectNumberOption(e.target);
            } else if (!e.target.closest('.valor-edit-container')) {
                // Click fuera de selectores - cerrar todos
                closeAllValorSelectors();
            }
        });
        
        // Cambio en turno - guardar automáticamente
        if (elements.turno) {
            elements.turno.addEventListener('change', guardarAutomatico);
        }
        
        // Verificar si viene con parámetro folio en URL (modo edición)
        const urlParams = new URLSearchParams(window.location.search);
        const folioParam = urlParams.get('folio');
        if (folioParam) {
            cargarMarcaExistente(folioParam);
        } else {
            // Si no hay folio, generar uno nuevo automáticamente
            generarNuevoFolio();
        }
        
        // Cargar datos STD (salón y %Efi) al inicio
        cargarDatosSTD();
        
        // Guardar automáticamente cuando el usuario presiona atrás o cierra la página
        window.addEventListener('beforeunload', function(e) {
            if (currentFolio) {
                // Guardar datos antes de salir
                guardarDatosTabla();
            }
        });
        
        // Interceptar navegación hacia atrás
        window.addEventListener('popstate', function(e) {
            if (currentFolio) {
                guardarDatosTabla();
            }
        });
    });

    function cargarMarcaExistente(folio) {
        fetch(`/modulo-marcas/${folio}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentFolio = folio;
                isNewRecord = false;
                isEditing = true;
                
                // Cargar datos en UI
                if (elements.folio) elements.folio.value = data.marca.Folio;
                if (elements.fecha) elements.fecha.value = data.marca.Date;
                if (elements.turno) elements.turno.value = data.marca.Turno;
                if (elements.status) elements.status.value = data.marca.Status;
                
                // Mostrar info del folio
                const folioInfo = document.getElementById('folio-activo-info');
                if (folioInfo) {
                    folioInfo.classList.remove('hidden');
                    document.getElementById('folio-activo').textContent = folio;
                }
                
                // Mostrar secciones
                if (elements.headerSection) elements.headerSection.style.display = 'block';
                
                // Cargar líneas
                if (data.lineas) {
                    data.lineas.forEach(linea => {
                        // Actualizar valores en la tabla
                        const tramaBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="trama"] .valor-display-text`);
                        const pieBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="pie"] .valor-display-text`);
                        const rizoBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="rizo"] .valor-display-text`);
                        const otrosBtn = document.querySelector(`button[data-telar="${linea.NoTelarId}"][data-type="otros"] .valor-display-text`);
                        
                        if (tramaBtn) tramaBtn.textContent = linea.Trama || 0;
                        if (pieBtn) pieBtn.textContent = linea.Pie || 0;
                        if (rizoBtn) rizoBtn.textContent = linea.Rizo || 0;
                        if (otrosBtn) otrosBtn.textContent = linea.Otros || 0;
                        
                        // Actualizar marcas totales
                        actualizarMarcasTotales(linea.NoTelarId);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar marca:', error);
            Swal.fire('Error', 'No se pudo cargar la marca', 'error');
        });
    }
</script>

<style>
    /* Estilos para la tabla */
    table {
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Hover effect para las filas */
    tbody tr:hover {
        background-color: #faf5ff !important;
    }

    /* Estilos para los inputs en la tabla */
    tbody input {
        transition: border-color 0.2s ease;
    }

    tbody input:focus {
        border-color: #9333ea;
        box-shadow: 0 0 0 1px #9333ea;
        outline: none;
    }

    /* Estilos para headers sticky */
    thead th {
        position: sticky;
        top: 0;
        z-index: 30;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Estilos para el scroll */
    .overflow-x-auto::-webkit-scrollbar,
    .overflow-y-auto::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .overflow-x-auto::-webkit-scrollbar-track,
    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb,
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb:hover,
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Ocultar scrollbar pero mantener funcionalidad */
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    /* Estilos para los selectores de valores */
    .valor-display-btn {
        transition: all 0.2s ease;
        min-width: 80px;
    }

    .valor-display-btn:hover {
        transform: scale(1.02);
    }

    .valor-edit-container {
        z-index: 1000;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .number-option {
        transition: all 0.15s ease;
        flex-shrink: 0;
    }

    .number-option:hover {
        transform: scale(1.1);
    }

    .number-option.selected {
        background-color: #9333ea !important;
        color: white !important;
        transform: scale(1.1);
    }

    /* Animación suave para mostrar/ocultar selector */
    .valor-edit-container.hidden {
        opacity: 0;
        transform: translateX(-50%) translateY(-100%) scale(0.95);
        transition: all 0.2s ease;
    }

    .valor-edit-container:not(.hidden) {
        opacity: 1;
        transform: translateX(-50%) translateY(-100%) scale(1);
        transition: all 0.2s ease;
    }
    
    /* Estilos para el display de marcas totales */
    .marcas-display {
        transition: all 0.3s ease;
    }
    
    .marcas-display:not(:empty) {
        animation: pulse 0.5s ease-out;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
</style>
@endsection
