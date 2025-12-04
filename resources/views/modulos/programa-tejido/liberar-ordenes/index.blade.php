@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create
        onclick="liberarOrdenes()"
        title="Liberar"
        text="Liberar"
        module="Programa Tejido"
        icon="fa-unlock"
        bg="bg-green-500"
        iconColor="text-white"
        hoverBg="hover:bg-green-600"
    />
</div>
@endsection

@section('page-title', 'Liberar Órdenes')

@section('content')
<div class="w-full">
    <div class="bg-white shadow overflow-hidden w-full rounded-lg">
        @php
        $columns = [
            ['field' => 'select', 'label' => 'Seleccionar'],
            ['field' => 'prioridad', 'label' => 'Prioridad'],
            ['field' => 'CuentaRizo', 'label' => 'Cuenta'],
            ['field' => 'SalonTejidoId', 'label' => 'Salon'],
            ['field' => 'NoTelarId', 'label' => 'Telar'],
            ['field' => 'Ultimo', 'label' => 'Ultimo'],
            ['field' => 'CambioHilo', 'label' => 'Cambios Hilo'],
            ['field' => 'Maquina', 'label' => 'Maq'],
            ['field' => 'Ancho', 'label' => 'Ancho'],
            ['field' => 'EficienciaSTD', 'label' => 'Ef Std'],
            ['field' => 'VelocidadSTD', 'label' => 'Vel'],
            ['field' => 'FibraRizo', 'label' => 'Hilo'],
            ['field' => 'CalibrePie2', 'label' => 'Calibre Pie'],
            ['field' => 'CalendarioId', 'label' => 'Jornada'],
            ['field' => 'TamanoClave', 'label' => 'Clave mod.'],
            ['field' => 'NoExisteBase', 'label' => 'Usar cuando no existe en base'],
            ['field' => 'NombreProducto', 'label' => 'Producto'],
            ['field' => 'SaldoPedido', 'label' => 'Saldos'],
            ['field' => 'ProgramarProd', 'label' => 'Day Sheduling'],
            ['field' => 'NoProduccion', 'label' => 'Orden Prod.'],
            ['field' => 'Programado', 'label' => 'INN'],
            ['field' => 'NombreProyecto', 'label' => 'Descrip.'],
            ['field' => 'AplicacionId', 'label' => 'Aplic.'],
            ['field' => 'Observaciones', 'label' => 'Obs'],
            ['field' => 'FechaInicio', 'label' => 'Fecha Inicio'],
            ['field' => 'FechaFinal', 'label' => 'Fecha Fin'],
        ];

        $formatValue = function($registro, $field) {
            if ($field === 'select') {
                return '<input type="checkbox" class="row-checkbox w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mx-auto block" data-id="' . ($registro->Id ?? '') . '">';
            }

            if ($field === 'prioridad') {
                // Input editable para Prioridad
                $prioridad = $registro->Prioridad ?? '';
                $id = $registro->Id ?? '';
                return '<input type="text"
                              class="prioridad-input w-full px-3 py-2 text-base border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                              value="' . htmlspecialchars($prioridad, ENT_QUOTES, 'UTF-8') . '"
                              data-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"
                              placeholder="Prioridad"
                              style="min-width: 280px;">';
            }

            // Columna INN (Programado) - Usar el valor calculado del controlador (verificar ANTES de validar $value)
            if ($field === 'Programado') {
                $programadoCalculado = $registro->ProgramadoCalculado ?? null;
                if ($programadoCalculado && $programadoCalculado instanceof \Carbon\Carbon) {
                    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                    $mes = $meses[$programadoCalculado->month - 1] ?? strtolower($programadoCalculado->format('M'));
                    return $programadoCalculado->format('d') . '-' . $mes . '-' . $programadoCalculado->format('Y');
                }
                return '';
            }

            $value = $registro->{$field} ?? null;
            if ($value === null || $value === '') return '';
            // Mapear '0' como nada para la columna 'Ultimo'
            if ($field === 'Ultimo' && $value === '0') return '';
            // Mapear 'UL' o '1' como "ULTIMO" para la columna 'Ultimo'
            if ($field === 'Ultimo') {
                $sv = strtoupper(trim((string)$value));
                if ($sv === 'UL' || $sv === '1') return 'ULTIMO';
                return $value;
            }

            // Formato de porcentaje para EficienciaSTD
            if ($field === 'EficienciaSTD' && is_numeric($value)) {
                $porcentaje = (float)$value * 100;
                return round($porcentaje) . '%';
            }

            // Formato de fechas (día-mes-año abreviado)
            $fechaCampos = ['ProgramarProd', 'FechaInicio', 'FechaFinal'];
            if (in_array($field, $fechaCampos, true)) {
                try {
                    if ($value instanceof \Carbon\Carbon) {
                        if ($value->year > 1970) {
                            // Formato: 03-nov-2024, 17-nov-2024, etc.
                            $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                            $mes = $meses[$value->month - 1] ?? strtolower($value->format('M'));
                            return $value->format('d') . '-' . $mes . '-' . $value->format('Y');
                        }
                        return '';
                    }
                    $dt = \Carbon\Carbon::parse($value);
                    if ($dt->year > 1970) {
                        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                        $mes = $meses[$dt->month - 1] ?? strtolower($dt->format('M'));
                        return $dt->format('d') . '-' . $mes . '-' . $dt->format('Y');
                    }
                    return '';
                } catch (\Exception $e) {
                    return '';
                }
            }

            // Números con formato (sin decimales para enteros, con comas para miles)
            if (is_numeric($value)) {
                $floatValue = (float)$value;
                // CuentaRizo no debe tener comas
                if ($field === 'CuentaRizo') {
                    if ($floatValue == floor($floatValue)) {
                        return (string)(int)$floatValue;
                    } else {
                        return number_format($floatValue, 2, '.', '');
                    }
                }
                if ($floatValue == floor($floatValue)) {
                    // Es un entero, mostrar sin decimales pero con comas para miles
                    return number_format($floatValue, 0, '.', ',');
                } else {
                    // Tiene decimales
                    return number_format($floatValue, 2, '.', ',');
                }
            }

            return $value;
        };
        @endphp

        @if(isset($error))
            <div class="px-6 py-4 bg-red-100 border-l-4 border-red-500 text-red-700">
                <p class="font-bold">Error</p>
                <p>{{ $error }}</p>
            </div>
        @endif

        @if(isset($registros) && is_countable($registros) && count($registros) > 0)
            <div class="overflow-x-auto">
                <div class="overflow-y-auto" style="max-height: calc(100vh - 80px);">
                    <table id="mainTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                @foreach($columns as $index => $col)
                                <th class="px-2 py-2 text-left text-sm font-semibold text-white whitespace-nowrap"
                                    style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6; min-width: {{ $col['field'] === 'prioridad' ? '300px' : '80px' }};">
                                    {{ $col['label'] }}
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($registros as $index => $registro)
                            <tr class="hover:bg-blue-50 transition-colors" data-id="{{ $registro->Id ?? '' }}">
                                @foreach($columns as $colIndex => $col)
                                <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap {{ $col['field'] === 'select' ? 'text-center' : '' }} {{ $col['field'] === 'prioridad' ? 'px-4 py-3' : '' }}">
                                    {!! $formatValue($registro, $col['field']) !!}
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron registros sin orden de producción.</p>
            </div>
        @endif
    </div>
</div>

<script>
const liberarOrdenesUrl = '{{ route('programa-tejido.liberar-ordenes.procesar') }}';
const redirectAfterLiberar = '{{ route('catalogos.req-programa-tejido') }}';

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.row-checkbox');

    function updateRowStyle(checkbox) {
        const row = checkbox.closest('tr');
        if (!row) return;

        if (checkbox.checked) {
            row.classList.add('bg-blue-500', 'text-white');
            row.classList.remove('hover:bg-blue-50');
            row.querySelectorAll('td').forEach(td => {
                td.classList.add('text-white');
                td.classList.remove('text-gray-700');
            });
        } else {
            row.classList.remove('bg-blue-500', 'text-white');
            row.classList.add('hover:bg-blue-50');
            row.querySelectorAll('td').forEach(td => {
                td.classList.remove('text-white');
                td.classList.add('text-gray-700');
            });
        }
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateRowStyle(this);
        });
    });
});

function obtenerRegistrosSeleccionados() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => {
        const row = cb.closest('tr');
        const prioridadInput = row ? row.querySelector('.prioridad-input') : null;
        return {
            id: cb.getAttribute('data-id'),
            prioridad: prioridadInput ? prioridadInput.value.trim() : ''
        };
    });
}

function descargarExcelBase64(data, fileName) {
    const byteCharacters = atob(data);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const link = document.createElement('a');
    const blobUrl = window.URL.createObjectURL(blob);
    link.href = blobUrl;
    link.download = fileName || 'liberar-ordenes.xlsx';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();

    // Limpiar el blob URL después de un breve delay para evitar la advertencia
    setTimeout(() => {
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    }, 100);
}

function liberarOrdenes() {
    const registros = obtenerRegistrosSeleccionados().filter(r => r.id);

    if (!registros.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin registros',
            text: 'Selecciona al menos un registro para liberar.',
        });
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    Swal.fire({
        title: 'Liberar órdenes',
        html: `Se actualizarán <strong>${registros.length}</strong> registros seleccionados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Liberar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#6b7280',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => {
            return fetch(liberarOrdenesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ registros }),
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Error al liberar las órdenes.');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
            });
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const payload = result.value;
            if (payload.fileData) {
                descargarExcelBase64(payload.fileData, payload.fileName);
            }
            Swal.fire({
                icon: 'success',
                title: 'Órdenes liberadas',
                text: payload.message || 'Se actualizaron los registros seleccionados.',
                confirmButtonText: 'Aceptar',
            }).then(() => {
                window.location.href = payload.redirectUrl || redirectAfterLiberar;
            });
        }
    });
}
</script>
@endsection
