@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')

@section('content')
<div class="w-full">
  <div class="bg-white shadow overflow-hidden w-full">

    @php
      $columns   = $columns ?? [];
      $registros = $registros ?? collect();

      $formatValue = function($registro, $field, $dateType = null) {
        $value = $registro->{$field} ?? null;

        if ($field === 'Reprogramar') {
          $registroId = $registro->Id ?? $registro->id ?? '';
          $valorActual = $value ?? '';
          $checked = ($valorActual == '1' || $valorActual == '2') ? 'checked' : '';
          $textoMostrar = '';
          if ($valorActual == '1') {
            $textoMostrar = 'P. Siguiente';
          } elseif ($valorActual == '2') {
            $textoMostrar = 'P. Ultima';
          }
          // Verificar si está en proceso
          $enProceso = $registro->EnProceso ?? 0;
          $estaEnProceso = ($enProceso == 1 || $enProceso === true);
          $disabled = $estaEnProceso ? '' : 'disabled';
          $cursorClass = $estaEnProceso ? 'cursor-pointer' : 'cursor-not-allowed opacity-50';
          $dataEnProceso = $estaEnProceso ? 'data-en-proceso="1"' : 'data-en-proceso="0"';
          return '<div class="relative inline-flex items-center reprogramar-container" data-registro-id="'.e($registroId).'" '.$dataEnProceso.'>
              <input type="checkbox" '.$checked.' '.$disabled.' class="reprogramar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 '.$cursorClass.'" data-registro-id="'.e($registroId).'" data-valor-actual="'.e($valorActual).'">
              <span class="reprogramar-texto ml-2 text-xs text-gray-600 font-medium">'.e($textoMostrar).'</span>
            </div>';
        }

        if ($value === null || $value === '') return '';

        if ($field === 'EnProceso') {
          $checked = ($value == 1 || $value === true) ? 'checked' : '';
          return '<input type="checkbox" '.$checked.' disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">';
        }

        if ($field === 'Ultimo') {
          $sv = strtoupper(trim((string)$value));
          if ($sv === 'UL') return '1';
          if ($sv === '0' || $value === 0) return '';
        }

        if ($field === 'CambioHilo') {
          if ($value === '0' || $value === 0) return '';
        }

        if ($field === 'EficienciaSTD' && is_numeric($value)) {
          return round(((float)$value) * 100) . '%';
        }

        if ($dateType === 'date' || $dateType === 'datetime') {
          try {
            $dt = $value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::parse($value);
            if ($dt->year <= 1970) return '';
            return $dateType === 'date' ? $dt->format('d/m/Y') : $dt->format('d/m/Y H:i');
          } catch (\Exception $e) {
            return '';
          }
        }

        if (is_numeric($value) && !preg_match('/^\d+$/', (string)$value)) {
          return number_format((float)$value, 2);
        }

        return $value;
      };
    @endphp

    @if($registros && $registros->count() > 0)
      <div class="overflow-x-auto">
        <div class="overflow-y-auto" style="max-height: calc(100vh - 70px); position: relative;">
          <table id="mainTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-blue-500 text-white" style="position: sticky; top: 0; z-index: 10;">
              <tr>
                @foreach($columns as $index => $col)
                  <th
                    class="px-2 py-1 text-left text-xs font-semibold text-white whitespace-nowrap column-{{ $index }}"
                    style="position: sticky; top: 0; z-index: 10; background-color: #3b82f6; min-width: 80px;"
                    data-column="{{ $col['field'] }}"
                    data-index="{{ $index }}"
                  >
                    {{ $col['label'] }}
                  </th>
                @endforeach
              </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-100">
              @foreach($registros as $index => $registro)
                @php
                  $producto = $registro->NombreProducto ?? '';
                  $esRepaso = !empty($producto) && strtoupper(substr(trim($producto), 0, 6)) === 'REPASO';
                @endphp
                <tr
                  class="hover:bg-blue-50 cursor-pointer selectable-row"
                  data-row-index="{{ $index }}"
                  data-id="{{ $registro->Id ?? $registro->id ?? '' }}"
                  @if(!empty($registro->OrdCompartida)) data-ord-compartida="{{ $registro->OrdCompartida }}" @endif
                  @if($esRepaso) data-es-repaso="1" @endif
                >
                  @foreach($columns as $colIndex => $col)
                    @php
                      $rawValue = $registro->{$col['field']} ?? '';
                      if ($rawValue instanceof \Carbon\Carbon) {
                        $rawValue = $rawValue->format('Y-m-d H:i:s');
                      }
                    @endphp
                    <td
                      class="px-3 py-2 text-sm text-gray-700 {{ ($col['dateType'] ?? null) ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }}"
                      data-column="{{ $col['field'] }}"
                      data-value="{{ e(is_scalar($rawValue) ? $rawValue : json_encode($rawValue)) }}"
                    >
                      {!! $formatValue($registro, $col['field'], $col['dateType'] ?? null) !!}
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>

            {{-- Fila informativa de totales --}}
            <tfoot id="tfootTotales">
              <tr id="rowTotales" class="bg-blue-100 border-t-2 border-blue-500">
                <td colspan="{{ count($columns) }}" class="px-4 py-3 text-sm font-semibold text-blue-900">
                  <div class="flex items-center justify-start gap-4">
                    <span>Total Registros: <strong id="totalRegistros" class="text-blue-700">0</strong></span>
                    <span>Total Pedido: <strong id="totalPedido" class="text-blue-700">0.00</strong></span>
                    <span>Total Producción: <strong id="totalProduccion" class="text-blue-700">0.00</strong></span>
                    <span>Total Saldos: <strong id="totalSaldos" class="text-blue-700">0.00</strong></span>
                  </div>
                </td>
              </tr>
            </tfoot>

          </table>
        </div>
      </div>
    @else
      @include('components.programa-tejido.empty-state')
    @endif
  </div>
</div>

{{-- Modal líneas --}}
@include('components.programa-tejido.req-programa-tejido-line-table')

{{-- Modal Actualizar Calendarios --}}
@include('modulos.programa-tejido.modal.act-calendarios')

{{-- Menú contextual --}}
<div id="contextMenu" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
  <button id="contextMenuCrear" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-plus-circle text-blue-500"></i>
    <span>Crear</span>
  </button>
  <button id="contextMenuEditar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-pen text-yellow-500"></i>
    <span>Editar fila</span>
  </button>
  {{-- redirigir a catalogo de codificacion --}}
  <button id="contextMenuCodificacion" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-code text-green-500"></i>
    <span>Codificación</span>
  </button>
  {{-- redirigir a catalogo de codificacion de modelos --}}
  <button id="contextMenuModelos" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-code text-green-500"></i>
    <span>Modelos</span>
  </button>
</div>

{{-- OJO: EL JS de duplicar/dividir NO VA AQUÍ (si lo incluyes aquí se imprime) --}}

<style>
  .pinned-column { position: sticky !important; background-color: #f3f8ff !important; color: #000 !important; }

  /* Asegurar que los encabezados de columnas fijadas se mantengan visibles */
  thead th.pinned-column {
    position: sticky !important;
    top: 0 !important;
    z-index: 100 !important;
    background-color: #3b82f6 !important;
    color: #fff !important;
  }

  /* Estilo para columnas fijadas en filas REPASO - rojo pastel */
  tr[data-es-repaso="1"] .pinned-column {
    background-color: #ffe5e5 !important;
    color: #000 !important;
  }

  .cursor-move { cursor: move !important; }
  .cursor-not-allowed { cursor: not-allowed !important; opacity: 0.6; }

  .selectable-row.dragging { opacity: 0.4; background-color: #e0e7ff !important; }
  .selectable-row.drag-over { border-top: 3px solid #3b82f6; background-color: #dbeafe; }
  .selectable-row.drag-over-warning { border-top: 3px solid #f59e0b; background-color: #fef3c7; box-shadow: 0 0 0 2px #f59e0b; }
  .selectable-row.drop-not-allowed { border-top: 3px solid #ef4444; background-color: #fee2e2; cursor: not-allowed !important; }

  td { transition: background-color 0.3s ease-in-out; }
  .selectable-row.dragging ~ tr td, .selectable-row.dragging td { transition: none !important; }
  .selectable-row.bg-yellow-100 { background-color: #fef3c7 !important; }
  .selectable-row.bg-yellow-100 td { background-color: #fef3c7 !important; }

  .inline-edit-mode .selectable-row { cursor: text !important; }
  .inline-edit-input {
    width: 100%;
    border: 1px solid #cbd5f5;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.85rem;
    color: #1f2937;
    background-color: #f9fafb;
    transition: border 0.2s ease, box-shadow 0.2s ease;
  }
  .inline-edit-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 1px #2563eb33;
    background-color: #fff;
  }
  .inline-edit-input-wide {
    min-width: 150px;
    width: auto !important;
    max-width: 200px;
  }
  td[data-column="TotalPedido"] .inline-edit-input-container {
    width: auto;
    min-width: 150px;
    max-width: 200px;
  }
  .inline-edit-row.inline-saving { opacity: 0.7; }

  #contextMenu { animation: contextMenuFadeIn 0.15s ease-out; }
  @keyframes contextMenuFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
  #contextMenu button:active { background-color: #dbeafe; }
  #contextMenu button:disabled { opacity: 0.5; cursor: not-allowed; }

  /* Asegurar que la barra informativa NO sea sticky */
  #tfootTotales {
    position: static !important;
  }
  #tfootTotales td {
    position: static !important;
  }

  /* Estilos para Reprogramar */
  .reprogramar-container {
    position: relative;
    display: inline-flex;
    align-items: center;
  }
  .reprogramar-texto {
    min-width: 90px;
    display: inline-block;
  }
</style>

{{-- balanceo --}}
@include('modulos.programa-tejido.balancear')

@include('components.ui.toast-notification')
@endsection

@push('scripts')
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

    const toast = (msg, type='info') => {
      if (typeof window.showToast === 'function') return window.showToast(msg, type);
      console.log('[Toast]', type, msg);
    };

    // =========================
    // BOTÓN DRAG & DROP (GRIS CUANDO ACTIVO)
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
    // Loader único
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
      const enProcesoCell = row.querySelector('[data-column="EnProceso"]');
      const enProceso = !!enProcesoCell?.querySelector('input[type="checkbox"]')?.checked;

      const meta = { telar, salon, cambioHilo, enProceso };
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
      // Si estamos en modo selección múltiple, actualizar visualización de filas bloqueadas
      if (window.multiSelectMode) {
        updateSelectedRowsVisual();
      }
      return window.allRows;
    }

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
        menuRow = row;
        menu.style.left = e.clientX + 'px';
        menu.style.top  = e.clientY + 'px';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth)  menu.style.left = (e.clientX - rect.width) + 'px';
        if (rect.bottom > window.innerHeight) menu.style.top = (e.clientY - rect.height) + 'px';

        menu.classList.remove('hidden');
      }

      if (!menu.dataset.bound) {
        menu.dataset.bound = '1';

        document.addEventListener('click', (e) => {
          if (!menu.classList.contains('hidden') && !menu.contains(e.target)) hide();
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !menu.classList.contains('hidden')) hide();
        });

        // Ocultar menú cuando cambia la selección de fila
        document.addEventListener('pt:selection-changed', () => {
          if (!menu.classList.contains('hidden')) {
            hide();
          }
        });

        const tb = tbodyEl();
        if (tb) {
          tb.addEventListener('contextmenu', (e) => {
            const clickedRow = e.target.closest('.selectable-row');
            if (!clickedRow) return;

            e.preventDefault();

            if (window.selectedRowIndex === null || window.selectedRowIndex === undefined || window.selectedRowIndex < 0) {
              toast('Por favor, selecciona un registro primero haciendo click en una fila', 'info');
              return;
            }

            const rows = window.allRows?.length ? window.allRows : qsa('.selectable-row', tb);
            const selectedRow = rows[window.selectedRowIndex];
            if (!selectedRow) {
              toast('No se pudo encontrar el registro seleccionado', 'error');
              return;
            }

            show(e, selectedRow);
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
          else toast('Edición inline no disponible', 'info');
        });

        // Abrir catálogo de Codificación en nueva ventana
        qs('#contextMenuCodificacion')?.addEventListener('click', () => {
          hide();
          window.open('{{ route("planeacion.codificacion.index") }}', '_blank');
        });

        // Abrir catálogo de Codificación de Modelos en nueva ventana
        qs('#contextMenuModelos')?.addEventListener('click', () => {
          hide();
          window.open('{{ route("planeacion.catalogos.codificacion-modelos") }}', '_blank');
        });
      }

      return { show, hide, getRow: () => menuRow };
    })();

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
      window.location.href = '/planeacion/programa-tejido/nuevo';
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
            sessionStorage.setItem('priorityChangeMessage', 'Registro eliminado correctamente');
            sessionStorage.setItem('priorityChangeType', 'success');
            window.location.href = '/planeacion/programa-tejido';
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

      // Activar edición inline
      if (typeof window.toggleInlineEditMode === 'function') {
        // Verificar si el modo inline ya está activado mirando la clase en el tbody
        const tb = qs('#mainTable tbody');
        const isActive = tb && tb.classList.contains('inline-edit-mode');

        // Si no está activado, activarlo
        if (!isActive) {
          window.toggleInlineEditMode();
        }

        // Activar edición en todas las celdas editables de esta fila
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
        toast('Edición inline no disponible', 'error');
      }
    };

    // =========================
    // Drag & Drop
    // =========================
    PT.dragdrop = PT.dragdrop || (function(){
      const state = {
        enabled: false,
        draggedRow: null,
        origin: { telar:'', salon:'', cambioHilo:'' },
        originalOrderIds: [],
        lastOverRow: null,
        lastOverTelar: null,
        lastDragOverTime: 0,
      };

      function snapshotOrder() {
        const tb = tbodyEl();
        if (!tb) return [];
        return Array.from(tb.querySelectorAll('.selectable-row')).map(r => r.getAttribute('data-id') || '');
      }

      function restoreOriginalOrder() {
        const tb = tbodyEl();
        if (!tb) return;

        const ids = state.originalOrderIds || [];
        if (!ids.length) return;

        const map = new Map();
        qsa('.selectable-row', tb).forEach(r => map.set(r.getAttribute('data-id') || '', r));

        const frag = document.createDocumentFragment();
        ids.forEach(id => { const r = map.get(id); if (r) frag.appendChild(r); });
        map.forEach((r, id) => { if (!ids.includes(id)) frag.appendChild(r); });

        tb.innerHTML = '';
        tb.appendChild(frag);

        refreshAllRows();
        state.originalOrderIds = [];
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

        // Remover listeners de selección antes de activar drag and drop
        window.allRows.forEach((row) => {
          // Remover listener de selección si existe
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

        // Remover todos los listeners anteriores y restaurar los de selección
        window.allRows.forEach((row, i) => {
          row.draggable = false;
          row.classList.remove('cursor-move', 'cursor-not-allowed');
          row.style.opacity = '';

          // Remover listener anterior si existe
          if (row._selectionHandler) {
            row.removeEventListener('click', row._selectionHandler);
            row._selectionHandler = null;
          }

          // Crear nuevo handler para selección (mismo patrón que assignClickEvents)
          row._selectionHandler = function(e) {
            // No seleccionar si estamos en modo inline edit y se hace click en una celda editable
            if (typeof inlineEditMode !== 'undefined' && inlineEditMode) {
              const cell = e.target.closest('td[data-column]');
              if (cell) {
                const col = cell.getAttribute('data-column');
                if (col && typeof uiInlineEditableFields !== 'undefined' && uiInlineEditableFields[col]) {
                  // El modo inline manejará este click
                  return;
                }
              }
            }

            // No seleccionar si estamos en modo selección múltiple (ese modo maneja sus propios clicks)
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
        const tb = tbodyEl();
        if (!tb || !state.draggedRow) return state.origin.telar;

        const rows = Array.from(tb.querySelectorAll('.selectable-row'));
        const idx = rows.indexOf(state.draggedRow);
        const prev = idx > 0 ? rows[idx - 1] : null;
        const next = idx < rows.length - 1 ? rows[idx + 1] : null;

        const prevTelar = prev ? rowMeta(prev).telar : '';
        const nextTelar = next ? rowMeta(next).telar : '';
        const originTelar = state.origin.telar;

        if (prevTelar && isSameTelar(prevTelar, originTelar)) return originTelar;
        if (nextTelar && isSameTelar(nextTelar, originTelar)) return originTelar;

        if (prevTelar && nextTelar && isSameTelar(prevTelar, nextTelar)) return prevTelar;
        if (state.lastOverTelar) return state.lastOverTelar;
        return originTelar;
      }

      function getAfterElement(container, y) {
        const elements = (window.allRows || []).filter(r => r && !r.classList.contains('dragging'));
        let closest = { offset: Number.NEGATIVE_INFINITY, element: null };

        for (const child of elements) {
          const box = child.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) closest = { offset, element: child };
        }
        return closest.element;
      }

      function clearVisualRows() {
        (window.allRows || []).forEach(r => r.classList.remove('drag-over', 'drag-over-warning', 'drop-not-allowed'));
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
        state.origin = { telar: meta.telar, salon: meta.salon, cambioHilo: meta.cambioHilo };
        state.originalOrderIds = snapshotOrder();
        state.lastOverRow = null;
        state.lastOverTelar = null;
        state.lastDragOverTime = 0;

        if (typeof window.deselectRow === 'function') window.deselectRow();

        row.classList.add('dragging');
        row.style.opacity = '0.4';

        e.dataTransfer.effectAllowed = 'move';
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

        let targetRow = e.target.closest('.selectable-row');
        if (!targetRow) {
          const rows = Array.from(tb.querySelectorAll('.selectable-row'));
          let closest = null;
          let best = Infinity;
          for (const r of rows) {
            if (r === state.draggedRow) continue;
            const rect = r.getBoundingClientRect();
            const dist = Math.abs(e.clientY - (rect.top + rect.height / 2));
            if (dist < best) { best = dist; closest = r; }
          }
          targetRow = closest;
        }

        if (!targetRow || targetRow === state.draggedRow) return false;

        const targetTelar = rowMeta(targetRow).telar;
        state.lastOverRow = targetRow;
        state.lastOverTelar = targetTelar;

        clearVisualRows();

        if (!isSameTelar(state.origin.telar, targetTelar)) targetRow.classList.add('drag-over-warning');
        else targetRow.classList.add('drag-over');

        const afterEl = getAfterElement(tb, e.clientY);
        if (afterEl == null) tb.appendChild(state.draggedRow);
        else tb.insertBefore(state.draggedRow, afterEl);

        refreshAllRows();
        return false;
      }

      function calcNewPositionWithinTelar(telarId) {
        const tb = tbodyEl();
        if (!tb) return 0;

        const rows = Array.from(tb.querySelectorAll('.selectable-row'));
        const telarRows = rows.filter(r => r !== state.draggedRow && isSameTelar(rowMeta(r).telar, telarId));

        const draggedIdx = rows.indexOf(state.draggedRow);
        let pos = 0;
        for (let i = 0; i < draggedIdx; i++) {
          const r = rows[i];
          if (r !== state.draggedRow && isSameTelar(rowMeta(r).telar, telarId)) pos++;
        }

        let lastEnProceso = -1;
        for (let i = 0; i < telarRows.length; i++) {
          if (rowMeta(telarRows[i]).enProceso) lastEnProceso = i;
        }
        if (lastEnProceso !== -1) pos = Math.max(pos, lastEnProceso + 1);

        return Math.max(0, Math.min(pos, telarRows.length));
      }

      async function onDrop(e) {
        if (!state.enabled) return;
        if (!state.draggedRow) return;

        e.preventDefault();
        e.stopPropagation();

        const registroId = e.dataTransfer.getData('text/plain') || state.draggedRow.getAttribute('data-id');
        if (!registroId) {
          toast('Error: No se pudo obtener el ID del registro', 'error');
          restoreOriginalOrder();
          return false;
        }

        const targetTelar = decideTargetTelarFromDOM();

        if (isSameTelar(targetTelar, state.origin.telar)) {
          const tb = tbodyEl();
          if (!tb) return false;

          refreshAllRows();
          const telarRows = window.allRows.filter(r => isSameTelar(rowMeta(r).telar, state.origin.telar));
          if (telarRows.length < 2) {
            toast('Se requieren al menos dos registros para reordenar la prioridad', 'info');
            restoreOriginalOrder();
            return false;
          }

          const newPos = calcNewPositionWithinTelar(state.origin.telar);

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
              restoreOriginalOrder();
              return false;
            }

            state.originalOrderIds = [];
            toast(`Prioridad actualizada<br>${data.cascaded_records || 0} registro(s) recalculado(s)`, 'success');

            if (typeof window.updateTableAfterDragDrop === 'function') {
              window.updateTableAfterDragDrop(data.detalles, registroId);
            } else {
              sessionStorage.setItem('selectRegistroId', registroId);
              sessionStorage.setItem('scrollToRegistroId', registroId);
              window.location.reload();
            }
          } catch (err) {
            PT.loader.hide();
            toast('Ocurrió un error al procesar la solicitud', 'error');
            restoreOriginalOrder();
          }
          return false;
        }

        const targetPosition = calcNewPositionWithinTelar(targetTelar);
        await procesarMovimientoOtroTelar(registroId, targetTelar, targetPosition);
        return false;
      }

      async function procesarMovimientoOtroTelar(registroId, nuevoTelar, targetPosition) {
        refreshAllRows();

        const sample = window.allRows.find(r => isSameTelar(rowMeta(r).telar, nuevoTelar));
        const nuevoSalon = sample ? rowMeta(sample).salon : '';

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
            restoreOriginalOrder();
            return;
          }

          const confirmacion = await Swal.fire({
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

          if (!confirmacion.isConfirmed) {
            toast('Operación cancelada', 'info');
            restoreOriginalOrder();
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
            restoreOriginalOrder();
            return;
          }

          sessionStorage.setItem('priorityChangeMessage', cambio.message || 'Telar actualizado correctamente');
          sessionStorage.setItem('priorityChangeType', 'success');
          if (cambio.registro_id) {
            sessionStorage.setItem('scrollToRegistroId', cambio.registro_id);
            sessionStorage.setItem('selectRegistroId', cambio.registro_id);
          }

          window.location.href = '/planeacion/programa-tejido';
        } catch (err) {
          PT.loader.hide();
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar el cambio de telar: ' + (err.message || 'Error desconocido'),
            confirmButtonColor: '#dc2626'
          });
          restoreOriginalOrder();
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
        state.lastDragOverTime = 0;
      }

      return { toggle, enable, disable, restoreOriginalOrder, isEnabled };
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
          updateVincularButtonState(); // Actualizar estado según selección
          updateSelectedRowsVisual(); // Actualizar visualización de filas bloqueadas
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
      // (con o sin OrdCompartida) - el primero seleccionado determinará las reglas
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
        // - Si el primer registro NO tiene OrdCompartida y este registro SÍ tiene, no permitir
        // - Si el primer registro SÍ tiene OrdCompartida y este NO tiene, permitir (usará el del primero)
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
          title: '¿Vincular registros?',
          html: mensajeHtml,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sí, vincular',
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

          // Recargar página después de un breve delay para ver los cambios
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          toast(data.message || 'Error al vincular los registros', 'error');
        }
      })
      .catch(err => {
        PT.loader.hide();
        toast('Error al procesar la solicitud: ' + (err.message || 'Error desconocido'), 'error');
      });
    }

    // Integrar selección múltiple en el handler existente
    document.addEventListener('DOMContentLoaded', () => {
        // Interceptar clicks en filas cuando está activo el modo selección múltiple
        const tb = tbodyEl();
        if (tb) {
          tb.addEventListener('click', (e) => {
            if (!window.multiSelectMode) return;

            const row = e.target.closest('.selectable-row');
            if (!row) return;

            // La validación de OrdCompartida se hace en toggleRowSelection
            // Si estamos en modo selección múltiple, manejar la selección
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
      // Filtrar filas visibles - verificar múltiples condiciones
      // IMPORTANTE: Verificar primero la clase filter-hidden ya que es lo que usa el sistema de filtros
      const visibleRows = allRows.filter(row => {
        // 1. PRIMERO: Verificar clase filter-hidden (esto es lo más importante - usado por el sistema de filtros)
        if (row.classList.contains('filter-hidden')) {
          return false;
        }

        // 2. Verificar estilo inline (puede estar oculta por display: none)
        const inlineDisplay = row.style.display;
        if (inlineDisplay === 'none') {
          return false;
        }

        // 3. Verificar estilo computado (más confiable, pero más lento)
        const computedStyle = window.getComputedStyle(row);
        const computedDisplay = computedStyle.display;
        const computedVisibility = computedStyle.visibility;

        if (computedDisplay === 'none' || computedVisibility === 'hidden') {
          return false;
        }

        // 4. Verificar que el offsetHeight sea mayor a 0 (otra forma de verificar visibilidad)
        // Esto puede ser útil si la fila está fuera del viewport pero aún es visible
        // Comentado porque puede dar falsos negativos si la fila está fuera del viewport
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
          // Si data-value está vacío o no existe, usar textContent
          if (!pedidoValue || pedidoValue === '' || pedidoValue === 'null') {
            pedidoValue = (pedidoCell.textContent || pedidoCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numéricos excepto punto y signo negativo
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
          // Si data-value está vacío o no existe, usar textContent
          if (!produccionValue || produccionValue === '' || produccionValue === 'null') {
            produccionValue = (produccionCell.textContent || produccionCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numéricos excepto punto y signo negativo
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
          // Si data-value está vacío o no existe, usar textContent
          if (!saldosValue || saldosValue === '' || saldosValue === 'null') {
            saldosValue = (saldosCell.textContent || saldosCell.innerText || '0').trim();
          }
          // Limpiar el valor: quitar comas, espacios, caracteres no numéricos excepto punto y signo negativo
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
        window.location.href = `/planeacion/programa-tejido/${encodeURIComponent(id)}/editar`;
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
        else toast('Error: No se pudo abrir el modal. Por favor recarga la página.', 'error');
      };

      qs('#btn-ver-lineas')?.addEventListener('click', openLines);
      qs('#layoutBtnVerLineas')?.addEventListener('click', openLines);

      qs('#btnResetColumns')?.addEventListener('click', resetAllView);
      qs('#btnResetColumnsMobile')?.addEventListener('click', resetAllView);
    }

    // =========================
    // Restaurar selección
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
      // Solo agregar amarillo temporal si no está en modo edición inline (muy breve, 500ms)
      if (!inlineEditMode && !window.inlineEditMode) {
        targetRow.classList.add('bg-yellow-100');
        window.yellowHighlightTimeout = setTimeout(() => {
          // Solo quitar si no está en modo edición inline y no hay inputs editando
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
    // Integración filtro layout
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
              const cell = tr.querySelector(`[data-column="${CSS.escape(col)}"]`);
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
      if (typeof window.initializeColumnVisibility === 'function') window.initializeColumnVisibility();

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
                      // El modo inline manejará este click
                      return;
                    }
                  }
                }

                e.stopPropagation();
                if (typeof window.selectRow === 'function') {
                  window.selectRow(row, i);
                } else {
                  console.warn('window.selectRow no está disponible. Verifica que selection.blade.php se haya cargado.');
                }
              };

              // Asignar evento con addEventListener (permite múltiples listeners)
              row.addEventListener('click', row._selectionHandler);
            });
          }
        };

        // Intentar asignar eventos inmediatamente
        assignClickEvents();

        // Reintentar después de un breve delay para asegurar que los scripts se hayan cargado
        setTimeout(assignClickEvents, 100);
        setTimeout(assignClickEvents, 500);
      }

      setTimeout(() => setDragDropButtonGray(!!window.dragDropMode), 0);

      window.addEventListener('resize', () => { if (typeof window.updatePinnedColumnsPositions === 'function') window.updatePinnedColumnsPositions(); });

      bindLayoutButtons();
      restoreSelectionAfterReload();
      // Actualizar estado del botón balancear después de restaurar selección
      // (también se actualizará automáticamente por el evento pt:selection-changed)
      setTimeout(() => updateBalanceBtnState(), 100);
      // Inicializar estado del botón vincular (solo si está en modo selección múltiple)
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

          // Verificar si está deshabilitado (no está en proceso)
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
                // Asegurar que el checkbox esté visualmente desmarcado
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

          // Si no está marcado, mostrar modal
          if (typeof Swal === 'undefined') {
            toast('SweetAlert no está disponible', 'error');
            checkbox.checked = false;
            return;
          }

          // Asegurar que el checkbox no esté marcado antes de mostrar el modal
          checkbox.checked = false;

          const resultado = await Swal.fire({
            title: 'Seleccionar Reprogramar',
            html: `
              <div class="text-left">
                <p class="mb-4 text-sm text-gray-600">Selecciona una opción:</p>
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

          // Si se canceló el modal, no hacer nada (el checkbox ya no estará marcado)
          if (resultado.dismiss === Swal.DismissReason.cancel || resultado.dismiss === Swal.DismissReason.backdrop) {
            checkbox.checked = false;
          }
        }, true); // Usar capture phase para capturar antes que otros listeners
      }

      // Inicializar listeners - se ejecutará desde el init principal
      window.initReprogramarListeners = initReprogramarListeners;
    })();

  })();
</script>
@endpush
