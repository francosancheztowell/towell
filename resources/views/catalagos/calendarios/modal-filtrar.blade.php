{{-- Modal para Filtrar por Columna --}}
<script>
    function filtrarPorColumna() {
        let filtrosActivosHTML = '';
        const totalFiltros = activeFiltersTab.length + activeFiltersLine.length;

        if (totalFiltros > 0) {
            const todos = [...activeFiltersTab, ...activeFiltersLine];

            filtrosActivosHTML = `
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                    <div class="space-y-1">
                        ${todos.map((filtro, index) => `
                            <div class="flex items-center justify-between bg-white p-2 rounded border">
                                <span class="text-xs">${filtro.columna}: ${filtro.valor}</span>
                                <button type="button"
                                    onclick="removeFilter(${index}, '${filtro.tabla}')"
                                    class="text-red-500 hover:text-red-700 text-xs">×</button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        Swal.fire({
            title: 'Filtrar por Columna',
            html: `
                ${filtrosActivosHTML}
                <div class="text-left space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tabla</label>
                        <select id="filtro-tabla"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="updateFilterColumns()">
                            <option value="tab">Calendarios (ReqCalendarioTab)</option>
                            <option value="line">Líneas de Calendario (ReqCalendarioLine)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                        <select id="filtro-columna"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="CalendarioId">No Calendario</option>
                            <option value="Nombre">Nombre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                        <input type="text" id="filtro-valor"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ingresa el valor a buscar">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" id="btn-agregar-otro"
                            class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                            + Agregar Otro Filtro
                        </button>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Agregar Filtro',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            width: '450px',
            preConfirm: () => {
                const tabla = document.getElementById('filtro-tabla').value;
                const columna = document.getElementById('filtro-columna').value;
                const valor = document.getElementById('filtro-valor').value.trim();

                if (!valor) {
                    Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                    return false;
                }

                const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                const existe = filtros.some(f => f.columna === columna && f.valor === valor);

                if (existe) {
                    Swal.showValidationMessage('Este filtro ya está activo');
                    return false;
                }

                return { tabla, columna, valor };
            },
            didOpen: () => {
                updateFilterColumns();

                document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                    const tabla = document.getElementById('filtro-tabla').value;
                    const columna = document.getElementById('filtro-columna').value;
                    const valor = document.getElementById('filtro-valor').value.trim();

                    if (!valor) {
                        Swal.showValidationMessage('Por favor ingresa un valor para filtrar');
                        return;
                    }

                    const filtros = tabla === 'tab' ? activeFiltersTab : activeFiltersLine;
                    const existe = filtros.some(f => f.columna === columna && f.valor === valor);

                    if (existe) {
                        Swal.showValidationMessage('Este filtro ya está activo');
                        return;
                    }

                    const filtro = { tabla, columna, valor };

                    if (tabla === 'tab') {
                        activeFiltersTab.push(filtro);
                    } else {
                        activeFiltersLine.push(filtro);
                    }

                    applyFilters();
                    showToast('Filtro agregado correctamente', 'success');
                    document.getElementById('filtro-valor').value = '';
                    updateFilterModal();
                });
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const filtro = { ...result.value };

            if (filtro.tabla === 'tab') {
                activeFiltersTab.push(filtro);
            } else {
                activeFiltersLine.push(filtro);
            }

            applyFilters();
            showToast('Filtro agregado correctamente', 'success');
        });
    }
</script>

