<script>
@include('modulos.programa-tejido.modal.duplicar-dividir')

  {!! view('modulos.programa-tejido.scripts.state', ['columns' => $columns ?? []])->render() !!}
  {!! view('modulos.programa-tejido.scripts.filters', ['columns' => $columns ?? []])->render() !!}
  {!! view('modulos.programa-tejido.scripts.columns', ['columns' => $columns ?? []])->render() !!}
  {!! view('modulos.programa-tejido.scripts.selection')->render() !!}
  {!! view('modulos.programa-tejido.scripts.inline-edit')->render() !!}

  (function () {
    window.PT = window.PT || {};
    const PT = window.PT;

    const qs  = (sel, ctx=document) => ctx.querySelector(sel);
    const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
    const tbodyEl = () => qs('#mainTable tbody');
    const setNavbarHeightVar = () => {
      const nav = document.querySelector('nav');
      if (!nav) return;
      document.documentElement.style.setProperty('--pt-navbar-height', `${nav.offsetHeight}px`);
    };

    const toast = (msg, type='info') => {
      if (typeof window.showToast === 'function') return window.showToast(msg, type);
    };

    // =========================
    // BOTÃ“N DRAG & DROP (GRIS CUANDO ACTIVO)
    // =========================
    const DD_ACTIVE_CLASSES = ['bg-gray-400','hover:bg-gray-500','text-white','opacity-80'];

    function findDragDropButton() {
      return (
        qs('#btnDragDrop') ||
        qs('#layoutBtnDragDrop') ||
        qs('#btn-dragdrop') ||
        qs('button[onclick*="toggleDragDropMode"]') ||
        qs('a[onclick*="toggleDragDropMode"]') ||
        qs('button[title*="Drag"]') ||
        qs('button[title*="Arrastr"]') ||
        qs('a[title*="Drag"]') ||
        qs('a[title*="Arrastr"]')
      );
    }

    function setDragDropButtonGray(isActive) {
      const btn = findDragDropButton();
      if (!btn) return;

      if (!btn.dataset.ddOrigClass) btn.dataset.ddOrigClass = btn.className;

      if (isActive) {
        btn.className = btn.dataset.ddOrigClass + ' ' + DD_ACTIVE_CLASSES.join(' ');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.className = btn.dataset.ddOrigClass;
        btn.setAttribute('aria-pressed', 'false');
      }
    }

    // =========================
    // Loader Ãºnico
    // =========================
    PT.loader = PT.loader || {
      show() {
        let loader = document.getElementById('priority-loader');
        if (!loader) {
          loader = document.createElement('div');
          loader.id = 'priority-loader';
          loader.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
          loader.innerHTML = '<div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);"><div style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.6s linear infinite;"></div><style>@keyframes spin{to{transform:rotate(360deg);}}</style></div>';
          document.body.appendChild(loader);
        } else {
          loader.style.display = 'flex';
        }
      },
      hide() {
        const loader = document.getElementById('priority-loader');
        if (loader) loader.style.display = 'none';
      }
    };

    // =========================
    // Cache por fila
    // =========================
    PT.rowCache = PT.rowCache || new WeakMap();

    function rowMeta(row) {
      if (!row) return { telar:'', salon:'', cambioHilo:'', enProceso:false };
      if (PT.rowCache.has(row)) return PT.rowCache.get(row);

      const telar = row.querySelector('[data-column="NoTelarId"]')?.textContent?.trim() ?? '';
      const salon = row.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim() ?? '';
      const cambioHilo = row.querySelector('[data-column="CambioHilo"]')?.textContent?.trim() ?? '';
      const posicionRaw =
        row.getAttribute('data-posicion') ??
        row.querySelector('[data-column="Posicion"]')?.textContent?.trim() ??
        '';
      const posicionParsed = parseInt(posicionRaw, 10);
      const posicion = Number.isFinite(posicionParsed) ? posicionParsed : null;
      const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
      const enProceso = !!enProcesoCell?.querySelector('input[type="checkbox"]')?.checked;

      const meta = { telar, salon, cambioHilo, posicion, enProceso };
      PT.rowCache.set(row, meta);
      return meta;
    }

    function clearRowCache() { PT.rowCache = new WeakMap(); }

    function normalizeTelarValue(value) {
      const str = String(value ?? '').trim();
      if (!str) return '';
      const num = Number(str);
      if (!Number.isNaN(num)) return String(num);
      return str.toUpperCase();
    }
    function isSameTelar(a, b) { return normalizeTelarValue(a) === normalizeTelarValue(b); }

    // =========================
    // Orden actual de filas
    // =========================
    function refreshAllRows() {
      const tb = tbodyEl();
      if (!tb) return [];
      window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      clearRowCache();
      // Si estamos en modo selecciÃ³n mÃºltiple, actualizar visualizaciÃ³n de filas bloqueadas
      if (window.multiSelectMode) {
        updateSelectedRowsVisual();
      }
      return window.allRows;
    }

    // =========================
    // Actualizar tabla despues de Drag & Drop
    // =========================
    const DD_NUM2_COLS = new Set([
      'DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2','StdToaHra','HorasProd','StdHrsEfect','DiasJornada','PesoGRM2',
      'TotalPedido','LargoCrudo','Luchaje','PesoCrudo'
    ]);
    const DD_DATE_TIME_COLS = new Set(['FechaInicio','FechaFinal','EntregaCte']);
    const DD_DATE_ONLY_COLS = new Set(['EntregaProduc','EntregaPT','ProgramarProd','Programado']);

    function ddFormatDateTime(raw) {
      if (typeof formatDateTimeDisplay === 'function') return formatDateTimeDisplay(raw);
      return raw ? String(raw) : '';
    }

    function ddFormatDateOnly(raw) {
      if (typeof formatDateOnlyDisplay === 'function') return formatDateOnlyDisplay(raw);
      if (typeof formatDateDisplay === 'function') return formatDateDisplay(raw);
      return raw ? String(raw) : '';
    }

    function ddFormatNumber(raw) {
      if (typeof formatNumber2 === 'function') return formatNumber2(raw);
      const n = Number(raw);
      if (!Number.isFinite(n)) return raw == null ? '' : String(raw);
      return n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ddSetCellValue(cell, display, rawValue) {
      if (!cell) return;
      cell.innerHTML = display ?? '';
      if (rawValue === null || rawValue === undefined) {
        delete cell.dataset.value;
      } else {
        cell.dataset.value = String(rawValue);
      }
    }

    function ddFormatCell(column, raw) {
      if (column === 'EnProceso') {
        const checked = (raw == 1 || raw === true) ? 'checked' : '';
        return {
          display: `<input type="checkbox" ${checked} disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">`,
          rawValue: raw ?? 0
        };
      }

      if (column === 'Ultimo') {
        const sv = String(raw ?? '').trim().toUpperCase();
        const isUltimo = (sv === 'UL' || sv === '1');
        return { display: isUltimo ? '<strong>ULTIMO</strong>' : '', rawValue: raw ?? '' };
      }

      if (column === 'CambioHilo') {
        const isZero = (raw == 0 || raw === '0' || raw === null || raw === undefined);
        return { display: isZero ? '' : String(raw), rawValue: raw ?? '' };
      }

      if (column === 'EficienciaSTD') {
        const n = Number(raw);
        if (!Number.isFinite(n)) return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
        return { display: `${Math.round(n * 100)}%`, rawValue: raw };
      }

      if (DD_DATE_TIME_COLS.has(column)) {
        return { display: raw ? ddFormatDateTime(raw) : '', rawValue: raw ?? '' };
      }

      if (DD_DATE_ONLY_COLS.has(column) || column.startsWith('Entrega')) {
        return { display: raw ? ddFormatDateOnly(raw) : '', rawValue: raw ?? '' };
      }

      if (DD_NUM2_COLS.has(column)) {
        return { display: ddFormatNumber(raw), rawValue: raw ?? '' };
      }

      if (typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[column]?.displayFormatter) {
        return {
          display: uiInlineEditableFields[column].displayFormatter(raw),
          rawValue: raw ?? ''
        };
      }

      return { display: raw == null ? '' : String(raw), rawValue: raw ?? '' };
    }

    function ddApplyUpdatesToRow(row, updates) {
      if (!row || !updates) return;

      if (updates.Posicion !== undefined && updates.Posicion !== null) {
        row.setAttribute('data-posicion', String(updates.Posicion));
      }

      Object.entries(updates).forEach(([column, raw]) => {
        const td = row.querySelector(`td[data-column="${column}"]`);
        if (!td) return;

        const fmt = ddFormatCell(column, raw);
        ddSetCellValue(td, fmt.display, fmt.rawValue);
      });
    }

    function ddReorderRows() {
      const tb = tbodyEl();
      if (!tb) return;

      const rows = Array.from(tb.querySelectorAll('.selectable-row'));
      rows.sort((a, b) => {
        const salonA = (a.querySelector('[data-column="SalonTejidoId"]')?.textContent || '').trim().toUpperCase();
        const salonB = (b.querySelector('[data-column="SalonTejidoId"]')?.textContent || '').trim().toUpperCase();
        if (salonA !== salonB) return salonA.localeCompare(salonB);

        const telarA = normalizeTelarValue(a.querySelector('[data-column="NoTelarId"]')?.textContent || '');
        const telarB = normalizeTelarValue(b.querySelector('[data-column="NoTelarId"]')?.textContent || '');
        if (telarA !== telarB) return telarA.localeCompare(telarB, undefined, { numeric: true, sensitivity: 'base' });

        const posA = parseInt(a.getAttribute('data-posicion') || '0', 10);
        const posB = parseInt(b.getAttribute('data-posicion') || '0', 10);
        return (Number.isFinite(posA) ? posA : 0) - (Number.isFinite(posB) ? posB : 0);
      });

      const frag = document.createDocumentFragment();
      rows.forEach(r => frag.appendChild(r));
      tb.innerHTML = '';
      tb.appendChild(frag);

      refreshAllRows();
    }

    function ddSelectRowById(registroId) {
      if (!registroId || typeof window.selectRow !== 'function') return;
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
      const row = rows.find(r => r.getAttribute('data-id') == registroId);
      if (!row) return;
      const idx = rows.indexOf(row);
      if (idx >= 0) window.selectRow(row, idx);
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    window.updateTableAfterDragDrop = function updateTableAfterDragDrop(detalles, registroId, updates = {}) {
      const tb = tbodyEl();
      if (!tb) return;

      const updatesById = (updates && typeof updates === 'object' && !Array.isArray(updates)) ? { ...updates } : {};
      const detallesList = Array.isArray(detalles) ? detalles : [];

      detallesList.forEach((detalle) => {
        const idKey = String(detalle?.Id ?? detalle?.id ?? '');
        if (!idKey) return;
        const fallback = {};
        if (detalle.NoTelar !== undefined) fallback.NoTelarId = detalle.NoTelar;
        if (detalle.Posicion !== undefined) fallback.Posicion = detalle.Posicion;
        if (detalle.FechaInicio_nueva !== undefined) fallback.FechaInicio = detalle.FechaInicio_nueva;
        if (detalle.FechaFinal_nueva !== undefined) fallback.FechaFinal = detalle.FechaFinal_nueva;
        if (detalle.EnProceso_nuevo !== undefined) fallback.EnProceso = detalle.EnProceso_nuevo;
        if (detalle.Ultimo_nuevo !== undefined) fallback.Ultimo = detalle.Ultimo_nuevo;
        if (detalle.CambioHilo_nuevo !== undefined) fallback.CambioHilo = detalle.CambioHilo_nuevo;
        if (detalle.HorasProd_calc !== undefined) fallback.HorasProd = detalle.HorasProd_calc;

        updatesById[idKey] = { ...fallback, ...(updatesById[idKey] || {}) };
      });

      const ids = Object.keys(updatesById);
      ids.forEach((idKey) => {
        const row = tb.querySelector(`tr.selectable-row[data-id="${idKey}"]`);
        if (!row) return;

        // Aplicar todos los updates
        ddApplyUpdatesToRow(row, updatesById[idKey]);

        // Si se actualizó SalonTejidoId o NoTelarId, reconstruir Maquina
        // Esto asegura que Maquina se actualice visualmente incluso si el backend ya lo calculó
        if (updatesById[idKey].SalonTejidoId !== undefined || updatesById[idKey].NoTelarId !== undefined) {
          // Si Maquina viene en los updates, usarlo directamente
          if (updatesById[idKey].Maquina !== undefined) {
            const maquinaTd = row.querySelector(`td[data-column="Maquina"]`);
            if (maquinaTd) {
              ddSetCellValue(maquinaTd, updatesById[idKey].Maquina, updatesById[idKey].Maquina);
            }
          } else if (typeof window.construirMaquinaRow === 'function') {
            // Si no viene Maquina en los updates, construirlo basándose en salón y telar
            window.construirMaquinaRow(row);
            const maquinaTd = row.querySelector(`td[data-column="Maquina"]`);
            if (maquinaTd && row.dataset.maquina) {
              ddSetCellValue(maquinaTd, row.dataset.maquina, row.dataset.maquina);
            }
          }
        }
      });

      clearRowCache();
      ddReorderRows();
      if (window.dragDropMode) {
        window.allRows.forEach((row) => {
          const meta = rowMeta(row);
          row.draggable = !meta.enProceso;
          row.classList.toggle('cursor-move', !meta.enProceso);
          row.classList.toggle('cursor-not-allowed', meta.enProceso);
          row.style.opacity = meta.enProceso ? '0.6' : '';
        });
      }

      if (registroId) ddSelectRowById(registroId);
      if (typeof window.updateTotales === 'function') window.updateTotales();
    };

    // =========================
    // Context menu
    // =========================
    PT.contextMenu = PT.contextMenu || (function(){
      const menu = qs('#contextMenu');
      if (!menu) return null;

      let menuRow = null;

      function hide() {
        menu.classList.add('hidden');
        menuRow = null;
      }

      function show(e, row) {
        // Cerrar el menú de encabezados si está abierto
        if (PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
          PT.contextMenuHeader.hide();
        }

        menuRow = row;
        menu.style.left = e.clientX + 'px';
        menu.style.top  = e.clientY + 'px';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth)  menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

        // Verificar si el registro está en proceso para ocultar el botón de eliminar
        const eliminarBtn = qs('#contextMenuEliminar');
        if (eliminarBtn && row) {
          const meta = rowMeta(row);
          const enProceso = meta.enProceso;

          // Ocultar el botón de eliminar si EnProceso === 1
          if (enProceso) {
            eliminarBtn.style.display = 'none';
          } else {
            eliminarBtn.style.display = '';
          }
        }

        // Mostrar/ocultar el botón de desvincular según si el registro tiene OrdCompartida
        const desvincularBtn = qs('#contextMenuDesvincular');
        if (desvincularBtn && row) {
          const ordCompartida = row.getAttribute('data-ord-compartida');
          // Ocultar el botón de desvincular si no tiene OrdCompartida
          if (!ordCompartida || ordCompartida.trim() === '') {
            desvincularBtn.style.display = 'none';
          } else {
            desvincularBtn.style.display = '';
          }
        }

        menu.classList.remove('hidden');
      }

      if (!menu.dataset.bound) {
        menu.dataset.bound = '1';

        document.addEventListener('click', (e) => {
          if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            hide();
          }
          // También cerrar el menú de encabezados si está abierto
          if (PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
            const headerMenu = qs('#contextMenuHeader');
            if (headerMenu && !headerMenu.classList.contains('hidden')) {
              PT.contextMenuHeader.hide();
            }
          }
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            hide();
          }
          // También cerrar el menú de encabezados con Escape
          if (e.key === 'Escape' && PT.contextMenuHeader && typeof PT.contextMenuHeader.hide === 'function') {
            PT.contextMenuHeader.hide();
          }
        });

        // Ocultar menÃº cuando cambia la selecciÃ³n de fila
        document.addEventListener('pt:selection-changed', () => {
          if (!menu.classList.contains('hidden')) {
            hide();
          }
        });

        const tb = tbodyEl();
        if (tb) {
          tb.addEventListener('contextmenu', (e) => {
            // No mostrar menú de filas si se hace click en un encabezado
            if (e.target.closest('th')) return;

            const clickedRow = e.target.closest('.selectable-row');
            if (!clickedRow) return;

            e.preventDefault();

            // Obtener todas las filas para encontrar el índice
            const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
            const clickedRowIndex = rows.indexOf(clickedRow);

            // Si la fila clickeada no está seleccionada, seleccionarla primero
            if (window.selectedRowIndex !== clickedRowIndex) {
              if (typeof window.selectRow === 'function') {
                window.selectRow(clickedRow, clickedRowIndex);
              }
            }

            // Usar la fila clickeada para el menú contextual
            show(e, clickedRow);
          });
        }

        qs('#contextMenuCrear')?.addEventListener('click', () => {
          if (menuRow && typeof window.duplicarTelar === 'function') {
            window.duplicarTelar(menuRow);
          }
          hide();
        });

        qs('#contextMenuEditar')?.addEventListener('click', () => {
          hide();
          if (typeof window.editarFilaSeleccionada === 'function') window.editarFilaSeleccionada();
          else toast('EdiciÃ³n inline no disponible', 'info');
        });

        // Abrir catÃ¡logo de CodificaciÃ³n en nueva ventana
        qs('#contextMenuCodificacion')?.addEventListener('click', () => {
          hide();
          window.open('{{ route("planeacion.codificacion.index") }}', '_blank');
        });

        // Abrir catÃ¡logo de CodificaciÃ³n de Modelos en nueva ventana
        qs('#contextMenuModelos')?.addEventListener('click', () => {
          hide();
          window.open('{{ route("planeacion.catalogos.codificacion-modelos") }}', '_blank');
        });

        // Eliminar registro
        qs('#contextMenuEliminar')?.addEventListener('click', () => {
          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
          const selectedRow = (window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0)
            ? rows[window.selectedRowIndex]
            : null;
          const row = menuRow || selectedRow;
          hide();
          if (row) {
            const id = row.getAttribute('data-id');
            if (id && typeof window.eliminarRegistro === 'function') {
              window.eliminarRegistro(id);
            } else {
              toast('No se pudo obtener el ID del registro', 'error');
            }
          } else {
            toast('No hay registro seleccionado', 'error');
          }
        });

        // Desvincular registro
        qs('#contextMenuDesvincular')?.addEventListener('click', () => {
          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tbodyEl());
          const selectedRow = (window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0)
            ? rows[window.selectedRowIndex]
            : null;
          const row = menuRow || selectedRow;
          hide();
          if (row) {
            const id = row.getAttribute('data-id');
            if (id && typeof window.desvincularRegistro === 'function') {
              window.desvincularRegistro(id);
            } else {
              toast('No se pudo obtener el ID del registro', 'error');
            }
          } else {
            toast('No hay registro seleccionado', 'error');
          }
        });
      }

      return { show, hide, getRow: () => menuRow };
    })();

    // =========================
    // Helper para escapar valores CSS
    // =========================
    function escapeCSSValue(value) {
      if (typeof value !== 'string') {
        value = String(value);
      }
      try {
        if (typeof CSS !== 'undefined' && CSS.escape) {
          return CSS.escape(value);
        }
      } catch (e) {
        // Fallback si CSS.escape falla
      }
      // Escape manual para atributos HTML y selectores CSS
      return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
    }

    // =========================
    // Context menu para encabezados
    // =========================
    PT.contextMenuHeader = PT.contextMenuHeader || (function(){
      const menu = qs('#contextMenuHeader');
      if (!menu) return null;

      let menuColumnIndex = null;
      let menuColumnField = null;

      function hide() {
        menu.classList.add('hidden');
        menuColumnIndex = null;
        menuColumnField = null;
      }

      function show(e, columnIndex, columnField) {
        // Cerrar el menú de filas si está abierto
        if (PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
          PT.contextMenu.hide();
        }

        menuColumnIndex = columnIndex;
        menuColumnField = columnField;
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

        menu.classList.remove('hidden');
      }

      if (!menu.dataset.bound) {
        menu.dataset.bound = '1';

        document.addEventListener('click', (e) => {
          if (!menu.classList.contains('hidden') && !menu.contains(e.target)) {
            hide();
          }
          // También cerrar el menú de filas si está abierto
          if (PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
            const rowMenu = qs('#contextMenu');
            if (rowMenu && !rowMenu.classList.contains('hidden')) {
              PT.contextMenu.hide();
            }
          }
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            hide();
          }
          // También cerrar el menú de filas con Escape
          if (e.key === 'Escape' && PT.contextMenu && typeof PT.contextMenu.hide === 'function') {
            PT.contextMenu.hide();
          }
        });

        // Agregar listener de contextmenu en los encabezados
        const thead = qs('#mainTable thead');
        if (thead) {
          thead.addEventListener('contextmenu', (e) => {
            const th = e.target.closest('th');
            if (!th) return;

            e.preventDefault();
            e.stopPropagation();

            // Intentar obtener el índice de varias formas
            let columnIndex = parseInt(th.dataset.index, 10);
            if (Number.isNaN(columnIndex)) {
              // Intentar desde el atributo data-index directamente
              const dataIndex = th.getAttribute('data-index');
              if (dataIndex) {
                columnIndex = parseInt(dataIndex, 10);
              }
            }

            // Si aún no tenemos el índice, intentar desde la clase
            if (Number.isNaN(columnIndex)) {
              const classMatch = th.className.match(/column-(\d+)/);
              if (classMatch) {
                columnIndex = parseInt(classMatch[1], 10);
              }
            }

            let columnField = th.dataset.column;
            if (!columnField) {
              columnField = th.getAttribute('data-column');
            }

            if (Number.isNaN(columnIndex) || !columnField) {
              console.error('[contextMenuHeader] No se pudo obtener índice o campo:', {
                index: columnIndex,
                field: columnField,
                th: th,
                dataset: th.dataset
              });
              return;
            }

            show(e, columnIndex, columnField);
          });
        }

        // Filtrar
        qs('#contextMenuHeaderFiltrar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          const savedField = menuColumnField;
          hide();

          if (savedIndex !== null && savedIndex >= 0 && savedField) {
            openFilterModal(savedIndex, savedField);
          } else {
            console.error('[contextMenuHeader] Filtrar - valores inválidos:', {
              index: savedIndex,
              field: savedField
            });
            toast('No se pudo obtener información de la columna', 'error');
          }
        });

        // Fijar
        qs('#contextMenuHeaderFijar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          hide();

          if (savedIndex !== null && savedIndex >= 0) {
            if (typeof window.togglePinColumn === 'function') {
              window.togglePinColumn(savedIndex);
            } else if (typeof window.pinColumn === 'function') {
              window.pinColumn(savedIndex);
            } else {
              toast('Función de fijar columna no disponible', 'error');
              return;
            }
            // Actualizar iconos después de fijar/desfijar
            setTimeout(() => {
              if (typeof window.updateColumnPinIcons === 'function') {
                window.updateColumnPinIcons();
              }
            }, 100);
          } else {
            console.error('[contextMenuHeader] Fijar - índice inválido:', savedIndex);
            toast('No se pudo obtener el índice de la columna', 'error');
          }
        });

        // Ocultar
        qs('#contextMenuHeaderOcultar')?.addEventListener('click', () => {
          // Guardar valores antes de ocultar
          const savedIndex = menuColumnIndex;
          hide();

          if (savedIndex !== null && savedIndex >= 0) {
            if (typeof window.hideColumn === 'function') {
              window.hideColumn(savedIndex);
            } else {
              toast('Función hideColumn no disponible', 'error');
            }
          } else {
            console.error('[contextMenuHeader] Ocultar - índice inválido:', savedIndex);
            toast('No se pudo obtener el índice de la columna', 'error');
          }
        });
      }

      return {
        show: show,
        hide: hide,
        getColumn: function() {
          return { index: menuColumnIndex, field: menuColumnField };
        }
      };
    })();

    // =========================
    // Función para abrir modal de filtro
    // =========================
    function openFilterModal(columnIndex, columnField) {
      // Validar parámetros
      if (columnIndex === null || columnIndex === undefined || columnIndex < 0) {
        console.error('[openFilterModal] Índice de columna inválido:', columnIndex);
        toast('No se pudo obtener el índice de la columna', 'error');
        return;
      }

      if (!columnField || typeof columnField !== 'string') {
        console.error('[openFilterModal] Campo de columna inválido:', columnField);
        toast('No se pudo obtener el campo de la columna', 'error');
        return;
      }

      // Obtener el label de la columna desde el encabezado si está disponible
      let columnLabel = columnField;
      const thead = qs('#mainTable thead');
      if (thead) {
        const th = thead.querySelector(`th[data-index="${columnIndex}"], th.column-${columnIndex}`);
        if (th) {
          const labelText = th.textContent || th.innerText || '';
          if (labelText.trim()) {
            columnLabel = labelText.trim();
          }
        }
      }

      // Obtener todos los valores únicos de la columna
      const tb = tbodyEl();
      if (!tb) {
        toast('No se pudo acceder a los datos de la tabla', 'error');
        return;
      }

      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
      const values = new Set();
      const valueCounts = new Map();

      rows.forEach(row => {
        const escapedField = escapeCSSValue(columnField);
        const selector = '[data-column="' + escapedField + '"]';
        const cell = row.querySelector(selector);
        if (cell) {
          // IMPORTANTE:
          // - data-value: valor crudo para comparación (usado en el filtro)
          // - textContent: valor formateado para mostrar (más legible)
          const rawValue = cell.dataset.value || '';
          const displayValue = (cell.textContent || cell.innerText || '').trim();

          // Usar el valor crudo (data-value) para la comparación del filtro
          // Si no hay data-value, usar textContent como fallback
          const valueForFilter = rawValue || displayValue;

          if (valueForFilter && String(valueForFilter).trim()) {
            const valueStr = String(valueForFilter).trim();
            // Usar el valor crudo como clave para agrupar
            if (!valueCounts.has(valueStr)) {
              values.add(valueStr);
              valueCounts.set(valueStr, {
                rawValue: valueStr,
                displayValue: displayValue || valueStr,
                count: 0
              });
            }
            // Incrementar contador
            const entry = valueCounts.get(valueStr);
            entry.count = (entry.count || 0) + 1;
            valueCounts.set(valueStr, entry);
          }
        }
      });

      const uniqueValues = Array.from(values).sort();

      if (uniqueValues.length === 0) {
        toast('No hay valores para filtrar en esta columna', 'info');
        return;
      }

      // Crear HTML del modal con checkboxes
      const escapedLabel = String(columnLabel).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      let html = '<div class="text-left">' +
        '<p class="text-sm text-gray-600 mb-4">Filtrar por: <strong>' + escapedLabel + '</strong></p>' +
        '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-2">' +
        '<div class="mb-2 pb-2 border-b border-gray-200">' +
        '<input type="text" id="filterSearchInput" placeholder="Buscar..." ' +
        'class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">' +
        '</div>' +
        '<div id="filterCheckboxesContainer" class="space-y-1">';

      uniqueValues.forEach(value => {
        const entry = valueCounts.get(value);
        const count = entry ? entry.count : 0;
        const displayValue = entry ? entry.displayValue : value;

        // Usar escape HTML estándar para el atributo data-value y el value del checkbox
        const escapedValueAttr = String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const escapedDisplayValue = String(displayValue).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        html += '<label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer filter-checkbox-item" data-value="' + escapedValueAttr + '">' +
          '<div class="flex items-center gap-2">' +
          '<input type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 filter-checkbox" ' +
          'value="' + escapedValueAttr + '">' +
          '<span class="text-sm text-gray-700">' + escapedDisplayValue + '</span>' +
          '</div>' +
          '<span class="text-xs text-gray-500">(' + count + ')</span>' +
          '</label>';
      });

      html += '</div></div></div>';

      Swal.fire({
        title: 'Filtrar Columna',
        html: html,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        width: '500px',
        didOpen: () => {
          // Restaurar estado de checkboxes si hay filtros activos para esta columna
          // Usar la variable global 'filters' directamente
          const currentFilters = (typeof filters !== 'undefined' ? filters : window.filters) || [];
          const activeFiltersForColumn = currentFilters.filter(f => f.column === columnField);
          if (activeFiltersForColumn.length > 0) {
            activeFiltersForColumn.forEach(filter => {
              const filterValue = String(filter.value || '').trim();
              const escapedValueAttr = filterValue.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
              const label = Array.from(document.querySelectorAll('.filter-checkbox-item')).find(l => {
                return l.dataset.value === filterValue || l.dataset.value === escapedValueAttr;
              });
              if (label) {
                const checkbox = label.querySelector('.filter-checkbox');
                if (checkbox) checkbox.checked = true;
              }
            });
          }

          // Búsqueda en tiempo real
          const searchInput = document.getElementById('filterSearchInput');
          const container = document.getElementById('filterCheckboxesContainer');
          const items = container.querySelectorAll('.filter-checkbox-item');

          searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            items.forEach(item => {
              const value = item.dataset.value || '';
              const text = item.textContent || '';
              if (value.toLowerCase().includes(searchTerm) || text.toLowerCase().includes(searchTerm)) {
                item.style.display = '';
              } else {
                item.style.display = 'none';
              }
            });
          });

          // Seleccionar/Deseleccionar todos
          const selectAllBtn = document.createElement('button');
          selectAllBtn.type = 'button';
          selectAllBtn.className = 'text-xs text-blue-600 hover:text-blue-800 mt-2';
          selectAllBtn.textContent = 'Seleccionar todos';
          selectAllBtn.addEventListener('click', () => {
            container.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = true);
          });
          container.parentElement.insertBefore(selectAllBtn, container);

          const deselectAllBtn = document.createElement('button');
          deselectAllBtn.type = 'button';
          deselectAllBtn.className = 'text-xs text-red-600 hover:text-red-800 ml-2 mt-2';
          deselectAllBtn.textContent = 'Deseleccionar todos';
          deselectAllBtn.addEventListener('click', () => {
            container.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
          });
          container.parentElement.insertBefore(deselectAllBtn, container);
        },
        preConfirm: () => {
          // ⚡ FIX: Obtener el valor desde data-value en lugar de value para evitar problemas con CSS.escape()
          const checked = Array.from(document.querySelectorAll('.filter-checkbox:checked')).map(cb => {
            // Siempre obtener el valor desde el data-value del label padre para evitar problemas con escape
            const label = cb.closest('label.filter-checkbox-item');
            if (label && label.dataset.value !== undefined) {
              // El data-value ya está en el formato correcto (sin escape de CSS)
              return label.dataset.value;
            }
            // Fallback: usar el value del checkbox directamente
            return cb.value;
          });
          return checked;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;

        const selectedValues = result.value || [];

        // Aplicar filtro
        if (typeof window.applyColumnFilter === 'function') {
          window.applyColumnFilter(columnField, selectedValues);
        } else {
          // Fallback: aplicar filtro manualmente
          applyColumnFilterManual(columnField, selectedValues);
        }
      });
    }

    // Función auxiliar para aplicar filtro manualmente
    function applyColumnFilterManual(columnField, selectedValues) {
      if (!Array.isArray(selectedValues) || selectedValues.length === 0) {
        // Remover filtro
        // Usar la variable global 'filters' directamente
        if (typeof filters !== 'undefined') {
          filters = filters.filter(f => f.column !== columnField);
          window.filters = filters;
        } else if (window.filters) {
          window.filters = window.filters.filter(f => f.column !== columnField);
          filters = window.filters;
        }
        // Aplicar filtros usando el sistema existente para actualizar correctamente
        if (typeof window.applyProgramaTejidoFilters === 'function') {
          window.applyProgramaTejidoFilters();
        } else if (typeof applyProgramaTejidoFilters === 'function') {
          applyProgramaTejidoFilters();
        } else if (typeof window.applyFilters === 'function') {
          window.applyFilters();
        } else if (typeof applyFilters === 'function') {
          applyFilters();
        } else {
          // Fallback: mostrar todas las filas
          const tb = tbodyEl();
          if (tb) {
            qsa('.selectable-row', tb).forEach(row => {
              row.style.display = '';
              row.classList.remove('filter-hidden');
            });
          }
        }
        // Actualizar iconos después de remover filtro
        setTimeout(() => {
          if (typeof window.updateColumnFilterIcons === 'function') {
            window.updateColumnFilterIcons();
          }
        }, 100);
        if (typeof window.updateTotales === 'function') window.updateTotales();
        toast('Filtro removido', 'info');
        return;
      }

      // Agregar/actualizar filtros en el formato del sistema existente
      // El sistema de filtros usa formato: { column: string, value: string, operator: string }
      // IMPORTANTE: filters se declara en state.blade.php como 'let filters = []', no window.filters
      // Usar la variable global 'filters' directamente
      if (typeof filters === 'undefined') {
        window.filters = window.filters || [];
        filters = window.filters;
      }
      if (!filters) filters = [];

      // Remover filtros anteriores de esta columna
      filters = filters.filter(f => f.column !== columnField);

      // Agregar nuevos filtros para cada valor seleccionado
      // IMPORTANTE: Los valores vienen del modal tal cual están en data-value
      // Usar 'equals' para coincidencia exacta (normalizado a lowercase para comparación)
      selectedValues.forEach(value => {
        if (value && String(value).trim()) {
          // Guardar el valor original (sin normalizar) para que coincida con data-value
          const originalValue = String(value).trim();
          filters.push({
            column: columnField,
            value: originalValue, // Guardar valor original, se normalizará en la comparación
            operator: 'equals' // Coincidencia exacta del valor seleccionado
          });
        }
      });

      console.log('[applyColumnFilterManual] Filtros aplicados:', {
        column: columnField,
        selectedValues: selectedValues,
        filters: filters.filter(f => f.column === columnField)
      });

      // Asegurar que window.filters también esté actualizado
      window.filters = filters;

      // Aplicar filtros usando el sistema existente
      // SIEMPRE usar applyProgramaTejidoFilters - es la función correcta del sistema
      if (typeof window.applyProgramaTejidoFilters === 'function') {
        window.applyProgramaTejidoFilters();
      } else if (typeof applyProgramaTejidoFilters === 'function') {
        applyProgramaTejidoFilters();
      } else {
        console.error('[applyColumnFilterManual] applyProgramaTejidoFilters no disponible!');
        // Si no está disponible, usar fallback manual (no debería llegar aquí)
        // Fallback: aplicar manualmente
        const tb = tbodyEl();
        if (!tb) return;

        // Usar la misma lógica que applyFilters en filters.blade.php
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);

        // Agrupar filtros por columna para permitir múltiples valores en la misma columna
        // Lógica: OR entre valores de la misma columna, AND entre diferentes columnas
        // IMPORTANTE: Usar exactamente la misma lógica que filters.blade.php
        const filtersByColumn = {};
        const currentFilters = (typeof filters !== 'undefined' ? filters : window.filters) || [];
        if (currentFilters.length > 0) {
          currentFilters.forEach(f => {
            const col = f.column;
            if (!filtersByColumn[col]) {
              filtersByColumn[col] = [];
            }
            filtersByColumn[col].push({
              value: String(f.value || '').toLowerCase(),
              operator: f.operator || 'equals', // El sistema usa 'contains' por defecto, pero nosotros usamos 'equals'
            });
          });
        }

        // Función para verificar un valor contra un filtro (igual que en filters.blade.php)
        const checkFilterMatch = (cellValue, filter) => {
          const filterValue = String(filter.value || '').toLowerCase();
          switch (filter.operator) {
            case 'equals':   return cellValue === filterValue;
            case 'starts':   return cellValue.startsWith(filterValue);
            case 'ends':     return cellValue.endsWith(filterValue);
            case 'not':      return !cellValue.includes(filterValue);
            case 'empty':    return cellValue === '';
            case 'notEmpty': return cellValue !== '';
            default:         return cellValue.includes(filterValue);
          }
        };

        rows.forEach(row => {
          // Verificar filtros personalizados con lógica OR por columna, AND entre columnas
          let matchesCustom = true;
          if (Object.keys(filtersByColumn).length > 0) {
            // Para cada columna con filtros, al menos uno debe coincidir (OR)
            // Todas las columnas deben tener al menos una coincidencia (AND entre columnas)
            matchesCustom = Object.entries(filtersByColumn).every(([column, columnFilters]) => {
              // Usar el selector sin escape para que funcione igual que filters.blade.php
              const cell = row.querySelector(`[data-column="${column}"]`);
              if (!cell) return false;

              // Usar data-value primero, luego textContent (igual que filters.blade.php)
              // IMPORTANTE: Normalizar exactamente igual que en filters.blade.php
              const rawCellValue = cell.dataset.value || cell.textContent || '';
              const cellValue = String(rawCellValue).toLowerCase().trim();

              // OR: al menos un filtro de esta columna debe coincidir
              const matches = columnFilters.some(filter => {
                const result = checkFilterMatch(cellValue, filter);
                // Debug solo para la primera columna filtrada
                if (column === columnField && !result) {
                  console.log('[applyColumnFilterManual] No match:', {
                    column: column,
                    cellValue: cellValue,
                    filterValue: filter.value,
                    operator: filter.operator
                  });
                }
                return result;
              });
              return matches;
            });
          }

          // Aplicar visibilidad según si cumple todos los filtros
          if (matchesCustom) {
            row.style.display = '';
            row.classList.remove('filter-hidden');
          } else {
            row.style.display = 'none';
            row.classList.add('filter-hidden');
          }
        });
      }

      // Actualizar iconos después de aplicar filtros
      setTimeout(() => {
        if (typeof window.updateColumnFilterIcons === 'function') {
          window.updateColumnFilterIcons();
        }
      }, 100);

      if (typeof window.updateTotales === 'function') window.updateTotales();
      toast(`Filtro aplicado: ${selectedValues.length} valor(es) seleccionado(s)`, 'success');
    }

    // Función para actualizar iconos de filtro en encabezados
    function updateColumnFilterIcons() {
      const thead = qs('#mainTable thead');
      if (!thead) return;

      // Obtener todas las columnas con filtros activos
      const filteredColumns = new Set();
      const currentFilters = (typeof filters !== 'undefined' ? filters : window.filters) || [];
      if (Array.isArray(currentFilters)) {
        currentFilters.forEach(f => {
          if (f.column) {
            filteredColumns.add(f.column);
          }
        });
      }

      // Recorrer todos los encabezados
      const allHeaders = thead.querySelectorAll('th[data-column]');
      allHeaders.forEach(th => {
        const columnField = th.dataset.column || th.getAttribute('data-column');
        if (!columnField) return;

        // Buscar o crear el icono de filtro
        let filterIcon = th.querySelector('.column-filter-icon');

        if (filteredColumns.has(columnField)) {
          // La columna tiene filtros activos - mostrar icono
          if (!filterIcon) {
            filterIcon = document.createElement('i');
            filterIcon.className = 'fas fa-filter column-filter-icon text-yellow-400 ml-1 text-xs cursor-pointer hover:text-yellow-500';
            filterIcon.title = 'Columna filtrada - Click para quitar filtro';
            filterIcon.style.cursor = 'pointer';

            // Agregar event listener para eliminar filtro al hacer clic
            filterIcon.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();

              // Eliminar filtros de esta columna
              if (typeof filters !== 'undefined') {
                filters = filters.filter(f => f.column !== columnField);
                window.filters = filters;
              } else if (window.filters) {
                window.filters = window.filters.filter(f => f.column !== columnField);
                filters = window.filters;
              }

              // Aplicar filtros actualizados
              if (typeof window.applyProgramaTejidoFilters === 'function') {
                window.applyProgramaTejidoFilters();
              } else if (typeof applyProgramaTejidoFilters === 'function') {
                applyProgramaTejidoFilters();
              }

              // Actualizar iconos después de eliminar filtro
              setTimeout(() => {
                if (typeof window.updateColumnFilterIcons === 'function') {
                  window.updateColumnFilterIcons();
                }
              }, 100);

              if (typeof window.updateTotales === 'function') window.updateTotales();
              toast('Filtro removido de la columna', 'info');
            });

            th.appendChild(filterIcon);
          }
          filterIcon.style.display = 'inline-block';
        } else {
          // La columna no tiene filtros - ocultar icono
          if (filterIcon) {
            filterIcon.style.display = 'none';
          }
        }
      });
    }

    // Función para actualizar iconos de columnas fijadas en encabezados
    function updateColumnPinIcons() {
      const thead = qs('#mainTable thead');
      if (!thead) return;

      // Obtener todas las columnas fijadas
      const pinnedIndices = new Set();
      if (typeof window.getPinnedColumns === 'function') {
        const pinned = window.getPinnedColumns();
        if (Array.isArray(pinned)) {
          pinned.forEach(idx => pinnedIndices.add(idx));
        }
      } else if (window.pinnedColumns && Array.isArray(window.pinnedColumns)) {
        window.pinnedColumns.forEach(idx => pinnedIndices.add(idx));
      }

      // Recorrer todos los encabezados
      const allHeaders = thead.querySelectorAll('th[data-index], th[class*="column-"]');
      allHeaders.forEach(th => {
        // Obtener el índice de la columna
        let columnIndex = null;
        if (th.dataset.index) {
          columnIndex = parseInt(th.dataset.index, 10);
        } else {
          const classMatch = th.className.match(/column-(\d+)/);
          if (classMatch) {
            columnIndex = parseInt(classMatch[1], 10);
          }
        }

        if (columnIndex === null || Number.isNaN(columnIndex)) return;

        // Buscar o crear el icono de fijado
        let pinIcon = th.querySelector('.column-pin-icon');

        if (pinnedIndices.has(columnIndex)) {
          // La columna está fijada - mostrar icono blanco
          if (!pinIcon) {
            pinIcon = document.createElement('i');
            pinIcon.className = 'fas fa-thumbtack column-pin-icon text-white ml-1 text-xs';
            pinIcon.title = 'Columna fijada';
            th.appendChild(pinIcon);
          }
          pinIcon.style.display = 'inline-block';
        } else {
          // La columna no está fijada - ocultar icono
          if (pinIcon) {
            pinIcon.style.display = 'none';
          }
        }
      });
    }

    // Interceptar updatePinnedColumnsPositions para actualizar iconos
    const originalUpdatePinnedColumnsPositions = window.updatePinnedColumnsPositions;
    if (typeof originalUpdatePinnedColumnsPositions === 'function') {
      window.updatePinnedColumnsPositions = function() {
        const result = originalUpdatePinnedColumnsPositions.apply(this, arguments);
        // Actualizar iconos después de actualizar posiciones
        setTimeout(() => {
          if (typeof window.updateColumnPinIcons === 'function') {
            window.updateColumnPinIcons();
          }
        }, 50);
        return result;
      };
    }

    // Exponer función globalmente
    window.applyColumnFilter = applyColumnFilterManual;
    window.updateColumnFilterIcons = updateColumnFilterIcons;
    window.updateColumnPinIcons = updateColumnPinIcons;

    // =========================
    // Acciones
    // =========================
    PT.actions = PT.actions || {};

    PT.actions.descargarPrograma = function descargarPrograma() {
      Swal.fire({
        title: 'Descargar Programa',
        html: `
          <div class="text-left">
            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicial:</label>
            <input type="date" id="fechaInicial"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Descargar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        didOpen: () => {
          const hoy = new Date().toISOString().split('T')[0];
          qs('#fechaInicial').value = hoy;
          qs('#fechaInicial').focus();
        },
        preConfirm: () => {
          const fechaInicial = qs('#fechaInicial').value;
          if (!fechaInicial) {
            Swal.showValidationMessage('Por favor seleccione una fecha inicial');
            return false;
          }
          return fechaInicial;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;

        PT.loader.show();
        fetch('/planeacion/programa-tejido/descargar-programa', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ fecha_inicial: result.value })
        })
        .then(r => r.json())
        .then(data => {
          PT.loader.hide();
          if (data.success) toast('Programa descargado correctamente', 'success');
          else toast(data.message || 'Error al descargar el programa', 'error');
        })
        .catch(() => { PT.loader.hide(); toast('Ocurrió un error al procesar la solicitud', 'error'); });
      });
    };

    PT.actions.abrirNuevo = function abrirNuevo() {
      // Funcionalidad de nuevo eliminada - ahora se usa duplicar/vincular/dividir
      console.warn('La funcionalidad de nuevo registro ha sido reemplazada por duplicar/vincular/dividir');
    };

    PT.actions.eliminarRegistro = function eliminarRegistro(id) {
      const doDelete = () => {
        PT.loader.show();
        fetch(`/planeacion/programa-tejido/${id}`, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
          }
        })
        .then(r => r.json())
        .then(data => {
          PT.loader.hide();
          if (data.success) {
            // Buscar la fila por su data-id
            const tb = tbodyEl();
            const rowToDelete = tb ? tb.querySelector(`tr.selectable-row[data-id="${id}"]`) : null;

            if (rowToDelete) {
              // Verificar si la fila eliminada está seleccionada antes de eliminarla
              const selectedRowId = window.selectedRowIndex !== null && window.selectedRowIndex !== undefined && window.selectedRowIndex >= 0
                ? (window.allRows && window.allRows[window.selectedRowIndex]
                    ? window.allRows[window.selectedRowIndex].getAttribute('data-id')
                    : null)
                : null;

              const isSelected = selectedRowId === id;

              // Verificar si el registro eliminado tenía Ultimo=1 antes de eliminarlo
              const ultimoCell = rowToDelete.querySelector('[data-column="Ultimo"]');
              const tieneUltimo = ultimoCell && (
                ultimoCell.textContent.includes('ULTIMO') ||
                ultimoCell.querySelector('strong') ||
                ultimoCell.getAttribute('data-value') === '1' ||
                ultimoCell.getAttribute('data-value') === 'UL'
              );
              const salonId = rowToDelete.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim();
              const telarId = rowToDelete.querySelector('[data-column="NoTelarId"]')?.textContent?.trim();

              // Obtener el ID del registro eliminado para comparar
              const registroIdEliminado = rowToDelete.getAttribute('data-id');

              // Eliminar la fila del DOM
              rowToDelete.remove();

              // Si el registro eliminado tenía Ultimo=1, buscar el último registro del mismo telar y actualizarlo
              if (tieneUltimo && salonId && telarId) {
                // Usar requestAnimationFrame para asegurar que el DOM se actualice
                requestAnimationFrame(() => {
                  const tb = tbodyEl();
                  if (!tb) return;

                  // Obtener todas las filas del mismo telar (después de eliminar)
                  const filasMismoTelar = Array.from(tb.querySelectorAll('.selectable-row')).filter(f => {
                    const rowId = f.getAttribute('data-id');
                    const fSalon = f.querySelector('[data-column="SalonTejidoId"]')?.textContent?.trim();
                    const fTelar = f.querySelector('[data-column="NoTelarId"]')?.textContent?.trim();
                    return rowId !== registroIdEliminado &&
                      fSalon === salonId &&
                      fTelar === telarId;
                  });

                  if (filasMismoTelar.length > 0) {
                    // Ordenar por FechaFinal primero, luego por FechaInicio (descendente) para encontrar el último
                    filasMismoTelar.sort((a, b) => {
                      // Intentar primero con FechaFinal, luego con FechaInicio
                      const fechaFinalA = a.querySelector('[data-column="FechaFinal"]')?.textContent?.trim() || '';
                      const fechaFinalB = b.querySelector('[data-column="FechaFinal"]')?.textContent?.trim() || '';
                      const fechaInicioA = a.querySelector('[data-column="FechaInicio"]')?.textContent?.trim() || '';
                      const fechaInicioB = b.querySelector('[data-column="FechaInicio"]')?.textContent?.trim() || '';

                      const fechaA = fechaFinalA || fechaInicioA;
                      const fechaB = fechaFinalB || fechaInicioB;

                      if (!fechaA && !fechaB) return 0;
                      if (!fechaA) return 1;
                      if (!fechaB) return -1;

                      try {
                        // Intentar parsear fecha con formato d/m/Y o d/m/Y H:i
                        let partesA = fechaA.split(' ');
                        let partesB = fechaB.split(' ');
                        let fechaSoloA = partesA[0];
                        let fechaSoloB = partesB[0];

                        const datePartsA = fechaSoloA.split('/');
                        const datePartsB = fechaSoloB.split('/');

                        if (datePartsA.length === 3 && datePartsB.length === 3) {
                          const dateA = new Date(datePartsA[2], datePartsA[1] - 1, datePartsA[0]);
                          const dateB = new Date(datePartsB[2], datePartsB[1] - 1, datePartsB[0]);

                          // Comparar por fecha completa si hay hora
                          if (partesA.length > 1 && partesB.length > 1) {
                            const horaA = partesA[1].split(':');
                            const horaB = partesB[1].split(':');
                            if (horaA.length >= 2 && horaB.length >= 2) {
                              dateA.setHours(parseInt(horaA[0]) || 0, parseInt(horaA[1]) || 0);
                              dateB.setHours(parseInt(horaB[0]) || 0, parseInt(horaB[1]) || 0);
                            }
                          }

                          return dateB.getTime() - dateA.getTime(); // Orden descendente (el más reciente primero)
                        }
                      } catch (e) {
                        // Si hay error, mantener orden original
                        console.warn('Error al parsear fecha para ordenar:', e);
                      }
                      return 0;
                    });

                    // La primera fila después de ordenar es la última (más reciente)
                    const nuevoUltimo = filasMismoTelar[0];
                    if (nuevoUltimo) {
                      const nuevoUltimoCell = nuevoUltimo.querySelector('[data-column="Ultimo"]');
                      if (nuevoUltimoCell) {
                        // Actualizar visualmente el campo Ultimo
                        nuevoUltimoCell.innerHTML = '<strong>ULTIMO</strong>';
                        nuevoUltimoCell.setAttribute('data-value', '1');

                        // Forzar repintado del navegador
                        nuevoUltimoCell.style.visibility = 'hidden';
                        nuevoUltimoCell.offsetHeight; // Trigger reflow
                        nuevoUltimoCell.style.visibility = 'visible';
                      }
                    }
                  }
                });
              }

              // Actualizar window.allRows removiendo la fila eliminada y actualizar índices
              window.allRows = Array.from(tb.querySelectorAll('.selectable-row'));

              // Actualizar data-row-index de todas las filas restantes
              window.allRows.forEach((fila, index) => {
                fila.setAttribute('data-row-index', index);
              });

              // Limpiar la selección si la fila eliminada estaba seleccionada
              if (isSelected) {
                window.selectedRowIndex = null;

                // También limpiar cualquier referencia visual de selección
                if (tb) {
                  const selectedRows = tb.querySelectorAll('.selectable-row.selected, .selectable-row.bg-blue-200');
                  selectedRows.forEach(row => {
                    row.classList.remove('selected', 'bg-blue-200');
                  });
                }
              }

              // Limpiar de selectedRowsIds si existe
              if (window.selectedRowsIds && typeof window.selectedRowsIds.delete === 'function') {
                window.selectedRowsIds.delete(id);
              }

              // Actualizar refreshAllRows para sincronizar el estado (esto también actualizará window.allRows)
              if (typeof refreshAllRows === 'function') {
                refreshAllRows();
              }

              // Actualizar los totales después de actualizar las filas
              if (typeof window.updateTotales === 'function') {
                window.updateTotales();
              }

              // Mostrar mensaje de éxito
              toast('Registro eliminado correctamente', 'success');
            } else {
              // Si no se encuentra la fila en el DOM, mostrar mensaje de éxito de todos modos
              toast('Registro eliminado correctamente', 'success');

              // Actualizar window.allRows por si acaso
              if (typeof refreshAllRows === 'function') {
                refreshAllRows();
              }
            }
          } else {
            toast(data.message || 'No se pudo eliminar el registro', 'error');
          }
        })
        .catch(() => { PT.loader.hide(); toast('Ocurrió un error al procesar la solicitud', 'error'); });
      };

      if (typeof Swal === 'undefined') {
        if (confirm('¿Eliminar registro? Esta acción no se puede deshacer.')) doDelete();
        return;
      }

      Swal.fire({
        title: '¿Eliminar registro?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
      }).then(r => { if (r.isConfirmed) doDelete(); });
    };

    window.descargarPrograma = PT.actions.descargarPrograma;
    window.abrirNuevo = PT.actions.abrirNuevo;
    window.eliminarRegistro = PT.actions.eliminarRegistro;

    // =========================
    // Desvincular registro
    // =========================
    window.desvincularRegistro = async function desvincularRegistro(id) {
      const doDesvincular = async () => {
        PT.loader.show();
        try {
          const response = await fetch(`/planeacion/programa-tejido/${id}/desvincular`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            }
          });

          const data = await response.json();
          PT.loader.hide();

          if (data.success) {
            // Actualizar registros sin recargar usando la misma función que para vincular
            if (data.registros_ids && Array.isArray(data.registros_ids) && data.registros_ids.length > 0) {
              await actualizarRegistrosVinculados(data.registros_ids, null);
            }

            toast(data.message || 'Registro desvinculado correctamente', 'success');
          } else {
            toast(data.message || 'No se pudo desvincular el registro', 'error');
          }
        } catch (error) {
          PT.loader.hide();
          toast('Ocurrió un error al procesar la solicitud', 'error');
        }
      };

      if (typeof Swal === 'undefined') {
        if (confirm('¿Desvincular este registro? Se eliminará su relación con otros registros.')) {
          doDesvincular();
        }
        return;
      }

      Swal.fire({
        title: '¿Desvincular registro?',
        text: 'Se eliminará la relación con otros registros vinculados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desvincular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#9333ea',
        cancelButtonColor: '#6b7280',
      }).then(r => { if (r.isConfirmed) doDesvincular(); });
    };

    // =========================
    // Editar fila seleccionada
    // =========================
    window.editarFilaSeleccionada = function() {
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
      if (window.selectedRowIndex === null || window.selectedRowIndex === undefined || window.selectedRowIndex < 0) {
        toast('Por favor, selecciona un registro primero', 'info');
        return;
      }

      const row = rows[window.selectedRowIndex];
      if (!row) {
        toast('No se pudo encontrar el registro seleccionado', 'error');
        return;
      }

      // Activar ediciÃ³n inline
      if (typeof window.toggleInlineEditMode === 'function') {
        // Verificar si el modo inline ya estÃ¡ activado mirando la clase en el tbody
        const tb = qs('#mainTable tbody');
        const isActive = tb && tb.classList.contains('inline-edit-mode');

        // Si no estÃ¡ activado, activarlo
        if (!isActive) {
          window.toggleInlineEditMode();
        }

        // Activar ediciÃ³n en todas las celdas editables de esta fila
        if (typeof window.enableInlineEditForAllCellsInRow === 'function') {
          window.enableInlineEditForAllCellsInRow(row);
        } else {
          // Fallback: activar manualmente cada celda editable
          const editableCells = row.querySelectorAll('td[data-column]');
          editableCells.forEach(cell => {
            const col = cell.getAttribute('data-column');
            if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
              if (typeof window.enableInlineEditForCell === 'function') {
                window.enableInlineEditForCell(cell);
              }
            }
          });
        }

        if (typeof window.showToast === 'function') {
          window.showToast('Edición activada en la fila seleccionada', 'success');
        }
      } else {
        toast('EdiciÃ³n inline no disponible', 'error');
      }
    };

    // =========================
    // Drag & Drop
    // =========================
    PT.dragdrop = PT.dragdrop || (function(){
      const state = {
        enabled: false,
        draggedRow: null,
        origin: { telar:'', salon:'', cambioHilo:'', posicion: null },
        lastOverRow: null,
        lastOverTelar: null,
        lastOverIsBefore: false,
        lastDragOverTime: 0,
        blockedReason: null,
        telarCache: { telar: null, rows: null, indexMap: null, lastEnProceso: -1 },
      };

      function resetTelarCache() {
        state.telarCache = { telar: null, rows: null, indexMap: null, lastEnProceso: -1 };
      }

      function buildTelarCache(telarId) {
        const tb = tbodyEl();
        if (!tb) return { rows: [], indexMap: new Map(), lastEnProceso: -1 };

        const norm = normalizeTelarValue(telarId);
        if (state.telarCache.telar === norm && state.telarCache.rows && state.telarCache.indexMap) {
          return state.telarCache;
        }

        const rows = [];
        const indexMap = new Map();
        let lastEnProceso = -1;

        for (const r of tb.querySelectorAll('.selectable-row')) {
          if (r === state.draggedRow) continue;
          const meta = rowMeta(r);
          if (!isSameTelar(meta.telar, telarId)) continue;
          if (meta.enProceso) lastEnProceso = rows.length;
          indexMap.set(r, rows.length);
          rows.push(r);
        }

        state.telarCache = { telar: norm, rows, indexMap, lastEnProceso };
        return state.telarCache;
      }

      function calcTargetPosition(telarId, targetRow, isBefore) {
        const cache = buildTelarCache(telarId);
        if (!targetRow) return cache.rows.length;

        const targetPos = rowMeta(targetRow).posicion;
        if (Number.isFinite(targetPos)) {
          let newPos = isBefore ? (targetPos - 1) : targetPos;
          const originPos = state.origin.posicion;
          if (isSameTelar(telarId, state.origin.telar) && Number.isFinite(originPos) && originPos < targetPos) {
            newPos -= 1;
          }
          return Math.max(0, Math.min(newPos, cache.rows.length));
        }

        const idx = cache.indexMap?.get(targetRow);
        if (typeof idx === 'number') {
          return isBefore ? idx : idx + 1;
        }

        return cache.rows.length;
      }

      function findClosestRow(tb, y) {
        const rows = Array.from(tb.querySelectorAll('.selectable-row'));
        let closest = null;
        let best = Infinity;
        for (const r of rows) {
          if (r === state.draggedRow) continue;
          const rect = r.getBoundingClientRect();
          const dist = Math.abs(y - (rect.top + rect.height / 2));
          if (dist < best) { best = dist; closest = r; }
        }
        return closest;
      }

      function setRowDraggable(row, draggable) {
        if (!row) return;
        row.draggable = !!draggable;
        row.classList.toggle('cursor-move', !!draggable);
        row.classList.toggle('cursor-not-allowed', !draggable);
        row.style.opacity = (!draggable && rowMeta(row).enProceso) ? '0.6' : '';
      }

      function enable() {
        const tb = tbodyEl();
        if (!tb) return;

        state.enabled = true;
        window.dragDropMode = true;
        setDragDropButtonGray(true);

        refreshAllRows();
        if (typeof window.deselectRow === 'function') window.deselectRow();

        // Remover listeners de selecciÃ³n antes de activar drag and drop
        window.allRows.forEach((row) => {
          // Remover listener de selecciÃ³n si existe
          if (row._selectionHandler) {
            row.removeEventListener('click', row._selectionHandler);
            row._selectionHandler = null;
          }
        });

        window.allRows.forEach(row => setRowDraggable(row, !rowMeta(row).enProceso));

        if (!tb.dataset.ddBound) {
          tb.dataset.ddBound = '1';
          tb.addEventListener('dragstart', onDragStart);
          tb.addEventListener('dragover',  onDragOver);
          tb.addEventListener('drop',      onDrop);
          tb.addEventListener('dragend',   onDragEnd);
        }

        toast('Modo arrastrar activado<br>Arrastra las filas para reorganizarlas', 'info');
      }

      function disable() {
        const tb = tbodyEl();
        if (!tb) return;

        state.enabled = false;
        window.dragDropMode = false;
        setDragDropButtonGray(false);

        refreshAllRows();

        // Remover todos los listeners anteriores y restaurar los de selecciÃ³n
        window.allRows.forEach((row, i) => {
          row.draggable = false;
          row.classList.remove('cursor-move', 'cursor-not-allowed');
          row.style.opacity = '';

          // Remover listener anterior si existe
          if (row._selectionHandler) {
            row.removeEventListener('click', row._selectionHandler);
            row._selectionHandler = null;
          }

          // Crear nuevo handler para selecciÃ³n (mismo patrÃ³n que assignClickEvents)
          row._selectionHandler = function(e) {
            // No seleccionar si estamos en modo inline edit y se hace click en una celda editable
            if (typeof inlineEditMode !== 'undefined' && inlineEditMode) {
              const cell = e.target.closest('td[data-column]');
              if (cell) {
                const col = cell.getAttribute('data-column');
                if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
                  // El modo inline manejarÃ¡ este click
                  return;
                }
              }
            }

            // No seleccionar si estamos en modo selecciÃ³n mÃºltiple (ese modo maneja sus propios clicks)
            if (window.multiSelectMode) {
              return;
            }

            e.stopPropagation();
            if (typeof window.selectRow === 'function') {
              window.selectRow(row, i);
            } else {
              console.warn('window.selectRow no está disponible.');
            }
          };

          // Asignar el nuevo listener
          row.addEventListener('click', row._selectionHandler);
        });

        toast('Modo arrastrar desactivado', 'info');
      }

      function toggle() { state.enabled ? disable() : enable(); }
      function isEnabled(){ return !!state.enabled; }

      function decideTargetTelarFromDOM() {
        return state.lastOverTelar || state.origin.telar;
      }

      function clearVisualRows() {
        (window.allRows || []).forEach(r =>
          r.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed', 'dd-drop-before', 'dd-drop-after')
        );
      }

      function onDragStart(e) {
        if (!state.enabled) return;

        const row = e.target.closest('.selectable-row');
        if (!row) return;

        const meta = rowMeta(row);
        if (meta.enProceso) {
          e.preventDefault();
          toast('No se puede mover un registro en proceso', 'error');
          return false;
        }

        state.draggedRow = row;
        state.origin = { telar: meta.telar, salon: meta.salon, cambioHilo: meta.cambioHilo, posicion: meta.posicion };
        state.lastOverRow = null;
        state.lastOverTelar = null;
        state.lastOverIsBefore = false;
        state.lastDragOverTime = 0;
        state.blockedReason = null;
        resetTelarCache();

        if (typeof window.deselectRow === 'function') window.deselectRow();

        row.classList.add('dragging');

        // Permitir tanto move como copy para permitir drag and drop entre telares
        e.dataTransfer.effectAllowed = 'all';
        e.dataTransfer.setData('text/plain', row.getAttribute('data-id') || '');
      }

      function onDragOver(e) {
        if (!state.enabled) return;
        if (!state.draggedRow) return;

        e.preventDefault();
        e.stopPropagation();

        const now = performance.now();
        if (now - state.lastDragOverTime < 16) return false;
        state.lastDragOverTime = now;

        const tb = tbodyEl();
        if (!tb) return false;

        clearVisualRows();

        let targetRow = e.target.closest('.selectable-row');
        if (!targetRow) {
          targetRow = findClosestRow(tb, e.clientY);
        }

        if (!targetRow || targetRow === state.draggedRow) {
          state.blockedReason = null;
          return false;
        }

        const rect = targetRow.getBoundingClientRect();
        const isBefore = e.clientY < (rect.top + rect.height / 2);
        const targetTelar = rowMeta(targetRow).telar;

        state.lastOverRow = targetRow;
        state.lastOverTelar = targetTelar;
        state.lastOverIsBefore = isBefore;

        const targetPosition = calcTargetPosition(targetTelar, targetRow, isBefore);
        const cache = buildTelarCache(targetTelar);
        const minAllowed = cache.lastEnProceso !== -1 ? (cache.lastEnProceso + 1) : 0;

        if (targetPosition < minAllowed) {
          state.blockedReason = 'No se puede colocar antes de un registro en proceso.';
          e.dataTransfer.dropEffect = 'none';
          targetRow.classList.add('drop-not-allowed');
        } else {
          state.blockedReason = null;
          // Permitir drop tanto en el mismo telar como en telares diferentes
          e.dataTransfer.dropEffect = isSameTelar(state.origin.telar, targetTelar) ? 'move' : 'move';
          targetRow.classList.add(isSameTelar(state.origin.telar, targetTelar) ? 'drag-over' : 'drag-over-warning');
        }

        targetRow.classList.add(isBefore ? 'dd-drop-before' : 'dd-drop-after');
        return false;
      }

      async function onDrop(e) {
        if (!state.enabled) return;
        if (!state.draggedRow) return;

        e.preventDefault();
        e.stopPropagation();

        const registroId = e.dataTransfer.getData('text/plain') || state.draggedRow.getAttribute('data-id');
        if (!registroId) {
          toast('Error: No se pudo obtener el ID del registro', 'error');
          clearVisualRows();
          return false;
        }

        const targetTelar = decideTargetTelarFromDOM();
        const targetRow = state.lastOverRow;
        const isBefore = !!state.lastOverIsBefore;

        if (state.blockedReason) {
          toast(state.blockedReason, 'error');
          clearVisualRows();
          return false;
        }

        // Permitir drag and drop tanto dentro del mismo telar como entre telares diferentes
        if (isSameTelar(targetTelar, state.origin.telar)) {
          // Movimiento dentro del mismo telar
          const tb = tbodyEl();
          if (!tb) return false;

          refreshAllRows();
          const telarRows = window.allRows.filter(r => isSameTelar(rowMeta(r).telar, state.origin.telar));
          if (telarRows.length < 2) {
            toast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
            clearVisualRows();
            return false;
          }

          const newPos = calcTargetPosition(state.origin.telar, targetRow, isBefore);
          const originPos = Number.isFinite(state.origin.posicion) ? (state.origin.posicion - 1) : null;

          if (originPos !== null && originPos === newPos) {
            toast('El registro ya está en esa posición', 'info');
            clearVisualRows();
            return false;
          }

          PT.loader.show();
          try {
            const resp = await fetch(`/planeacion/programa-tejido/${registroId}/prioridad/mover`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ new_position: newPos })
            });
            const data = await resp.json();
            PT.loader.hide();

            if (!data.success) {
              toast(data.message || 'No se pudo actualizar la prioridad', 'error');
              clearVisualRows();
              return false;
            }

            toast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

            if (typeof window.updateTableAfterDragDrop === 'function') {
              window.updateTableAfterDragDrop(data.detalles, registroId, data.updates || {});
            }
          } catch (err) {
            PT.loader.hide();
            clearVisualRows();
          }
          return false;
        }

        // Movimiento entre telares diferentes - permitir sin bloqueo
        const cache = buildTelarCache(targetTelar);
        const minAllowed = cache.lastEnProceso !== -1 ? (cache.lastEnProceso + 1) : 0;
        let targetPosition = calcTargetPosition(targetTelar, targetRow, isBefore);
        targetPosition = Math.max(minAllowed, Math.min(targetPosition, cache.rows?.length || 0));

        const targetSalon = targetRow ? rowMeta(targetRow).salon : '';
        await procesarMovimientoOtroTelar(registroId, targetTelar, targetPosition, targetSalon);
        return false;
      }

      async function procesarMovimientoOtroTelar(registroId, nuevoTelar, targetPosition, nuevoSalon = '') {
        refreshAllRows();

        if (!nuevoSalon) {
          const sample = window.allRows.find(r => isSameTelar(rowMeta(r).telar, nuevoTelar));
          nuevoSalon = sample ? rowMeta(sample).salon : '';
        }

        // Obtener salón origen
        const salonOrigen = state.origin.salon || '';
        const mismoSalon = salonOrigen && nuevoSalon && salonOrigen.trim() === nuevoSalon.trim();

        PT.loader.show();
        try {
          const verResp = await fetch(`/planeacion/programa-tejido/${registroId}/verificar-cambio-telar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ nuevo_salon: nuevoSalon, nuevo_telar: nuevoTelar })
          });

          const verificacion = await verResp.json();
          PT.loader.hide();

          if (!verificacion?.puede_mover) {
            Swal.fire({
              icon: 'error',
              title: 'No se puede cambiar de telar',
              html: `
                <div class="text-left">
                  <p class="mb-3">${verificacion?.mensaje || 'Validación fallida'}</p>
                  <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm">
                    <p><span class="font-medium">Clave Modelo:</span> ${verificacion?.clave_modelo || 'N/A'}</p>
                    <p><span class="font-medium">Telar Destino:</span> ${verificacion?.telar_destino || nuevoTelar} (${verificacion?.salon_destino || nuevoSalon || 'N/A'})</p>
                  </div>
                </div>
              `,
              confirmButtonText: 'Entendido',
              confirmButtonColor: '#dc2626',
              width: '520px'
            });
            clearVisualRows();
            return;
          }

          // Solo mostrar confirmación si el salón es diferente
          let confirmacion = { isConfirmed: true }; // Por defecto, confirmado

          if (!mismoSalon) {
            // Si el salón es diferente, mostrar la alerta de confirmación
            confirmacion = await Swal.fire({
              icon: 'warning',
              title: 'Cambio de Telar/Salón',
              html: `
                <div class="text-left">
                  <p class="mb-2">${verificacion?.mensaje || 'Se aplicará el cambio de telar'}</p>
                  <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm">
                    <p><span class="font-medium">Origen:</span> Telar ${verificacion?.telar_origen || state.origin.telar} (Salón ${verificacion?.salon_origen || state.origin.salon || 'N/A'})</p>
                    <p><span class="font-medium">Destino:</span> Telar ${verificacion?.telar_destino || nuevoTelar} (Salón ${verificacion?.salon_destino || nuevoSalon || 'N/A'})</p>
                  </div>
                </div>
              `,
              showCancelButton: true,
              confirmButtonText: 'Sí, cambiar de telar',
              cancelButtonText: 'Cancelar',
              confirmButtonColor: '#3b82f6',
              cancelButtonColor: '#6b7280',
              width: '700px',
              allowOutsideClick: false,
              allowEscapeKey: true
            });
          }

          if (!confirmacion.isConfirmed) {
            toast('Operación cancelada', 'info');
            clearVisualRows();
            return;
          }

          PT.loader.show();
          const cambioResp = await fetch(`/planeacion/programa-tejido/${registroId}/cambiar-telar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
              nuevo_salon: nuevoSalon,
              nuevo_telar: nuevoTelar,
              target_position: targetPosition
            })
          });

          const cambio = await cambioResp.json();
          PT.loader.hide();

          if (!cambio?.success) {
            Swal.fire({
              icon: 'error',
              title: 'Error al cambiar de telar',
              text: cambio?.message || 'No se pudo cambiar de telar',
              confirmButtonColor: '#dc2626'
            });
            clearVisualRows();
            return;
          }

          toast(cambio.message || 'Telar actualizado correctamente', 'success');
          if (typeof window.updateTableAfterDragDrop === 'function') {
            window.updateTableAfterDragDrop(cambio.detalles, cambio.registro_id || registroId, cambio.updates || {});
          }
        } catch (err) {
          PT.loader.hide();
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar el cambio de telar: ' + (err.message || 'Error desconocido'),
            confirmButtonColor: '#dc2626'
          });
          clearVisualRows();
        }
      }

      function onDragEnd() {
        if (!state.draggedRow) return;

        state.draggedRow.classList.remove('dragging');
        state.draggedRow.style.opacity = '';
        clearVisualRows();

        state.draggedRow = null;
        state.lastOverRow = null;
        state.lastOverTelar = null;
        state.lastOverIsBefore = false;
        state.lastDragOverTime = 0;
        state.blockedReason = null;
        resetTelarCache();
      }

      return { toggle, enable, disable, isEnabled };
    })();

    // Exponer toggle para botón navbar
    window.toggleDragDropMode = function () {
      PT.dragdrop.toggle();
      setDragDropButtonGray(PT.dragdrop.isEnabled());
    };

    // =========================
    // Balance button
    // =========================
    function updateBalanceBtnState() {
      const btn = document.querySelector('a[title="Balancear"]');
      if (!btn) return;

      const disable = () => {
        btn.classList.remove('bg-green-500', 'hover:bg-green-600', 'focus:ring-teal-400');
        btn.classList.add('bg-gray-400', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      };
      const enable = () => {
        btn.classList.add('bg-green-500', 'hover:bg-green-600', 'focus:ring-teal-400');
        btn.classList.remove('bg-gray-400', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      };

      const tb = tbodyEl();
      const rows = window.allRows?.length ? window.allRows : (tb ? qsa('.selectable-row', tb) : []);

      // Validar que selectedRowIndex sea válido
      const selectedIndex = window.selectedRowIndex;
      const isValidIndex = selectedIndex !== null && selectedIndex !== undefined && selectedIndex >= 0 && selectedIndex < rows.length;
      const row = isValidIndex ? rows[selectedIndex] : null;
      const ord = row?.getAttribute('data-ord-compartida');

      // Habilitar solo si hay una fila seleccionada con OrdCompartida
      if (ord && ord.trim() !== '') {
        enable();
      } else {
        disable();
      }
    }

    document.addEventListener('pt:selection-changed', updateBalanceBtnState);

    // =========================
    // Selección múltiple para vincular registros existentes
    // =========================
    window.selectedRowsIds = window.selectedRowsIds || new Set();
    window.selectedRowsOrder = window.selectedRowsOrder || []; // Array para mantener el orden de selección
    window.multiSelectMode = false;

    function toggleMultiSelectMode() {
      window.multiSelectMode = !window.multiSelectMode;
      const btn = qs('#btnVincularExistentes');
      if (btn) {
        if (window.multiSelectMode) {
          btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
          btn.classList.add('bg-blue-700', 'ring-2', 'ring-blue-300');
          btn.disabled = false; // Mantener habilitado para permitir cancelar
          btn.title = 'Modo selección múltiple activado. Haz click en las filas para seleccionarlas. Click aquí sin selecciones para cancelar.';
          updateVincularButtonState(); // Actualizar estado segÃºn selecciÃ³n
          updateSelectedRowsVisual(); // Actualizar visualizaciÃ³n de filas bloqueadas
        } else {
          btn.classList.remove('bg-blue-700', 'ring-2', 'ring-blue-300', 'bg-blue-300', 'cursor-not-allowed');
          btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
          btn.disabled = false; // Habilitar para activar modo de nuevo
          btn.title = 'Vincular registros existentes - Click para activar modo selección múltiple';
          window.selectedRowsIds.clear();
          window.selectedRowsOrder = [];
          updateSelectedRowsVisual();
        }
      }
      toast(window.multiSelectMode ? 'Modo selección múltiple activado. Selecciona al menos 2 registros. Si el primer registro tiene OrdCompartida, se usará ese para vincular los demás. Click sin selecciones para cancelar.' : 'Modo selección múltiple desactivado', 'info');
    }

    function toggleRowSelection(row) {
      if (!window.multiSelectMode) return;

      const id = row.getAttribute('data-id');
      if (!id) return;

      const ordCompartida = row.getAttribute('data-ord-compartida');
      const tieneOrdCompartida = ordCompartida && ordCompartida.trim() !== '';

      // Si NO hay ningún registro seleccionado todavía, permitir seleccionar cualquier registro
      // (con o sin OrdCompartida) - el primero seleccionado determinarÃ¡ las reglas
      if (window.selectedRowsIds.size === 0) {
        // Permitir seleccionar cualquier registro como primer registro
      } else {
        // Ya hay al menos un registro seleccionado, aplicar validaciones
        // Obtener el OrdCompartida del primer registro seleccionado (si existe)
        // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
        let primerOrdCompartida = null;
        if (window.selectedRowsOrder.length > 0) {
          const primerId = window.selectedRowsOrder[0];
          const primerRow = window.allRows?.find(r => r.getAttribute('data-id') === primerId);
          if (primerRow) {
            const primerOrd = primerRow.getAttribute('data-ord-compartida');
            if (primerOrd && primerOrd.trim() !== '') {
              primerOrdCompartida = primerOrd.trim();
            }
          }
        }

        // Validación solo si ya hay registros seleccionados:
        // - Si el primer registro NO tiene OrdCompartida y este registro Sí tiene, no permitir
        // - Si el primer registro Sí tiene OrdCompartida y este NO tiene, permitir (usará el del primero)
        // - Si ambos tienen OrdCompartida pero son diferentes, no permitir
        if (!primerOrdCompartida && tieneOrdCompartida) {
          toast('No se puede vincular: El primer registro seleccionado no tiene OrdCompartida, pero este registro sí lo tiene', 'warning');
          return;
        }

        if (primerOrdCompartida && tieneOrdCompartida && ordCompartida.trim() !== primerOrdCompartida) {
          toast(`No se puede vincular: Este registro tiene OrdCompartida ${ordCompartida.trim()}, pero el primer registro tiene ${primerOrdCompartida}`, 'warning');
          return;
        }
      }

      const cells = row.querySelectorAll('td');

      if (window.selectedRowsIds.has(id)) {
        window.selectedRowsIds.delete(id);
        // Remover del array de orden
        window.selectedRowsOrder = window.selectedRowsOrder.filter(selectedId => selectedId !== id);
        row.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
        // Remover text-white de las celdas
        cells.forEach(cell => {
          cell.classList.remove('text-white');
          cell.classList.add('text-gray-700');
        });
      } else {
        window.selectedRowsIds.add(id);
        // Agregar al array de orden (solo si no está ya)
        if (!window.selectedRowsOrder.includes(id)) {
          window.selectedRowsOrder.push(id);
        }
        row.classList.add('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
        // Aplicar text-white a todas las celdas
        cells.forEach(cell => {
          cell.classList.remove('text-gray-700');
          cell.classList.add('text-white');
        });
      }

      // Actualizar visualización de todos los registros para reflejar el nuevo estado de bloqueo
      updateSelectedRowsVisual();
      updateVincularButtonState();
    }

    function updateSelectedRowsVisual() {
      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');

      // Obtener el OrdCompartida del primer registro seleccionado (si existe)
      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      let primerOrdCompartida = null;
      if (window.selectedRowsOrder.length > 0) {
        const primerId = window.selectedRowsOrder[0];
        const primerRow = rows.find(r => r.getAttribute('data-id') === primerId);
        if (primerRow) {
          const primerOrd = primerRow.getAttribute('data-ord-compartida');
          if (primerOrd && primerOrd.trim() !== '') {
            primerOrdCompartida = primerOrd.trim();
          }
        }
      }

      rows.forEach(row => {
        const id = row.getAttribute('data-id');
        const cells = row.querySelectorAll('td');
        const ordCompartida = row.getAttribute('data-ord-compartida');
        const tieneOrdCompartida = ordCompartida && ordCompartida.trim() !== '';
        let debeBloquear = false;
        let mensajeBloqueo = '';

        if (window.multiSelectMode && window.selectedRowsIds.size > 0) {
          // Solo aplicar bloqueo si ya hay al menos un registro seleccionado
          if (!primerOrdCompartida && tieneOrdCompartida) {
            debeBloquear = true;
            mensajeBloqueo = 'No se puede vincular: El primer registro seleccionado no tiene OrdCompartida';
          } else if (primerOrdCompartida && tieneOrdCompartida && ordCompartida.trim() !== primerOrdCompartida) {
            debeBloquear = true;
            mensajeBloqueo = `No se puede vincular: Este registro tiene OrdCompartida ${ordCompartida.trim()}, pero el primer registro tiene ${primerOrdCompartida}`;
          }
        }

        if (debeBloquear) {
          row.classList.add('opacity-50', 'cursor-not-allowed');
          row.classList.remove('hover:bg-blue-50');
          row.title = mensajeBloqueo;
        } else {
          row.classList.remove('opacity-50', 'cursor-not-allowed');
          row.classList.add('hover:bg-blue-50');
          row.removeAttribute('title');
        }

        if (window.selectedRowsIds.has(id)) {
          row.classList.add('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
          // Aplicar text-white a todas las celdas
          cells.forEach(cell => {
            cell.classList.remove('text-gray-700');
            cell.classList.add('text-white');
          });
        } else {
          row.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-500', 'text-white');
          // Restaurar text-gray-700 en las celdas
          cells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
          });
        }
      });
    }

    function updateVincularButtonState() {
      const btn = qs('#btnVincularExistentes');
      if (!btn) return;

      // Solo actualizar estado si estamos en modo selección múltiple
      if (!window.multiSelectMode) {
        return;
      }

      const count = window.selectedRowsIds.size;
      if (count >= 2) {
        btn.disabled = false;
        btn.classList.remove('bg-blue-300', 'cursor-not-allowed');
        btn.classList.remove('bg-blue-700', 'ring-2', 'ring-blue-300');
        btn.classList.add('bg-blue-500', 'hover:bg-blue-600', 'ring-2', 'ring-blue-300');
        btn.title = `Vincular ${count} registro(s) seleccionado(s) - Click para vincular`;
      } else {
        // NO deshabilitar el botón cuando no hay selecciones - permitir cancelar el modo
        btn.disabled = false;
        btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        btn.classList.add('bg-blue-700', 'ring-2', 'ring-blue-300');
        btn.classList.remove('bg-blue-300', 'cursor-not-allowed');
        btn.title = count === 0 ? 'Click para cancelar modo selección múltiple' : `Selecciona ${2 - count} registro(s) más para vincular o click para cancelar`;
      }
    }

    // Vincular registros existentes
    window.vincularRegistrosExistentes = function() {
      if (!window.multiSelectMode) {
        // Activar modo selección múltiple
        toggleMultiSelectMode();
        return;
      }

      const selectedIds = Array.from(window.selectedRowsIds);

        // Si no hay registros seleccionados, cancelar el modo selección múltiple
      if (selectedIds.length === 0) {
        toggleMultiSelectMode();
        return;
      }

      if (selectedIds.length < 2) {
        toast('Debes seleccionar al menos 2 registros para vincular', 'warning');
        return;
      }

      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      const selectedIdsOrdenados = window.selectedRowsOrder.filter(id => selectedIds.includes(id));

      // Obtener el OrdCompartida del primer registro para el mensaje
      const primerId = selectedIdsOrdenados[0] || selectedIds[0];
      const primerRow = window.allRows?.find(r => r.getAttribute('data-id') === primerId);
      const primerOrdCompartida = primerRow?.getAttribute('data-ord-compartida');
      const primerTieneOrdCompartida = primerOrdCompartida && primerOrdCompartida.trim() !== '';

      // Confirmar acción
      if (typeof Swal === 'undefined') {
        const mensaje = primerTieneOrdCompartida
          ? `¿Vincular ${selectedIds.length} registro(s) usando el OrdCompartida existente (${primerOrdCompartida.trim()})?`
          : `¿Vincular ${selectedIds.length} registro(s) con un nuevo OrdCompartida?`;
        if (!confirm(mensaje)) return;
        doVincular(selectedIds);
      } else {
        const mensajeHtml = primerTieneOrdCompartida
          ? `Se vincularán <strong>${selectedIds.length} registro(s)</strong> usando el OrdCompartida existente: <strong>${primerOrdCompartida.trim()}</strong>.<br><br>Esto no afectará los datos de los registros, solo los agrupará.`
          : `Se vincularán <strong>${selectedIds.length} registro(s)</strong> con un nuevo OrdCompartida.<br><br>Esto no afectará los datos de los registros, solo los agrupará.`;

        Swal.fire({
          title: 'Vincular registros?',
          html: mensajeHtml,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Si, vincular',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#6366f1',
          cancelButtonColor: '#6b7280',
        }).then((result) => {
          if (result.isConfirmed) {
            doVincular(selectedIds);
          }
        });
      }
    };

    // Función para actualizar registros vinculados sin recargar
    async function actualizarRegistrosVinculados(registrosIds, ordCompartida) {
      const tb = document.querySelector('#mainTable tbody');
      if (!tb) return;

      // Obtener columnas para formatear valores
      const columns = (typeof columnsData !== 'undefined' && columnsData && columnsData.length > 0)
        ? columnsData
        : (window.columns || Array.from(document.querySelectorAll('#mainTable thead th[data-column]')).map(th => ({
          field: th.getAttribute('data-column'),
          label: th.textContent.trim(),
          dateType: null
        })));

      if (!columns || columns.length === 0) return;

      // Función para obtener el tipo de fecha de una columna
      const getDateType = (field) => {
        const col = columns.find(c => c.field === field);
        return col?.dateType || null;
      };

      // Función para formatear valor (usar la función global si existe, sino básica)
      const formatearValor = (registro, field, value) => {
        if (typeof formatearValorCelda === 'function') {
          return formatearValorCelda(registro, field, value, getDateType(field));
        }
        // Fallback básico
        if (value === null || value === undefined || value === '') return '';
        if (getDateType(field) === 'date' || getDateType(field) === 'datetime') {
          try {
            const dt = new Date(value);
            if (dt.getFullYear() <= 1970) return '';
            return getDateType(field) === 'date'
              ? dt.toLocaleDateString('es-MX')
              : dt.toLocaleString('es-MX');
          } catch (e) {
            return String(value);
          }
        }
        if (!isNaN(value) && !Number.isInteger(parseFloat(value))) {
          return parseFloat(value).toFixed(2);
        }
        return String(value);
      };

      // Actualizar cada registro
      for (const registroId of registrosIds) {
        try {
          // Obtener datos actualizados del registro
          const response = await fetch(`/planeacion/programa-tejido/${registroId}/detalles-balanceo?t=${Date.now()}`, {
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content,
              'Cache-Control': 'no-cache'
            }
          });

          if (!response.ok) continue;

          const result = await response.json();
          if (!result.success || !result.registro) continue;

          const registro = result.registro;

          // Buscar la fila existente
          const fila = tb.querySelector(`tr.selectable-row[data-id="${registroId}"]`);
          if (!fila) continue;

          // Actualizar data attribute de OrdCompartida
          if (registro.OrdCompartida) {
            fila.setAttribute('data-ord-compartida', registro.OrdCompartida);
          } else {
            // Si OrdCompartida es null, eliminar el atributo
            fila.removeAttribute('data-ord-compartida');
          }

          // Actualizar celdas relevantes
          columns.forEach(col => {
            const field = col.field;
            const value = registro[field] !== undefined ? registro[field] : null;
            const celda = fila.querySelector(`td[data-column="${field}"]`);

            if (celda) {
              // Actualizar data-value
              celda.setAttribute('data-value', value !== null && value !== undefined ? String(value) : '');

              // Actualizar contenido HTML formateado
              const contenidoHTML = formatearValor(registro, field, value);
              celda.innerHTML = contenidoHTML;
            }
          });

          // Pequeño delay para no saturar
          await new Promise(resolve => setTimeout(resolve, 50));
        } catch (error) {
          console.warn(`Error al actualizar registro ${registroId}:`, error);
        }
      }
    }

    function doVincular(registrosIds) {
      PT.loader.show();

      // Usar el array de orden para asegurar que el primero es realmente el primero seleccionado
      const registrosIdsOrdenados = window.selectedRowsOrder.filter(id => registrosIds.includes(id));
      const idsParaEnviar = registrosIdsOrdenados.length > 0 ? registrosIdsOrdenados : registrosIds;

      fetch('{{ route("programa-tejido.vincular-registros-existentes") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ registros_ids: idsParaEnviar })
      })
      .then(r => r.json())
      .then(data => {
        PT.loader.hide();

        if (data.success) {
          // Actualizar registros sin recargar
          actualizarRegistrosVinculados(data.registros_ids || registrosIds, data.ord_compartida);

          toast(data.message || 'Registros vinculados correctamente', 'success');
          // Limpiar selección y desactivar modo
          window.selectedRowsIds.clear();
          window.selectedRowsOrder = [];
          window.multiSelectMode = false;
          updateSelectedRowsVisual();
          updateVincularButtonState();

          const btn = qs('#btnVincularExistentes');
          if (btn) {
            btn.classList.remove('bg-blue-500','text-white','hover:bg-blue-600','ring-2', 'ring-blue-300', 'bg-blue-300', 'cursor-not-allowed');
            btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
            btn.disabled = false;
            btn.title = 'Vincular registros existentes - Click para activar modo selección múltiple';
          }
        } else {
          toast(data.message || 'Error al vincular los registros', 'error');
        }
      })
      .catch(err => {
        PT.loader.hide();
        toast('Error al procesar la solicitud: ' + (err.message || 'Error desconocido'), 'error');
      });
    }

    // Integrar selecciÃ³n mÃºltiple en el handler existente
    document.addEventListener('DOMContentLoaded', () => {
        // Interceptar clicks en filas cuando estÃ¡ activo el modo selecciÃ³n mÃºltiple
        const tb = tbodyEl();
        if (tb) {
          tb.addEventListener('click', (e) => {
            if (!window.multiSelectMode) return;

            const row = e.target.closest('.selectable-row');
            if (!row) return;

            // La validaciÃ³n de OrdCompartida se hace en toggleRowSelection
            // Si estamos en modo selecciÃ³n mÃºltiple, manejar la selecciÃ³n
            e.preventDefault();
            e.stopPropagation();
            toggleRowSelection(row);
          }, true); // Usar capture phase para interceptar antes que otros handlers
        }
    });

    // =========================
    // Actualizar totales basados en filas visibles
    // =========================
    window.updateTotales = function updateTotales() {
      const tb = tbodyEl();
      if (!tb) return;

      // Obtener todas las filas y filtrar solo las visibles
      const allRows = Array.from(tb.querySelectorAll('.selectable-row'));
      // Filtrar filas visibles - verificar mÃºltiples condiciones
      // IMPORTANTE: Verificar primero la clase filter-hidden ya que es lo que usa el sistema de filtros
      const visibleRows = allRows.filter(row => {
        // 1. PRIMERO: Verificar clase filter-hidden (esto es lo mÃ¡s importante - usado por el sistema de filtros)
        if (row.classList.contains('filter-hidden')) {
          return false;
        }

        // 2. Verificar estilo inline (puede estar oculta por display: none)
        const inlineDisplay = row.style.display;
        if (inlineDisplay === 'none') {
          return false;
        }

        // 3. Verificar estilo computado (mÃ¡s confiable, pero mÃ¡s lento)
        const computedStyle = window.getComputedStyle(row);
        const computedDisplay = computedStyle.display;
        const computedVisibility = computedStyle.visibility;

        if (computedDisplay === 'none' || computedVisibility === 'hidden') {
          return false;
        }

        // 4. Verificar que el offsetHeight sea mayor a 0 (otra forma de verificar visibilidad)
        // Esto puede ser Ãºtil si la fila estÃ¡ fuera del viewport pero aÃºn es visible
        // Comentado porque puede dar falsos negativos si la fila estÃ¡ fuera del viewport
        // if (row.offsetHeight === 0 && row.offsetWidth === 0) {
        //   return false;
        // }

        return true;
      });

      // Debug: verificar que los elementos existan
      const totalRegistrosEl = qs('#totalRegistros');


      let totalRegistros = visibleRows.length;
      let totalPedido = 0;
      let totalProduccion = 0;
      let totalSaldos = 0;

      visibleRows.forEach(row => {
        // Obtener TotalPedido - usar data-value primero, luego textContent
        const pedidoCell = row.querySelector('[data-column="TotalPedido"]');
        if (pedidoCell) {
          let pedidoValue = pedidoCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!pedidoValue || pedidoValue === '' || pedidoValue === 'null') {
            pedidoValue = (pedidoCell.textContent || pedidoCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = pedidoValue.toString().replace(/[^\d.-]/g, '');
          const pedido = parseFloat(cleanedValue) || 0;
          if (!isNaN(pedido)) {
            totalPedido += pedido;
          }
        }

        // Obtener Produccion - usar data-value primero, luego textContent
        const produccionCell = row.querySelector('[data-column="Produccion"]');
        if (produccionCell) {
          let produccionValue = produccionCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!produccionValue || produccionValue === '' || produccionValue === 'null') {
            produccionValue = (produccionCell.textContent || produccionCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = produccionValue.toString().replace(/[^\d.-]/g, '');
          const produccion = parseFloat(cleanedValue) || 0;
          if (!isNaN(produccion)) {
            totalProduccion += produccion;
          }
        }

        // Obtener SaldoPedido - usar data-value primero, luego textContent
        const saldosCell = row.querySelector('[data-column="SaldoPedido"]');
        if (saldosCell) {
          let saldosValue = saldosCell.getAttribute('data-value') || '';
          // Si data-value estÃ¡ vacÃ­o o no existe, usar textContent
          if (!saldosValue || saldosValue === '' || saldosValue === 'null') {
            saldosValue = (saldosCell.textContent || saldosCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numÃ©ricos excepto punto y signo negativo
          const cleanedValue = saldosValue.toString().replace(/[^\d.-]/g, '');
          const saldos = parseFloat(cleanedValue) || 0;
          if (!isNaN(saldos)) {
            totalSaldos += saldos;
          }
        }
      });

      // Verificar que los elementos existan
      const totalPedidoEl = qs('#totalPedido');
      const totalProduccionEl = qs('#totalProduccion');
      const totalSaldosEl = qs('#totalSaldos');

      if (!totalRegistrosEl || !totalPedidoEl || !totalProduccionEl || !totalSaldosEl) {
        return;
      }

      // Actualizar los elementos
      totalRegistrosEl.textContent = totalRegistros.toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
      totalPedidoEl.textContent = totalPedido.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      totalProduccionEl.textContent = totalProduccion.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      totalSaldosEl.textContent = totalSaldos.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // =========================
    // Reset (filtros + columnas)
    // =========================
    function resetAllView(e) {
      if (e) e.preventDefault();

      if (typeof window.filters !== 'undefined') window.filters = [];
      if (typeof window.quickFilters !== 'undefined') {
        window.quickFilters = {
          ultimos:false, divididos:false, enProceso:false,
          salonJacquard:false, salonSmit:false, conCambioHilo:false
        };
      }
      if (typeof window.dateRangeFilters !== 'undefined') {
        window.dateRangeFilters = { fechaInicio:{desde:null,hasta:null}, fechaFinal:{desde:null,hasta:null} };
      }
      if (typeof window.lastFilterState !== 'undefined') window.lastFilterState = null;

      const tb = tbodyEl();
      if (tb) {
        qsa('.selectable-row', tb).forEach(r => {
          r.style.display = '';
          r.classList.remove('filter-hidden');
        });
      }

      if (typeof window.updateFilterUI === 'function') window.updateFilterUI();

      if (typeof window.resetColumnVisibility === 'function') window.resetColumnVisibility();
      else {
        const headers = qsa('#mainTable thead th');
        headers.forEach((_, i) => qsa('.column-' + i).forEach(el => el.style.display = ''));
        if (typeof window.updatePinnedColumnsPositions === 'function') window.updatePinnedColumnsPositions();
      }

      updateTotales();
      toast('Vista restablecida (filtros y columnas)', 'success');
    }

    // =========================
    // Bind botones layout
    // =========================
    function bindLayoutButtons() {
      qs('#layoutBtnEditar')?.setAttribute('disabled', 'disabled');
      qs('#layoutBtnEliminar')?.setAttribute('disabled', 'disabled');
      qs('#layoutBtnVerLineas')?.setAttribute('disabled', 'disabled');

      const btnInlineEdit = qs('#btnInlineEdit');
      if (btnInlineEdit) btnInlineEdit.remove();

      qs('#btn-editar-programa')?.addEventListener('click', () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;
        // Funcionalidad de editar eliminada - ahora se usa duplicar/vincular/dividir
        console.warn('La funcionalidad de editar ha sido reemplazada por duplicar/vincular/dividir');
      });

      qs('#btn-eliminar-programa')?.addEventListener('click', () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;
        PT.actions.eliminarRegistro(id);
      });

      const openLines = () => {
        const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row');
        const row = rows[window.selectedRowIndex];
        const id = row?.getAttribute('data-id');
        if (!id) return;

        if (typeof window.openLinesModal === 'function') window.openLinesModal(id);
        else toast('Error: No se pudo abrir el modal. Por favor recarga la pÃ¡gina.', 'error');
      };

      qs('#btn-ver-lineas')?.addEventListener('click', openLines);
      qs('#layoutBtnVerLineas')?.addEventListener('click', openLines);

      qs('#btnResetColumns')?.addEventListener('click', resetAllView);
      qs('#btnResetColumnsMobile')?.addEventListener('click', resetAllView);
    }

    // =========================
    // Restaurar selecciÃ³n
    // =========================
    window.yellowHighlightTimeout = null;

    function restoreSelectionAfterReload() {
      const tb = tbodyEl();
      if (!tb) return;

      // Cancelar timeout anterior si existe
      if (window.yellowHighlightTimeout) {
        clearTimeout(window.yellowHighlightTimeout);
        window.yellowHighlightTimeout = null;
      }

      const urlParams = new URLSearchParams(window.location.search);
      const registroIdParam = urlParams.get('registro_id');

      const ssSelect = sessionStorage.getItem('selectRegistroId');
      const ssScroll = sessionStorage.getItem('scrollToRegistroId');

      const idToUse = registroIdParam || ssSelect || ssScroll;
      if (!idToUse) return;

      const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
      const targetRow = rows.find(r => r.getAttribute('data-id') == idToUse);
      if (!targetRow) return;

      const idx = rows.indexOf(targetRow);
      if (typeof window.selectRow === 'function' && idx >= 0) window.selectRow(targetRow, idx);

      targetRow.scrollIntoView({ behavior:'smooth', block:'center' });
      // Solo agregar amarillo temporal si no estÃ¡ en modo ediciÃ³n inline (muy breve, 500ms)
      if (!inlineEditMode && !window.inlineEditMode) {
        targetRow.classList.add('bg-yellow-100');
        window.yellowHighlightTimeout = setTimeout(() => {
          // Solo quitar si no estÃ¡ en modo ediciÃ³n inline y no hay inputs editando
          if (!inlineEditMode && !window.inlineEditMode && !targetRow.querySelector('.inline-edit-input')) {
            targetRow.classList.remove('bg-yellow-100');
          }
          window.yellowHighlightTimeout = null;
        }, 500);
      }

      if (registroIdParam) {
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('registro_id');
        window.history.replaceState({}, '', cleanUrl.toString());
      }

      sessionStorage.removeItem('selectRegistroId');
      sessionStorage.removeItem('scrollToRegistroId');
    }

    function showSavedToastIfAny() {
      const msg  = sessionStorage.getItem('priorityChangeMessage');
      const type = sessionStorage.getItem('priorityChangeType') || 'success';
      if (!msg) return;
      setTimeout(() => {
        toast(msg, type);
        sessionStorage.removeItem('priorityChangeMessage');
        sessionStorage.removeItem('priorityChangeType');
      }, 350);
    }

    // =========================
    // IntegraciÃ³n filtro layout
    // =========================
    window.applyTableFilters = function(values) {
      try {
        const tb = tbodyEl();
        if (!tb) return;

        refreshAllRows();

        const rows = window.allRows.slice();
        const entries = Object.entries(values || {});
        const filtered = entries.length
          ? rows.filter(tr => entries.every(([col, val]) => {
              const escapedCol = escapeCSSValue(col);
              const selector = '[data-column="' + escapedCol + '"]';
              const cell = tr.querySelector(selector);
              if (!cell) return false;
              return (cell.textContent || '').toLowerCase().includes(String(val).toLowerCase());
            }))
          : rows;

        tb.innerHTML = '';
        const frag = document.createDocumentFragment();
        filtered.forEach(r => frag.appendChild(r));
        tb.appendChild(frag);

        refreshAllRows();
        updateTotales();
      } catch (e) {}
    };

    // =========================
    // Dropdown Actualizar
    // =========================
    (function() {
      // Cerrar dropdown al hacer click fuera
      document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('actualizarDropdownMenu');
        const btn = document.getElementById('btnActualizarDropdown');
        if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
          dropdown.classList.add('hidden');
        }
      });

      // Manejar click en "Act. Calendarios"
      document.addEventListener('DOMContentLoaded', () => {
        const menuActCalendarios = document.getElementById('menuActCalendarios');
        if (menuActCalendarios) {
          menuActCalendarios.addEventListener('click', () => {
            const dropdown = document.getElementById('actualizarDropdownMenu');
            if (dropdown) {
              dropdown.classList.add('hidden');
            }
            if (typeof window.abrirModalActCalendarios === 'function') {
              window.abrirModalActCalendarios();
            } else {
              toast('Función abrirModalActCalendarios no disponible', 'error');
            }
          });
        }

        // Manejar click en "Act. Fechas" (por ahora no hace nada)
        const menuActFechas = document.getElementById('menuActFechas');
        if (menuActFechas) {
          menuActFechas.addEventListener('click', () => {
            const dropdown = document.getElementById('actualizarDropdownMenu');
            if (dropdown) {
              dropdown.classList.add('hidden');
            }
            toast('Funcionalidad de Actualizar Fechas próximamente', 'info');
          });
        }
      });
    })();

    // =========================
    // Init
    // =========================
    document.addEventListener('DOMContentLoaded', () => {
      setNavbarHeightVar();

      // ⚡ OPTIMIZACIÓN: Cargar estados guardados primero
      // initializeColumnVisibility se llamará automáticamente desde loadPersistedHiddenColumns
      // si no hay estados guardados, evitando conflictos
      if (typeof window.loadPersistedHiddenColumns === 'function') {
        window.loadPersistedHiddenColumns();
      }

      // Asegurar que los fijados automáticos se apliquen
      setTimeout(() => {
        if (typeof window.pinDefaultColumns === 'function') {
          window.pinDefaultColumns();
        } else if (typeof window.applyDefaultPinsOnce === 'function') {
          window.applyDefaultPinsOnce();
        }

        // Actualizar iconos de filtro al cargar
        if (typeof window.updateColumnFilterIcons === 'function') {
          window.updateColumnFilterIcons();
        }
        // Actualizar iconos de columnas fijadas al cargar
        if (typeof window.updateColumnPinIcons === 'function') {
          window.updateColumnPinIcons();
        }
        if (typeof window.updatePinnedColumnsPositions === 'function') {
          window.updatePinnedColumnsPositions();
          // Actualizar iconos después de actualizar posiciones
          setTimeout(() => {
            if (typeof window.updateColumnPinIcons === 'function') {
              window.updateColumnPinIcons();
            }
          }, 100);
        }
      }, 100);

      const tb = tbodyEl();
      if (tb) {
        refreshAllRows();
        // Función helper para asignar eventos onclick
        const assignClickEvents = () => {
          if (!window.dragDropMode && window.allRows && window.allRows.length > 0) {
            window.allRows.forEach((row, i) => {
              // Remover listener anterior si existe
              if (row._selectionHandler) {
                row.removeEventListener('click', row._selectionHandler);
              }

              // Crear nuevo handler
              row._selectionHandler = function(e) {
                // No seleccionar si estamos en modo inline edit y se hace click en una celda editable
                if (inlineEditMode) {
                  const cell = e.target.closest('td[data-column]');
                  if (cell) {
                    const col = cell.getAttribute('data-column');
                    if (col && uiInlineEditableFields && uiInlineEditableFields[col]) {
                      // El modo inline manejarÃ¡ este click
                      return;
                    }
                  }
                }

                e.stopPropagation();
                if (typeof window.selectRow === 'function') {
                  window.selectRow(row, i);
                } else {
                  console.warn('window.selectRow no estÃ¡ disponible. Verifica que selection.blade.php se haya cargado.');
                }
              };

              // Asignar evento con addEventListener (permite mÃºltiples listeners)
              row.addEventListener('click', row._selectionHandler);
            });
          }
        };

        // Intentar asignar eventos inmediatamente
        assignClickEvents();

        // Reintentar despuÃ©s de un breve delay para asegurar que los scripts se hayan cargado
        setTimeout(assignClickEvents, 100);
        setTimeout(assignClickEvents, 500);
      }

      setTimeout(() => setDragDropButtonGray(!!window.dragDropMode), 0);

      window.addEventListener('resize', () => {
        setNavbarHeightVar();
        if (typeof window.updatePinnedColumnsPositions === 'function') window.updatePinnedColumnsPositions();
      });

      bindLayoutButtons();
      restoreSelectionAfterReload();
      // Actualizar estado del botÃ³n balancear despuÃ©s de restaurar selecciÃ³n
      // (tambiÃ©n se actualizarÃ¡ automÃ¡ticamente por el evento pt:selection-changed)
      setTimeout(() => updateBalanceBtnState(), 100);
      // Inicializar estado del botÃ³n vincular (solo si estÃ¡ en modo selecciÃ³n mÃºltiple)
      setTimeout(() => {
        if (window.multiSelectMode) {
          updateVincularButtonState();
        }
      }, 100);
      // Inicializar totales
      setTimeout(() => updateTotales(), 200);
      showSavedToastIfAny();

      // Inicializar listeners de Reprogramar
      if (typeof window.initReprogramarListeners === 'function') {
        setTimeout(() => window.initReprogramarListeners(), 300);
      }

      const balanceBtn = document.querySelector('a[title="Balancear"]');
      if (balanceBtn) {
        balanceBtn.addEventListener('click', (e) => {
          e.preventDefault();

          const tb = tbodyEl();
          if (!tb) return;

          const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
          if (window.selectedRowIndex === null || window.selectedRowIndex === undefined || window.selectedRowIndex < 0) {
            toast('Selecciona primero un registro con OrdCompartida', 'info');
            return;
          }

          const row = rows[window.selectedRowIndex];
          const ordCompartida = row?.getAttribute('data-ord-compartida');
          if (!ordCompartida) {
            toast('El registro seleccionado no tiene OrdCompartida', 'info');
            return;
          }

          if (typeof window.verDetallesGrupoBalanceo === 'function') {
            window.verDetallesGrupoBalanceo(parseInt(ordCompartida));
          } else {
            toast('No existe verDetallesGrupoBalanceo()', 'error');
          }
        });
      }
    });

    // =========================
    // Reprogramar checkbox con modal
    // =========================
    (function() {
      // Función para procesar la selección
      async function procesarSeleccionReprogramar(registroId, valor, checkbox, texto) {
        // Actualizar UI
        checkbox.checked = true;
        checkbox.setAttribute('data-valor-actual', valor);

        if (valor == '1') {
          texto.textContent = 'P. Siguiente';
        } else if (valor == '2') {
          texto.textContent = 'P. Ultima';
        }

        // Enviar al backend
        try {
          PT.loader.show();
          const response = await fetch(`/planeacion/programa-tejido/${registroId}/reprogramar`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ reprogramar: valor })
          });

          const data = await response.json();
          PT.loader.hide();

          if (data.success) {
            toast('Reprogramar actualizado correctamente', 'success');
          } else {
            toast(data.message || 'Error al actualizar reprogramar', 'error');
            // Revertir cambios
            checkbox.checked = false;
            checkbox.setAttribute('data-valor-actual', '');
            texto.textContent = '';
          }
        } catch (error) {
          PT.loader.hide();
          toast('Error al procesar la solicitud', 'error');
          // Revertir cambios
          checkbox.checked = false;
          checkbox.setAttribute('data-valor-actual', '');
          texto.textContent = '';
        }
      }

      // Manejar click en checkbox usando delegación de eventos
      function initReprogramarListeners() {
        const tb = tbodyEl();
        if (!tb) return;

        // Evitar agregar listener múltiples veces
        if (tb.dataset.reprogramarListenerAdded === 'true') return;
        tb.dataset.reprogramarListenerAdded = 'true';

        // Usar delegación de eventos en el tbody
        tb.addEventListener('click', async (e) => {
          // Verificar si el click fue en el checkbox directamente
          if (!e.target || e.target.type !== 'checkbox') return;
          if (!e.target.classList || !e.target.classList.contains('reprogramar-checkbox')) return;

          const checkbox = e.target;
          const container = checkbox.closest('.reprogramar-container');

          if (!container) return;

          // Verificar si estÃ¡ deshabilitado (no estÃ¡ en proceso)
          if (checkbox.disabled) {
            toast('Solo los registros en proceso pueden tener Reprogramar activo', 'warning');
            return;
          }

          // Verificar el atributo data-en-proceso del contenedor
          const enProceso = container.getAttribute('data-en-proceso');
          if (enProceso !== '1') {
            toast('Solo los registros en proceso pueden tener Reprogramar activo', 'warning');
            return;
          }

          // Capturar el estado ANTES de prevenir el comportamiento por defecto
          const texto = container.querySelector('.reprogramar-texto');
          const registroId = checkbox.getAttribute('data-registro-id');
          const valorActual = checkbox.getAttribute('data-valor-actual') || '';
          const estabaMarcado = checkbox.checked || (valorActual && (valorActual == '1' || valorActual == '2'));

          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          // Si ya tiene un valor activo (estaba marcado), limpiarlo
          if (estabaMarcado && valorActual && (valorActual == '1' || valorActual == '2')) {
            // Limpiar visualmente - forzar que se vea desmarcado
            checkbox.checked = false;
            checkbox.removeAttribute('checked');
            checkbox.setAttribute('data-valor-actual', '');
            if (texto) texto.textContent = '';

            // Enviar al backend para limpiar
            try {
              PT.loader.show();
              const response = await fetch(`/planeacion/programa-tejido/${registroId}/reprogramar`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': qs('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ reprogramar: null })
              });

              const data = await response.json();
              PT.loader.hide();

              if (data.success) {
                // Asegurar que el checkbox estÃ© visualmente desmarcado
                checkbox.checked = false;
                checkbox.removeAttribute('checked');
                checkbox.setAttribute('data-valor-actual', '');
                if (texto) texto.textContent = '';
                toast('Reprogramar limpiado correctamente', 'success');
              } else {
                toast(data.message || 'Error al limpiar reprogramar', 'error');
                // Revertir cambios - restaurar estado marcado
                checkbox.checked = true;
                checkbox.setAttribute('checked', 'checked');
                checkbox.setAttribute('data-valor-actual', valorActual);
                if (texto) {
                  if (valorActual == '1') {
                    texto.textContent = 'P. Siguiente';
                  } else if (valorActual == '2') {
                    texto.textContent = 'P. Ultima';
                  }
                }
              }
            } catch (error) {
              PT.loader.hide();
              toast('Error al procesar la solicitud', 'error');
              // Revertir cambios - restaurar estado marcado
              checkbox.checked = true;
              checkbox.setAttribute('checked', 'checked');
              checkbox.setAttribute('data-valor-actual', valorActual);
              if (texto) {
                if (valorActual == '1') {
                  texto.textContent = 'P. Siguiente';
                } else if (valorActual == '2') {
                  texto.textContent = 'P. Ultima';
                }
              }
            }
            return;
          }

          // Si no estÃ¡ marcado, mostrar modal
          if (typeof Swal === 'undefined') {
            toast('SweetAlert no estÃ¡ disponible', 'error');
            checkbox.checked = false;
            return;
          }

          // Asegurar que el checkbox no estÃ© marcado antes de mostrar el modal
          checkbox.checked = false;

          const resultado = await Swal.fire({
            title: 'Seleccionar Reprogramar',
            html: `
              <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">Selecciona una opciÃ³n:</p>
                <div class="space-y-2">
                  <button type="button" id="swal-opcion-1" class="w-full text-left px-4 py-3 bg-blue-50 hover:bg-blue-100 border border-blue-300 rounded-md text-blue-700 font-medium transition-colors">
                    P. Siguiente
                  </button>
                  <button type="button" id="swal-opcion-2" class="w-full text-left px-4 py-3 bg-green-50 hover:bg-green-100 border border-green-300 rounded-md text-green-700 font-medium transition-colors">
                    P. Ultima
                  </button>
                </div>
              </div>
            `,
            showCancelButton: true,
            showConfirmButton: false,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#6b7280',
            width: '400px',
            allowOutsideClick: true,
            allowEscapeKey: true,
            didOpen: () => {
              // Manejar click en opciones
              const opcion1 = document.getElementById('swal-opcion-1');
              const opcion2 = document.getElementById('swal-opcion-2');

              if (opcion1) {
                opcion1.addEventListener('click', () => {
                  Swal.close();
                  procesarSeleccionReprogramar(registroId, '1', checkbox, texto);
                });
              }

              if (opcion2) {
                opcion2.addEventListener('click', () => {
                  Swal.close();
                  procesarSeleccionReprogramar(registroId, '2', checkbox, texto);
                });
              }
            }
          });

          // Si se cancelÃ³ el modal, no hacer nada (el checkbox ya no estarÃ¡ marcado)
          if (resultado.dismiss === Swal.DismissReason.cancel || resultado.dismiss === Swal.DismissReason.backdrop) {
            checkbox.checked = false;
          }
        }, true); // Usar capture phase para capturar antes que otros listeners
      }

      // Inicializar listeners - se ejecutarÃ¡ desde el init principal
      window.initReprogramarListeners = initReprogramarListeners;
    })();

  })();
</script>
