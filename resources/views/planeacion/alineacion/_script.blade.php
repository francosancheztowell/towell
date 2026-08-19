<script>
    (function () {
        const CONFIG = {
            columnas: {!! json_encode($columnas) !!},
            columnLabels: @json($columnLabels ?? []),
            apiUrl: {!! json_encode(route('planeacion.alineacion.api.data')) !!},
        };
        const state = {
            data: @json($items),
            filtered: [],
            pinnedColumns: [],
            filters: [],
            selectedRowIndex: null,
        };
        state.filtered = [...state.data];

        const $ = (sel, ctx = document) => ctx.querySelector(sel);
        const $$ = (sel, ctx = document) => Array.from((ctx || document).querySelectorAll(sel));

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function applyFiltersToData() {
            if (!state.filters || state.filters.length === 0) {
                state.filtered = [...state.data];
                return;
            }
            const byColumn = {};
            state.filters.forEach(f => {
                if (!byColumn[f.column]) byColumn[f.column] = [];
                byColumn[f.column].push(String(f.value || '').toLowerCase().trim());
            });
            state.filtered = state.data.filter(row => {
                return Object.entries(byColumn).every(([col, values]) => {
                    const cellVal = row[col];
                    const str = (cellVal != null ? String(cellVal) : '').toLowerCase().trim();
                    return values.includes(str);
                });
            });
        }

        function getColumnElements(index) {
            return $$('#mainTable .column-' + index);
        }

        function updatePinnedPositions() {
            const table = $('#mainTable');
            if (!table) return;
            let left = 0;
            state.pinnedColumns.forEach(idx => {
                const els = getColumnElements(idx);
                const th = els.find(el => el.tagName === 'TH');
                if (!th) return;
                const w = th.offsetWidth || 80;
                els.forEach(el => {
                    el.classList.add('alineacion-pinned');
                    el.style.left = left + 'px';
                    el.style.position = 'sticky';
                    if (el.tagName === 'TH') el.style.top = '0';
                });
                left += w;
            });
            $$('#mainTable th[data-index], #mainTable td[data-index]').forEach(el => {
                const dataIndex = el.getAttribute('data-index');
                const idx = dataIndex !== null && dataIndex !== '' ? parseInt(dataIndex, 10) : NaN;
                if (Number.isNaN(idx) || !state.pinnedColumns.includes(idx)) {
                    el.classList.remove('alineacion-pinned');
                    el.style.left = '';
                    el.style.position = '';
                    el.style.top = '';
                }
            });
        }

        function updateColumnHeaderIcons() {
            CONFIG.columnas.forEach((col, idx) => {
                const thList = getColumnElements(idx).filter(el => el.tagName === 'TH');
                const th = thList[0];
                if (!th) return;
                const field = col;
                const container = th.querySelector('.alineacion-header-icons');
                if (!container) return;
                let html = '';
                const hasFilter = (state.filters || []).some(f => f.column === field);
                if (hasFilter) {
                    html += '<button type="button" class="alineacion-header-icon" data-action="clear-filter" data-column="' + escapeHtml(field) + '" title="Quitar filtro"><i class="fas fa-filter"></i></button>';
                }
                if (state.pinnedColumns.includes(idx)) {
                    html += '<button type="button" class="alineacion-header-icon" data-action="unpin" data-index="' + idx + '" title="Desfijar"><i class="fas fa-thumbtack"></i></button>';
                }
                container.innerHTML = html;
            });
        }

        function renderTable() {
            applyFiltersToData();
            const tbody = $('#alineacion-body');
            if (!tbody) return;

            const data = state.filtered;
            const totalCols = CONFIG.columnas.length;

            if (!data.length) {
                tbody.innerHTML =
                    '<tr><td colspan="' + totalCols + '" class="py-16 text-center text-gray-500">No hay datos para mostrar</td></tr>';
                return;
            }

            tbody.innerHTML = data.map((row, index) => {
                const selected = state.selectedRowIndex === index;
                const tieneParoActivo = !!row._tieneParoActivo;
                const isEven = index % 2 === 0;
                let baseClass;
                if (selected && tieneParoActivo) {
                    baseClass = 'alineacion-row-alerta alineacion-row-alerta-selected';
                } else if (selected) {
                    baseClass = 'alineacion-row-selected bg-blue-500 text-white hover:bg-blue-600';
                } else if (tieneParoActivo) {
                    baseClass = 'alineacion-row-alerta';
                } else {
                    baseClass = isEven ? 'bg-white hover:bg-gray-100' : 'bg-gray-50 hover:bg-gray-200';
                }
                const rowClass = 'alineacion-selectable-row cursor-pointer transition-colors ' + baseClass;
                const cellClass = (selected && !tieneParoActivo) ? 'px-3 py-1.5 border-b border-r border-blue-400 whitespace-nowrap text-sm text-white column-' : 'px-3 py-1.5 border-b border-r border-gray-200 whitespace-nowrap text-sm text-gray-700 column-';
                const cells = CONFIG.columnas.map((col, colIdx) => {
                    let value = row[col] ?? '';
                    let raw = value !== null && value !== '' ? String(value) : '';
                    // AnchoToalla (Med. Cen.) ya no se formatea: viene de CatCodificados.MedidaCenefa,
                    // texto con diagonales ("6/2") que parseFloat truncaria a "6.000".
                    if (col === 'PesoGRM2' && value !== '' && value != null && !isNaN(parseFloat(value))) {
                        raw = parseFloat(value).toFixed(3);
                    }
                    if (col === 'DiasPorEjecutar' && value !== '' && value != null && !isNaN(parseFloat(value))) {
                        raw = parseFloat(value).toFixed(2);
                    }
                    // "Días de prod." (DiasEficiencia) llega ya calculado desde el servidor
                    // (Carbon, a partir de FechaTejido) — no se recalcula en el cliente.
                    let cellContent = raw ? escapeHtml(raw) : '';
                    if (col === 'NoTelarId' && tieneParoActivo) {
                        cellContent = '<i class="fas fa-exclamation-triangle text-yellow-500 mr-1" title="Paro activo en mantenimiento"></i>' + cellContent;
                    }
                    return '<td class="' + cellClass + colIdx + '" data-column="' + escapeHtml(col) + '" data-index="' + colIdx + '" data-value="' + escapeHtml(raw) + '">' +
                        cellContent + '</td>';
                }).join('');
                return '<tr class="' + rowClass + '" data-row-index="' + index + '">' + cells + '</tr>';
            }).join('');

            updatePinnedPositions();
            updateColumnHeaderIcons();
        }

        function setSelectedRow(rowIndex) {
            if (state.selectedRowIndex === rowIndex) {
                state.selectedRowIndex = null;
            } else {
                state.selectedRowIndex = rowIndex;
            }
            renderTable();
        }

        /**
         * Refresca datos desde API (simula sockets). Se ejecuta cada 5 min.
         */
        let refreshEnCurso = false;
        async function refreshData() {
            if (refreshEnCurso) return;
            refreshEnCurso = true;
            try {
                const resp = await fetch(CONFIG.apiUrl, { headers: { 'Accept': 'application/json' } });
                const json = await resp.json();
                if (json.s && Array.isArray(json.items)) {
                    state.data = json.items;
                    state.selectedRowIndex = null;
                    applyFiltersToData();
                    renderTable();
                } else if (window.notify) {
                    notify.error(json.message || 'No se pudieron actualizar los datos de alineación.');
                }
            } catch (e) {
                console.warn('Alineación: error al refrescar datos', e);
                if (window.notify) notify.error('No se pudo conectar para actualizar los datos de alineación.');
            } finally {
                refreshEnCurso = false;
            }
        }

        // ----- Menú contextual en encabezados -----
        const menu = $('#alineacionContextMenuHeader');
        let menuColumnIndex = null;
        let menuColumnField = null;

        function hideContextMenu() {
            if (menu) {
                menu.classList.add('hidden');
                menu.style.display = 'none';
            }
            menuColumnIndex = null;
            menuColumnField = null;
        }

        function showContextMenu(e, columnIndex, columnField) {
            menuColumnIndex = columnIndex;
            menuColumnField = columnField;
            if (!menu) return;
            const fijarLabel = $('#alineacionCtxFijarLabel');
            if (fijarLabel) fijarLabel.textContent = state.pinnedColumns.includes(columnIndex) ? 'Desfijar' : 'Fijar';
            menu.style.left = e.clientX + 'px';
            menu.style.top = e.clientY + 'px';
            menu.style.display = 'block';
            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
            if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';
            menu.classList.remove('hidden');
        }

        function openFilterModal(columnIndex, columnField) {
            const columnLabel = CONFIG.columnLabels[columnField] || columnField;
            const valueCounts = new Map();
            state.filtered.forEach(row => {
                const v = row[columnField];
                const str = (v != null ? String(v) : '').trim();
                if (!valueCounts.has(str)) valueCounts.set(str, { raw: str, count: 0 });
                valueCounts.get(str).count++;
            });
            const uniqueValues = Array.from(valueCounts.keys()).filter(Boolean).sort();
            if (uniqueValues.length === 0) {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'info', title: 'Sin valores', text: 'No hay valores para filtrar en esta columna.' });
                return;
            }
            const currentForColumn = (state.filters || []).filter(f => f.column === columnField).map(f => f.value);

            let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapeHtml(columnLabel) + '</strong></p>';
            html += '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
            html += '<div class="mb-2 pb-2 border-b border-gray-200"><input type="text" id="alineacionFilterSearch" placeholder="Buscar..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></div>';
            html += '<div id="alineacionFilterCheckboxes" class="space-y-1">';
            uniqueValues.forEach(value => {
                const entry = valueCounts.get(value);
                const count = entry ? entry.count : 0;
                const checked = currentForColumn.includes(value) ? ' checked' : '';
                html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"><div class="flex items-center gap-2">';
                html += '<input type="checkbox" class="alineacion-filter-cb w-4 h-4 text-blue-600" value="' + escapeHtml(value) + '"' + checked + '>';
                html += '<span class="text-sm text-gray-700">' + escapeHtml(value) + '</span></div><span class="text-xs text-gray-500">(' + count + ')</span></label>';
            });
            html += '</div></div></div>';

            Swal.fire({
                title: 'Filtrar columna',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Aplicar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6',
                width: '500px',
                didOpen: () => {
                    const search = document.getElementById('alineacionFilterSearch');
                    const container = document.getElementById('alineacionFilterCheckboxes');
                    if (search && container) {
                        search.addEventListener('input', () => {
                            const term = search.value.toLowerCase();
                            container.querySelectorAll('label').forEach(lab => {
                                const text = (lab.textContent || '').toLowerCase();
                                lab.style.display = text.includes(term) ? '' : 'none';
                            });
                        });
                    }
                },
                preConfirm: () => {
                    const checked = $$('.alineacion-filter-cb:checked').map(cb => cb.value);
                    return checked;
                }
            }).then(result => {
                if (!result.isConfirmed) return;
                state.filters = (state.filters || []).filter(f => f.column !== columnField);
                (result.value || []).forEach(v => {
                    state.filters.push({ column: columnField, value: v });
                });
                renderTable();
            });
        }

        function getColumnLabel(idx) {
            const field = CONFIG.columnas[idx];
            return (CONFIG.columnLabels && CONFIG.columnLabels[field]) || field || ('Columna ' + idx);
        }

        function openPanelFijar() {
            const pinnedSet = new Set(state.pinnedColumns || []);
            let html = '<div class="text-left"><p class="text-sm text-gray-600 mb-3">Marca las columnas que quieres <strong>fijar</strong> (quedan a la izquierda al hacer scroll):</p>';
            html += '<div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">';
            CONFIG.columnas.forEach((_, idx) => {
                const label = escapeHtml(getColumnLabel(idx));
                const checked = pinnedSet.has(idx) ? ' checked' : '';
                html += '<label class="flex items-center gap-2 py-1.5 px-2 hover:bg-gray-50 rounded cursor-pointer alineacion-fijar-row">';
                html += '<input type="checkbox" class="alineacion-fijar-cb w-4 h-4 text-amber-600 rounded border-gray-300" data-index="' + idx + '"' + checked + '>';
                html += '<span class="text-sm text-gray-800">' + label + '</span></label>';
            });
            html += '</div></div>';
            Swal.fire({
                title: 'Fijar columnas',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Aplicar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                width: '380px',
                preConfirm: () => {
                    const checked = $$('.alineacion-fijar-cb:checked').map(cb => parseInt(cb.getAttribute('data-index'), 10)).filter(i => !Number.isNaN(i));
                    return checked;
                }
            }).then(result => {
                if (!result.isConfirmed) return;
                state.pinnedColumns = (result.value || []).slice().sort((a, b) => a - b);
                updatePinnedPositions();
                updateColumnHeaderIcons();
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderTable();

            $('#alineacionNavFijar')?.addEventListener('click', openPanelFijar);

            const tbody = $('#alineacion-body');
            if (tbody) {
                tbody.addEventListener('click', (e) => {
                    const tr = e.target.closest('tr.alineacion-selectable-row');
                    if (!tr) return;
                    const idx = parseInt(tr.getAttribute('data-row-index'), 10);
                    if (!Number.isNaN(idx)) setSelectedRow(idx);
                });
            }

            const thead = $('#mainTable thead');
            if (thead) {
                thead.addEventListener('contextmenu', (e) => {
                    const th = e.target.closest('th');
                    if (!th) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const columnIndex = parseInt(th.getAttribute('data-index'), 10);
                    const columnField = th.getAttribute('data-column');
                    if (Number.isNaN(columnIndex) || !columnField) return;
                    showContextMenu(e, columnIndex, columnField);
                });
            }

            document.addEventListener('click', (e) => {
                if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target)) hideContextMenu();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') hideContextMenu();
            });

            // Clic en iconos del encabezado: quitar filtro o desfijar
            const mainTable = $('#mainTable');
            if (mainTable) {
                mainTable.addEventListener('click', (e) => {
                    const btn = e.target.closest('.alineacion-header-icon');
                    if (!btn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const action = btn.getAttribute('data-action');
                    if (action === 'clear-filter') {
                        const field = btn.getAttribute('data-column');
                        if (field) {
                            state.filters = (state.filters || []).filter(f => f.column !== field);
                            renderTable();
                        }
                    } else if (action === 'unpin') {
                        const idx = parseInt(btn.getAttribute('data-index'), 10);
                        if (!Number.isNaN(idx)) {
                            const i = state.pinnedColumns.indexOf(idx);
                            if (i >= 0) {
                                state.pinnedColumns.splice(i, 1);
                                updatePinnedPositions();
                                updateColumnHeaderIcons();
                            }
                        }
                    }
                });
                mainTable.addEventListener('contextmenu', (e) => {
                    if (e.target.closest('.alineacion-header-icon')) e.stopPropagation();
                });
            }

            $('#alineacionCtxFiltrar')?.addEventListener('click', () => {
                const idx = menuColumnIndex;
                const field = menuColumnField;
                hideContextMenu();
                if (idx != null && field) openFilterModal(idx, field);
            });

            $('#alineacionCtxFijar')?.addEventListener('click', () => {
                const idx = menuColumnIndex;
                hideContextMenu();
                if (idx == null) return;
                const i = state.pinnedColumns.indexOf(idx);
                if (i >= 0) {
                    state.pinnedColumns.splice(i, 1);
                } else {
                    state.pinnedColumns.push(idx);
                    state.pinnedColumns.sort((a, b) => a - b);
                }
                updatePinnedPositions();
                updateColumnHeaderIcons();
            });

            // Refresco automático cada 5 min (simula sockets)
            setInterval(refreshData, 5 * 60 * 1000);
        });
    })();
</script>
