@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $pageTitle ?? 'Programa de Tejido')

@section('content')
<div class="w-full pt-page">
  <div class="bg-white overflow-hidden w-full pt-page-card">

    @php
      $columns   = $columns ?? [];
      $registros = $registros ?? collect();

      $getRegistroId = function($registro) {
        return $registro->Id ?? $registro->id ?? '';
      };

      $formatValue = function($registro, $field, $dateType = null) use ($getRegistroId) {
        $value = $registro->{$field} ?? null;

        if ($field === 'Reprogramar') {
          $registroId = $getRegistroId($registro);
          $valorActual = $value ?? '';
          $checked = ($valorActual == '1' || $valorActual == '2') ? 'checked' : '';
          $textoMostrar = '';
          if ($valorActual == '1') {
            $textoMostrar = 'P. Siguiente';
          } elseif ($valorActual == '2') {
            $textoMostrar = 'P. Ultima';
          }
          // Verificar si esta en proceso
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
          if ($sv === 'UL') return '<strong>ULTIMO</strong>';
          if ($sv === '1' || $value === 1 || $value === '1') return '<strong>ULTIMO</strong>';
          if ($sv === '0' || $value === 0) return '';
        }

        if ($field === 'CambioHilo') {
          if ($value === '0' || $value === 0) return '';
        }

        if ($field === 'EficienciaSTD' && is_numeric($value)) {
          return round(((float)$value) * 100) . '%';
        }

        // Formatear PTvsCte (Dif vs Compromiso) como entero con redondeo
        if ($field === 'PTvsCte' && is_numeric($value)) {
          $valorFloat = (float)$value;
          // Obtener la parte entera (truncar hacia cero)
          $parteEntera = (int)$valorFloat;
          // Calcular la parte decimal absoluta de forma más precisa
          $parteDecimal = abs($valorFloat - $parteEntera);

          // Si la parte decimal es mayor a 0.50, redondear hacia arriba
          if ($parteDecimal > 0.50) {
            // Redondear hacia arriba: para positivos usar ceil, para negativos usar floor
            if ($valorFloat >= 0) {
              return (string)(int)ceil($valorFloat);
            } else {
              return (string)(int)floor($valorFloat);
            }
          } else {
            // Si es <= 0.50, truncar (hacia cero)
            return (string)$parteEntera;
          }
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
      <div class="overflow-x-auto pt-table-wrapper">
        <div class="overflow-y-auto pt-table-scroll" style="position: relative;">
          <table id="mainTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-blue-500 text-white " style="position: sticky; top: 0; z-index: 10;">
              <tr>
                @foreach($columns as $index => $col)
                  <th
                    class="px-2 py-1 text-left text-xs font-semibold text-white whitespace-nowrap column-{{ $index }}"
                    style="position: sticky; top: 0; background-color: #1b4a7b; min-width: 80px; z-index: 10;"
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
                  $noExisteBase = $registro->NoExisteBase ?? null;
                  $tieneNoExisteBase = !empty($noExisteBase) && ($noExisteBase !== '0' && $noExisteBase !== 0 && $noExisteBase !== false);
                  $rowId = $getRegistroId($registro);
                @endphp
                <tr
                  class="hover:bg-blue-50 cursor-pointer selectable-row"
                  data-row-index="{{ $index }}"
                  data-id="{{ $rowId }}"
                  data-posicion="{{ e($registro->Posicion ?? '') }}"
                  @if(!empty($registro->OrdCompartida)) data-ord-compartida="{{ $registro->OrdCompartida }}" @endif
                  @if($esRepaso) data-es-repaso="1" @endif
                  @if($tieneNoExisteBase) data-no-existe-base="1" @endif
                >
                  @foreach($columns as $colIndex => $col)
                    @php
                      $rawValue = $registro->{$col['field']} ?? '';
                      if ($rawValue instanceof \Carbon\Carbon) {
                        $rawValue = $rawValue->format('Y-m-d H:i:s');
                      }
                      // Detectar valores negativos en PTvsCte (Dif vs Compromiso)
                      $esNegativo = false;
                      if ($col['field'] === 'PTvsCte' && $rawValue !== null && $rawValue !== '') {
                        $valorNumerico = is_numeric($rawValue) ? (float)$rawValue : 0;
                        $esNegativo = $valorNumerico < 0;
                      }
                    @endphp
                    <td
                      class="px-3 py-2 text-sm text-gray-700 {{ ($col['dateType'] ?? null) ? 'whitespace-normal' : 'whitespace-nowrap' }} column-{{ $colIndex }} {{ $esNegativo ? 'valor-negativo' : '' }}"
                      data-column="{{ $col['field'] }}"
                      data-value="{{ e(is_scalar($rawValue) ? $rawValue : json_encode($rawValue)) }}"
                      @if($esNegativo) data-es-negativo="1" @endif
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
      {{-- Eliminar registro --}}
  <button id="contextMenuEliminar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-trash text-red-500"></i>
    <span>Eliminar</span>
  </button>
  {{-- Desvincular registro --}}
  <button id="contextMenuDesvincular" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 flex items-center gap-2">
    <i class="fas fa-unlink text-purple-500"></i>
    <span>Desvincular</span>
  </button>

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

{{-- Menú contextual para encabezados de columnas --}}
<div id="contextMenuHeader" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
  <button id="contextMenuHeaderFiltrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-filter text-yellow-500"></i>
    <span>Filtrar</span>
  </button>
  <button id="contextMenuHeaderFijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
    <i class="fas fa-thumbtack text-blue-500"></i>
    <span>Fijar</span>
  </button>
  <button id="contextMenuHeaderOcultar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-eye-slash text-red-500"></i>
    <span>Ocultar</span>
  </button>
</div>

{{-- OJO: EL JS de duplicar/dividir NO VA AQUÍ (si lo incluyes aquí se imprime) --}}

<style>
  :root {
    --pt-navbar-height: 64px;
  }

  main {
    padding-top: 0 !important;
    position: fixed;
    top: var(--pt-navbar-height);
    bottom: 0;
    left: 0;
    right: 0;
    height: auto !important;
    max-height: none !important;
    overflow: hidden !important;
  }

  .pt-page,
  .pt-page-card {
    height: 100%;
  }

  .pt-page-card {
    display: flex;
    flex-direction: column;
  }

  .pt-table-wrapper {
    flex: 1 1 auto;
    min-height: 0;
  }

  .pt-table-scroll {
    height: 100%;
    max-height: none;
    min-height: 0;
  }

  .pinned-column { position: sticky !important; background-color: #3a6faf !important; color: #fff !important; }
  
  /* Restaurar estilos por defecto del header cuando NO está fijado - forzar reset */
  thead th:not(.pinned-column) {
    background-color: #1b4a7b !important;
    color: #fff !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
  }
  
  /* Restaurar estilos por defecto de las celdas cuando NO están fijadas - forzar reset */
  tbody td:not(.pinned-column) {
    background-color: transparent !important;
    color: #374151 !important;
    position: static !important;
    left: auto !important;
    z-index: auto !important;
  }
  
  /* Asegurar que las celdas con estilos especiales se restauren cuando no están fijadas */
  tbody td:not(.pinned-column)[data-column="EficienciaSTD"],
  tbody td:not(.pinned-column)[data-column="VelocidadSTD"],
  tbody td:not(.pinned-column)[data-column="FibraRizo"],
  tbody td:not(.pinned-column)[data-column="CalibrePie2"],
  tbody td:not(.pinned-column)[data-column="TotalPedido"],
  tbody td:not(.pinned-column)[data-column="PorcentajeSegundos"],
  tbody td:not(.pinned-column)[data-column="Produccion"],
  tbody td:not(.pinned-column)[data-column="SaldoPedido"],
  tbody td:not(.pinned-column)[data-column="SaldoMarbete"],
  tbody td:not(.pinned-column)[data-column="ProgramarProd"],
  tbody td:not(.pinned-column)[data-column="NoProduccion"],
  tbody td:not(.pinned-column)[data-column="NombreProyecto"],
  tbody td:not(.pinned-column)[data-column="CustName"],
  tbody td:not(.pinned-column)[data-column="AplicacionId"],
  tbody td:not(.pinned-column)[data-column="Observaciones"],
  tbody td:not(.pinned-column)[data-column="TipoPedido"],
  tbody td:not(.pinned-column)[data-column="EntregaProduc"],
  tbody td:not(.pinned-column)[data-column="EntregaPT"] {
    background-color: #fff4c2 !important;
    color: #000 !important;
  }

  /* Estilos para fila seleccionada - color más fuerte y mate */
  .selectable-row.bg-blue-700 {
    background-color: #1e3a5f !important;
  }
  .selectable-row.bg-blue-700 td {
    background-color: #1e3a5f !important;
    color: #fff !important;
  }
  .selectable-row.bg-blue-400 {
    background-color: #60a5fa !important;
  }
  .selectable-row.bg-blue-400 td {
    background-color: #60a5fa !important;
    color: #fff !important;
  }

  /* Columnas amarillas más fuertes cuando la fila está seleccionada */
  .selectable-row.bg-blue-700 td[data-column="EficienciaSTD"],
  .selectable-row.bg-blue-400 td[data-column="EficienciaSTD"],
  .selectable-row.bg-blue-700 td[data-column="VelocidadSTD"],
  .selectable-row.bg-blue-400 td[data-column="VelocidadSTD"],
  .selectable-row.bg-blue-700 td[data-column="FibraRizo"],
  .selectable-row.bg-blue-400 td[data-column="FibraRizo"],
  .selectable-row.bg-blue-700 td[data-column="CalibrePie2"],
  .selectable-row.bg-blue-400 td[data-column="CalibrePie2"],
  .selectable-row.bg-blue-700 td[data-column="TotalPedido"],
  .selectable-row.bg-blue-400 td[data-column="TotalPedido"],
  .selectable-row.bg-blue-700 td[data-column="PorcentajeSegundos"],
  .selectable-row.bg-blue-400 td[data-column="PorcentajeSegundos"],
  .selectable-row.bg-blue-700 td[data-column="Produccion"],
  .selectable-row.bg-blue-400 td[data-column="Produccion"],
  .selectable-row.bg-blue-700 td[data-column="SaldoPedido"],
  .selectable-row.bg-blue-400 td[data-column="SaldoPedido"],
  .selectable-row.bg-blue-700 td[data-column="SaldoMarbete"],
  .selectable-row.bg-blue-400 td[data-column="SaldoMarbete"],
  .selectable-row.bg-blue-700 td[data-column="ProgramarProd"],
  .selectable-row.bg-blue-400 td[data-column="ProgramarProd"],
  .selectable-row.bg-blue-700 td[data-column="NoProduccion"],
  .selectable-row.bg-blue-400 td[data-column="NoProduccion"],
  .selectable-row.bg-blue-700 td[data-column="NombreProyecto"],
  .selectable-row.bg-blue-400 td[data-column="NombreProyecto"],
  .selectable-row.bg-blue-700 td[data-column="CustName"],
  .selectable-row.bg-blue-400 td[data-column="CustName"],
  .selectable-row.bg-blue-700 td[data-column="AplicacionId"],
  .selectable-row.bg-blue-400 td[data-column="AplicacionId"],
  .selectable-row.bg-blue-700 td[data-column="Observaciones"],
  .selectable-row.bg-blue-400 td[data-column="Observaciones"],
  .selectable-row.bg-blue-700 td[data-column="TipoPedido"],
  .selectable-row.bg-blue-400 td[data-column="TipoPedido"],
  .selectable-row.bg-blue-700 td[data-column="EntregaProduc"],
  .selectable-row.bg-blue-400 td[data-column="EntregaProduc"],
  .selectable-row.bg-blue-700 td[data-column="EntregaPT"],
  .selectable-row.bg-blue-400 td[data-column="EntregaPT"] {
    background-color: #ffd700 !important;
    color: #000 !important;
  }

  /* Columnas fijadas más fuertes cuando la fila está seleccionada */
  .selectable-row.bg-blue-700 td.pinned-column {
    background-color: #2d5aa0 !important;
    color: #fff !important;
  }
  .selectable-row.bg-blue-400 td.pinned-column {
    background-color: #3b82f6 !important;
    color: #fff !important;
  }

  /* Asegurar que el thead completo se mantenga visible */
  thead {
    z-index: 10 !important; /* Base para todos los encabezados */
  }

  /* Asegurar que los encabezados de columnas fijadas se mantengan visibles al hacer scroll */
  thead th.pinned-column {
    position: sticky !important;
    background-color: #1b4a7b !important;
    color: #fff !important;
    z-index: 20 !important; /* Mayor que las celdas pero menor que modales (z-50) */
    padding: 0.5rem 0.75rem !important;
  }

  /* Asegurar que las celdas de columnas fijadas también tengan z-index apropiado */
  tbody td.pinned-column {
    z-index: 1 !important; /* Menor que el header para que el header siempre esté encima */
  }

  /* Estilo para columnas fijadas en filas REPASO - rojo pastel */
  tr[data-es-repaso="1"] .pinned-column {
    background-color: #ff9090 !important;
    color: #000 !important;
  }

  /* Estilo para columnas fijadas en filas con NoExisteBase (Usar cuando no existe en base) - rojo pastel */
  tr[data-no-existe-base="1"] .pinned-column {
    background-color: #ff9090 !important;
    color: #000 !important;
  }

  /* Estilo para valores negativos en columna Dif vs Compromiso (PTvsCte) - rojo pastel */
  td[data-column="PTvsCte"][data-es-negativo="1"],
  td.valor-negativo[data-column="PTvsCte"] {
    background-color: #ffe5e5 !important;
    color: #dc2626 !important; /* Texto rojo para valores negativos */
  }

  /* Estilo para columnas ef std, vel, hilo, calibre pie, pedido, % segundas, producción, saldos, saldo marbetes, day scheduling, orden prod, descrip, aplic, obs, tipo ped, fecha compromiso - amarillo pastel solo en celdas */
  td[data-column="EficienciaSTD"],
  td[data-column="VelocidadSTD"],
  td[data-column="FibraRizo"],
  td[data-column="CalibrePie2"],
  td[data-column="TotalPedido"],
  td[data-column="PorcentajeSegundos"],
  td[data-column="Produccion"],
  td[data-column="SaldoPedido"],
  td[data-column="SaldoMarbete"],
  td[data-column="ProgramarProd"],
  td[data-column="NoProduccion"],
  td[data-column="NombreProyecto"],
  td[data-column="CustName"],
  td[data-column="AplicacionId"],
  td[data-column="Observaciones"],
  td[data-column="TipoPedido"],
  td[data-column="EntregaProduc"],
  td[data-column="EntregaPT"] {
    background-color: #fff4c2 !important;
    color: #000 !important;
  }

  /* Asegurar que el amarillo pastel se aplique incluso cuando las columnas están fijadas */
  td[data-column="EficienciaSTD"].pinned-column,
  td[data-column="VelocidadSTD"].pinned-column,
  td[data-column="FibraRizo"].pinned-column,
  td[data-column="CalibrePie2"].pinned-column,
  td[data-column="TotalPedido"].pinned-column,
  td[data-column="PorcentajeSegundos"].pinned-column,
  td[data-column="Produccion"].pinned-column,
  td[data-column="SaldoPedido"].pinned-column,
  td[data-column="SaldoMarbete"].pinned-column,
  td[data-column="ProgramarProd"].pinned-column,
  td[data-column="NoProduccion"].pinned-column,
  td[data-column="NombreProyecto"].pinned-column,
  td[data-column="CustName"].pinned-column,
  td[data-column="AplicacionId"].pinned-column,
  td[data-column="Observaciones"].pinned-column,
  td[data-column="TipoPedido"].pinned-column,
  td[data-column="EntregaProduc"].pinned-column,
  td[data-column="EntregaPT"].pinned-column {
    background-color: #fff4c2 !important;
    color: #000 !important;
  }

  /* Asegurar ancho suficiente para NombreProyecto y CustName */
  th[data-column="NombreProyecto"],
  td[data-column="NombreProyecto"] {
    min-width: 120px !important;
  }
  th[data-column="CustName"],
  td[data-column="CustName"] {
    min-width: 100px !important;
  }

  /* Los encabezados (th) mantienen el azul por defecto */
  th[data-column="EficienciaSTD"],
  th[data-column="VelocidadSTD"],
  th[data-column="FibraRizo"],
  th[data-column="CalibrePie2"],
  th[data-column="TotalPedido"],
  th[data-column="PorcentajeSegundos"],
  th[data-column="Produccion"],
  th[data-column="SaldoPedido"],
  th[data-column="SaldoMarbete"],
  th[data-column="ProgramarProd"],
  th[data-column="NoProduccion"],
  th[data-column="NombreProyecto"],
  th[data-column="CustName"],
  th[data-column="AplicacionId"],
  th[data-column="Observaciones"],
  th[data-column="TipoPedido"],
  th[data-column="EntregaProduc"],
  th[data-column="EntregaPT"] {
    background-color: #1b4a7b !important;
    color: #fff !important;
  }

  .cursor-move { cursor: move !important; }
  .cursor-not-allowed { cursor: not-allowed !important; opacity: 0.6; }

  .selectable-row.dragging { opacity: 0.25; background-color: #f8fafc !important; box-shadow: inset 0 0 0 1px #e5e7eb; }
  .selectable-row.drag-over { --dd-line-color: #2563eb; background-color: #f8fafc; }
  .selectable-row.drag-over-warning { --dd-line-color: #d97706; background-color: #fff7ed; }
  .selectable-row.drop-not-allowed { --dd-line-color: #dc2626; background-color: #fef2f2; cursor: not-allowed !important; }
  .selectable-row.dd-drop-before td { box-shadow: inset 0 2px 0 0 var(--dd-line-color); }
  .selectable-row.dd-drop-after td { box-shadow: inset 0 -2px 0 0 var(--dd-line-color); }

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
    box-shadow: 0 0 0 1px #5688f533;
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
  {!! view('modulos.programa-tejido.scripts.main', [
    'columns' => $columns ?? [],
    'basePath' => $basePath ?? null,
    'apiPath' => $apiPath ?? null,
    'linePath' => $linePath ?? null,
  ])->render() !!}
@endpush
