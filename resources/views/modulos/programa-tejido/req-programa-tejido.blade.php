@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $pageTitle ?? 'Programa de Tejido')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-recalcular-fechas');
    if (!btn) return;

    const url = @json($isMuestras ?? false
        ? route('muestras.recalcular-fechas')
        : route('programa-tejido.recalcular-fechas'));

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.querySelector('i').classList.add('fa-spin');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                Swal.fire({ icon: 'success', title: 'Listo', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error de conexión' }))
        .finally(() => {
            btn.disabled = false;
            btn.querySelector('i').classList.remove('fa-spin');
        });
    });
});
</script>
@endpush

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

{{-- Modal Crear Repaso --}}
@include('modulos.programa-tejido.modal.repaso')

{{-- Permisos del módulo para menú contextual --}}
@php
  $moduloPT = 'Programa Tejido';
  $canCrear = function_exists('userCan') ? userCan('crear', $moduloPT) : true;
  $canModificar = function_exists('userCan') ? userCan('modificar', $moduloPT) : true;
  $canEliminar = function_exists('userCan') ? userCan('eliminar', $moduloPT) : true;
@endphp

{{-- Menú contextual --}}
<div id="contextMenu" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
  @if($canCrear)
  <button id="contextMenuCrear" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-plus-circle text-blue-500"></i>
    <span>Crear</span>
  </button>
  <button id="contextMenuRepaso" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-redo text-blue-500"></i>
    <span>Crear Repaso</span>
  </button>
  @endif
  @if($canModificar)
  <button id="contextMenuEditar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-pen text-yellow-500"></i>
    <span>Editar fila</span>
  </button>
  @endif
  @if($canEliminar)
  <button id="contextMenuEliminar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-trash text-red-500"></i>
    <span>Eliminar</span>
  </button>
  <button id="contextMenuEliminarEnProceso" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-stop-circle text-red-700"></i>
    <span>Eliminar en proceso</span>
  </button>
  @endif
  @if($canModificar)
  <button id="contextMenuDesvincular" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 flex items-center gap-2">
    <i class="fas fa-unlink text-purple-500"></i>
    <span>Desvincular</span>
  </button>
  @endif
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

<link rel="stylesheet" href="{{ asset('css/programa-tejido/main.css') }}">

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
